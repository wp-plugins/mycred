<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Install class
 * Used when the plugin is activated/de-activated or deleted. Installs core settings and
 * base templates, checks compatibility and uninstalls.
 * @since 0.1
 * @version 1.1.1
 */
if ( ! class_exists( 'myCRED_Install' ) ) :
	class myCRED_Install {

		public $core;
		public $ver;

		/**
		 * Construct
		 */
		function __construct() {
			$this->core = mycred();
			// Get main sites settings
			$this->ver = get_option( 'mycred_version', false );
		}

		/**
		 * Compat
		 * Check to make sure we reach minimum requirements for this plugin to work propery.
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function compat() {
			global $wpdb;

			$message = array();
			// WordPress check
			$wp_version = $GLOBALS['wp_version'];
			if ( version_compare( $wp_version, '3.8', '<' ) && ! defined( 'MYCRED_FOR_OLDER_WP' ) )
				$message[] = __( 'myCRED requires WordPress 3.8 or higher. Version detected:', 'mycred' ) . ' ' . $wp_version;

			// PHP check
			$php_version = phpversion();
			if ( version_compare( $php_version, '5.2.4', '<' ) )
				$message[] = __( 'myCRED requires PHP 5.2.4 or higher. Version detected: ', 'mycred' ) . ' ' . $php_version;

			// SQL check
			$sql_version = $wpdb->db_version();
			if ( version_compare( $sql_version, '5.0', '<' ) )
				$message[] = __( 'myCRED requires SQL 5.0 or higher. Version detected: ', 'mycred' ) . ' ' . $sql_version;

			// mcrypt library check (if missing, this will cause a fatal error)
			$extensions = get_loaded_extensions();
			if ( ! in_array( 'mcrypt', $extensions ) && ! defined( 'MYCRED_DISABLE_PROTECTION' ) )
				$message[] = __( 'The mcrypt PHP library must be enabled in order to use this plugin! Please check your PHP configuration or contact your host and ask them to enable it for you!', 'mycred' );

			// Not empty $message means there are issues
			if ( ! empty( $message ) ) {
				$error_message = implode( "\n", $message );
				die( __( 'Sorry but your WordPress installation does not reach the minimum requirements for running myCRED. The following errors were given:', 'mycred' ) . "\n" . $error_message );
			}
		}

		/**
		 * First time activation
		 * @since 0.1
		 * @version 1.3
		 */
		public function activate() {
			// Add general settings
			add_option( 'mycred_pref_core', $this->core->defaults() );

			// Add add-ons settings
			add_option( 'mycred_pref_addons', array(
				'installed' => array(),
				'active'    => array()
			) );

			// Add hooks settings
			add_option( 'mycred_pref_hooks', array(
				'installed'  => array(),
				'active'     => array(),
				'hook_prefs' => array()
			) );

			// Add version number making sure we never run this function again
			add_option( 'mycred_version', myCRED_VERSION );
			$key = wp_generate_password( 12, true, true );
			add_option( 'mycred_key', $key );

			do_action( 'mycred_activation' );

			if ( isset( $_GET['activate-multi'] ) )
				return;

			set_transient( '_mycred_activation_redirect', true, 60 );
		}

		/**
		 * Re-activation
		 * @since 0.1
		 * @version 1.3.1
		 */
		public function reactivate() {

			do_action( 'mycred_reactivation', $this->ver );

			if ( isset( $_GET['activate-multi'] ) )
				return;

			set_transient( '_mycred_activation_redirect', true, 60 );

		}

		/**
		 * Uninstall
		 * TODO: Add a call to all add-ons to allow them to uninstall their own
		 * settings and data once the core is gone.
		 * @filter 'mycred_uninstall_this'
		 * @since 0.1
		 * @version 1.4.2
		 */
		public function uninstall() {
			// Everyone should use this filter to delete everything else they have created before returning the option ids.
			$installed = apply_filters( 'mycred_uninstall_this', array(
				'mycred_pref_core',
				'mycred_pref_hooks',
				'mycred_pref_addons',
				'mycred_pref_bank',
				'mycred_pref_remote',
				'mycred_types',
				'woocommerce_mycred_settings'
			) );

			// Delete each option
			foreach ( $installed as $option_id )
				delete_option( $option_id );

			// Delete flags
			delete_option( 'mycred_setup_completed' );
			delete_option( 'mycred_version' );
			delete_option( 'mycred_version_db' );
			delete_option( 'mycred_key' );

			// Delete widget options
			delete_option( 'widget_mycred_widget_balance' );
			delete_option( 'widget_mycred_widget_list' );
			delete_option( 'widget_mycred_widget_transfer' );

			delete_option( 'mycred_ref_hook_counter' );

			// Remove Add-on settings
			delete_option( 'mycred_espresso_gateway_prefs' );
			delete_option( 'mycred_eventsmanager_gateway_prefs' );

			// Clear Cron
			wp_clear_scheduled_hook( 'mycred_reset_key' );
			wp_clear_scheduled_hook( 'mycred_banking_recurring_payout' );
			wp_clear_scheduled_hook( 'mycred_banking_do_batch' );
			wp_clear_scheduled_hook( 'mycred_banking_interest_compound' );
			wp_clear_scheduled_hook( 'mycred_banking_do_compound_batch' );
			wp_clear_scheduled_hook( 'mycred_banking_interest_payout' );
			wp_clear_scheduled_hook( 'mycred_banking_interest_do_batch' );
			wp_clear_scheduled_hook( 'mycred_send_email_notices' );

			global $wpdb;

			// Get log table
			if ( defined( 'MYCRED_LOG_TABLE' ) )
				$table_name = MYCRED_LOG_TABLE;

			else {

				if ( mycred_centralize_log() )
					$table_name = $wpdb->base_prefix . 'myCRED_log';
				else
					$table_name = $wpdb->prefix . 'myCRED_log';

			}

			// Delete log table
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" );

			// Multisite
			if ( is_multisite() )
				delete_site_option( 'mycred_network' );
			
			// Delete custom post types
			$post_types = array( 'mycred_rank', 'mycred_email_notice', 'mycred_badge', 'buycred_payment' );
			if ( is_array( $post_types ) || ! empty( $post_types ) )
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ('" . implode( "','", $post_types ) . "');" );

			// Delete custom post type meta
			$post_meta = array( 'myCRED_sell_content', 'mycred_rank_min', 'mycred_rank_max', 'badge_requirements', 'mycred_email_instance', 'mycred_email_settings', 'mycred_email_ctype', 'mycred_email_styling', 'ctype' );
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('" . implode( "','", $post_meta ) . "');" );

			// Delete all point type balances and settings
			$mycred_types = mycred_get_types();
			foreach ( $mycred_types as $type => $label ) {
				delete_option( 'mycred_pref_core_' . $type );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s;", $type ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s;", $type . '_total' ) );
			}

			// Delete Badge Connections
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s;", 'mycred_badge%' ) );

			// Delete Rank Connections
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ( %s, %s );", 'mycred_rank%', 'mycred_rank' ) );

			// Good bye.
		}
	}
endif;

/**
 * myCRED_Setup class
 *
 * Used when the plugin has been activated for the first time. Handles the setup
 * wizard along with temporary admin menus.
 * @since 0.1
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Setup' ) ) :
	class myCRED_Setup {

		public $step;
		public $status;

		public $core;
		public $log_table;

		/**
		 * Construct
		 */
		function __construct() {
			// Status
			$this->status = false;
			$mycred = mycred();
			$this->core = $mycred->core;
			$this->log_table = $mycred->log_table;

			// Setup Step
			$this->step = false;
			if ( isset( $_POST['step'] ) && isset( $_POST['token'] ) )
				$this->step = $_POST['step'];

			// Process choices
			if ( isset( $_POST['step'] ) && isset( $_POST['token'] ) )
				add_action( 'init',              array( $this, 'process_choices' ) );

			// Register Setup Nag and Admin menu
			add_action( 'admin_notices',         array( $this, 'admin_notice' )    );
			add_action( 'admin_menu' ,           array( $this, 'setup_menu' )      );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' )   );
		}

		/**
		 * Setup Setup Nag
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_notice() {
			$screen = get_current_screen();
			if ( $screen->id == 'plugins_page_myCRED-setup' ) return;

			echo '
			<div class="updated">
				<p>' . __( 'myCRED needs your attention.', 'mycred' ) . ' <a href="' . admin_url( 'plugins.php?page=myCRED-setup' ) . '">' . __( 'Run Setup', 'mycred' ) . '</a></p>
			</div>';
		}

		/**
		 * Add Setup page under "Plugins"
		 * @since 0.1
		 * @version 1.0
		 */
		public function setup_menu() {
			$page = add_submenu_page(
				'plugins.php',
				__( 'myCRED Setup', 'mycred' ),
				__( 'myCRED Setup', 'mycred' ),
				'manage_options',
				'myCRED-setup',
				array( &$this, 'setup_page' ) );

			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_header' ) );
		}
		
		/**
		 * Enqueue Admin
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_enqueue() {
			// Admin Style
			wp_register_style(
				'mycred-setup',
				plugins_url( 'assets/css/admin.css', myCRED_THIS ),
				false,
				myCRED_VERSION . '.1',
				'all'
			);
		}

		/**
		 * Return setup status
		 * @since 0.1
		 * @version 1.0
		 */
		public function status() {
			return $this->status;
		}

		/**
		 * Process Setup Steps
		 * @since 0.1
		 * @version 1.0
		 */
		public function process_choices() {
			// Make sure that if we are re-loading the setup page we do not execute again
			$singular = get_option( 'mycred_temp_singular' );
			if ( $singular == $this->step ) return;

			$step = $this->step;
			$settings = $this->core;
			$ok = false;

			// Save step 1 formats.
			if ( $step == 1 ) {
				$ok = true;
			}
			// Step 2
			elseif ( $step == 2 ) {
				$settings['cred_id'] = 'mycred_default';
				// Decimals
				$settings['format']['decimals'] = (int) sanitize_text_field( $_POST['myCRED-format-dec'] );
				if ( empty( $settings['format']['decimals'] ) ) $settings['format']['decimals'] = 0;

				// Separators
				$settings['format']['separators']['decimal'] = $_POST['myCRED-sep-dec'];
				$settings['format']['separators']['thousand'] = $_POST['myCRED-sep-tho'];

				// DB Format
				if ( $settings['format']['decimals'] > 0 )
					$settings['format']['type'] = 'decimal';
				else
					$settings['format']['type'] = 'bigint';

				// Install database
				if ( ! function_exists( 'mycred_install_log' ) )
					require_once( myCRED_INCLUDES_DIR . 'mycred-functions.php' );

				mycred_install_log( $settings['format']['decimals'], $this->log_table );

				// Name
				$settings['name']['singular'] = sanitize_text_field( $_POST['myCRED-name-singular'] );
				$settings['name']['plural'] = sanitize_text_field( $_POST['myCRED-name-plural'] );

				// Prefix & Suffix
				$settings['before'] = sanitize_text_field( $_POST['myCRED-prefix'] );
				$settings['after'] = sanitize_text_field( $_POST['myCRED-suffix'] );

				update_option( 'mycred_pref_core', $settings );
				$this->core = $settings;
				$ok = true;
			}
			// Step 3
			elseif ( $step == 3 ) {
				// Capabilities
				$settings['caps']['plugin'] = ( isset( $_POST['myCRED-cap-plugin'] ) ) ? trim( $_POST['myCRED-cap-plugin'] ) : 'manage_options';
				$settings['caps']['creds'] = ( isset( $_POST['myCRED-cap-creds'] ) ) ? trim( $_POST['myCRED-cap-creds'] ) : 'export';
				$settings['max'] = ( isset( $_POST['myCRED-max'] ) ) ? trim( $_POST['myCRED-max'] ) : 0;

				// Excludes
				$settings['exclude']['plugin_editors'] = ( isset( $_POST['myCRED-exclude-plugin-editors'] ) ) ? true : false;
				$settings['exclude']['cred_editors'] = ( isset( $_POST['myCRED-exclude-cred-editors'] ) ) ? true : false;
				$settings['exclude']['list'] = sanitize_text_field( $_POST['myCRED-exclude-list'] );

				if ( isset( $_POST['myCRED-delete-user'] ) )
					$settings['delete_user'] = sanitize_text_field( $_POST['myCRED-delete-user'] );
				else
					$settings['delete_user'] = 0;

				// Save
				mycred_update_option( 'mycred_pref_core', $settings );
				$this->core = $settings;
				$ok = true;
			}

			if ( $ok )
				update_option( 'mycred_temp_singular', $step );
		}

		/**
		 * Setup Header
		 * @since 0.1
		 * @version 1.1
		 */
		public function settings_header() {
			wp_enqueue_style( 'mycred-setup' );
		}

		/**
		 * Setup page
		 * Outputs the setup page.
		 * @since 0.1
		 * @version 1.1
		 */
		public function setup_page() {
			$label = sprintf( __( '%s Setup', 'mycred' ), mycred_label() );
			if ( $this->step !== false )
				$label .= ' <span>' . __( 'Step', 'mycred' ) . ' ' . $this->step . ' / 3'; ?>

<div class="wrap setup" id="myCRED-wrap">
	<h2><?php echo $label; ?></span></h2>
	<?php $this->get_view(); ?>

</div>
<?php
		}

		/**
		 * Get View for current step
		 * Outputs the current setup step's page.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		protected function get_view() {
			// If step is not set we have not started the setup
			if ( ! $this->step ) { ?>

<form method="post" action="" class="setup step1">
	<input type="hidden" name="step" value="1" />
	<input type="hidden" name="token" value="<?php echo wp_create_nonce( 'myCRED-setup-step1' ); ?>" />
	<p><?php _e( 'Click "Begin Setup" to install myCRED. You will be able to select your points format, layout and security settings.', 'mycred' ); ?></p>
	<p class="action-row"><input type="submit" class="button button-primary button-large" name="being-setup" value="<?php _e( 'Begin Setup', 'mycred' ); ?>" /></p>
</form>
<?php
			}
			// Run setup by calling the current step's method
			else {
				$key = 'myCRED-setup-step' . $this->step;
				// Verify token
				if ( isset( $_POST['token'] ) && wp_verify_nonce( $_POST['token'], $key ) ) {
					$step = (int) $this->step;
					$step_mehod_name = 'setup_step' . $step;

					// Check if method exists
					if ( method_exists( get_class(), $step_mehod_name ) )
						$this->$step_mehod_name();
				}
			}
		}

		/**
		 * Setup Step 1 - Format
		 * First we want to select the creds format we want to use. When submitting this step,
		 * the log database is installed.
		 * @since 0.1
		 * @version 1.0
		 */
		protected function setup_step1() {
			if ( ! $this->step ) return;

			$number = 10;
			$decimals = $this->core['format']['decimals'];
			if ( (int) $decimals > 0 )
				$number = number_format( (float) $number, (int) $decimals, '.', '' );

			// Default no. of decimals
			if ( isset( $_POST['myCRED-format-dec'] ) )
				$default_decimals = sanitize_text_field( abs( $_POST['myCRED-format-dec'] ) );
			else
				$default_decimals = 2;

			// Default decimal separator
			if ( isset( $_POST['myCRED-sep-dec'] ) )
				$default_sep_decimal = strip_tags( $_POST['myCRED-sep-dec'] );
			else
				$default_sep_decimal = '.';

			// Default thousand separator
			if ( isset( $_POST['myCRED-sep-tho'] ) )
				$default_sep_thousand = strip_tags( $_POST['myCRED-sep-tho'] );
			else
				$default_sep_thousand = ' '; ?>

<form method="post" action="" class="setup step2">
	<input type="hidden" name="step" value="2" />
	<input type="hidden" name="token" value="<?php echo wp_create_nonce( 'myCRED-setup-step2' ); ?>" />
	<p><?php _e( 'Select the format you want to use for your points.', 'mycred' ); ?></p>
	<h2><?php _e( 'Format', 'mycred' ); ?></h2>
	<ol class="regular">
		<li class="tl">
			<label><?php _e( 'Separators', 'mycred' ); ?></label>
			<h2>1 <input type="text" name="myCRED-sep-tho" id="myCRED-format-sep-thousand" value="<?php echo $default_sep_thousand; ?>" size="2" /></h2>
			<span class="description">&nbsp;</span>
		</li>
		<li class="tl">
			<label>&nbsp;</label>
			<h2>000 <input type="text" name="myCRED-sep-dec" id="myCRED-format-sep-dec" value="<?php echo $default_sep_decimal; ?>" size="2" /></h2>
			<span class="description">&nbsp;</span>
		</li>
		<li>
			<label><?php _e( 'Decimals', 'mycred' ); ?></label>
			<h2><input type="text" name="myCRED-format-dec" id="myCRED-format-dec" value="<?php echo $default_decimals; ?>" size="6" /></h2>
			<span class="description"><?php _e( 'Use zero for no decimals or maximum 20.', 'mycred' ); ?></span>
		</li>
	</ol>
	<h2 class="shadow"><?php _e( 'Presentation', 'mycred' ); ?></h2>
	<ol class="inline">
		<li>
			<label><?php _e( 'Name (Singular)', 'mycred' ); ?></label>
			<h2><input type="text" name="myCRED-name-singular" id="myCRED-name-singular" value="Point" /></h2>
		</li>
		<li>
			<label><?php _e( 'Name (Plural)', 'mycred' ); ?></label>
			<h2><input type="text" name="myCRED-name-plural" id="myCRED-name-plural" value="Points" /></h2>
		</li>
	</ol>
	<ol class="inline">
		<li>
			<label><?php _e( 'Prefix', 'mycred' ); ?></label>
			<h2><input type="text" size="5" name="myCRED-prefix" id="myCRED-prefix" value="" /></h2>
		</li>
		<li class="middle">
			<label>&nbsp;</label>
			<h2><?php echo $number; ?></h2>
		</li>
		<li>
			<label><?php _e( 'Suffix', 'mycred' ); ?></label>
			<h2><input type="text" size="5" name="myCRED-suffix" id="myCRED-suffix" value="" /></h2>
		</li>
	</ol>
	<p class="action-row"><a href="<?php echo admin_url( 'plugins.php?page=myCRED-setup' ); ?>" title="<?php _e( 'Cancel Setup', 'mycred' ); ?>" class="button button-secondary button-large" style="margin-right:24px;"><?php _e( 'Cancel', 'mycred' ); ?></a> <input type="submit" class="button button-primary button-large" name="being-setup" id="mycred-next-button" value="<?php _e( 'Next', 'mycred' ); ?>" /></p>
</form>
<?php
		}

		/**
		 * Setup Step 2 - Presentation
		 * In this step we get to name our creds along with settup how creds are shown around
		 * the website.
		 * @since 0.1
		 * @version 1.2
		 */
		protected function setup_step2() {
			if ( ! $this->step ) return;
			$mycred = mycred();

			// Capabilities
			$edit_plugin = ( isset( $_POST['myCRED-cap-plugin'] ) ) ? sanitize_text_field( $_POST['myCRED-cap-plugin'] ) : 'manage_options';
			$edit_creds = ( isset( $_POST['myCRED-cap-creds'] ) ) ? sanitize_text_field( $_POST['myCRED-cap-creds'] ) : 'export';

			// Max
			$max = ( isset( $_POST['myCRED-max'] ) ) ? $mycred->number( $_POST['myCRED-max'] ) : $mycred->number( 0 );

			// Excludes
			$exclude_plugin_editors = ( isset( $_POST['myCRED-exclude-plugin-editors'] ) ) ? 1 : 0;
			$exclude_cred_editors = ( isset( $_POST['myCRED-exclude-cred-editors'] ) ) ? 1 : 0;
			$exclude_list = ( isset( $_POST['myCRED-exclude-list'] ) ) ? $_POST['myCRED-exclude-list'] : '';
			$delete_user = ( isset( $_POST['delete_user'] ) ) ? 1 : 0; ?>

<form method="post" action="" class="setup step3">
	<input type="hidden" name="step" value="3" />
	<input type="hidden" name="token" value="<?php echo wp_create_nonce( 'myCRED-setup-step3' ); ?>" />
	<h2><?php _e( 'Security', 'mycred' ); ?></h2>
	<ol class="inline">
		<li>
			<label for="myCRED-cap-plugin"><?php _e( 'Edit Settings Capability', 'mycred' ); ?></label>
			<h2><input type="text" name="myCRED-cap-plugin" id="myCRED-cap-plugin" value="<?php echo $edit_plugin; ?>" /></h2>
		</li>
		<li>
			<label for="myCRED-cap-creds"><?php echo $mycred->template_tags_general( __( 'Edit Users %plural% Capability', 'mycred' ) ); ?></label>
			<h2><input type="text" name="myCRED-cap-creds" id="myCRED-cap-creds" value="<?php echo $edit_creds; ?>" /></h2>
		</li>
		<li class="block">
			<label for="myCRED-max"><?php echo $mycred->template_tags_general( __( 'Maximum %plural% payouts', 'mycred' ) ); ?></label>
			<div class="h2"><input type="text" name="myCRED-max" id="myCRED-max" value="<?php echo $max; ?>" size="8" /></div>
			<span class="description"><?php _e( 'As an added security, you can set the maximum amount a user can gain or loose in a single instance. If used, make sure this is the maximum amount a user would be able to transfer, buy, or spend in your store. Use zero to disable.', 'mycred' ); ?></span>
		</li>
	</ol>
	<h2><?php _e( 'Excludes', 'mycred' ); ?></h2>
	<ol>
		<li>
			<input type="checkbox" name="myCRED-exclude-plugin-editors" id="myCRED-exclude-plugin"<?php checked( $exclude_plugin_editors, 1 ); ?> value="1" class="checkbox" /> 
			<label for="myCRED-exclude-plugin"><?php _e( 'Exclude those who can "Edit Settings".', 'mycred' ); ?></label>
		</li>
		<li style="width: 100%;">
			<input type="checkbox" name="myCRED-exclude-cred-editors" id="myCRED-exclude-caps"<?php checked( $exclude_cred_editors, 1 ); ?> value="1" class="checkbox" /> 
			<label for="myCRED-exclude-caps"><?php echo $mycred->template_tags_general( __( 'Exclude those who can "Edit Users %plural%".', 'mycred' ) ); ?></label>
		</li>
		<li>
			<label for="myCRED-exclude-list"><?php _e( 'Exclude the following user IDs:', 'mycred' ); ?></label>
			<h2><input type="text" name="myCRED-exclude-list" id="myCRED-exclude-list" value="<?php echo $exclude_list; ?>" /></h2>
		</li>
	</ol>
	<h2><?php _e( 'User Deletions', 'mycred' ); ?></h2>
	<ol>
		<li>
			<input type="checkbox" name="myCRED-delete-user" id="myCRED-delete-user"<?php checked( $delete_user, 1 ); ?> value="1" class="checkbox" />
			<label for="myCRED-delete-user"><?php _e( 'Delete log entries when user is deleted.', 'mycred' ); ?></label>
		</li>
	</ol>
	<p class="action-row"><input type="submit" class="button button-primary button-large" name="being-setup" value="<?php _e( 'Next', 'mycred' ); ?>" /></p>
</form>
<?php
		}

		/**
		 * Setup Step 3 -  Final Page
		 * @since 0.1
		 * @version 1.0
		 */
		protected function setup_step3() {
			if ( ! $this->step ) return;

			// Once this is set the setup will no longer load
			update_option( 'mycred_setup_completed', date_i18n( 'U' ) );
			delete_option( 'mycred_temp_singular' ); ?>

<form method="post" action="" class="step4">
	<h1><?php _e( 'Ready', 'mycred' ); ?></h1>
	<h2 class="shadow"><?php _e( 'Almost done! Click the button below to finish this setup.', 'mycred' ); ?></h2>
	<p class="action-row"><a href="<?php echo admin_url( '/admin.php?page=myCRED' ); ?>" class="button button-primary button-large"><?php _e( 'Install & Run', 'mycred' ); ?></a></p>
</form>
<?php
		}
	}
endif;

?>