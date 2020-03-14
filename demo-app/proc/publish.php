<?php
	// Publishes prepared binaries.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../www/ver.php";

	$newver = APP_MAJOR_VER . "." . APP_MINOR_VER . "." . APP_PATCH_VER;
	$majorver = APP_MAJOR_VER;
	$minorver = APP_MINOR_VER;
	$patchver = APP_PATCH_VER;
	$productid = APP_PRODUCT_ID;

	echo "Publishing " . APP_NAME . " " . $newver . "...\n";

	$finaldir = $rootpath . "/../installers/FINAL";

	// Find a .phpapp file.
	$basepath = str_replace("\\", "/", realpath($rootpath . "/../"));
	$appname = false;
	$dir = opendir($basepath);
	while (($file = readdir($dir)) !== false)
	{
		if (substr($file, -7) === ".phpapp")  $appname = substr($file, 0, -7);
	}
	closedir($dir);
	if ($appname === false)
	{
		echo "Unable to find a .phpapp file.\n";

		exit();
	}

	// Load Linux JSON.
	$filename = realpath($rootpath . "/../installers/nix-tar-gz/" . $appname . ".json");
	$data = json_decode(file_get_contents($filename), true);
	$businessname = $data["business_name"];
	$appfilename = $data["app_filename"];
	$appurl = $data["app_url"];

	function AddProductFileHashes(&$finalinfo, $finaldir, $filename, $ver, $os, $osdisp, $installer, $bits32, $bits64, $url)
	{
		if (!file_exists($finaldir . "/" . $filename))
		{
			echo "Error:  The file '" . $finaldir . "/" . $filename . "' does not exist.\n";

			exit();
		}

		$data = file_get_contents($finaldir . "/" . $filename);

		$finalinfo["files"][$filename] = array(
			"version" => $ver,
			"os" => $os,
			"os_disp" => $osdisp,
			"installer" => $installer,
			"32bit" => $bits32,
			"64bit" => $bits64,
			"url" => $url,
			"size" => strlen($data),
			"md5" => hash("md5", $data),
			"sha1" => hash("sha1", $data),
			"sha256" => hash("sha256", $data),
			"sha512" => hash("sha512", $data)
		);
	}

	echo "Calculating product hashes...\n";
	$finalinfo = array(
		"info" => array(
			"title" => APP_NAME . " Hashes",
			"description" => "File hashes for the latest " . APP_NAME . " installer packages.",
			"url" => APP_VERIFY_URL,
			"product_url" => $appurl
		),
		"files" => array()
	);

	$baseurl = APP_SUPPORT_URL . "/download/v" . (int)$majorver;

	AddProductFileHashes($finalinfo, $finaldir, $appfilename . "-" . $newver . ".exe", $newver, "windows", "Windows", "exe", true, true, $baseurl . "/" . $appfilename . "-" . $newver . ".exe");
	AddProductFileHashes($finalinfo, $finaldir, $appfilename . "-" . $newver . ".msi", $newver, "windows", "Windows", "msi", true, true, $baseurl . "/" . $appfilename . "-" . $newver . ".msi");
	AddProductFileHashes($finalinfo, $finaldir, $appfilename . "-" . $newver . "-osx.tar.gz", $newver, "osx", "Mac OSX", "tar-gz", true, true, $baseurl . "/" . $appfilename . "-" . $newver . "-osx.tar.gz");
	AddProductFileHashes($finalinfo, $finaldir, $appfilename . "-" . $newver . "-linux.tar.gz", $newver, "linux", "Linux", "tar-gz", true, true, $baseurl . "/" . $appfilename . "-" . $newver . "-linux.tar.gz");

	file_put_contents($finaldir . "/info.json", str_replace("    ", "\t", json_encode($finalinfo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "\n");

	echo "Done.\n";
?>