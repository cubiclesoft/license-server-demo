<?php
	require_once "../base.php";
	require_once "../secrets.php";
	require_once $rootpath . "/layout.php";
	require_once $rootpath . "/support/request.php";
	require_once $rootpath . "/support/random.php";
	require_once $rootpath . "/support/flex_forms.php";
	require_once $rootpath . "/support/flex_forms_stripe.php";
	require_once $rootpath . "/support/sdk_license_server.php";
	require_once $rootpath . "/support/sdk_stripe.php";
	require_once $rootpath . "/support/smtp.php";

	Request::Normalize();

	session_start();

	$sesskey = "lsrv_" . $productid;

	if (!isset($_SESSION[$sesskey]))  $_SESSION[$sesskey] = array();

	// Connect to the license server and retrieve version information.
	$lsrv = new LicenseServer();

	$result = $lsrv->Connect();
	if (!$result["success"])  OutputPage("Error", "License Server Unavailable", "<p>The license server is not available at the moment.  Try again later.  If the problem persists, contact " . htmlspecialchars($companyname) . ".</p><p>" . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "</p>");

	$result = $lsrv->GetMajorVersions($productid, true);
	if (!$result["success"])  OutputPage("Error", "No Versions Available", "<p>The license server returned an unexpected error while retrieving version information.  Try again later.  If the problem persists, contact " . htmlspecialchars($companyname) . ".</p><p>" . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "</p>");
	$productname = $result["product_name"];
	$versions = $result["versions"];
	if (!count($versions))  OutputPage("Error", "No Versions Available", "<p>No versions of " . htmlspecialchars($productname) . " are available for purchase at the moment.</p>");

	$majorver = max(array_keys($versions));
	$minorver = $versions[$majorver]["info"]["minor_ver"];

	$displaynamever = $productname . " " . $majorver . "." . $minorver;

	$errors = array();
	$message = "";

	// Cost and quantity.  Default is USD.
	if (isset($_SESSION[$sesskey]["serialinfo"]))  $cost = 10.00;
	else  $cost = 30.00;

	$quantity = 1;

	$ff = new FlexForms();
	$ff->SetState(array("supporturl" => "/support"));
	$ff->SetSecretKey($buy_form_secretkey);
	$ff->SetTokenExtraInfo($_SERVER["REMOTE_ADDR"]);

	$confirmpayment = false;

	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "done")
	{
		if (isset($_REQUEST["status"]) && $_REQUEST["status"] != "success")
		{
			OutputBasicHeader("Error", "Purchase Successful But...");

?>
<p>Thank you for supporting <?=htmlspecialchars($productname)?>.  The payment processed successfully and the license was issued.</p>
<p>Unfortunately, a problem occurred while attempting to send the license information and the receipt to <?=(isset($_REQUEST["email"]) ? "<b>" . htmlspecialchars($_REQUEST["email"]) . "</b>" : "the email address provided")?>.</p>
<p>Please contact <?=htmlspecialchars($companyname)?> directly about this message at <a href="mailto:<?=htmlspecialchars($realemail)?>?subject=[<?=htmlspecialchars($productname)?> Support] Missing receipt for license"><?=htmlspecialchars($displayemail)?></a> and include the following error message:</p>
<p style="margin-left: 1em; color: #A94442; font-weight: bold;"><i><?=htmlspecialchars($productname)?> Error - <?=(isset($_REQUEST["error"]) && isset($_REQUEST["errorcode"]) ? htmlspecialchars($_REQUEST["error"] . " (" . $_REQUEST["errorcode"] . ")") : "Unknown or missing error information.")?></i></p>
<?php
		}
		else
		{
			OutputBasicHeader("Thank You", "Purchase Successful");

?>
<p>Thank you for supporting <?=htmlspecialchars($productname)?>.  The payment processed successfully and the license was issued.</p>
<p>License information and the receipt have been sent to <?=(isset($_REQUEST["email"]) ? "<b>" . htmlspecialchars($_REQUEST["email"]) . "</b>" : "the email address provided")?>.</p>
<p>Please add <b><?=htmlspecialchars($realemail)?></b> to your address book.  If the message doesn't arrive in your email inbox, try checking your spam folder.</p>
<?php
		}

?>
<p><?=htmlspecialchars($productname)?> downloads and product support can be obtained through the <a href="/product-support/">product support center</a>.</p>
<?php

		if (isset($_SESSION[$sesskey]["print_receipt"]))
		{
?>
<h2>Receipt for Order #<?=htmlspecialchars($_SESSION[$sesskey]["print_receipt"])?></h2>
<?php

			// Load the license from the order number.
			$orderinfo = $lsrv->ExtractOrderNumberInfo($_SESSION[$sesskey]["print_receipt"]);
			if ($orderinfo === false)  echo "<p>Invalid order number.</p>";
			else
			{
				$result = $lsrv->GetLicenses(false, false, -1, -1, array("revoked" => false, "created" => $orderinfo["created"], "order_num" => $orderinfo["order_num"]));
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
		}

		OutputBasicFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "frombank")
	{
		if (!isset($_SESSION[$sesskey]["stripe_info"]))  $errors["card_details"] = "Unfortunately, your browser session expired before the payment cycle completed.  No charges have been made but all information will have to be re-entered.";
		else
		{
			// Restore the request.
			$_REQUEST = $_SESSION[$sesskey]["stripe_info"]["request"];

			$fields = $_SESSION[$sesskey]["stripe_info"]["fields"];

			// Load the PaymentIntent.
			$stripe = new StripeSDK();
			$stripe->SetAccessInfo($stripe_secretkey);

			$result = $stripe->RunAPI("GET", "/payment_intents/" . $_SESSION[$sesskey]["stripe_info"]["payment_intent"]);
			if (!$result["success"])  $errors["card_details"] = $result["error"] . " (" . $result["errorcode"] . ")";
			else
			{
				$paymentintent = $result["data"];

				$confirmpayment = true;
			}
		}
	}
	else if (isset($_REQUEST[$ff->GetHashedFieldName("card_details")]))
	{
		$defaults = array(
			"email" => "",
			"org_name" => "",
			"card_name" => "",
			"billing_address1" => "",
			"billing_city" => "",
			"billing_state" => "",
			"billing_zip" => "",
			"card_details" => "",
		);

		$fields = $ff->GetHashedFieldValues($defaults);

		if (!isset($_REQUEST["expected_cost"]) || $_REQUEST["expected_cost"] != $cost)  $errors["cost"] = "The cost unexpectedly changed.  This most frequently happens when signed in and taking too long on the renewal screen.";

		if (isset($_SESSION[$sesskey]["serialinfo"]))  $_REQUEST[$ff->GetHashedFieldName("email")] = $fields["email"] = $_SESSION[$sesskey]["serialinfo"]["userinfo"];

		if ($fields["email"] == "")  $errors["email"] = "Please fill in this field.";
		else
		{
			$result = SMTP::MakeValidEmailAddress($fields["email"]);
			if (!$result["success"])  $errors["email"] = "Invalid email address.  " . $result["error"] . " (" . $result["errorcode"] . ")";
			else  $_REQUEST[$ff->GetHashedFieldName("email")] = $fields["email"] = $result["email"];
		}

		if ($fields["card_name"] == "")  $errors["card_name"] = "Please fill in this field.";
		if ($fields["billing_address1"] == "")  $errors["billing_address1"] = "Please fill in billing address.";
		if ($fields["billing_city"] == "")  $errors["billing_city"] = "Please fill in billing city.";
		if ($fields["billing_state"] == "")  $errors["billing_state"] = "Please fill in billing state.";
		if ($fields["billing_zip"] == "")  $errors["billing_zip"] = "Please fill in this billing zip.";

		// Verify that no licenses are already registered to the user.
		if (!count($errors) && !isset($_SESSION[$sesskey]["serialinfo"]))
		{
			$result = $lsrv->GetLicenses(false, $fields["email"], $productid, -1, array("revoked" => false));
			if (!$result["success"])  $errors["email"] = $result["error"] . " (" . $result["errorcode"] . ")";
			else if (count($result["licenses"]))  $errors["email"] = "There are one or more licenses already associated with this email address.  Please sign into the product support center to get the renewal rate.";
		}

		if ($fields["card_details"] == "")  $errors["card_details"] = "Please fill in card details.";
		else if (count($errors))  $errors["card_details"] = "Due to other errors, card details need to be re-entered.";

		if (!count($errors))
		{
			$stripe = new StripeSDK();
			$stripe->SetAccessInfo($stripe_secretkey);

			// Create a PaymentIntent.
			$postvars = array(
				"amount" => $quantity * $cost * 100,
				"currency" => "usd",
				"description" => $displaynamever . " " . $versions[$majorver]["info"]["product_classes"][0],
				"metadata[type]" => "lsrv",
				"metadata[product]" => (string)$productid,
				"metadata[majorver]" => (string)$majorver,
				"metadata[userinfo]" => $fields["email"],
				"payment_method" => $fields["card_details"],
				"confirmation_method" => "manual",
				"statement_descriptor" => "LSRV DEMO"
			);

			$result = $stripe->RunAPI("POST", "/payment_intents", $postvars);
			if (!$result["success"])  $errors["card_details"] = $result["error"] . " (" . $result["errorcode"] . ")";
			else
			{
				$paymentintent = $result["data"];

				$confirmpayment = true;
			}
		}
	}

	// Attempt to confirm the PaymentIntent.
	if ($confirmpayment)
	{
		if ($paymentintent["status"] === "requires_payment_method")  $errors["card_details"] = "The payment attempt failed.  Please enter a different payment method or contact your card company.";
		else if ($paymentintent["status"] === "succeeded")  $errors["card_details"] = "Payment succeeded but this page was reloaded.  Check your email for the receipt and license info.";
		else if ($paymentintent["status"] === "requires_confirmation")
		{
			// Retrieve active major versions and existing licenses for the user (if any).
			$result = $lsrv->GetLicenses(false, $fields["email"], $productid, -1, array("revoked" => false));
			if (!$result["success"])  $errors["email"] = $result["error"] . " (" . $result["errorcode"] . ")";
			else
			{
				$licenses = $result["licenses"];

				// Confirm the PaymentIntent.
				$postvars = array(
					"return_url" => Request::GetFullURLBase() . "?action=frombank&ts=" . time(),
				);

				$result = $stripe->RunAPI("POST", "/payment_intents/" . $paymentintent["id"] . "/confirm", $postvars);
				if (!$result["success"])  $errors["card_details"] = $result["error"] . " (" . $result["errorcode"] . ")";
				else if ($result["data"]["status"] === "requires_action")
				{
					// Process 3DSecure.
					if ($result["data"]["next_action"]["type"] !== "redirect_to_url")  $errors["card_details"] = "An internal error occurred while attempting payment.  Unsupported next action returned from payment processor.  No charges have been made.";
					else
					{
						// Save the current request for later.
						$_SESSION[$sesskey]["stripe_info"] = array(
							"request" => $_REQUEST,
							"fields" => $fields,
							"payment_intent" => $paymentintent["id"]
						);

						header("Location: " . $result["data"]["next_action"]["redirect_to_url"]["url"]);

						exit();
					}
				}
				else if ($result["data"]["status"] !== "succeeded")  $errors["card_details"] = "An internal error occurred while attempting payment.  Unexpected payment status '" . $result["data"]["status"] . "' returned from the payment processor.";
				else
				{
					// Create a new license.
					$expirets = mktime(0, 0, 0, date("n"), date("j") + 366);

					// Adjust the expiration date to be 366 days past the expiration date of the most recent license if the license is not expired.
					$ts = time();
					foreach ($licenses as $license)
					{
						if ($license["serial_info"]["expires"] && $license["serial_info"]["date"] > $ts)  $expirets = mktime(0, 0, 0, date("n", $license["serial_info"]["date"]), date("j", $license["serial_info"]["date"]) + 366, date("Y", $license["serial_info"]["date"]));
					}

					// Squirrel a log of the transaction away for static receipt generation purposes later.
					$log = array(
						"success" => true,
						"payment_processor" => "stripe",
						"payment_id" => $result["data"]["id"],
						"items" => array(
							array("disp" => $displaynamever . " " . $versions[$majorver]["info"]["product_classes"][0] . " license for 1 year", "qty" => $quantity, "unit_cost" => $cost, "currency" => "USD")
						),
						"total" => $result["data"]["amount"] / 100,
						"total_currency" => strtoupper($result["data"]["currency"]),
						"billing_info" => $result["data"]["charges"]["data"][0]["billing_details"]
					);

					$options = array(
						"expires" => true,
						"date" => $expirets,
						"product_class" => 0,
						"minor_ver" => $minorver,
						"custom_bits" => 0,
						"info" => array(
							"quantity" => $quantity,
							"extra" => ($fields["org_name"] !== "" ? $fields["org_name"] : $fields["card_name"])
						),
						"log" => json_encode($log, JSON_UNESCAPED_SLASHES)
					);

					$result2 = $lsrv->CreateLicense($productid, $majorver, $fields["email"], $options);
					if (!$result2["success"])
					{
						// Exceedingly rare error message.  The license server has to go offline right after payment succeeds.
						$message = "<p>Unfortunately, something went horribly wrong.  The payment was successful but the software license was NOT issued.</p>";
						$message .= "<p>Please contact " . htmlspecialchars($companyname) . " at <a href=\"mailto:" . htmlspecialchars($realemail) . "?subject=[" . htmlspecialchars($productname) . " Support] License server failure (Payment ID: " . htmlspecialchars($paymentintent["id"]) . ")\">" . htmlspecialchars($displayemail) . "</a> immediately.</p>";
						$message .= "<p>In your message, be sure to include the following critical diagnostic error information:</p>";
						$message .= "<p>Payment ID:  " . htmlspecialchars($paymentintent["id"]) . "</p>";
						$message .= "<p>Licensing error:  " . htmlspecialchars($result2["error"] . " (" . $result2["errorcode"] . ")") . "</p>";
						$message .= "<p>Diagnostics:  " . htmlspecialchars(json_encode(array("majorver" => $majorver, "userinfo" => $fields["email"], "options" => $options), JSON_UNESCAPED_SLASHES)) . "</p>";

						OutputPage("Error", "License NOT Issued", $message);
					}
					else
					{
						// Generate and send a receipt.
						$bodyopts = array(
							"<p>" . htmlspecialchars($fields["card_name"]) . ",</p>",
							"<p>Thank you for your purchase of a " . htmlspecialchars($companyname) . " " . htmlspecialchars($productname) . " license.</p>",
							array("type" => "space", "height" => 5),
							array("type" => "split", "bgcolor" => "#F0F0F0"),
							array("type" => "space", "height" => 5),
							"<p>License information:</p>"
						);

						$serialinfo = $result2["serial_info"];

						$info = "<p style=\"font-size: 16px; line-height: 24px;\"><b>Serial:</b>  " . htmlspecialchars($serialinfo["serial_num"]);
						$info .= "<br><b>Email:</b> " . htmlspecialchars($serialinfo["userinfo"]);
						$info .= "<br><b>Support Password:</b> " . htmlspecialchars($result2["info"]["password"]);
						$info .= "<br><b>Product:</b>  " . htmlspecialchars($result2["product_name"]) . " " . htmlspecialchars($serialinfo["major_ver"] . "." . $serialinfo["minor_ver"] . " " . $serialinfo["product_class_name"]);
						$info .= "<br><b>" . ($serialinfo["expires"] ? ($serialinfo["date"] < time() ? "Expired" : "Expires") : "Purchased") . ":</b>  " . date("F j, Y", $serialinfo["date"]);
						if (isset($result2["info"]["quantity"]) && $result2["info"]["quantity"] > 1)  $info .= "<br><b>Quantity:</b>  " . (int)$result2["info"]["quantity"];
						if (isset($result2["info"]["extra"]))  $info .= "<br><b>Details:</b>  " . htmlspecialchars($result2["info"]["extra"]);
						$info .= "</p>";

						$bodyopts[] = $info;

						$bodyopts[] = "<p>" . htmlspecialchars($companyname) . " " . htmlspecialchars($productname) . " downloads and product support can be obtained <a href=\"" . Request::GetHost("https") . "/product-support/?serial=" . urlencode($serialinfo["serial_num"]) . "&userinfo=" . urlencode($serialinfo["userinfo"]) . "&password=" . urlencode($result2["info"]["password"]) . "\">here</a>.</p>";

						$bodyopts[] = array("type" => "space", "height" => 5);
						$bodyopts[] = array("type" => "split", "bgcolor" => "#F0F0F0");
						$bodyopts[] = array("type" => "space", "height" => 5);

						$bodyopts[] = "<p>Receipt for Order #" . $lsrv->GetUserOrderNumber("CSLS", $result2["created"], $result2["order_num"]) . "</p>";

						$bodyopts[] = "<table style=\"padding: 0; margin: 0; width: 100%; font-size: 16px; line-height: 24px;\" width=\"100%\" cellspacing=\"1\" cellpadding=\"5\" border=\"0\">";
						$bodyopts[] = "<tr bgcolor=\"#F8F8F8\"><td valign=\"top\">" . htmlspecialchars($displaynamever . " " . $versions[$majorver]["info"]["product_classes"][0] . " license for 1 year") . ($quantity > 1 ? "<br><span style=\"font-size: 14px;\">" . htmlspecialchars($quantity . " x $" . number_format($cost, 2) . " USD") . "</span>" : "") . "</td><td valign=\"top\" align=\"right\">$" . number_format($quantity * $cost, 2) . " USD</td></tr>";
						$bodyopts[] = "<tr><td valign=\"top\" align=\"right\"><b>Total:</b></td><td valign=\"top\" align=\"right\"><b>$" . number_format($quantity * $cost, 2) . " USD</b></td></tr>";
						$bodyopts[] = "</table>";

						$billinginfo = $result["data"]["charges"]["data"][0]["billing_details"];

						$info = "<p style=\"font-size: 16px; line-height: 24px;\"><b>Billing Address</b>";
						$info .= "<br>" . htmlspecialchars($billinginfo["name"]);
						$info .= "<br>" . htmlspecialchars($billinginfo["address"]["line1"]);
						$info .= "<br>" . htmlspecialchars($billinginfo["address"]["city"] . ", " . $billinginfo["address"]["state"] . " " . $billinginfo["address"]["postal_code"]);
						$info .= "</p>";

						$bodyopts[] = $info;

						$paymentinfo = $result["data"]["charges"]["data"][0]["payment_method_details"];

						if ($paymentinfo["type"] !== "card")
						{
							$info = "<p style=\"font-size: 16px; line-height: 24px;\"><b>Payment Method</b>";
							$info .= "<br>Unknown (" . htmlspecialchars($paymentinfo["type"]) . ")";
							$info .= "</p>";
						}
						else
						{
							$brandmap = FlexForms_Stripe::GetBrandMap();

							$bodyopts[] = "<table style=\"padding: 0; margin: 0;\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">";
							$bodyopts[] = "<tr><td colspan=\"2\" style=\"font-size: 16px; line-height: 24px;\"><b>Payment Method</b></td></tr>";
							$bodyopts[] = "<tr><td valign=\"middle\" width=\"1\" nowrap>";
							$bodyopts[] = array(
								"type" => "image",
								"inline" => true,
								"file" => $config["rootpath"] . "/support/card-images/" . (isset($brandmap[$paymentinfo["card"]["brand"]]) ? $brandmap[$paymentinfo["card"]["brand"]]["img"] : $brandmap[""]["img"]),
//								"src" => "/support/card-images/" . (isset($brandmap[$paymentinfo["card"]["brand"]]) ? $brandmap[$paymentinfo["card"]["brand"]]["img"] : $brandmap[""]["img"]),
								"alt" => (isset($brandmap[$paymentinfo["card"]["brand"]]) ? $brandmap[$paymentinfo["card"]["brand"]]["name"] : $brandmap[""]["name"])
							);
							$bodyopts[] = "&nbsp;</td>";

							$bodyopts[] = "<td valign=\"middle\" style=\"font-size: 16px; line-height: 24px;\">" . htmlspecialchars($paymentinfo["card"]["last4"]) . "</td>";
							$bodyopts[] = "</tr></table>";
							$bodyopts[] = array("type" => "space", "height" => 15);
						}

						$footeropts = array(
							"<p>This email was sent by an automated transactional system.</p>",
							"<p>On " . date("F j, Y", $result2["created"]) . " at " . date("g:i a", $result2["created"]) . " MST, you or someone at " . htmlspecialchars($_SERVER["REMOTE_ADDR"]) . " made an authorized purchase of a " . htmlspecialchars($companyname) . " product license.</p>",
							"<p>Please keep this receipt for your records.</p>"
						);

						$result3 = GetEmailHTMLTemplate($bodyopts, $footeropts);

						// Send the email to the user.
						$smtpoptions = array(
							"headers" => SMTP::GetUserAgent("Thunderbird"),
							"htmlmessage" => $result3["html"],
							"textmessage" => SMTP::ConvertHTMLToText($result3["html"]),
							"server" => "localhost",
							"port" => 25,
						);

						if (count($result3["inlineimages"]))
						{
							$smtpoptions["inlineattachments"] = true;
							$smtpoptions["attachments"] = $result3["inlineimages"];
						}

						$toaddr = $result2["userinfo"];
						$fromaddr = "\"" . $productname . "\" <" . htmlspecialchars($realemail) . ">";
						$subject = $productname . " license + order receipt";

						$result = SMTP::SendEmail($fromaddr, $toaddr, $subject, $smtpoptions);

						$url = Request::GetFullURLBase() . "?action=done&email=" . urlencode($toaddr) . "&status=" . ($result["success"] ? "success" : "error&error=" . urlencode($result["error"]) . "&errorcode=" . urlencode($result["errorcode"]));

						header("Location: " . $url);

						exit();
					}
				}
			}
		}
	}

	if (isset($_SESSION[$sesskey]["serialinfo"]))
	{
		OutputBasicHeader("Renew Now", "Renew Now");

?>
<p>Thanks for supporting <?=htmlspecialchars($companyname)?> <?=htmlspecialchars($productname)?>!  The renewal rate for <?=htmlspecialchars($productname)?> Standard Edition is just $<?=$cost?> USD per year and covers download access, upgrades, and product maintenance and support during that time.</p>

<p>Renewing will create a new license valid for one year.  If you have a current license that has not expired yet, the new license period will end 366 days after the expiration date of the most recent license.</p>
<?php
	}
	else
	{
		OutputBasicHeader("Buy Now", "Buy Now");

?>
<p><?=htmlspecialchars($productname)?> Standard Edition is $<?=$cost?> USD and covers download access, upgrades, and product maintenance and support for one year and is $10 USD per year after that.</p>

<p>If you already have purchased a license, please go through the <a href="/product-support/">product support center</a> to get the renewal rate.</p>
<?php
	}

	// Output messages.
	if (count($errors))  $ff->OutputMessage("error", "Please correct the errors below to continue.");
	else if ($message !== "")  $ff->OutputMessage("info", $message);
	else  $ff->OutputSignedMessage();

	$contentopts = array(
		"hashnames" => true,
		"hidden" => array(
			"expected_cost" => $cost,
		),
		"fields" => array(
			(isset($_SESSION[$sesskey]["serialinfo"]) ? array(
				"title" => "Email Address",
				"type" => "static",
				"name" => "email",
				"value" => $_SESSION[$sesskey]["serialinfo"]["userinfo"]
			) : array(
				"title" => "Email Address",
				"type" => "text",
				"name" => "email",
				"default" => "",
				"htmldesc" => "The email address to associate with the license and purchase."
			)),
			array(
				"title" => "Organization Name",
				"type" => "text",
				"name" => "org_name",
				"default" => "",
				"desc" => "Optional."
			),
			"split",
			array(
				"title" => "Name on Card",
				"type" => "text",
				"name" => "card_name",
				"default" => ""
			),
			array(
				"title" => "Billing Address",
				"type" => "text",
				"name" => "billing_address1",
				"default" => ""
			),
			"startrow",
			array(
				"title" => "Billing City",
				"width" => "15em",
				"type" => "text",
				"name" => "billing_city",
				"default" => ""
			),
			array(
				"title" => "State",
				"width" => "5em",
				"type" => "text",
				"name" => "billing_state",
				"default" => ""
			),
			array(
				"title" => "Zip",
				"width" => "10em",
				"type" => "text",
				"name" => "billing_zip",
				"default" => ""
			),
			"endrow",
			array(
				"title" => "Card Details",
				"type" => "stripe",
				"name" => "card_details",
				"pubkey" => $stripe_publickey,
				"options" => array(
					"fonts" => array(
						"family" => "site-font",
						"src" => "url('" . Request::GetHost() . "/res/main.woff')"
					)
				),
				"style" => array(
					"base" => array(
						"fontFamily" => "site-font",
						"fontSize" => "1.2em",
						"color" => "#333333"
					)
				),
				"elements" => array(
					array("cardNumber" => array()),
					array("cardExpiry" => array(), "cardCvc" => array())
				),
				"datamap" => array(
					"name" => $ff->GetHashedFieldName("card_name"),
					"address.line1" => $ff->GetHashedFieldName("billing_address1"),
					"address.city" => $ff->GetHashedFieldName("billing_city"),
					"address.state" => $ff->GetHashedFieldName("billing_state"),
					"address.postal_code" => $ff->GetHashedFieldName("billing_zip"),
				),
				"process" => "Processing...",
				"htmldesc" => (substr($stripe_publickey, 0, 8) === "pk_test_" ? "Currently running in Stripe test card mode.  Accepts <a href=\"https://stripe.com/docs/testing#cards\" target=\"_blank\">Stripe test cards</a> only." : "")
			),
			array(
				"title" => "License",
				"type" => "static",
				"value" => $displaynamever . " " . $versions[$majorver]["info"]["product_classes"][0] . " for 1 year"
			),
			array(
				"title" => "Total",
				"type" => "static",
				"name" => "cost",
				"value" => "$" . ($quantity * $cost) . " USD"
			)
		),
		"submit" => "Submit payment"
	);

	$ff->Generate($contentopts, $errors);

	OutputBasicFooter();
?>