<?php
/**
 * Addon: Banking
 * Addon URI: http://mycred.me/add-ons/banking/
 * Version: 1.0
 * Description: This add-on allows you to offer interest on your users %_plural% balances or setup recurring payouts.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
// Translate Header (by Dan bp-fr)
$mycred_addon_header_translate = array(
	__( 'Banking', 'mycred' ),
	__( 'This add-on allows you to offer interest on your users points balances or setup recurring payouts.', 'mycred' )
);

if ( !defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_BANK',              __FILE__ );
define( 'myCRED_BANK_DIR',          myCRED_ADDONS_DIR . 'banking/' );
define( 'myCRED_BANK_ABSTRACT_DIR', myCRED_BANK_DIR . 'abstracts/' );
define( 'myCRED_BANK_SERVICES_DIR', myCRED_BANK_DIR . 'services/' );

require_once( myCRED_BANK_ABSTRACT_DIR . 'mycred-abstract-service.php' );
require_once( myCRED_BANK_SERVICES_DIR . 'mycred-bank-service-interest.php' );
require_once( myCRED_BANK_SERVICES_DIR . 'mycred-bank-service-payouts.php' );

/**
 * myCRED_Banking class
 *
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Banking' ) ) {
	class myCRED_Banking extends myCRED_Module {

		/**
		 * Constructor
		 */
		public function __construct() {
			parent::__construct( 'myCRED_Banking', array(
				'module_name' => 'banking',
				'option_id'   => 'mycred_pref_bank',
				'defaults'    => array(
					'active'        => array(),
					'services'      => array(),
					'service_prefs' => array()
				),
				'labels'      => array(
					'menu'        => __( 'Banking', 'mycred' ),
					'page_title'  => __( 'Banking', 'mycred' ),
					'page_header' => __( 'Banking', 'mycred' )
				),
				'screen_id'   => 'myCRED_page_banking',
				'accordion'   => true,
				'menu_pos'    => 30
			) );
		}

		/**
		 * Load Services
		 * @since 1.2
		 * @version 1.0
		 */
		public function module_init() {
			if ( !empty( $this->services ) ) {
				foreach ( $this->services as $key => $gdata ) {
					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'run', $gdata['callback'] );
					}
				}
			}
		}

		/**
		 * Call
		 * Either runs a given class method or function.
		 * @since 1.2
		 * @version 1.1
		 */
		public function call( $call, $callback, $return = NULL ) {
			// Class
			if ( is_array( $callback ) && class_exists( $callback[0] ) ) {
				$class = $callback[0];
				$methods = get_class_methods( $class );
				if ( in_array( $call, $methods ) ) {
					$new = new $class( ( isset( $this->service_prefs ) ) ? $this->service_prefs : array() );
					return $new->$call( $return );
				}
			}

			// Function
			if ( !is_array( $callback ) ) {
				if ( function_exists( $callback ) ) {
					if ( $return !== NULL )
						return call_user_func( $callback, $return, $this );
					else
						return call_user_func( $callback, $this );
				}
			}
		}

		/**
		 * Get Bank Services
		 * @since 1.2
		 * @version 1.0
		 */
		public function get( $save = false ) {
			// Savings
			$services['interest'] = array(
				'title'        => __( 'Compound Interest', 'mycred' ),
				'description'  => __( 'Apply an interest rate on your users %_plural% balances. Interest rate is annual and is compounded daily as long as this service is enabled. Positive interest rate leads to users gaining %_plural% while a negative interest rate will to users loosing %_plural%.', 'mycred' ),
				'callback'     => array( 'myCRED_Banking_Service_Interest' )
			);

			// Inflation
			$services['payouts'] = array(
				'title'       => __( 'Recurring Payouts', 'mycred' ),
				'description' => __( 'Give your users %_plural% on a regular basis with the option to set the number of times you want this payout to run (cycles).', 'mycred' ),
				'callback'    => array( 'myCRED_Banking_Service_Payouts' )
			);

			$services = apply_filters( 'mycred_setup_banking', $services );

			if ( $save === true && $this->core->can_edit_plugin() ) {
				update_option( 'mycred_pref_bank', array(
					'active'        => $this->active,
					'services'      => $services,
					'service_prefs' => $this->service_prefs
				) );
			}

			$this->services = $services;
			return $services;
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
		 * Admin Page
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_page() {
			// Security
			if ( !$this->core->can_edit_plugin( get_current_user_id() ) ) wp_die( __( 'Access Denied', 'mycred' ) );

			// Get installed
			$installed = $this->get( true );

			// Message
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
				echo '<div class="updated settings-error"><p>' . __( 'Settings Updated', 'mycred' ) . '</p></div>';
			} ?>

	<div class="wrap" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php echo apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'Banking', 'mycred' ); ?></h2>
		<p><?php echo $this->core->template_tags_general( __( 'This add-on allows you to setup transaction fees for %_plural% transfers, purchases or payments using the Gateway add-on, along with offering interest on %_plural% balances.', 'mycred' ) ); ?></p>
		<?php if ( defined( 'DISABLE_WP_CRON' ) ) : ?>

		<p><strong><?php _e( 'WP-Cron deactivation detected!', 'mycred' ); ?></strong></p>
		<p><?php _e( 'Warning! This add-on requires WP - Cron to work.', 'mycred' ); ?></p>
		<?php return; endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'myCRED-banking' ); ?>

			<!-- Loop though Services -->
			<div class="list-items expandable-li" id="accordion">
<?php		if ( !empty( $installed ) ) {
				foreach ( $installed as $key => $data ) { ?>

				<h4><div class="gate-icon <?php if ( $this->is_active( $key ) ) echo 'active'; else echo 'inactive'; ?>"></div><?php echo $this->core->template_tags_general( $data['title'] ); ?></h4>
				<div class="body" style="display:none;">
					<p><?php echo nl2br( $this->core->template_tags_general( $data['description'] ) ); ?></p>
					<label class="subheader"><?php _e( 'Enable', 'mycred' ); ?></label>
					<ol>
						<li>
							<input type="checkbox" name="mycred_pref_bank[active][]" id="mycred-bank-service-<?php echo $key; ?>" value="<?php echo $key; ?>"<?php if ( $this->is_active( $key ) ) echo ' checked="checked"'; ?> />
						</li>
					</ol>
					<?php echo $this->call( 'preferences', $data['callback'] ); ?>

				</div>
<?php			}
			} ?>

			</div>
			<?php submit_button( __( 'Update Changes', 'mycred' ), 'primary large', 'submit', false ); ?>

		</form>
	</div>
<?php		unset( $installed );
			unset( $this );
		}

		/**
		 * Sanititze Settings
		 * @since 1.2
		 * @version 1.0
		 */
		public function sanitize_settings( $post ) {
			// Loop though all installed hooks
			$installed = $this->get();

			// Construct new settings
			$new_post['services'] = $installed;
			if ( empty( $post['active'] ) || !isset( $post['active'] ) ) $post['active'] = array();
			$new_post['active'] = $post['active'];

			if ( !empty( $installed ) ) {
				// Loop though all installed
				foreach ( $installed as $key => $data ) {
					// Callback and settings are required
					if ( isset( $data['callback'] ) && isset( $post['service_prefs'][$key] ) ) {
						// Old settings
						$old_settings = $post['service_prefs'][$key];

						// New settings
						$new_settings = $this->call( 'sanitise_preferences', $data['callback'], $old_settings );

						// If something went wrong use the old settings
						if ( empty( $new_settings ) || $new_settings === NULL || !is_array( $new_settings ) )
							$new_post['service_prefs'][$key] = $old_settings;
						// Else we got ourselves new settings
						else
							$new_post['service_prefs'][$key] = $new_settings;

						// Handle de-activation
						if ( isset( $this->active ) && !empty( $this->active ) ) {
							foreach ( $this->active as $id ) {
								// If a previously active id is no longer in the new active array call deactivate
								if ( !in_array( $id, $new_post['active'] ) ) {
									$this->call( 'deactivate', $data['callback'] );
								}
							}
						}
						// Next item
					}
				}
			}

			return $new_post;
		}
	}
	$bank = new myCRED_Banking();
	$bank->load();
}
?>