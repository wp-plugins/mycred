<?php
/**
 * Plugin Name: myCRED
 * Plugin URI: http://mycred.me
 * Description: <strong>my</strong>CRED is an adaptive points management system for WordPress powered websites, giving you full control on how points are gained, used, traded, managed, logged or presented.
 * Version: 1.3.1
 * Tags: points, tokens, credit, management, reward, charge
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: info@merovingi.com
 * Requires at least: WP 3.1
 * Tested up to: WP 3.6
 * Text Domain: mycred
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
define( 'myCRED_VERSION',      '1.3.1' );
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

require_once( myCRED_INCLUDES_DIR . 'mycred-functions.php' );

require_once( myCRED_ABSTRACTS_DIR . 'mycred-abstract-hook.php' );
require_once( myCRED_ABSTRACTS_DIR . 'mycred-abstract-module.php' );

/**
 * myCRED_Core Class
 * Removed in 1.3 but defined since some customizations
 * use this to check that myCRED exists or is installed.
 * @since 1.3
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Core' ) ) {
	final class myCRED_Core {

		public $plug;

		/**
		 * Construct
		 */
		function __construct() {
			$this->plug = plugin_basename( myCRED_THIS );
			// no longer used
		}
	}
}

/**
 * Required
 * @since 1.3
 * @version 1.1
 */
function mycred_load() {
	require_once( myCRED_INCLUDES_DIR . 'mycred-remote.php' );
	require_once( myCRED_INCLUDES_DIR . 'mycred-log.php' );
	require_once( myCRED_INCLUDES_DIR . 'mycred-network.php' );
	
	// Bail now if the setup needs to run
	if ( is_mycred_ready() === false ) return;
	
	require_once( myCRED_INCLUDES_DIR . 'mycred-rankings.php' );
	require_once( myCRED_INCLUDES_DIR . 'mycred-shortcodes.php' );
	require_once( myCRED_INCLUDES_DIR . 'mycred-widgets.php' );
	
	// Add-ons
	require_once( myCRED_MODULES_DIR . 'mycred-module-addons.php' );
	$addons = new myCRED_Addons();
	$addons->load();
	
	do_action( 'mycred_ready' );
	
	add_action( 'init',         'mycred_init' );
	add_action( 'widgets_init', 'mycred_widgets_init' );
	add_action( 'admin_init',   'mycred_admin_init' );
}
mycred_load();

/**
 * Plugin Activation
 * @since 1.3
 * @version 1.0
 */
register_activation_hook( myCRED_THIS, 'mycred_plugin_activation' );
function mycred_plugin_activation()
{
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

	// Delete stray debug options
	delete_option( 'mycred_catch_fires' );
}

/**
 * Runs when the plugin is deactivated
 * @since 1.3
 * @version 1.0
 */
register_deactivation_hook( myCRED_THIS, 'mycred_plugin_deactivation' );
function mycred_plugin_deactivation() {
	// Clear Cron
	wp_clear_scheduled_hook( 'mycred_reset_key' );
	wp_clear_scheduled_hook( 'mycred_banking_recurring_payout' );
	wp_clear_scheduled_hook( 'mycred_banking_interest_compound' );
	wp_clear_scheduled_hook( 'mycred_banking_interest_payout' );

	do_action( 'mycred_deactivation' );
}

/**
 * Runs when the plugin is deleted
 * @since 1.3
 * @version 1.0
 */
register_uninstall_hook( myCRED_THIS, 'mycred_plugin_uninstall' );
function mycred_plugin_uninstall()
{
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
 * myCRED Plugin Startup
 * @since 1.3
 * @version 1.0
 */
add_action( 'plugins_loaded', 'mycred_plugin_start_up', 999 );
function mycred_plugin_start_up()
{
	global $mycred;
	$mycred = new myCRED_Settings();
	
	// Load Translation
	load_plugin_textdomain( 'mycred', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

	// Adjust the plugin links
	add_filter( 'plugin_action_links_mycred/mycred.php', 'mycred_plugin_links', 10, 4 );

	// Lets start with Multisite
	if ( is_multisite() ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) )
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		if ( is_plugin_active_for_network( 'mycred/mycred.php' ) ) {
			$network = new myCRED_Network();
			$network->load();
		}
	}

	// Load only hooks that we have use of
	if ( defined( 'JETPACK__PLUGIN_DIR' ) ) 
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-jetpack.php' );

	if ( class_exists( 'bbPress' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-bbPress.php' );

	if ( function_exists( 'invite_anyone_init' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-invite-anyone.php' );

	if ( function_exists( 'wpcf7' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-contact-form7.php' );

	if ( class_exists( 'BadgeOS' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-badgeOS.php' );

	if ( function_exists( 'vote_poll' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-wp-polls.php' );

	if ( function_exists( 'wp_favorite_posts' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-wp-favorite-posts.php' );

	if ( function_exists( 'bp_em_init' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-events-manager-light.php' );

	if ( defined( 'STARRATING_DEBUG' ) )
		require_once( myCRED_PLUGINS_DIR . 'mycred-hook-gd-star-rating.php' );

	// Load Settings
	require_once( myCRED_MODULES_DIR . 'mycred-module-general.php' );
	$settings = new myCRED_General();
	$settings->load();

	// Load hooks
	require_once( myCRED_MODULES_DIR . 'mycred-module-hooks.php' );
	$hooks = new myCRED_Hooks();
	$hooks->load();

	// Load log
	require_once( myCRED_MODULES_DIR . 'mycred-module-log.php' );
	$log = new myCRED_Log();
	$log->load();
	
	do_action( 'mycred_pre_init' );
}

/**
 * Init
 * @since 1.3
 * @version 1.0
 */
function mycred_init()
{
	// Enqueue scripts & styles
	add_action( 'wp_enqueue_scripts',    'mycred_enqueue_front' );
	add_action( 'admin_enqueue_scripts', 'mycred_enqueue_admin' );

	// Admin Menu
	add_action( 'admin_menu',     'mycred_admin_menu', 9 );

	// Admin Bar / Tool Bar
	add_action( 'admin_bar_menu', 'mycred_hook_into_toolbar' );

	// Shortcodes
	if ( !is_admin() ) {
		add_shortcode( 'mycred_history',     'mycred_render_shortcode_history'    );
		add_shortcode( 'mycred_leaderboard', 'mycred_render_leaderboard'          );
		add_shortcode( 'mycred_my_ranking',  'mycred_render_my_ranking'           );
		add_shortcode( 'mycred_my_balance',  'mycred_render_shortcode_my_balance' );
		add_shortcode( 'mycred_give',        'mycred_render_shortcode_give'       );
		add_shortcode( 'mycred_send',        'mycred_render_shortcode_send'       );
	}

	// Let others play
	do_action( 'mycred_init' );
}

/**
 * Widgets Init
 * @since 1.3
 * @version 1.0
 */
function mycred_widgets_init()
{
	// Register Widgets
	register_widget( 'myCRED_Widget_Balance' );
	register_widget( 'myCRED_Widget_List' );

	// Let others play
	do_action( 'mycred_widgets_init' );
}

/**
 * Admin Init
 * @since 1.3
 * @version 1.0
 */
function mycred_admin_init()
{
	// Load admin
	require_once( myCRED_INCLUDES_DIR . 'mycred-admin.php' );
	$admin = new myCRED_Admin();
	$admin->load();

	// Let others play
	do_action( 'mycred_admin_init' );
}

/**
 * Adjust the Tool Bar
 * @since 1.3
 * @version 1.1
 */
function mycred_hook_into_toolbar( $wp_admin_bar )
{
	global $bp;
	if ( isset( $bp ) ) return;

	$mycred = mycred_get_settings();
	$user_id = get_current_user_id();
	if ( $mycred->exclude_user( $user_id ) ) return;

	$cred = $mycred->get_users_cred( $user_id );

	$wp_admin_bar->add_group( array(
		'parent' => 'my-account',
		'id'     => 'mycred-actions',
	) );

	if ( $mycred->can_edit_plugin() )
		$url = 'users.php?page=mycred_my_history';
	else
		$url = 'profile.php?page=mycred_my_history';

	$my_balance = apply_filters( 'mycred_label_my_balance', __( 'My Balance: %cred_f%', 'mycred' ), $user_id, $mycred );
	$wp_admin_bar->add_menu( array(
		'parent' => 'mycred-actions',
		'id'     => 'user-creds',
		'title'  => $mycred->template_tags_amount( $my_balance, $cred ),
		'href'   => add_query_arg( array( 'page' => 'mycred_my_history' ), get_edit_profile_url( $user_id ) )
	) );

	// Let others play
	do_action( 'mycred_tool_bar', $mycred );
}

/**
 * Add myCRED Admin Menu
 * @uses add_menu_page()
 * @since 1.3
 * @version 1.0
 */
function mycred_admin_menu()
{
	$mycred = mycred_get_settings();
	$name = apply_filters( 'mycred_label', myCRED_NAME );
	$page = add_menu_page(
		$name,
		$name,
		$mycred->edit_creds_cap(),
		'myCRED',
		'',
		''
	);
	add_action( 'admin_print_styles-' . $page, 'mycred_admin_page_styles' );

	// Let others play
	do_action( 'mycred_add_menu', $mycred );
}

/**
 * Enqueue Front
 * @filter 'mycred_remove_widget_css'
 * @since 1.3
 * @version 1.0
 */
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

/**
 * Enqueue Admin
 * @since 1.3
 * @version 1.2
 */
function mycred_enqueue_admin()
{
	$mycred = mycred_get_settings();
	// General Admin Script
	wp_register_script(
		'mycred-admin',
		plugins_url( 'assets/js/accordion.js', myCRED_THIS ),
		array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ),
		myCRED_VERSION . '.1'
	);
	wp_localize_script( 'mycred-admin', 'myCRED', apply_filters( 'mycred_localize_admin', array( 'active' => '-1' ) ) );

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
			'working'       => __( 'Processing...', 'mycred' ),
			'confirm_log'   => __( 'Warning! All entries in your log will be permamenly removed! This can not be undone!', 'mycred' ),
			'confirm_reset' => __( 'Warning! All user balances will be set to zero! This can not be undone!', 'mycred' ),
			'done'          => __( 'Done!', 'mycred' ),
			'export_close'  => __( 'Close', 'mycred' ),
			'export_title'  => $mycred->template_tags_general( __( 'Export users %plural%', 'mycred' ) )
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
			'title'   => sprintf( __( 'Edit Users %s balance', 'mycred' ),$mycred->plural() ),
			'close'   => __( 'Close', 'mycred' ),
			'working' => __( 'Processing...', 'mycred' )
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

/**
 * Enqueue Admin Styling
 * @since 1.3
 * @version 1.0
 */
function mycred_admin_page_styles()
{
	wp_enqueue_style( 'mycred-admin' );
}

/**
 * Reset Key
 * @since 1.3
 * @version 1.0
 */
add_action( 'mycred_reset_key', 'mycred_reset_key' );
function mycred_reset_key()
{
	require_once( myCRED_INCLUDES_DIR . 'mycred-protect.php' );
	$protect = new myCRED_Protect();
	$protect->reset_key();
}

/**
 * myCRED Plugin Links
 * @since 1.3
 * @version 1.0.1
 */
function mycred_plugin_links( $actions, $plugin_file, $plugin_data, $context )
{
	// Link to Setup
	if ( !is_mycred_ready() ) {
		$actions['setup'] = '<a href="' . admin_url( 'plugins.php?page=myCRED-setup' ) . '">' . __( 'Setup', 'mycred' ) . '</a>';
		return $actions;
	}

	$actions['tutorials'] = '<a href="http://mycred.me/support/tutorials/" target="_blank">' . __( 'Tutorials', 'mycred' ) . '</a>';
	$actions['docs'] = '<a href="http://codex.mycred.me/" target="_blank">' . __( 'Codex', 'mycred' ) . '</a>';
	$actions['store'] = '<a href="http://mycred.me/store/" target="_blank">' . __( 'Store', 'mycred' ) . '</a>';

	return $actions;
}
?>