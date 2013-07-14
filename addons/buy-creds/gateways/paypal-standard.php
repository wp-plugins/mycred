<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_PayPal class
 * PayPal Payments Standard - Payment Gateway
 * 
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_PayPal_Standard' ) ) {
	class myCRED_PayPal_Standard extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			parent::__construct( array(
				'id'       => 'paypal-standard',
				'defaults' => array(
					'sandbox'   => 0,
					'currency'  => '',
					'account'   => '',
					'item_name' => __( 'Purchase of myCRED %plural%', 'mycred' ),
					'exchange'  => 1
				)
			), $gateway_prefs );
		}

		/**
		 * Process Handler
		 * @since 0.1
		 * @version 1.2
		 */
		public function process() {
			// Prep
			$id = $this->id;
			$error = false;
			$log_entry = array();

			// PayPal Host
			if ( $this->prefs['sandbox'] )
				$host = 'www.sandbox.paypal.com';
			else
				$host = 'www.paypal.com';

			$data = $this->POST_to_data();

			// Prep Respons
			$request = 'cmd=_notify-validate';
			$get_magic_quotes_exists = false;
			if ( function_exists( 'get_magic_quotes_gpc' ) )
				$get_magic_quotes_exists = true;

			foreach ( $data as $key => $value ) {
				if ( $get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1 )
					$value = urlencode( stripslashes( $value ) );
				else
					$value = urlencode( $value );

				$request .= "&$key=$value";
			}

			// Call PayPal
			$curl_attempts = apply_filters( 'mycred_paypal_standard_max_attempts', 3 );
			$attempt = 1;
			$result = '';
			// We will make a x number of curl attempts before finishing with a fsock.
			// Success ends loop.
			do {

				$call = curl_init( "https://$host/cgi-bin/webscr" );
				curl_setopt( $call, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
				curl_setopt( $call, CURLOPT_POST, 1 );
				curl_setopt( $call, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $call, CURLOPT_POSTFIELDS, $request );
				curl_setopt( $call, CURLOPT_SSL_VERIFYPEER, 1 );
				curl_setopt( $call, CURLOPT_CAINFO, myCRED_PURCHASE_DIR . '/cacert.pem' );
				curl_setopt( $call, CURLOPT_SSL_VERIFYHOST, 1 );
				curl_setopt( $call, CURLOPT_FRESH_CONNECT, 1 );
				curl_setopt( $call, CURLOPT_FORBID_REUSE, 1 );
				curl_setopt( $call, CURLOPT_HTTPHEADER, array( 'Connection: Close' ) );
				$result = curl_exec( $call );

				// End on success
				if ( $result !== false ) {
					curl_close( $call );
					break;
				}

				$log_entry[] = 'curl attempt: ' . $attempt . ' failed. Error: [' . curl_errno( $call ) . '][' . curl_error( $call ) . ']';

				curl_close( $call );

				// Final try
				if ( $attempt == $curl_attempts ) {
					$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
					$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
					$header .= "Content-Length: " . strlen( $request ) . "\r\n\r\n";
					$fp = fsockopen( 'ssl://' . $host, 443, $errno, $errstr, 30 );
					if ( !$fp ) {
						$log_entry[] = 'fsockopen try failed as well [' . $errno . '][' . $errstr . ']!';
					}
					else {
						fputs( $fp, $header . $request );
						while ( !feof( $fp ) ) {
							$result = fgets( $fp, 1024 );
						}
						fclose( $fp );
					}
				}
				$attempt++;

			} while ( $attempt <= $curl_attempts );

			$sales_data = $this->decode_sales_data( $data['custom'] );
			$s_data = explode( '|', $sales_data );
			// to|from|amount|cost|currency|token|extra
			list ( $_to, $_from, $amount, $cost, $_currency, $token, $other ) = $s_data;

			// Request is verified
			if ( strcmp( $result, "VERIFIED" ) == 0 ) {

				// Verify token
				if ( !$this->verify_token( $_from, trim( $token ), 'mycred-buy-paypal-standard' ) ) {
					$log_entry[] = 'Could not verify token: [' . $token . ']';
					$error = true;
				}

				// Make sure Purchase is unique
				if ( !$this->transaction_id_is_unique( $data['txn_id'] ) ) {
					$log_entry[] = 'Transaction ID previously used: [' . $data['txn_id'] . ']';
					$error = true;
				}

				// Make sure accounts match
				if ( $data['receiver_email'] != trim( $this->prefs['account'] ) ) {
					$log_entry[] = 'Recipient Email mismatch: [' . $data['receiver_email'] . ']';
					$error = true;
				}

				// Verify Currency
				if ( $data['mc_currency'] != $this->prefs['currency'] || $data['mc_currency'] != $_currency ) {
					$log_entry[] = 'Currency mismatch: [' . $data['mc_currency'] . '] [' . $_currency . ']';
					$error = true;
				}

				// Verify Cost
				$amount = $this->core->number( $amount );
				$_cost = $amount*$this->prefs['exchange'];
				$_cost = number_format( $cost, 2, '.', '' );
				if ( $cost != $_cost ) {
					$log_entry[] = 'Amount mismatch: [' . $cost . '] [' . $_cost . ']';
					$error = true;
				}

				// Handle Payment Status
				if ( $error === false ) {
					// Completed transaction
					if ( $data['payment_status'] == 'Completed' ) {
						$entry = $this->get_entry( $_to, $_from );
						$entry = str_replace( '%gateway%', 'PayPal', $entry );
						if ( $this->prefs['sandbox'] ) $entry = 'TEST ' . $entry;

						$data = array(
							'txn_id'       => $data['txn_id'],
							'payer_id'     => $data['payer_id'],
							'name'         => $data['first_name'] . ' ' . $data['last_name'],
							'ipn_track_id' => $data['ipn_track_id'],
							'sales_data'   => $sales_data
						);

						// Add creds
						$this->core->add_creds(
							'buy_creds_with_paypal_standard',
							$_to,
							$amount,
							$entry,
							$_from,
							$data
						);

						$log_entry[] = 'CREDs Added.';
						do_action( "mycred_buy_cred_{$id}_approved", $data );
					}

					// Pending transaction
					elseif ( $data['payment_status'] == 'Pending' ) {
						$log_entry[] = 'Transaction Pending';
						do_action( "mycred_buy_cred_{$id}_pending", $data );
					}

					// Failed transaction
					else {
						$log_entry[] = 'Transaction Failed. PayPal replied: ' . $data['payment_status'];
						do_action( "mycred_buy_cred_{$id}_failed", $data );
					}
				}

				// Error
				else {
					do_action( "mycred_buy_cred_{$id}_error", $log_entry, $data );
				}

			}
			else {
				$log_entry[] = 'Transaction could not be verified by PayPal. Reply received: ' . $result;
			}

			do_action( "mycred_buy_cred_{$id}_end", $log_entry, $data );
			unset( $data );

			die();
		}

		/**
		 * Results Handler
		 * @since 0.1
		 * @version 1.0
		 */
		public function returning() {
			if ( isset( $_GET['tx'] ) && isset( $_GET['st'] ) && $_GET['st'] == 'Completed' ) {
				// Thank you page
				$thankyou_url = $this->get_thankyou();

				$this->purchase_header( __( 'Success', 'mycred' ), $thankyou_url );
				echo '<h1>' . __( 'Thank you for your purchase', 'mycred' ) . '</h1>';
				$this->purchase_footer();
				exit();
			}
		}

		/**
		 * Buy Handler
		 * @since 0.1
		 * @version 1.1
		 */
		public function buy() {
			if ( !isset( $this->prefs['account'] ) || empty( $this->prefs['account'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$home = get_bloginfo( 'url' );
			$token = $this->create_token();
			$logo_url = 'https://www.paypalobjects.com/webstatic/mktg/logo/bdg_payments_by_pp_2line.png';

			// Location
			if ( $this->prefs['sandbox'] )
				$location = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
			else
				$location = 'https://www.paypal.com/cgi-bin/webscr';

			// Finance
			$amount = $this->core->number( $_REQUEST['amount'] );
			// Enforce minimum
			if ( $amount < $this->core->buy_creds['minimum'] )
				$amount = $this->core->buy_creds['minimum'];
			// No negative amounts please
			$amount = abs( $amount );
			// Calculate cost here so we can use any exchange rate
			$cost = $amount*$this->prefs['exchange'];
			// Return a properly formated cost so PayPal is happy
			$cost = number_format( $cost, 2, '.', '' );

			// Thank you page
			$thankyou_url = $this->get_thankyou();

			// Cancel page
			$cancel_url = $this->get_cancelled();

			// Return to a url
			if ( isset( $_REQUEST['return_to'] ) ) {
				$thankyou_url = $_REQUEST['return_to'];
				$cancel_url = $_REQUEST['return_to'];
			}

			$to = $this->get_to();
			$from = $this->current_user_id;

			// Let others play
			$extra = apply_filters( 'mycred_paypal_standard_extra', '', $cost, $from, $to, $this->prefs, $this->core );
			unset( $_REQUEST );

			// Hidden form fields
			// to|from|amount|cost|currency|token|extra
			$sales_data = $to . '|' . $from . '|' . $amount . '|' . $cost . '|' . $this->prefs['currency'] . '|' . $token . '|' . $extra;
			$item_name = str_replace( '%number%', $amount, $this->prefs['item_name'] );
			$hidden_fields = array(
				'cmd'           => '_xclick',
				'business'      => $this->prefs['account'],
				'item_name'     => $this->core->template_tags_general( $item_name ),
				'quantity'      => 1,
				'amount'        => $cost,
				'currency_code' => $this->prefs['currency'],
				'no_shipping'   => 1,
				'no_note'       => 1,
				'custom'        => $this->encode_sales_data( $sales_data ),
				'return'        => $thankyou_url,
				'notify_url'    => $this->callback_url(),
				'rm'            => 2,
				'cbt'           => __( 'Return to ', 'mycred' ) . get_bloginfo( 'name' ),
				'cancel_return' => $cancel_url
			);

			// Generate processing page
			$this->purchase_header( __( 'Processing payment &hellip;', 'mycred' ) );
			$this->form_with_redirect( $hidden_fields, $location, $logo_url, '', 'custom' );
			$this->purchase_footer();

			// Exit
			unset( $this );
			exit();
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences( $buy_creds ) {
			$prefs = $this->prefs; ?>

					<label class="subheader" for="<?php echo $this->field_id( 'sandbox' ); ?>"><?php _e( 'Sandbox Mode', 'mycred' ); ?></label>
					<ol>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'sandbox' ); ?>" id="<?php echo $this->field_id( 'sandbox' ); ?>" value="1"<?php checked( $prefs['sandbox'], 1 ); ?> />
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'currency' ); ?>"><?php _e( 'Currency', 'mycred' ); ?></label>
					<ol>
						<li>
							<?php $this->currencies_dropdown( 'currency' ); ?>
							
							<p><strong><?php _e( 'Important!', 'mycred' ); ?></strong> <?php _e( 'Make sure you select a currency that your PayPal account supports. Otherwise transactions will not be approved until you login to your PayPal account and Accept each transaction!', 'mycred' ); ?></p>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'account' ); ?>"><?php _e( 'Account Email', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'account' ); ?>" id="<?php echo $this->field_id( 'account' ); ?>" value="<?php echo $prefs['account']; ?>" class="long" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'item_name' ); ?>"><?php _e( 'Item Name', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'item_name' ); ?>" id="<?php echo $this->field_id( 'item_name' ); ?>" value="<?php echo $prefs['item_name']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Description of the item being purchased by the user.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'exchange' ); ?>"><?php echo $this->core->template_tags_general( __( '%plural% Exchange Rate', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><?php echo $this->core->format_creds( 1 ); ?> = <input type="text" name="<?php echo $this->field_name( 'exchange' ); ?>" id="<?php echo $this->field_id( 'exchange' ); ?>" value="<?php echo $prefs['exchange']; ?>" size="3" /> <span id="mycred-gateway-paypal-currency"><?php echo ( empty( $prefs['currency'] ) ) ? __( 'Your selected currency', 'mycred' ) : $prefs['currency']; ?></span></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'IPN Address', 'mycred' ); ?></label>
					<ol>
						<li>
							<code style="padding: 12px;display:block;"><?php echo $this->callback_url(); ?></code>
							<p><?php _e( 'For this gateway to work, you must login to your PayPal account and under "Profile" > "Selling Tools" enable "Instant Payment Notifications". Make sure the "Notification URL" is set to the above address and that you have selected "Receive IPN messages (Enabled)".', 'mycred' ); ?></p>
						</li>
					</ol>
<?php
		}

		/**
		 * Sanatize Prefs
		 * @since 0.1
		 * @version 1.1
		 */
		public function sanitise_preferences( $data ) {
			$data['sandbox'] = ( !isset( $data['sandbox'] ) ) ? 0 : 1;

			// Exchange can not be empty
			if ( empty( $data['exchange'] ) ) {
				$data['exchange'] = 1;
			}
			// If exchange is less then 1 we must start with a zero
			if ( $data['exchange'] != 1 && substr( $data['exchange'], 0, 1 ) != '0' ) {
				$data['exchange'] = (float) '0' . $data['exchange'];
			}
			// Decimal seperator must be punctuation and not comma
			$data['exchange'] = str_replace( ',', '.', $data['exchange'] );

			return $data;
		}
	}
}
?>