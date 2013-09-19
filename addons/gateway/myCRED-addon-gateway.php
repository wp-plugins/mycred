<?php
/**
 * Addon: Gateway
 * Addon URI: http://mycred.me/add-ons/gateway/
 * Version: 1.3
 * Description: Let your users pay using their <strong>my</strong>CRED points balance. Supported Carts: WooCommerce, MarketPress. Supported Event Bookings: Event Espresso, Events Manager.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
// Translate Header (by Dan bp-fr)
$mycred_addon_header_translate = array(
	__( 'Gateway', 'mycred' ),
	__( 'Let your users pay using their <strong>my</strong>CRED points balance. Supported Carts: WooCommerce, MarketPress. Supported Event Bookings: Event Espresso, Events Manager.', 'mycred' )
);

if ( !defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_GATE',            __FILE__ );
define( 'myCRED_GATE_DIR',        myCRED_ADDONS_DIR . 'gateway/' );
define( 'myCRED_GATE_ASSETS_DIR', myCRED_GATE_DIR . 'assets/' );
define( 'myCRED_GATE_CART_DIR',   myCRED_GATE_DIR . 'carts/' );
define( 'myCRED_GATE_EVENT_DIR',   myCRED_GATE_DIR . 'event-booking/' );

/**
 * Supported Carts
 */
require_once( myCRED_GATE_CART_DIR . 'mycred-woocommerce.php' );
require_once( myCRED_GATE_CART_DIR . 'mycred-marketpress.php' );
require_once( myCRED_GATE_CART_DIR . 'mycred-wpecommerce.php' );

/**
 * Supported Event Management Plugins
 */
require_once( myCRED_GATE_EVENT_DIR . 'mycred-eventespresso3.php' );
require_once( myCRED_GATE_EVENT_DIR . 'mycred-eventsmanager.php' );
?>