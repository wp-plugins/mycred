<?php
/**
 * Addon: Banking
 * Addon URI: http://mycred.me/add-ons/banking/
 * Version: 1.2
 * Description: Setup recurring payouts or offer / charge interest on user account balances.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_BANK',              __FILE__ );
define( 'myCRED_BANK_DIR',          myCRED_ADDONS_DIR . 'banking/' );
define( 'myCRED_BANK_ABSTRACT_DIR', myCRED_BANK_DIR . 'abstracts/' );
define( 'myCRED_BANK_SERVICES_DIR', myCRED_BANK_DIR . 'services/' );

require_once( myCRED_BANK_ABSTRACT_DIR . 'mycred-abstract-service.php' );

require_once( myCRED_BANK_SERVICES_DIR . 'mycred-bank-service-central.php' );
require_once( myCRED_BANK_SERVICES_DIR . 'mycred-bank-service-interest.php' );
require_once( myCRED_BANK_SERVICES_DIR . 'mycred-bank-service-payouts.php' );

/**
 * myCRED_Banking_Module class
 * @since 0.1
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Banking_Module' ) ) {
	class myCRED_Banking_Module extends myCRED_Module {

		/**
		 * Constructor
		 */
		public function __construct( $type = 'mycred_default' ) {
			parent::__construct( 'myCRED_Banking_Module', array(
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
			), $type );

			add_action( 'mycred_edit_profile',        array( $this, 'user_level_override' ), 30, 2 );
			add_action( 'mycred_edit_profile_action', array( $this, 'save_user_level_override' ) );
			add_action( 'mycred_admin_notices',       array( $this, 'update_user_level_profile_notice' ) );	
		}

		/**
		 * Load Services
		 * @since 1.2
		 * @version 1.0
		 */
		public function module_init() {

			if ( ! empty( $this->services ) ) {
				foreach ( $this->services as $key => $gdata ) {
					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'run', $gdata['callback'] );
					}
				}
			}

		}

		/**
		 * User Level Override
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function user_level_override( $user, $type ) {
			if ( $this->mycred_type != $type ) return;

			if ( ! empty( $this->services ) ) {
				foreach ( $this->services as $key => $gdata ) {
					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'user_override', $gdata['callback'], $user, $type );
					}
				}
			}
		}

		/**
		 * Save User Level Override
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function save_user_level_override() {
			if ( ! empty( $this->services ) ) {
				foreach ( $this->services as $key => $gdata ) {
					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'save_user_override', $gdata['callback'] );
					}
				}
			}
		}

		/**
		 * User Level Profile Notice
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function update_user_level_profile_notice() {
			if ( ! empty( $this->services ) ) {
				foreach ( $this->services as $key => $gdata ) {
					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'user_override_notice', $gdata['callback'] );
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
		public function call( $call, $callback, $return = NULL, $var1 = NULL, $var2 = NULL, $var3 = NULL ) {
			// Class
			if ( is_array( $callback ) && class_exists( $callback[0] ) ) {
				$class = $callback[0];
				$methods = get_class_methods( $class );
				if ( in_array( $call, $methods ) ) {
					$new = new $class( ( isset( $this->service_prefs ) ) ? $this->service_prefs : array(), $this->mycred_type );
					return $new->$call( $return, $var1, $var2, $var3 );
				}
			}

			// Function
			elseif ( ! is_array( $callback ) ) {
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
			$services['central'] = array(
				'title'        => __( 'Central Banking', 'mycred' ),
				'description'  => __( 'Instead of creating %_plural% out of thin-air, all payouts are made from a nominated "Central Bank" account. Any %_plural% a user spends or loses are deposited back into this account.', 'mycred' ),
				'callback'     => array( 'myCRED_Banking_Service_Central' )
			);

			// Interest
			$services['interest'] = array(
				'title'        => __( 'Compound Interest', 'mycred' ),
				'description'  => __( 'Apply a positive or negative interest rate on your users %_plural% balances.', 'mycred' ),
				'callback'     => array( 'myCRED_Banking_Service_Interest' )
			);

			// Inflation
			$services['payouts'] = array(
				'title'       => __( 'Recurring Payouts', 'mycred' ),
				'description' => __( 'Setup mass %_singular% payouts for your users.', 'mycred' ),
				'callback'    => array( 'myCRED_Banking_Service_Payouts' )
			);

			$services = apply_filters( 'mycred_setup_banking', $services );

			if ( $save === true && $this->core->can_edit_plugin() ) {
				$new_data = array(
					'active'        => $this->active,
					'services'      => $services,
					'service_prefs' => $this->service_prefs
				);
				mycred_update_option( $this->option_id, $new_data );
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
			$banking_icons = plugins_url( 'assets/images/gateway-icons.png', myCRED_THIS ); ?>

<!-- Banking Add-on -->
<style type="text/css">
#myCRED-wrap #accordion h4 .gate-icon { display: block; width: 48px; height: 48px; margin: 0 0 0 0; padding: 0; float: left; line-height: 48px; }
#myCRED-wrap #accordion h4 .gate-icon { background-repeat: no-repeat; background-image: url("<?php echo $banking_icons; ?>"); background-position: 0 0; }
#myCRED-wrap #accordion h4 .gate-icon.inactive { background-position-x: 0; }
#myCRED-wrap #accordion h4 .gate-icon.active { background-position-x: -48px; }
#myCRED-wrap #accordion h4 .gate-icon.sandbox { background-position-x: -96px; }
</style>
<?php
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page() {
			// Security
			if ( ! $this->core->can_edit_creds() )
				wp_die( __( 'Access Denied', 'mycred' ) );

			// Get installed
			$installed = $this->get( true ); ?>

<div class="wrap" id="myCRED-wrap">
	<h2><?php echo sprintf( __( '%s Banking', 'mycred' ), mycred_label() ); ?></h2>
	<?php $this->update_notice(); ?>

	<p><?php echo $this->core->template_tags_general( __( 'Your banking setup for %plural%.', 'mycred' ) ); ?></p>
	<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>

	<p><strong><?php _e( 'WP-Cron deactivation detected!', 'mycred' ); ?></strong></p>
	<p><?php _e( 'Warning! This add-on requires WP - Cron to work.', 'mycred' ); ?></p>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( $this->settings_name ); ?>

		<!-- Loop though Services -->
		<div class="list-items expandable-li" id="accordion">
<?php
			// Installed Services
			if ( ! empty( $installed ) ) {
				foreach ( $installed as $key => $data ) { ?>

			<h4><div class="gate-icon <?php if ( $this->is_active( $key ) ) echo 'active'; else echo 'inactive'; ?>"></div><?php echo $this->core->template_tags_general( $data['title'] ); ?></h4>
			<div class="body" style="display:none;">
				<p><?php echo nl2br( $this->core->template_tags_general( $data['description'] ) ); ?></p>
				<label class="subheader"><?php _e( 'Enable', 'mycred' ); ?></label>
				<ol>
					<li>
						<input type="checkbox" name="<?php echo $this->option_id; ?>[active][]" id="mycred-bank-service-<?php echo $key; ?>" value="<?php echo $key; ?>"<?php if ( $this->is_active( $key ) ) echo ' checked="checked"'; ?> />
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
<?php
		}

		/**
		 * Sanititze Settings
		 * @since 1.2
		 * @version 1.1
		 */
		public function sanitize_settings( $post ) {
			// Loop though all installed hooks
			$installed = $this->get();

			// Construct new settings
			$new_post['services'] = $installed;
			if ( empty( $post['active'] ) || ! isset( $post['active'] ) )
				$post['active'] = array();

			$new_post['active'] = $post['active'];

			if ( ! empty( $installed ) ) {
				foreach ( $installed as $key => $data ) {
					if ( isset( $data['callback'] ) && isset( $post['service_prefs'][ $key ] ) ) {
						// Old settings
						$old_settings = $post['service_prefs'][ $key ];

						// New settings
						$new_settings = $this->call( 'sanitise_preferences', $data['callback'], $old_settings );

						// If something went wrong use the old settings
						if ( empty( $new_settings ) || $new_settings === NULL || ! is_array( $new_settings ) )
							$new_post['service_prefs'][ $key ] = $old_settings;
						// Else we got ourselves new settings
						else
							$new_post['service_prefs'][ $key ] = $new_settings;

						// Handle de-activation
						if ( in_array( $key, (array) $this->active ) && ! in_array( $key, $new_post['active'] ) )
							$this->call( 'deactivate', $data['callback'], $new_post['service_prefs'][ $key ] );

						// Handle activation
						if ( ! in_array( $key, (array) $this->active ) && in_array( $key, $new_post['active'] ) )
							$this->call( 'activate', $data['callback'], $new_post['service_prefs'][ $key ] );

						// Next item
					}
				}
			}

			$installed = NULL;
			return $new_post;
		}
	}
}

add_action( 'mycred_pre_init', 'mycred_load_banking' );
function mycred_load_banking()
{
	global $mycred_modules;

	$mycred_types = mycred_get_types();
	foreach ( $mycred_types as $type => $title ) {
		$mycred_modules[ $type ]['banking'] = new myCRED_Banking_Module( $type );
		$mycred_modules[ $type ]['banking']->load();
	}
}
?>