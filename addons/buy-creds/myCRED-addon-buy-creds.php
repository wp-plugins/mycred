<?php
/**
 * Addon: buyCRED
 * Addon URI: http://mycred.me/add-ons/buycred/
 * Version: 1.3
 * Description: The <strong>buy</strong>CRED Add-on allows your users to buy points using PayPal, Skrill (Moneybookers), Zombaio or NETbilling. <strong>buy</strong>CRED can also let your users buy points for other members.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

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
require_once( myCRED_PURCHASE_DIR . 'gateways/bitpay.php' );
require_once( myCRED_PURCHASE_DIR . 'gateways/netbilling.php' );
require_once( myCRED_PURCHASE_DIR . 'gateways/skrill.php' );
require_once( myCRED_PURCHASE_DIR . 'gateways/zombaio.php' );

do_action( 'mycred_buycred_load_gateways' );

/**
 * myCRED_buyCRED_Module class
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_buyCRED_Module' ) ) {
	class myCRED_buyCRED_Module extends myCRED_Module {

		public $purchase_log = '';

		/**
		 * Construct
		 */
		function __construct( $type = 'mycred_default' ) {
			parent::__construct( 'myCRED_BuyCRED_Module', array(
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
			), $type );

			// Adjust Module to the selected point type
			$this->mycred_type = 'mycred_default';
			if ( isset( $this->core->buy_creds['type'] ) )
				$this->mycred_type = $this->core->buy_creds['type'];

			add_filter( 'mycred_parse_log_entry',  array( $this, 'render_gift_tags' ), 10, 2 );
		}

		/**
		 * Load
		 * @version 1.0
		 */
		public function load() {
			add_action( 'mycred_init',             array( $this, 'module_init' ) );

			add_filter( 'set-screen-option',       array( $this, 'set_payments_per_page' ), 11, 3 );
			add_action( 'mycred_admin_init',       array( $this, 'register_settings' ) );
			add_action( 'mycred_add_menu',         array( $this, 'add_menu' ), $this->menu_pos );
			add_action( 'mycred_add_menu',         array( $this, 'add_to_menu' ), $this->menu_pos+1 );
			add_action( 'mycred_after_core_prefs', array( $this, 'after_general_settings' ) );
			add_filter( 'mycred_save_core_prefs',  array( $this, 'sanitize_extra_settings' ), 90, 3 );
		}

		/**
		 * Render Gift Tags
		 * @since 1.4.1
		 * @version 1.0
		 */
		public function render_gift_tags( $content, $log ) {
			if ( substr( $log->ref, 0, 15 ) != 'buy_creds_with_' ) return $content;
			return $this->core->template_tags_user( $content, absint( $log->ref_id ) );
		}

		/**
		 * Process
		 * Processes Gateway returns and IPN calls
		 * @since 0.1
		 * @version 1.1
		 */
		public function module_init() {
			// Add shortcodes first
			add_shortcode( 'mycred_buy',      array( $this, 'render_shortcode_basic' ) );
			add_shortcode( 'mycred_buy_form', array( $this, 'render_shortcode_form' ) );

			$gateway = NULL;

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
				if ( ! $this->is_active( $id ) ) continue;
				$this->call( 'returning', $installed[ $id ]['callback'] );
			}

			/**
			 * Step 2 - Check for gateway calls
			 * Checks to see if a gateway should be loaded.
			 */
			$gateway_id = '';
			if ( isset( $_REQUEST['mycred_call'] ) )
				$gateway_id = trim( $_REQUEST['mycred_call'] );
			elseif ( isset( $_REQUEST['mycred_buy'] ) && is_user_logged_in() )
				$gateway_id = trim( $_REQUEST['mycred_buy'] );
			elseif ( isset( $_REQUEST['wp_zombaio_ips'] ) || isset( $_REQUEST['ZombaioGWPass'] ) )
				$gateway_id = 'zombaio';

			$gateway_id = apply_filters( 'mycred_gateway_id', $gateway_id );

			// If we have a valid gateway ID and the gateway is active, lets run that gateway.
			if ( ! empty( $gateway_id ) && array_key_exists( $gateway_id, $installed ) && $this->is_active( $gateway_id ) ) {
				// Gateway Class
				$class = $installed[ $gateway_id ]['callback'][0];

				// Construct Gateway
				$gateway = new $class( $this->gateway_prefs );

				// Check payment processing
				if ( isset( $_REQUEST['mycred_call'] ) || $gateway_id == 'zombaio' ) {
					$gateway->process();
				
					do_action( 'mycred_buycred_process', $gateway_id, $this->gateway_prefs, $this->core->buy_creds );
					do_action( 'mycred_buycred_process_' . $gateway_id, $this->gateway_prefs, $this->core->buy_creds );
				}

				// Check purchase request
				if ( isset( $_REQUEST['mycred_buy'] ) ) {
					// Validate token
					$token = false;
					if ( isset( $_REQUEST['token'] ) && wp_verify_nonce( $_REQUEST['token'], 'mycred-buy-creds' ) )
						$token = true;

					// Validate amount
					$amount = false;
					if ( isset( $_REQUEST['amount'] ) && $_REQUEST['amount'] != 0 && $_REQUEST['amount'] >= $this->core->buy_creds['minimum'] )
						$amount = true;

					if ( $token && $amount ) {
						$gateway->buy();
					
						do_action( 'mycred_buycred_buy', $gateway_id, $this->gateway_prefs, $this->core->buy_creds );
						do_action( 'mycred_buycred_buy_' . $gateway_id, $this->gateway_prefs, $this->core->buy_creds );
					}
				}
			}
		}

		/**
		 * Add Admin Menu Item
		 * @since 0.1
		 * @version 1.1
		 */
		function add_to_menu() {
			if ( isset( $this->core->buy_creds['custom_log'] ) && $this->core->buy_creds['custom_log'] ) {
				// Menu Slug
				$menu_slug = 'myCRED';
				if ( isset( $this->core->buy_creds['type'] ) && $this->core->buy_creds['type'] != 'mycred_default' )
					$menu_slug .= '_' . $this->core->buy_creds['type'];

				$page = add_submenu_page(
					$menu_slug,
					__( 'buyCRED Purchase Log', 'mycred' ),
					__( 'Purchase Log', 'mycred' ),
					$this->core->edit_plugin_cap(),
					'myCRED_page_gateways_log',
					array( $this, 'purchase_log_page' )
				);
				add_action( 'admin_print_styles-' . $page, array( $this, 'settings_page_enqueue' ) );
				add_action( 'load-' . $page,               array( $this, 'screen_options' ) );
				$this->purchase_log = $page;
			}
		}

		/**
		 * Get Payment Gateways
		 * Retreivs all available payment gateways that can be used to buy CREDs.
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function get() {
			$installed = array();

			// PayPal Standard
			$installed['paypal-standard'] = array(
				'title'    => 'PayPal Payments Standard',
				'callback' => array( 'myCRED_PayPal_Standard' )
			);

			// BitPay
			$installed['bitpay'] = array(
				'title'    => 'BitPay (Bitcoins)',
				'callback' => array( 'myCRED_Bitpay' )
			);

			// NetBilling
			$installed['netbilling'] = array(
				'title'    => 'NETbilling',
				'callback' => array( 'myCRED_NETbilling' )
			);

			// Skrill
			$installed['skrill'] = array(
				'title'    => 'Skrill (Moneybookers)',
				'callback' => array( 'myCRED_Skrill' )
			);

			// Zombaio
			$installed['zombaio'] = array(
				'title'    => 'Zombaio',
				'callback' => array( 'myCRED_Zombaio' )
			);

			$installed = apply_filters( 'mycred_setup_gateways', $installed );

			$this->installed = $installed;
			return $installed;
		}

		/**
		 * Page Header
		 * @since 1.3
		 * @version 1.1
		 */
		public function settings_header() {
			$gateway_icons = plugins_url( 'assets/images/gateway-icons.png', myCRED_THIS ); ?>

<!-- buyCRED Module -->
<style type="text/css">
#myCRED-wrap #accordion h4 .gate-icon { display: block; width: 48px; height: 48px; margin: 0 0 0 0; padding: 0; float: left; line-height: 48px; }
#myCRED-wrap #accordion h4 .gate-icon { background-repeat: no-repeat; background-image: url("<?php echo $gateway_icons; ?>"); background-position: 0 0; }
#myCRED-wrap #accordion h4 .gate-icon.inactive { background-position-x: 0; }
#myCRED-wrap #accordion h4 .gate-icon.active { background-position-x: -48px; }
#myCRED-wrap #accordion h4 .gate-icon.sandbox { background-position-x: -96px; }
#myCRED-wrap #accordion h4 .gate-icon.monitor { background-position-x: 0; background-position-y: -48px; }
</style>
<?php
		}

		/**
		 * Screen Options
		 * @since 1.4
		 * @version 1.0
		 */
		public function screen_options() {
			if ( empty( $this->purchase_log ) ) return;

			$current_screen = get_current_screen();
			if ( $this->purchase_log != $current_screen->id ) return;
			
			$args = array(
				'label'   => __( 'Payments', 'mycred' ),
				'default' => 10,
				'option'  => 'mycred_payments_per_page'
			);
			add_screen_option( 'per_page', $args );
		}

		/**
		 * Save Payments per page
		 * @since 1.4
		 * @version 1.0
		 */
		public function set_payments_per_page( $status, $option, $value ) {
			if ( 'mycred_payments_per_page' == $option ) return $value;
			return $status;
		}

		/**
		 * Add to General Settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function after_general_settings() {
			// Since we are both registering our own settings and want to hook into
			// the core settings, we need to define our "defaults" here.
			$defaults = array(
				'minimum'    => 1,
				'type'       => 'mycred_default',
				'exchange'   => 1,
				'log'        => '%plural% purchase',
				'login'      => __( 'Please login to purchase %_plural%', 'mycred' ),
				'custom_log' => 0,
				'thankyou'   => array(
					'use'        => 'page',
					'custom'     => '',
					'page'       => ''
				),
				'cancelled'  => array(
					'use'        => 'custom',
					'custom'     => '',
					'page'       => ''
				),
				'gifting'    => array(
					'members'    => 1,
					'authors'    => 1,
					'log'        => __( 'Gift purchase from %display_name%.', 'mycred' )
				)
			);

			if ( isset( $this->core->buy_creds ) )
				$buy_creds = $this->core->buy_creds;
			else
				$buy_creds = array();
			
			$buy_creds = mycred_apply_defaults( $defaults, $buy_creds );

			$thankyou_use = $buy_creds['thankyou']['use'];
			$cancelled_use = $buy_creds['cancelled']['use'];
			
			$mycred_types = mycred_get_types(); ?>

<h4><div class="icon icon-active"></div><strong>buy</strong>CRED</h4>
<div class="body" style="display:none;">
	<label class="subheader"><?php echo $this->core->template_tags_general( __( 'Minimum %plural%', 'mycred' ) ); ?></label>
	<ol id="mycred-buy-creds-minimum-amount">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[buy_creds][minimum]" id="<?php echo $this->field_id( 'minimum' ); ?>" value="<?php echo $buy_creds['minimum']; ?>" size="5" /></div>
			<span class="description"><?php echo $this->core->template_tags_general( __( 'Minimum amount of %plural% a user must purchase. Will default to 1.', 'mycred' ) ); ?></span>
		</li>
	</ol>
	<?php if ( count( $mycred_types ) > 1 ) : ?>

	<label class="subheader"><?php _e( 'Point Type', 'mycred' ); ?></label>
	<ol id="mycred-buy-creds-type">
		<li>
			<?php mycred_types_select_from_dropdown( 'mycred_pref_core[buy_creds][type]', $this->field_id( 'type' ), $buy_creds['type'] ); ?>

		</li>
	</ol>
	<?php else : ?>

	<input type="hidden" name="mycred_pref_core[buy_creds][type]" value="mycred_default" />
	<?php endif; ?>

	<label class="subheader" for="<?php echo $this->field_id( 'login' ); ?>"><?php _e( 'Login Template', 'mycred' ); ?></label>
	<ol id="mycred-buy-creds-default-log">
		<li>
			<input type="text" name="mycred_pref_core[buy_creds][login]" id="<?php echo $this->field_id( 'login' ); ?>" class="large-text code" value="<?php echo esc_attr( $buy_creds['login'] ); ?>" />
			<span class="description"><?php _e( 'Content to show when a user is not logged in.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
	<ol id="mycred-buy-creds-default-log">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[buy_creds][log]" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $buy_creds['log']; ?>" class="long" /></div>
			<span class="description"><?php echo $this->core->available_template_tags( array( 'general' ), '%gateway%' ); ?></span>
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
	<label class="subheader"><?php _e( 'Purchase Log', 'mycred' ); ?></label>
	<ol id="mycred-buy-creds-seperate-log">
		<li><input type="checkbox" name="mycred_pref_core[buy_creds][custom_log]" id="<?php echo $this->field_id( 'custom_log' ); ?>"<?php checked( $buy_creds['custom_log'], 1 ); ?> value="1" /><label for="<?php echo $this->field_id( 'custom_log' ); ?>"><?php echo $this->core->template_tags_general( __( 'Show seperate log for %_plural% purchases.', 'mycred' ) ); ?></label></li>
	</ol>
	<label class="subheader"><?php _e( 'Gifting', 'mycred' ); ?></label>
	<ol id="mycred-buy-creds-gifting">
		<li><input type="checkbox" name="mycred_pref_core[buy_creds][gifting][members]" id="<?php echo $this->field_id( array( 'gifting' => 'members' ) ); ?>"<?php checked( $buy_creds['gifting']['members'], 1 ); ?> value="1" /><label for="<?php echo $this->field_id( array( 'gifting' => 'members' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Allow users to buy %_plural% for other users.', 'mycred' ) ); ?></label></li>
		<li><input type="checkbox" name="mycred_pref_core[buy_creds][gifting][authors]" id="<?php echo $this->field_id( array( 'gifting' => 'authors' ) ); ?>"<?php checked( $buy_creds['gifting']['authors'], 1 ); ?> value="1" /><label for="<?php echo $this->field_id( array( 'gifting' => 'authors' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Allow users to buy %_plural% for content authors.', 'mycred' ) ); ?></label></li>
		<li class="empty">&nbsp;</li>
		<li>
			<label for="<?php echo $this->field_id( array( 'gifting' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="mycred_pref_core[buy_creds][gifting][log]" id="<?php echo $this->field_id( array( 'gifting' => 'log' ) ); ?>" value="<?php echo $buy_creds['gifting']['log']; ?>" class="long" /></div>
			<div class="description"><?php echo $this->core->available_template_tags( array( 'general', 'user' ) ); ?></div>
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

			$new_data['buy_creds']['minimum'] = abs( $data['buy_creds']['minimum'] );
			$new_data['buy_creds']['type'] = sanitize_text_field( $data['buy_creds']['type'] );
			$new_data['buy_creds']['log'] = sanitize_text_field( $data['buy_creds']['log'] );
			$new_data['buy_creds']['login'] = trim( $data['buy_creds']['login'] );

			$new_data['buy_creds']['thankyou']['use'] = sanitize_text_field( $data['buy_creds']['thankyou']['use'] );
			$new_data['buy_creds']['thankyou']['custom'] = sanitize_text_field( $data['buy_creds']['thankyou']['custom'] );
			$new_data['buy_creds']['thankyou']['page'] = abs( $data['buy_creds']['thankyou']['page'] );

			$new_data['buy_creds']['cancelled']['use'] = sanitize_text_field( $data['buy_creds']['cancelled']['use'] );
			$new_data['buy_creds']['cancelled']['custom'] = sanitize_text_field( $data['buy_creds']['cancelled']['custom'] );
			$new_data['buy_creds']['cancelled']['page'] = abs( $data['buy_creds']['cancelled']['page'] );

			$new_data['buy_creds']['custom_log'] = ( ! isset( $data['buy_creds']['custom_log'] ) ) ? 0 : 1;

			$new_data['buy_creds']['gifting']['members'] = ( ! isset( $data['buy_creds']['gifting']['members'] ) ) ? 0 : 1;
			$new_data['buy_creds']['gifting']['authors'] = ( ! isset( $data['buy_creds']['gifting']['authors'] ) ) ? 0 : 1;
			$new_data['buy_creds']['gifting']['log'] = sanitize_text_field( $data['buy_creds']['gifting']['log'] );

			return $new_data;
		}

		/**
		 * Payment Gateways Page
		 * @since 0.1
		 * @version 1.2
		 */
		public function admin_page() {
			// Security
			if ( ! $this->core->can_edit_creds() )
				wp_die( __( 'Access Denied', 'mycred' ) );

			$installed = $this->get();

			$last_call = get_option( 'mycred_buycred_last_call', array() );
			
			$last_call_entries = array();
			if ( isset( $last_call['entries'] ) )
				$last_call_entries = maybe_unserialize( $last_call['entries'] ); ?>

<div class="wrap list" id="myCRED-wrap">
	<h2><?php echo sprintf( __( '%s Payment Gateways', 'mycred' ), '<strong>buy</strong>CRED' ); ?> <?php if ( isset( $this->core->buy_creds['custom_log'] ) && $this->core->buy_creds['custom_log'] == 1 ) : ?><a href="<?php echo admin_url( 'admin.php?page=myCRED_page_gateways_log' ); ?>" class="add-new-h2"><?php _e( 'Purchase Log', 'mycred' ); ?></a><?php endif; ?><a href="<?php echo $this->get_settings_url( 'buycred_module' ); ?>" class="add-new-h2"><?php _e( 'buyCRED Settings', 'mycred' ); ?></a></h2>
<?php
			// Updated settings
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true )
				echo '<div class="updated settings-error"><p>' . __( 'Settings Updated', 'mycred' ) . '</p></div>'; ?>

	<p><?php echo $this->core->template_tags_general( __( 'Select the payment gateways you want to offer your users to buy %plural%.', 'mycred' ) ); ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( $this->settings_name ); ?>

		<?php do_action( 'mycred_before_buycreds_page', $this ); ?>

		<div class="list-items expandable-li" id="accordion">
			<h4><div class="gate-icon monitor" title="Last IPN Call"></div><?php _e( 'Last Payment Notification', 'mycred' ); ?></h4>
			<div class="body" style="display:none;">
				<p><?php _e( 'Here you can view the last payment confirmation that was sent to buyCRED for processing.', 'mycred' ); ?></p>
				<?php if ( isset( $last_call['gateway'] ) ) : ?>

				<label class="subheader"><?php _e( 'Details', 'mycred' ); ?></label>
				<ol class="inline">
					<li>
						<label><?php _e( 'Time', 'mycred' ); ?></label>
						<p><code><?php echo date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $last_call['date'] ); ?></code></p>
					</li>
					<li>
						<label><?php _e( 'Gateway', 'mycred' ); ?></label>
						<p><code><?php echo $last_call['gateway']; ?></code></p>
					</li>
					<li>
						<label><?php _e( 'Transaction ID', 'mycred' ); ?></label>
						<p><code><?php echo $last_call['id']; ?></code></p>
					</li>
					<li>
						<label><?php _e( 'Outcome', 'mycred' ); ?></label>
						<p><code><?php echo $last_call['outcome']; ?></code></p>
					</li>
				</ol>
				<label class="subheader"><?php _e( 'Gateway Log', 'mycred' ); ?></label>
				<ol>
					<li>
						<ul><?php foreach ( $last_call_entries as $entry ) echo '<li>&bull; ' . $entry . '</li>'; ?></ul>
					</li>
				</ol>
				<?php else : ?>

				<p><code><?php _e( 'No recorded calls found.', 'mycred' ); ?></code></p>
				<?php endif; ?>

			</div>
<?php
			if ( ! empty( $installed ) ) {
				foreach ( $installed as $key => $data ) { ?>

			<h4><div class="gate-icon <?php

					// Mark
					if ( $this->is_active( $key ) ) {
						if ( isset( $this->gateway_prefs[ $key ]['sandbox'] ) && $this->gateway_prefs[ $key ]['sandbox'] == 1 )
							echo 'sandbox" title="' . __( 'Test Mode', 'mycred' );
						else
							echo 'active" title="' . __( 'Enabled', 'mycred' );
					}
					else
						echo 'inactive" title="' . __( 'Disabled', 'mycred' ); ?>"></div><?php echo $this->core->template_tags_general( $data['title'] ); ?></h4>
			<div class="body" style="display:none;">
				<label class="subheader"><?php _e( 'Enable', 'mycred' ); ?></label>
				<ol>
					<li>
						<input type="checkbox" name="mycred_pref_buycreds[active][]" id="mycred-gateway-<?php echo $key; ?>" value="<?php echo $key; ?>"<?php if ( $this->is_active( $key ) ) echo ' checked="checked"'; ?> />
					</li>
				</ol>
<?php				if ( isset( $this->gateway_prefs[ $key ]['sandbox'] ) && $this->gateway_prefs[ $key ]['sandbox'] !== NULL ) : ?>

				<label class="subheader" for="mycred-gateway-<?php echo $key; ?>-sandbox"><?php _e( 'Sandbox Mode', 'mycred' ); ?></label>
				<ol>
					<li>
						<input type="checkbox" name="mycred_pref_buycreds[gateway_prefs][<?php echo $key; ?>][sandbox]" id="mycred-gateway-<?php echo $key; ?>-sandbox" value="1"<?php checked( $this->gateway_prefs[ $key ]['sandbox'], 1 ); ?> /> <span class="description"><?php _e( 'Enable for test purchases.', 'mycred' ); ?></span>
					</li>
				</ol>
<?php
					endif;

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

<script type="text/javascript">
jQuery(function($) {
	$( 'select.currency' ).change(function(){
		var target = $(this).attr( 'data-update' );
		$( '#' + target ).empty();
		$( '#' + target ).text( $(this).val() );
	});
});
</script>
</div>
<?php
		}

		/**
		 * Custom Log Page
		 * @since 1.4
		 * @version 1.1
		 */
		public function purchase_log_page() {
			// Security
			if ( ! $this->core->can_edit_creds() )
				wp_die( __( 'Access Denied', 'mycred' ) );

			$per_page = get_user_meta( get_current_user_id(), 'mycred_payments_per_page', true );
			if ( empty( $per_page ) || $per_page < 1 ) $per_page = 10;

			// Get references
			$references = apply_filters( 'mycred_buycred_log_refs', array(
				'buy_creds_with_paypal_standard',
				'buy_creds_with_skrill',
				'buy_creds_with_zombaio',
				'buy_creds_with_netbilling',
				'buy_creds_with_bitpay'
			), $this );

			// Prep
			$args = array(
				'number' => $per_page,
				'ctype'  => $this->mycred_type,
				'ref'    => implode( ',', $references )
			);

			if ( isset( $_GET['user_id'] ) && ! empty( $_GET['user_id'] ) )
				$args['user_id'] = $_GET['user_id'];

			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) )
				$args['s'] = $_GET['s'];

			if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) )
				$args['ref'] = $_GET['ref'];

			if ( isset( $_GET['show'] ) && ! empty( $_GET['show'] ) )
				$args['time'] = $_GET['show'];

			if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) )
				$args['order'] = $_GET['order'];
			
			if ( isset( $_GET['start'] ) && isset( $_GET['end'] ) )
				$args['amount'] = array( 'start' => $_GET['start'], 'end' => $_GET['end'] );
			
			elseif ( isset( $_GET['num'] ) && isset( $_GET['compare'] ) )
				$args['amount'] = array( 'num' => $_GET['num'], 'compare' => $_GET['compare'] );

			elseif ( isset( $_GET['amount'] ) )
				$args['amount'] = $_GET['amount'];

			$log = new myCRED_Query_Log( $args );
			
			$log->headers = apply_filters( 'mycred_buycred_log_columns', array(
				'column-gateway'  => __( 'Gateway', 'mycred' ),
				'column-username' => __( 'User', 'mycred' ),
				'column-date'     => __( 'Date', 'mycred' ),
				'column-amount'   => __( 'Amount', 'mycred' ),
				'column-payed'    => __( 'Payed', 'mycred' ),
				'column-tranid'   => __( 'Transaction ID', 'mycred' )
			) ); ?>

<div class="wrap list" id="myCRED-wrap">
	<h2><?php _e( '<strong>buy</strong>CRED Purchase Log', 'mycred' ); ?> <a href="<?php echo admin_url( 'admin.php?page=myCRED_page_gateways' ); ?>" class="click-to-toggle add-new-h2"><?php _e( 'Gateway Settings', 'mycred' ); ?></a> <a href="<?php echo $this->get_settings_url( 'buycred_module' ); ?>" class="click-to-toggle add-new-h2"><?php _e( 'buyCRED Settings', 'mycred' ); ?></a></h2>
	<?php $log->filter_dates( admin_url( 'admin.php?page=myCRED_page_gateways_log' ) ); ?>

	<div class="clear"></div>
	<span class="description"><?php _e( 'Only completed purchases are shown here. Purchases that were cancelled or failed are not logged.', 'mycred' ); ?></span>
	<form method="get" action="">
<?php

			if ( isset( $_GET['user_id'] ) && ! empty( $_GET['user_id'] ) )
				echo '<input type="hidden" name="user_id" value="' . $_GET['user_id'] . '" />';

			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) )
				echo '<input type="hidden" name="s" value="' . $_GET['s'] . '" />';

			if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) )
				echo '<input type="hidden" name="ref" value="' . $_GET['ref'] . '" />';

			if ( isset( $_GET['show'] ) && ! empty( $_GET['show'] ) )
				echo '<input type="hidden" name="show" value="' . $_GET['show'] . '" />';

			if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) )
				echo '<input type="hidden" name="order" value="' . $_GET['order'] . '" />';

			$log->search(); ?>

		<input type="hidden" name="page" value="myCRED" />
		<?php do_action( 'mycred_above_payment_log_table', $this ); ?>

		<div class="tablenav top">
<?php 
			$log->filter_options( false, $references );
			$log->navigation( 'top' );
?>

		</div>
		<table class="table wp-list-table widefat mycred-table log-entries" cellspacing="0">
			<thead>
				<tr>
<?php
			foreach ( $log->headers as $col_id => $col_title )
				echo '<th scope="col" id="' . str_replace( 'column-', '', $col_id ) . '" class="manage-column ' . $col_id . '">' . $col_title . '</th>';
?>
				</tr>
			</thead>
			<tfoot>
				<tr>
<?php
			foreach ( $log->headers as $col_id => $col_title )
				echo '<th scope="col" class="manage-column ' . $col_id . '">' . $col_title . '</th>';
?>
				</tr>
			</tfoot>
			<tbody id="the-list">
<?php
			// If we have results
			if ( $log->have_entries() ) {

				// Prep
				$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				$entry_data = '';
				$alt = 0;

				// Loop results				
				foreach ( $log->results as $log_entry ) {

					// Highlight alternate rows
					$alt = $alt+1;
					if ( $alt % 2 == 0 )
						$class = ' alt';
					else
						$class = '';

					// Prep Sales data for use in columns
					$sales_data = $this->get_sales_data_from_log_data( $log_entry->data );
					list ( $buyer_id, $payer_id, $amount, $cost, $currency, $token, $other ) = $sales_data;

					// Default Currency
					if ( empty( $currency ) )
						$currency = 'USD';

					$gateway_name = str_replace( 'buy_creds_with_', '', $log_entry->ref );

					if ( ! array_key_exists( str_replace( '_', '-', $gateway_name ), $this->installed ) )
						$style = ' style="color:silver;"';
					elseif ( ! $this->is_active( str_replace( '_', '-', $gateway_name ) ) )
						$style = ' style="color:gray;"';
					elseif ( substr( $log_entry->entry, 0, 4 ) == 'TEST' )
						$style = ' style="color:orange;"';
					else
						$style = '';
			
					echo '<tr class="myCRED-log-row' . $class . '" id="mycred-log-entry-' . $log_entry->id . '">';
					
					// Run though columns
					foreach ( $log->headers as $column_id => $column_name ) {

						echo '<td class="' . $column_id . '"' . $style . '>';

						switch ( $column_id ) {

							// Used gateway
							case 'column-gateway' :
							
								$gateway = str_replace( array( '-', '_' ), ' ', $gateway_name );
								echo ucwords( $gateway );
							
							break;

							// Username Column
							case 'column-username' :

								$user = get_userdata( $log_entry->user_id );
								if ( $user === false )
									echo __( 'User Missing', 'mycred' ) . ' <em>(ID: ' . $log_entry->user_id . ')</em>';
								else
									echo $user->display_name . ' <em><small>(ID: ' . $log_entry->user_id . ')</small></em>';

							break;

							// Date & Time Column
							case 'column-date' :

								echo date_i18n( $date_format, $log_entry->time );

							break;

							// Amount Column
							case 'column-amount' :

								echo $this->core->format_creds( $log_entry->creds );

							break;

							// Amount Paid
							case 'column-payed' :

								if ( empty( $cost ) )
									echo 'n/a';
								else
									echo number_format( $cost, 2 ) . ' ' . $currency;

							break;

							// Transaction ID
							case 'column-tranid' :

								$transaction_id = $log_entry->time . $log_entry->user_id;
								$saved_data = maybe_unserialize( $log_entry->data );
								if ( isset( $saved_data['txn_id'] ) )
									$transaction_id = $saved_data['txn_id'];
								elseif ( isset( $saved_data['transaction_id'] ) )
									$transaction_id = $saved_data['transaction_id'];

								echo $transaction_id;

							break;

							default :

								do_action( 'mycred_payment_log_' . $column_id, $log_entry );

							break;

						}

						echo '</td>';

					}

					echo '</tr>';

				}

			}
			// No log entry
			else {
				echo '<tr><td colspan="' . count( $log->headers ) . '" class="no-entries">' . __( 'No purchases found', 'mycred' ) . '</td></tr>';
			}
?>

			</tbody>
		</table>
		<div class="tablenav bottom">
			<?php $log->table_nav( 'bottom', false ); ?>

		</div>
		<?php do_action( 'mycred_below_payment_log_table', $this ); ?>

	</form>
</div>
<?php
		}

		/**
		 * Get Sales Data from Log Data
		 * @since 1.4
		 * @version 1.0
		 */
		public function get_sales_data_from_log_data( $log_data = '' ) {
			$defaults = array( '', '', '', '', '', '', '' );
			$log_data = maybe_unserialize( $log_data );
			
			$found_data = array();
			if ( is_array( $log_data ) && array_key_exists( 'sales_data', $log_data ) ) {
				if ( is_array( $log_data['sales_data'] ) )
					$found_data = $log_data['sales_data'];
				else
					$found_data = explode( '|', $log_data['sales_data'] );
			}
			elseif ( ! empty( $log_data ) && ! is_array( $log_data ) ) {
				$try = explode( '|', $log_data );
				if ( count( $try == 7 ) )
					$found_data = $log_data;
			}
			
			return mycred_apply_defaults( $defaults, $found_data );
		}

		/**
		 * Sanititze Settings
		 * @since 0.1
		 * @version 1.1
		 */
		public function sanitize_settings( $data ) {
			$data = apply_filters( 'mycred_buycred_save_prefs', $data );

			$installed = $this->get();
			if ( empty( $installed ) ) return $data;

			foreach ( $installed as $id => $gdata )
				$data['gateway_prefs'][ $id ] = $this->call( 'sanitise_preferences', $installed[ $id ]['callback'], $data['gateway_prefs'][ $id ] );

			$data = mycred_apply_defaults( $this->default_prefs, $data );

			unset( $installed );
			return $data;
		}

		/**
		 * Render Shortcode Basic
		 * This shortcode returns a link element to a specified payment gateway.
		 * @since 0.1
		 * @version 1.1.2
		 */
		public function render_shortcode_basic( $atts, $title = '' ) {
			// Make sure the add-on has been setup
			if ( ! isset( $this->core->buy_creds ) ) {
				if ( mycred_is_admin() )
					return '<p style="color:red;"><a href="' . $this->get_settings_url( 'buycred_module' ) . '">' . __( 'This Add-on needs to setup before you can use this shortcode.', 'mycred' ) . '</a></p>';
				else
					return '';
			}

			extract( shortcode_atts( array(
				'gateway' => '',
				'amount'  => '',
				'gift_to' => '',
				'class'   => 'mycred-buy-link button large custom',
				'login'   => $this->core->template_tags_general( $this->core->buy_creds['login'] )
			), $atts ) );

			// If we are not logged in
			if ( ! is_user_logged_in() ) return '<div class="mycred-buy login">' . $this->core->template_tags_general( $login ) . '</div>';

			// Gateways
			$installed = $this->get();
			if ( empty( $installed ) ) return __( 'No gateways installed.', 'mycred' );
			if ( ! empty( $gateway ) && ! array_key_exists( $gateway, $installed ) ) return __( 'Gateway does not exist.', 'mycred' );
			if ( empty( $gateway ) || ! array_key_exists( $gateway, $installed ) ) {
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
			elseif ( $this->core->buy_creds['gifting']['members'] == 1 && $gift_to != '' ) {
				$user_id = absint( $gift_to );
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
			if ( ! isset( $this->core->buy_creds ) ) {
				if ( mycred_is_admin() )
					return '<p style="color:red;"><a href="' . $this->get_settings_url( 'buycred_module' ) . '">' . __( 'This Add-on needs to setup before you can use this shortcode.', 'mycred' ) . '</a></p>';
				else
					return '';
			}

			extract( shortcode_atts( array(
				'button'  => '',
				'gateway' => '',
				'amount'  => '',
				'gift_to' => '',
				'login'   => $this->core->template_tags_general( $this->core->buy_creds['login'] ),
			), $atts ) );

			// If we are not logged in
			if ( ! is_user_logged_in() ) return '<p class="mycred-buy login">' . $login . '</p>';

			// Catch errors
			$installed = $this->get();
			if ( empty( $installed ) ) return __( 'No gateways installed.', 'mycred' );
			if ( ! empty( $gateway ) && ! array_key_exists( $gateway, $installed ) ) return __( 'Gateway does not exist.', 'mycred' );
			if ( empty( $this->active ) ) return __( 'No active gateways found.', 'mycred' );
			if ( ! empty( $gateway ) && ! $this->is_active( $gateway ) ) return __( 'The selected gateway is not active.', 'mycred' );

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
				$user_id = absint( $gift_to );
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
			if ( ! empty( $gateway ) && isset( $installed[ $gateway ]['title'] ) && empty( $button ) )
				$button_label = __( 'Buy with %gateway%', 'mycred' );

			elseif ( ! empty( $button ) )
				$button_label = $button;

			else
				$button_label = __( 'Buy Now', 'mycred' );

			$button_label = $this->core->template_tags_general( $button_label );

			if ( ! empty( $gateway ) ) {
				$gateway_name = explode( ' ', $installed[ $gateway ]['title'] );
				$button_label = str_replace( '%gateway%', $gateway_name[0], $button_label );
				$classes[] = $gateway_name[0];
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
					if ( ! empty( $blog_users ) ) {
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
			if ( ! empty( $amount ) )
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
					if ( ! $this->is_active( $gateway_id ) ) continue;
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
	<input type="submit" name="submit" value="' . $button_label . '" class="mycred-buy button large" />
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

			if ( $this->core->buy_creds['gifting']['authors'] == 1 && $gift_to == 'author' && in_the_loop() )
				return $GLOBALS['post']->post_author;

			if ( $this->core->buy_creds['gifting']['members'] == 1 )
				return abs( $gift_to );

			return false;
		}
	}

	$buy_creds = new myCRED_buyCRED_Module();
	$buy_creds->load();
}

?>