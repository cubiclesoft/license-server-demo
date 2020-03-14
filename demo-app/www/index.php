<?php
	// License Server Demo App.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	require_once "support/str_basics.php";
	require_once "support/page_basics.php";

	Str::ProcessAllInput();

	require_once "ver.php";

	define("APP_DECRYPT_SECRET", "\x12\xF0\xF6\x29\x9C\x54\x53\x04\xE0\x98\xA9\xFE\x7B\x2B\x75\x87\xC1\xDC\x23\xB9");
	define("APP_VALIDATE_SECRET", "\x2E\xFB\x3D\xB7\x2E\xC7\x7B\x3F\xD5\x73\xE6\x32\x95\xC6\xAD\xAD\x9F\xBF\x92\x2B");

	// $bb_randpage is used in combination with a user token to prevent hackers from sending malicious URLs.
	$bb_randpage = "269a88797a5f01777c95524b8c2732e01474abc6";
	$bb_rootname = APP_NAME . " " . APP_VER_DISPLAY;

	$bb_usertoken = $_SERVER["PAS_SECRET"];

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	session_start();

	if (file_exists($_SERVER["DOCUMENT_ROOT_USER"] . "/index_hook.php"))  require_once $_SERVER["DOCUMENT_ROOT_USER"] . "/index_hook.php";


	BB_ProcessPageToken("action");

	// Menu/Navigation options.
	$menuopts = array(
		APP_SHORT_NAME => array(
		),
	);

	// License check.
	// A good approach is to only offer software updates and technical support to those who have valid licenses.
	// The license code here only displays a little message when a valid license hasn't been entered in.
	require_once $rootpath . "/support/serial_number.php";

	$serialinfo = array("success" => false, "error" => "License information has not been entered.", "errorcode" => "not_licensed");
	$data = @json_decode(file_get_contents($_SERVER["PAS_ROOT"] . "/license.json"), true);
	if (is_array($data) && isset($data["serial_num"]) && isset($data["userinfo"]))
	{
		$options = array(
			"decrypt_secret" => APP_DECRYPT_SECRET,
			"validate_secret" => APP_VALIDATE_SECRET,
		);

		$result = SerialNumber::Verify($data["serial_num"], APP_PRODUCT_ID, APP_MAJOR_VER, $data["userinfo"], $options);
		if (!$result["success"])  $serialinfo = $result;
		else if ($result["expires"] && $result["date"] < time())  $serialinfo = array("success" => false, "error" => "License has expired.", "errorcode" => "expired_license");
		else
		{
			$serialinfo = $result;
			$serialinfo["password"] = $data["password"];
		}
	}

	if ($serialinfo["success"])
	{
		// Showing these two menu options and checking for updates without a valid license doesn't make sense.
		$menuopts[APP_SHORT_NAME]["Manage License"] = BB_GetRequestURLBase() . "?action=managelicense&sec_t=" . BB_CreateSecurityToken("managelicense");
		$menuopts[APP_SHORT_NAME]["Help/Support"] = array("href" => BB_GetRequestURLBase() . "?action=support&sec_t=" . BB_CreateSecurityToken("support"), "target" => "_blank");

		// Check for software updates.
		$filename = $_SERVER["PAS_PROG_FILES"] . "/updates.json";
		$ts = (isset($_SESSION["last_update_check"]) ? (int)$_SESSION["last_update_check"] : (file_exists($filename) ? filemtime($filename) : 0));
		if ($ts < time() - 7 * 24 * 60 * 60)
		{
			$data = @file_get_contents(APP_SUPPORT_URL . "/latest/?major=" . APP_MAJOR_VER . "&minor=" . APP_MINOR_VER . "&patch=" . APP_PATCH_VER);
			$data2 = @json_decode($data, true);
			if (is_array($data2) && isset($data2["versions"]))  file_put_contents($filename, $data);

			$_SESSION["last_update_check"] = time();
		}

		if (file_exists($filename))
		{
			$data = @json_decode(file_get_contents($filename), true);
			if (is_array($data) && isset($data["versions"]) && count($data["versions"]))
			{
				$menuopts[APP_SHORT_NAME]["Update Available"] = BB_GetRequestURLBase() . "?action=availableupdates&sec_t=" . BB_CreateSecurityToken("availableupdates");

				if (!isset($_REQUEST["action"]))
				{
					header("Location: " . BB_GetFullRequestURLBase() . "?action=availableupdates&sec_t=" . BB_CreateSecurityToken("availableupdates"));

					exit();
				}
			}
		}
	}
	else
	{
		$options = array(
			"Register " . APP_SHORT_NAME => BB_GetRequestURLBase() . "?action=registerlicense&sec_t=" . BB_CreateSecurityToken("registerlicense")
		);
		foreach ($menuopts[APP_SHORT_NAME] as $key => $val)  $options[$key] = $val;

		$menuopts[APP_SHORT_NAME] = $options;

		if (!isset($_REQUEST["action"]))
		{
			header("Location: " . BB_GetFullRequestURLBase() . "?action=registerlicense&sec_t=" . BB_CreateSecurityToken("registerlicense"));

			exit();
		}
		else if ($_REQUEST["action"] !== "registerlicense" && BB_GetPageMessageType() == "")
		{
			BB_SetPageMessage("info", $serialinfo["error"] . "  Use the 'Register " . APP_SHORT_NAME . "' menu option to register a valid " . APP_NAME . " " . APP_VER_DISPLAY . " license.");
		}
	}

	// Optional function to customize styles.
	function BB_InjectLayoutHead()
	{
		// Menu title underline:  Colors with 60% saturation and 75% brightness generally look good.
?>
<link rel="icon" type="image/png" href="favicon.png" />
<link rel="apple-touch-icon" href="favicon.png" />

<style type="text/css">
#menuwrap .menu .title { border-bottom: 2px solid #4C86BF; }
</style>
<?php

		// Keep PHP sessions alive.
		if (session_status() === PHP_SESSION_ACTIVE)
		{
?>
<script type="text/javascript">
setInterval(function() {
	jQuery.post('<?=BB_GetRequestURLBase()?>', {
		'action': 'heartbeat',
		'sec_t': '<?=BB_CreateSecurityToken("heartbeat")?>'
	});
}, 5 * 60 * 1000);
</script>
<?php
		}
	}

	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "heartbeat")
	{
		$_SESSION["lastts"] = time();

		echo "OK";

		exit();
	}

	// Various menu options...

	// Manage License.
	if ($serialinfo["success"] && isset($_REQUEST["action"]) && $_REQUEST["action"] == "managelicense")
	{
		if (isset($_REQUEST["submit"]))
		{
			if (BB_GetPageMessageType() != "error")
			{
				// Save the license.  Probably requires elevation to root/admin.
				@set_time_limit(0);

				require_once $_SERVER["PAS_ROOT"] . "/support/process_helper.php";
				require_once $_SERVER["PAS_ROOT"] . "/support/pas_functions.php";

				$cmd = escapeshellarg(PAS_GetPHPBinary());
				$cmd .= " " . escapeshellarg(realpath($_SERVER["PAS_ROOT"] . "/support/update_app_license.php"));
				$cmd .= " -s remove";

				$options = array(
					"stdin" => ""
				);

				$result = ProcessHelper::StartProcess($cmd, $options);
				if (!$result["success"])  BB_SetPageMessage("error", $result["error"]);
				else
				{
					$proc = $result["proc"];
					$pipes = $result["pipes"];

					$result = ProcessHelper::Wait($proc, $pipes);
					$data = @json_decode($result["stdout"], true);
					if (!is_array($data))  BB_SetPageMessage("error", "Application license process did not return expected output.  " . $result["stdout"] . "  " . $result["stderr"]);
					else if (!$data["success"])  BB_SetPageMessage("error", $data["error"] . " (" . $data["errorcode"] . ")");
					else
					{
						BB_RedirectPage("success", "Successfully removed the license information.", array(""));
					}
				}
			}
		}

		$productclasses = array(0 => "Standard", 1 => "Pro", 2 => "Enterprise");

		$contentopts = array(
			"desc" => "Manage the registered license for " . APP_NAME . " " . APP_VER_DISPLAY . ".  To enter a new or renewed license, first unregister the license below and then register again.",
			"htmldesc" => "<br><a href=\"" . BB_GetRequestURLBase() . "?action=support&action2=buy&sec_t=" . BB_CreateSecurityToken("support") . "\" target=\"_blank\">Renew license</a>",
			"fields" => array(
				array(
					"title" => "Email Address",
					"type" => "static",
					"value" => $serialinfo["userinfo"]
				),
				array(
					"title" => "Serial Number",
					"type" => "static",
					"value" => $serialinfo["serial_num"]
				),
				array(
					"title" => "Support Password",
					"type" => "static",
					"value" => $serialinfo["password"]
				),
				array(
					"title" => "Product",
					"type" => "static",
					"value" => APP_NAME . " " . $serialinfo["major_ver"] . "." . $serialinfo["minor_ver"] . " " . (isset($productclasses[$serialinfo["product_class"]]) ? $productclasses[$serialinfo["product_class"]] : "Unknown")
				),
				array(
					"title" => ($serialinfo["expires"] ? "Expires" : "Purchased"),
					"type" => "static",
					"value" => date("F j, Y", $serialinfo["date"])
				)
			),
			"submit" => "Unregister",
			"submitname" => "submit"
		);

		BB_GeneratePage("Manage License", $menuopts, $contentopts);
	}

	// Product Support.
	if ($serialinfo["success"] && isset($_REQUEST["action"]) && $_REQUEST["action"] == "support")
	{
		header("Location: " . APP_SUPPORT_URL . "/?serial=" . urlencode($serialinfo["serial_num"]) . "&userinfo=" . urlencode($serialinfo["userinfo"]) . ($serialinfo["password"] != "" ? "&password=" . urlencode($serialinfo["password"]) : "") . (isset($_REQUEST["action2"]) ? "&action=" . urlencode($_REQUEST["action2"]) : "") . "&ts=" . time());

		exit();
	}

	// Available Updates.
	if ($serialinfo["success"] && isset($_REQUEST["action"]) && $_REQUEST["action"] == "availableupdates")
	{
		$rows = array();

		$filename = $_SERVER["PAS_PROG_FILES"] . "/updates.json";

		if (file_exists($filename))
		{
			$data = @json_decode(file_get_contents($filename), true);
			if (is_array($data) && isset($data["versions"]))
			{
				foreach ($data["versions"] as $ver => $ts)
				{
					$rows[] = array(htmlspecialchars($ver), htmlspecialchars(date("F j, Y", $ts)), (APP_MAJOR_VER === (int)$ver ? "<a href=\"" . BB_GetRequestURLBase() . "?action=support&sec_t=" . BB_CreateSecurityToken("support") . "\" target=\"_blank\">Download</a>" : "<a href=\"" . BB_GetRequestURLBase() . "?action=support&action2=buy&sec_t=" . BB_CreateSecurityToken("support") . "\" target=\"_blank\">Purchase upgrade</a>"));
				}
			}
		}

		$contentopts = array(
			"desc" => "An update is available for " . APP_NAME . ".",
			"fields" => array(
				array(
					"title" => "Current Version",
					"type" => "static",
					"value" => APP_MAJOR_VER . "." . APP_MINOR_VER . "." . APP_PATCH_VER
				),
				(count($rows) ? array(
					"title" => "Update",
					"type" => "table",
					"cols" => array("Version", "Released", "Options"),
					"rows" => $rows
				) : array(
					"title" => "Update",
					"type" => "custom",
					"value" => "Local updates file corrupt or missing.  <a href=\"" . BB_GetRequestURLBase() . "?action=support&sec_t=" . BB_CreateSecurityToken("support") . "\" target=\"_blank\">Check online</a>"
				))
			)
		);

		BB_GeneratePage("Update Available", $menuopts, $contentopts);
	}

	// Register License.
	if (!$serialinfo["success"] && isset($_REQUEST["action"]) && $_REQUEST["action"] == "registerlicense")
	{
		if (isset($_REQUEST["userinfo"]))
		{
			if ($_REQUEST["userinfo"] == "")  BB_SetPageMessage("error", "Please fill in 'Email Address'.", "userinfo");
			if ($_REQUEST["serial_num"] == "")  BB_SetPageMessage("error", "Please fill in 'Serial Number'.", "serial_num");

			if (BB_GetPageMessageType() != "error")
			{
				$_REQUEST["userinfo"] = strtolower($_REQUEST["userinfo"]);
				$_REQUEST["serial_num"] = strtolower($_REQUEST["serial_num"]);

				$options = array(
					"decrypt_secret" => APP_DECRYPT_SECRET,
					"validate_secret" => APP_VALIDATE_SECRET,
				);

				$result = SerialNumber::Verify($_REQUEST["serial_num"], APP_PRODUCT_ID, APP_MAJOR_VER, $_REQUEST["userinfo"], $options);
				if (!$result["success"])  BB_SetPageMessage("error", $result["error"]);
				else if ($result["expires"] && $result["date"] < time())  BB_SetPageMessage("error", "License has expired.");
			}

			if (BB_GetPageMessageType() != "error")
			{
				// Save the license.  Probably requires elevation to root/admin.
				@set_time_limit(0);

				require_once $_SERVER["PAS_ROOT"] . "/support/process_helper.php";
				require_once $_SERVER["PAS_ROOT"] . "/support/pas_functions.php";

				$cmd = escapeshellarg(PAS_GetPHPBinary());
				$cmd .= " " . escapeshellarg(realpath($_SERVER["PAS_ROOT"] . "/support/update_app_license.php"));
				$cmd .= " -s update -serial_num " . escapeshellarg($_REQUEST["serial_num"]) . " -userinfo " . escapeshellarg($_REQUEST["userinfo"]) . " -password " . escapeshellarg($_REQUEST["password"]);

				$options = array(
					"stdin" => ""
				);

				$result = ProcessHelper::StartProcess($cmd, $options);
				if (!$result["success"])  BB_SetPageMessage("error", $result["error"]);
				else
				{
					$proc = $result["proc"];
					$pipes = $result["pipes"];

					$result = ProcessHelper::Wait($proc, $pipes);
					$data = @json_decode($result["stdout"], true);
					if (!is_array($data))  BB_SetPageMessage("error", "Application license process did not return expected output.  " . $result["stdout"] . "  " . $result["stderr"]);
					else if (!$data["success"])  BB_SetPageMessage("error", $data["error"] . " (" . $data["errorcode"] . ")");
					else
					{
						BB_RedirectPage("success", "Successfully saved the license information.", array(""));
					}
				}
			}
		}

		$contentopts = array(
			"desc" => $serialinfo["error"] . "  Fill in the following license information fields to register " . APP_NAME . " " . APP_VER_DISPLAY . ".",
			"htmldesc" => "<br><a href=\"" . APP_BUY_URL . "?ver=" . APP_MAJOR_VER . "\" target=\"_blank\">Purchase a license</a>",
			"fields" => array(
				array(
					"title" => "Email Address",
					"width" => "38em",
					"type" => "text",
					"name" => "userinfo",
					"default" => "",
					"desc" => "The email address used for purchasing the license."
				),
				array(
					"title" => "Serial Number",
					"width" => "38em",
					"type" => "text",
					"name" => "serial_num",
					"default" => "",
					"desc" => "The 16 letter/number sequence that looks like:  xxxx-xxxx-xxxx-xxxx"
				),
				array(
					"title" => "Support Password",
					"width" => "38em",
					"type" => "text",
					"name" => "password",
					"default" => "",
					"desc" => "The support password associated with the license for one-click login to the product support center later.  Optional."
				),
			),
			"submit" => "Register"
		);

		BB_GeneratePage("Register " . APP_SHORT_NAME, $menuopts, $contentopts);
	}

	// Default action.  For security, this page should never actually do anything.
	$contentopts = array(
		"desc" => "Pick an option from the menu."
	);

	BB_GeneratePage("Home", $menuopts, $contentopts);
?>