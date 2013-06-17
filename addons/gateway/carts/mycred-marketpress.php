<?php
if ( !defined( 'WP_PLUGIN_DIR' ) ) exit;
/**
 * MarketPress Payment Gateway
 * @since 1.1
 * @version 1.0
 */
if ( !class_exists( 'MP_Gateway_myCRED' ) && function_exists( 'mp_register_gateway_plugin' ) ) {
	/**
	 * Locate the MarketPress base gateway file
	 * @from MarketPress::init_vars()
	 */
	$file = '/marketpress-includes/marketpress-gateways.php';
	if ( file_exists( WP_PLUGIN_DIR . '/wordpress-ecommerce' . $file ) )
		include_once( WP_PLUGIN_DIR . '/wordpress-ecommerce' . $file );
	elseif ( file_exists( WP_PLUGIN_DIR . $file ) )
		include_once( WP_PLUGIN_DIR . $file );
	elseif ( is_multisite() && file_exists( WPMU_PLUGIN_DIR . $file ) )
		include_once( WPMU_PLUGIN_DIR . $file );

	/**
	 * myCRED Custom Gateway
	 */
	class MP_Gateway_myCRED extends MP_Gateway_API {

		var $plugin_name = 'mycred';
		var $admin_name = 'myCRED';
		var $public_name = 'myCRED';
		var $method_img_url = '';
		var $method_button_img_url = '';
		var $force_ssl = false;
		var $ipn_url;
		var $skip_form = false;

		/**
		 * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
		 */
		function on_creation() {
			global $mp;
			$settings = get_option( 'mp_settings' );

			//set names here to be able to translate
			$this->admin_name = 'myCRED';
			$this->public_name = ( !empty( $settings['gateways']['mycred']['name'] ) ) ? $settings['gateways']['mycred']['name'] : apply_filters( 'mycred_label', myCRED_NAME );
			$this->method_img_url = plugins_url( 'assets/images/cred-icon32.png', myCRED_THIS );
			$this->method_button_img_url = $settings['gateways']['mycred']['name'];
		}
		
		/**
		 * Use Exchange
		 * Checks to see if exchange is needed.
		 * @since 1.1
		 * @version 1.0
		 */
		function use_exchange() {
			global $mp;

			$settings = get_option( 'mp_settings' );
			if ( $settings['currency'] == 'POINTS' ) return false;
			return true;
		}

		/**
		 * Return fields you need to add to the payment screen, like your credit card info fields
		 *
		 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
		 * @param array $shipping_info. Contains shipping info and email in case you need it
		 * @since 1.1
		 * @version 1.0
		 */
		function payment_form( $cart, $shipping_info ) {
			global $mp;
			
			$settings = get_option( 'mp_settings' );
			$mycred = mycred_get_settings();
			return '<div id="mp-mycred-balance">' . $mycred->template_tags_general( $settings['gateways']['mycred']['name'] ) . '</div>';
		}

		/**
		 * Return the chosen payment details here for final confirmation. You probably don't need
		 * to post anything in the form as it should be in your $_SESSION var already.
		 *
		 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
		 * @param array $shipping_info. Contains shipping info and email in case you need it
		 * @since 1.1
		 * @version 1.0
		 */
		function confirm_payment_form( $cart, $shipping_info ) {
			global $mp;

			$settings = get_option( 'mp_settings' );
			$mycred = mycred_get_settings();
			$user_id = get_current_user_id();
			return '<div id="mp-mycred-balance">' . $mycred->template_tags_user( $settings['gateways']['mycred']['instructions'], $user_id ) . '</div>';
		}
		
		function process_payment_form( $cart, $shipping_info ) { }

		/**
		 * Use this to do the final payment. Create the order then process the payment. If
		 * you know the payment is successful right away go ahead and change the order status
		 * as well.
		 * Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
		 * it will redirect to the next step.
		 *
		 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
		 * @param array $shipping_info. Contains shipping info and email in case you need it
		 * @since 1.1
		 * @version 1.0
		 */
		function process_payment( $cart, $shipping_info ) {
			global $mp;
			
			$settings = get_option('mp_settings');
			$timestamp = time();

			// This gateway requires buyer to be logged in
			if ( !is_user_logged_in() )
				$mp->cart_checkout_error(
					sprintf(
						__( 'Sorry, but you must be logged in to use this gateway. Please <a href="%s">Login</a> or <a href="%s">select a different payment method</a>.', 'mycred' ),
						wp_login_url( mp_checkout_step_url( 'checkout' ) ),
						mp_checkout_step_url( 'checkout' )
					)
				);
			
			$mycred = mycred_get_settings();
			$user_id = get_current_user_id();
			
			// Make sure current user is not excluded from using myCRED
			if ( $mycred->exclude_user( $user_id ) )
				$mp->cart_checkout_error(
					sprintf( __( 'Sorry, but you can not use this gateway as your account is excluded. Please <a href="%s">select a different payment method</a>.', 'mycred' ), mp_checkout_step_url( 'checkout' ) )
				);

			// Get total
			$totals = array();
			foreach ( $cart as $product_id => $variations ) {
				foreach ( $variations as $data ) {
					$totals[] = $mp->before_tax_price( $data['price'], $product_id ) * $data['quantity'];
				}
			}
			$total = array_sum( $totals );

			// Apply Coupons
			if ( $coupon = $mp->coupon_value( $mp->get_coupon_code(), $total ) ) {
				$total = $coupon['new_total'];
			}

			// Shipping Cost
			if ( ( $shipping_price = $mp->shipping_price() ) !== false ) {
				$total = $total + $shipping_price;
			}

			// Tax
			if ( ( $tax_price = $mp->tax_price() ) !== false ) {
				$total = $total + $tax_price;
			}

			$balance = $mycred->get_users_cred( $user_id );
			if ( $this->use_exchange() )
				$balance = $mycred->apply_exchange_rate( $mycred->number( $total ), $settings['gateways']['mycred']['exchange'] );
			
			// Check if there is enough to fund this
			if ( $balance >= $total ) {
				// Create MarketPress order
				$order_id = $mp->generate_order_id();
				$payment_info['gateway_public_name'] = $this->public_name;
				$payment_info['gateway_private_name'] = $this->admin_name;
				$payment_info['status'][$timestamp] = __( 'Paid', 'mycred' );
				$payment_info['total'] = $total;
				$payment_info['currency'] = $settings['currency'];
				$payment_info['method'] = __( 'myCRED', 'mycred' );
				$payment_info['transaction_id'] = $order_id;
				$paid = true;
				$result = $mp->create_order( $order_id, $cart, $shipping_info, $payment_info, $paid );
				
				$order = get_page_by_title( $result, 'OBJECT', 'mp_order' );
				// Deduct cost
				$mycred->add_creds(
					'marketpress_payment',
					$user_id,
					0-$total,
					$settings['gateways']['mycred']['log_template'],
					$order->ID,
					array( 'ref_type' => 'post' )
				);
				
				
				
			}
			// Insuffient Funds
			else {
				$mp->cart_checkout_error(
					sprintf( __( 'Insufficient Funds Please select a different payment method. <a href="%s">Go Back</a>', 'mycred' ), mp_checkout_step_url( 'checkout' ) )
				);
			}
		}
		
		function order_confirmation( $order ) { }

		/**
		 * Filters the order confirmation email message body. You may want to append something to
		 * the message. Optional
		 * @since 1.1
		 * @version 1.0
		 */
		function order_confirmation_email( $msg, $order ) {
			global $mp;
			$settings = get_option('mp_settings');

			if ( isset( $settings['gateways']['mycred']['email'] ) )
				$msg = $mp->filter_email( $order, $settings['gateways']['mycred']['email'] );
			else
				$msg = $settings['email']['new_order_txt'];

			return $msg;
		}

		/**
		 * Return any html you want to show on the confirmation screen after checkout. This
		 * should be a payment details box and message.
		 * @since 1.1
		 * @version 1.0
		 */
		function order_confirmation_msg( $content, $order ) {
			global $mp;
			$settings = get_option('mp_settings');

			$mycred = mycred_get_settings();
			$user_id = get_current_user_id();
			
			return $content . str_replace(
				'TOTAL',
				$mp->format_currency( $order->mp_payment_info['currency'], $order->mp_payment_info['total'] ),
				$mycred->template_tags_user( $settings['gateways']['mycred']['confirmation'], $user_id )
			);
		}

		/**
		 * myCRED Gateway Settings
		 * @since 1.1
		 * @version 1.0
		 */
		function gateway_settings_box( $settings ) {
			global $mp;
			$settings = get_option( 'mp_settings' );
			$mycred = mycred_get_settings();
			
			$name = apply_filters( 'mycred_label', myCRED_NAME );
			
			if ( empty( $settings['gateways']['mycred']['name'] ) )
				$settings['gateways']['mycred']['name'] = strip_tags( $name ) . ' ' . $mycred->template_tags_general( __( '%_singular% Balance', 'mycred' ) );
			
			if ( !isset( $settings['gateways']['mycred']['logo'] ) )
				$settings['gateways']['mycred']['logo'] = $this->method_button_img_url;
			
			if ( !isset( $settings['gateways']['mycred']['log_template'] ) )
				$settings['gateways']['mycred']['log_template'] = 'Payment for Order: #%order_id%';
			
			if ( !isset( $settings['gateways']['mycred']['exchange'] ) )
				$settings['gateways']['mycred']['exchange'] = 1;
			
			if ( !isset( $settings['gateways']['mycred']['instructions'] ) )
				$settings['gateways']['mycred']['instructions'] = 'Pay using your account balance.';
			
			if ( !isset( $settings['gateways']['mycred']['confirmation'] ) )
				$settings['gateways']['mycred']['confirmation'] = 'TOTAL amount has been deducted from your account. Your current balance is: %balance_f%';

			if ( !isset( $settings['gateways']['mycred']['email'] ) )
				$settings['gateways']['mycred']['email'] = $settings['email']['new_order_txt']; ?>

<div id="mp_cubepoints_payments" class="postbox mp-pages-msgs">
	<h3 class="handle"><span><?php echo $name . ' ' . __( 'Settings', 'mycred' ); ?></span></h3>
	<div class="inside">
		<span class="description"><?php echo sprintf( __( 'Let your users pay for items in their shopping cart using their %s Account. Note! This gateway requires your users to be logged in when making a purchase!', 'mycred' ), $name ); ?></span>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="mycred-method-name"><?php _e( 'Method Name', 'mycred' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Enter a public name for this payment method that is displayed to users - No HTML', 'mycred' ); ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['mycred']['name'] ); ?>" style="width: 100%;" name="mp[gateways][mycred][name]" id="mycred-method-name" type="text" /></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mycred-method-logo"><?php _e( 'Gateway Logo URL', 'mycred' ); ?></label></th>
				<td>
					<p><input value="<?php echo esc_attr( $settings['gateways']['mycred']['logo'] ); ?>" style="width: 100%;" name="mp[gateways][mycred][logo]" id="mycred-method-logo" type="text" /></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mycred-log-template"><?php _e( 'Log Template', 'mycred' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Log entry template for successful payments. Available template tags: %order_id%, %order_link%', 'mycred' ); ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['mycred']['log_template'] ); ?>" style="width: 100%;" name="mp[gateways][mycred][log_template]" id="mycred-log-template" type="text" /></p>
				</td>
			</tr>
<?php
			// Exchange rate
			if ( $this->use_exchange() ) :
				$exchange_desc = __( 'How much is 1 %_singular% worth in %currency%?', 'mycred' );
				$exchange_desc = $mycred->template_tags_general( $exchange_desc );
				$exchange_desc = str_replace( '%currency%', $settings['currency'], $exchange_desc ); ?>

			<tr>
				<th scope="row"><label for="mycred-exchange-rate"><?php _e( 'Exchange Rate', 'mycred' ); ?></label></th>
				<td>
					<span class="description"><?php echo $exchange_desc; ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['mycred']['exchange'] ); ?>" size="8" name="mp[gateways][mycred][exchange]" id="mycred-exchange-rate" type="text" /></p>
				</td>
			</tr>
<?php		endif; ?>

			<tr>
				<th scope="row"><label for="mycred-instructions"><?php _e( 'User Instructions', 'mycred' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Information to show users before payment.', 'mycred' ); ?></span>
					<p><?php wp_editor( $settings['gateways']['mycred']['instructions'] , 'mycred-instructions', array( 'textarea_name' => 'mp[gateways][mycred][instructions]' ) ); ?><br />
					<span class="description"><?php _e( 'Available template tags are: %balance% and %balance_f% for users current balance.', 'mycred' ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mycred-confirmation"><?php _e( 'Confirmation Information', 'mycred' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Information to display on the order confirmation page. - HTML allowed', 'mycred' ); ?></span>
					<p><?php wp_editor( $settings['gateways']['mycred']['confirmation'], 'mycred-confirmation', array( 'textarea_name' => 'mp[gateways][mycred][confirmation]' ) ); ?><br />
					<span class="description"><?php _e( 'Available template tags: TOTAL - total cart cost, %balance% and %balance_f% - users current balance.', 'mycred' ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mycred-email"><?php _e( 'Order Confirmation Email', 'mycred' ); ?></label></th>
				<td>
					<span class="description"><?php echo sprintf( __( 'This is the email text to send to those who have made %s checkouts. It overrides the default order checkout email. These codes will be replaced with order details: CUSTOMERNAME, ORDERID, ORDERINFO, SHIPPINGINFO, PAYMENTINFO, TOTAL, TRACKINGURL. No HTML allowed.', 'mycred' ), $name ); ?></span>
					<p><textarea id="mycred-email" name="mp[gateways][mycred][email]" class="mp_emails_txt"><?php echo esc_textarea( $settings['gateways']['mycred']['email'] ); ?></textarea></p>
					<span class="description"><?php _e( 'Available template tags: %balance% or %balance_f% for users balance.', 'mycred' ); ?></span>
				</td>
			</tr>
		</table>
	</div>
</div>
<?php
		}

		/**
		 * Filter Gateway Settings
		 * @since 1.1
		 * @version 1.0
		 */
		function process_gateway_settings( $settings ) {
			// Name (no html)
			$settings['gateways']['mycred']['name'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['mycred']['name'] ) );

			// Log Template (no html)
			$settings['gateways']['mycred']['log_template'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['mycred']['log_template'] ) );
			$settings['gateways']['mycred']['logo'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['mycred']['logo'] ) );

			// Exchange rate (if used)
			if ( $this->use_exchange() ) {
				// Decimals must start with a zero
				if ( $settings['gateways']['mycred']['exchange'] != 1 && substr( $settings['gateways']['mycred']['exchange'], 0, 1 ) != '0' ) {
					$settings['gateways']['mycred']['exchange'] = (float) '0' . $settings['gateways']['mycred']['exchange'];
				}
				// Decimal seperator must be punctuation and not comma
				$settings['gateways']['mycred']['exchange'] = str_replace( ',', '.', $settings['gateways']['mycred']['exchange'] );
			}
			else
				$settings['gateways']['mycred']['exchange'] = 1;
			
			// Filter Instruction & Confirmation (if needed)
			if ( !current_user_can( 'unfiltered_html' ) ) {
				$settings['gateways']['mycred']['instructions'] = wp_filter_post_kses( $settings['gateways']['mycred']['instructions'] );
				$settings['gateways']['mycred']['confirmation'] = wp_filter_post_kses( $settings['gateways']['mycred']['confirmation'] );
			}

			// Email (no html)
			$settings['gateways']['mycred']['email'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['mycred']['email'] ) );

			return $settings;
		}
	}
	// Register Gateway
	mp_register_gateway_plugin( 'MP_Gateway_myCRED', 'mycred', 'myCRED' );
}
/**
 * Filter the myCRED Log
 * Parses the %order_id% and %order_link% template tags.
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_marketpress_parse_log' ) ) {
	add_filter( 'mycred_parse_log_entry_marketpress_payment', 'mycred_marketpress_parse_log', 90, 2 );
	function mycred_marketpress_parse_log( $content, $log_entry )
	{
		// Prep
		global $mp;
		$mycred = mycred_get_settings();
		$order = get_post( $log_entry->ref_id );
		$order_id = $order->post_title;
		$user_id = get_current_user_id();

		// Order ID
		$content = str_replace( '%order_id%', $order->post_title, $content );

		// Link to order if we can edit plugin or are the user who made the order
		if ( $user_id == $log_entry->user_id || $mycred->can_edit_plugin( $user_id ) ) {
			$track_link = '<a href="' . mp_orderstatus_link( false, true ) . $order_id . '/' . '">#' . $order->post_title . '/' . '</a>';
			$content = str_replace( '%order_link%', $track_link, $content );
		}
		else {
			$content = str_replace( '%order_link%', '#' . $order_id, $content );
		}

		return $content;
	}
}
?>