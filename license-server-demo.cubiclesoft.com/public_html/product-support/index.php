<?php
	require_once "../base.php";
	if (file_exists($rootpath . "/secrets.php"))  require_once $rootpath . "/secrets.php";
	require_once $rootpath . "/layout.php";
	require_once $rootpath . "/support/request.php";
	require_once $rootpath . "/support/flex_forms.php";
	require_once $rootpath . "/support/sdk_license_server.php";

	Request::Normalize();

	session_start();

	// Connect to the license server and retrieve version information.
	$lsrv = new LicenseServer();

	$result = $lsrv->Connect();
	if (!$result["success"])  OutputPage("Error", "License Server Unavailable", "<p>The license server is not available at the moment.  Try again later.  If the problem persists, contact " . htmlspecialchars($companyname) . ".</p><p>" . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "</p>");

	$result = $lsrv->GetMajorVersions($productid);
	if (!$result["success"])  OutputPage("Error", "No Versions Available", "<p>The license server returned an unexpected error while retrieving version information.  Try again later.  If the problem persists, contact " . htmlspecialchars($companyname) . ".</p><p>" . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "</p>");
	$productname = $result["product_name"];
	$versions = $result["versions"];

	$ff = new FlexForms();
	$ff->SetState(array("supporturl" => "/support"));

	// Reset session serial info if defined.
	if (isset($_REQUEST["serial"]) || isset($_REQUEST["userinfo"]) || isset($_REQUEST["password"]))  unset($_SESSION["serialinfo"]);

	$errors = array();
	$message = "";

	function LoadMajorVersionMap()
	{
		global $lsrv, $productid, $rootpath, $companyname;

		// Retrieve all licenses and user order history.
		$result = $lsrv->GetLicenses(false, $_SESSION["serialinfo"]["userinfo"], $productid, -1, array("revoked" => false));
		if (!$result["success"])  OutputPage("Error", "License Retrieval Failed", "<p>Unable to retrieve licenses.  Try again later.  If the problem persists, contact " . htmlspecialchars($companyname) . ".</p><p>" . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "</p>");

		$licenses = $result["licenses"];

		// Prepare to map in whether or not order history exists.
		$result = $lsrv->GetHistory(false, $_SESSION["serialinfo"]["userinfo"], $productid, -1, "created");
		if (!$result["success"])  OutputPage("Error", "History Retrieval Failed", "<p>Unable to retrieve license history.  Try again later.  If the problem persists, contact " . htmlspecialchars($companyname) . ".</p><p>" . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "</p>");

		$entrymap = array();
		foreach ($result["entries"] as $entry)
		{
			$key = json_encode(array($entry["serial_num"], $entry["product_id"], $entry["major_ver"], $entry["userinfo"]), JSON_UNESCAPED_SLASHES);

			$entrymap[$key] = $entry["id"];
		}

		// Sort by major version in descending order.
		$versions = array();
		$ts = time();
		foreach ($licenses as $license)
		{
			if (!isset($versions[$license["major_ver"]]))
			{
				$versions[$license["major_ver"]] = array();

				$filename = $rootpath . "/../protected_html/downloads/v" . $license["major_ver"] . "/info.json";

				if (file_exists($filename))
				{
					$versions[$license["major_ver"]]["downloads"] = @json_decode(file_get_contents($filename), true);
					$versions[$license["major_ver"]]["lastupdated"] = filemtime($filename);
				}

				$versions[$license["major_ver"]]["can_download"] = false;
				$versions[$license["major_ver"]]["exp_licenses"] = array();
				$versions[$license["major_ver"]]["perm_licenses"] = array();
			}

			$key = json_encode(array($license["serial_num"], $license["product_id"], $license["major_ver"], $license["userinfo"]), JSON_UNESCAPED_SLASHES);

			$license["log_id"] = (isset($entrymap[$key]) ? $entrymap[$key] : false);

			if ($license["serial_info"]["expires"])
			{
				$versions[$license["major_ver"]]["exp_licenses"][$license["serial_info"]["date"]] = $license;
				if ($license["serial_info"]["date"] > $ts)  $versions[$license["major_ver"]]["can_download"] = true;
			}
			else
			{
				$versions[$license["major_ver"]]["perm_licenses"][] = $license;
				$versions[$license["major_ver"]]["can_download"] = true;
			}
		}

		krsort($versions);

		return $versions;
	}

	if (isset($_SESSION["serialinfo"]))
	{
		// Signed in.
		if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "viewreceipt" && isset($_REQUEST["ordernum"]))
		{
			OutputBasicHeader("Product Support Center", "Product Support Center");

?>
<p>Signed in as <b><?=htmlspecialchars($_SESSION["serialinfo"]["userinfo"])?></b> | <a href="/product-support/?serial=&ts=<?=time()?>">Logout</a></p>

<h2>Receipt for Order #<?=htmlspecialchars($_REQUEST["ordernum"])?></h2>
<?php

			// Load the license from the order number.
			$orderinfo = $lsrv->ExtractOrderNumberInfo($_REQUEST["ordernum"]);
			if ($orderinfo === false)  echo "<p>Invalid order number.</p>";
			else
			{
				$result = $lsrv->GetLicenses(false, $_SESSION["serialinfo"]["userinfo"], -1, -1, array("revoked" => false, "created" => $orderinfo["created"], "order_num" => $orderinfo["order_num"]));
				if (!$result["success"])  echo "<p>An error occurred while loading the order information.  " . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "</p>";
				else if (count($result["licenses"]) != 1)  echo "<p>Order information not found.</p>";
				else
				{
					$license = $result["licenses"][0];

					$result2 = $lsrv->GetHistory($license["serial_num"], $license["userinfo"], $license["product_id"], $license["major_ver"], "created");
					if (!$result2["success"])  echo "<p>An error occurred while loading the order information.  " . htmlspecialchars($result2["error"] . " (" . $result2["errorcode"] . ")") . "</p>";
					else if (count($result2["entries"]) != 1)  echo "<p>Order information not found.</p>";
					else
					{
						$info = @json_decode($result2["entries"][0]["info"], true);
						if (!is_array($info) || !isset($info["payment_processor"]))  echo "<p>Order information not found.</p>";
						else
						{
?>
<table class="receiptitems">
<?php
							foreach ($info["items"] as $item)
							{
								echo "\t<tr><td>" . htmlspecialchars($item["disp"]) . ($item["qty"] > 1 ? "<br><span class=\"quantity_unit\">" . htmlspecialchars($item["qty"] . " x \$" . number_format($item["unit_cost"], 2) . " " . $item["currency"]) . "</span>" : "") . "</td>";
								echo "<td>" . htmlspecialchars("\$" . number_format($item["qty"] * $item["unit_cost"], 2) . " " . $item["currency"]) . "</td></tr>\n";
							}
?>
	<tr class="total"><td>Total:</td><td><?=htmlspecialchars("\$" . number_format($info["total"], 2) . " " . $info["total_currency"])?></td></tr>
</table>

<h3 style="margin-bottom: 0;">Purchased</h3>
<p style="margin-top: 0;"><?=date("F j, Y", $result2["entries"][0]["created"])?> at <?=date("g:i a", $result2["entries"][0]["created"])?> MST</p>

<h3 style="margin-bottom: 0;">Billing Address</h3>
<p style="margin-top: 0;">
<?=htmlspecialchars($info["billing_info"]["name"])?><br>
<?=htmlspecialchars($info["billing_info"]["address"]["line1"])?><br>
<?=htmlspecialchars($info["billing_info"]["address"]["city"] . ", " . $info["billing_info"]["address"]["state"] . " " . $info["billing_info"]["address"]["postal_code"])?>
</p>
<?php
						}
					}
				}
			}

			OutputBasicFooter();
		}
		else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "livechat" && isset($discord_webhook) && $discord_webhook !== false && $discord_bottoken !== false && $discord_channelid !== false)
		{
			// Load and map major versions, licenses, and order history.
			$versions = LoadMajorVersionMap();

			$hassupport = false;
			foreach ($versions as $majorver => $vinfo)
			{
				if ($vinfo["can_download"] && isset($vinfo["downloads"]))
				{
					$hassupport = true;

					break;
				}
			}

			if (!$hassupport)  OutputPage("Error", "Access Denied", "<p>Access to the help and support system requires a valid, current license.  <a href=\"/buy/\">Renew now</a></p>");

			require_once $rootpath . "/support/sdk_discord.php";

			// Notify license channel of the incoming user.
			$options = array(
				"content" => $_SESSION["serialinfo"]["userinfo"] . " (" . $productname . " v" . $majorver . ")"
			);

			$result = DiscordSDK::SendWebhookMessage($discord_webhook, $options);
			if (!$result["success"])  OutputPage("Error", "Discord Error", "<p>An error occurred while attempting to access Discord.  " . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")</p>"));

			// Create a temporary invite.
			$discord = new DiscordSDK();
			$discord->SetAccessInfo("Bot", $discord_bottoken);

			$options = array(
				"max_age" => 1800,
				"max_uses" => 1,
				"unique" => true,
				"temporary" => true
			);

			$result = $discord->RunAPI("POST", "channels/" . $discord_channelid . "/invites", $options);
			if (!$result["success"])  OutputPage("Error", "Discord Error", "<p>An error occurred while attempting to setup Discord.  " . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")</p>"));

			$url = "https://discord.gg/" . $result["data"]["code"];

			header("Location: " . $url);

			exit();
		}
		else
		{
			// Load and map major versions, licenses, and order history.
			$versions = LoadMajorVersionMap();

			OutputBasicHeader("Product Support Center", "Product Support Center");

?>
<p>Signed in as <b><?=htmlspecialchars($_SESSION["serialinfo"]["userinfo"])?></b> | <a href="/product-support/?serial=&ts=<?=time()?>">Logout</a></p>

<p>Download product updates, open a helpdesk ticket, and view your licenses and purchase history.</p>

<h2>Downloads and Licenses</h2>
<?php
			$ff->OutputJQuery();

			$expiringts = time() + 31 * 24 * 60 * 60;
			$keys = array("perm_licenses", "exp_licenses");
			$hassupport = false;
			foreach ($versions as $majorver => $vinfo)
			{
				if ($vinfo["can_download"] && isset($vinfo["downloads"]))
				{
					echo "<div class=\"downloadswrap\">\n";
					foreach ($vinfo["downloads"]["files"] as $filename => $finfo)
					{
						if ($finfo["32bit"] && $finfo["32bit"])  $bitsextra = "";
						else if ($finfo["32bit"])  $bitsextra = ", 32-bit";
						else  $bitsextra = ", 64-bit";

						echo "<div class=\"downloaditem\"><img src=\"/res/" . htmlspecialchars($finfo["os"]) . "_18x18.png\" class=\"os_icon\" title=\"" . htmlspecialchars($finfo["os_disp"]) . "\"> <a href=\"" . htmlspecialchars($finfo["url"]) . "\">" . htmlspecialchars($filename) . "</a> <span style=\"white-space: nowrap;\">(" . number_format($finfo["size"], 0) . " bytes" . $bitsextra . ")</span></div>\n";
					}
					echo "</div>\n";

?>
<p><span style="white-space: nowrap;">Released <?=date("M j, Y", $vinfo["lastupdated"])?> |</span> <span style="white-space: nowrap;"><a href="/install/">Installation instructions</a> |</span> <span style="white-space: nowrap;"><a href="/product-support/downloads/">File hashes</a></span></p>
<?php

					$hassupport = true;
				}
				else
				{
?>
<p><i>No downloads available.</i>  All <?=htmlspecialchars($productname)?> <?=$majorver?>.x licenses have expired.  <a href="/buy/">Renew now</a></p>
<?php
				}

				krsort($vinfo["exp_licenses"]);

				$num = 0;
				$ts = time();
				foreach ($keys as $key)
				{
					foreach ($vinfo[$key] as $license)
					{
						if ($num == 1)  echo "<div class=\"morewrapper\"><p><a href=\"#\" onclick=\"$(this).closest('.morewrapper').remove(); $('#all_licenses_" . $majorver . "').show(); return false;\">Show all licenses</a></p></div><div id=\"all_licenses_" . $majorver . "\" style=\"display: none;\">";

						$serialinfo = $license["serial_info"];

						if (!$hassupport && $num == 0 && $serialinfo["expires"] && $serialinfo["date"] < $ts)  $ff->OutputMessage("error", "All licenses have expired.  <a href=\"/buy/\">Renew now</a>");
						if ($serialinfo["expires"] && $serialinfo["date"] > $ts && $serialinfo["date"] < $expiringts)  $ff->OutputMessage("info", "License is expiring soon.  Click 'Renew now' below to get the discounted renewal rate.");

						echo "<p style=\"font-size: 0.9em;\"><b>Serial:</b>  " . htmlspecialchars($serialinfo["serial_num"]);
						echo "<br><b>Email:</b> " . htmlspecialchars($serialinfo["userinfo"]);
						if (isset($license["info"]["password"]))  echo "<br><b>Support Password:</b> " . htmlspecialchars($license["info"]["password"]);
						echo "<br><b>Product:</b>  " . $license["product_name"] . " " . htmlspecialchars($serialinfo["major_ver"] . "." . $serialinfo["minor_ver"] . " " . $serialinfo["product_class_name"]);
						echo "<br><b>" . ($serialinfo["expires"] ? ($serialinfo["date"] < $ts ? "Expired" : "Expires") : "Purchased") . ":</b>  " . date("F j, Y", $serialinfo["date"]);
						if (isset($license["info"]["quantity"]) && $license["info"]["quantity"] > 1)  echo "<br><b>Quantity:</b>  " . (int)$license["info"]["quantity"];
						if (isset($license["info"]["extra"]))  echo "<br><b>Details:</b>  " . htmlspecialchars($license["info"]["extra"]);
						if ($serialinfo["expires"] && $serialinfo["date"] > $ts && $serialinfo["date"] < $expiringts)  echo "<br><b>License is expiring soon.</b>";

						$options = array();
						if ($serialinfo["expires"])  $options[] = "<a href=\"/buy/\">Renew now</a>";
						if ($license["log_id"] !== false)  $options[] = "<a href=\"/product-support/?action=viewreceipt&ordernum=" . urlencode($lsrv->GetUserOrderNumber("CSLS", $license["created"], $license["order_num"])) . "\">View receipt</a>";
						if (count($options))  echo "<br>" . implode(" | ", $options);

						echo "</p>";

						if ($num == 1)  echo "</div>";

						$num++;
					}
				}
			}

?>
<h2>Help and Support</h2>
<?php

			if ($hassupport)
			{
				if (isset($discord_webhook) && $discord_webhook !== false && $discord_bottoken !== false && $discord_channelid !== false)
				{
?>
<p><a href="/product-support/?action=livechat" target="_blank">Live Chat</a> with <?=htmlspecialchars($companyname)?> (via Discord) to ask questions, report an issue, or request a new feature.  Alternatively, send an email to <a href="mailto:<?=htmlspecialchars($realemail)?>"><?=htmlspecialchars($displayemail)?></a>.</p>
<?php
				}
				else
				{
?>
<p>If you have questions, want to report an issue, or request a new feature, send an email to <a href="mailto:<?=htmlspecialchars($realemail)?>"><?=htmlspecialchars($displayemail)?></a>.</p>
<?php
				}
?>

<p>Are you a developer and love APIs?  Check out the <a href="https://cubiclesoft.com/product-support-api/" target="_blank">Product Support Center API</a>.</p>
<?php
			}
			else
			{
?>
<p>Access to the helpdesk requires a valid, current license.  <a href="/buy/">Renew now</a></p>
<?php
			}

			OutputBasicFooter();
		}
	}
	else
	{
		// Not signed in.
		if (isset($_REQUEST["userinfo"]) && isset($_REQUEST["serial"]) && isset($_REQUEST["password"]))
		{
			$_REQUEST["userinfo"] = (string)$_REQUEST["userinfo"];
			$_REQUEST["serial"] = (string)$_REQUEST["serial"];
			$_REQUEST["password"] = (string)$_REQUEST["password"];

			if ($_REQUEST["userinfo"] == "")  $errors["userinfo"] = "Please enter an email address.";

			if (!count($errors))
			{
				$result = $lsrv->GetLicenses(($_REQUEST["serial"] == "" ? false : $_REQUEST["serial"]), $_REQUEST["userinfo"], $productid, -1, array("revoked" => false, "password" => ($_REQUEST["serial"] == "" ? false : (string)$_REQUEST["password"])));
				if (!$result["success"])  $errors["userinfo"] = $result["error"] . " (" . $result["errorcode"] . ")";
				else if ($_REQUEST["serial"] == "")
				{
					// Send the serial numbers to the user.
					if (!count($result["licenses"]))  $errors["userinfo"] = "No licenses were found for the specified email address.";
					else
					{
						$bodyopts = array(
							(count($result["licenses"]) == 1 ? "<p>As requested, here is your current " . htmlspecialchars($productname) . " license:</p>" : "<p>As requested, here are your current " . htmlspecialchars($productname) . " licenses:</p>"),
						);

						foreach ($result["licenses"] as $license)
						{
							$serialinfo = $license["serial_info"];

							$info = "<p style=\"font-size: 16px; line-height: 24px;\"><b>Serial:</b>  " . htmlspecialchars($serialinfo["serial_num"]);
							$info .= "<br><b>Email:</b> " . htmlspecialchars($serialinfo["userinfo"]);
							if (isset($license["info"]["password"]))  $info .= "<br><b>Support Password:</b> " . htmlspecialchars($license["info"]["password"]);
							$info .= "<br><b>Product:</b>  " . $license["product_name"] . " " . htmlspecialchars($serialinfo["major_ver"] . "." . $serialinfo["minor_ver"] . " " . $serialinfo["product_class_name"]);
							$info .= "<br><b>" . ($serialinfo["expires"] ? ($serialinfo["date"] < time() ? "Expired" : "Expires") : "Purchased") . ":</b>  " . date("F j, Y", $serialinfo["date"]);
							if (isset($license["info"]["quantity"]) && $license["info"]["quantity"] > 1)  $info .= "<br><b>Quantity:</b>  " . (int)$license["info"]["quantity"];
							if (isset($license["info"]["extra"]))  $info .= "<br><b>Details:</b>  " . htmlspecialchars($license["info"]["extra"]);
							$info .= "</p>";

							$bodyopts[] = $info;
						}

						$bodyopts[] = "<p>" . htmlspecialchars($companyname) . " " . htmlspecialchars($productname) . " product support can be obtained <a href=\"" . Request::GetHost("https") . "/product-support/\">here</a>.</p>";

						$footeropts = array(
							"<p>This email was sent by an automated transactional system.</p>",
							"<p>On " . date("F j, Y") . " at " . date("g:i a") . " MST, you or someone at " . htmlspecialchars($_SERVER["REMOTE_ADDR"]) . " requested product license information to be sent to the account of record.</p>",
							"<p>If you did not request your license information, please disregard this email.</p>"
						);

						$result2 = GetEmailHTMLTemplate($bodyopts, $footeropts);

						require_once $rootpath . "/support/smtp.php";

						// Send the email to the user.
						$smtpoptions = array(
							"headers" => SMTP::GetUserAgent("Thunderbird"),
							"htmlmessage" => $result2["html"],
							"textmessage" => SMTP::ConvertHTMLToText($result2["html"]),
							"server" => "localhost",
							"port" => 25,
						);

						if (count($result2["inlineimages"]))
						{
							$smtpoptions["inlineattachments"] = true;
							$smtpoptions["attachments"] = $result2["inlineimages"];
						}

						$toaddr = $_REQUEST["userinfo"];
						$fromaddr = "\"" . $productname . "\" <" . htmlspecialchars($realemail) . ">";
						$subject = $productname . " licenses";

						$result = SMTP::SendEmail($fromaddr, $toaddr, $subject, $smtpoptions);
						if (!$result["success"])  $errors["userinfo"] = "An error occurred while attempting to send license information to the email address given.  Contact " . $companyname . " directly about this message.";
						else  $message = "License information sent to the email address given.";
					}
				}
				else if (!count($result["licenses"]))  $errors["serial"] = "Invalid license or support password specified.  Try again.";
				else
				{
					$_SESSION["serialinfo"] = array(
						"serial" => $_REQUEST["serial"],
						"userinfo" => $_REQUEST["userinfo"]
					);

					if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "buy")  header("Location: " . Request::GetHost() . "/buy/");
					else  header("Location: " . Request::GetFullURLBase() . "?ts=" . time());

					exit();
				}
			}
		}

		OutputBasicHeader("Product Support Center", "Product Support Center");

?>
<p>Download product updates, open a helpdesk ticket, and view your licenses and purchase history.  Access to this section of the website requires a valid license key and support password.  The easiest way to sign in is to launch <?=htmlspecialchars($productname)?> and click "Help/Support" from the menu.</p>
<?php

		$ff->OutputJQuery();

		// Output messages.
		if (count($errors))  $ff->OutputMessage("error", "Please correct the errors below to continue.");
		else if ($message !== "")  $ff->OutputMessage("info", $message);
		else  $ff->OutputSignedMessage();

		$contentopts = array(
			"fields" => array(
				array(
					"title" => "Email Address",
					"type" => "text",
					"name" => "userinfo",
					"default" => "",
					"desc" => "The email address used for purchasing the license."
				),
				array(
					"title" => "Serial Number",
					"type" => "text",
					"name" => "serial",
					"default" => "",
					"htmldesc" => "The 16 letter/number sequence that looks like:  xxxx-xxxx-xxxx-xxxx.<br>Don't remember/have your serial number or support password?  Leave this field empty to receive your license(s) via email."
				),
				array(
					"title" => "Support Password",
					"type" => "text",
					"name" => "password",
					"default" => "",
					"htmldesc" => "The support password associated with the license.<script type=\"text/javascript\">$(function() { $('input[name=password]').attr('autocomplete', 'off'); } );</script>"
				),
			),
			"submit" => "Sign in"
		);

		$ff->Generate($contentopts, $errors);

		OutputBasicFooter();
	}
?>