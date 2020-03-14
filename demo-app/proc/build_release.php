<?php
	// Prepare and build a release for all platforms.  Requires Windows + Inno Setup + WiX + Portable Git (for GNU tar).
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../www/ver.php";

	$newver = APP_MAJOR_VER . "." . APP_MINOR_VER . "." . APP_PATCH_VER;
	echo "Generating " . APP_NAME . " " . $newver . " installers...\n";

	require_once $rootpath . "/../installers/nix-tar-gz/install-support/dir_helper.php";

	$finaldir = $rootpath . "/../installers/FINAL";

	DirHelper::Delete($finaldir);
	if (!mkdir($finaldir))
	{
		echo "Unable to create '" . $finaldir . "'.\n";

		exit();
	}

	// Verify tools.
	$innosetupbin = "C:\\Program Files (x86)\\Inno Setup 5\\iscc.exe";
	$tarpath = "D:\\Portable Apps\\PortableGit\\usr\\bin";

	if (!file_exists($innosetupbin))
	{
		echo "Inno Setup executable not found at '" . $innosetupbin . "'.  Adjust the path in '" . $rootpath . "' and/or install Inno Setup.\n";

		exit();
	}

	if (!is_dir($tarpath))
	{
		echo "Portable Git binaries not found at '" . $tarpath . "'.  Adjust the path in '" . $rootpath . "' and/or install Portable Git.\n";

		exit();
	}

	if (getenv("WIX") === false)
	{
		echo "WIX environment variable is not set.  Set the variable and/or install the WiX toolset.\n";

		exit();
	}

	if (!is_dir(getenv("WIX")))
	{
		echo "WiX directory not found at '" . getenv("WIX") . "'.  Set the 'WIX' environment variable to the correct directory and/or install the WiX toolset.\n";

		exit();
	}

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


	// Windows builds.
	echo "Windows build | InnoSetup...\n";
	$filename = realpath($rootpath . "/../installers/win-innosetup/" . $appname . ".iss");
	$lines = explode("\n", str_replace("\r\n", "\n", file_get_contents($filename)));
	foreach ($lines as $num => $line)
	{
		if (stripos($line, "#define AppVer") !== false)  $lines[$num] = "#define AppVer \"" . $newver . "\"";

		if (stripos($line,"#define AppCopyright") !== false)  $lines[$num] = "#define AppCopyright \"(C) " . date("Y") . " " . $businessname . "\"";
	}

	file_put_contents($filename, implode("\r\n", $lines));

	system(escapeshellarg($innosetupbin) . " " . escapeshellarg($filename));

	copy($rootpath . "/../installers/win-innosetup/Output/" . $appfilename . "-" . $newver . ".exe", $finaldir . "/" . $appfilename . "-" . $newver . ".exe");


	echo "Windows build | WiX...\n";
	$filename = realpath($rootpath . "/../installers/win-wix/" . $appname . ".wxs");
	$lines = explode("\n", str_replace("\r\n", "\n", file_get_contents($filename)));
	foreach ($lines as $num => $line)
	{
		if (stripos($line, "?define AppVer") !== false)  $lines[$num] = "\t<" . "?define AppVer = \"" . $newver . "\" ?" . ">";
		if (stripos($line, "?define AppCopyright") !== false)  $lines[$num] = "\t<" . "?define AppCopyright = \"(C) " . date("Y") . " " . $businessname . "\" ?" . ">";
	}

	file_put_contents($filename, implode("\r\n", $lines));

	chdir($rootpath . "/../installers/win-wix/");
	system(escapeshellarg("build.bat"));

	copy($rootpath . "/../installers/win-wix/" . $appname . ".msi", $finaldir . "/" . $appfilename . "-" . $newver . ".msi");


	// Mac OSX build.
	echo "Mac OSX build...\n";
	$filename = realpath($rootpath . "/../installers/osx-tar-gz/" . $appname . ".json");
	$data = json_decode(file_get_contents($filename), true);
	$data["app_ver"] = $newver;
	$data["app_copyright"] = "(C) " . date("Y") . " " . $businessname;

	file_put_contents($filename, str_replace("    ", "\t", json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "\n");

	$prevpath = getenv("PATH");
	putenv("PATH=" . $tarpath . PATH_SEPARATOR . $prevpath);
	system(escapeshellarg(PHP_BINARY) . " " . escapeshellarg(realpath($rootpath . "/../installers/osx-tar-gz/package.php")));
	putenv("PATH=" . $prevpath);

	copy($rootpath . "/../installers/osx-tar-gz/" . $appfilename . "-" . $newver . "-osx.tar.gz", $finaldir . "/" . $appfilename . "-" . $newver . "-osx.tar.gz");


	// Linux build.
	echo "Linux build...\n";
	$filename = realpath($rootpath . "/../installers/nix-tar-gz/" . $appname . ".json");
	$data = json_decode(file_get_contents($filename), true);
	$data["app_ver"] = $newver;
	$data["app_copyright"] = "(C) " . date("Y") . " " . $businessname;

	file_put_contents($filename, str_replace("    ", "\t", json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "\n");

	$prevpath = getenv("PATH");
	putenv("PATH=" . $tarpath . PATH_SEPARATOR . $prevpath);
	system(escapeshellarg(PHP_BINARY) . " " . escapeshellarg(realpath($rootpath . "/../installers/nix-tar-gz/package.php")));
	putenv("PATH=" . $prevpath);

	copy($rootpath . "/../installers/nix-tar-gz/" . $appfilename . "-" . $newver . "-linux.tar.gz", $finaldir . "/" . $appfilename . "-" . $newver . "-linux.tar.gz");


	// Cleanup build files.
	echo "Cleaning up...\n";
	unlink($rootpath . "/../installers/win-innosetup/Output/" . $appfilename . "-" . $newver . ".exe");
	unlink($rootpath . "/../installers/win-wix/" . $appname . ".msi");
	unlink($rootpath . "/../installers/osx-tar-gz/" . $appfilename . "-" . $newver . "-osx.tar.gz");
	unlink($rootpath . "/../installers/nix-tar-gz/" . $appfilename . "-" . $newver . "-linux.tar.gz");

	echo "Done.\n";
?>