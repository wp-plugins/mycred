<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Skrill class
 * Skrill (Moneybookers) - Payment Gateway
 * 
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Skrill' ) ) {
	class myCRED_Skrill extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			parent::__construct( array(
				'id'       => 'skrill',
				'defaults' => array(
					'sandbox'           => 0,
					'currency'          => '',
					'account'           => '',
					'word'              => '',
					'account_title'     => '',
					'account_logo'      => '',
					'confirmation_note' => '',
					'email_receipt'     => 0,
					'item_name'         => __( 'Purchase of myCRED %plural%', 'mycred' ),
					'exchange'          => 1
				),
				'allowed'  => array( 'pay_to_email', 'pay_from_email', 'merchant_id', 'customer_id', 'transaction_id', 'mb_transaction_id', 'mb_amount', 'mb_currency', 'status', 'failed_reason_code', 'md5sig', 'sha2sig', 'amount', 'currency', 'payment_type', 'merchant_fields', 'sales_data' )
			), $gateway_prefs );
			
		}

		/**
		 * Adjust Currencies
		 * @since 1.0.6
		 * @version 1.0
		 */
		public function skrill_currencies( $currencies ) {
			$currencies['RON'] = 'Romanian Leu';
			$currencies['TRY'] = 'New Turkish Lira';
			$currencies['RON'] = 'Romanian Leu';
			$currencies['AED'] = 'Utd. Arab Emir. Dirham';
			$currencies['MAD'] = 'Moroccan Dirham';
			$currencies['QAR'] = 'Qatari Rial';
			$currencies['SAR'] = 'Saudi Riyal';
			$currencies['SKK'] = 'Slovakian Koruna';
			$currencies['EEK'] = 'Estonian Kroon';
			$currencies['BGN'] = 'Bulgarian Leva';
			$currencies['ISK'] = 'Iceland Krona';
			$currencies['INR'] = 'Indian Rupee';
			$currencies['LVL'] = 'Latvian Lat';
			$currencies['KRW'] = 'South-Korean Won';
			$currencies['ZAR'] = 'South-African Rand';
			$currencies['HRK'] = 'Croatian Kuna';
			$currencies['LTL'] = 'Lithuanian Litas';
			$currencies['JOD'] = 'Jordanian Dinar';
			$currencies['OMR'] = 'Omani Rial';
			$currencies['RSD'] = 'Serbian Dinar';
			$currencies['TND'] = 'Tunisian Dinar';
			
			unset( $currencies['MXN'] );
			unset( $currencies['BRL'] );
			unset( $currencies['PHP'] );
			
			return $currencies;
		}

		/**
		 * Process Handler
		 * @since 0.1
		 * @version 1.0
		 */
		public function process() {
			// Prep
			$id = $this->id;
			$error = false;
			$log_entry = array();

			$data = $this->POST_to_data();
			if ( $this->prefs['sandbox'] ) {
				$log_entry[] = 'Incoming Test IPN Call at ' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			}
			else {
				$log_entry[] = 'Incoming IPN Call' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			}

			// Step 1. Compare md5
			if ( !empty( $this->prefs['word'] ) ) {
				$check = $data['merchant_id'] . $data['transaction_id'] . strtoupper( md5( $this->prefs['word'] ) ) . $data['mb_amount'] . $data['mb_currency'] . $data['status'];
				if ( strtoupper( md5( $check ) ) !== $data['md5sig'] ) {
					$log_entry[] = 'MD5 mismatch: [' . strtoupper( md5( $check ) ) . '] [' . $data['md5sig'] . ']';
					$error = true;
				}
			}

			// Step 2. Verify Sales Data
			$sales_data = $this->decode_sales_data( $data['sales_data'] );
			$s_data = explode( '|', $sales_data );

			// to|from|amount|cost|currency|token|extra
			list ( $_to, $_from, $_amount, $cost, $_currency, $token, $other ) = $s_data;

			// Verify Token
			if ( !$this->verify_token( $_from, trim( $token ) ) ) {
				$log_entry[] = 'Could not verify token: [' . $token . ']';
				$error = true;
			}

			// Make sure Purchase is unique
			if ( !$this->transaction_id_is_unique( $data['transaction_id'] ) ) {
				$log_entry[] = 'Transaction ID previously used: [' . $data['transaction_id'] . ']';
				$error = true;
			}

			// Make sure accounts match
			if ( $data['pay_to_email'] != trim( $this->prefs['account'] ) ) {
				$log_entry[] = 'Recipient Email mismatch: [' . $data['pay_to_email'] . ']';
				$error = true;
			}

			// Verify Currency
			if ( $data['mb_currency'] != $this->prefs['currency'] || $data['mb_currency'] != $_currency ) {
				$log_entry[] = 'Currency mismatch: [' . $data['mb_currency'] . '] [' . $_currency . ']';
				$error = true;
			}

			// Verify Cost
			$amount = $this->core->number( $data['amount'] );
			$_cost = $amount*$this->prefs['exchange'];
			if ( $cost != $_cost ) {
				$log_entry[] = 'Amount mismatch: [' . $cost . '] [' . $_cost . ']';
				$error = true;
			}

			// Step 3. Act acording to our findings
			if ( $error === false ) {

				// Pending transaction
				if ( $data['status'] == '0' ) {
					$log_entry[] = 'Transaction Pending';
					do_action( "mycred_buy_cred_{$id}_pending", $data );
				}

				// Completed transaction
				elseif ( $data['status']  == '2' ) {
					// Highlight test purchases
					$entry = $this->get_entry( $_to, $_from );
					$entry = str_replace( '%gateway%', 'Skrill', $entry );
					if ( $this->prefs['sandbox'] ) $entry = 'TEST ' . $entry;

					$data = array(
						'transaction_id' => $data['transaction_id'],
						'skrill_ref'     => $data['mb_transaction_id'],
						'md5'            => $data['md5sig'],
						'sales_data'     => $sales_data
					);

					// Add creds
					$this->core->add_creds(
						'buy_creds_with_skrill',
						$_to,
						$amount,
						$entry,
						$_from,
						$data
					);

					$log_entry[] = 'CREDs Added.';
					do_action( "mycred_buy_cred_{$id}_approved", $data );
				}

				// Cancelled transaction
				elseif ( $data['status'] == '-1' ) {
					$log_entry[] = 'Transaction Cancelled';
					do_action( "mycred_buy_cred_{$id}_cancelled", $data );
				}

				// Failed transaction
				else {
					$log_entry[] = 'Transaction Failed';
					do_action( "mycred_buy_cred_{$id}_failed", $data );
				}
			}

			// Error
			else {
				do_action( "mycred_buy_cred_{$id}_error", $log_entry, $data );
			}

			// Step 4. Log if need be
			do_action( "mycred_buy_cred_{$id}_end", $log_entry, $data );
			unset( $data );

			// Respond & Die
			header( "HTTP/1.1 200 OK" );
			die();
		}

		/**
		 * Results Handler
		 * @since 0.1
		 * @version 1.0
		 */
		public function returning() {
			if ( isset( $_GET['transaction_id'] ) && !empty( $_GET['transaction_id'] ) && isset( $_GET['msid'] ) && !empty( $_GET['msid'] ) ) {
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
		 * @version 1.0
		 */
		public function buy() {
			if ( !isset( $this->prefs['account'] ) || empty( $this->prefs['account'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$amount = $_REQUEST['amount'];
			$home = get_bloginfo( 'url' );
			$logo_url = plugins_url( 'images/skrill.png', myCRED_PURCHASE );
			$token = $this->create_token();
			$transaction_id = 'BUYCRED' . date_i18n( 'U' ) . $this->get_to();

			// Location
			$location = 'https://www.moneybookers.com/app/payment.pl';

			// Finance
			$currency = $this->prefs['currency'];
			$exchange = $this->prefs['exchange'];

			$amount =  $this->core->number( $amount );
			$amount = abs( $amount );

			$cost = $amount*$exchange;
			$cost = $this->core->number( $cost );

			// Thank you page
			$thankyou_url = $this->get_thankyou();

			// Cancel page
			$cancel_url = $this->get_cancelled();

			$to = $this->get_to();
			$from = $this->current_user_id;

			// Let others play
			$extra = apply_filters( 'mycred_skrill_extra', '', $amount, $from, $to, $this->prefs, $this->core );
			unset( $_REQUEST );

			// Start constructing merchant details
			$hidden_fields = array(
				'pay_to_email'    => $this->prefs['account'],
				'transaction_id'  => $transaction_id,
				'return_url'      => $thankyou_url,
				'cancel_url'      => $cancel_url,
				'status_url'      => $this->callback_url(),
				'return_url_text' => __( 'Return to ', 'mycred' ) . get_bloginfo( 'name' ),
				'hide_login'      => 1
			);

			// Customize Checkout Page
			if ( isset( $this->prefs['account_title'] ) && !empty( $this->prefs['account_title'] ) )
				$hidden_fields = array_merge_recursive( $hidden_fields, array(
					'recipient_description' => $this->core->template_tags_general( $this->prefs['account_title'] )
				) );

			if ( isset( $this->prefs['account_logo'] ) && !empty( $this->prefs['account_logo'] ) )
				$hidden_fields = array_merge_recursive( $hidden_fields, array(
					'logo_url'              => $this->prefs['account_logo']
				) );

			if ( isset( $this->prefs['confirmation_note'] ) && !empty( $this->prefs['confirmation_note'] ) )
				$hidden_fields = array_merge_recursive( $hidden_fields, array(
					'confirmation_note'     => $this->core->template_tags_general( $this->prefs['confirmation_note'] )
				) );

			// If we want an email receipt for purchases
			if ( isset( $this->prefs['email_receipt'] ) && !empty( $this->prefs['email_receipt'] ) )
				$hidden_fields = array_merge_recursive( $hidden_fields, array(
					'status_url2'           => $this->prefs['account']
				) );

			// Sale Details
			// to|from|amount|cost|currency|token|extra
			$sales_data = $to . '|' . $from . '|' . $amount . '|' . $cost . '|' . $currency . '|' . $token . '|' . $extra;
			$item_name = str_replace( '%number%', $amount, $this->prefs['item_name'] );
			$sale_details = array(
				'merchant_fields'     => 'sales_data',
				'sales_data'          => $this->encode_sales_data( $sales_data ),

				'amount'              => $cost,
				'currency'            => $currency,

				'detail1_description' => __( 'Product:', 'mycred' ),
				'detail1_text'        => $this->core->template_tags_general( $item_name )
			);
			$hidden_fields = array_merge_recursive( $hidden_fields, $sale_details );

			// Gifting
			if ( $to != $from ) {
				$user = get_userdata( $to );
				$gift_details = array(
					'detail2_description' => __( 'Gift to:', 'mycred' ),
					'detail2_text'        => $user->display_name . ' ' . __( '(author)', 'mycred' )
				);
				$hidden_fields = array_merge_recursive( $hidden_fields, $gift_details );
				unset( $user );
			}

			// Generate processing page
			$this->purchase_header( __( 'Processing payment &hellip;', 'mycred' ) );
			$this->form_with_redirect( $hidden_fields, $location, $logo_url, '', 'sales_data' );
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
			add_filter( 'mycred_dropdown_currencies', array( $this, 'skrill_currencies' ) );
			$prefs = $this->prefs; ?>

					<label class="subheader" for="<?php echo $this->field_id( 'sandbox' ); ?>"><?php _e( 'Sandbox Mode', 'mycred' ); ?></label>
					<ol>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'sandbox' ); ?>" id="<?php echo $this->field_id( 'sandbox' ); ?>" value="1"<?php checked( $prefs['sandbox'], 1 ); ?> /><span class="description"><?php _e( 'Remember to use your Test Merchant Account when Sandbox mode is active!', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'currency' ); ?>"><?php _e( 'Currency', 'mycred' ); ?></label>
					<ol>
						<li>
							<?php $this->currencies_dropdown( 'currency' ); ?>

						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'account' ); ?>"><?php _e( 'Merchant Account Email', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'account' ); ?>" id="<?php echo $this->field_id( 'account' ); ?>" value="<?php echo $prefs['account']; ?>" class="long" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'word' ); ?>"><?php _e( 'Secret Word', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'word' ); ?>" id="<?php echo $this->field_id( 'word' ); ?>" value="<?php echo $prefs['word']; ?>" class="medium" /></div>
							<span class="description"><?php _e( 'You can set your secret word under "Merchant Tools" in your Skrill Account.', 'mycred' ); ?></span>
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
					<label class="subheader" for="<?php echo $this->field_id( 'email_receipt' ); ?>"><?php _e( 'Confirmation Email', 'mycred' ); ?></label>
					<ol>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'email_receipt' ); ?>" id="<?php echo $this->field_id( 'email_receipt' ); ?>" value="1"<?php checked( $prefs['email_receipt'], 1 ); ?> /><?php _e( 'Ask Skrill to send me a confirmation email for each successful purchase.', 'mycred' ); ?>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Checkout Page', 'mycred' ); ?></label>
					<ol>
						<li>
							<label for="<?php echo $this->field_id( 'account_title' ); ?>"><?php _e( 'Title', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'account_title' ); ?>" id="<?php echo $this->field_id( 'account_title' ); ?>" value="<?php echo $prefs['account_title']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'If left empty, your account email is used as title on the Skill Payment Page.', 'mycred' ); ?></span>
						</li>
						<li>
							<label for="<?php echo $this->field_id( 'account_logo' ); ?>"><?php _e( 'Logo URL', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'account_logo' ); ?>" id="<?php echo $this->field_id( 'account_title' ); ?>" value="<?php echo $prefs['account_logo']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'The URL to the image you want to use on the top of the gateway. For best integration results we recommend you use logos with dimensions up to 200px in width and 50px in height.', 'mycred' ); ?></span>
						</li>
						<li>
							<label for="<?php echo $this->field_id( 'confirmation_note' ); ?>"><?php _e( 'Confirmation Note', 'mycred' ); ?></label>
							<textarea rows="10" cols="50" name="<?php echo $this->field_name( 'confirmation_note' ); ?>" id="<?php echo $this->field_id( 'confirmation_note' ); ?>" class="large-text code"><?php echo $prefs['confirmation_note']; ?></textarea>
							<span class="description"><?php _e( 'Optional text to show user once a transaction has been successfully completed. This text is shown by Skrill.', 'mycred' ); ?></span>
						</li>
						<li>
							<h3><?php _e( 'Important!', 'mycred' ); ?></h3>
							<p><span class="description"><strong>1. </strong><?php echo $this->core->template_tags_general( __( 'By default all Skrill Merchant account accept payments via Bank Transfers. When a user selects this option, no %_plural% are awarded! You will need to manually award these once the bank transfer is completed.', 'mycred' ) ); ?></span></p>
							<p><span class="description"><strong>2. </strong><?php _e( 'By default purchases made using Skrill will result in users having to signup for a Skrill account (if they do not have one already). You can contact Skrill Merchant Services and request to disable this feature.', 'mycred' ); ?></span></p>
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
			$data['sandbox'] = ( !isset( $data['sandbox'] ) ) ? 0 : 1;
			$data['email_receipt'] = ( !isset( $data['email_receipt'] ) ) ? 0 : 1;
			
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

			$data['account_title'] = substr( $data['account_title'], 0, 30 );
			$data['confirmation_note'] = substr( $data['confirmation_note'], 0, 240 );
			
			return $data;
		}
	}
}
?>