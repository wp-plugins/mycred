<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Payment_Gateway class
 * @see http://mycred.me/add-ons/mycred_payment_gateway/
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Payment_Gateway' ) ) {
	abstract class myCRED_Payment_Gateway {

		protected $id;
		protected $core;
		protected $prefs = false;
		protected $current_user_id;
		protected $response;
		protected $request;
		protected $status;
		protected $errors;

		/**
		 * Construct
		 */
		function __construct( $args = array(), $gateway_prefs = NULL ) {
			// Current User ID
			$this->current_user_id = get_current_user_id();

			// Arguments
			if ( !empty( $args ) ) {
				foreach ( $args as $key => $value ) {
					$this->$key = $value;
				}
			}

			// Preferences
			if ( $gateway_prefs !== NULL ) {
				// Assign prefs if set
				if ( is_array( $gateway_prefs ) && isset( $gateway_prefs[$this->id] ) )
					$this->prefs = $gateway_prefs[$this->id];
				elseif ( is_object( $gateway_prefs ) && isset( $gateway_prefs->gateway_prefs[$this->id] ) )
					$this->prefs = $gateway_prefs->gateway_prefs[$this->id];

				// Apply defaults (if needed)
				if ( empty( $this->prefs ) || $this->prefs === false )
					$this->prefs = $this->defaults;
			}
			$this->core = mycred_get_settings();

			// Decode Log Entries
			add_filter( 'mycred_prep_template_tags',                          array( $this, 'decode_log_entries' ), 10, 2 );
			add_filter( 'mycred_parse_log_entry_buy_creds_with_' . $this->id, array( $this, 'log_entry' ), 10, 2          );
		}

		/**
		 * Process Purchase
		 * @since 0.1
		 * @version 1.0
		 */
		function process() {
			wp_die( __( 'function myCRED_Payment_Gateway::process() must be over-ridden in a sub-class.', 'mycred' ) );
		}

		/**
		 * Buy Creds Handler
		 * @since 0.1
		 * @version 1.0
		 */
		function buy() {
			wp_die( __( 'function myCRED_Payment_Gateway::buy() must be over-ridden in a sub-class.', 'mycred' ) );
		}

		/**
		 * Results Handler
		 * @since 0.1
		 * @version 1.0
		 */
		public function returning() { }

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		function preferences() {
			echo '<p>' . __( 'This Payment Gateway has no settings', 'mycred' ) . '</p>';
		}

		/**
		 * Sanatize Prefs
		 * @since 0.1
		 * @version 1.0
		 */
		function sanitise_preferences( $data ) {
			return $data;
		}

		/**
		 * Decode Log Entries
		 * @since 0.1
		 * @version 1.0
		 */
		function log_entry( $content, $log_entry ) {
			$content = $this->core->template_tags_user( $content, $log_entry->ref_id );
			return $content;
		}

		/**
		 * Get Field Name
		 * Returns the field name for the current gateway
		 * @since 0.1
		 * @version 1.0
		 */
		public function field_name( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( !is_numeric( $parent ) )
						$array[] = str_replace( '_', '-', $parent );

					if ( !empty( $child ) && !is_array( $child ) )
						$array[] = str_replace( '_', '-', $child );
				}
				$field = '[' . implode( '][', $array ) . ']';
			}
			else {
				$field = '[' . $field . ']';
			}
			return 'mycred_pref_buycreds[gateway_prefs][' . $this->id . ']' . $field;
		}

		/**
		 * Get Field ID
		 * Returns the field id for the current gateway
		 * @since 0.1
		 * @version 1.0
		 */
		public function field_id( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( !is_numeric( $parent ) )
						$array[] = str_replace( '_', '-', $parent );

					if ( !empty( $child ) && !is_array( $child ) )
						$array[] = str_replace( '_', '-', $child );
				}
				$field = implode( '-', $array );
			}
			else {
				$field = str_replace( '_', '-', $field );
			}
			return 'mycred-gateway-prefs-' . str_replace( '_', '-', $this->id ) . '-' . $field;
		}

		/**
		 * Callback URL
		 * @since 0.1
		 * @version 1.0
		 */
		public function callback_url() {
			return get_bloginfo( 'url' ) . '/?mycred_call=' . $this->id;
		}

		/**
		 * Purchase Page Header
		 * @since 0.1
		 * @version 1.0
		 */
		public function purchase_header( $title = '', $reload = false ) { ?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US"> 
<head> 
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $title; ?></title>
	<meta name="robots" content="noindex, nofollow" />
	<?php if ( $reload ) echo '<meta http-equiv="refresh" content="2;url=' . $reload . '" />'; ?>

	<style type="text/css">
		html { text-align: center; background-color: #FCFCFC; }
		body { text-align: center; width: 50%; margin: 100px auto 48px auto; border-radius: 5px; border: 1px solid #dedede; padding: 32px 24px 24px 24px; background-color: white; font-family: Arial; }
		.tl { text-align: left; }
		.tc { text-align: center; }
		.tr { text-align: right; }
		.tj { text-align: justify; }
		p { font-size: 10px; margin-top: 24px; text-align: left; }
		form p { margin-top: 0; }
		form label { display: block; }
		form .long { width: 40%; }
		form .medium { width: 20%; }
		form .short { width: 10%; }
		form .submit { text-align: right; }
		form .error label { color: red; }
		ul li { text-align: left; }
		pre { text-align: left; background-color: #eee; padding: 4px; white-space: pre-wrap; }
		a { color: #0E79BF; text-decoration: none; }
		img { margin: 0 auto 12px auto; display: block; float: none; }
		@media only screen and (min-width: 480px) and (max-width: 767px) {
			body { padding: 32px 12px; }
		}
		@media only screen and (max-width: 480px) {
			body { padding: 48px 12px; margin-top: 48px; }
			span { display: block; padding-top: 24px; }
		}
	</style>
</head>
<body>
<?php
		}

		/**
		 * Purchase Page Footer
		 * @since 0.1
		 * @version 1.0
		 */
		public function purchase_footer() { ?>

</body> 
</html>
<?php
		}

		/**
		 * Form Builder with Redirect
		 * Used by gateways that redirects users to a remote processor.
		 * @since 0.1
		 * @version 1.0
		 */
		public function form_with_redirect( $hidden_fields = array(), $location = '', $logo_url = '', $custom_html = '', $sales_data = '' ) {
			// Prep
			$id = $this->id;
			$goto = str_replace( '-', ' ', $id );
			$goto = str_replace( '_', ' ', $goto );
			$goto = __( 'Go to ', 'mycred' ) . ucwords( $goto );

			// Logo
			if ( empty( $logo_url ) )
				$logo_url = plugins_url( 'images/cred-icon32.png', myCRED_THIS );

			// Hidden Fields
			$hidden_fields = apply_filters( "mycred_{$id}_purchase_fields", $hidden_fields, $this ); ?>

	<form name="mycred_<?php echo str_replace( '-', '_', $this->id ); ?>_request" action="<?php echo $location; ?>" method="post">
<?php
			// Required hidden form fields
			foreach ( $hidden_fields as $name => $value ) {
				echo "\t" . '<input type="hidden" name="' . $name . '" value="' . $value . '" />' . "\n";
			}

			// Option to add custom HTML
			if ( !empty( $custom_html ) )
				echo $custom_html; ?>

		<div id="payment-gateway">
			<img src="<?php echo $logo_url; ?>" border="0" alt="<?php _e( 'Payment Gateway Logo', 'mycred' ); ?>" />
			<img src="<?php echo plugins_url( 'images/loading.gif', myCRED_PURCHASE ); ?>" alt="Loading" />
		</div>
<?php
		// Hidden submit button
		$hidden_submit = '<input type="submit" name="submit-form" value="' . $goto . '" />';
		if ( $this->prefs['sandbox'] )
			echo $hidden_submit;
		else
			echo '<noscript>' . $hidden_submit . '</noscript>'; ?>

	</form>
	<div>
		<p class="tc"><a href="javascript:void(0);" onclick="document.mycred_<?php echo str_replace( '-', '_', $this->id ); ?>_request.submit();return false;"><?php _e( 'Click here if you are not automatically redirected', 'mycred' ); ?></a></p>
	</div>
<?php
		// Sandbox
		if ( $this->prefs['sandbox'] ) {
			echo '<pre>request: ' . print_r( $hidden_fields, true ) . '</pre>';
			
			if ( !empty( $sales_data ) && isset( $hidden_fields[$sales_data] ) )
				echo '<pre>sales_data: ' . print_r( $this->decode_sales_data( $hidden_fields[$sales_data] ), true ) . '</pre>';
			
			if ( isset( $hidden_fields[$sales_data] ) )
				echo '<pre>length: ' . print_r( strlen( $hidden_fields[$sales_data] ), true ) . '</pre>';
		}
?>

	<script type="text/javascript">
		<?php if ( $this->prefs['sandbox'] ) echo '//'; ?>setTimeout( "document.mycred_<?php echo str_replace( '-', '_', $this->id ); ?>_request.submit()",2000 );
	</script>
<?php
		}

		/**
		 * Get To
		 * Returns either the current user id or if gifting is enabled and used
		 * the id of the user this is gifted to.
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_to() {
			// Gift to a user
			if ( $this->core->buy_creds['gifting']['members'] == 1 ) {
				if ( isset( $_POST['gift_to'] ) ) {
					$gift_to = trim( $_POST['gift_to'] );
					return abs( $gift_to );
				}
				elseif ( isset( $_GET['gift_to'] ) ) {
					$gift_to = trim( $_GET['gift_to'] );
					return abs( $gift_to );
				}
			}

			// Gifting author
			if ( $this->core->buy_creds['gifting']['authors'] == 1 ) {
				if ( isset( $_POST['post_id'] ) ) {
					$post_id = trim( $_POST['post_id'] );
					$post_id = abs( $post_id );
					$post = get_post( (int) $post_id );
					$author = $post->post_author;
					unset( $post );
					return (int) $author;
				}
				elseif ( isset( $_GET['post_id'] ) ) {
					$post_id = trim( $_GET['post_id'] );
					$post_id = abs( $post_id );
					$post = get_post( (int) $post_id );
					$author = $post->post_author;
					unset( $post );
					return (int) $author;
				}
			}

			return $this->current_user_id;
		}

		/**
		 * Get Thank You Page
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_thankyou() {
			if ( $this->core->buy_creds['thankyou']['use'] == 'page' ) {
				if ( empty( $this->core->buy_creds['thankyou']['page'] ) )
					return get_bloginfo( 'url' );
				else
					return get_permalink( $this->core->buy_creds['thankyou']['page'] );
			}
			else
				return get_bloginfo( 'url' ) . '/' . $this->core->buy_creds['thankyou']['custom'];
		}

		/**
		 * Get Cancelled Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function get_cancelled() {
			if ( $this->core->buy_creds['cancelled']['use'] == 'page' ) {
				if ( empty( $this->core->buy_creds['cancelled']['page'] ) )
					return get_bloginfo( 'url' );
				else
					return get_permalink( $this->core->buy_creds['cancelled']['page'] );
			}
			else
				return get_bloginfo( 'url' ) . '/' . $this->core->buy_creds['cancelled']['custom'];
		}

		/**
		 * Get Entry
		 * Returns the appropriate log entry template.
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_entry( $_to, $_from ) {
			// Log entry
			if ( $_to == $_from ) return $this->core->buy_creds['log'];

			if ( $this->core->buy_creds['gifting']['members'] == 1 || $this->core->buy_creds['gifting']['authors'] == 1 )
				return $this->core->buy_creds['gifting']['log'];

			return $this->core->buy_creds['log'];
		}

		/**
		 * POST to data
		 * @since 0.1
		 * @version 1.2
		 */
		public function POST_to_data( $unset = false ) {
			$data = array();
			foreach ( $_POST as $key => $value ) {
				$data[$key] = stripslashes( $value );
			}
			if ( $unset )
				unset( $_POST );

			return $data;
		}

		/**
		 * Transaction ID unique
		 * Searches the Log for a given transaction.
		 *
		 * @returns (bool) true if transaction id is unique or false
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function transaction_id_is_unique( $transaction_id = '' ) {
			if ( empty( $transaction_id ) ) return false;

			global $wpdb;

			// Make sure this is a new transaction
			$sql = "SELECT * FROM {$this->core->log_table} WHERE ref = %s AND data LIKE %s;";

			$gateway = str_replace( '-', '_', $this->id );
			$gateway_id = 'buy_creds_with_' . $gateway;

			$check = $wpdb->get_results( $wpdb->prepare( $sql, $gateway_id, '%' . $transaction_id . '%' ) );
			if ( $wpdb->num_rows > 0 ) return false;

			return true;
		}

		/**
		 * Create Token
		 * Returns a wp nonce
		 * @since 0.1
		 * @version 1.0
		 */
		public function create_token() {
			return wp_create_nonce( 'mycred-buy-' . $this->id );
		}

		/**
		 * Verify Token
		 * Based on wp_verify_nonce() this function requires the user id used when the token
		 * was created as by default not logged in users would generate different tokens causing us
		 * to fail.
		 *
		 * @param $user_id (int) required user id
		 * @param $nonce (string) required nonce to check
		 * @param $action (string) required nonce id
		 * @returns true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function verify_token( $user_id, $nonce ) {
			$uid = (int) $user_id;

			$i = wp_nonce_tick();

			// Nonce generated 0-12 hours ago
			if ( substr( wp_hash( $i . 'mycred-buy-' . $this->id . $uid, 'nonce' ), -12, 10 ) == $nonce )
				return true;
			
			return false;
		}

		/**
		 * Encode Sales Data
		 * @since 0.1
		 * @version 1.0
		 */
		public function encode_sales_data( $data ) {
			// Include
			require_once( myCRED_INCLUDES_DIR . 'mycred-protect.php' );
			$protect = new myCRED_Protect();
			return $protect->do_encode( $data );
		}

		/**
		 * Decode Sales Data
		 * @since 0.1
		 * @version 1.0
		 */
		public function decode_sales_data( $data ) {
			// Include
			require_once( myCRED_INCLUDES_DIR . 'mycred-protect.php' );
			$protect = new myCRED_Protect();
			return $protect->do_decode( $data );
		}

		/**
		 * Currencies Dropdown
		 * @since 0.1
		 * @version 1.0
		 */
		public function currencies_dropdown( $name = '' ) {
			$currencies = array(
				'USD' => 'US Dollars',
				'AUD' => 'Australian Dollars',
				'CAD' => 'Canadian Dollars',
				'EUR' => 'Euro',
				'GBP' => 'British Pound Sterling',
				'JPY' => 'Japanese Yen',
				'NZD' => 'New Zealand Dollars',
				'CHF' => 'Swiss Francs',
				'HKD' => 'Hong Kong Dollars',
				'SGD' => 'Singapore Dollars',
				'SEK' => 'Swedish Kronor',
				'DKK' => 'Danish Kroner',
				'PLN' => 'Polish Zloty',
				'NOK' => 'Norwegian Kronor',
				'HUF' => 'Hungarian Forint',
				'CZK' => 'Check Koruna',
				'ILS' => 'Israeli Shekel',
				'MXN' => 'Mexican Peso',
				'BRL' => 'Brazilian Real',
				'MYR' => 'Malaysian Ringgits',
				'PHP' => 'Philippine Pesos',
				'TWD' => 'Taiwan New Dollars',
				'THB' => 'Thai Baht'
			);
			$currencies = apply_filters( 'mycred_dropdown_currencies', $currencies );

			echo '<select name="' . $this->field_name( $name ) . '" id="' . $this->field_id( $name ) . '">';
			echo '<option value="">' . __( 'Select', 'mycred' ) . '</option>';
			foreach ( $currencies as $code => $cname ) {
				echo '<option value="' . $code . '"';
				if ( $this->prefs[$name] == $code ) echo ' selected="selected"';
				echo '>' . $cname . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Item Type Dropdown
		 * @since 0.1
		 * @version 1.0
		 */
		public function item_types_dropdown( $name = '' ) {
			$types = array(
				'product'  => 'Product',
				'service'  => 'Service',
				'donation' => 'Donation'
			);
			$types = apply_filters( 'mycred_dropdown_item_types', $types );

			echo '<select name="' . $this->field_name( $name ) . '" id="' . $this->field_id( $name ) . '">';
			echo '<option value="">' . __( 'Select', 'mycred' ) . '</option>';
			foreach ( $types as $code => $cname ) {
				echo '<option value="' . $code . '"';
				if ( $this->prefs[$name] == $code ) echo ' selected="selected"';
				echo '>' . $cname . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Countries Dropdown Options
		 * @since 0.1
		 * @version 1.0
		 */
		public function list_option_countries( $selected = '' ) {
			$countries = array (
				"US"  =>  "UNITED STATES",
				"AF"  =>  "AFGHANISTAN",
				"AL"  =>  "ALBANIA",
				"DZ"  =>  "ALGERIA",
				"AS"  =>  "AMERICAN SAMOA",
				"AD"  =>  "ANDORRA",
				"AO"  =>  "ANGOLA",
				"AI"  =>  "ANGUILLA",
				"AQ"  =>  "ANTARCTICA",
				"AG"  =>  "ANTIGUA AND BARBUDA",
				"AR"  =>  "ARGENTINA",
				"AM"  =>  "ARMENIA",
				"AW"  =>  "ARUBA",
				"AU"  =>  "AUSTRALIA",
				"AT"  =>  "AUSTRIA",
				"AZ"  =>  "AZERBAIJAN",
				"BS"  =>  "BAHAMAS",
				"BH"  =>  "BAHRAIN",
				"BD"  =>  "BANGLADESH",
				"BB"  =>  "BARBADOS",
				"BY"  =>  "BELARUS",
				"BE"  =>  "BELGIUM",
				"BZ"  =>  "BELIZE",
				"BJ"  =>  "BENIN",
				"BM"  =>  "BERMUDA",
				"BT"  =>  "BHUTAN",
				"BO"  =>  "BOLIVIA",
				"BA"  =>  "BOSNIA AND HERZEGOVINA",
				"BW"  =>  "BOTSWANA",
				"BV"  =>  "BOUVET ISLAND",
				"BR"  =>  "BRAZIL",
				"IO"  =>  "BRITISH INDIAN OCEAN TERRITORY",
				"BN"  =>  "BRUNEI DARUSSALAM",
				"BG"  =>  "BULGARIA",
				"BF"  =>  "BURKINA FASO",
				"BI"  =>  "BURUNDI",
				"KH"  =>  "CAMBODIA",
				"CM"  =>  "CAMEROON",
				"CA"  =>  "CANADA",
				"CV"  =>  "CAPE VERDE",
				"KY"  =>  "CAYMAN ISLANDS",
				"CF"  =>  "CENTRAL AFRICAN REPUBLIC",
				"TD"  =>  "CHAD",
				"CL"  =>  "CHILE",
				"CL"  =>  "CHILE",
				"CN"  =>  "CHINA",
				"CX"  =>  "CHRISTMAS ISLAND",
				"CC"  =>  "COCOS (KEELING) ISLANDS",
				"CO"  =>  "COLOMBIA",
				"KM"  =>  "COMOROS",
				"CG"  =>  "CONGO",
				"CD"  =>  "CONGO, THE DEMOCRATIC REPUBLIC OF THE",
				"CK"  =>  "COOK ISLANDS",
				"CR"  =>  "COSTA RICA",
				"CI"  =>  "COTE D'IVOIRE",
				"HR"  =>  "CROATIA",
				"CU"  =>  "CUBA",
				"CY"  =>  "CYPRUS",
				"CZ"  =>  "CZECH REPUBLIC",
				"DK"  =>  "DENMARK",
				"DJ"  =>  "DJIBOUTI",
				"DM"  =>  "DOMINICA",
				"DO"  =>  "DOMINICAN REPUBLIC",
				"EC"  =>  "ECUADOR",
				"EG"  =>  "EGYPT",
				"SV"  =>  "EL SALVADOR",
				"GQ"  =>  "EQUATORIAL GUINEA",
				"ER"  =>  "ERITREA",
				"EE"  =>  "ESTONIA",
				"ET"  =>  "ETHIOPIA",
				"FK"  =>  "FALKLAND ISLANDS (MALVINAS)",
				"FO"  =>  "FAROE ISLANDS",
				"FJ"  =>  "FIJI",
				"FI"  =>  "FINLAND",
				"FR"  =>  "FRANCE",
				"GF"  =>  "FRENCH GUIANA",
				"PF"  =>  "FRENCH POLYNESIA",
				"TF"  =>  "FRENCH SOUTHERN TERRITORIES",
				"GA"  =>  "GABON",
				"GM"  =>  "GAMBIA",
				"GE"  =>  "GEORGIA",
				"DE"  =>  "GERMANY",
				"GH"  =>  "GHANA",
				"GI"  =>  "GIBRALTAR",
				"GR"  =>  "GREECE",
				"GL"  =>  "GREENLAND",
				"GD"  =>  "GRENADA",
				"GP"  =>  "GUADELOUPE",
				"GU"  =>  "GUAM",
				"GT"  =>  "GUATEMALA",
				"GN"  =>  "GUINEA",
				"GW"  =>  "GUINEA-BISSAU",
				"GY"  =>  "GUYANA",
				"HT"  =>  "HAITI",
				"HM"  =>  "HEARD ISLAND AND MCDONALD ISLANDS",
				"VA"  =>  "HOLY SEE (VATICAN CITY STATE)",
				"HN"  =>  "HONDURAS",
				"HK"  =>  "HONG KONG",
				"HU"  =>  "HUNGARY",
				"IS"  =>  "ICELAND",
				"IN"  =>  "INDIA",
				"ID"  =>  "INDONESIA",
				"IR"  =>  "IRAN, ISLAMIC REPUBLIC OF",
				"IQ"  =>  "IRAQ",
				"IE"  =>  "IRELAND",
				"IL"  =>  "ISRAEL",
				"IT"  =>  "ITALY",
				"JM"  =>  "JAMAICA",
				"JP"  =>  "JAPAN",
				"JO"  =>  "JORDAN",
				"KZ"  =>  "KAZAKHSTAN",
				"KE"  =>  "KENYA",
				"KI"  =>  "KIRIBATI",
				"KP"  =>  "KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF",
				"KR"  =>  "KOREA, REPUBLIC OF",
				"KW"  =>  "KUWAIT",
				"KG"  =>  "KYRGYZSTAN",
				"LA"  =>  "LAO PEOPLE'S DEMOCRATIC REPUBLIC",
				"LV"  =>  "LATVIA",
				"LB"  =>  "LEBANON",
				"LS"  =>  "LESOTHO",
				"LR"  =>  "LIBERIA",
				"LY"  =>  "LIBYAN ARAB JAMAHIRIYA",
				"LI"  =>  "LIECHTENSTEIN",
				"LT"  =>  "LITHUANIA",
				"LU"  =>  "LUXEMBOURG",
				"MO"  =>  "MACAO",
				"MK"  =>  "MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF",
				"MG"  =>  "MADAGASCAR",
				"MW"  =>  "MALAWI",
				"MY"  =>  "MALAYSIA",
				"MV"  =>  "MALDIVES",
				"ML"  =>  "MALI",
				"MT"  =>  "MALTA",
				"MH"  =>  "MARSHALL ISLANDS",
				"MQ"  =>  "MARTINIQUE",
				"MR"  =>  "MAURITANIA",
				"MU"  =>  "MAURITIUS",
				"YT"  =>  "MAYOTTE",
				"MX"  =>  "MEXICO",
				"FM"  =>  "MICRONESIA, FEDERATED STATES OF",
				"MD"  =>  "MOLDOVA, REPUBLIC OF",
				"MC"  =>  "MONACO",
				"MN"  =>  "MONGOLIA",
				"MS"  =>  "MONTSERRAT",
				"MA"  =>  "MOROCCO",
				"MZ"  =>  "MOZAMBIQUE",
				"MM"  =>  "MYANMAR",
				"NA"  =>  "NAMIBIA",
				"NR"  =>  "NAURU",
				"NP"  =>  "NEPAL",
				"NL"  =>  "NETHERLANDS",
				"AN"  =>  "NETHERLANDS ANTILLES",
				"NC"  =>  "NEW CALEDONIA",
				"NZ"  =>  "NEW ZEALAND",
				"NI"  =>  "NICARAGUA",
				"NE"  =>  "NIGER",
				"NG"  =>  "NIGERIA",
				"NU"  =>  "NIUE",
				"NF"  =>  "NORFOLK ISLAND",
				"MP"  =>  "NORTHERN MARIANA ISLANDS",
				"NO"  =>  "NORWAY",
				"OM"  =>  "OMAN",
				"PK"  =>  "PAKISTAN",
				"PW"  =>  "PALAU",
				"PS"  =>  "PALESTINIAN TERRITORY, OCCUPIED",
				"PA"  =>  "PANAMA",
				"PG"  =>  "PAPUA NEW GUINEA",
				"PY"  =>  "PARAGUAY",
				"PE"  =>  "PERU",
				"PH"  =>  "PHILIPPINES",
				"PN"  =>  "PITCAIRN",
				"PL"  =>  "POLAND",
				"PT"  =>  "PORTUGAL",
				"PR"  =>  "PUERTO RICO",
				"QA"  =>  "QATAR",
				"RE"  =>  "REUNION",
				"RO"  =>  "ROMANIA",
				"RU"  =>  "RUSSIAN FEDERATION",
				"RW"  =>  "RWANDA",
				"SH"  =>  "SAINT HELENA",
				"KN"  =>  "SAINT KITTS AND NEVIS",
				"LC"  =>  "SAINT LUCIA",
				"PM"  =>  "SAINT PIERRE AND MIQUELON",
				"VC"  =>  "SAINT VINCENT AND THE GRENADINES",
				"WS"  =>  "SAMOA",
				"SM"  =>  "SAN MARINO",
				"ST"  =>  "SAO TOME AND PRINCIPE",
				"SA"  =>  "SAUDI ARABIA",
				"SN"  =>  "SENEGAL",
				"CS"  =>  "SERBIA AND MONTENEGRO",
				"SC"  =>  "SEYCHELLES",
				"SL"  =>  "SIERRA LEONE",
				"SG"  =>  "SINGAPORE",
				"SK"  =>  "SLOVAKIA",
				"SI"  =>  "SLOVENIA",
				"SB"  =>  "SOLOMON ISLANDS",
				"SO"  =>  "SOMALIA",
				"ZA"  =>  "SOUTH AFRICA",
				"GS"  =>  "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS",
				"ES"  =>  "SPAIN",
				"LK"  =>  "SRI LANKA",
				"SD"  =>  "SUDAN",
				"SR"  =>  "SURINAME",
				"SJ"  =>  "SVALBARD AND JAN MAYEN",
				"SZ"  =>  "SWAZILAND",
				"SE"  =>  "SWEDEN",
				"CH"  =>  "SWITZERLAND",
				"SY"  =>  "SYRIAN ARAB REPUBLIC",
				"TW"  =>  "TAIWAN, PROVINCE OF CHINA",
				"TJ"  =>  "TAJIKISTAN",
				"TZ"  =>  "TANZANIA, UNITED REPUBLIC OF",
				"TH"  =>  "THAILAND",
				"TL"  =>  "TIMOR-LESTE",
				"TG"  =>  "TOGO",
				"TK"  =>  "TOKELAU",
				"TO"  =>  "TONGA",
				"TT"  =>  "TRINIDAD AND TOBAGO",
				"TN"  =>  "TUNISIA",
				"TR"  =>  "TURKEY",
				"TM"  =>  "TURKMENISTAN",
				"TC"  =>  "TURKS AND CAICOS ISLANDS",
				"TV"  =>  "TUVALU",
				"UG"  =>  "UGANDA",
				"UA"  =>  "UKRAINE",
				"AE"  =>  "UNITED ARAB EMIRATES",
				"GB"  =>  "UNITED KINGDOM",
				"US"  =>  "UNITED STATES",
				"UM"  =>  "UNITED STATES MINOR OUTLYING ISLANDS",
				"UY"  =>  "URUGUAY",
				"UZ"  =>  "UZBEKISTAN",
				"VU"  =>  "VANUATU",
				"VE"  =>  "VENEZUELA",
				"VN"  =>  "VIET NAM",
				"VG"  =>  "VIRGIN ISLANDS, BRITISH",
				"VI"  =>  "VIRGIN ISLANDS, U.S.",
				"WF"  =>  "WALLIS AND FUTUNA",
				"EH"  =>  "WESTERN SAHARA",
				"YE"  =>  "YEMEN",
				"ZM"  =>  "ZAMBIA",
				"ZW"  =>  "ZIMBABWE"
			);
			$countries = apply_filters( 'mycred_list_option_countries', $countries );
			
			foreach ( $countries as $code => $cname ) {
				echo '<option value="' . $code . '"';
				if ( $selected == $code ) echo ' selected="selected"';
				echo '>' . $cname . '</option>';
			}
		}

		/**
		 * US States Dropdown Options
		 * @since 0.1
		 * @version 1.0
		 */
		public function list_option_us_states( $selected = '', $non_us = false ) {
			$states = array (
				"AL"  =>  "Alabama",
				"AK"  =>  "Alaska",
				"AZ"  =>  "Arizona",
				"AR"  =>  "Arkansas",
				"CA"  =>  "California",
				"CO"  =>  "Colorado",
				"CT"  =>  "Connecticut",
				"DC"  =>  "D.C.",
				"DE"  =>  "Delaware",
				"FL"  =>  "Florida",
				"GA"  =>  "Georgia",
				"HI"  =>  "Hawaii",
				"ID"  =>  "Idaho",
				"IL"  =>  "Illinois",
				"IN"  =>  "Indiana",
				"IA"  =>  "Iowa",
				"KS"  =>  "Kansas",
				"KY"  =>  "Kentucky",
				"LA"  =>  "Louisiana",
				"ME"  =>  "Maine",
				"MD"  =>  "Maryland",
				"MA"  =>  "Massachusetts",
				"MI"  =>  "Michigan",
				"MN"  =>  "Minnesota",
				"MS"  =>  "Mississippi",
				"MO"  =>  "Missouri",
				"MT"  =>  "Montana",
				"NE"  =>  "Nebraska",
				"NV"  =>  "Nevada",
				"NH"  =>  "New Hampshire",
				"NJ"  =>  "New Jersey",
				"NM"  =>  "New Mexico",
				"NY"  =>  "New York",
				"NC"  =>  "North Carolina",
				"ND"  =>  "North Dakota",
				"OH"  =>  "Ohio",
				"OK"  =>  "Oklahoma",
				"OR"  =>  "Oregon",
				"PA"  =>  "Pennsylvania",
				"RI"  =>  "Rhode Island",
				"SC"  =>  "South Carolina",
				"SD"  =>  "South Dakota",
				"TN"  =>  "Tennessee",
				"TX"  =>  "Texas",
				"UT"  =>  "Utah",
				"VT"  =>  "Vermont",
				"VA"  =>  "Virginia",
				"WA"  =>  "Washington",
				"WV"  =>  "West Virginia",
				"WI"  =>  "Wisconsin",
				"WY"  =>  "Wyoming"
			);
			$states = apply_filters( 'mycred_list_option_us', $states );

			$outside = __( 'Outside US', 'mycred' );
			if ( $non_us == 'top' ) echo '<option value="">' . $outside . '</option>';
			foreach ( $states as $code => $cname ) {
				echo '<option value="' . $code . '"';
				if ( $selected == $code ) echo ' selected="selected"';
				echo '>' . $cname . '</option>';
			}
			if ( $non_us == 'bottom' ) echo '<option value="">' . $outside . '</option>';
		}

		/**
		 * Months Dropdown Options
		 * @since 0.1
		 * @version 1.0
		 */
		public function list_option_months( $selected = '' ) {
			$months = array (
				"01"  =>  "January",
				"02"  =>  "February",
				"03"  =>  "March",
				"04"  =>  "April",
				"05"  =>  "May",
				"06"  =>  "June",
				"07"  =>  "July",
				"08"  =>  "August",
				"09"  =>  "September",
				"10"  =>  "October",
				"11"  =>  "November",
				"12"  =>  "December"
			);

			foreach ( $months as $number => $text ) {
				echo '<option value="' . $number . '"';
				if ( $selected == $number ) echo ' selected="selected"';
				echo '>' . $text . '</option>';
			}
		}

		/**
		 * Years Dropdown Options
		 * @since 0.1
		 * @version 1.0
		 */
		public function list_option_card_years( $selected = '', $number = 16 ) {
			$yy = date_i18n( 'y' );
			$yyyy = date_i18n( 'Y' );
			$count = 0;
			$options = array();

			while ( $count <= (int) $number ) {
				$count++;
				if ( $count > 1 ) {
					$yy++;
					$yyyy++;
				}
				$options[$yy] = $yyyy;
			}

			foreach ( $options as $key => $value ) {
				echo '<option value="' . $key . '"';
				if ( $selected == $key ) echo ' selected="selected"';
				echo '>' . $value . '</option>';
			}
		}

		/**
		 * Contextual Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function help( $screen_id, $screen ) {
			if ( $screen_id != 'mycred_page_myCRED_page_gateways' ) return;
		}
	}
}
?>