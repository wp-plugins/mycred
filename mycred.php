<?php
/**
 * Plugin Name: myCRED
 * Plugin URI: http://mycred.me
 * Description: <strong>my</strong>CRED is an adaptive points management system for WordPress powered websites, giving you full control on how points are gained, used, traded, managed, logged or presented.
 * Version: 1.6.3
 * Tags: points, tokens, credit, management, reward, charge, buddypress, bbpress, jetpack, woocommerce, marketpress, wp e-commerce, gravity forms, simplepress
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 3.8
 * Tested up to: WP 4.1
 * Text Domain: mycred
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * SSL Compatible: yes
 * bbPress Compatible: yes
 * WordPress Compatible: yes
 * BuddyPress Compatible: yes
 * Forum URI: http://mycred.me/support/forums/
 */
define( 'myCRED_VERSION',      '1.6.3' );
define( 'myCRED_SLUG',         'mycred' );
define( 'myCRED_NAME',         '<strong>my</strong>CRED' );

define( 'myCRED_THIS',          __FILE__ );
define( 'myCRED_ROOT_DIR',      plugin_dir_path( myCRED_THIS ) );
define( 'myCRED_ABSTRACTS_DIR', myCRED_ROOT_DIR . 'abstracts/' );
define( 'myCRED_ADDONS_DIR',    myCRED_ROOT_DIR . 'addons/' );
define( 'myCRED_ASSETS_DIR',    myCRED_ROOT_DIR . 'assets/' );
define( 'myCRED_INCLUDES_DIR',  myCRED_ROOT_DIR . 'includes/' );
define( 'myCRED_LANG_DIR',      myCRED_ROOT_DIR . 'lang/' );
define( 'myCRED_MODULES_DIR',   myCRED_ROOT_DIR . 'modules/' );
define( 'myCRED_PLUGINS_DIR',   myCRED_ROOT_DIR . 'plugins/' );

require_once myCRED_INCLUDES_DIR . 'mycred-functions.php';
require_once myCRED_INCLUDES_DIR . 'mycred-about.php';

require_once myCRED_ABSTRACTS_DIR . 'mycred-abstract-hook.php';
require_once myCRED_ABSTRACTS_DIR . 'mycred-abstract-module.php';

/**
 * myCRED_Core Class
 * Removed in 1.3 but defined since some customizations
 * use this to check that myCRED exists or is installed.
 * @since 1.3
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Core' ) ) {
	final class myCRED_Core {

		/**
		 * Construct
		 */
		function __construct() {
			_deprecated_function( __CLASS__, '1.3', 'mycred_load()' );
		}
	}
}

/**
 * Required
 * @since 1.3
 * @version 1.2
 */
if ( ! function_exists( 'mycred_load' ) ) :
	function mycred_load()
	{
		// Check Network blocking
		if ( mycred_is_site_blocked() ) return;

		// Load required files
		require_once myCRED_INCLUDES_DIR . 'mycred-remote.php';
		require_once myCRED_INCLUDES_DIR . 'mycred-log.php';
		require_once myCRED_INCLUDES_DIR . 'mycred-network.php';
		require_once myCRED_INCLUDES_DIR . 'mycred-protect.php';
		include_once myCRED_INCLUDES_DIR . 'mycred-update.php';

		// Bail now if the setup needs to run
		if ( is_mycred_ready() === false ) return;

		require_once myCRED_INCLUDES_DIR . 'mycred-widgets.php';

		// Add-ons
		require_once myCRED_MODULES_DIR . 'mycred-module-addons.php';
		$addons = new myCRED_Addons_Module();
		$addons->load();
		$addons->run_addons();

		do_action( 'mycred_ready' );

		add_action( 'plugins_loaded',   'mycred_plugin_start_up', 999 );
		add_action( 'init',             'mycred_init', 5 );
		add_action( 'widgets_init',     'mycred_widgets_init' );
		add_action( 'admin_init',       'mycred_admin_init' );
		add_action( 'mycred_reset_key', 'mycred_reset_key' );

		register_activation_hook(   myCRED_THIS, 'mycred_plugin_activation' );
		register_deactivation_hook( myCRED_THIS, 'mycred_plugin_deactivation' );
		register_uninstall_hook(    myCRED_THIS, 'mycred_plugin_uninstall' );

		add_action( 'in_plugin_update_message-mycred/mycred.php', 'mycred_update_warning' );

	}
endif;

mycred_load();

/**
 * Plugin Activation
 * @since 1.3
 * @version 1.1.1
 */
if ( ! function_exists( 'mycred_plugin_activation' ) ) :
	function mycred_plugin_activation()
	{
		// Load Installer
		require_once myCRED_INCLUDES_DIR . 'mycred-install.php';
		$install = new myCRED_Install();

		// Compatibility check
		$install->compat();

		// First time activation
		if ( $install->ver === false )
			$install->activate();
		// Re-activation
		else
			$install->reactivate();

	}
endif;

/**
 * Runs when the plugin is deactivated
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_plugin_deactivation' ) ) :
	function mycred_plugin_deactivation()
	{
		// Clear Cron
		wp_clear_scheduled_hook( 'mycred_reset_key' );
		wp_clear_scheduled_hook( 'mycred_banking_recurring_payout' );
		wp_clear_scheduled_hook( 'mycred_banking_interest_compound' );
		wp_clear_scheduled_hook( 'mycred_banking_interest_payout' );

		do_action( 'mycred_deactivation' );
	}
endif;

/**
 * Runs when the plugin is deleted
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_plugin_uninstall' ) ) :
	function mycred_plugin_uninstall()
	{
		// Load Installer
		require_once myCRED_INCLUDES_DIR . 'mycred-install.php';
		$install = new myCRED_Install();

		do_action( 'mycred_before_deletion', $install );

		// Run uninstaller
		$install->uninstall();

		do_action( 'mycred_after_deletion', $install );
		unset( $install );
	}
endif;

/**
 * myCRED Plugin Startup
 * @since 1.3
 * @version 1.6
 */
if ( ! function_exists( 'mycred_plugin_start_up' ) ) :
	function mycred_plugin_start_up()
	{
		global $mycred, $mycred_types, $mycred_modules;
		$mycred = new myCRED_Settings();

		$mycred_types = mycred_get_types();

		require_once myCRED_INCLUDES_DIR . 'mycred-shortcodes.php';
		require_once myCRED_INCLUDES_DIR . 'mycred-referrals.php';

		// Load Translation
		$locale = apply_filters( 'plugin_locale', get_locale(), 'mycred' );
		load_textdomain( 'mycred', WP_LANG_DIR . "/mycred/mycred-$locale.mo" );
		load_plugin_textdomain( 'mycred', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

		// Adjust the plugin links
		add_filter( 'plugin_action_links_mycred/mycred.php', 'mycred_plugin_links', 10, 4 );
		add_filter( 'plugin_row_meta', 'mycred_plugin_description_links', 10, 2 );

		// Lets start with Multisite
		if ( is_multisite() ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) )
				require_once ABSPATH . '/wp-admin/includes/plugin.php';

			if ( is_plugin_active_for_network( 'mycred/mycred.php' ) ) {
				$network = new myCRED_Network_Module();
				$network->load();
			}
		}

		// Load Settings
		require_once myCRED_MODULES_DIR . 'mycred-module-settings.php';
		foreach ( $mycred_types as $type => $title ) {
			$mycred_modules[ $type ]['settings'] = new myCRED_Settings_Module( $type );
			$mycred_modules[ $type ]['settings']->load();
		}

		// Load only hooks that we have use of
		if ( defined( 'JETPACK__PLUGIN_DIR' ) ) 
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-jetpack.php';

		if ( class_exists( 'bbPress' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-bbPress.php';

		if ( function_exists( 'invite_anyone_init' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-invite-anyone.php';

		if ( function_exists( 'wpcf7' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-contact-form7.php';

		if ( class_exists( 'BadgeOS' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-badgeOS.php';

		if ( function_exists( 'vote_poll' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-wp-polls.php';

		if ( function_exists( 'wp_favorite_posts' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-wp-favorite-posts.php';

		if ( function_exists( 'bp_em_init' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-events-manager-light.php';

		if ( defined( 'STARRATING_DEBUG' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-gd-star-rating.php';

		if ( defined( 'SFTOPICS' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-simplepress.php';
	
		if ( function_exists( 'bp_links_setup_root_component' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-buddypress-links.php';

		if ( function_exists( 'bpa_init' ) || function_exists( 'bpgpls_init' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-buddypress-gallery.php';

		if ( class_exists( 'GFForms' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-gravityforms.php';

		if ( function_exists( 'rtmedia_autoloader' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-buddypress-media.php';

		if ( function_exists( 'install_ShareThis' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-sharethis.php';

		if ( class_exists( 'WooCommerce' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-woocommerce.php';

		if ( class_exists( 'MarketPress' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-marketpress.php';

		if ( defined( 'WP_POSTRATINGS_VERSION' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-wp-postratings.php';

		if ( class_exists( 'Affiliate_WP' ) )
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-affiliatewp.php';

		// Load hooks
		require_once myCRED_MODULES_DIR . 'mycred-module-hooks.php';
		foreach ( $mycred_types as $type => $title ) {
			$mycred_modules[ $type ]['hooks'] = new myCRED_Hooks_Module( $type );
			$mycred_modules[ $type ]['hooks']->load();
		}

		// Load log
		require_once myCRED_MODULES_DIR . 'mycred-module-log.php';
		foreach ( $mycred_types as $type => $title ) {
			$mycred_modules[ $type ]['log'] = new myCRED_Log_Module( $type );
			$mycred_modules[ $type ]['log']->load();
		}

		// BuddyPress	
		if ( class_exists( 'BuddyPress' ) ) {
			require_once myCRED_PLUGINS_DIR . 'mycred-hook-buddypress.php';

			require_once myCRED_MODULES_DIR . 'mycred-module-buddypress.php';
			$mycred_modules['mycred_default']['buddypress'] = new myCRED_BuddyPress_Module( 'mycred_default' );
			$mycred_modules['mycred_default']['buddypress']->load();
		}

		// Load admin
		require_once myCRED_INCLUDES_DIR . 'mycred-admin.php';
		$admin = new myCRED_Admin();
		$admin->load();

		do_action( 'mycred_pre_init' );
	}
endif;

/**
 * Init
 * @since 1.3
 * @version 1.3.1
 */
if ( ! function_exists( 'mycred_init' ) ) :
	function mycred_init()
	{
		// Add Cron Schedule
		if ( ! wp_next_scheduled( 'mycred_reset_key' ) )
			wp_schedule_event( date_i18n( 'U' ), apply_filters( 'mycred_cron_reset_key', 'daily' ), 'mycred_reset_key' );

		// Enqueue scripts & styles
		add_action( 'wp_enqueue_scripts',    'mycred_enqueue_front' );
		add_action( 'admin_enqueue_scripts', 'mycred_enqueue_admin' );

		add_action( 'admin_head',     'mycred_admin_head', 999 );
		add_action( 'admin_menu',     'mycred_admin_menu', 9 );
		add_action( 'admin_bar_menu', 'mycred_hook_into_toolbar' );

		// Shortcodes
		add_shortcode( 'mycred_history',       'mycred_render_shortcode_history' );
		add_shortcode( 'mycred_leaderboard',   'mycred_render_shortcode_leaderboard' );
		add_shortcode( 'mycred_my_ranking',    'mycred_render_shortcode_my_ranking' );
		add_shortcode( 'mycred_my_balance',    'mycred_render_shortcode_my_balance' );
		add_shortcode( 'mycred_give',          'mycred_render_shortcode_give' );
		add_shortcode( 'mycred_send',          'mycred_render_shortcode_send' );
		add_shortcode( 'mycred_total_balance', 'mycred_render_shortcode_total' );
		add_shortcode( 'mycred_exchange',      'mycred_render_shortcode_exchange' );
		add_shortcode( 'mycred_hook_table',    'mycred_render_shortcode_hook_table' );

		// Shortcode related
		add_action( 'wp_footer',                  'mycred_send_shortcode_footer' );
		add_action( 'wp_ajax_mycred-send-points', 'mycred_shortcode_send_points_ajax' );

		// Referral System
		mycred_load_referral_program();

		add_shortcode( 'mycred_affiliate_link', 'mycred_render_affiliate_link' );
		add_shortcode( 'mycred_affiliate_id',   'mycred_render_affiliate_id' );

		// Exchange
		mycred_catch_exchange_requests();

		// Let others play
		do_action( 'mycred_init' );
	}
endif;

/**
 * Widgets Init
 * @since 1.3
 * @version 1.1
 */
if ( ! function_exists( 'mycred_widgets_init' ) ) :
	function mycred_widgets_init()
	{
		// Register Widgets
		register_widget( 'myCRED_Widget_Balance' );
		register_widget( 'myCRED_Widget_Leaderboard' );

		$mycred_types = mycred_get_types();
		if ( count( $mycred_types ) > 1 )
			register_widget( 'myCRED_Widget_Wallet' );

		// Let others play
		do_action( 'mycred_widgets_init' );
	}
endif;

/**
 * Admin Init
 * @since 1.3
 * @version 1.3
 */
if ( ! function_exists( 'mycred_admin_init' ) ) :
	function mycred_admin_init()
	{
		// Run update if needed
		$mycred_version = get_option( 'mycred_version', myCRED_VERSION );
		if ( $mycred_version != myCRED_VERSION )
			do_action( 'mycred_reactivation', $mycred_version );

		// Dashboard Overview
		require_once myCRED_INCLUDES_DIR . 'mycred-overview.php';

		// Register importers
		if ( defined( 'WP_LOAD_IMPORTERS' ) )
			require_once myCRED_INCLUDES_DIR . 'mycred-importer.php';

		// Let others play
		do_action( 'mycred_admin_init' );

		if ( get_transient( '_mycred_activation_redirect' ) === apply_filters( 'mycred_active_redirect', false ) )
			return;

		delete_transient( '_mycred_activation_redirect' );

		$url = add_query_arg( array( 'page' => 'mycred' ), admin_url( 'index.php' ) );
		wp_safe_redirect( $url );
		die;
	}
endif;

/**
 * Remove About Page
 * @since 1.3.2
 * @version 1.1
 */
if ( ! function_exists( 'mycred_admin_head' ) ) :
	function mycred_admin_head()
	{
		remove_submenu_page( 'index.php', 'mycred' );
		remove_submenu_page( 'index.php', 'mycred-credit' );
		remove_submenu_page( 'users.php', 'mycred-edit-balance' );
	}
endif;

/**
 * Adjust the Tool Bar
 * @since 1.3
 * @version 1.4.2
 */
if ( ! function_exists( 'mycred_hook_into_toolbar' ) ) :
	function mycred_hook_into_toolbar( $wp_admin_bar )
	{
		if ( ! is_user_logged_in() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) return;

		$user_id = get_current_user_id();

		global $bp, $mycred, $mycred_types;

		// Start by making sure we have usable point types
		$usable = 0;
		foreach ( $mycred_types as $type => $type_label ) {
			$point_type = mycred( $type );
			if ( ! $point_type->exclude_user( $user_id ) )
				$usable ++;
		}
		if ( $usable == 0 ) return;

		$main_label = __( 'Balance', 'mycred' );
		if ( count( $mycred_types ) == 1 )
			$main_label = $mycred->plural();

		// BuddyPress
		if ( is_object( $bp ) )
			$wp_admin_bar->add_menu( array(
				'parent' => 'my-account-buddypress',
				'id'     => 'mycred-account',
				'title'  => $main_label,
				'href'   => false
			) );

		// Default
		else
			$wp_admin_bar->add_menu( array(
				'parent' => 'my-account',
				'id'     => 'mycred-account',
				'title'  => $main_label,
				'meta'   => array(
					'class' => 'ab-sub-secondary'
				)
			) );

		$my_balance_label = apply_filters( 'mycred_label_my_balance', '%label%: %cred_f%', $user_id, $mycred );
		$mycred_history_label = __( '%label% History', 'mycred' );

		$counter = 0;
		foreach ( $mycred_types as $type => $type_label ) {

			$point_type = mycred( $type );

			if ( $point_type->exclude_user( $user_id ) ) continue;
			$counter++;

			$balance = $point_type->get_users_cred( $user_id, $type );

			$mycred_history_url = apply_filters( 'mycred_my_history_url', admin_url( 'users.php?page=' . $type . '_history' ), $type );

			$bp_query = '';
			if ( $type != 'mycred_default' )
				$bp_query = '?show-ctype=' . $type;

			if ( isset( $mycred->buddypress['history_url'] ) && function_exists( 'bp_loggedin_user_domain' ) )
				$mycred_history_url = bp_loggedin_user_domain() . $mycred->buddypress['history_url'] . '/' . $bp_query;

			if ( $type == 'mycred_default' )
				$type_label = $point_type->plural();

			$my_balance_label = str_replace( '%label%', $type_label, $my_balance_label );
			$mycred_history_label = str_replace( '%label%', $type_label, $mycred_history_label );

			$id = str_replace( '_', '-', $type ) . $counter;

			$wp_admin_bar->add_menu( array(
				'parent' => 'mycred-account',
				'id'     => 'mycred-account-balance-' . $id,
				'title'  => $point_type->template_tags_amount( $my_balance_label, $balance ),
				'href'   => false
			) );

			if ( ( function_exists( 'bp_loggedin_user_domain' ) && isset( $mycred->buddypress['visibility']['history'] ) && $mycred->buddypress['visibility']['history'] ) || ( ! function_exists( 'bp_loggedin_user_domain' ) ) || $mycred->can_edit_creds() )
				$wp_admin_bar->add_menu( array(
					'parent' => 'mycred-account',
					'id'     => 'mycred-account-history-' . $id,
					'title'  => $mycred_history_label,
					'href'   => $mycred_history_url
				) );

			$my_balance_label = str_replace( $type_label, '%label%', $my_balance_label );
			$mycred_history_label = str_replace( $type_label, '%label%', $mycred_history_label );

		}

		if ( $counter == 0 )
			$wp_admin_bar->remove_menu( array( 'id' => 'mycred-account' ) );

		// Let others play
		do_action( 'mycred_tool_bar', $wp_admin_bar, $mycred );
	}
endif;

/**
 * Add myCRED Admin Menu
 * @uses add_menu_page()
 * @since 1.3
 * @version 1.2
 */
if ( ! function_exists( 'mycred_admin_menu' ) ) :
	function mycred_admin_menu()
	{
		$mycred = mycred();
		$name = mycred_label( true );

		global $mycred_types, $wp_version;

		$pages = array();
		$slug = 'myCRED';

		$menu_icon = 'dashicons-star-filled';
		if ( version_compare( $wp_version, '3.8', '<' ) )
			$menu_icon = '';

		foreach ( $mycred_types as $type => $title ) {
			$type_slug = 'myCRED';
			if ( $type != 'mycred_default' )
				$type_slug = 'myCRED_' . trim( $type );

			$pages[] = add_menu_page(
				$title,
				$title,
				$mycred->edit_creds_cap(),
				$type_slug,
				'',
				$menu_icon
			);
		}

		$about_label = sprintf( __( 'About %s', 'mycred' ), $name );
		$pages[] = add_dashboard_page(
			$about_label,
			$about_label,
			'moderate_comments',
			'mycred',
			'mycred_about_page'
		);

		$cred_label = __( 'Awesome People', 'mycred' );
		$pages[] = add_dashboard_page(
			$cred_label,
			$cred_label,
			'moderate_comments',
			'mycred-credit',
			'mycred_about_credit_page'
		);

		$pages = apply_filters( 'mycred_admin_pages', $pages, $mycred );
		foreach ( $pages as $page )
			add_action( 'admin_print_styles-' . $page, 'mycred_admin_page_styles' );

		// Let others play
		do_action( 'mycred_add_menu', $mycred );
	}
endif;

/**
 * Enqueue Front
 * @filter 'mycred_remove_widget_css'
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_enqueue_front' ) ) :
	function mycred_enqueue_front()
	{
		global $mycred_sending_points;

		// Send Points Shortcode
		wp_register_script(
			'mycred-send-points',
			plugins_url( 'assets/js/send.js', myCRED_THIS ),
			array( 'jquery' ),
			myCRED_VERSION . '.1',
			true
		);

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
endif;

/**
 * Enqueue Admin
 * @since 1.3
 * @version 1.2.1
 */
if ( ! function_exists( 'mycred_enqueue_admin' ) ) :
	function mycred_enqueue_admin()
	{
		$mycred = mycred();
		// General Admin Script
		wp_register_script(
			'mycred-admin',
			plugins_url( 'assets/js/accordion.js', myCRED_THIS ),
			array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ),
			myCRED_VERSION . '.1'
		);

		// Management Admin Script
		wp_register_script(
			'mycred-manage',
			plugins_url( 'assets/js/management.js', myCRED_THIS ),
			array( 'jquery', 'mycred-admin', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide' ),
			myCRED_VERSION . '.1'
		);
		wp_localize_script(
			'mycred-manage',
			'myCREDmanage',
			array(
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'token'         => wp_create_nonce( 'mycred-management-actions' ),
				'working'       => esc_attr__( 'Processing...', 'mycred' ),
				'confirm_log'   => esc_attr__( 'Warning! All entries in your log will be permanently removed! This can not be undone!', 'mycred' ),
				'confirm_clean' => esc_attr__( 'All log entries belonging to deleted users will be permanently deleted! This can not be undone!', 'mycred' ),
				'confirm_reset' => esc_attr__( 'Warning! All user balances will be set to zero! This can not be undone!', 'mycred' ),
				'done'          => esc_attr__( 'Done!', 'mycred' ),
				'export_close'  => esc_attr__( 'Close', 'mycred' ),
				'export_title'  => $mycred->template_tags_general( esc_attr__( 'Export users %plural%', 'mycred' ) ),
				'decimals'      => esc_attr__( 'In order to adjust the number of decimal places you want to use we must update your log. It is highly recommended that you backup your current log before continuing!', 'mycred' )
			)
		);

		// Inline Editing Script
		wp_register_script(
			'mycred-inline-edit',
			plugins_url( 'assets/js/inline-edit.js', myCRED_THIS ),
			array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide' ),
			myCRED_VERSION . '.1'
		);
		wp_localize_script(
			'mycred-inline-edit',
			'myCREDedit',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'title'   => esc_attr__( 'Edit Users Balance', 'mycred' ),
				'close'   => esc_attr__( 'Close', 'mycred' ),
				'working' => esc_attr__( 'Processing...', 'mycred' )
			)
		);
		
		// Log Edit Script
		wp_register_script(
			'mycred-edit-log',
			plugins_url( 'assets/js/edit-log.js', myCRED_THIS ),
			array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide' ),
			myCRED_VERSION . '.1'
		);
		wp_localize_script(
			'mycred-edit-log',
			'myCREDLog',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'title'   => esc_attr__( 'Edit Log Entry', 'mycred' ),
				'close'   => esc_attr__( 'Close', 'mycred' ),
				'working' => esc_attr__( 'Processing...', 'mycred' ),
				'messages' => array(
					'delete_row'  => esc_attr__( 'Are you sure you want to delete this log entry? This can not be undone!', 'mycred' ),
					'updated_row' => esc_attr__( 'Log entry updated', 'mycred' )
				),
				'tokens' => array(
					'delete_row' => wp_create_nonce( 'mycred-delete-log-entry' ),
					'update_row' => wp_create_nonce( 'mycred-update-log-entry' )
				)
			)
		);

		// Admin Style
		wp_register_style(
			'mycred-admin',
			plugins_url( 'assets/css/admin.css', myCRED_THIS ),
			false,
			myCRED_VERSION . '.1',
			'all'
		);
		wp_register_style(
			'mycred-inline-edit',
			plugins_url( 'assets/css/inline-edit.css', myCRED_THIS ),
			false,
			myCRED_VERSION . '.1',
			'all'
		);

		// Let others play
		do_action( 'mycred_admin_enqueue' );
	}
endif;

/**
 * Enqueue Admin Styling
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_admin_page_styles' ) ) :
	function mycred_admin_page_styles()
	{
		wp_enqueue_style( 'mycred-admin' );
	}
endif;

/**
 * myCRED Plugin Links
 * @since 1.3
 * @version 1.1
 */
if ( ! function_exists( 'mycred_plugin_links' ) ) :
	function mycred_plugin_links( $actions, $plugin_file, $plugin_data, $context )
	{
		if ( mycred_is_site_blocked() ) return $actions;

		// Link to Setup
		if ( ! is_mycred_ready() )
			$actions['_setup'] = '<a href="' . admin_url( 'plugins.php?page=myCRED-setup' ) . '">' . __( 'Setup', 'mycred' ) . '</a>';
		else
			$actions['_settings'] = '<a href="' . admin_url( 'admin.php?page=myCRED' ) . '" >' . __( 'Settings', 'mycred' ) . '</a>';

		ksort( $actions );
		return $actions;
	}
endif;

/**
 * myCRED Plugin Description Links
 * @since 1.3.3.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_plugin_description_links' ) ) :
	function mycred_plugin_description_links( $links, $file )
	{
		if ( $file != plugin_basename( myCRED_THIS ) ) return $links;
	
		// Link to Setup
		if ( ! is_mycred_ready() ) {
			$links[] = '<a href="' . admin_url( 'plugins.php?page=myCRED-setup' ) . '">' . __( 'Setup', 'mycred' ) . '</a>';
			return $links;
		}

		$links[] = '<a href="' . admin_url( 'index.php?page=mycred' ) . '">About</a>';
		$links[] = '<a href="http://mycred.me/support/tutorials/" target="_blank">Tutorials</a>';
		$links[] = '<a href="http://codex.mycred.me/" target="_blank">Codex</a>';
		$links[] = '<a href="http://mycred.me/store/" target="_blank">Store</a>';

		return $links;
	}
endif;

/**
 * Update Warning
 * Remind users to always backup before updating.
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_update_warning' ) ) :
	function mycred_update_warning() {
		echo '<div style="color:#cc0000;">' . __( 'Make sure to backup your database and files before updating, in case anything goes wrong!', 'mycred' ) . '</div>';
	}
endif;

/**
 * Reset Key
 * @since 1.3
 * @version 1.1
 */
if ( ! function_exists( 'mycred_reset_key' ) ) :
	function mycred_reset_key()
	{
		$protect = mycred_protect();
		if ( $protect !== false )
			$protect->reset_key();
	}
endif;
