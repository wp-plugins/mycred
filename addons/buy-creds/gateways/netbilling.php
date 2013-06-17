<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_NETbilling class
 * NETbilling Payment Gateway
 * 
 * @see http://secure.netbilling.com/public/docs/merchant/public/directmode/directmode3protocol.html
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_NETbilling' ) ) {
	class myCRED_NETbilling extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			global $netbilling_errors;

			parent::__construct( array(
				'id'       => 'netbilling',
				'defaults' => array(
					'sandbox'                  => 0,
					'account'                  => '',
					'site_tag'                 => '',
					'item_name'                => __( 'Purchase of myCRED %plural%', 'mycred' ),
					'exchange'                 => '',
					'dynip_sec_code'           => '',
					'num_attempts'             => 3,
					'disable_avs'              => 0,
					'disable_cvv2'             => 0,
					'disable_fraud_checks'     => 0,
					'disable_negative_db'      => 0,
					'disable_email_receipts'   => 0,
					'disable_expiration_check' => 0
				)
			), $gateway_prefs );
		}

		/**
		 * Process
		 * @since 0.1
		 * @version 1.0
		 */
		public function process() {
			// Nonce check
			if ( !isset( $_POST['token'] ) || !wp_verify_nonce( $_POST['token'], 'netbilling-purchase' ) ) {
				unset( $_POST );
				return;
			}

			// Attempt Limit
			if ( $_POST['num_attempts'] > $this->prefs['num_attempts'] ) {
				$this->status = 'fail';
				$this->response = __( 'You have tried too many times.  Please contact support.', 'mycred' );
				return;
			}

			// Gateway is not installed
			elseif ( empty( $this->prefs['account'] ) ) {
				$this->status = 'fail';
				$this->response = __( 'This payment gateway has not yet been setup! Exiting.', 'mycred' );
				return;
			}

			// All good
			else {
				$attempts = $_REQUEST['num_attempts'];
			}

			$sales_data = array();
			$error = array();

			// Begin form validation
			$_POST = array_map( 'strip_tags', $_POST );

			// First Name check
			if ( isset( $_POST['bill_name1'] ) && !empty( $_POST['bill_name1'] ) )
				$sales_data['bill_name1'] = $_POST['bill_name1'];
			else
				$error['bill_name1'] = __( 'First name can not be empty', 'mycred' );

			// Last Name check
			if ( isset( $_POST['bill_name2'] ) && !empty( $_POST['bill_name2'] ) )
				$sales_data['bill_name2'] = $_POST['bill_name2'];
			else
				$error['bill_name2'] =  __( 'Last name can not be empty', 'mycred' );

			// Street Check
			if ( isset( $_POST['bill_street'] ) && !empty( $_POST['bill_street'] ) )
				$sales_data['bill_street'] = $_POST['bill_street'];
			else
				$error['bill_street'] =  __( 'Street can not be empty', 'mycred' );

			// City check
			if ( isset( $_POST['bill_city'] ) && !empty( $_POST['bill_city'] ) )
				$sales_data['bill_city'] = $_POST['bill_city'];
			else
				$error['bill_city'] =  __( 'City can not be empty', 'mycred' );

			// Country check
			if ( isset( $_POST['bill_country'] ) && !empty( $_POST['bill_country'] ) )
				$sales_data['bill_country'] = $_POST['bill_country'];
			else
				$error['bill_country'] =  __( 'Country can not be empty', 'mycred' );

			// State Check
			if ( isset( $_POST['bill_state_us'] ) && !empty( $_POST['bill_state_us'] ) && ( isset( $_POST['bill_country'] ) && $_POST['bill_country'] == 'US' ) )
				$sales_data['bill_state'] = $_POST['bill_state_us'];
			elseif ( isset( $_POST['bill_state_non'] ) && !empty( $_POST['bill_state_non'] ) && ( isset( $_POST['bill_country'] ) && $_POST['bill_country'] != 'US' ) )
				$sales_data['bill_state'] = $_POST['bill_state_non'];
			else
				$error['bill_state'] =  __( 'State can not be empty', 'mycred' );

			// Zip / Post code check
			if ( isset( $_POST['bill_zip'] ) && ( $_POST['bill_zip'] == 'US' && strlen( $_POST['bill_zip'] ) == 5 ) )
				$sales_data['bill_zip'] = $_POST['bill_zip'];
			elseif ( isset( $_POST['bill_zip'] ) && $_POST['bill_zip'] != 'US' )
				$sales_data['bill_zip'] = $_POST['bill_zip'];
			else
				$error['bill_zip'] =  __( 'Zip / Post Code can not be empty', 'mycred' );

			// Email check
			if ( isset( $_POST['cust_email'] ) && is_email( $_POST['cust_email'] ) )
				$sales_data['cust_email'] = $_POST['cust_email'];
			else
				$error['cust_email'] =  __( 'Email can not be empty', 'mycred' );

			// Phone check
			if ( isset( $_POST['cust_phone'] ) && strlen( $_POST['cust_phone'] ) < 10 )
				$sales_data['cust_phone'] = $_POST['cust_phone'];

			// Payment method check
			if ( isset( $_POST['payment_method'] ) ) {
				$sales_data['payment_method'] = $_POST['payment_method'];

				// Pay using credit card
				if ( $sales_data['payment_method'] == 'card' ) {
					// Card Number check
					if ( isset( $_POST['card_number'] ) && !empty( $_POST['card_number'] ) )
						$sales_data['card_number'] = $_POST['card_number'];
					else
						$error['card_number'] =  __( 'Please enter your credit card number', 'mycred' );

					// Exiration Month check
					if ( isset( $_POST['card_expire_month'] ) && !empty( $_POST['card_expire_month'] ) )
						$sales_data['card_expire_month'] = $_POST['card_expire_month'];
					else
						$error['card_expire_month'] =  __( 'Card Expiration Month must be selected', 'mycred' );

					// Expiration Year check
					if ( isset( $_POST['card_expire_year'] ) && !empty( $_POST['card_expire_year'] ) )
						$sales_data['card_expire_year'] = $_POST['card_expire_year'];
					else
						$error['card_expire_year'] =  __( 'Card Expiration Year must be set', 'mycred' );

					// CCV2 Check
					if ( isset( $_POST['card_cvv2'] ) && !empty( $_POST['card_cvv2'] ) )
						$sales_data['card_cvv2'] = $_POST['card_cvv2'];
					else
						$error['card_cvv2'] =  __( 'Please enter the CVV2 code from the back of your card', 'mycred' );
				}

				// Pay using bank transfer
				else {
					// Routing check
					if ( isset( $_POST['ach_routing'] ) && !empty( $_POST['ach_routing'] ) )
						$sales_data['ach_routing'] = $_POST['ach_routing'];
					else
						$error['ach_routing'] =  __( 'Account Routing number missing', 'mycred' );

					// Account check
					if ( isset( $_POST['ach_account'] ) && !empty( $_POST['ach_account'] ) )
						$sales_data['ach_account'] = $_POST['ach_account'];
					else
						$error['ach_account'] =  __( 'Account Number missing', 'mycred' );
				}
			}

			// Validate credit card
			if ( $sales_data['payment_method'] == 'card' && isset( $sales_data['card_number'] ) && isset( $sales_data['card_expire_month'] ) && isset( $sales_data['card_expire_year'] ) ) {
				// Check length
				if ( strlen( $sales_data['card_number'] ) < 13 || strlen( $sales_data['card_number'] ) > 19 || !is_numeric( $sales_data['card_number'] ) )
					$error['card_number'] =  __( 'Incorrect Credit Card number', 'mycred' );

				// Check expiration date
				$exp_date = mktime( 0, 0, 0, $sales_data['card_expire_month'], 30, $sales_data['card_expire_year'] );
				$today_date = date_i18n( 'U' );
				if ( $exp_date < $today_date )
					$error['card_expire_month'] =  __( 'The credit card entered is past its expiration date.', 'mycred' );
			}

			// Validate CCV2
			if ( isset( $sales_data['card_cvv2'] ) ) {
				if ( strlen( $sales_data['card_cvv2'] ) < 3 || strlen( $sales_data['card_cvv2'] ) > 4 || !is_numeric( $sales_data['card_cvv2'] ) )
					$error['card_cvv2'] =  __( 'The CVV2 number entered is not valid.', 'mycred' );
			}

			// Validate check
			if ( $sales_data['payment_method'] == 'check' && isset( $sales_data['ach_routing'] ) && isset( $sales_data['ach_account'] ) ) {
				if ( strlen( $sales_data['ach_routing'] ) != 9 || !is_numeric( $sales_data['ach_routing'] ) )
					$error['ach_routing'] =  __( 'The bank routing number entered is not valid.', 'mycred' );
				
				if ( strlen( $sales_data['ach_account'] ) <= 5 || !is_numeric( $sales_data['ach_account'] ) )
					$error['ach_account'] =  __( 'The bank account number entered is not valid.', 'mycred' );
			}

			// Errors
			if ( !empty( $error ) ) {
				$this->errors = $error;
				$this->status = 'error';
				$this->request = $_POST;
				return;
			}
			// end of validation

			// Construct payment request
			$request = array();
			$request['account_id'] = $this->prefs['account'];
			$request['site_tag'] = $this->prefs['site_tag'];
			$request['dynip_sec_code'] = $this->prefs['dynip_sec_code'];
			$request['tran_type'] = "S";

			$request['amount'] = $this->core->number( $_POST['cost'] );
			$request['description'] = $this->core->template_tags_general( $this->prefs['item_name'] );

			// Payment Form - Check
			if ( $sales_data['payment_method'] == 'check' ) {
				$request['pay_type'] = "K";
				$request['account_number'] = $sales_data['ach_routing'] . ':' . $sales_data['ach_account'];
				unset( $sales_data['ach_routing'] );
				unset( $sales_data['ach_account'] );
			}

			// Payment Form - Credit Card
			elseif ( $sales_data['payment_method'] == 'card' ) {
				$request['pay_type'] = "C";
			}
			unset( $sales_data['payment_method'] );

			// Merge what remains of $sales_data
			$request = array_merge_recursive( $request, $sales_data );

			// IP & Browser
			$request['cust_ip'] = $_SERVER["REMOTE_ADDR"];
			$request['cust_browser'] = $_SERVER["HTTP_USER_AGENT"];

			// Advanced
			$request['disable_avs'] = $this->prefs['disable_avs'];
			$request['disable_cvv2'] = $this->prefs['disable_cvv2'];
			$request['disable_fraud_checks'] = $this->prefs['disable_fraud_checks'];
			$request['disable_negative_db'] = $this->prefs['disable_negative_db'];
			$request['disable_email_receipts'] = $this->prefs['disable_email_receipts'];
			$request['disable_expiration_check'] = $this->prefs['disable_expiration_check'];

			$this->request = $request;
			unset( $sales_data );

			// Sandbox
			if ( $this->prefs['sandbox'] ) {
				$this->status = 'ready';
				return;
			}

			// Builds the request string, all values are urlencoded
			$post_str = '';
			foreach ( $this->request as $k => $v ) {
				if ( !empty( $post_str ) )
					$post_str .= '&';
				$post_str .= $k . '=' . urlencode( $v );
			}

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $gateway_url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_HEADER, 1 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 180 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_str );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST,  2 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
			curl_setopt( $ch, CURLOPT_ENCODING, "x-www-form-urlencoded" );

			$res = curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			// parses received data into variables
			$resp = explode( "\n\r\n", $res );
			$header = explode( "\n", $resp[0] );
			parse_str( $resp[1], $result );

			// Request Good - No exception
			// http://secure.netbilling.com/public/docs/merchant/public/directmode/directmode3protocol.html#er
			if ( $http_code == "200" ) {
				// Response
				$status_code = $result['status_code'];
				$trans_id = $result['trans_id'];
				$auth_code = $result['auth_code'];
				$auth_date = $result['auth_date'];
				$auth_msg = $result['auth_msg'];
				$avs_code = $result['avs_code'];
				$cvv2_code = $result['cvv2_code'];
				$ticket_code = $result['ticket_code'];
				$reason_code2 = $result['reason_code2'];

				// Error codes
				if ( $status_code == '0' || $status_code == 'F' ) {
					if ( $auth_msg == 'BAD ADDRESS' ) {
						$this->status = 'retry';
						$this->response = __( 'Invalid Address', 'mycred' );
					} elseif ( $auth_msg == 'CVV2 MISMATCH') {
						$this->status = 'retry';
						$this->response = __( 'Invalid CVV2', 'mycred' );
					} elseif ( $auth_msg == 'A/DECLINED' ) {
						$this->status = 'retry';
						$this->response = __( 'You have tried too many times.  Please contact support.', 'mycred' );
					} elseif ( $auth_msg == 'B/DECLINED' ) {
						$this->status = 'retry';
						$this->response = __( 'Please contact support.', 'mycred' );
					} elseif ( $auth_msg == 'C/DECLINED' ) {
						$this->status = 'retry';
						$this->response = __( 'Please contact support.', 'mycred' );
					} elseif ( $auth_msg == 'E/DECLINED' ) {
						$this->status = 'retry';
						$this->response = __( 'Your email address is invalid.', 'mycred' );
					} elseif ( $auth_msg == 'J/DECLINED' ) {
						$this->status = 'retry';
						$this->response = __( 'Your information is invalid.  Please correct', 'mycred' );
					} elseif ( $auth_msg == 'L/DECLINED' ) {
						$this->status = 'retry';
						$this->response = __( 'Invalid Address', 'mycred' );
					} else {
						$this->status = 'retry';
						$this->response = __( 'Your card was declined.  Please try again.', 'mycred' );
					}
				} elseif ( $status_code == 'D' ) {
					$this->status = 'fail';
					$this->response = __( 'Duplicate transaction.  Please contact support', 'mycred' );
				} else {
					$this->status = 'approved';
					$this->response = __( 'Your transaction was approved', 'mycred' );
				}
			}

			// Request Bad - Exception (respons is an error)
			else {
				$this->status = 'retry';
				$this->response = __( ' error: ', 'mycred' ) . substr( $header[0], 13 );
			}

			// Transaction Approved, add creds
			if ( $this->status == 'approved' ) {
				// Make sure this transaction is unique
				if ( !$this->transaction_id_is_unique( $trans_id ) ) {
					$this->status = 'fail';
					$this->response = __( 'Duplicate transaction.  Please contact support', 'mycred' );
					return;
				}

				// Prep
				$_to = $this->get_to();
				$_from = $this->current_user_id;

				// Add creds
				$this->core->add_creds(
					'buy_creds_with_netbilling',
					$_to,
					$amount,
					$this->get_entry( $_to, $_from ),
					$_from,
					$trans_id
				);
			}

			// Fail
			elseif ( $this->status == 'retry' ) {
				// Adjust attempt counter
				if ( $_POST['num_attempts'] < $this->prefs['num_attempts'] ) {
					$_POST['num_attempts']++;
				}
			}
		}

		/**
		 * Buy Handler
		 * @since 0.1
		 * @version 1.0
		 */
		public function buy() {
			// Attempt Counter
			if ( isset( $_POST['num_attempts'] ) ) 
				$attempts = $_POST['num_attempts'];
			else
				$attempts = 0;

			// Payment Method
			$payment_method = 'card';
			if ( isset( $_REQUEST['payment_method'] ) && $_REQUEST['payment_method'] == 'check' )
				$payment_method = 'check';

			$to = $this->get_to();
			$from = $this->current_user_id;

			// Thank you page
			$thankyou_url = $this->get_thankyou();

			// Cancel page
			$cancel_url = $this->get_cancelled();

			// Amount & Cost
			$amount = $_REQUEST['amount'];
			$exchange = $this->prefs['exchange'];
			$cost = $amount*$exchange;

			// Set
			$bill_name1 = $bill_name2 = $bill_street = $bill_city = $bill_state = $bill_zip = $bill_country = $cust_phone = $card_number = $card_expire_month = $card_expire_year = $card_cvv2 = $ach_routing = $ach_account = '';

			$user = get_userdata( (int) $this->current_user_id );

			// Header
			$this->purchase_header( __( 'NETbilling', 'mycred' ) ); ?>

<p><img src="<?php echo plugins_url( 'images/netbilling.png', myCRED_PURCHASE ); ?>" alt="NETbilling Logo" /></p>
<?php
			// Debug
			if ( $this->prefs['sandbox'] && $this->core->can_edit_plugin( $from ) ) {
				echo '
<h1>' . __( 'Debug', 'mycred' ) . '</h1>
<pre>$attempts: ' . print_r( $attempts, true ) . '</pre>
<pre>$this->status: ' . print_r( $this->status, true ) . '</pre>
<pre>$this->request: ' . print_r( $this->request, true ) . '</pre>
<pre>$this->response: ' . print_r( $this->response, true ) . '</pre>';
			}
			// Errors
			if ( !empty( $this->errors ) ) {
				echo '
<h1>' . __( 'Error', 'mycred' ) . '</h1>
<p>' . __( 'The following error/s were found: ', 'mycred' ) . $this->response . '</p>
<ul>';
				foreach ( $this->errors as $form_field => $error_message ) {
					echo '<li class="' . $form_field . '">' . $error_message . '</li>';
				}

				echo '
</ul>
<p class="try-again">' . __( 'Please update and try again.', 'mycred' ) . '</p>';
			}

			// Approved (do not load form)
			elseif ( $this->status == 'approved' ) {
				echo '
<h1>' . __( 'Transaction Approved', 'mycred' ) . '</h1>
<p>' . __( 'Your have successfully purchased ', 'mycred' ) . $this->core->number( $amount ) . ' ' . $this->core->plural() . '.</p>
<p class="action"><a href="' . $thankyou_url . '">' . __( 'Click here to continue', 'mycred' ) . '</a></p>';
				$this->purchase_footer();
				exit();
			}

			// Fail (do not load form)
			elseif ( $this->status == 'fail' ) {
				echo '
<h1>' . __( 'Transaction Declined', 'mycred' ) . '</h1>
<p>' . __( 'I am sorry but your transaction could not be completed due to the following ', 'mycred' ) . $this->response . '</p>
<p class="action"><a href="' . $cancel_url . '">' . __( 'Click here to continue', 'mycred' ) . '</a></p>';
				$this->purchase_footer();
				exit();
			}

			// Retry (reload form)
			elseif ( $this->status == 'retry' ) {
				echo '
<h1>' . __( 'Transaction Error', 'mycred' ) . '</h1>
<p>' . __( 'NETbilling returned the following error: ', 'mycred' ) . $this->response . '</p>
<p class="try-again">' . __( 'Please try again.', 'mycred' ) . '</p>';
			} ?>

<form id="payment_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
	<h2><?php echo __( 'Purchase of', 'mycred' ) . ' ' . $this->core->number( $amount ) . ' ' . $this->core->plural() . ' ' . __( 'for', 'mycred' ) . ' $' . $this->core->number( $cost ); ?></h2>
	<p class="info"><?php _e( 'Fields marked * are required!', 'mycred' ); ?></p>
	<input type="hidden" name="mycred_call" value="netbilling" />
	<input type="hidden" name="mycred_buy" value="netbilling" />
	<input type="hidden" name="gift_to" value="<?php echo $to; ?>" />
	<input type="hidden" name="note" value="<?php echo $message; ?>" />
	<input type="hidden" name="amount" value="<?php echo $amount; ?>" />
	<input type="hidden" name="cost" value="<?php echo $cost; ?>" />
	<input type="hidden" name="payment_method" value="<?php echo $payment_method; ?>" />
	<input type="hidden" name="token" value="<?php echo wp_create_nonce( 'netbilling-purchase' ); ?>" />
	<input type="hidden" name="num_attempts" value="<?php echo $attempts; ?>" />
	<h3><?php _e( 'Billing Details', 'mycred' ); ?></h3>
	<p class="<?php if ( array_key_exists( 'bill_name1', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_name1">First Name *</label>
		<input type="text" id="bill_name1" name="bill_name1" value="<?php if ( isset( $_POST['bill_name1'] ) ) echo $bill_name1 = $_POST['bill_name1']; ?>" maxlength="35" class="long" />
	</p>
	<p class="<?php if ( array_key_exists( 'bill_name2', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_name2">Last Name *</label>
		<input type="text" id="bill_name2" name="bill_name2" value="<?php if ( isset( $_POST['bill_name2'] ) ) echo $bill_name2 = $_POST['bill_name2']; ?>" maxlength="35" class="long" />
	</p>
	<p class="<?php if ( array_key_exists( 'bill_street', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_street">Street Address *</label>
		<input type="text" id="bill_street" name="bill_street" value="<?php if ( isset( $_POST['bill_street'] ) ) echo $bill_street = $_POST['bill_street']; ?>" maxlength="100" class="long" />
	</p>
	<p class="<?php if ( array_key_exists( 'bill_city', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_city">City *</label>
		<input type="text" id="bill_city" name="bill_city" value="<?php if ( isset( $_POST['bill_city'] ) ) echo $bill_city = $_POST['bill_city']; ?>" maxlength="100" class="medium" />
	</p>
	<p class="<?php if ( array_key_exists( 'bill_state', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_state_us">State - US Residents *</label>
		<select id="bill_state_us" name="bill_state_us">
<?php
			// State
			if ( isset( $_POST['bill_state_us'] ) ) $bill_state_us = $_POST['bill_state_us']; else $bill_state_us = '';
			echo '<option value="">' . __( 'Select', 'mycred' );
			$this->list_option_us_states( $bill_state_us ); ?>

		</select>
	</p>
	<p class="<?php if ( array_key_exists( 'bill_state', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_state_non">State - All other *</label>
		<input type="text" id="bill_state_non" name="bill_state_non" value="<?php if ( isset( $_POST['bill_state_non'] ) ) echo $_POST['bill_state_non']; ?>" maxlength="100" class="medium" />
	</p>
	<p class="<?php if ( array_key_exists( 'bill_zip', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_zip">Zip/Postal Code *</label>
		<input type="text" id="bill_zip" name="bill_zip" value="<?php if ( isset( $_POST['bill_zip'] ) ) echo $_POST['bill_zip']; ?>" maxlength="12" class="short" />
	</p>
	<p class="<?php if ( array_key_exists( 'bill_country', $this->errors ) ) { echo 'error'; } ?>">
		<label for="bill_country">Country *</label>
		<select id="bill_country" name="bill_country">
			<option value="">Choose Country</option>
<?php
			// Country
			if ( isset( $_POST['bill_country'] ) ) $bill_country = $_POST['bill_country']; else $bill_country = '';
			$this->list_option_countries( $bill_country ); ?>

		</select>
	</p>
	<p class="<?php if ( array_key_exists( 'cust_email', $this->errors ) ) { echo 'error'; } ?>">
		<label for="cust_email">Email Address *</label>
		<input type="text" id="cust_email" name="cust_email" value="<?php echo $user->user_email; ?>" maxlength="100" class="long" />
	</p>
	<p class="<?php if ( array_key_exists( 'cust_phone', $this->errors ) ) { echo 'error'; } ?>">
		<label for="cust_phone">Phone Number</label>
		<input type="text" id="cust_phone" name="cust_phone" value="<?php if ( isset( $_POST['cust_phone'] ) ) echo $cust_phone = $_POST['cust_phone']; ?>" maxlength="20" class="medium" />
	</p>
<?php 		// Credit Card
			if ( $payment_method == 'card' ) { ?>

	<h3>Credit Card Information</h3>
	<input type="hidden" name="payment_method" value="card" />
	<p class="<?php if ( array_key_exists( 'card_number', $this->errors ) ) { echo 'error'; } ?>">
		<label for="card_number">Credit Card Number *</label>
		<input type="text" id="card_number" name="card_number" value="<?php if ( isset( $_POST['card_number'] ) ) echo $card_number = $_POST['card_number']; ?>" maxlength="19" class="medium" />
	</p>
	<p class="<?php if ( array_key_exists( 'card_expire_month', $this->errors ) ) { echo 'error'; } ?>">
		<label for="card_expire_month">Expiration Date *</label>
		<select id="card_expire_month" name="card_expire_month">
<?php
				if ( isset( $_POST['card_expire_month'] ) ) $card_expire_month = $_POST['card_expire_month']; else $card_expire_month = '';
				echo '<option value="">' . __( 'Month', 'mycred' ) . '</option>';
				$this->list_option_months( $card_expire_month ); ?>

		</select> <select id="card_expire_year" name="card_expire_year">
<?php
				if ( isset( $_POST['card_expire_year'] ) ) $card_expire_year = $_POST['card_expire_year']; else $card_expire_year = '';
				echo '<option value="">' . __( 'Year', 'mycred' ) . '</option>';
				$this->list_option_card_years( $card_expire_year ); ?>

		</select>
	</p>
	<p class="<?php if ( array_key_exists( 'card_cvv2', $this->errors ) ) { echo 'error'; } ?>">
		<label for="card_cvv2">CVV2 Number *</label>
		<input type="text" id="card_cvv2" name="card_cvv2" value="<?php if ( isset( $_POST['card_cvv2'] ) ) echo $card_cvv2 = $_POST['card_cvv2']; ?>" maxlength="4" class="short" />
	</p>
<?php		}

			// Check
			elseif ( $payment_method == 'check' ) { ?>

	<h3>Check Information</h3>
	<input type="hidden" name="payment_method" value="check" />
	<p class="<?php if ( array_key_exists( 'ach_routing', $this->errors ) ) { echo 'error'; } ?>">
		<label for="ach_routing">Routing Number *</label>
		<input type="text" id="ach_routing" name="ach_routing" value="<?php if ( isset( $_POST['ach_routing'] ) ) echo $ach_routing = $_POST['ach_routing']; ?>" maxlength="9" class="long" />
	</p>
	<p class="<?php if ( array_key_exists( 'ach_account', $this->errors ) ) { echo 'error'; } ?>">
		<label for="ach_account">Account Number *</label>
		<input type="text" id="ach_account" name="ach_account" value="<?php if ( isset( $_POST['ach_account'] ) ) echo $ach_account = $_POST['ach_account']; ?>" maxlength="17" class="long" />
	</p>
<?php 		} ?>

	<p class="submit"><input type="submit" name="process_button" id="process_button" value="Submit" /></p>
</form>
<?php
			$this->purchase_footer();
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
					<label class="subheader" for="<?php echo $this->field_id( 'dynip_sec_code' ); ?>"><?php _e( 'Dynamic IP Security Code', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'dynip_sec_code' ); ?>" id="<?php echo $this->field_id( 'dynip_sec_code' ); ?>" value="<?php echo $prefs['dynip_sec_code']; ?>" class="long" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'item_name' ); ?>"><?php _e( 'Item Name', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'item_name' ); ?>" id="<?php echo $this->field_id( 'item_name' ); ?>" value="<?php echo $prefs['item_name']; ?>" class="long" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'exchange' ); ?>"><?php echo $this->core->template_tags_general( __( '%plural% Exchange Rate', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><?php echo $this->core->format_creds( 1 ); ?> = <input type="text" name="<?php echo $this->field_name( 'exchange' ); ?>" id="<?php echo $this->field_id( 'exchange' ); ?>" value="<?php echo $prefs['exchange']; ?>" size="3" /> <span id="mycred-gateway-netbilling-currency">USD</span></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'num_attempts' ); ?>"><?php _e( 'Allowed Attempts', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'num_attempts' ); ?>" id="<?php echo $this->field_id( 'num_attempts' ); ?>" value="<?php echo $prefs['num_attempts']; ?>" size="3" /></div>
							<p><?php _e( 'Maximum number of attempts allowed for purchases.', 'mycred' ); ?></p>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Advanced', 'mycred' ); ?></label>
					<ol>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'disable_avs' ); ?>" id="<?php echo $this->field_id( 'disable_avs' ); ?>" value="1"<?php checked( $prefs['disable_avs'], 1 ); ?> />
							<label for="<?php echo $this->field_id( 'disable_avs' ); ?>"><?php _e( 'Disable AVS (Address Verification System) for credit card transactions.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'disable_cvv2' ); ?>" id="<?php echo $this->field_id( 'disable_cvv2' ); ?>" value="1"<?php checked( $prefs['disable_cvv2'], 1 ); ?> />
							<label for="<?php echo $this->field_id( 'disable_cvv2' ); ?>"><?php _e( 'Disable CVV2 (Card Verification Value 2) for credit card transactions.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'disable_fraud_checks' ); ?>" id="<?php echo $this->field_id( 'disable_fraud_checks' ); ?>" value="1"<?php checked( $prefs['disable_fraud_checks'], 1 ); ?> />
							<label for="<?php echo $this->field_id( 'disable_fraud_checks' ); ?>"><?php _e( 'Disable all fraud protection other than AVS/CVV2. (This implies disable_negative_db)', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'disable_negative_db' ); ?>" id="<?php echo $this->field_id( 'disable_negative_db' ); ?>" value="1"<?php checked( $prefs['disable_negative_db'], 1 ); ?> />
							<label for="<?php echo $this->field_id( 'disable_negative_db' ); ?>"><?php _e( 'Disable only the negative database component of the fraud protection system.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'disable_email_receipts' ); ?>" id="<?php echo $this->field_id( 'disable_email_receipts' ); ?>" value="1"<?php checked( $prefs['disable_email_receipts'], 1 ); ?> />
							<label for="<?php echo $this->field_id( 'disable_email_receipts' ); ?>"><?php _e( 'Disable automatic sending of both merchant and customer email receipts.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'disable_expiration_check' ); ?>" id="<?php echo $this->field_id( 'disable_expiration_check' ); ?>" value="1"<?php checked( $prefs['disable_expiration_check'], 1 ); ?> />
							<label for="<?php echo $this->field_id( 'disable_expiration_check' ); ?>"><?php _e( 'Disable immediate rejection of expired cards.', 'mycred' ); ?></label>
						</li>
					</ol>
<?php
		}

		/**
		 * Sanatize Prefs
		 * @since 0.1
		 * @version 1.0
		 */
		public function sanitise_preferences( $data ) {
			$data['sandbox'] = ( isset( $data['sandbox'] ) ) ? 1 : 0;
			
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
			
			$data['disable_avs'] = ( isset( $data['disable_avs'] ) ) ? 1 : 0;
			$data['disable_cvv2'] = ( isset( $data['disable_cvv2'] ) ) ? 1 : 0;
			$data['disable_fraud_checks'] = ( isset( $data['disable_fraud_checks'] ) ) ? 1 : 0;
			$data['disable_negative_db'] = ( isset( $data['disable_negative_db'] ) ) ? 1 : 0;
			$data['disable_email_receipts'] = ( isset( $data['disable_email_receipts'] ) ) ? 1 : 0;
			$data['disable_expiration_check'] = ( isset( $data['disable_expiration_check'] ) ) ? 1 : 0;
			
			return $data;
		}
	}
}
?>