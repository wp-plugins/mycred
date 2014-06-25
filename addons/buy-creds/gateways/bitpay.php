<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Bitpay class
 * BitPay (Bitcoins) - Payment Gateway
 * @since 1.4
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Bitpay' ) ) {
	class myCRED_Bitpay extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			parent::__construct( array(
				'id'               => 'bitpay',
				'label'            => 'Bitpay',
				'gateway_logo_url' => plugins_url( 'assets/images/bitpay.png', myCRED_PURCHASE ),
				'defaults'         => array(
					'api_key'          => '',
					'currency'         => 'USD',
					'exchange'         => 1,
					'item_name'        => __( 'Purchase of myCRED %plural%', 'mycred' ),
					'speed'            => 'high',
					'notifications'    => 1
				)
			), $gateway_prefs );
		}

		/**
		 * Process
		 * @since 1.4
		 * @version 1.0
		 */
		public function process() {
			$outcome = 'FAILED';
			$valid_call = false;
			$valid_sale = false;

			$this->start_log();
			
			// VALIDATION OF CALL
			$required_fields = array(
				'postData',
				'price',
				'currency',
				'status',
				'id',
				'btcPrice'
			);

			// All required fields exists
			if ( $this->IPN_has_required_fields( $required_fields, 'POST' ) ) {

				// Validate call
				if ( $this->IPN_is_valid_call() ) {

					$valid_call = true;
					
					// Validate sale
					$sales_data = false;
					$sales_data = $this->IPN_is_valid_sale( 'postData', 'price', 'id' );
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
				if ( $_POST['status'] == 'paid' ) {
							
					$this->new_log_entry( sprintf( __( 'Attempting to credit %s to users account', 'mycred' ), $this->core->plural() ) );

					$data = array(
						'txn_id'       => $_POST['id'],
						'bitcoin'      => $_POST['payer_id'],
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

			$this->save_log_entry( $_POST['id'], $outcome );

			do_action( "mycred_buycred_{$this->id}_end", $this->processing_log, $_REQUEST );
		}

		/**
		 * Returning
		 * @since 1.4
		 * @version 1.0
		 */
		public function returning() { }

		public function create_invoice( $args ) {
			$data = json_encode( $args );

			$curl = curl_init( 'https://bitpay.com/api/invoice/' );

			curl_setopt( $curl, CURLOPT_POST, 1 );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
			$length = strlen( $data );

			$key = base64_encode( $args['apiKey'] );
			$header = array(
				'Content-Type: application/json',
				"Content-Length: $length",
				"Authorization: Basic $key",
			);

			curl_setopt( $curl, CURLOPT_PORT, 443 );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $header );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
			curl_setopt( $curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
			curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, 1 );
			curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, 2 );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $curl, CURLOPT_FORBID_REUSE, 1 );
			curl_setopt( $curl, CURLOPT_FRESH_CONNECT, 1 );

			$reply = curl_exec( $curl );

			if ( $reply == false )
				$response = curl_error( $curl );
			else
				$response = json_decode( $reply, true );

			curl_close( $curl );

			if ( is_string( $response ) )
				return array( 'error' => $response );	

			return $response;
		}

		/**
		 * Buy Creds
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function buy() {
			if ( ! isset( $this->prefs['api_key'] ) || empty( $this->prefs['api_key'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$token = $this->create_token();

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
			$extra = apply_filters( 'mycred_bitpay_extra', '', $cost, $from, $to, $this->prefs, $this->core );
			unset( $_REQUEST );

			// Hidden form fields
			// to|from|amount|cost|currency|token|extra
			$sales_data = $to . '|' . $from . '|' . $amount . '|' . $cost . '|' . $this->prefs['currency'] . '|' . $token . '|' . $extra;
			$item_name = str_replace( '%number%', $amount, $this->prefs['item_name'] );

			$from_user = get_userdata( $from );

			$request = $this->create_invoice( array(
				'apiKey'            => $this->prefs['api_key'],
				'transactionSpeed'  => $this->prefs['speed'],
				'price'             => number_format( $cost, 2, '.', '' ),
				'currency'          => $this->prefs['currency'],
				'notificationURL'   => $this->callback_url(),
				'fullNotifications' => ( $this->prefs['notifications'] ) ? true : false,
				'posData'           => $this->encode_sales_data( $sales_data ),
				'buyerName'         => $from_user->first_name . ' ' . $from_user->last_name,
				'itemDesc'          => $this->core->template_tags_general( $item_name )
			) );

			// Request Failed
			if ( isset( $request['error'] ) ) {
				$this->get_page_header( __( 'Processing payment &hellip;', 'mycred' ) ); ?>

<p><?php _e( 'Could not create a BitPay Invoice. Please contact the site administrator!', 'mycred' ); ?></p>
<p><?php printf( __( 'Bitpay returned the following error message:', 'mycred' ) . ' ', $request['error'] ); ?></p>
<?php
			}

			// Request success
			else {
				$this->get_page_header( __( 'Processing payment &hellip;', 'mycred' )); ?>

<div class="continue-forward" style="text-align:center;">
	<p>&nbsp;</p>
	<img src="<?php echo plugins_url( 'assets/images/loading.gif', myCRED_PURCHASE ); ?>" alt="Loading" />
	<p id="manual-continue"><a href="<?php echo $request['url']; ?>"><?php _e( 'Click here if you are not automatically redirected', 'mycred' ); ?></a></p>
</div>
<?php
			}

			$this->get_page_footer();

			// Exit
			unset( $this );
			exit;
		}

		/**
		 * Gateway Prefs
		 * @since 1.4
		 * @version 1.0
		 */
		function preferences( $buy_creds = NULL ) {
			$prefs = $this->prefs; ?>

<?php if ( ! is_ssl() ) : ?>
<p><strong style="color:red;"><?php _e( 'Warning! - Bitpay requires your website to use SSL in order to notify you of confirmed payments! Without SSL your website will not receive updates!', 'mycred' ); ?></strong></p>
<?php endif; ?>
<label class="subheader" for="<?php echo $this->field_id( 'api_key' ); ?>"><?php _e( 'API Key', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'api_key' ); ?>" id="<?php echo $this->field_id( 'api_key' ); ?>" value="<?php echo $prefs['api_key']; ?>" class="long" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'currency' ); ?>"><?php _e( 'Currency', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'currency' ); ?>" id="<?php echo $this->field_id( 'currency' ); ?>" value="<?php echo $prefs['currency']; ?>" class="medium" maxlength="3" placeholder="<?php _e( 'Currency Code', 'mycred' ); ?>" /></div>

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
<label class="subheader" for="<?php echo $this->field_id( 'speed' ); ?>"><?php _e( 'Transaction Speed', 'mycred' ); ?></label>
<ol>
	<li>
		<select name="<?php echo $this->field_name( 'speed' ); ?>" id="<?php echo $this->field_id( 'speed' ); ?>">
			<?php

			$options = array(
				'high'   => __( 'High', 'mycred' ),
				'medium' => __( 'Medium', 'mycred' ),
				'low'    => __( 'Low', 'mycred' )
			);
			foreach ( $options as $value => $label ) {
				echo '<option value="' . $value . '"';
				if ( $prefs['speed'] == $value ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>

		</select>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'notifications' ); ?>"><?php _e( 'Full Notifications', 'mycred' ); ?></label>
<ol>
	<li>
		<select name="<?php echo $this->field_name( 'notifications' ); ?>" id="<?php echo $this->field_id( 'notifications' ); ?>">
			<?php

			$options = array(
				0 => __( 'No', 'mycred' ),
				1 => __( 'Yes', 'mycred' )
			);
			foreach ( $options as $value => $label ) {
				echo '<option value="' . $value . '"';
				if ( $prefs['notifications'] == $value ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>

		</select>
	</li>
</ol>
<?php
		}
		
		/**
		 * Sanatize Prefs
		 * @since 1.4
		 * @version 1.0
		 */
		public function sanitise_preferences( $data ) {
			return $data;
		}
	}
}
?>