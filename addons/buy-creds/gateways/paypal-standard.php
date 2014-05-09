<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_PayPal class
 * PayPal Payments Standard - Payment Gateway
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_PayPal_Standard' ) ) {
	class myCRED_PayPal_Standard extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			parent::__construct( array(
				'id'               => 'paypal-standard',
				'label'            => 'PayPal',
				'gateway_logo_url' => plugins_url( 'assets/images/paypal.png', myCRED_PURCHASE ),
				'defaults'         => array(
					'sandbox'          => 0,
					'currency'         => '',
					'account'          => '',
					'item_name'        => __( 'Purchase of myCRED %plural%', 'mycred' ),
					'exchange'         => 1
				)
			), $gateway_prefs );
		}

		/**
		 * IPN - Is Valid Call
		 * Replaces the default check
		 * @since 1.4
		 * @version 1.0
		 */
		public function IPN_is_valid_call() {
			// PayPal Host
			if ( $this->sandbox_mode )
				$host = 'www.sandbox.paypal.com';
			else
				$host = 'www.paypal.com';

			$data = $this->POST_to_data();

			$this->new_log_entry( __( 'Attempting to contact PayPal', 'mycred' ) );

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
			do {

				$call = curl_init( "https://$host/cgi-bin/webscr" );
				curl_setopt( $call, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
				curl_setopt( $call, CURLOPT_POST, 1 );
				curl_setopt( $call, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $call, CURLOPT_POSTFIELDS, $request );
				curl_setopt( $call, CURLOPT_SSL_VERIFYPEER, 1 );
				curl_setopt( $call, CURLOPT_CAINFO, myCRED_PURCHASE_DIR . '/cacert.pem' );
				curl_setopt( $call, CURLOPT_SSL_VERIFYHOST, 2 );
				curl_setopt( $call, CURLOPT_FRESH_CONNECT, 1 );
				curl_setopt( $call, CURLOPT_FORBID_REUSE, 1 );
				curl_setopt( $call, CURLOPT_HTTPHEADER, array( 'Connection: Close' ) );
				$result = curl_exec( $call );

				// End on success
				if ( $result !== false ) {
					curl_close( $call );
					
					$this->new_log_entry( __( 'Connection established', 'mycred' ) );

					break;
				}

				$this->new_log_entry( sprintf( ' > ' . __( 'Attempt: %d failed. Error: %s : %s', 'mycred' ), $attempt, curl_errno( $call ), curl_error( $call ) ) );

				curl_close( $call );

				// Final try
				if ( $attempt == $curl_attempts ) {
					$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
					$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
					$header .= "Content-Length: " . strlen( $request ) . "\r\n\r\n";
					$fp = fsockopen( 'ssl://' . $host, 443, $errno, $errstr, 30 );
					if ( ! $fp ) {
						$log_entry[] = $this->new_log_entry( sprintf( ' > ' . __( 'Secondary systems failing. Final note: %s : %s', 'mycred' ), $errno, $errstr ) );
					}
					else {
						fputs( $fp, $header . $request );
						while ( ! feof( $fp ) ) {
							$result = fgets( $fp, 1024 );
						}
						fclose( $fp );
					}
				}
				$attempt++;

			} while ( $attempt <= $curl_attempts );
			
			if ( strcmp( $result, "VERIFIED" ) == 0 ) {
				$this->new_log_entry( __( 'Call verified', 'mycred' ) );
				return true;
			}
			
			$this->new_log_entry( __( 'Call rejected', 'mycred' ) );
			return false;
		}

		/**
		 * Process Handler
		 * @since 0.1
		 * @version 1.3
		 */
		public function process() {
			$outcome = 'FAILED';
			$valid_call = false;
			$valid_sale = false;

			$this->start_log();
			
			// VALIDATION OF CALL
			$required_fields = array(
				'custom',
				'txn_id',
				'receiver_email',
				'mc_currency',
				'mc_gross',
				'payment_status'
			);

			// All required fields exists
			if ( $this->IPN_has_required_fields( $required_fields, 'POST' ) ) {

				// Validate call
				if ( $this->IPN_is_valid_call() ) {

					$valid_call = true;
					
					// Validate sale
					$sales_data = false;
					$sales_data = $this->IPN_is_valid_sale( 'custom', 'mc_gross', 'txn_id' );
					if ( $sales_data !== false ) {

						$valid_sale = true;
						$this->new_log_entry( __( 'Sales Data is Valid', 'mycred' ) );

					}

					else $this->new_log_entry( __( 'Failed to validate sale', 'mycred' ) );

				}

			}

			else $this->new_log_entry( __( 'Failed to verify caller', 'mycred' ) );

			// EXECUTION
			if ( $valid_call === true && $valid_sale === true ) {

				// Finally check payment
				if ( $_POST['payment_status'] == 'Completed' ) {
							
					$this->new_log_entry( sprintf( __( 'Attempting to credit %s to users account', 'mycred' ), $this->core->plural() ) );

					$data = array(
						'txn_id'       => $_POST['txn_id'],
						'payer_id'     => $_POST['payer_id'],
						'name'         => $_POST['first_name'] . ' ' . $_POST['last_name'],
						'ipn_track_id' => $_POST['ipn_track_id'],
						'sales_data'   => implode( '|', $sales_data )
					);

					// Add creds
					if ( $this->complete_payment( $sales_data[0], $sales_data[1], $sales_data[2], $data ) ) {

						$this->new_log_entry( sprintf( __( '%s was successfully credited to users account', 'mycred' ), $this->core->format_creds( $sales_data[2] ) ) );
						$outcome = 'COMPLETED';

						do_action( "mycred_buycred_{$this->id}_approved", $this->processing_log, $_REQUEST );

					}

					else $this->new_log_entry( __( 'Failed to credit the users account', 'mycred' ) );
				}
				
				else $this->new_log_entry( __( 'Purchase not paid', 'mycred' ) );

			}
			else {
				$this->new_log_entry( __( 'Hanging up on caller', 'mycred' ) );
				do_action( "mycred_buycred_{$this->id}_error", $this->processing_log, $_REQUEST );
			}

			$this->save_log_entry( $_POST['txn_id'], $outcome );

			do_action( "mycred_buycred_{$this->id}_end", $this->processing_log, $_REQUEST );
		}

		/**
		 * Results Handler
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function returning() {
			if ( isset( $_REQUEST['tx'] ) && isset( $_REQUEST['st'] ) && $_REQUEST['st'] == 'Completed' ) {
				$this->get_page_header( __( 'Success', 'mycred' ), $this->get_thankyou() );
				echo '<h1 style="text-align:center;">' . __( 'Thank you for your purchase', 'mycred' ) . '</h1>';
				$this->get_page_footer();
				exit;
			}
		}

		/**
		 * Buy Handler
		 * @since 0.1
		 * @version 1.1.1
		 */
		public function buy() {
			if ( ! isset( $this->prefs['account'] ) || empty( $this->prefs['account'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$token = $this->create_token();

			// Location
			if ( $this->sandbox_mode )
				$location = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
			else
				$location = 'https://www.paypal.com/cgi-bin/webscr';

			// Amount
			$amount = $this->core->number( $_REQUEST['amount'] );
			$amount = abs( $amount );

			// Get Cost
			$cost = $this->get_cost( $amount );

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
			$this->get_page_header( __( 'Processing payment &hellip;', 'mycred' ) );
			$this->get_page_redirect( $hidden_fields, $location );
			$this->get_page_footer();

			// Exit
			unset( $this );
			exit;
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		function preferences( $buy_creds = NULL ) {
			$prefs = $this->prefs; ?>

<label class="subheader" for="<?php echo $this->field_id( 'currency' ); ?>"><?php _e( 'Currency', 'mycred' ); ?></label>
<ol>
	<li>
		<?php $this->currencies_dropdown( 'currency', 'mycred-gateway-paypal-currency' ); ?>
							
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
		<div class="h2"><?php echo $this->core->format_creds( 1 ); ?> = <input type="text" name="<?php echo $this->field_name( 'exchange' ); ?>" id="<?php echo $this->field_id( 'exchange' ); ?>" value="<?php echo $prefs['exchange']; ?>" size="3" /> <span id="mycred-gateway-paypal-currency"><?php echo ( empty( $prefs['currency'] ) ) ? __( 'Select currency', 'mycred' ) : $prefs['currency']; ?></span></div>
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
		 * @version 1.2
		 */
		public function sanitise_preferences( $data ) {
			$new_data = array();

			$new_data['sandbox']   = ( isset( $data['sandbox'] ) ) ? 1 : 0;
			$new_data['currency']  = sanitize_text_field( $data['currency'] );
			$new_data['account']   = sanitize_text_field( $data['account'] );
			$new_data['item_name'] = sanitize_text_field( $data['item_name'] );
			$new_data['exchange']  = ( ! empty( $data['exchange'] ) ) ? $data['exchange'] : 1;

			// If exchange is less then 1 we must start with a zero
			if ( $new_data['exchange'] != 1 && in_array( substr( $new_data['exchange'], 0, 1 ), array( '.', ',' ) ) )
				$new_data['exchange'] = (float) '0' . $new_data['exchange'];

			return $new_data;
		}
	}
}
?>