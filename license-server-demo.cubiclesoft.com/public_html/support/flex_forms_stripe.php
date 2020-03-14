<?php
	// Add a Stripe field type.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class FlexForms_Stripe
	{
		public static function GetBrandMap()
		{
			$brandmap = array(
				"visa" => array("name" => "Visa", "img" => "visa_28x18.png", "css" => "stripecard-visa"),
				"mastercard" => array("name" => "Mastercard", "img" => "mastercard_28x18.png", "css" => "stripecard-mastercard"),
				"amex" => array("name" => "American Express", "img" => "amex_28x18.png", "css" => "stripecard-amex"),
				"discover" => array("name" => "Discover", "img" => "discover_28x18.png", "css" => "stripecard-discover"),
				"diners" => array("name" => "Diners Club", "img" => "diners_club_28x18.png", "css" => "stripecard-diners_club"),
				"jcb" => array("name" => "JCB", "img" => "jcb_28x18.png", "css" => "stripecard-jcb"),
				"unionpay" => array("name" => "UnionPay", "img" => "unionpay_28x18.png", "css" => "stripecard-unionpay"),
				"" => array("name" => "Other", "img" => "card_front_28x18.png", "css" => "stripecard-card_front")
			);

			return $brandmap;
		}

		public static function Init(&$state, &$options)
		{
			if (!isset($state["modules_stripe"]))  $state["modules_stripe"] = false;
		}

		public static function FieldType(&$state, $num, &$field, $id)
		{
			if ($field["type"] === "stripe" && isset($field["pubkey"]) && isset($field["elements"]))
			{
				$id .= "_stripe";

?>
<div class="formitemdata">
	<div class="stripeitemwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . "\""; ?>>
		<div id="<?php echo htmlspecialchars($id); ?>" class="stripeiteminner">
<?php
				// Output element placeholders.
				foreach ($field["elements"] as $elements)
				{
?>
			<div class="stripeitemrow">
<?php
					foreach ($elements as $key => $opts)
					{
?>
				<div class="stripeitemcol"><div id="<?php echo htmlspecialchars($id . "_" . $key); ?>"<?php if ($key === "cardNumber")  echo " class=\"stripecard_before stripecard-card_front\""; ?><?php if ($key === "cardCvc")  echo " class=\"stripecard_after stripecard-card_back\""; ?>></div></div>
<?php
					}
?>
			</div>
<?php
				}
?>
		</div>
	</div>
</div>
<?php

				if ($state["modules_stripe"] === false)
				{
					$state["css"]["modules-stripe"] = array("mode" => "link", "dependency" => false, "src" => $state["supporturl"] . "/flex_forms_stripe.css");

					// While Stripe JS itself doesn't require jQuery, the code later on does.
					$state["js"]["modules-stripe-js"] = array("mode" => "src", "dependency" => "jquery", "src" => "https://js.stripe.com/v3/", "detect" => "Stripe");

					$state["modules_stripe"] = true;
				}

				if (!isset($field["style"]))  $field["style"] = array();
				$field["style"]["__flexforms"] = true;

				// Allow each Stripe instance to be fully customized beyond basic support.
				// Valid options:  https://stripe.com/docs/stripe-js/reference#stripe-elements
				$options = array("locale" => "auto");
				if (isset($field["options"]))
				{
					foreach ($field["options"] as $key => $val)  $options[$key] = $val;
				}

				if (!isset($field["datamap"]))  $field["datamap"] = array();
				if (!isset($field["process"]))  $field["process"] = "";

				// Queue up the necessary Javascript for later output.
				ob_start();
?>
	jQuery(function() {
		var EscapeHTML = function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};

			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		}

		var DisplayError = function(msg) {
			jQuery('#<?php echo FlexForms::JSSafe($id); ?>').closest('.formitem').find('.formitemresult').remove();

			if (msg !== '')  jQuery('#<?php echo FlexForms::JSSafe($id); ?>').closest('.formitem').append('<div class="formitemresult"><div class="formitemerror">' + EscapeHTML(msg) + '</div></div>');
		};

		var brandmap = <?php echo json_encode(self::GetBrandMap(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

		if (window.Stripe)
		{
			var stripe = new Stripe('<?php echo FlexForms::JSSafe($field["pubkey"]); ?>', <?php echo json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>);
			var elements = stripe.elements();

			var mounted = [];
			var element;

<?php
				// Create elements.
				foreach ($field["elements"] as $elements)
				{
					foreach ($elements as $key => $opts)
					{
						if (!isset($opts["style"]))  $opts["style"] = $field["style"];

?>
			element = elements.create('<?php echo FlexForms::JSSafe($key); ?>', <?php echo json_encode($opts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>);
			element.mount('#<?php echo FlexForms::JSSafe($id . "_" . $key); ?>');

			(function() {
				var $elem = $('#<?php echo FlexForms::JSSafe($id . "_" . $key); ?>');
				var currbrand = 'stripecard-card_front';

				element.addEventListener('change', function(e) {
<?php
						if ($key === "cardNumber")
						{
?>
					if (e.brand)
					{
						$elem.removeClass(currbrand);

						currbrand = (brandmap[e.brand] ? brandmap[e.brand].css : brandmap[''].css);

						$elem.addClass(currbrand);
					}
<?php
						}
?>

					DisplayError(e.error ? e.error.message : '');
				});
			})();

			mounted.push(element);
<?php
					}
				}
?>

			// Handle form submission.
			var form = document.getElementById('<?php echo FlexForms::JSSafe($state["formid"]); ?>');

			form.addEventListener('submit', function(e) {
				e.preventDefault();

				var message = '';
				var opts = {};
				var datamap = <?php echo json_encode($field["datamap"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
				for (var x in datamap)
				{
					var elem = $(form).find('[name="' + datamap[x] + '"]');

					if (!elem.length)  message = '<?php echo FlexForms::JSSafe(FlexForms::FFTranslate("An expected field is missing")); ?> (' + x + ')';
					else if (elem.val() === '')  message = '<?php echo FlexForms::JSSafe(FlexForms::FFTranslate("Please fill in all required fields.")); ?> (' + x + ')';
					else
					{
						var path = x.split('.');
						var opts2 = opts;
						for (var x2 = 0; x2 < path.length - 1; x2++)
						{
							if (opts2[path[x2]] === undefined)  opts2[path[x2]] = {};

							opts2 = opts2[path[x2]];
						}

						opts2[path[path.length - 1]] = elem.val();
					}

					if (message !== '')  break;
				}

				if (message !== '')  DisplayError(message);
				else
				{
					// Disable submit buttons and inject process text.
					$(form).find('input[type=submit]').prop('disabled', true);

					var processelem = $('<span>').addClass('stripeprocessing').html('&nbsp;&nbsp; <?php echo FlexForms::JSSafe(FlexForms::FFTranslate($field["process"])); ?>');
					$(form).find('.formsubmit').append(processelem);

					stripe.createPaymentMethod('card', mounted[0], { 'billing_details': opts }).then(function(result) {
						if (result.error)
						{
							DisplayError(result.error.message);

							$(form).find('input[type=submit]').prop('disabled', false);
							processelem.remove();
						}
						else
						{
							$(form).find('input[name="<?php echo FlexForms::JSSafe($field["name"]); ?>"]').remove();
							$(form).append($('<input>').attr('type', 'hidden').attr('name', '<?php echo FlexForms::JSSafe($field["name"]); ?>').val(result.paymentMethod.id));

							form.submit();
						}
					});
				}
			});
		}
		else
		{
			DisplayError('<?php echo FlexForms::JSSafe(FlexForms::FFTranslate("Warning:  Unable to load required card processing library from Stripe.  Try again later.")); ?>');
		}

<?php
				if (isset($field["error"]) && $field["error"] != "")
				{
?>
		DisplayError('<?php echo FlexForms::JSSafe($field["error"]); ?>');
<?php
				}
?>
	});
<?php
				$state["js"]["modules-stripe-js|" . $id] = array("mode" => "inline", "dependency" => "modules-stripe-js", "src" => ob_get_contents());
				ob_end_clean();

				unset($field["error"]);
			}
		}
	}

	// Register form handlers.
	if (is_callable("FlexForms::RegisterFormHandler"))
	{
		FlexForms::RegisterFormHandler("init", "FlexForms_Stripe::Init");
		FlexForms::RegisterFormHandler("field_type", "FlexForms_Stripe::FieldType");
	}
?>