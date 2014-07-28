<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED 1.5 Update
 * Updated existing myCRED installations to 1.5
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_update_to_onefive' ) ) :
	add_action( 'mycred_reactivation', 'mycred_update_to_onefive' );
	function mycred_update_to_onefive() {

		$version = get_option( 'mycred_version', myCRED_VERSION );

		// Clean up after the 1.4.6 Email Notice bug
		if ( version_compare( $version, '1.4.7', '<' ) ) {
			$cron = get_option( 'cron' );
			if ( ! empty( $cron ) ) {
				foreach ( $cron as $time => $job ) {
					if ( isset( $job['mycred_send_email_notices'] ) )
						unset( $cron[ $time ] );
					
				}
				update_option( 'cron', $cron );
			}
		}

		// 1.4 Update
		if ( version_compare( $version, '1.4', '>=' ) ) {
			delete_option( 'mycred_update_req_settings' );
			delete_option( 'mycred_update_req_hooks' );
		}

		// 1.5 Update
		if ( version_compare( $version, '1.5', '<' ) && class_exists( 'myCRED_buyCRED_Module' ) ) {
			// Update buyCRED Settings
			$type_set = 'mycred_default';
			$setup = mycred_get_option( 'mycred_pref_core', false );
			if ( isset( $setup['buy_creds'] ) ) {
				if ( isset( $setup['buy_creds']['type'] ) ) {
					$type_set = $setup['buy_creds']['type'];
					unset( $setup['buy_creds']['type'] );
					$setup['buy_creds']['types'] = array( $type_set );
					mycred_update_option( 'mycred_pref_core', $setup );
				}
			}
			
			// Update buyCRED Gateways Settings
			$buy_cred = mycred_get_option( 'mycred_pref_buycreds', false );
			if ( isset( $buy_cred['gateway_prefs'] ) ) {
				foreach ( $buy_cred['gateway_prefs'] as $gateway_id => $prefs ) {
					if ( ! isset( $prefs['exchange'] ) ) continue;
					$buy_cred['gateway_prefs'][ $gateway_id ]['exchange'] = array(
						$type_set => $prefs['exchange']
					);
				}
				
				$buy_cred['active'] = array();
				
				mycred_update_option( 'mycred_pref_buycreds', $buy_cred );
				add_option( 'mycred_buycred_reset', 'true' );
			}
		}
		else {
			delete_option( 'mycred_buycred_reset' );
		}

		// Update complted
		update_option( 'mycred_version', myCRED_VERSION );
	}
endif;

?>