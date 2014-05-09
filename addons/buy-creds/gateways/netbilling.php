<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_NETbilling class
 * NETbilling Payment Gateway
 * @see http://secure.netbilling.com/public/docs/merchant/public/directmode/directmode3protocol.html
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_NETbilling' ) ) {
	class myCRED_NETbilling extends myCRED_Payment_Gateway {

		protected $http_code = '';

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			global $netbilling_errors;

			parent::__construct( array(
				'id'               => 'netbilling',
				'label'            => 'NETbilling',
				'gateway_logo_url' => plugins_url( 'assets/images/netbilling.png', myCRED_PURCHASE ),
				'defaults'         => array(
					'sandbox'          => 0,
					'account'          => '',
					'site_tag'         => '',
					'item_name'        => __( 'Purchase of myCRED %plural%', 'mycred' ),
					'exchange'         => 1,
					'cryptokey'        => ''
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
			$result = true;

			// Accounts Match
			$account = explode( ':', $_REQUEST['Ecom_Ezic_AccountAndSitetag'] );
			if ( $account[0] != $this->prefs['account'] || $account[1] != $this->prefs['site_tag'] )
				$result = false;
					
			// Crypto Check
			$crypto_check = md5( $this->prefs['cryptokey'] . $_REQUEST['Ecom_Cost_Total'] . $_REQUEST['Ecom_Receipt_Description'] );
			if ( $crypto_check != $_REQUEST['Ecom_Ezic_Security_HashValue_MD5'] )
				$result = false;
			
			if ( ! $result )
				$this->new_log_entry( ' > ' . __( 'Invalid Call', 'mycred' ) );
			else
				$this->new_log_entry( sprintf( __( 'Caller verified as "%s"', 'mycred' ), $this->id ) );

			return $result;
		}

		/**
		 * Process
		 * @since 0.1
		 * @version 1.1
		 */
		public function process() {
			$outcome = 'FAILED';
			$valid_call = false;
			$valid_sale = false;

			$this->start_log();
			
			// VALIDATION OF CALL
			$required_fields = array(
				'Ecom_Ezic_AccountAndSitetag',
				'Ecom_UserData_salesdata',
				'Ecom_Ezic_Response_TransactionID',
				'Ecom_Receipt_Description',
				'Ecom_Cost_Total',
				'Ecom_Ezic_Security_HashValue_MD5',
				'Ecom_Ezic_Response_StatusCode',
				'Ecom_Ezic_Response_AuthCode'
			);

			// All required fields exists
			if ( $this->IPN_has_required_fields( $required_fields ) ) {

				// Validate call
				if ( $this->IPN_is_valid_call() ) {

					$valid_call = true;
					
					// Validate sale
					$sales_data = false;
					$sales_data = $this->IPN_is_valid_sale( 'Ecom_UserData_salesdata', 'Ecom_Cost_Total', 'Ecom_Ezic_Response_TransactionID' );
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
				if ( $_REQUEST['Ecom_Ezic_Response_StatusCode'] == 1 ) {
							
					$this->new_log_entry( sprintf( __( 'Attempting to credit %s to users account', 'mycred' ), $this->core->plural() ) );

					$data = array(
						'txn_id'     => $_REQUEST['Ecom_Ezic_Response_TransactionID'],
						'auth_code'  => $_REQUEST['Ecom_Ezic_Response_AuthCode'],
						'name'       => $_REQUEST['Ecom_BillTo_Postal_Name_First'] . ' ' . $_REQUEST['Ecom_BillTo_Postal_Name_Last'],
						'sales_data' => implode( '|', $sales_data )
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

			$this->save_log_entry( $_REQUEST['Ecom_Ezic_Response_TransactionID'], $outcome );

			do_action( "mycred_buycred_{$this->id}_end", $this->processing_log, $_REQUEST );
		}

		/**
		 * Returns
		 * @since 0.1
		 * @version 1.1
		 */
		public function returning() {
			if ( isset( $_REQUEST['Ecom_Ezic_AccountAndSitetag'] ) && isset( $_REQUEST['Ecom_UserData_salesdata'] ) ) {
				$this->process();
			}
		}

		/**
		 * Buy Handler
		 * @since 0.1
		 * @version 1.1
		 */
		public function buy() {
			if ( ! isset( $this->prefs['account'] ) || empty( $this->prefs['account'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$home = get_bloginfo( 'url' );
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
			$extra = apply_filters( 'mycred_netbilling_extra', '', $cost, $from, $to, $this->prefs, $this->core );
			unset( $_REQUEST );

			// Hidden form fields
			$this_purchase = (string) $to . '|' . $from . '|' . $amount . '|' . $cost . '|USD|' . $token . '|' . $extra;

			$item_name = str_replace( '%number%', $amount, $this->prefs['item_name'] );
			$item_name = $this->core->template_tags_general( $item_name );

			$hidden_fields = array(
				'Ecom_Ezic_AccountAndSitetag'         => $this->prefs['account'] . ':' . $this->prefs['site_tag'],
				'Ecom_Ezic_Payment_AuthorizationType' => 'SALE',
				'Ecom_Receipt_Description'            => $item_name,
				'Ecom_Ezic_Fulfillment_ReturnMethod'  => 'POST',
				'Ecom_Cost_Total'                     => $cost,
				'Ecom_UserData_salesdata'             => $this->encode_sales_data( $this_purchase ),
				'Ecom_Ezic_Fulfillment_ReturnURL'     => $thankyou_url,
				'Ecom_Ezic_Fulfillment_GiveUpURL'     => $cancel_url,
				'Ecom_Ezic_Security_HashValue_MD5'    => md5( $this->prefs['cryptokey'] . $cost . $item_name ),
				'Ecom_Ezic_Security_HashFields'       => 'Ecom_Cost_Total Ecom_Receipt_Description'
			);

			// Generate processing page
			$this->get_page_header( __( 'Processing payment &hellip;', 'mycred' ) );
			$this->get_page_redirect( $hidden_fields, 'https://secure.netbilling.com/gw/native/interactive2.2' );
			$this->get_page_footer();

			// Exit
			unset( $this );
			exit;
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.1
		 */
		function preferences( $buy_creds = NULL ) {
			$prefs = $this->prefs; ?>

<label class="subheader" for="<?php echo $this->field_id( 'account' ); ?>"><?php _e( 'Account ID', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'account' ); ?>" id="<?php echo $this->field_id( 'account' ); ?>" value="<?php echo $prefs['account']; ?>" class="long" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'site_tag' ); ?>"><?php _e( 'Site Tag', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'site_tag' ); ?>" id="<?php echo $this->field_id( 'site_tag' ); ?>" value="<?php echo $prefs['site_tag']; ?>" class="long" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'cryptokey' ); ?>"><?php _e( 'Order Integrity Key', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="password" name="<?php echo $this->field_name( 'cryptokey' ); ?>" id="<?php echo $this->field_id( 'cryptokey' ); ?>" value="<?php echo $prefs['cryptokey']; ?>" class="long" /></div>
		<span class="description"><?php _e( 'Found under Step 12 on the Fraud Defense page.', 'mycred' ); ?></span>
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
		<div class="h2"><?php echo $this->core->format_creds( 1 ); ?> = <input type="text" name="<?php echo $this->field_name( 'exchange' ); ?>" id="<?php echo $this->field_id( 'exchange' ); ?>" value="<?php echo $prefs['exchange']; ?>" size="3" /> <span id="mycred-gateway-netbilling-currency">USD</span></div>
	</li>
</ol>
<label class="subheader"><?php _e( 'Postback CGI URL', 'mycred' ); ?></label>
<ol>
	<li>
		<code style="padding: 12px;display:block;"><?php echo $this->callback_url(); ?></code>
		<p><?php _e( 'For this gateway to work, you must login to your NETbilling account and edit your site. Under "Default payment form settings" make sure the Postback CGI URL is set to the above address and "Return method" is set to POST.', 'mycred' ); ?></p>
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
			$new_data = array();

			$new_data['sandbox']   = ( isset( $data['sandbox'] ) ) ? 1 : 0;
			$new_data['account']   = sanitize_text_field( $data['account'] );
			$new_data['site_tag']  = sanitize_text_field( $data['site_tag'] );
			$new_data['cryptokey'] = sanitize_text_field( $data['cryptokey'] );
			$new_data['item_name'] = sanitize_text_field( $data['item_name'] );
			$new_data['exchange']  = ( ! empty( $data['exchange'] ) ) ? $data['exchange'] : 1;

			// If exchange is less then 1 we must start with a zero
			if ( $new_data['exchange'] != 1 && in_array( substr( $new_data['exchange'], 0, 1 ), array( '.', ',' ) ) )
				$new_data['exchange'] = (float) '0' . $new_data['exchange'];

			return $new_data;
		}
		
		protected function validate_cc( $data = array() ) {
			$errors = array();

			// Credit Card
			if ( $data['payment_method'] == 'card' ) {
				// Check length
				if ( strlen( $data['card_number'] ) < 13 || strlen( $data['card_number'] ) > 19 || ! is_numeric( $data['card_number'] ) )
					$errors['number'] =  __( 'Incorrect Credit Card number', 'mycred' );

				// Check expiration date
				$exp_date = mktime( 0, 0, 0, $data['card_expire_month'], 30, $data['card_expire_year'] );
				$today_date = date_i18n( 'U' );
				if ( $exp_date < $today_date )
					$errors['expire'] =  __( 'The credit card entered is past its expiration date.', 'mycred' );
				
				if ( strlen( $data['card_cvv2'] ) < 3 || strlen( $data['card_cvv2'] ) > 4 || ! is_numeric( $data['card_cvv2'] ) )
					$errors['cvc'] =  __( 'The CVV2 number entered is not valid.', 'mycred' );
			}

			// Check
			else {
				// Check routing
				if ( strlen( $data['ach_routing'] ) != 9 || ! is_numeric( $data['ach_routing'] ) )
					$errors['routing'] =  __( 'The bank routing number entered is not valid.', 'mycred' );

				// Check account
				if ( strlen( $data['ach_account'] ) <= 5 || ! is_numeric( $data['ach_account'] ) )
					$errors['account'] =  __( 'The bank account number entered is not valid.', 'mycred' );
			}

			return $errors;
		}
	}
}
?>