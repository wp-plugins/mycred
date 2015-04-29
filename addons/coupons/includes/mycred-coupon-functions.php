<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Create New Coupon
 * Creates a new myCRED coupon post.
 * @filter mycred_create_new_coupon_post
 * @filter mycred_create_new_coupon
 * @returns false if data is missing, post ID on success or wp_error / 0 if 
 * post creation failed.
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_create_new_coupon' ) ) :
	function mycred_create_new_coupon( $data = array() ) {
		// Required data is missing
		if ( empty( $data ) ) return false;

		// Apply defaults
		extract( shortcode_atts( array(
			'code'        => mycred_get_unique_coupon_code(),
			'value'       => 0,
			'global_max'  => 1,
			'user_max'    => 1,
			'min_balance' => 0,
			'max_balance' => 0,
			'expires'     => ''
		), $data ) );

		// Create Coupon Post
		$post_id = wp_insert_post( apply_filters( 'mycred_create_new_coupon_post', array(
			'post_type'   => 'mycred_coupon',
			'post_title'  => $code,
			'post_status' => 'publish'
		), $data ) );

		// Error
		if ( $post_id !== 0 && ! is_wp_error( $post_id ) ) {

			// Save Coupon Details
			add_post_meta( $post_id, 'value',  $value );
			add_post_meta( $post_id, 'global', $global_max );
			add_post_meta( $post_id, 'user',   $user_max );
			add_post_meta( $post_id, 'min',    $min_balance );
			add_post_meta( $post_id, 'max',    $max_balance );
			if ( ! empty( $expires ) )
				add_post_meta( $post_id, 'expires', $expires );

		}

		return apply_filters( 'mycred_create_new_coupon', $post_id, $data );
	}
endif;

/**
 * Get Unique Coupon Code
 * Generates a unique 12 character alphanumeric coupon code.
 * @filter mycred_get_unique_coupon_code
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_unique_coupon_code' ) ) :
	function mycred_get_unique_coupon_code() {
		global $wpdb;

		do {

			$id = strtoupper( wp_generate_password( 12, false, false ) );
			$query = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s;", $id, 'mycred_coupon' ) );

		} while ( ! empty( $query ) );

		return apply_filters( 'mycred_get_unique_coupon_code', $id );
	}
endif;

/**
 * Get Coupon Post
 * @filter mycred_get_coupon_by_code
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_coupon_post' ) ) :
	function mycred_get_coupon_post( $code = '' ) {
		return apply_filters( 'mycred_get_coupon_by_code', get_page_by_title( strtoupper( $code ), 'OBJECT', 'mycred_coupon' ), $code );
	}
endif;

/**
 * Use Coupon
 * Will attempt to use a given coupon and award it's value
 * to a given user. Requires you to provide a log entry template.
 * @action mycred_use_coupon
 * @since 1.4
 * @version 1.0.2
 */
if ( ! function_exists( 'mycred_use_coupon' ) ) :
	function mycred_use_coupon( $code = '', $user_id = 0 ) {

		// Missing required information
		if ( empty( $code ) || $user_id === 0 ) return 'missing';

		// Get coupon by code (post title)
		$coupon = mycred_get_coupon_post( $code );

		// Coupon does not exist
		if ( $coupon === NULL )
			return 'missing';

		// Check Expiration
		$now = current_time( 'timestamp' );
		$expires = mycred_get_coupon_expire_date( $coupon->ID, true );
		if ( ! empty( $expires ) && $expires !== 0 && $expires <= $now ) {
			wp_trash_post( $coupon->ID );
			return 'expired';
		}

		// Get Global Count
		$global_count = mycred_get_global_coupon_count( $coupon->ID );

		// We start with enforcing the global count
		$global_max = mycred_get_coupon_global_max( $coupon->ID );
		if ( $global_count >= $global_max ) {
			wp_trash_post( $coupon->ID );
			return 'expired';
		}

		$type = get_post_meta( $coupon->ID, 'type', true );
		if ( $type == '' )
			$type = 'mycred_default';

		$mycred = mycred( $type );

		// Get User max
		$user_count = mycred_get_users_coupon_count( $code, $user_id );

		// Next we enforce the user max
		$user_max = mycred_get_coupon_user_max( $coupon->ID );
		if ( $user_count >= $user_max )
			return 'max';

		// Min balance requirement
		$users_balance = $mycred->get_users_cred( $user_id, $type );
		$min_balance = mycred_get_coupon_min_balance( $coupon->ID );
		if ( $min_balance > $mycred->zero() && $users_balance < $min_balance )
			return 'min_balance';

		// Max balance requirement
		$max_balance = mycred_get_coupon_max_balance( $coupon->ID );
		if ( $max_balance > $mycred->zero() && $users_balance >= $max_balance )
			return 'max_balance';

		// Ready to use coupon!
		$value = mycred_get_coupon_value( $coupon->ID );
		$value = $mycred->number( $value );

		// Get Coupon log template
		if ( ! isset( $mycred->core['coupons']['log'] ) )
			$mycred->core['coupons']['log'] = 'Coupon redemption';

		// Apply Coupon
		$mycred->add_creds(
			'coupon',
			$user_id,
			$value,
			$mycred->core['coupons']['log'],
			$coupon->ID,
			$code,
			$type
		);

		do_action( 'mycred_use_coupon', $user_id, $coupon );

		// Increment global counter
		$global_count ++;
		update_post_meta( $coupon->ID, 'global_count', $global_count );

		// If the updated counter reaches the max, trash the coupon now
		if ( $global_count >= $global_max )
			wp_trash_post( $coupon->ID );

		return $mycred->number( $users_balance+$value );

	}
endif;

/**
 * Get Users Coupon Count
 * Counts the number of times a user has used a given coupon.
 * @filter mycred_get_users_coupon_count
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_users_coupon_count' ) ) :
	function mycred_get_users_coupon_count( $code = '', $user_id = '' ) {
		global $wpdb, $mycred;

		// Count how many times a given user has used a given coupon
		$result = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT( * ) 
			FROM {$mycred->log_table} 
			WHERE ref = %s 
				AND user_id = %d
				AND data = %s;", 'coupon', $user_id, $code ) );

		return apply_filters( 'mycred_get_users_coupon_count', $result, $code, $user_id );
	}
endif;

/**
 * Get Coupons Global Count
 * @filter mycred_get_global_coupon_count
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_global_coupon_count' ) ) :
	function mycred_get_global_coupon_count( $post_id = 0 ) {
		$count = get_post_meta( $post_id, 'global_count', true );
		if ( empty( $count ) )
			$count = 0;

		return apply_filters( 'mycred_get_global_coupon_count', $count, $post_id );
	}
endif;

/**
 * Get Coupon Value
 * @filter mycred_coupon_value
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_coupon_value' ) ) :
	function mycred_get_coupon_value( $post_id = 0 ) {
		return apply_filters( 'mycred_coupon_value', get_post_meta( $post_id, 'value', true ), $post_id );
	}
endif;

/**
 * Get Coupon Expire Date
 * @filter mycred_coupon_max_balance
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_coupon_expire_date' ) ) :
	function mycred_get_coupon_expire_date( $post_id = 0, $unix = false ) {
		$date = get_post_meta( $post_id, 'expires', true );
		
		if ( ! empty( $date ) && $unix )
			$date = strtotime( $date );
		
		return apply_filters( 'mycred_coupon_expires', $date, $post_id, $unix );
	}
endif;

/**
 * Get Coupon User Max
 * The maximum number a user can use this coupon.
 * @filter mycred_coupon_user_max
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_coupon_user_max' ) ) :
	function mycred_get_coupon_user_max( $post_id = 0 ) {
		return apply_filters( 'mycred_coupon_user_max', get_post_meta( $post_id, 'user', true ), $post_id );
	}
endif;

/**
 * Get Coupons Global Max
 * @filter mycred_coupon_global_max
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_coupon_global_max' ) ) :
	function mycred_get_coupon_global_max( $post_id = 0 ) {
		return apply_filters( 'mycred_coupon_global_max', get_post_meta( $post_id, 'global', true ), $post_id );
	}
endif;

/**
 * Get Coupons Minimum Balance Requirement
 * @filter mycred_coupon_min_balance
 * @since 1.4
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_get_coupon_min_balance' ) ) :
	function mycred_get_coupon_min_balance( $post_id = 0 ) {
		return apply_filters( 'mycred_coupon_min_balance', get_post_meta( $post_id, 'min_balance', true ), $post_id );
	}
endif;

/**
 * Get Coupons Maximum Balance Requirement
 * @filter mycred_coupon_max_balance
 * @since 1.4
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_get_coupon_max_balance' ) ) :
	function mycred_get_coupon_max_balance( $post_id = 0 ) {
		return apply_filters( 'mycred_coupon_max_balance', get_post_meta( $post_id, 'max_balance', true ), $post_id );
	}
endif;
?>