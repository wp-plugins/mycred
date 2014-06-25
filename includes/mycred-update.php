<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED 1.4 Update
 * Updated existing myCRED installations to 1.4
 * @since 1.4
 * @version 1.1
 */
if ( ! function_exists( 'mycred_update_to_onefour' ) ) :
	add_action( 'mycred_reactivation', 'mycred_update_to_onefour' );
	function mycred_update_to_onefour() {

		// Clean up after the 1.4.6 Email Notice bug
		$cron = get_option( 'cron' );
		if ( ! empty( $cron ) ) {
			foreach ( $cron as $time => $job ) {
				if ( isset( $job['mycred_send_email_notices'] ) )
					unset( $cron[ $time ] );
					
			}
			update_option( 'cron', $cron );
		}

		// Check if we should update
		$version = get_option( 'mycred_version', myCRED_VERSION );
		if ( version_compare( $version, '1.4', '>=' ) ) {
			delete_option( 'mycred_update_req_settings' );
			delete_option( 'mycred_update_req_hooks' );
			return;
		}

		// Rankings are renamed to Leaderboard
		delete_transient( 'mycred_default_leaderboard' );

		// Remove BuddyPress and Import Addons.
		$addons = get_option( 'mycred_pref_addons' );
		$new_active = array();
		foreach ( (array) $addons['active'] as $active ) {
			if ( in_array( $active, array( 'import', 'buddypress' ) ) ) continue;
			$new_active[] = $active;
		}
		$addons['active'] = array_unique( $new_active );

		mycred_update_option( 'mycred_pref_addons', $addons );

		// Update complted
		update_option( 'mycred_version', myCRED_VERSION );
		
		update_option( 'mycred_update_req_settings', time() );
		update_option( 'mycred_update_req_hooks', time() );
	}
endif;

?>