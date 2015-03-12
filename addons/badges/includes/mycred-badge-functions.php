<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Get Badge Requirements
 * Returns the badge requirements as an array.
 * @since 1.5
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_get_badge_requirements' ) ) :
	function mycred_get_badge_requirements( $post_id = NULL, $editor = false )
	{
		$req = (array) get_post_meta( $post_id, 'badge_requirements', true );
		if ( $editor && empty( $req ) )
			$req = array(
				0 => array(
					'type'      => 'mycred_default',
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
 * @version 1.1.1
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
			$req_count = count( $requirements );

			$output = array();
			foreach ( $requirements as $level => $needs ) {

				if ( $needs['type'] == '' )
					$needs['type'] = 'mycred_default';

				if ( ! isset( $types[ $needs['type'] ] ) )
					continue;

				$mycred = mycred( $needs['type'] );
				$point_type = $mycred->plural();

				if ( ! isset( $references[ $needs['reference'] ] ) )
					$ref = '-';
				else
					$ref = $references[ $needs['reference'] ];

				$level_label = '';
				if ( $req_count > 1 )
					$level_label = '<strong>' . sprintf( __( 'Level %s', 'mycred' ), ( $level + 1 ) ) . '</strong>';

				if ( $needs['by'] == 'count' )
					$output[] = sprintf( _x( '%s for %s %s - %s', '"Points" for "reference" "x time(s)" - Level', 'mycred' ), $point_type, $ref, sprintf( _n( '1 time', '%d times', $needs['amount'], 'mycred' ), $needs['amount'] ), $level_label );
				else
					$output[] = sprintf( _x( '%s for %s in total', '"x points" for "reference" in total', 'mycred' ), $mycred->format_creds( $needs['amount'] ), $ref );

			}
			$reply = implode( $sep, $output );

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
 * @version 1.2
 */
if ( ! function_exists( 'mycred_ref_has_badge' ) ) :
	function mycred_ref_has_badge( $reference = NULL, $request = NULL )
	{
		if ( $reference === NULL ) return false;

		global $wpdb;

		$badge_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT post_id 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = %s 
			AND meta_value LIKE %s;", 'badge_requirements', '%"' . $reference . '"%' ) );

		if ( empty( $badge_ids ) )
			$badge_ids = false;

		return apply_filters( 'mycred_ref_has_badge', $badge_ids, $reference, $request );
	}
endif;

/**
 * Check if User Gets Badge
 * Checks if a given user has earned one or multiple badges.
 * @since 1.5
 * @version 1.2.2
 */
if ( ! function_exists( 'mycred_check_if_user_gets_badge' ) ) :
	function mycred_check_if_user_gets_badge( $user_id = NULL, $badge_ids = array(), $save = true )
	{
		if ( $user_id === NULL || empty( $badge_ids ) ) return;

		global $wpdb;

		$ids = array();
		foreach ( $badge_ids as $badge_id ) {

			$level = false;
			$requirements = mycred_get_badge_requirements( $badge_id );
			foreach ( $requirements as $req_level => $needs ) {

				if ( $needs['type'] == '' )
					$needs['type'] = 'mycred_default';

				$mycred = mycred( $needs['type'] );

				// Count occurences
				if ( $needs['by'] == 'count' ) {
					$select = 'COUNT( * )';
					$amount = absint( $needs['amount'] );
				}

				// Sum up points
				else {
					$select = 'SUM( creds )';
					$amount = $mycred->number( $needs['amount'] );
				}

				$result = $wpdb->get_var( apply_filters( 'mycred_if_user_gets_badge_sql', $wpdb->prepare( "
					SELECT {$select} 
					FROM {$mycred->log_table} 
					WHERE user_id = %d 
						AND ctype = %s 
						AND ref = %s;", $user_id, $needs['type'], $needs['reference'] ), $user_id, $badge_id, $req_level, $needs ) );

				if ( $result === NULL ) $result = 0;

				if ( $needs['by'] != 'count' )
					$result = $mycred->number( $result );
				else
					$result = absint( $result );

				$level = NULL;
				if ( $result >= $amount )
					$level = absint( $req_level );

				$current = mycred_get_user_meta( $user_id, 'mycred_badge' . $badge_id, '', true );
				if ( $current == '' ) $current = -1;

				// If a level has been reached assign it now unless the user has this level already
				if ( $level !== NULL && $current < $level ) {

					if ( $save )
						mycred_update_user_meta( $user_id, 'mycred_badge' . $badge_id, '', apply_filters( 'mycred_badge_user_value', $level, $user_id, $badge_id ) );

					$ids[ $badge_id ] = $level;

				}

			}

		}

		return $ids;

	}
endif;

/**
 * Get Users Badges
 * Returns the badge post IDs that a given user currently holds.
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_users_badges' ) ) :
	function mycred_get_users_badges( $user_id = NULL )
	{
		if ( $user_id === NULL ) return '';

		global $wpdb;

		$query = $wpdb->get_results( $wpdb->prepare( "
			SELECT * 
			FROM {$wpdb->usermeta} 
			WHERE user_id = %d 
			AND meta_key LIKE %s", $user_id, 'mycred_badge%' ) );

		$badge_ids = array();
		if ( ! empty( $query ) ) {
			foreach ( $query as $badge ) {
				$badge_id = substr( $badge->meta_key, 12 );
				if ( $badge_id == '' ) continue;
				
				$badge_id = (int) $badge_id;
				if ( array_key_exists( $badge_id, $badge_ids ) ) continue;

				$requirements = mycred_get_badge_requirements( $badge_id );
				if ( count( $requirements ) > 1 )
					$badge_ids[ $badge_id ] = $badge->meta_value;
				else
					$badge_ids[ $badge_id ] = 0;
			}
		}

		return apply_filters( 'mycred_get_users_badges', $badge_ids, $user_id );
	}
endif;

/**
 * Display Users Badges
 * Will echo all badge images a given user has earned.
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'mycred_display_users_badges' ) ) :
	function mycred_display_users_badges( $user_id = NULL )
	{
		if ( $user_id === NULL || $user_id == 0 ) return;

		$users_badges = mycred_get_users_badges( $user_id );
			
		echo '<div id="mycred-users-badges">';

		do_action( 'mycred_before_users_badges', $user_id, $users_badges );

		if ( ! empty( $users_badges ) ) {

			foreach ( $users_badges as $badge_id => $level ) {

				$level_image = get_post_meta( $badge_id, 'level_image' . $level, true );
				if ( $level_image == '' )
					$level_image = get_post_meta( $badge_id, 'main_image', true );

				echo '<img src="' . $level_image . '" class="mycred-badge earned badge-id-' . $badge_id . ' level-' . $level . '" alt="' . get_the_title( $badge_id ) . '" title="' . get_the_title( $badge_id ) . '" />';
			}

		}

		do_action( 'mycred_after_users_badges', $user_id, $users_badges );

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