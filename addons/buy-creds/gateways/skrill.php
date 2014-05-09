<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Skrill class
 * Skrill (Moneybookers) - Payment Gateway
 * @since 0.1
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Skrill' ) ) {
	class myCRED_Skrill extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			parent::__construct( array(
				'id'               => 'skrill',
				'label'            => 'Skrill Payment',
				'gateway_logo_url' => plugins_url( 'assets/images/skrill.png', myCRED_PURCHASE ),
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
				)
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
		 * IPN - Is Valid Call
		 * Replaces the default check
		 * @since 1.4
		 * @version 1.0
		 */
		public function IPN_is_valid_call() {
			$result = true;

			$check = $_POST['merchant_id'] . $_POST['transaction_id'] . strtoupper( md5( $this->prefs['word'] ) ) . $_POST['mb_amount'] . $_POST['mb_currency'] . $_POST['status'];
			if ( strtoupper( md5( $check ) ) !== $_POST['md5sig'] )
				$result = false;
			
			if ( $_POST['pay_to_email'] != trim( $this->prefs['account'] ) )
				$result = false;

			if ( ! $result )
				$this->new_log_entry( ' > ' . __( 'Invalid Call', 'mycred' ) );
			else
				$this->new_log_entry( sprintf( __( 'Caller verified as "%s"', 'mycred' ), $this->id ) );

			return $result;
		}

		/**
		 * Process Handler
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
				'merchant_id',
				'transaction_id',
				'amount',
				'currency',
				'status',
				'md5sig',
				'sales_data',
				'pay_to_email'
			);

			// All required fields exists
			if ( $this->IPN_has_required_fields( $required_fields, 'POST' ) ) {

				// Validate call
				if ( $this->IPN_is_valid_call() ) {

					$valid_call = true;
					
					// Validate sale
					$sales_data = false;
					$sales_data = $this->IPN_is_valid_sale( 'sales_data', 'amount', 'transaction_id' );
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
				if ( $_POST['status']  == '2' ) {
							
					$this->new_log_entry( sprintf( __( 'Attempting to credit %s to users account', 'mycred' ), $this->core->plural() ) );

					$data = array(
						'transaction_id' => $_POST['transaction_id'],
						'skrill_ref'     => $_POST['mb_transaction_id'],
						'md5'            => $_POST['md5sig'],
						'sales_data'     => implode( '|', $sales_data )
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

			$this->save_log_entry( $_POST['transaction_id'], $outcome );

			do_action( "mycred_buycred_{$this->id}_end", $this->processing_log, $_REQUEST );
		}

		/**
		 * Results Handler
		 * @since 0.1
		 * @version 1.1
		 */
		public function returning() {
			if ( isset( $_GET['transaction_id'] ) && ! empty( $_GET['transaction_id'] ) && isset( $_GET['msid'] ) && ! empty( $_GET['msid'] ) ) {
				$this->get_page_header( __( 'Success', 'mycred' ), $this->get_thankyou() );
				echo '<h1>' . __( 'Thank you for your purchase', 'mycred' ) . '</h1>';
				$this->get_page_footer();
				exit;
			}
		}

		/**
		 * Buy Handler
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function buy() {
			if ( ! isset( $this->prefs['account'] ) || empty( $this->prefs['account'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$token = $this->create_token();

			// Location
			$location = 'https://www.moneybookers.com/app/payment.pl';

			// Finance
			$currency = $this->prefs['currency'];

			// Amount
			$amount = $this->core->number( $_REQUEST['amount'] );
			$amount = abs( $amount );

			// Get Cost
			$cost = $this->get_cost( $amount );

			// Thank you page
			$thankyou_url = $this->get_thankyou();

			// Cancel page
			$cancel_url = $this->get_cancelled();

			$to = $this->get_to();
			$from = $this->current_user_id;

			$transaction_id = 'BUYCRED' . date_i18n( 'U' ) . $to . $from;

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
			add_filter( 'mycred_dropdown_currencies', array( $this, 'skrill_currencies' ) );
			$prefs = $this->prefs; ?>

<label class="subheader" for="<?php echo $this->field_id( 'currency' ); ?>"><?php _e( 'Currency', 'mycred' ); ?></label>
<ol>
	<li>
		<?php $this->currencies_dropdown( 'currency', 'mycred-gateway-skrill-currency' ); ?>

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
		<div class="h2"><?php echo $this->core->format_creds( 1 ); ?> = <input type="text" name="<?php echo $this->field_name( 'exchange' ); ?>" id="<?php echo $this->field_id( 'exchange' ); ?>" value="<?php echo $prefs['exchange']; ?>" size="3" /> <span id="mycred-gateway-skrill-currency"><?php echo ( empty( $prefs['currency'] ) ) ? __( 'Select currency', 'mycred' ) : $prefs['currency']; ?></span></div>
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
			$new_data = array();

			$new_data['sandbox']           = ( isset( $data['sandbox'] ) ) ? 1 : 0;
			$new_data['currency']          = sanitize_text_field( $data['currency'] );
			$new_data['account']           = sanitize_text_field( $data['account'] );
			$new_data['word']              = sanitize_text_field( $data['word'] );
			$new_data['email_receipt']     = ( isset( $data['email_receipt'] ) ) ? 1 : 0;
			$new_data['item_name']         = sanitize_text_field( $data['item_name'] );
			$new_data['exchange']          = ( ! empty( $data['exchange'] ) ) ? $data['exchange'] : 1;
			$new_data['account_title']     = substr( $data['account_title'], 0, 30 );
			$new_data['account_logo']      = sanitize_text_field( $data['account_logo'] );
			$new_data['confirmation_note'] = substr( $data['confirmation_note'], 0, 240 );

			// If exchange is less then 1 we must start with a zero
			if ( $new_data['exchange'] != 1 && in_array( substr( $new_data['exchange'], 0, 1 ), array( '.', ',' ) ) )
				$new_data['exchange'] = (float) '0' . $new_data['exchange'];

			return $new_data;
		}
	}
}
?>