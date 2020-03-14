<?php
	// Minimalist Stripe SDK.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	// Load dependencies.
	if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

	class StripeSDK
	{
		private $web, $fp, $debug, $apikey;

		public function __construct()
		{
			$this->web = new WebBrowser();
			$this->fp = false;
			$this->debug = false;
			$this->apikey = false;
		}

		// Enabling debug mode can leak information (e.g. the API token).
		public function SetDebug($debug)
		{
			$this->debug = (bool)$debug;
		}

		public function SetAccessInfo($apikey)
		{
			$this->web = new WebBrowser();
			$this->fp = false;
			$this->apikey = $apikey;
		}

		public static function MakeIdempotencyKey()
		{
			return bin2hex(@random_bytes(32));
		}

		public function RunAPI($method, $apipath, $postvars = array(), $options = array(), $expected = 200, $decodebody = true, $idempotentkey = false)
		{
			if ($this->apikey === false)  return array("success" => false, "error" => self::Stripe_Translate("Missing API key."), "errorcode" => "no_access_info");

			$url = "https://api.stripe.com/v1";

			$method = strtoupper($method);

			$options2 = array(
				"method" => $method,
				"headers" => array(
					"Connection" => "keep-alive",
					"Authorization" => "Basic " . base64_encode($this->apikey . ":")
				)
			);
			if ($this->debug)  $options2["debug"] = true;

			if ($this->fp !== false)  $options2["fp"] = $this->fp;

			if ($method === "POST")
			{
				// Generate idempotent string.
				if ($idempotentkey === false)  $idempotentkey = self::MakeIdempotencyKey();

				$options2["headers"]["Idempotency-Key"] = $idempotentkey;
				$options2["postvars"] = $postvars;

				foreach ($options as $key => $val)
				{
					if (isset($options2[$key]) && is_array($options2[$key]))  $options2[$key] = array_merge($options2[$key], $val);
					else  $options2[$key] = $val;
				}
			}
			else
			{
				$options2 = array_merge($options2, $options);
			}

			$result = $this->web->Process($url . $apipath, $options2);

			if (!$result["success"] && $this->fp !== false)
			{
				// If the server terminated the connection, then re-establish the connection and rerun the request.
				@fclose($this->fp);
				$this->fp = false;

				return $this->RunAPI($method, $apipath, $options, $expected, $decodebody, $idempotentkey);
			}

			if (!$result["success"])  return $result;

			if ($this->debug)
			{
				echo "------- RAW SEND START -------\n";
				echo $result["rawsend"];
				echo "------- RAW SEND END -------\n\n";

				echo "------- RAW RECEIVE START -------\n";
				echo $result["rawrecv"];
				echo "------- RAW RECEIVE END -------\n\n";
			}

			if (isset($result["fp"]) && is_resource($result["fp"]))  $this->fp = $result["fp"];
			else  $this->fp = false;

			if ($result["response"]["code"] != $expected)
			{
				if ($decodebody)
				{
					$data = json_decode($result["body"], true);
					if (is_array($data) && isset($data["error"]))
					{
						if ($data["error"]["type"] === "card_error")  return array("success" => false, "error" => self::Stripe_Translate("An error occurred while processing the card.  %s", self::Stripe_Translate($data["error"]["message"])), "errorcode" => $data["error"]["type"], "info" => $data["error"]);

						return array("success" => false, "error" => self::Stripe_Translate("An unexpected Stripe API error occurred.  Try again later."), "errorcode" => $data["error"]["type"], "info" => $data["error"]);
					}
				}

				return array("success" => false, "error" => self::Stripe_Translate("Expected a %d response from the Stripe API.  Received '%s'.", $expected, $result["response"]["line"]), "errorcode" => "unexpected_stripe_api_response", "info" => $result);
			}

			if ($decodebody)
			{
				$data = json_decode($result["body"], true);
				if (!is_array($data))  return array("success" => false, "error" => self::Stripe_Translate("Unable to decode the server response as JSON."), "errorcode" => "expected_json", "info" => $result);

				$result = array(
					"success" => true,
					"requestid" => $result["headers"]["Request-Id"][0],
					"data" => $data
				);
			}

			return $result;
		}

		protected static function Stripe_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>