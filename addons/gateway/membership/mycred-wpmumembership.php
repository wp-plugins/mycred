<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * WPMU Membership Payment Gateway
 *
 * Custom Payment Gateway for WPMU Membership.
 * @since 1.3
 * @version 1.0
 */
if ( !function_exists( 'mycred_init_wpmumembership_gateway' ) ) {
	/**
	 * Construct Gateway
	 * @since 1.3
	 * @version 1.0
	 */
	add_action( 'plugins_loaded', 'mycred_init_wpmumembership_gateway' );
	function mycred_init_wpmumembership_gateway()
	{
		
	}
}
?>