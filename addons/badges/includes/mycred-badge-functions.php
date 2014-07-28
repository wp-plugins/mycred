<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Get Badge Requirements
 * Returns the badge requirements as an array.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_badge_requirements' ) ) :
	function mycred_get_badge_requirements( $post_id = NULL, $editor = false )
	{
		$req = (array) get_post_meta( $post_id, 'badge_requirements', true );
		if ( $editor && empty( $req ) )
			$req = array(
				0 => array(
					'type'      => '',
					'reference' => '',
					'amount'    => '',
					'by'        => ''
				)
			);

		return apply_filters( 'mycred_badge_requirements', $req, $editor );
	}
endif;

/**
 * Display Badge Requirements
 * Returns the badge requirements as a string in a readable format.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_display_badge_requirement' ) ) :
	function mycred_display_badge_requirements( $post_id = NULL, $sep = '<br />' )
	{
		$requirements = mycred_get_badge_requirements( $post_id );
		if ( empty( $requirements ) ) {

			$reply = '-';

		}
		else {

			$types = mycred_get_types();
			$references = mycred_get_all_references();

			$output = array();
			foreach ( $requirements as $row => $needs ) {
				if ( ! isset( $types[ $needs['type'] ] ) )
					$point_type = '-';
				else
					$point_type = $types[ $needs['type'] ];

				if ( ! isset( $references[ $needs['reference'] ] ) )
					$ref = '-';
				else
					$ref = $references[ $needs['reference'] ];

				if ( $needs['by'] == 'count' )
					$output[] = $point_type . ' ' . __( 'for', 'mycred' ) . ' ' . $ref . ' ' . $needs['amount'] . ' ' . __( 'time(s)', 'mycred' );
				else
					$output[] = $needs['amount'] . ' ' . $point_type . ' ' . __( 'for', 'mycred' ) . ' ' . $ref . ' ' . __( 'in total', 'mycred' );

			}
			$reply = implode( $sep, $output ) . '.';

		}

		return apply_filters( 'mycred_badge_display_requirements', $reply, $post_id, $sep );
	}
endif;

/**
 * Count Users with Badge
 * Counts the number of users that has the given badge.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_count_users_with_badge' ) ) :
	function mycred_count_users_with_badge( $post_id = NULL )
	{
		global $wpdb;

		$key = 'mycred_badge' . $post_id;
		$count = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT( DISTINCT user_id ) 
			FROM {$wpdb->usermeta} 
			WHERE meta_key = %s", $key ) );

		return apply_filters( 'mycred_count_users_with_badge', $count, $post_id );
	}
endif;

/**
 * Count Users without Badge
 * Counts the number of users that does not have a given badge.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_count_users_without_badge' ) ) :
	function mycred_count_users_without_badge( $post_id = NULL )
	{
		global $wpdb;

		$key = 'mycred_badge' . $post_id;
		$count = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT( DISTINCT user_id ) 
			FROM {$wpdb->usermeta} 
			WHERE meta_key != %s", $key ) );

		return apply_filters( 'mycred_count_users_without_badge', $count, $post_id );
	}
endif;

/**
 * Reference Has Badge
 * Checks if a given reference has a badge associated with it.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_ref_has_badge' ) ) :
	function mycred_ref_has_badge( $reference = '' )
	{
		if ( $reference == '' ) return false;

		global $wpdb;

		$badge_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT post_id 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = %s 
			AND meta_value LIKE %s;", 'badge_requirements', '%' . $reference . '%' ) );

		if ( empty( $badge_ids ) )
			$badge_ids = false;

		return apply_filters( 'mycred_ref_has_badge', $badge_ids, $reference );
	}
endif;

/**
 * Check if User Gets Badge
 * Checks if a given user has earned one or multiple badges.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_check_if_user_gets_badge' ) ) :
	function mycred_check_if_user_gets_badge( $user_id = NULL, $request = array(), $badge_ids = array() )
	{
		if ( $user_id === NULL || empty( $badge_ids ) ) return;

		global $wpdb;

		foreach ( $badge_ids as $badge_id ) {

			// See if user already has badge
			if ( mycred_get_user_meta( $user_id, 'mycred_badge' . $badge_id, '', true ) != '' ) continue;

			$requirements = mycred_get_badge_requirements( $badge_id );
			$needs = $requirements[0];

			$mycred = mycred( $needs['type'] );
			$mycred_log = $mycred->log_table;

			if ( $needs['by'] == 'count' ) {
				$select = 'COUNT( * )';
				$amount = $needs['amount'];
			}
			else {
				$select = 'SUM( creds )';
				$amount = $mycred->number( $needs['amount'] );
			}

			$result = $wpdb->get_var( $wpdb->prepare( "
			SELECT {$select} 
			FROM {$mycred_log} 
			WHERE user_id = %d 
				AND ctype = %s 
				AND ref = %s;", $user_id, $needs['type'], $needs['reference'] ) );

			// If this function is used by the mycred_add filter, we need to take into
			// account the instance that we are currently being hooked into as the log entry
			// will be added after this code has executed.

			// In case we sum up, add the points the user will gain to the result
			if ( ! isset( $request['done'] ) && $needs['by'] == 'sum' )
				$result = $result + $request['amount'];

			// Else if we add up, increment the count by the missing 1 entry.
			elseif ( ! isset( $request['done'] ) && $needs['by'] == 'count' )
				$result = $result + 1;

			if ( $needs['by'] != 'count' )
				$result = $mycred->number( $result );

			// Got it!
			if ( $result >= $amount ) {
				mycred_update_user_meta( $user_id, 'mycred_badge' . $badge_id, '', apply_filters( 'mycred_badge_user_value', 1, $user_id, $badge_id ) );
			}

		}
	}
endif;

/**
 * Get Users Badges
 * Returns the badge post IDs that a given user currently holds.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_users_badges' ) ) :
	function mycred_get_users_badges( $user_id = NULL )
	{
		if ( $user_id === NULL ) return '';

		global $wpdb;

		$query = $wpdb->get_col( $wpdb->prepare( "
			SELECT meta_key 
			FROM {$wpdb->usermeta} 
			WHERE user_id = %d 
			AND meta_key LIKE %s", $user_id, 'mycred_badge%' ) );

		$badge_ids = array();
		if ( ! empty( $query ) ) {
			foreach ( $query as $row => $badge ) {
				$badge_id = substr( $badge, 12 );
				if ( $badge_id == '' ) continue;
				$badge_ids[] = absint( $badge_id );
			}
			$badge_ids = array_unique( $badge_ids );
		}

		return apply_filters( 'mycred_get_users_badges', $badge_ids, $user_id );
	}
endif;

/**
 * Display Users Badges
 * Will echo all badge images a given user has earned.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_display_users_badges' ) ) :
	function mycred_display_users_badges( $user_id = NULL )
	{
		if ( $user_id === NULL || $user_id == 0 ) return;

		$users_badges = mycred_get_users_badges( $user_id );
			
		echo '<div id="mycred-users-badges">';

		if ( ! empty( $users_badges ) ) {

			foreach ( $users_badges as $badge_id )
				echo '<img src="' . get_post_meta( $badge_id, 'main_image', true ) . '" class="mycred-badge earned badge-id-' . $badge_id . '" alt="' . get_the_title( $badge_id ) . '" title="' . get_the_title( $badge_id ) . '" />';

		}

		echo '</div>';
	}
endif;

/**
 * Get Badge IDs
 * Returns all published badge post IDs.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_badge_ids' ) ) :
	function mycred_get_badge_ids()
	{
		global $wpdb;

		$badge_ids = $wpdb->get_col( "
			SELECT ID 
			FROM {$wpdb->posts} 
			WHERE post_type = 'mycred_badge' 
			AND post_status = 'publish';" );

		return apply_filters( 'mycred_get_badge_ids', $badge_ids );
	}
endif;

/**
 * Get Badges
 * Returns all badges with it's requirements and badge images.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_badges' ) ) :
	function mycred_get_badges()
	{
		global $wpdb;

		$badges = $wpdb->get_results( "
			SELECT posts.ID, posts.post_title, req.meta_value AS requires, def.meta_value AS default_img, main.meta_value AS main_img 
			FROM {$wpdb->posts} posts 
			INNER JOIN {$wpdb->postmeta} req 
				ON ( posts.ID = req.post_id ) 
			INNER JOIN {$wpdb->postmeta} def 
				ON ( posts.ID = def.post_id ) 
			INNER JOIN {$wpdb->postmeta} main 
				ON ( posts.ID = main.post_id ) 
			WHERE posts.post_type = 'mycred_badge' 
				AND posts.post_status = 'publish' 
				AND req.meta_key = 'badge_requirements' 
				AND def.meta_key = 'default_image' 
				AND main.meta_key = 'main_image'
			ORDER BY posts.post_date DESC;" );

		return apply_filters( 'mycred_get_badges', $badges );
	}
endif;
?>