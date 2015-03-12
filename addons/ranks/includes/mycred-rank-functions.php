<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Have Ranks
 * Checks if there are any rank posts.
 * @returns (bool) true or false
 * @since 1.1
 * @version 1.5
 */
if ( ! function_exists( 'mycred_have_ranks' ) ) :
	function mycred_have_ranks( $type = NULL )
	{
		global $wpdb;

		if ( ! mycred_override_settings() ) {
			$posts = $wpdb->posts;
			$postmeta = $wpdb->postmeta;
		}
		else {
			$posts = $wpdb->base_prefix . 'posts';
			$postmeta = $wpdb->base_prefix . 'postmeta';
		}

		$type_filter = '';
		if ( $type !== NULL && sanitize_text_field( $type ) != '' )
			$type_filter = $wpdb->prepare( "
				INNER JOIN {$postmeta} ctype 
					ON ( ranks.ID = ctype.post_id AND ctype.meta_key = %s AND ctype.meta_value = %s )", 'ctype', $type );

		$mycred_ranks = $wpdb->get_var( "
			SELECT COUNT(*) 
			FROM {$posts} 
			{$type_filter} 
			WHERE post_type = 'mycred_rank';" );
		
		$return = false;
		if ( $mycred_ranks > 0 )
			$return = true;

		return apply_filters( 'mycred_have_ranks', $return, $mycred_ranks );
	}
endif;

/**
 * Have Published Ranks
 * Checks if there are any published rank posts.
 * @returns (int) the number of published ranks found.
 * @since 1.3.2
 * @version 1.2
 */
if ( ! function_exists( 'mycred_get_published_ranks_count' ) ) :
	function mycred_get_published_ranks_count( $type = NULL )
	{
		global $wpdb;

		if ( ! mycred_override_settings() ) {
			$posts = $wpdb->posts;
			$postmeta = $wpdb->postmeta;
		}
		else {
			$posts = $wpdb->base_prefix . 'posts';
			$postmeta = $wpdb->base_prefix . 'postmeta';
		}

		$type_filter = '';
		if ( $type !== NULL && sanitize_text_field( $type ) != '' )
			$type_filter = $wpdb->prepare( "
				INNER JOIN {$postmeta} ctype 
					ON ( ranks.ID = ctype.post_id AND ctype.meta_key = %s AND ctype.meta_value = %s )", 'ctype', $type );

		$count = $wpdb->get_var( "
			SELECT COUNT(*) 
			FROM {$posts} ranks 
			{$type_filter} 
			WHERE ranks.post_type = 'mycred_rank' 
			AND ranks.post_status = 'publish';" );

		return apply_filters( 'mycred_get_published_ranks_count', $count );
	}
endif;

/**
 * Assign Ranks
 * Runs though all user balances and assigns each users their
 * appropriate ranks.
 * @returns (int) number of users effected by rank change or -1 if all users were effected.
 * @since 1.3.2
 * @version 1.4
 */
if ( ! function_exists( 'mycred_assign_ranks' ) ) :
	function mycred_assign_ranks( $type = 'mycred_default' )
	{
		global $wpdb;

		$mycred = mycred( $type );

		// Get rank key
		$rank_meta_key = 'mycred_rank';
		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_master_template )
			$rank_meta_key .= '_' . $GLOBALS['blog_id'];

		do_action( 'mycred_assign_ranks_start' );

		if ( ! mycred_override_settings() ) {
			$posts = $wpdb->posts;
			$postmeta = $wpdb->postmeta;
		}
		else {
			$posts = $wpdb->base_prefix . 'posts';
			$postmeta = $wpdb->base_prefix . 'postmeta';
		}

		// Check for published ranks
		$published_ranks = mycred_get_published_ranks_count( $type );

		// Point Type Filter
		$type_filter = '';
		if ( $type !== NULL && sanitize_text_field( $type ) != '' ) {

			$type_filter = $wpdb->prepare( "
				INNER JOIN {$postmeta} ctype 
					ON ( ranks.ID = ctype.post_id AND ctype.meta_key = %s AND ctype.meta_value = %s )", 'ctype', $type );

			if ( $type != 'mycred_default' )
				$rank_meta_key .= $type;

		}

		$result = 0;

		if ( $published_ranks > 0 ) {

			// Get balance key for this type
			$balance_key = $type;

			if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_central_logging )
				$balance_key .= '_' . $GLOBALS['blog_id'];

			if ( isset( $mycred->rank['base'] ) && $mycred->rank['base'] == 'total' )
				$balance_key .= '_total';

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
				WHERE meta_key = %s;", $balance_key ), 'ARRAY_A' );

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
				SELECT ranks.ID AS ID, min.meta_value AS min, max.meta_value AS max 
				FROM {$posts} ranks 
				{$type_filter} 
				INNER JOIN {$postmeta} min 
					ON ( min.post_id = ranks.ID AND min.meta_key = 'mycred_rank_min' )
				INNER JOIN {$postmeta} max 
					ON ( max.post_id = ranks.ID AND max.meta_key = 'mycred_rank_max' )
				WHERE ranks.post_type = 'mycred_rank' 
					AND ranks.post_status = 'publish';", 'ARRAY_A' );

			$count = 0;
			foreach ( $users as $user ) {
				foreach ( $ranks as $rank ) {
					if ( $user['balance'] >= $rank['min'] && $user['balance'] <= $rank['max'] ) {

						$end = '';
						if ( $type != 'mycred_default' )
							$end = $type;

						mycred_update_user_meta( $user['ID'], 'mycred_rank', $end, $rank['ID'] );
						$count = $count+1;
						break 1;

					}
				}
			}
			$wpdb->flush();
			
			$result = $count;
		}

		do_action( 'mycred_assign_ranks_end' );

		return $result;

	}
endif;

/**
 * Get Ranks
 * Retreaves a given rank.
 * @param $rank_title (string) the rank title (case sensitive)
 * @param $format (string) optional output type: OBJECT, ARRAY_N or ARRAY_A
 * @uses get_page_by_title()
 * @returns empty string if title is missing, NULL if rank is not found else the output type
 * @since 1.1
 * @version 1.3
 */
if ( ! function_exists( 'mycred_get_rank' ) ) :
	function mycred_get_rank( $rank_title = '', $format = 'OBJECT' )
	{
		if ( empty( $rank_title ) ) return $rank_title;

		if ( ! mycred_override_settings() )
			$rank = get_page_by_title( $rank_title, $format, 'mycred_rank' );

		else {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( 1 );

			$rank = get_page_by_title( $rank_title, $format, 'mycred_rank' );

			switch_to_blog( $original_blog_id );
		}

		return apply_filters( 'mycred_get_rank', $rank, $rank_title, $format );
	}
endif;

/**
 * Get Users Rank
 * Retreaves the users current saved rank or if rank is missing
 * finds the appropriate rank and saves it.
 * @param $user_id (int) required user id to check
 * @param $return (string) post detail to return, defaults to post_title
 * @param $logo_size (string) if $return is set to 'logo', the size of the logo to return
 * @param $attr (array) if $return is set to 'logo', optional logo image attributes
 * @param $type (string) optional point type
 * @uses mycred_find_users_rank()
 * @uses get_the_title()
 * @returns (string) rank object item requested or - if no ranks exists
 * @since 1.1
 * @version 1.4
 */
if ( ! function_exists( 'mycred_get_users_rank' ) ) :
	function mycred_get_users_rank( $user_id = NULL, $return = 'post_title', $logo_size = 'post-thumbnail', $attr = NULL, $type = 'mycred_default' )
	{
		// User ID is required
		if ( $user_id === NULL )
			return __( 'mycred_get_users_rank() : Missing required user id', 'mycred' );

		$end = '';
		if ( $type != 'mycred_default' )
			$end = $type;

		// Get users rank
		$rank_id = mycred_get_user_meta( $user_id, 'mycred_rank', $end, true );

		// If empty, get the users rank now and save it
		if ( $rank_id == '' )
			$rank_id = mycred_find_users_rank( $user_id, true, $type );

		$reply = __( 'no rank', 'mycred' );

		// Have rank
		if ( $rank_id != '' && $rank_id !== NULL ) {

			// If we want to see the logo
			if ( $return == 'logo' )
				$reply = mycred_get_rank_logo( (int) $rank_id, $logo_size, $attr );
			
			// Else get the post object
			else {

				// Not using master template
				if ( ! mycred_override_settings() )
					$rank = get_post( (int) $rank_id );

				// Master template enforced
				else {
					$original_blog_id = get_current_blog_id();
					switch_to_blog( 1 );

					$rank = get_post( (int) $rank_id );

					switch_to_blog( $original_blog_id );
				}

				// If the requested detail exists, return it
				if ( isset( $rank->$return ) )
					$reply = $rank->$return;
			}

		}

		return apply_filters( 'mycred_get_users_rank', $reply, $user_id, $return, $logo_size );
	}
endif;

/**
 * Get Users Rank ID
 * Returns the rank post ID for the given point type.
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_users_rank_id' ) ) :
	function mycred_get_users_rank_id( $user_id = NULL, $type = 'mycred_default' ) {

		$end = '';
		if ( $type != 'mycred_default' )
			$end = $type;

		$rank_id = mycred_get_user_meta( $user_id, 'mycred_rank', $end, true );
		if ( $rank_id == '' )
			$rank_id = mycred_find_users_rank( $user_id, true, $type );

		return $rank_id;

	}
endif;

/**
 * Find Users Rank
 * Compares the given users points balance with existing ranks to determain
 * where the user fits in.
 * @param $user_id (int) required user id
 * @param $save (bool) option to save the rank to the given users meta data
 * @param $type (string) optional point type
 * @uses $wpdb
 * @returns (string) users rank ID.
 * @since 1.1
 * @version 1.5
 */
if ( ! function_exists( 'mycred_find_users_rank' ) ) :
	function mycred_find_users_rank( $user_id = NULL, $save = false, $type = 'mycred_default' )
	{
		global $wpdb;

		$mycred = mycred( $type );

		// In case user id is not set
		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		// Get current balanace
		$current_balance = $wpdb->get_var( $wpdb->prepare( "
			SELECT meta_value 
			FROM {$wpdb->usermeta} 
			WHERE user_id = %d 
			AND meta_key = %s;", $user_id, $type ) );

		if ( $current_balance === NULL )
			$current_balance = 0;

		// If ranks are based on total we get the total balance which in turn
		// if not set will default to the users current balance.
		if ( mycred_rank_based_on_total( $type ) ) {

			$balance = mycred_query_users_total( $user_id, $type );
			if ( $balance == 0 )
				$balance = $current_balance;

		}
		else
			$balance = $current_balance;

		// Prep format for the db query
		$balance_format = '%d';
		if ( isset( $mycred->format['decimals'] ) && $mycred->format['decimals'] > 0 )
			$balance_format = 'CAST( %f AS DECIMAL( 10, ' . $mycred->format['decimals'] . ' ) )';

		// Get the appropriate post tables
		if ( ! mycred_override_settings() ) {
			$posts = $wpdb->posts;
			$postmeta = $wpdb->postmeta;
		}
		else {
			$posts = $wpdb->base_prefix . 'posts';
			$postmeta = $wpdb->base_prefix . 'postmeta';
		}

		$type_filter = $wpdb->prepare( "
			INNER JOIN {$postmeta} ctype 
				ON ( ranks.ID = ctype.post_id AND ctype.meta_key = %s AND ctype.meta_value = %s )", 'ctype', $type );

		// Get the rank based on balance
		$rank_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT ranks.ID 
			FROM {$posts} ranks 
			{$type_filter}
			INNER JOIN {$postmeta} min 
				ON ( ranks.ID = min.post_id AND min.meta_key = 'mycred_rank_min' )
			INNER JOIN {$postmeta} max 
				ON ( ranks.ID = max.post_id AND max.meta_key = 'mycred_rank_max' )
			WHERE ranks.post_type = 'mycred_rank' 
				AND ranks.post_status = 'publish'
				AND {$balance_format} BETWEEN min.meta_value AND max.meta_value
			LIMIT 0,1;", $balance ) );

		// Let others play
		if ( $rank_id !== NULL ) {

			if ( mycred_user_got_demoted( $user_id, $rank_id ) )
				do_action( 'mycred_user_got_demoted', $user_id, $rank_id );

			elseif ( mycred_user_got_promoted( $user_id, $rank_id ) )
				do_action( 'mycred_user_got_promoted', $user_id, $rank_id );

		}

		$end = '';
		if ( $type != 'mycred_default' )
			$end = $type;

		// Save if requested
		if ( $save && $rank_id !== NULL )
			mycred_update_user_meta( $user_id, 'mycred_rank', $end, $rank_id );

		return apply_filters( 'mycred_find_users_rank', $rank_id, $user_id, $save, $type );
	}
endif;

/**
 * Get Rank ID from Title
 * Used to get the rank object based on the ranks title.
 * @param $title (string) required rank title
 * @uses mycred_get_rank()
 * @returns empty (string) if title is missing, NULL if rank is not found else (string) the rank.
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_rank_id_from_title' ) ) :
	function mycred_get_rank_id_from_title( $title )
	{
		$rank = mycred_get_rank( $title );
		if ( ! isset( $rank->ID ) )
			$return = '';
		else
			$return = $rank->ID;

		return apply_filters( 'mycred_get_rank_id_from_title', $return, $title );
	}
endif;

/**
 * Get My Rank
 * Returns the current users rank
 * @since 1.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_my_rank' ) ) :
	function mycred_get_my_rank()
	{
		return mycred_get_users_rank( get_current_user_id() );
	}
endif;

/**
 * Get Ranks
 * Returns an associative array of ranks with the given status.
 * @param $status (string) post status, defaults to 'publish'
 * @param $number (int|string) number of ranks to return, defaults to all
 * @param $order (string) option to return ranks ordered Ascending or Descending
 * @param $type (string) optional point type
 * @returns (array) empty if no ranks are found or associative array with post ID as key and title as value
 * @since 1.1
 * @version 1.4
 */
if ( ! function_exists( 'mycred_get_ranks' ) ) :
	function mycred_get_ranks( $status = 'publish', $number = '-1', $order = 'DESC', $type = NULL )
	{
		global $wpdb;

		// Order
		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		// Limit
		if ( $number != '-1' )
			$limit = ' 0,' . absint( $number );
		else
			$limit = '';

		if ( ! mycred_override_settings() ) {
			$posts = $wpdb->posts;
			$postmeta = $wpdb->postmeta;
		}
		else {
			$posts = $wpdb->base_prefix . 'posts';
			$postmeta = $wpdb->base_prefix . 'postmeta';
		}

		$type_filter = '';
		if ( $type !== NULL && sanitize_text_field( $type ) != '' )
			$type_filter = $wpdb->prepare( "
			INNER JOIN {$postmeta} ctype 
				ON ( ranks.ID = ctype.post_id AND ctype.meta_key = %s AND ctype.meta_value = %s )", 'ctype', $type );

		// Get ranks
		$all_ranks = $wpdb->get_results( $wpdb->prepare( "
			SELECT * 
			FROM {$posts} ranks
			INNER JOIN {$postmeta} min
				ON ( ranks.ID = min.post_id AND min.meta_key = %s )
			{$type_filter}
			WHERE ranks.post_type = %s 
			AND ranks.post_status = %s 

			ORDER BY min.meta_value+0 {$order} {$limit};", 'mycred_rank_min', 'mycred_rank', $status ) );

		// Sort
		$ranks = array();
		if ( ! empty( $all_ranks ) ) {

			foreach ( $all_ranks as $rank )
				$ranks[ $rank->ID ] = $rank;

		}

		return apply_filters( 'mycred_get_ranks', $ranks, $status, $number, $order );
	}
endif;

/**
 * Get Users of Rank
 * Returns an associative array of user IDs and display names of users for a given
 * rank.
 * @param $rank (int|string) either a rank id or rank name
 * @param $number (int) number of users to return
 * @returns (array) empty if no users were found or associative array with user ID as key and display name as value
 * @since 1.1
 * @version 1.4
 */
if ( ! function_exists( 'mycred_get_users_of_rank' ) ) :
	function mycred_get_users_of_rank( $rank_id, $number = NULL, $order = 'DESC', $type = 'mycred_default' )
	{
		if ( ! is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		if ( $rank_id === NULL ) return '';

		global $wpdb;

		$mycred = mycred( $type );

		$balance_key = $type;

		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_central_logging )
			$balance_key .= '_' . $GLOBALS['blog_id'];

		if ( mycred_rank_based_on_total( $type ) )
			$balance_key .= '_total';

		$rank_meta_key = 'mycred_rank';
		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_master_template )
			$rank_meta_key .= '_' . $GLOBALS['blog_id'];

		if ( $type != 'mycred_default' )
			$rank_meta_key .= $type;

		$sql = "
			SELECT rank.user_id 
			FROM {$wpdb->usermeta} rank 
			INNER JOIN {$wpdb->usermeta} balance 
				ON ( rank.user_id = balance.user_id AND balance.meta_key = %s )";

		$sql .= "
			WHERE rank.meta_key = %s 
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
		$users = $wpdb->get_results( $wpdb->prepare( $sql, $balance_key, $rank_meta_key, $rank_id ), 'ARRAY_A' );
		$wpdb->flush();

		return apply_filters( 'mycred_get_users_of_rank', $users, $rank_id, $number, $order, $type );
	}
endif;

/**
 * Count Users with Rank
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_count_users_with_rank' ) ) :
	function mycred_count_users_with_rank( $rank_id = NULL ) {

		if ( $rank_id === NULL ) return 0;

		$type = get_post_meta( $rank_id, 'ctype', true );
		if ( $type == '' ) return 0;

		$mycred = mycred( $type );

		$rank_meta_key = 'mycred_rank';
		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_master_template )
			$rank_meta_key .= '_' . $GLOBALS['blog_id'];

		if ( $type != 'mycred_default' )
			$rank_meta_key .= $type;

		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( user_id ) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %d;", $rank_meta_key, $rank_id ) );

		if ( $count === NULL )
			$count = 0;

		return $count;

	}
endif;

/**
 * Rank Has Logo
 * Checks if a given rank has a logo.
 * @param $rank_id (int|string) either the rank id or the rank title
 * @uses mycred_get_rank_id_from_title()
 * @uses has_post_thumbnail()
 * @returns (bool) true or false
 * @since 1.1
 * @version 1.2
 */
if ( ! function_exists( 'mycred_rank_has_logo' ) ) :
	function mycred_rank_has_logo( $rank_id )
	{
		if ( ! is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		$return = false;
		if ( ! mycred_override_settings() ) {
			if ( has_post_thumbnail( $rank_id ) )
				$return = true;
		}
		else {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( 1 );

			if ( has_post_thumbnail( $rank_id ) )
				$return = true;

			switch_to_blog( $original_blog_id );
		}

		return apply_filters( 'mycred_rank_has_logo', $return, $rank_id );
	}
endif;

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
 * @version 1.2
 */
if ( ! function_exists( 'mycred_get_rank_logo' ) ) :
	function mycred_get_rank_logo( $rank_id, $size = 'post-thumbnail', $attr = NULL )
	{
		if ( ! is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		if ( ! mycred_rank_has_logo( $rank_id ) ) return '';

		if ( is_numeric( $size ) )
			$size = array( $size, $size );

		if ( ! mycred_override_settings() )
			$logo = get_the_post_thumbnail( $rank_id, $size, $attr );

		else {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( 1 );

			$logo = get_the_post_thumbnail( $rank_id, $size, $attr );

			switch_to_blog( $original_blog_id );
		}

		return apply_filters( 'mycred_get_rank_logo', $logo, $rank_id, $size, $attr );
	}
endif;

/**
 * User Got Demoted
 * Checks if a user got demoted.
 * @since 1.3.3
 * @version 1.4
 */
if ( ! function_exists( 'mycred_user_got_demoted' ) ) :
	function mycred_user_got_demoted( $user_id = NULL, $rank_id = NULL )
	{
		$type = get_post_meta( $rank_id, 'ctype', true );
		if ( $type == '' ) {
			$type = 'mycred_default';
			update_post_meta( $rank_id, 'ctype', $type );
		}

		$end = '';
		if ( $type != 'mycred_default' )
			$end = $type;

		$current_rank_id = mycred_get_user_meta( $user_id, 'mycred_rank', $end, true );

		// No demotion
		if ( $current_rank_id == $rank_id ) return false;

		// User did not have a rank before but will have now, that is assumed to be a promotion
		if ( empty( $current_rank_id ) && ! empty( $rank_id ) ) return false;

		// Get minimums
		if ( ! mycred_override_settings() ) {
			$current_min = get_post_meta( $current_rank_id, 'mycred_rank_min', true );
			$new_min = get_post_meta( $rank_id, 'mycred_rank_min', true );
		}
		else {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( 1 );

			$current_min = get_post_meta( $current_rank_id, 'mycred_rank_min', true );
			$new_min = get_post_meta( $rank_id, 'mycred_rank_min', true );

			switch_to_blog( $original_blog_id );
		}

		// Compare
		if ( $new_min < $current_min ) return true;

		return false;
	}
endif;

/**
 * User Got Promoted
 * Checks if a user got promoted.
 * @since 1.3.3
 * @version 1.4
 */
if ( ! function_exists( 'mycred_user_got_promoted' ) ) :
	function mycred_user_got_promoted( $user_id = NULL, $rank_id = NULL )
	{
		$type = get_post_meta( $rank_id, 'ctype', true );
		if ( $type == '' ) {
			$type = 'mycred_default';
			update_post_meta( $rank_id, 'ctype', $type );
		}

		$end = '';
		if ( $type != 'mycred_default' )
			$end = $type;

		$current_rank_id = mycred_get_user_meta( $user_id, 'mycred_rank', $end, true );

		// No promotion
		if ( $current_rank_id == $rank_id ) return false;

		// User did not have a rank before but will have now, that is assumed to be a promotion
		if ( empty( $current_rank_id ) && ! empty( $rank_id ) ) return true;

		// Get minimums
		if ( ! mycred_override_settings() ) {
			$current_min = get_post_meta( $current_rank_id, 'mycred_rank_min', true );
			$new_min = get_post_meta( $rank_id, 'mycred_rank_min', true );
		}
		else {
			$original_blog_id = get_current_blog_id();
			switch_to_blog( 1 );

			$current_min = get_post_meta( $current_rank_id, 'mycred_rank_min', true );
			$new_min = get_post_meta( $rank_id, 'mycred_rank_min', true );

			switch_to_blog( $original_blog_id );
		}

		// Compare
		if ( $new_min > $current_min ) return true;

		return false;
	}
endif;

/**
 * Rank Based on Total
 * Checks if ranks for a given point type are based on total or current
 * balance.
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_rank_based_on_total' ) ) :
	function mycred_rank_based_on_total( $type = 'mycred_default' ) {

		$prefs_key = 'mycred_pref_core';
		if ( $type != 'mycred_default' )
			$prefs_key .= '_' . $type;

		$prefs = get_option( $prefs_key );

		$result = false;
		if ( isset( $prefs['rank']['base'] ) && $prefs['rank']['base'] == 'total' )
			$result = true;

		return $result;

	}
endif;

/**
 * Rank Shown in BuddyPress
 * Returns either false or the location where the rank is to be shown in BuddyPress.
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_show_rank_in_buddypress' ) ) :
	function mycred_show_rank_in_buddypress( $type = 'mycred_default' ) {

		$prefs_key = 'mycred_pref_core';
		if ( $type != 'mycred_default' )
			$prefs_key .= '_' . $type;

		$prefs = get_option( $prefs_key );

		$result = false;
		if ( isset( $prefs['rank']['bb_location'] ) && $prefs['rank']['bb_location'] != '' )
			$result = $prefs['rank']['bb_location'];

		return $result;

	}
endif;

/**
 * Rank Shown in bbPress
 * Returns either false or the location where the rank is to be shown in bbPress.
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_show_rank_in_bbpress' ) ) :
	function mycred_show_rank_in_bbpress( $type = 'mycred_default' ) {

		$prefs_key = 'mycred_pref_core';
		if ( $type != 'mycred_default' )
			$prefs_key .= '_' . $type;

		$prefs = get_option( $prefs_key );

		$result = false;
		if ( isset( $prefs['rank']['bp_location'] ) && $prefs['rank']['bp_location'] != '' )
			$result = $prefs['rank']['bp_location'];

		return $result;

	}
endif;
?>