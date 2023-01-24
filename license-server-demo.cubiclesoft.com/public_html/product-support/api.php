<?php
	require_once "../base.php";
	require_once $rootpath . "/support/request.php";
	require_once $rootpath . "/support/sdk_license_server.php";

	Request::Normalize();

	session_start();

	// Process the incoming request.
	$parts = $_SERVER["REQUEST_URI"];
	$pos = strpos($parts, "?");
	if ($pos !== false)  $parts = substr($parts, 0, $pos);
	$parts = explode("/", trim(substr($parts, strlen(dirname($_SERVER["SCRIPT_NAME"]))), "/"));

	function DisplayResult($result)
	{
		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

		exit();
	}

	function DisplayError($msg, $code)
	{
		$result = array(
			"success" => false,
			"error" => $msg,
			"errorcode" => $code
		);

		DisplayResult($result);
	}

	$lsrv = new LicenseServer();

	if ($parts[0] === "latest")
	{
		$result = $lsrv->Connect();
		if (!$result["success"])  DisplayResult($result);

		$result = $lsrv->GetMajorVersions($productid, true);
		if (!$result["success"])  DisplayResult($result);

		$result2 = array(
			"success" => true,
			"versions" => array()
		);

		if (!isset($_REQUEST["major"]))  $_REQUEST["major"] = -1;
		if (!isset($_REQUEST["minor"]))  $_REQUEST["minor"] = -1;
		if (!isset($_REQUEST["patch"]))  $_REQUEST["patch"] = -1;

		foreach ($result["versions"] as $majorver => $vinfo)
		{
			if ($_REQUEST["major"] < $majorver || ($_REQUEST["major"] == $majorver && $_REQUEST["minor"] < $vinfo["info"]["minor_ver"]) || ($_REQUEST["major"] == $majorver && $_REQUEST["minor"] == $vinfo["info"]["minor_ver"] && $_REQUEST["patch"] < $vinfo["info"]["patch_ver"]))
			{
				$filename = $rootpath . "/../protected_html/downloads/v" . $majorver . "/info.json";

				if (file_exists($filename))  $result2["versions"][$majorver . "." . $vinfo["info"]["minor_ver"] . "." . $vinfo["info"]["patch_ver"]] = filemtime($filename);
			}
		}

		DisplayResult($result2);
	}
	else if ($parts[0] === "login")
	{
		if (!isset($_REQUEST["userinfo"]))  DisplayError("Missing 'userinfo' string.  The email address of a valid license.", "missing_userinfo");
		if (!isset($_REQUEST["serial"]))  DisplayError("Missing 'serial' string.  The serial number of a valid license.", "missing_serial");
		if (!isset($_REQUEST["password"]))  DisplayError("Missing 'password' string.  The support password associated with the license.", "missing_password");

		$_REQUEST["userinfo"] = (string)$_REQUEST["userinfo"];
		$_REQUEST["serial"] = (string)$_REQUEST["serial"];
		$_REQUEST["password"] = (string)$_REQUEST["password"];

		if ($_REQUEST["userinfo"] == "")  DisplayError("Empty 'userinfo' string.", "invalid_userinfo");
		if ($_REQUEST["serial"] == "")  DisplayError("Empty 'serial' string.", "invalid_serial");
		if ($_REQUEST["password"] == "")  DisplayError("Empty 'password' string.", "invalid_password");

		$result = $lsrv->Connect();
		if (!$result["success"])  DisplayError($result["error"], $result["errorcode"]);

		$result = $lsrv->GetLicenses($_REQUEST["serial"], $_REQUEST["userinfo"], $productid, -1, array("revoked" => false, "password" => $_REQUEST["password"]));
		if (!$result["success"])  DisplayError($result["error"], $result["errorcode"]);

		if (!count($result["licenses"]))  DisplayError("Invalid license or support password specified.", "login_error");

		$_SESSION["serialinfo"] = array(
			"serial" => $_REQUEST["serial"],
			"userinfo" => $_REQUEST["userinfo"],
			"password" => $_REQUEST["password"]
		);

		$result = array(
			"success" => true
		);

		DisplayResult($result);
	}
	else
	{
		$result = $lsrv->Connect();
		if (!$result["success"])  DisplayError($result["error"], $result["errorcode"]);

		if (!isset($_SESSION["serialinfo"]))
		{
			if (!isset($_REQUEST["userinfo"]) || !isset($_REQUEST["serial"]) || !isset($_REQUEST["password"]))  DisplayError("Not logged in.  Use the 'login' API to login to gain access to restricted resources.", "not_logged_in");

			$_REQUEST["userinfo"] = (string)$_REQUEST["userinfo"];
			$_REQUEST["serial"] = (string)$_REQUEST["serial"];
			$_REQUEST["password"] = (string)$_REQUEST["password"];

			if ($_REQUEST["userinfo"] == "" || $_REQUEST["serial"] == "" || $_REQUEST["password"] == "")  DisplayError("Not logged in.  Use the 'login' API to login to gain access to restricted resources.", "not_logged_in");

			$result = $lsrv->GetLicenses($_REQUEST["serial"], $_REQUEST["userinfo"], $productid, -1, array("revoked" => false, "password" => $_REQUEST["password"]));
			if (!$result["success"])  DisplayError($result["error"], $result["errorcode"]);

			if (!count($result["licenses"]))  DisplayError("Invalid license or support password specified.", "login_error");

			$_SESSION["serialinfo"] = array(
				"serial" => $_REQUEST["serial"],
				"userinfo" => $_REQUEST["userinfo"],
				"password" => $_REQUEST["password"]
			);
		}

		$result = $lsrv->GetLicenses(false, $_SESSION["serialinfo"]["userinfo"], $productid, -1, array("revoked" => false));
		if (!$result["success"])  DisplayError($result["error"], $result["errorcode"]);

		$licenses = $result["licenses"];

		if ($parts[0] === "downloads")
		{
			$result = array(
				"success" => true,
				"versions" => array()
			);

			foreach ($licenses as $license)
			{
				if (!isset($result["versions"][$license["major_ver"]]))
				{
					$filename = $rootpath . "/../protected_html/downloads/v" . $license["major_ver"] . "/info.json";

					if (file_exists($filename))
					{
						$result["versions"][$license["major_ver"]] = @json_decode(file_get_contents($filename), true);
						$result["versions"][$license["major_ver"]]["lastupdated"] = filemtime($filename);
					}
				}
			}

			krsort($result["versions"]);

			DisplayResult($result);
		}
		else if ($parts[0] === "download")
		{
			if (!isset($parts[1]) || $parts[1] === "")  DisplayError("Missing major version number in the URL.", "missing_major_ver");
			if (!isset($parts[2]) || $parts[2] === "")  DisplayError("Missing download filename in the URL.", "missing_filename");

			$majorver = (int)preg_replace('/[^0-9]/', "", $parts[1]);
			$ts = time();

			foreach ($licenses as $license)
			{
				if ($license["major_ver"] === $majorver && (!$license["serial_info"]["expires"] || $license["serial_info"]["date"] > $ts))
				{
					$filename = $rootpath . "/../protected_html/downloads/v" . $license["major_ver"] . "/info.json";

					if (!file_exists($filename))  DisplayError("No downloads available.  Try again in a bit.  A new version is possibly being released.", "metadata_missing");
					else
					{
						$downloadinfo = @json_decode(file_get_contents($filename), true);
						foreach ($downloadinfo["files"] as $filename => $file)
						{
							if ($filename === $parts[2])
							{
								$result = $lsrv->VerifySerial($license["serial_num"], $license["product_id"], $license["major_ver"], $license["userinfo"], "download", $_SERVER["REMOTE_ADDR"]);
								if (!$result["success"])  DisplayError($result["error"], $result["errorcode"]);

								header("Content-Type: application/octet-stream");
								header("X-Accel-Redirect: /protected/downloads/v" . $license["major_ver"] . "/" . $filename);

								exit();
							}
						}

						DisplayError("Access denied.  No files match the supplied filename.", "access_denied");
					}

					break;
				}
			}

			DisplayError("Access denied.  No licenses match the supplied major version number.", "access_denied");
		}
		else
		{
			DisplayError("Unknown API call '" . $parts[0] . "'.", "invalid_api_call");
		}
	}
?>