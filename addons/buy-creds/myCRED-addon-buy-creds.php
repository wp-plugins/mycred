<?php
/**
 * Addon: buyCRED
 * Addon URI: http://mycred.me/add-ons/buycred/
 * Version: 1.0
 * Description: The <strong>buy</strong>CRED Add-on allows your users to buy points using PayPal, Skrill (Moneybookers) or NETbilling. <strong>buy</strong>CRED can also let your users buy points for other members.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
// Translate Header (by Dan bp-fr)
$mycred_addon_header_translate = array(
	__( 'buyCRED', 'mycred' ),
	__( 'The <strong>buy</strong>CRED Add-on allows your users to buy points using PayPal, Skrill (Moneybookers) or NETbilling. <strong>buy</strong>CRED can also let your users buy points for other members.', 'mycred' )
);

if ( !defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_PURCHASE',         __FILE__ );
define( 'myCRED_PURCHASE_VERSION', myCRED_VERSION . '.1' );
define( 'myCRED_PURCHASE_DIR',     myCRED_ADDONS_DIR . 'buy-creds/' );
/**
 * Payment Gateway factory
 */
require_once( myCRED_PURCHASE_DIR . 'abstracts/mycred-abstract-payment-gateway.php' );
/**
 * Payment Gateways, if you do not want to use one just comment it out.
 */
require_once( myCRED_PURCHASE_DIR . 'gateways/paypal-standard.php' );
require_once( myCRED_PURCHASE_DIR . 'gateways/netbilling.php' );
require_once( myCRED_PURCHASE_DIR . 'gateways/skrill.php' );
require_once( myCRED_PURCHASE_DIR . 'gateways/zombaio.php' );
/**
 * myCRED_Buy_CREDs class
 *
 * 
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Buy_CREDs' ) ) {
	class myCRED_Buy_CREDs extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Buy_CREDs', array(
				'module_name' => 'gateways',
				'option_id'   => 'mycred_pref_buycreds',
				'defaults'    => array(
					'installed'     => array(),
					'active'        => array(),
					'gateway_prefs' => array()
				),
				'labels'      => array(
					'menu'        => __( 'Payment Gateways', 'mycred' ),
					'page_title'  => __( 'Payment Gateways', 'mycred' ),
					'page_header' => __( 'Payment Gateways', 'mycred' )
				),
				'screen_id'   => 'myCRED_page_gateways',
				'accordion'   => true,
				'add_to_core' => true,
				'menu_pos'    => 85
			) );

			add_action( 'mycred_help',           array( $this, 'help' ), 10, 2 );
		}

		/**
		 * Process
		 * Processes Gateway returns and IPN calls
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_init() {
			// Make sure we have installed gateways.
			$installed = $this->get();
			if ( empty( $installed ) ) return;

			/**
			 * Step 1 - Look for returns
			 * Runs though all active payment gateways and lets them decide if this is the
			 * user returning after a remote purchase. Each gateway should know what to look
			 * for to determen if they are responsible for handling the return.
			 */
			foreach ( $installed as $id => $data ) {
				if ( !$this->is_active( $id ) ) continue;
				$this->call( 'returning', $installed[$id]['callback'] );
			}

			/**
			 * Init Gateway
			 */
			if ( isset( $_REQUEST['mycred_call'] ) || ( isset( $_REQUEST['mycred_buy'] ) && is_user_logged_in() ) || ( isset( $_GET['wp_zombaio_ips'] ) || isset( $_GET['ZombaioGWPass'] ) ) ) {
				if ( isset( $_GET['wp_zombaio_ips'] ) || isset( $_GET['ZombaioGWPass'] ) ) {
					$gateway = new myCRED_Zombaio( $this->gateway_prefs );
				}
				else {
					$gateway_id = ( isset( $_REQUEST['mycred_call'] ) ) ? $_REQUEST['mycred_call'] : $_REQUEST['mycred_buy'];
					if ( array_key_exists( $gateway_id, $installed ) && $this->is_active( $gateway_id ) ) {
						$class = $installed[$gateway_id]['callback'][0];
						$gateway = new $class( $this->gateway_prefs );
					}
				}
			}

			/**
			 * Step 2 - Process
			 * Next we check to see if there is a purchase request, either made locally though
			 * a form submission or by gateways calling remotly (see PayPal).
			 */
			if ( isset( $_REQUEST['mycred_call'] ) || ( isset( $_GET['wp_zombaio_ips'] ) || isset( $_GET['ZombaioGWPass'] ) ) ) {
				$gateway->process();
			}

			/**
			 * Step 3 - Buy Requests
			 * Finally we check if there is a request to buy creds. A request must be made by nominating
			 * the payment gateway that we want to use. Locally managed purchases can use this to show
			 * the form again if i.e. an error was detected. $this->core->buy_creds
			 */
			if ( isset( $_REQUEST['mycred_buy'] ) && is_user_logged_in() ) {
				if ( !isset( $_REQUEST['token'] ) || !wp_verify_nonce( $_REQUEST['token'], 'mycred-buy-creds' ) ) return;
				if ( !isset( $_REQUEST['amount'] ) || $_REQUEST['amount'] == 0 || $_REQUEST['amount'] < $this->core->buy_creds['minimum'] ) return;
				$gateway->buy();
			}

			// Finish by adding our shortcodes
			add_shortcode( 'mycred_buy',      array( $this, 'render_shortcode_basic' ) );
			add_shortcode( 'mycred_buy_form', array( $this, 'render_shortcode_form' ) );
		}

		/**
		 * Get Payment Gateways
		 * Retreivs all available payment gateways that can be used to buy CREDs.
		 * @since 0.1
		 * @version 1.0
		 */
		public function get() {
			// Defaults
			$installed['paypal-standard'] = array(
				'title'    => __( 'PayPal Payments Standard' ),
				'callback' => array( 'myCRED_PayPal_Standard' )
			);
			$installed['netbilling'] = array(
				'title'    => __( 'NETbilling' ),
				'callback' => array( 'myCRED_NETbilling' )
			);
			$installed['skrill'] = array(
				'title'    => __( 'Skrill (Moneybookers)' ),
				'callback' => array( 'myCRED_Skrill' )
			);
			$installed['zombaio'] = array(
				'title'    => __( 'Zombaio' ),
				'callback' => array( 'myCRED_Zombaio' )
			);
			$installed = apply_filters( 'mycred_setup_gateways', $installed );

			$this->installed = $installed;
			return $installed;
		}

		/**
		 * Page Header
		 * @since 1.3
		 * @version 1.0
		 */
		public function settings_header() {
			wp_dequeue_script( 'bpge_admin_js_acc' );
			wp_enqueue_script( 'mycred-admin' );
			wp_enqueue_style( 'mycred-admin' ); ?>

<style type="text/css">
#icon-myCRED, .icon32-posts-mycred_email_notice, .icon32-posts-mycred_rank { background-image: url(<?php echo apply_filters( 'mycred_icon', plugins_url( 'assets/images/cred-icon32.png', myCRED_THIS ) ); ?>); }
#myCRED-wrap #accordion h4 .gate-icon { display: block; width: 48px; height: 48px; margin: 0 0 0 0; padding: 0; float: left; line-height: 48px; }
#myCRED-wrap #accordion h4 .gate-icon { background-repeat: no-repeat; background-image: url(<?php echo plugins_url( 'assets/images/gateway-icons.png', myCRED_THIS ); ?>); background-position: 0 0; }
#myCRED-wrap #accordion h4 .gate-icon.inactive { background-position-x: 0; }
#myCRED-wrap #accordion h4 .gate-icon.active { background-position-x: -48px; }
#myCRED-wrap #accordion h4 .gate-icon.sandbox { background-position-x: -96px; }
h4:before { float:right; padding-right: 12px; font-size: 14px; font-weight: normal; color: silver; }
h4.ui-accordion-header.ui-state-active:before { content: "<?php _e( 'click to close', 'mycred' ); ?>"; }
h4.ui-accordion-header:before { content: "<?php _e( 'click to open', 'mycred' ); ?>"; }
</style>
<?php
		}

		/**
		 * Add to General Settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function after_general_settings() {
			// Since we are both registering our own settings and want to hook into
			// the core settings, we need to define our "defaults" here.
			if ( !isset( $this->core->buy_creds ) ) {
				$buy_creds = array(
					'minimum'   => 1,
					'exchange'  => 1,
					'log'       => '%plural% purchase',
					'login'     => __( 'Please login to purchase %_plural%', 'mycred' ),
					'thankyou'  => array(
						'use'      => 'page',
						'custom'   => '',
						'page'     => ''
					),
					'cancelled' => array(
						'use'      => 'custom',
						'custom'   => '',
						'page'     => ''
					),
					'gifting'   => array(
						'members'  => 1,
						'authors'  => 1,
						'log'      => __( 'Gift purchase from %display_name%.', 'mycred' )
					)
				);
			}
			else {
				$buy_creds = $this->core->buy_creds; 
			}

			$thankyou_use = $buy_creds['thankyou']['use'];
			$cancelled_use = $buy_creds['cancelled']['use']; ?>

				<h4><div class="icon icon-active"></div><?php _e( 'buyCRED', 'mycred' ); ?></h4>
				<div class="body" style="display:none;">
					<label class="subheader"><?php echo $this->core->template_tags_general( __( 'Minimum %plural%', 'mycred' ) ); ?></label>
					<ol id="mycred-buy-creds-minimum-amount">
						<li>
							<div class="h2"><input type="text" name="mycred_pref_core[buy_creds][minimum]" id="<?php echo $this->field_id( 'minimum' ); ?>" value="<?php echo $buy_creds['minimum']; ?>" size="5" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( 'Minimum amount of %plural% a user must purchase. Will default to 1.', 'mycred' ) ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'login' ); ?>"><?php _e( 'Login Template', 'mycred' ); ?></label>
					<ol id="mycred-buy-creds-default-log">
						<li>
							<textarea rows="10" cols="50" name="mycred_pref_core[buy_creds][login]" id="<?php echo $this->field_id( 'login' ); ?>" class="large-text code"><?php echo $buy_creds['login']; ?></textarea>
							<span class="description"><?php _e( 'Content to show when a user is not logged in.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol id="mycred-buy-creds-default-log">
						<li>
							<div class="h2"><input type="text" name="mycred_pref_core[buy_creds][log]" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $buy_creds['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General and %gateway% for the payment gateway used.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Thank You Page', 'mycred' ); ?></label>
					<ol id="mycred-buy-creds-thankyou-page">
						<li class="option">
							<input type="radio" name="mycred_pref_core[buy_creds][thankyou][use]" <?php checked( $thankyou_use, 'custom' ); ?> id="<?php echo $this->field_id( array( 'thankyou' => 'use' ) ); ?>-custom" value="custom" /> <label for="<?php echo $this->field_id( array( 'thankyou' => 'custom' ) ); ?>"><?php _e( 'Custom URL', 'mycred' ); ?></label><br />
							<div class="h2"><?php echo get_bloginfo( 'url' ) . '/'; ?>  <input type="text" name="mycred_pref_core[buy_creds][thankyou][custom]" id="<?php echo $this->field_id( array( 'thankyou' => 'custom' ) ); ?>" value="<?php echo $buy_creds['thankyou']['custom']; ?>" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li class="option">
							<input type="radio" name="mycred_pref_core[buy_creds][thankyou][use]" <?php checked( $thankyou_use, 'page' ); ?> id="<?php echo $this->field_id( array( 'thankyou' => 'use' ) ); ?>-page" value="page" /> <label for="mycred-buy-creds-thankyou-use-page"><?php _e( 'Page', 'mycred' ); ?></label><br />
<?php
			// Thank you page dropdown
			$thankyou_args = array(
				'name'             => 'mycred_pref_core[buy_creds][thankyou][page]',
				'id'               => $this->field_id( array( 'thankyou' => 'page' ) ) . '-id',
				'selected'         => $buy_creds['thankyou']['page'],
				'show_option_none' => __( 'Select', 'mycred' )
			);
			wp_dropdown_pages( $thankyou_args ); ?>

						</li>
					</ol>
					<label class="subheader"><?php _e( 'Cancellation Page', 'mycred' ); ?></label>
					<ol id="mycred-buy-creds-cancel-page">
						<li class="option">
							<input type="radio" name="mycred_pref_core[buy_creds][cancelled][use]" <?php checked( $cancelled_use, 'custom' ); ?> id="<?php echo $this->field_id( array( 'cancelled' => 'custom' ) ); ?>" value="custom" /> <label for="<?php echo $this->field_id( array( 'cancelled' => 'custom' ) ); ?>"><?php _e( 'Custom URL', 'mycred' ); ?></label><br />
							<div class="h2"><?php echo get_bloginfo( 'url' ) . '/'; ?> <input type="text" name="mycred_pref_core[buy_creds][cancelled][custom]" id="mycred-buy-creds-cancelled-custom-url" value="<?php echo $buy_creds['cancelled']['custom']; ?>" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li class="option">
							<input type="radio" name="mycred_pref_core[buy_creds][cancelled][use]" <?php checked( $cancelled_use, 'page' ); ?> id="<?php echo $this->field_id( array( 'cancelled' => 'use' ) ); ?>-page" value="page" /> <label for="<?php echo $this->field_id( array( 'cancelled' => 'use' ) ); ?>-page"><?php _e( 'Page', 'mycred' ); ?></label><br />
<?php
			// Cancelled page dropdown
			$cancelled_args = array(
				'name'             => 'mycred_pref_core[buy_creds][cancelled][page]',
				'id'               => $this->field_id( array( 'cancelled' => 'page' ) ) . '-id',
				'selected'         => $buy_creds['cancelled']['page'],
				'show_option_none' => __( 'Select', 'mycred' )
			);
			wp_dropdown_pages( $cancelled_args ); ?>

						</li>
					</ol>
					<label class="subheader"><?php _e( 'Gifting', 'mycred' ); ?></label>
					<ol id="mycred-buy-creds-gifting">
						<li><input type="checkbox" name="mycred_pref_core[buy_creds][gifting][members]" id="<?php echo $this->field_id( array( 'gifting' => 'members' ) ); ?>"<?php checked( $buy_creds['gifting']['members'], 1 ); ?> value="1" /><label for="<?php echo $this->field_id( array( 'gifting' => 'members' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Allow users to buy %_plural% for other users.', 'mycred' ) ); ?></label></li>
						<li><input type="checkbox" name="mycred_pref_core[buy_creds][gifting][authors]" id="<?php echo $this->field_id( array( 'gifting' => 'authors' ) ); ?>"<?php checked( $buy_creds['gifting']['authors'], 1 ); ?> value="1" /><label for="<?php echo $this->field_id( array( 'gifting' => 'authors' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Allow users to buy %_plural% for content authors.', 'mycred' ) ); ?></label></li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'gifting' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="mycred_pref_core[buy_creds][gifting][log]" id="<?php echo $this->field_id( array( 'gifting' => 'log' ) ); ?>" value="<?php echo $buy_creds['gifting']['log']; ?>" class="long" /></div>
							<div class="description"><?php _e( 'Available template tags: %singular%, %plural% and %display_name%' ); ?></div>
						</li>
					</ol>
				</div>
<?php
		}

		/**
		 * Save Settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			$settings = $data['buy_creds'];

			$new_data['buy_creds']['minimum'] = abs( $settings['minimum'] );
			$new_data['buy_creds']['log'] = sanitize_text_field( $settings['log'] );
			$new_data['buy_creds']['login'] = trim( $settings['login'] );

			$new_data['buy_creds']['thankyou']['use'] = sanitize_text_field( $settings['thankyou']['use'] );
			$new_data['buy_creds']['thankyou']['custom'] = sanitize_text_field( $settings['thankyou']['custom'] );
			$new_data['buy_creds']['thankyou']['page'] = abs( $settings['thankyou']['page'] );

			$new_data['buy_creds']['cancelled']['use'] = sanitize_text_field( $settings['cancelled']['use'] );
			$new_data['buy_creds']['cancelled']['custom'] = sanitize_text_field( $settings['cancelled']['custom'] );
			$new_data['buy_creds']['cancelled']['page'] = abs( $settings['cancelled']['page'] );

			$new_data['buy_creds']['gifting']['members'] = ( !isset( $settings['gifting']['members'] ) ) ? 0 : 1;
			$new_data['buy_creds']['gifting']['authors'] = ( !isset( $settings['gifting']['authors'] ) ) ? 0 : 1;
			$new_data['buy_creds']['gifting']['log'] = sanitize_text_field( $settings['gifting']['log'] );

			return $new_data;
		}

		/**
		 * Payment Gateways Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page() {
			$installed = $this->get();

			// Updated settings
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
				echo '<div class="updated settings-error"><p>' . __( 'Settings Updated', 'mycred' ) . '</p></div>';
			} ?>

	<div class="wrap list" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php echo apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'Payment Gateways', 'mycred' ); ?></h2>
		<p><?php echo $this->core->template_tags_general( __( 'Select the payment gateways you want to offer your users to buy %plural%.', 'mycred' ) ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'myCRED-gateways' ); ?>

			<?php do_action( 'mycred_before_buycreds_page', $this ); ?>

			<div class="list-items expandable-li" id="accordion">
<?php
			if ( !empty( $installed ) ) {
				foreach ( $installed as $key => $data ) { ?>

				<h4><div class="gate-icon <?php

					// Mark
					if ( $this->is_active( $key ) ) {
						if ( isset( $this->gateway_prefs[$key]['sandbox'] ) && $this->gateway_prefs[$key]['sandbox'] == 1 )
							echo 'sandbox" title="' . __( 'Test Mode', 'mycred' );
						else
							echo 'active" title="' . __( 'Enabled', 'mycred' );
					}
					else
						echo 'inactive" title="' . __( 'Disabled', 'mycred' ); ?>"></div><?php echo $this->core->template_tags_general( $data['title'] ); ?></h4>
				<div class="body" style="display:none;">
					<label class="subheader"><?php _e( 'Enable', 'mycred' ); ?></label>
					<ol id="">
						<li>
							<input type="checkbox" name="mycred_pref_buycreds[active][]" id="mycred-gateway-<?php echo $key; ?>" value="<?php echo $key; ?>"<?php if ( $this->is_active( $key ) ) echo ' checked="checked"'; ?> />
						</li>
					</ol>
<?php
					echo $this->call( 'preferences', $data['callback'] ); ?>

					<input type="hidden" name="mycred_pref_buycreds[installed]" value="<?php echo $key; ?>" />
				</div>
<?php
				}
			} ?>

			</div>
			<?php do_action( 'mycred_after_buycreds_page', $this ); ?>

			<?php submit_button( __( 'Update Gateway Settings', 'mycred' ), 'primary large', 'submit', false ); ?>

		</form>
		<?php do_action( 'mycred_bottom_buycreds_page', $this ); ?>

	</div>
<?php
			unset( $this );
		}

		/**
		 * Sanititze Settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function sanitize_settings( $data ) {
			
			$installed = $this->get();
			if ( empty( $installed ) ) return $data;
			
			foreach ( $installed as $id => $gdata ) {
				$data['gateway_prefs'][$id] = $this->call( 'sanitise_preferences', $installed[$id]['callback'], $data['gateway_prefs'][$id] );
			}

			unset( $installed );
			return $data;
		}

		/**
		 * Register Widgets
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_widgets_init() {
			//register_widget( 'myCRED_Buy_CREDs' );
		}

		/**
		 * Render Shortcode Basic
		 * This shortcode returns a link element to a specified payment gateway.
		 * @since 0.1
		 * @version 1.1.1
		 */
		public function render_shortcode_basic( $atts, $title = '' ) {
			// Make sure the add-on has been setup
			if ( !isset( $this->core->buy_creds ) ) {
				if ( mycred_is_admin() )
					return '<p style="color:red;"><a href="' . admin_url( 'admin.php?page=myCRED_page_settings' ) . '">' . __( 'This Add-on needs to setup before you can use this shortcode.', 'mycred' ) . '</a></p>';
				else
					return '';
			}

			extract( shortcode_atts( array(
				'gateway' => '',
				'amount'  => '',
				'gift_to' => false,
				'class'   => 'mycred-buy-link button large custom',
				'login'   => $this->core->template_tags_general( $this->core->buy_creds['login'] )
			), $atts ) );

			// If we are not logged in
			if ( !is_user_logged_in() ) return '<div class="mycred-buy login">' . $this->core->template_tags_general( $login ) . '</div>';

			// Gateways
			$installed = $this->get();
			if ( empty( $installed ) ) return __( 'No gateways installed.', 'mycred' );
			if ( !empty( $gateway ) && !array_key_exists( $gateway, $installed ) ) return __( 'Gateway does not exist.', 'mycred' );
			if ( empty( $gateway ) || !array_key_exists( $gateway, $installed ) ) {
				reset( $installed );
				$gateway = key( $installed );
			}

			$buy_author = false;
			$buy_member = false;
			$buy_self = false;

			// Gift to author (if allowed)
			if ( $this->core->buy_creds['gifting']['authors'] == 1 && $gift_to == 'author' ) {
				$user_id = $GLOBALS['post']->post_author;
				$buy_author = true;
			}

			// Gift to member (if allowed)
			elseif ( $this->core->buy_creds['gifting']['members'] == 1 && $gift_to !== false ) {
				$user_id = abs( $gift_to );
				$buy_member = true;
			}

			// Current user
			else {
				$user_id = get_current_user_id();
				$buy_self = true;
			}

			// Adjust title
			if ( $buy_self === false ) {
				$user = get_userdata( (int) $user_id );
				$username = $user->user_login;
				$title = $this->core->template_tags_user( $title, $user );
				unset( $user );
			}
			else {
				$title = str_replace( '%display_name%', __( 'Yourself', 'mycred' ), $title );
			}

			// Amount
			$amount = $this->prep_shortcode_amount( $amount );

			// Title
			$title = $this->prep_shortcode_title( $title );

			// URL
			$url = get_bloginfo( 'url' ) . '/';
			$args = array(
				'mycred_buy' => $gateway,
				'amount'     => $this->core->number( $amount ),
				'token'      => wp_create_nonce( 'mycred-buy-creds' )
			);

			// Classes
			$classes = explode( ' ', $class );
			if ( empty( $classes ) )
				$classes = array( 'mycred-buy-link', 'button large', 'custom' );
			$classes[] = $gateway;

			if ( $buy_author || $buy_member )
				$args = array_merge_recursive( $args, array( 'gift_to' => $user_id ) );

			// Element to return
			$element = '<a href="' . add_query_arg( $args, $url ) . '" class="' . implode( ' ', $classes ) . '" title="' . $title . '">' . $title . '</a>';
			unset( $this );
			return $element;
		}

		/**
		 * Render Shortcode Form
		 * Returns an advanced version allowing for further customizations.
		 * @since 0.1
		 * @version 1.2
		 */
		public function render_shortcode_form( $atts, $content = '' ) {
			// Make sure the add-on has been setup
			if ( !isset( $this->core->buy_creds ) ) {
				if ( mycred_is_admin() )
					return '<p style="color:red;"><a href="' . admin_url( 'admin.php?page=myCRED_page_settings' ) . '">' . __( 'This Add-on needs to setup before you can use this shortcode.', 'mycred' ) . '</a></p>';
				else
					return '';
			}

			extract( shortcode_atts( array(
				'gateway' => '',
				'amount'  => '',
				'gift_to' => false,
				'login'   => $this->core->template_tags_general( $this->core->buy_creds['login'] ),
			), $atts ) );

			// If we are not logged in
			if ( !is_user_logged_in() ) return '<p class="mycred-buy login">' . $login . '</p>';

			// Catch errors
			$installed = $this->get();
			if ( empty( $installed ) ) return __( 'No gateways installed.', 'mycred' );
			if ( !empty( $gateway ) && !array_key_exists( $gateway, $installed ) ) return __( 'Gateway does not exist.', 'mycred' );
			if ( empty( $this->active ) ) return __( 'No active gateways found.', 'mycred' );
			if ( !empty( $gateway ) && !$this->is_active( $gateway ) ) return __( 'The selected gateway is not active.', 'mycred' );

			// Prep
			$buy_author = false;
			$buy_member = false;
			$buy_others = false;
			$buy_self = false;
			$classes = array( 'myCRED-buy-form' );

			// Gift to author (if allowed)
			if ( $this->core->buy_creds['gifting']['authors'] == 1 && $gift_to == 'author' ) {
				$post_id = $GLOBALS['post']->ID;
				$user_id = $GLOBALS['post']->post_author;
				$buy_author = true;
			}

			// Gift to specific member (if allowed)
			elseif ( $this->core->buy_creds['gifting']['members'] == 1 && is_integer( $gift_to ) ) {
				$user_id = abs( $gift_to );
				$buy_member = true;
			}

			// Gift to other members (no member selected, user will select one for us)
			elseif ( $this->core->buy_creds['gifting']['members'] == 1 && $gift_to !== false ) {
				$user_id = get_current_user_id();
				$buy_others = true;
			}

			// Current user
			else {
				$user_id = get_current_user_id();
				$buy_self = true;
			}

			// Button
			if ( !empty( $gateway ) ) {
				$gateway_title = $installed[$gateway]['title'];

				$button = explode( ' ', $gateway_title );
				$button = $button[0];
				$button = __( 'Buy with', 'mycred' ) . ' ' . $button;
				$classes[] = $gateway;
			}
			else {
				$button = __( 'Buy Now', 'mycred' );
			}

			// Start constructing form with title and submit button
			$form = '
<form method="post" action="" class="' . implode( ' ', $classes ) . '">';

			// Gifting a specific user or post author
			if ( $buy_author ) {
				$form .= '
	<input type="hidden" name="post_id" value="' . $post_id . '" />';
			}

			// Gift to a specific member
			elseif ( $buy_member ) {
				$form .= '
	<input type="hidden" name="gift_to" value="' . $user_id . '" />';
			}

			// Gifting is allowed so we can select someone
			elseif ( $buy_others ) {
				// Select gift recipient from a drop-down
				if ( $gift_to == 'select' ) {
					$select = '<select name="gift_to">';
					$blog_users = get_users();
					if ( !empty( $blog_users ) ) {
						foreach ( $blog_users as $blog_user ) {
							if ( $this->core->exclude_user( $blog_user->ID ) || $blog_user->ID === get_current_user_id() ) continue;
							$select .= '<option value="' . $blog_user->ID . '">' . $blog_user->display_name . '</option>';
						}
						unset( $blog_users );
					}
					else {
						$select .= '<option value="">' . __( 'No users found', 'mycred' ) . '</option>';
					}
					$select .= '</select>';
				}
				// Nominate user
				else {
					$select = '<input type="text" name="gift_to" value="" class="pick-user" size="20" />';
				}
				$form .= '
	<div class="select-to">
		<label>' . __( 'To', 'mycred' ) . ':</label>
		' . $select . '
	</div>';
			}

			// Amount
			$no_of_amounts = 0;
			$minimum = $this->core->number( $this->core->buy_creds['minimum'] );
			if ( !empty( $amount ) )
				$no_of_amounts = sizeof( array_filter( explode( ',', $amount ), create_function( '$a', 'return !empty($a);' ) ) );

			// Multiple amounts set
			if ( $no_of_amounts > 1 ) {
				// Let user select from this list of amounts
				$amount = explode( ',', $amount );
				$form .= '
	<div class="select-amount">
		<label>' . __( 'Select Amount', 'mycred' ) . ':</label>
		<select name="amount">';

				foreach ( $amount as $number ) {
					$form .= '<option value="' . $number . '">' . $number . '</option>';
				}

				$form .= '
		</select>
	</div>';
			}

			// One amount set
			elseif ( (int) $no_of_amounts == 1 ) {
				$form .= '
	<input type="hidden" name="amount" value="' . $this->core->number( $amount ) . '" />';
			}

			// No amount set let user pick
			else {
				$form .= '
	<div class="select-amount">
		<label>' . __( 'Amount', 'mycred' ) . ':</label>
		<input type="text" name="amount" value="' . $minimum . '" size="5" /><br />
		<em>' . __( 'min.', 'mycred' ) . ' ' . $minimum . '</em>
	</div>';
			}

			// Gateways
			if ( empty( $gateway ) ) {
				$form .= '
	<div class="select-gateway">
		<label>' . __( 'Select Gateway', 'mycred' ) . ':</label>
		<select name="mycred_buy">';

				foreach ( $installed as $gateway_id => $data ) {
					if ( !$this->is_active( $gateway_id ) ) continue;
					//if ( !$this->is_active( $gateway_id ) ) continue;
					$form .= '<option value="' . $gateway_id . '">' . $data['title'] . '</option>';
				}

				$form .= '
		</select>
	</div>';
			}
			else {
				$form .= '
	<input type="hidden" name="mycred_buy" value="' . $gateway . '" />';
			}

			$form .= '
	<input type="hidden" name="token" value="' . wp_create_nonce( 'mycred-buy-creds' ) . '" />
	<input type="submit" name="submit" value="' . $button . '" class="mycred-buy button large" />
</form>';

			return $form;
		}

		/**
		 * Prep Shortcode Title
		 * @since 0.1
		 * @version 1.0
		 */
		public function prep_shortcode_title( $string = '' ) {
			$string = $this->core->allowed_tags( $string, false );
			$string = trim( $string );
			$title = $this->core->template_tags_general( $string );
			return $title;
		}

		/**
		 * Prep Shortcode Amount
		 * @since 0.1
		 * @version 1.0
		 */
		public function prep_shortcode_amount( $amount = '' ) {
			$amount = $this->core->number( $amount );
			$minimum = $this->core->number( $this->core->buy_creds['minimum'] );
			if ( empty( $amount ) || $amount < $minimum )
				return $minimum;
			else
				return $amount;
		}

		/**
		 * Prep Shortcode Gifting
		 * @since 0.1
		 * @version 1.0
		 */
		public function prep_shortcode_gifting( $gift_to = '' ) {
			if ( empty( $gift_to ) ) return $gift_to;

			if ( $this->core->buy_creds['gifting']['authors'] == 1 && $gift_to == 'author' && in_the_loop() ) {
				return $GLOBALS['post']->post_author;
			}

			if ( $this->core->buy_creds['gifting']['members'] == 1 ) {
				return abs( $gift_to );
			}

			return false;
		}

		/**
		 * Contextual Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function help( $screen_id, $screen ) {
			if ( $screen_id == 'mycred_page_myCRED_page_settings' ) {
				$screen->add_help_tab( array(
					'id'		=> 'mycred-buy-creds',
					'title'		=> $this->core->template_tags_general( __( 'Buy %plural%', 'mycred' ) ),
					'content'	=> '
<p>' . $this->core->template_tags_general( __( 'This add-on lets your users buy %_plural% using a payment gateway.', 'mycred' ) ) . '</p>
<p><strong>' . __( 'Supported Gateways', 'mycred' ) . '</strong></p>
<p>' . __( 'myCRED supports purchases through: PayPal Payments Standard, Skrill (Moneybookers) and NETbilling. Let us know if you want to add other payment gateways.', 'mycred' ) . '</p>
<p><strong>' . __( 'Usage', 'mycred' ) . '</strong></p>
<p>' . __( 'Purchases can be made using one of the following shortcodes:', 'mycred' ) . '</p>
<ul>
<li><code>mycred_buy</code> ' . __( 'When you want to sell a pre-set amount, sell to a specific user or use a specific gateway.<br />For more information on how to use the shortcode, please visit the', 'mycred' ) . ' <a href="http://mycred.me/shortcodes/mycred_buy/" target="_blank">myCRED Codex</a>.</li>
<li><code>mycred_buy_form</code> ' . __( 'When you want to give your users the option to select an amount, gateway or recipient.<br />For more information on how to use the shortcode, please visit the', 'mycred' ) . ' <a href="http://mycred.me/shortcodes/mycred_buy_form/" target="_blank">myCRED Codex</a>.</li>
</ul>'
				) );
			}
			elseif ( $screen_id == 'mycred_page_myCRED_page_gateways' ) {
				$screen->add_help_tab( array(
					'id'		=> 'mycred-paypal',
					'title'		=> __( 'PayPal Payments Standard', 'mycred' ),
					'content'	=> '
<p><strong>' . __( 'Currency', 'mycred' ) . '</strong></p>
<p>' . __( 'Make sure you select a currency that your PayPal account supports. Otherwise transactions will not be approved until you login to your PayPal account and Accept each transaction! Purchases made in a currency that is not supported will not be applied to the buyer until you have resolved the issue.', 'mycred' ) . '</p>
<p><strong>' . __( 'Instant Payment Notifications', 'mycred' ) . '</strong></p>
<p>' . __( 'For this gateway to work, you must login to your PayPal account and under "Profile" > "Selling Tools" enable "Instant Payment Notifications". Make sure the "Notification URL" is set to the above address and that you have selected "Receive IPN messages (Enabled)".', 'mycred' ) . '</p>'
				) );
				$screen->add_help_tab( array(
					'id'		=> 'mycred-skrill',
					'title'		=> __( 'Skrill', 'mycred' ),
					'content'	=> '
<p><strong>' . __( 'Sandbox Mode', 'mycred' ) . '</strong></p>
<p>' . __( 'Transactions made while Sandbox mode is active are real transactions! Remember to use your "Test Merchant Account" when Sandbox mode is active!', 'mycred' ) . '</p>
<p><strong>' . __( 'Checkout Page', 'mycred' ) . '</strong></p>
<p>' . __( 'By default all Skrill Merchant account accept payments via Bank Transfers. When a user selects this option, no points are awarded! You will need to manually award these once the bank transfer is completed.', 'mycred' ) . '</p>
<p>' . __( 'By default purchases made using Skrill will result in users having to signup for a Skrill account (if they do not have one already). You can contact Skrill Merchant Services and request to disable this feature.', 'mycred' ) . '</p>'
				) );
			}
		}
	}
	$buy_creds = new myCRED_Buy_CREDs();
	$buy_creds->load();
}
?>