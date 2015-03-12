<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * User Can Transfer
 * @see http://mycred.me/functions/mycred_user_can_transfer/
 * @param $user_id (int) requred user id
 * @param $amount (int) optional amount to check against balance
 * @returns true if no limit is set, 'limit' (string) if user is over limit else the amount of creds left
 * @filter 'mycred_user_can_transfer'
 * @filter 'mycred_transfer_limit'
 * @filter 'mycred_transfer_acc_limit'
 * @since 0.1
 * @version 1.3.1
 */
if ( ! function_exists( 'mycred_user_can_transfer' ) ) :
	function mycred_user_can_transfer( $user_id = NULL, $amount = NULL, $type = 'mycred_default', $ref = NULL ) {

		if ( $user_id === NULL ) $user_id = get_current_user_id();

		if ( $ref === NULL )
			$ref = 'mycred_transactions';

		// Grab Settings (from main type where the settings are saved)
		$mycred = mycred();
		$pref = $mycred->transfers;
		$zero = $mycred->zero();

		// Get users balance
		$balance = $mycred->get_users_cred( $user_id, $type );

		// Get Transfer Max
		$max = apply_filters( 'mycred_transfer_limit', $mycred->number( $pref['limit']['amount'] ), $user_id, $amount, $pref, $mycred );

		// If an amount is given, deduct this amount to see if the transaction
		// brings us over the account limit
		if ( $amount !== NULL )
			$balance = $mycred->number( $balance-$amount );

		// Account Limit
		// The lowest amount a user can have on their account. By default, this
		// is zero. But you can override this via the mycred_transfer_acc_limit hook.
		$account_limit = $mycred->number( apply_filters( 'mycred_transfer_acc_limit', $zero, $type, $user_id, $ref ) );

		// Check if users balance is below the account limit
		if ( $balance < $account_limit ) return 'low';

		// If there are no limits, return the current balance
		if ( $pref['limit']['limit'] == 'none' ) return $balance;

		// Else we have a limit to impose
		$now = current_time( 'timestamp' );
		$max = $mycred->number( $pref['limit']['amount'] );

		// Daily limit
		if ( $pref['limit']['limit'] == 'daily' )
			$total = mycred_get_total_by_time( 'today', 'now', $ref, $user_id, $type );

		// Weekly limit
		elseif ( $pref['limit']['limit'] == 'weekly' ) {
			$this_week = mktime( 0, 0, 0, date( 'n', $now ), date( 'j', $now ) - date( 'n', $now ) + 1 );
			$total = mycred_get_total_by_time( $this_week, 'now', $ref, $user_id, $type );
		}

		// Custom limits will need to return the result
		// here and now. Accepted answers are 'limit', 'low' or the amount left on limit.
		else {
			return apply_filters( 'mycred_user_can_transfer', 'limit', $user_id, $amount, $pref, $mycred );
		}

		// We are adding up point deducations.
		$total = abs( $total );

		if ( $amount !== NULL ) {

			$total = $mycred->number( $total + $amount );

			// Transfer limit reached
			if ( $total > $max ) return 'limit';

		}

		else {

			// Transfer limit reached
			if ( $total >= $max ) return 'limit';

		}

		// Return whats remaining of limit
		return $mycred->number( $max - $total );

	}
endif;

/**
 * Get Users Transfer History
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_users_transfer_history' ) ) :
	function mycred_get_users_transfer_history( $user_id, $type = 'mycred_default', $key = NULL ) {

		if ( $key === NULL )
			$key = 'mycred_transactions';

		if ( $type != 'mycred_default' && $type != '' )
			$key .= '_' . $type;

		$default = array(
			'frame'  => '',
			'amount' => 0
		);
		return mycred_apply_defaults( $default, mycred_get_user_meta( $user_id, $key, '', true ) );

	}
endif;

/**
 * Update Users Transfer History
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_update_users_transfer_history' ) ) :
	function mycred_update_users_transfer_history( $user_id, $history, $type = 'mycred_default', $key = NULL ) {

		if ( $key === NULL )
			$key = 'mycred_transactions';

		if ( $type != 'mycred_default' && $type != '' )
			$key .= '_' . $type;

		// Get current history
		$current = mycred_get_users_transfer_history( $user_id, $type, $key );

		// Reset
		if ( $history === true )
			$new_history = array(
				'frame'  => '',
				'amount' => 0
			);

		// Update
		else $new_history = mycred_apply_defaults( $current, $history );

		mycred_update_user_meta( $user_id, $key, '', $new_history );

	}
endif;

?>