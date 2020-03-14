<?php
	function OutputHeader($title = "CubicleSoft License Server Demo", $desc = "", $img = false)
	{
		header("Content-Type: text/html; UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
<title><?=htmlspecialchars($title)?></title>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style type="text/css">html { visibility: hidden; opacity: 0; }</style>
<link rel="stylesheet" href="/main.css" type="text/css" media="all">
<link rel="icon" type="image/png" sizes="256x256" href="/icon_256x256.png">
<link rel="apple-touch-icon" href="/icon_256x256.png" />
<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
<?php
		if ($desc !== "")
		{
			if ($img === false)  $img = Request::PrependHost() . "/icon_256x256.png";

?>
<meta name="description" content="<?=htmlspecialchars($desc)?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@CubicleSoft">
<meta name="twitter:title" content="<?=htmlspecialchars($title)?>">
<meta name="og:title" content="<?=htmlspecialchars($title)?>">
<meta name="og:type" content="website">
<meta name="twitter:description" content="<?=htmlspecialchars($desc)?>">
<meta name="og:description" content="<?=htmlspecialchars($desc)?>">
<meta name="twitter:image" content="<?=htmlspecialchars($img)?>">
<meta name="og:image" content="<?=htmlspecialchars($img)?>">
<?php
		}
?>
</head>
<body>
<div class="headerwrap">
	<div class="menuwrap">
		<div class="menuinner">
			<div class="menuleft">
				<a href="/"><img src="/res/main-logo.png" alt="License Server Demo logo"></a>
			</div>

			<div class="menuright">
				<a href="/buy/"><span class="menuiteminner">Buy Now</span></a><a href="/product-support/"><span class="menuiteminner">Support</span></a>
			</div>
		</div>
	</div>
</div>
<?php
	}

	function OutputFooter()
	{
?>
<div class="footerwrap">
	<div class="footerinnerwrap">
		<div class="footerinner">
			<div class="footercols">
				<div class="footercol itemswrap">
					<div class="itemstitle">CubicleSoft Products</div>
					<a href="https://github.com/cubiclesoft/sso-server" title="A login system that rocks">Single Sign-On Server/Client</a>
					<a href="https://github.com/cubiclesoft/cloud-backup" title="Awesome backup software">Cloud Backup</a>
					<a href="https://github.com/cubiclesoft/cloud-storage-server" title="Self-hosted cloud storage API">Cloud Storage Server</a>
					<a href="https://github.com/cubiclesoft/php-app-server" title="Write native applications for Windows, Mac, and Linux desktop in PHP">PHP App Server</a>
					<a href="https://github.com/cubiclesoft/ultimate-web-scraper" title="The most powerful and flexible toolkit on earth for scraping content">Ultimate Web Scraper Toolkit</a>
					<a href="https://github.com/cubiclesoft/ultimate-email" title="Send amazing e-mail">Ultimate E-mail Toolkit</a>
					<a href="https://github.com/cubiclesoft/admin-pack" title="Create powerful admin interfaces">Admin Pack</a>
					<a href="https://github.com/cubiclesoft/php-flexforms" title="Impressive HTML form generation library">FlexForms</a>
					<a href="https://github.com/cubiclesoft/csdb" title="CubicleSoft database access layer for cross-database SQL">CSDB</a>
					<a href="https://github.com/cubiclesoft/web-knocker-firewall-service" title="Close off those open server ports">Web Knocker Firewall Service</a>
					<a href="https://github.com/cubiclesoft" title="Follow CubicleSoft on GitHub">And More...</a>
				</div>
				<div class="footercol itemswrap">
					<div class="itemstitle">Support</div>
					<a href="/product-support/">Product Help</a>
					<a href="mailto:support@cubiclesoft.com">Contact</a>

					<div class="itemstitle">Legal</div>
					<a href="https://cubiclesoft.com/terms-of-service/">Terms of Service</a>
					<a href="https://cubiclesoft.com/privacy-policy/">Privacy Policy</a>
					<a href="https://cubiclesoft.com/compatibility-policies/">Compatibility Policies</a>
					<a href="https://file-tracker.cubiclesoft.com/eula/">EULA</a>
				</div>
			</div>

			<div class="copyright">&copy; <?=date("Y")?> <a href="https://cubiclesoft.com/">CubicleSoft</a></div>
		</div>
	</div>
</div>
</body>
</html>
<?php
	}

	function OutputBasicHeader($title, $header)
	{
		OutputHeader($title . " | CubicleSoft License Server Demo");

?>
<div class="contentwrap">
<div class="contentwrapinner">
<h1><?=htmlspecialchars($header)?></h1>
<?php
	}

	function OutputBasicFooter()
	{
?>
</div>
</div>
<?php

		OutputFooter();

		exit();
	}

	function OutputPage($title, $header, $message)
	{
		OutputBasicHeader($title, $header);

		echo $message;

		OutputBasicFooter();

		exit();
	}

	function Output404()
	{
		http_response_code(404);

		OutputPage("Invalid Resource", "Invalid Resource", "<p>The requested resource does not exist, was unpublished, or has moved.  Unfortunately, this is a 404 so there's nothing to do.</p>");
	}

	function GetEmailHTMLTemplate($bodyopts, $footeropts, $extrastyles = array())
	{
		$rootpath = str_replace("\\", "/", dirname(__FILE__));

		require_once $rootpath . "/support/email_builder.php";

		$styles = array(
			"a" => "text-decoration: none;",
			"#headerwrap" => "font-size: 0; line-height: 0;",
			"#contentwrap" => "font-family: Helvetica, Arial, sans-serif; font-size: 18px; line-height: 27px; color: #333333;",
			"#contentwrap a:not(.bigbutton)" => "color: #4E88C2;",
			"#contentwrap a.bigbutton" => "font-family: Helvetica, Arial, sans-serif; font-size: 18px; line-height: 27px; color: #FEFEFE;",
			"#footerwrap" => "font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 21px; color: #F0F0F0;",
			"#footerwrap a" => "color: #CCCCCC;"
		) + $extrastyles;

		array_unshift($bodyopts, array("type" => "space", "height" => 1));
		$bodyopts[] = array("type" => "space", "height" => 1);

		array_unshift($footeropts, array("type" => "space", "height" => 1));
		$footeropts[] = array("type" => "space", "height" => 1);

		$content = array(
			// Header.
			array(
				"type" => "layout",
				"id" => "headerwrap",
				"width" => 600,
				"bgcolor" => "#182434",
				"content" => array(
					array(
						"type" => "image",
						"width" => 600,
						"file" => $rootpath . "/res/main-logo.png",
						"alt" => "CubicleSoft License Server Demo"
					)
				)
			),

			// Main content.
			array(
				"type" => "layout",
				"width" => 600,
				"content" => array(
					array(
						"type" => "layout",
						"id" => "contentwrap",
						"width" => "90%",
						"content" => $bodyopts
					)
				)
			),

			// Footer.
			array(
				"type" => "layout",
				"width" => 600,
				"bgcolor" => "#182434",
				"content" => array(
					array(
						"type" => "layout",
						"id" => "footerwrap",
						"width" => "90%",
						"content" => $footeropts
					)
				)
			)
		);

		return EmailBuilder::Generate($styles, $content);
	}
?>