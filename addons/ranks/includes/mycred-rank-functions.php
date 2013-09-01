<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * Have Ranks
 * Checks if there are any registered rank.
 * @returns (bool) true or false
 * @since 1.1
 * @version 1.2
 */
if ( !function_exists( 'mycred_have_ranks' ) ) {
	function mycred_have_ranks() {
		$data = get_transient( 'mycred_ranks' );

		if ( $data == 0 ) {
			delete_transient( 'mycred_ranks' );
			$data = false;
		}
		
		if ( $data === false ) {
			global $wpdb;
			$data = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mycred_rank';" );
			set_transient( 'mycred_ranks', $data, DAY_IN_SECONDS * 7 );
		}

		if ( $data > 0 ) return true;
		return false;
	}
}
/**
 * Get Ranks
 * Retreaves a given rank.
 * @param $rank_title (string) the rank title (case sensitive)
 * @param $format (string) optional output type: OBJECT, ARRAY_N or ARRAY_A
 * @uses get_page_by_title()
 * @returns empty string if title is missing, NULL if rank is not found else the output type
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_rank' ) ) {
	function mycred_get_rank( $rank_title = '', $format = 'OBJECT' ) {
		if ( empty( $rank_title ) ) return $rank_title;
		return get_page_by_title( $rank_title, $format, 'mycred_rank' );
	}
}
/**
 * Get Users Rank
 * Retreaves the users current saved rank or if rank is missing
 * finds the appropriate rank and saves it.
 * @param $user_id (int) required user id to check
 * @param $return (string) post detail to return, defaults to post_title
 * @param $logo_size (string) if $return is set to 'logo', the size of the logo to return
 * @param $attr (array) if $return is set to 'logo', optional logo image attributes
 * @uses mycred_find_users_rank()
 * @uses get_the_title()
 * @returns rank (string) or empty string on fail
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_users_rank' ) ) {
	function mycred_get_users_rank( $user_id = NULL, $return = 'post_title', $logo_size = 'post-thumbnail', $attr = NULL ) {
		if ( $user_id === NULL ) return '';
		$rank_id = get_user_meta( $user_id, 'mycred_rank', true );
		if ( empty( $rank ) ) {
			mycred_find_users_rank( $user_id, true );
			$rank_id = get_user_meta( $user_id, 'mycred_rank', true );
		}
		
		if ( $return == 'logo' )
			return mycred_get_rank_logo( $rank_id, $logo_size, $attr );

		return get_post( $rank_id )->$return;
	}
}
/**
 * Find Users Rank
 * Compares the given users points balance with existing ranks to determain
 * where the user fits in.
 * @param $user_id (int) required user id
 * @param $save (bool) option to save the rank to the given users meta data
 * @param $amount (int|float) optional amount to add to current balance
 * @param $type (string) optional cred type to check
 * @uses WP_Query()
 * @uses mycred_have_ranks()
 * @uses update_user_meta()
 * @returns empty (string) on failure or the users rank
 * @since 1.1
 * @version 1.2
 */
if ( !function_exists( 'mycred_find_users_rank' ) ) {
	function mycred_find_users_rank( $user_id = NULL, $save = false, $amount = 0, $type = '' ) {
		$mycred = mycred_get_settings();
		
		// Check for exclusion
		if ( $mycred->exclude_user( $user_id ) ) return '';

		// Get users balance
		if ( $user_id === NULL )
			$balance = $mycred->get_users_cred( get_current_user_id(), $type );
		else
			$balance = $mycred->get_users_cred( $user_id, $type );

		// The new balance before it is saved
		$balance = $balance+$amount;
		// Rank query arguments
		$args = array(
			'post_type'      => 'mycred_rank', // rank type
			'post_status'    => 'publish',     // in case we have some draft ranks
			'posts_per_page' => 1,             // we should recieve just one match but just in case
			'meta_query'     => array(
				array(
					'key'     => 'mycred_rank_min',
					'value'   => $balance,
					'compare' => '<=',
					'type'    => 'NUMERIC'
				),
				array(
					'key'     => 'mycred_rank_max',
					'value'   => $balance,
					'compare' => '>=',
					'type'    => 'NUMERIC'
				)
			)
		);
		$rank = new WP_Query( $args );
		
		// Found a matching rank
		if ( $rank->have_posts() ) {
			$rank_title = $rank->post->post_title;
			$rank_id = $rank->post->ID;
		}
		// No matching rank found
		else {
			// Reset
			wp_reset_postdata();
			// Check if there is any ranks (should be one)
			if ( mycred_have_ranks() ) {
				// Get this rank
				$new_args = array(
					'post_type'      => 'mycred_rank', // rank type
					'post_status'    => 'publish'
				);
				$default = new WP_Query( $new_args );
				if ( $default->have_posts() ) {
					$rank_title = $default->post->post_title;
					$rank_id = $default->post->ID;
				}
				else {
					$rank_title = __( 'No Rank', 'mycred' );
					$rank_id = '';
				}
			}
			// No ranks at all
			else {
				$rank_title = __( 'No Rank', 'mycred' );
				$rank_id = '';
			}
		}
		
		// Save if requested
		if ( $save )
			update_user_meta( $user_id, 'mycred_rank', $rank_id );

		// Let others play
		do_action( 'mycred_find_users_rank', $user_id, $rank_id );

		// Reset & Return
		wp_reset_postdata();
		return $rank_title;
	}
}
/**
 * Get Rank ID from Title
 * Used to get the rank object based on the ranks title.
 * @param $title (string) required rank title
 * @uses mycred_get_rank()
 * @returns empty (string) if title is missing, NULL if rank is not found else (string) the rank.
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_rank_id_from_title' ) ) {
	function mycred_get_rank_id_from_title( $title ) {
		$rank = mycred_get_rank( $title );
		if ( $rank === NULL || empty( $rank ) ) return '';
		return $rank->ID;
	}
}
/**
 * Get My Rank
 * Returns the current users rank
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_my_rank' ) ) {
	function mycred_get_my_rank() {
		return mycred_get_users_rank( get_current_user_id() );
	}
}
/**
 * Get Ranks
 * Returns an associative array of ranks with the given status.
 * @param $status (string) post status, defaults to 'publish'
 * @param $number (int|string) number of ranks to return, defaults to all
 * @param $order (string) option to return ranks ordered Ascending or Descending
 * @uses WP_Query()
 * @returns (array) empty if no ranks are found or associative array with post ID as key and title as value
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_ranks' ) ) {
	function mycred_get_ranks( $status = 'publish', $number = '-1', $order = 'DESC' ) {
		$args = array(
			'post_type'      => 'mycred_rank',
			'post_status'    => $status,
			'posts_per_page' => $number,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'mycred_rank_min',
			'order'          => $order
		);
		$ranks = new WP_Query( $args );
		$all_ranks = array();
		if ( $ranks->have_posts() ) {
			while ( $ranks->have_posts() ) {
				$ranks->the_post();
				$all_ranks[get_the_ID()] = $ranks->post;
			}
		}
		
		wp_reset_postdata();
		return $all_ranks;
	}
}
/**
 * Get Users of Rank
 * Returns an associative array of user IDs and display names of users for a given
 * rank.
 * @param $rank (int|string) either a rank id or rank name
 * @param $number (int) number of users to return
 * @uses mycred_get_rank_id_from_title()
 * @uses get_users()
 * @returns (array) empty if no users were found or associative array with user ID as key and display name as value
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_users_of_rank' ) ) {
	function mycred_get_users_of_rank( $rank, $number = NULL, $order = 'DESC' ) {
		if ( !is_numeric( $rank ) )
			$rank = mycred_get_rank_id_from_title( $rank );
		
		if ( $rank === NULL ) return '';

		global $wpdb;
		$sql = "SELECT u.ID FROM $wpdb->users u INNER JOIN $wpdb->usermeta m ON (u.ID = m.user_id) INNER JOIN $wpdb->usermeta c ON (u.ID = c.user_id) WHERE 1=1 AND ( m.meta_key = %s AND m.meta_value = %d AND c.meta_key = %s ) ORDER BY c.meta_value+0";
		
		// Order
		if ( $order == 'ASC' || $order == 'DESC' )
			$sql .= ' ' . trim( $order );
		else
			$sql .= ' DESC';

		// Limit
		if ( $number !== NULL )
			$sql .= ' LIMIT 0,' . abs( $number );

		// Run query
		$users = $wpdb->get_results( $wpdb->prepare( $sql, 'mycred_rank', $rank, 'mycred_default' ) );
		
		$rank_users = array();
		if ( $users ) {
			$mycred = mycred_get_settings();
			foreach ( $users as $user ) {
				// make sure user is not excluded
				if ( $mycred->exclude_user( $user->ID ) ) continue;
				$rank_users[] = $user->ID;
			}
		}
		
		return $rank_users;
	}
}
/**
 * Rank Has Logo
 * Checks if a given rank has a logo.
 * @param $rank_id (int|string) either the rank id or the rank title
 * @uses mycred_get_rank_id_from_title()
 * @uses has_post_thumbnail()
 * @returns (bool) true or false
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_rank_has_logo' ) ) {
	function mycred_rank_has_logo( $rank_id ) {
		if ( !is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		if ( has_post_thumbnail( $rank_id ) ) return true;
		return false;
	}
}
/**
 * Get Rank Logo
 * Returns the given ranks logo.
 * @param $rank_id (int|string) either the rank id or the rank title
 * @param $size (string|array) see http://codex.wordpress.org/Function_Reference/get_the_post_thumbnail
 * @param $attr (string|array) see http://codex.wordpress.org/Function_Reference/get_the_post_thumbnail
 * @uses mycred_get_rank_id_from_title()
 * @uses mycred_rank_has_logo()
 * @uses get_the_post_thumbnail()
 * @returns empty string if rank does not that logo or the HTML IMG element with given size and attribute
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_rank_logo' ) ) {
	function mycred_get_rank_logo( $rank_id, $size = 'post-thumbnail', $attr = NULL ) {
		if ( !is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		if ( !mycred_rank_has_logo( $rank_id ) ) return '';
		
		if ( is_numeric( $size ) )
			$size = array( $size, $size );

		return get_the_post_thumbnail( $rank_id, $size, $attr );
	}
}
?>