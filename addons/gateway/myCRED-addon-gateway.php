<?php
/**
 * Addon: Gateway
 * Addon URI: http://mycred.me/add-ons/gateway/
 * Version: 1.4
 * Description: Let your users pay using their <strong>my</strong>CRED points balance. Supported Carts: WooCommerce, MarketPress and WP E-Commerce. Supported Event Bookings: Event Espresso, Events Manager.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_GATE',               __FILE__ );
define( 'myCRED_GATE_DIR',           myCRED_ADDONS_DIR . 'gateway/' );
define( 'myCRED_GATE_ASSETS_DIR',    myCRED_GATE_DIR . 'assets/' );
define( 'myCRED_GATE_CART_DIR',      myCRED_GATE_DIR . 'carts/' );
define( 'myCRED_GATE_EVENT_DIR',     myCRED_GATE_DIR . 'event-booking/' );
define( 'myCRED_GATE_MEMBER_DIR',    myCRED_GATE_DIR . 'membership/' );
define( 'myCRED_GATE_AFFILIATE_DIR', myCRED_GATE_DIR . 'affiliate/' );

/**
 * Supported Carts
 */
require_once myCRED_GATE_CART_DIR . 'mycred-woocommerce.php';
require_once myCRED_GATE_CART_DIR . 'mycred-marketpress.php';
require_once myCRED_GATE_CART_DIR . 'mycred-wpecommerce.php';

/**
 * Event Espresso
 */
add_action( 'mycred_init', 'mycred_load_event_espresso3' );
function mycred_load_event_espresso3() {
	if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) return;

	require_once myCRED_GATE_EVENT_DIR . 'mycred-eventespresso3.php';
	$gateway = new myCRED_Espresso_Gateway();
	$gateway->load();
}

/**
 * Events Manager
 */
add_action( 'mycred_init', 'mycred_load_events_manager' );
function mycred_load_events_manager() {
	if ( ! defined( 'EM_VERSION' ) ) return;
	
	// Pro
	if ( class_exists( 'EM_Pro' ) && class_exists( 'EM_Gateways' ) ) {
		require_once myCRED_GATE_EVENT_DIR . 'mycred-eventsmanager-pro.php';
		EM_Gateways::register_gateway( 'mycred', 'EM_Gateway_myCRED' );
	}
	// Free
	else {
		require_once myCRED_GATE_EVENT_DIR . 'mycred-eventsmanager.php';
		$events = new myCRED_Events_Manager_Gateway();
		$events->load();
	}
}

?>