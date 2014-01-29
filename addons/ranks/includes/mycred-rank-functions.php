<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Have Ranks
 * Checks if there are any rank posts.
 * @returns (bool) true or false
 * @since 1.1
 * @version 1.3
 */
if ( ! function_exists( 'mycred_have_ranks' ) ) {
	function mycred_have_ranks() {
		global $mycred_ranks, $wpdb;
		$mycred_ranks = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mycred_rank';" );
		
		$return = false;
		if ( $mycred_ranks > 0 )
			$return = true;

		return apply_filters( 'mycred_have_ranks', $return, $mycred_ranks );
	}
}

/**
 * Have Published Ranks
 * Checks if there are any published rank posts.
 * @returns (int) the number of published ranks found.
 * @since 1.3.2
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_published_ranks' ) ) {
	function mycred_get_published_ranks() {
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mycred_rank' AND post_status = 'publish';" );

		return apply_filters( 'mycred_get_published_ranks', $count );
	}
}

/**
 * Assign Ranks
 * Runs though all user balances and assigns each users their
 * appropriate ranks.
 * @returns (int) number of users effected by rank change or -1 if all users were effected.
 * @since 1.3.2
 * @version 1.1
 */
if ( ! function_exists( 'mycred_assign_ranks' ) ) {
	function mycred_assign_ranks() {
		global $mycred_ranks, $wpdb;

		$mycred = mycred_get_settings();

		// Get key
		if ( $mycred->rank['base'] == 'total' )
			$key = $mycred->get_cred_id() . '_total';
		else
			$key = $mycred->get_cred_id();

		do_action( 'mycred_assign_ranks_start' );

		// Check for published ranks
		$published_ranks = mycred_get_published_ranks();

		// Only one rank exists
		if ( $published_ranks == 1 ) {
			// Get this single rank
			$rank_id = $wpdb->get_var( "
				SELECT ID FROM {$wpdb->posts} 
				WHERE post_type = 'mycred_rank' AND post_status = 'publish';" );

			// Update all users rank to this single rank
			$wpdb->query( $wpdb->prepare( "
				UPDATE {$wpdb->usermeta} 
				SET meta_value = %d 
				WHERE meta_key = 'mycred_rank';", $rank_id ) );

			$wpdb->flush();
			return 0-1;
		}
		// Multiple ranks exists
		elseif ( $published_ranks > 1 ) {
			/*
				Get all user balances
				$users = array(
					[0] => array(
						[ID]      => user id
						[balance] => current balance
					)
				);
			*/
			$users = $wpdb->get_results( $wpdb->prepare( "
SELECT user_id AS ID, meta_value AS balance 
FROM {$wpdb->usermeta} 
WHERE meta_key = %s;", $key ), 'ARRAY_A' );

			/*
				Get rank ids with each ranks min and max values
				$ranks = array(
					[0] => array(
						[ID]  => rank id
						[min] => mycred_rank_min value
						[max] => mycred_rank_max value
					)
				);
			*/
			$ranks = $wpdb->get_results( "
SELECT rank.ID AS ID, min.meta_value AS min, max.meta_value AS max 
FROM {$wpdb->posts} rank 
	INNER JOIN {$wpdb->postmeta} min 
		ON ( min.post_id = rank.ID AND min.meta_key = 'mycred_rank_min' )
	INNER JOIN {$wpdb->postmeta} max 
		ON ( max.post_id = rank.ID AND max.meta_key = 'mycred_rank_max' )
WHERE rank.post_type = 'mycred_rank' 
	AND rank.post_status = 'publish';", 'ARRAY_A' );

			$count = 0;
			foreach ( $users as $user ) {
				foreach ( $ranks as $rank ) {
					if ( $rank['min'] <= $user['balance'] && $rank['max'] >= $user['balance'] ) {
						update_user_meta( $user['ID'], 'mycred_rank', $rank['ID'] );
						$count = $count+1;
						break 1;
					}
				}
			}
			$wpdb->flush();

			unset( $users );
			unset( $ranks );
			
			return $count;
		}
		// No ranks exists
		else {
			// nothing to do when there are no ranks
			return 0;
		}

		do_action( 'mycred_assign_ranks_end' );
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
 * @version 1.2
 */
if ( ! function_exists( 'mycred_get_rank' ) ) {
	function mycred_get_rank( $rank_title = '', $format = 'OBJECT' ) {
		if ( empty( $rank_title ) ) return $rank_title;
		$rank = get_page_by_title( $rank_title, $format, 'mycred_rank' );

		return apply_filters( 'mycred_get_rank', $rank, $rank_title, $format );
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
 * @returns (string) rank object item requested or - if no ranks exists
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_users_rank' ) ) {
	function mycred_get_users_rank( $user_id = NULL, $return = 'post_title', $logo_size = 'post-thumbnail', $attr = NULL ) {
		// User ID is required
		if ( $user_id === NULL )
			return __( 'mycred_get_users_rank() : Missing required user id', 'mycred' );

		// Get users rank
		$rank_id = get_user_meta( $user_id, 'mycred_rank', true );

		// If empty, get the users rank now and save it
		if ( empty( $rank_id ) ) {
			$rank = mycred_find_users_rank( $user_id, true );
			$rank_id = mycred_get_rank_id_from_title( $rank );
		}

		$reply = __( 'no rank', 'mycred' );

		// Have rank
		if ( ! empty( $rank_id ) ) {

			// If we want to see the logo
			if ( $return == 'logo' )
				$reply = mycred_get_rank_logo( (int) $rank_id, $logo_size, $attr );
			
			// Else get the post object
			else {
				// Get rank post object
				$rank = get_post( (int) $rank_id );

				// If the requested detail exists, return it
				if ( isset( $rank->$return ) )
					$reply = $rank->$return;
			}

		}

		return apply_filters( 'mycred_get_users_rank', $reply, $user_id, $return, $logo_size );
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
 * @uses $wpdb
 * @returns (string) users rank title.
 * @since 1.1
 * @version 1.3
 */
if ( ! function_exists( 'mycred_find_users_rank' ) ) {
	function mycred_find_users_rank( $user_id = NULL, $save = false, $amount = 0, $type = '' ) {
		global $mycred_ranks, $wpdb;

		$mycred = mycred_get_settings();
		
		// Check for exclusion
		if ( $mycred->exclude_user( $user_id ) ) return;

		// In case user id is not set
		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		// In case the type is not set
		if ( empty( $type ) ) {
			switch ( $mycred->rank['base'] ) {
				case 'total' :
					$type = $mycred->get_cred_id() . '_total';
				break;
				
				default :
					$type = $mycred->get_cred_id();
				break;
			}
		}

		// Get the balance as get_user_meta() will return the previous value if it was just
		// changed.
		$balance = $wpdb->get_var( $wpdb->prepare( "
SELECT meta_value 
FROM {$wpdb->usermeta} 
WHERE user_id = %d 
	AND meta_key = %s;", $user_id, $type ) );

		// The new balance before it is saved
		$balance = $mycred->number( $balance+$amount );

		// Get Published ranks
		$mycred_ranks = mycred_get_published_ranks();
		
		$rank_id = 0;
		$rank_title = __( 'No rank', 'mycred' );

		// Only one rank exists
		if ( $mycred_ranks == 1 ) {
			// Get this rank
			$rank = $wpdb->get_row( "
SELECT ID, post_title 
FROM {$wpdb->posts} 
WHERE post_type = 'mycred_rank' 
	AND post_status = 'publish';" );
			
			$rank_id = $rank->ID;
			$rank_title = $rank->post_title;
			unset( $rank );
		}
		// Multiple ranks exists
		elseif ( $mycred_ranks > 1 ) {
			/*
				Get rank ids with each ranks min and max values
				$ranks = array(
					[0] => array(
						[ID]    => rank id
						[title] => rank title
						[min]   => mycred_rank_min value
						[max]   => mycred_rank_max value
					)
				);
			*/
			$ranks = $wpdb->get_results( "
SELECT rank.ID AS ID, rank.post_title AS title, min.meta_value AS min, max.meta_value AS max 
FROM {$wpdb->posts} rank
	INNER JOIN {$wpdb->postmeta} min 
		ON ( min.post_id = rank.ID AND min.meta_key = 'mycred_rank_min' )
	INNER JOIN {$wpdb->postmeta} max 
		ON ( max.post_id = rank.ID AND max.meta_key = 'mycred_rank_max' )
WHERE post_type = 'mycred_rank' 
	AND post_status = 'publish'
ORDER BY ID;", 'ARRAY_A' );
			
			arsort( $ranks );

			// Loop though each rank
			foreach ( $ranks as $rank ) {
				// If balance fits break with this ranks details
				if ( $balance >= $mycred->number( $rank['min'] ) && $balance <= $mycred->number( $rank['max'] ) ) {
					$rank_id = $rank['ID'];
					$rank_title = $rank['title'];
				}
			}
			unset( $ranks );
		}
		$wpdb->flush();

		// Let others play
		if ( $rank_id != 0 ) {
			if ( mycred_user_got_demoted( $user_id, $rank_id ) )
				do_action( 'mycred_user_got_demoted', $user_id, $rank_id );

			elseif ( mycred_user_got_promoted( $user_id, $rank_id ) )
				do_action( 'mycred_user_got_promoted', $user_id, $rank_id );
		
			else
				do_action( 'mycred_find_users_rank', $user_id, $rank_id );
		}

		// Save if requested
		if ( $save && $rank_id != 0 )
			update_user_meta( $user_id, 'mycred_rank', $rank_id );

		return apply_filters( 'mycred_find_users_rank', $rank_title, $rank_id, $user_id, $save, $amount, $type );
	}
}

/**
 * Get Rank ID from Title
 * Used to get the rank object based on the ranks title.
 * @param $title (string) required rank title
 * @uses mycred_get_rank()
 * @returns empty (string) if title is missing, NULL if rank is not found else (string) the rank.
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_rank_id_from_title' ) ) {
	function mycred_get_rank_id_from_title( $title ) {
		$rank = mycred_get_rank( $title );
		if ( ! isset( $rank->ID ) )
			$return = '';
		else
			$return = $rank->ID;

		return apply_filters( 'mycred_get_rank_id_from_title', $return, $title );
	}
}

/**
 * Get My Rank
 * Returns the current users rank
 * @since 1.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_my_rank' ) ) {
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
 * @returns (array) empty if no ranks are found or associative array with post ID as key and title as value
 * @since 1.1
 * @version 1.2
 */
if ( ! function_exists( 'mycred_get_ranks' ) ) {
	function mycred_get_ranks( $status = 'publish', $number = '-1', $order = 'DESC' ) {
		global $wpdb;

		// Order
		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		// Limit
		if ( $number != '-1' )
			$limit = ' 0,' . absint( $number );
		else
			$limit = '';

		// Get ranks
		$all_ranks = $wpdb->get_results( $wpdb->prepare( "
SELECT * 
FROM {$wpdb->posts} ranks
	INNER JOIN {$wpdb->postmeta} min
		ON ( min.post_id = ranks.ID AND min.meta_key = %s )
WHERE ranks.post_type = %s 
	AND ranks.post_status = %s 
ORDER BY min.meta_value+0 {$order} {$limit};", 'mycred_rank_min', 'mycred_rank', $status ) );
		$wpdb->flush();

		// Sort
		$ranks = array();
		if ( ! empty( $all_ranks ) ) {

			foreach ( $all_ranks as $rank )
				$ranks[ $rank->ID ] = $rank;

		}

		return apply_filters( 'mycred_get_ranks', $ranks, $status, $number, $order );
	}
}

/**
 * Get Users of Rank
 * Returns an associative array of user IDs and display names of users for a given
 * rank.
 * @param $rank (int|string) either a rank id or rank name
 * @param $number (int) number of users to return
 * @returns (array) empty if no users were found or associative array with user ID as key and display name as value
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_users_of_rank' ) ) {
	function mycred_get_users_of_rank( $rank_id, $number = NULL, $order = 'DESC' ) {
		if ( ! is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		if ( $rank_id === NULL ) return '';

		global $wpdb;

		$sql = "
SELECT rank.user_id 
FROM {$wpdb->usermeta} rank 
	INNER JOIN {$wpdb->usermeta} balance 
		ON ( rank.user_id = balance.user_id AND balance.meta_key = 'mycred_default' )
WHERE rank.meta_key = 'mycred_rank' 
	AND rank.meta_value = %d
ORDER BY balance.meta_value+0";

		// Order
		if ( $order == 'ASC' )
			$sql .= ' ASC';
		else
			$sql .= ' DESC';

		// Limit
		if ( $number !== NULL )
			$sql .= ' LIMIT 0,' . abs( $number ) . ';';
		else
			$sql .= ';';

		// Run query
		$users = $wpdb->get_results( $wpdb->prepare( $sql, $rank_id ), 'ARRAY_A' );
		$wpdb->flush();

		return apply_filters( 'mycred_get_users_of_rank', $users, $rank_id, $number, $order );
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
 * @version 1.1
 */
if ( ! function_exists( 'mycred_rank_has_logo' ) ) {
	function mycred_rank_has_logo( $rank_id ) {
		if ( ! is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		$return = false;
		if ( has_post_thumbnail( $rank_id ) )
			$return = true;

		return apply_filters( 'mycred_rank_has_logo', $return, $rank_id );
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
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_rank_logo' ) ) {
	function mycred_get_rank_logo( $rank_id, $size = 'post-thumbnail', $attr = NULL ) {
		if ( ! is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		if ( ! mycred_rank_has_logo( $rank_id ) ) return '';

		if ( is_numeric( $size ) )
			$size = array( $size, $size );

		$logo = get_the_post_thumbnail( $rank_id, $size, $attr );
		return apply_filters( 'mycred_get_rank_logo', $logo, $rank_id, $size, $attr );
	}
}

/**
 * User Got Demoted
 * Checks if a user got demoted.
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_user_got_demoted' ) ) {
	function mycred_user_got_demoted( $user_id = NULL, $rank_id = NULL ) {
		$current_rank_id = get_user_meta( $user_id, 'mycred_rank', true );
		if ( $current_rank_id == $rank_id ) return false;
		if ( empty( $current_rank_id ) && ! empty( $rank_id ) ) return true;

		$current_min = get_post_meta( $current_rank_id, 'mycred_rank_min', true );
		$new_min = get_post_meta( $rank_id, 'mycred_rank_min', true );
		
		if ( $new_min < $current_min ) return true;
		
		return false;
	}
}

/**
 * User Got Promoted
 * Checks if a user got promoted.
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_user_got_promoted' ) ) {
	function mycred_user_got_promoted( $user_id = NULL, $rank_id = NULL ) {
		$current_rank_id = get_user_meta( $user_id, 'mycred_rank', true );
		if ( $current_rank_id == $rank_id ) return false;
		if ( empty( $current_rank_id ) && ! empty( $rank_id ) ) return true;
		
		$current_min = get_post_meta( $current_rank_id, 'mycred_rank_min', true );
		$new_min = get_post_meta( $rank_id, 'mycred_rank_min', true );
		
		if ( $new_min > $current_min ) return true;
		
		return false;
	}
}
?>