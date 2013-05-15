<?php
/*
Plugin Name: myCRED
Plugin URI: http://mycred.me
Description: <strong>my</strong>CRED is an adaptive points management system for WordPress powered websites, giving you full control on how points are gained, used, traded, managed, logged or presented.
Version: 1.0.9
Tags: points, tokens, credit, management, reward, charge
Author: Gabriel S Merovingi
Author URI: http://www.merovingi.com
Author Email: mycred@merovingi.com
Requires at least: WP 3.1
Tested up to: WP 3.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
define( 'myCRED_VERSION',      '1.0.9' );
define( 'myCRED_SLUG',         'mycred' );

define( 'myCRED_THIS',          __FILE__ );
define( 'myCRED_ROOT_DIR',      plugin_dir_path( myCRED_THIS ) );
define( 'myCRED_ABSTRACTS_DIR', myCRED_ROOT_DIR . 'abstracts/' );
define( 'myCRED_ADDONS_DIR',    myCRED_ROOT_DIR . 'addons/' );
define( 'myCRED_ASSETS_DIR',    myCRED_ROOT_DIR . 'assets/' );
define( 'myCRED_INCLUDES_DIR',  myCRED_ROOT_DIR . 'includes/' );
define( 'myCRED_LANG_DIR',      myCRED_ROOT_DIR . 'lang/' );
define( 'myCRED_MODULES_DIR',   myCRED_ROOT_DIR . 'modules/' );

/**
 * myCRED_Core class
 * @see http://mycred.me/classes/mycred_core/
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Core' ) ) {
	class myCRED_Core {

		public $plug;

		/**
		 * Construct
		 */
		function __construct() {
			// Core functions
			require_once( myCRED_INCLUDES_DIR . 'mycred-functions.php' );
			if ( !$this->enabled() ) return;

			// Plugin related
			$this->plug = plugin_basename( myCRED_THIS );
			add_filter( 'plugin_action_links_' . $this->plug, array( $this, 'plugin_links' ), 99, 4 );

			// Introduce ourselves to WordPress
			register_uninstall_hook(    myCRED_THIS, array( __CLASS__, 'uninstall_mycred' ) );
			register_activation_hook(   myCRED_THIS, array( $this, 'activate_mycred' )      );
			register_deactivation_hook( myCRED_THIS, array( $this, 'deactivate_mycred' )    );

			// Network Settings
			if ( is_multisite() && is_admin() )
				require_once( myCRED_INCLUDES_DIR . 'mycred-network.php' );

			// Make sure we are ready
			if ( !$this->ready() ) return;

			// Load these only when ready
			require_once( myCRED_INCLUDES_DIR . 'mycred-rankings.php' );

			// Load
			$this->load();

			// Localization
			add_action( 'plugins_loaded',   array( $this, 'language' )            );

			// Inits
			add_action( 'init',             array( $this, 'init_mycred' )         );

			if ( is_admin() )
				add_action( 'admin_init',   array( $this, 'admin_init_mycred' )   );

			add_action( 'widgets_init',     array( $this, 'widgets_init_mycred' ) );
			add_action( 'mycred_reset_key', array( $this, 'reset_key' )           );

			// Pre Init
			do_action( 'mycred_pre_init' );

			// Clean up
			$this->clean_up();
		}

		/**
		 * Plugin Links
		 * @since 0.1
		 * @version 1.0
		 */
		function plugin_links( $actions, $plugin_file, $plugin_data, $context ) {
			// Link to Setup
			if ( !$this->ready() ) {
				$actions['setup'] = '<a href="' . admin_url( 'plugins.php?page=myCRED-setup' ) . '">' . __( 'Setup', 'mycred' ) . '</a>';
				return $actions;
			}

			// Link to Settings
			$mycred = mycred_get_settings();
			if ( $mycred->can_edit_plugin() )
				$actions['settings'] = '<a href="' . admin_url( 'admin.php?page=myCRED_page_settings' ) . '">' . __( 'Settings', 'mycred' ) . '</a>';

			return $actions;
		}

		/**
		 * Runs when the plugin is activated
		 * @since 0.1
		 * @version 1.0
		 */
		function activate_mycred() {
			// Check if blocked
			if ( !$this->enabled() )
				die( __( 'myCRED is blocked for this site. Please contact your network administrator for further details.', 'mycred' ) );

			// Load Installer
			require_once( myCRED_INCLUDES_DIR . 'mycred-install.php' );
			$install = new myCRED_Install();

			// Compatibility check
			$install->compat();

			// First time activation
			if ( $install->ver === false )
				$install->activate();
			// Re-activation
			else
				$install->reactivate();

			// Add Cron Schedule
			if ( !wp_next_scheduled( 'mycred_reset_key' ) ) {
				$frequency = apply_filters( 'mycred_cron_reset_key', 'daily' );
				wp_schedule_event( date_i18n( 'U' ), $frequency, 'mycred_reset_key' );
			}
		}

		/**
		 * Runs when the plugin is deactivated
		 * @since 0.1
		 * @version 1.0
		 */
		function deactivate_mycred() {
			do_action( 'mycred_deactivation' );
		}

		/**
		 * Reset Key
		 * @since 0.1
		 * @version 1.0
		 */
		function reset_key() {
			require_once( myCRED_INCLUDES_DIR . 'mycred-protect.php' );
			$protect = new myCRED_Protect();
			$protect->reset_key();
		}

		/**
		 * Runs when the plugin is deleted
		 * @since 0.1
		 * @version 1.0
		 */
		function uninstall_mycred() {
			// Load Installer
			require_once( myCRED_INCLUDES_DIR . 'mycred-install.php' );
			$install = new myCRED_Install();

			do_action( 'mycred_before_deletion', $install );

			// Run uninstaller
			$install->uninstall();

			do_action( 'mycred_after_deletion', $install );
			unset( $install );
		}

		/**
		 * Check if Ready
		 * @since 0.1
		 * @version 1.0
		 */
		function ready() {
			global $mycred;
			$mycred = new myCRED_Settings();

			// Multisite Ready Check
			if ( is_multisite() ) {
				$mycred_network = mycred_get_settings_network();

				// Check if setup is done
				if ( $mycred_network['master'] == true && $GLOBALS['blog_id'] != 1 )
					$setup = get_blog_option( 1, 'mycred_setup_completed_' . $GLOBALS['blog_id'] );
				else
					$setup = get_option( 'mycred_setup_completed' );

				if ( $setup !== false ) return true;

				// Install local database if needed
				if ( $mycred_network['master'] == true && $GLOBALS['blog_id'] != 1 ) {
					return $this->install_log();
				}
			}

			// Regular Ready Check
			if ( !is_multisite() ) {
				$setup = get_option( 'mycred_setup_completed' );
				if ( $setup !== false ) return true;
			}

			// If we have come this far we need to load the setup
			require_once( myCRED_INCLUDES_DIR . 'mycred-install.php' );
			$setup = new myCRED_Setup();
			return $setup->status();
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 2.0
		 */
		function load() {
			// Load Modules
			$modules = apply_filters( 'mycred_modules', array(
				'addons'  => array( 'class' => 'myCRED_Addons' ),
				'general' => array( 'class' => 'myCRED_General' ),
				'hooks'   => array( 'class' => 'myCRED_Hooks' ),
				'log'     => array( 'class' => 'myCRED_Log' ),
				'help'    => array( 'class' => 'myCRED_Help' )
			) );

			if ( !empty( $modules ) ) {

				// Load Abstracts
				require_once( myCRED_ABSTRACTS_DIR . 'mycred-abstract-module.php' );
				require_once( myCRED_ABSTRACTS_DIR . 'mycred-abstract-hook.php' );

				// Include, init and load each module
				foreach ( $modules as $id => $data ) {
					// If a file is not specified we assume it is our own and load from the default locaiton
					if ( !isset( $data['file'] ) )
						require_once( myCRED_MODULES_DIR . 'mycred-module-' . $id . '.php' );
					// Load the custom file
					else
						require_once( $data['file'] );

					// Load class
					if ( isset( $data['class'] ) ) {
						$class = $data['class'];
						$module = new $class();
						$module->load(); 
					}
				}
				// Clean up
				unset( $modules );
			}
		}

		/**
		 * Load Textdomain
		 * @since 0.1
		 * @version 2.0
		 */
		function language() {
			load_plugin_textdomain( 'mycred', false, myCRED_LANG_DIR );
			do_action( 'mycred_plugin_loaded' );
		}

		/**
		 * Initialize
		 * @since 0.1
		 * @version 2.0
		 */
		function init_mycred() {
			// Enqueue scripts & styles
			add_action( 'wp_enqueue_scripts',    array( $this, 'front_enqueue' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

			// Admin Menu
			add_action( 'admin_menu',            array( $this, 'add_menu' ) );

			// Admin Bar / Tool Bar
			add_action( 'admin_bar_menu',        array( $this, 'tool_bar' ) );

			// Ranking shortcode
			add_shortcode( 'mycred_leaderboard', 'mycred_render_leaderboard' );
			add_shortcode( 'mycred_my_balance',  'mycred_render_my_balance'  );

			// Let others play
			do_action( 'mycred_init' );
		}

		/**
		 * Init Admin
		 * @since 0.1
		 * @version 2.0
		 */
		function admin_init_mycred() {
			// Load admin
			require_once( myCRED_INCLUDES_DIR . 'mycred-admin.php' );
			$admin = new myCRED_Admin();
			$admin->load();

			// Let others play
			do_action( 'mycred_admin_init' );
		}

		/**
		 * Runs when widgets initialize
		 * Grabs the plugin widgets and registers them.
		 *
		 * @uses register_widget()
		 * @since 0.1
		 * @version 1.0
		 */
		function widgets_init_mycred() {
			// Load widgets
			require_once( myCRED_INCLUDES_DIR . 'mycred-widgets.php' );

			// Register Widgets
			register_widget( 'myCRED_Widget_Balance' );
			register_widget( 'myCRED_Widget_List' );

			do_action( 'mycred_widgets_init' );
		}

		/**
		 * Adjust the Tool Bar
		 * @since 0.1
		 * @version 1.0
		 */
		function tool_bar( $wp_admin_bar ) {
			global $bp;

			if ( isset( $bp ) ) return;

			$mycred = mycred_get_settings();
			$user_id = get_current_user_id();
			if ( $mycred->exclude_user( $user_id ) ) return;

			$cred = $mycred->get_users_cred( $user_id );
			$creds_formated = $mycred->format_creds( $cred );

			$wp_admin_bar->add_group( array(
				'parent' => 'my-account',
				'id'     => 'mycred-actions',
			) );

			if ( $mycred->can_edit_plugin() )
				$url = 'users.php?page=mycred_my_history';
			else
				$url = 'profile.php?page=mycred_my_history';

			$wp_admin_bar->add_menu( array(
				'parent' => 'mycred-actions',
				'id'     => 'user-creds',
				'title'  => __( 'My Balance: ', 'mycred' ) . $creds_formated,
				'href'   => admin_url( $url )
			) );

			// Let others play
			do_action( 'mycred_tool_bar', $mycred );
		}

		/**
		 * Add myCRED Admin Menu
		 * @uses add_menu_page()
		 * @since 0.1
		 * @version 1.0
		 */
		function add_menu() {
			$mycred = mycred_get_settings();
			$page = add_menu_page(
				__( 'myCRED', 'mycred' ),
				__( 'myCRED', 'mycred' ),
				$mycred->edit_creds_cap(),
				'myCRED',
				'',
				''
			);
			add_action( 'admin_print_styles-' . $page, array( $this, 'admin_print_styles' ) );

			// Let others play
			do_action( 'mycred_add_menu', $mycred );
		}

		/**
		 * Enqueue Front
		 * @filter 'mycred_remove_widget_css'
		 * @since 0.1
		 * @version 1.0
		 */
		function front_enqueue() {
			// Widget Scripts
			wp_register_script(
				'mycred-widget',
				plugins_url( 'assets/js/widget.js', myCRED_THIS ),
				array( 'jquery' ),
				myCRED_VERSION . '.1'
			);
			wp_enqueue_script( 'mycred-widget' );

			// Widget Style (can be disabled)
			if ( apply_filters( 'mycred_remove_widget_css', false ) === false ) {
				wp_register_style(
					'mycred-widget',
					plugins_url( 'assets/css/widget.css', myCRED_THIS ),
					false,
					myCRED_VERSION . '.1',
					'all'
				);
				wp_enqueue_style( 'mycred-widget' );
			}

			// Let others play
			do_action( 'mycred_front_enqueue' );
		}

		/**
		 * Enqueue Admin
		 * @since 0.1
		 * @version 1.0
		 */
		function admin_enqueue() {
			// Admin Scripts
			wp_register_script(
				'mycred-admin',
				plugins_url( 'assets/js/accordion.js', myCRED_THIS ),
				array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ),
				myCRED_VERSION . '.1'
			);
			wp_localize_script( 'mycred-admin', 'myCRED', apply_filters( 'mycred_localize_admin', array( 'active' => '-1' ) ) );

			// Admin Style
			wp_register_style(
				'mycred-admin',
				plugins_url( 'assets/css/admin.css', myCRED_THIS ),
				false,
				myCRED_VERSION . '.1',
				'all'
			);

			// Let others play
			do_action( 'mycred_admin_enqueue' );
		}
		
		/**
		 * Enqueue Admin Styling
		 * @since 0.1
		 * @version 1.0
		 */
		function admin_print_styles() {
			wp_enqueue_style( 'mycred-admin' );
		}

		/**
		 * Clear up
		 * @since 0.1
		 * @version 1.0
		 */
		private function clean_up() {
			unset( $this );
		}

		/**
		 * Enabled
		 * Check if plugin is enabled.
		 * Requires Multisite
		 * @since 0.1
		 * @version 1.0
		 */
		private function enabled() {
			// Not a multisite = enabled
			if ( !is_multisite() ) return true;

			$prefs = mycred_get_settings_network();

			// Disable list is empty = enabled
			if ( empty( $prefs['block'] ) ) return true;

			// Not in disable list = enabled
			$blog_ids = explode( ',', $prefs['block'] );
			if ( !in_array( $GLOBALS['blog_id'], $blog_ids ) ) return true;

			// All else = disabled
			return false;
		}

		/**
		 * Install Log
		 * Installs the log for a site.
		 * Requires Multisite
		 * @since 0.1
		 * @version 1.0
		 */
		private function install_log() {
			if ( get_blog_option( $GLOBALS['blog_id'], 'mycred_version_db', false ) !== false ) return true;

			global $wpdb;

			$mycred = mycred_get_settings();
			$decimals = (int) $mycred->format['decimals'];
			if ( $decimals > 0 ) {
				if ( $decimals > 4 )
					$cred_format = "decimal(32,$decimals)";
				else
					$cred_format = "decimal(22,$decimals)";
			}
			else {
				$cred_format = 'bigint(22)';
			}

			$table_name = $wpdb->prefix . 'myCRED_log';
			// Log structure
			$sql = "id int(11) NOT NULL AUTO_INCREMENT,
				ref VARCHAR(256) NOT NULL, 
				ref_id int(11) DEFAULT NULL, 
				user_id int(11) DEFAULT NULL, 
				creds $cred_format DEFAULT NULL, 
				ctype VARCHAR(64) DEFAULT 'mycred_default', 
				time bigint(20) DEFAULT NULL, 
				entry LONGTEXT DEFAULT NULL, 
				data LONGTEXT DEFAULT NULL, 
				PRIMARY KEY  (id), 
				UNIQUE KEY id (id)"; 

			// Insert table
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( "CREATE TABLE IF NOT EXISTS " . $table_name . " ( " . $sql . " ) DEFAULT CHARSET = utf8 COLLATE = utf8_general_ci;" );
			add_blog_option( 'mycred_version_db', '1.0', '', 'no' );
			return true;
		}
	}
	new myCRED_Core();
}
?>