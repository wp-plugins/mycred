<?php
/**
 * Addon: Gateway
 * Addon URI: http://mycred.me/add-ons/gateway/
 * Version: 1.2
 * Description: Let your users pay using their <strong>my</strong>CRED points balance. Supported Carts: WooCommerce, MarketPress. Other plugins: Event Espresso
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( !defined( 'myCRED_VERSION' ) ) exit;
define( 'myCRED_GATE',            __FILE__ );
define( 'myCRED_GATE_DIR',        myCRED_ADDONS_DIR . 'gateway/' );
define( 'myCRED_GATE_ASSETS_DIR', myCRED_GATE_DIR . 'assets/' );
define( 'myCRED_GATE_CART_DIR',   myCRED_GATE_DIR . 'carts/' );
/**
 * Supported Carts
 */
require_once( myCRED_GATE_CART_DIR . 'mycred-woocommerce.php' );
require_once( myCRED_GATE_CART_DIR . 'mycred-marketpress.php' );
require_once( myCRED_GATE_CART_DIR . 'mycred-eventespresso3.php' );
?>