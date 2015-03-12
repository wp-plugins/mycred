<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED Shortcode: mycred_my_rank
 * Returns a given users rank
 * @see http://mycred.me/shortcodes/mycred_my_rank/
 * @since 1.1
 * @version 1.2
 */
if ( ! function_exists( 'mycred_render_my_rank' ) ) :
	function mycred_render_my_rank( $atts, $content = NULL )
	{
		extract( shortcode_atts( array(
			'user_id'    => '',
			'ctype'      => 'mycred_default',
			'show_title' => 1,
			'show_logo'  => 0,
			'logo_size'  => 'post-thumbnail',
			'first'      => 'logo'
		), $atts ) );
		
		if ( $user_id == '' && ! is_user_logged_in() ) return;

		if ( $user_id == 'author' ) {
			global $post;
			if ( ! isset( $post->ID ) ) return;
			$user_id = $post->post_author;
		}

		if ( $user_id == '' )
			$user_id = get_current_user_id();
		
		$rank_id = mycred_get_users_rank_id( $user_id, $ctype );
		$show = array();
		
		if ( $show_logo )
			$show[] = mycred_get_rank_logo( $rank_id, $logo_size );

		if ( $show_title )
			$show[] = get_the_title( $rank_id );
		
		if ( $first != 'logo' )
			$show = array_reverse( $show );

		if ( empty( $show ) ) return;
		return '<div class="mycred-my-rank">' . implode( ' ', $show ) . '</div>';
	}
endif;

/**
 * myCRED Shortcode: mycred_my_ranks
 * Returns the given users ranks.
 * @see http://mycred.me/shortcodes/mycred_my_rank/
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_render_my_ranks' ) ) :
	function mycred_render_my_ranks( $atts, $content = NULL )
	{
		extract( shortcode_atts( array(
			'user_id'    => NULL,
			'show_title' => 1,
			'show_logo'  => 0,
			'logo_size'  => 'post-thumbnail',
			'first'      => 'logo'
		), $atts ) );
		
		if ( $user_id === NULL && ! is_user_logged_in() ) return;
		if ( $user_id === NULL )
			$user_id = get_current_user_id();
		
		$mycred_types = mycred_get_types();
		$show = array();

		foreach ( $mycred_types as $type_id => $label ) {

			$row = array();
			$rank_id = mycred_get_users_rank_id( $user_id, $type_id );

			if ( $show_logo )
				$row[] = mycred_get_rank_logo( $rank_id, $logo_size );

			if ( $show_title )
				$row[] = get_the_title( $rank_id );
		
			if ( $first != 'logo' )
				$row = array_reverse( $row );

			$show = array_merge( $row, $show );

		}

		if ( empty( $show ) ) return;
		return '<div class="mycred-all-ranks">' . implode( ' ', $show ) . '</div>';
	}
endif;

/**
 * myCRED Shortcode: mycred_users_of_rank
 * Returns all users who have the given rank with the option to show the rank logo and optional content.
 * @see http://mycred.me/shortcodes/mycred_users_of_rank/
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_render_users_of_rank' ) ) :
	function mycred_render_users_of_rank( $atts, $row_template = NULL )
	{
		extract( shortcode_atts( array(
			'rank_id' => NULL,
			'login'   => '',
			'number'  => NULL,
			'wrap'    => 'div',
			'col'     => 1,
			'nothing' => __( 'No users found with this rank', 'mycred' ),
			'ctype'   => NULL,
			'order'   => 'DESC'
		), $atts ) );
		
		// Rank ID required
		if ( $rank_id === NULL )
			return '<strong>' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Rank ID is required!', 'mycred' );

		$mycred = mycred( ( $ctype === NULL ) ? 'mycred_default' : $ctype );

		// User is not logged in
		if ( ! is_user_logged_in() && $login != '' )
			return $mycred->template_tags_general( $login );
		
		// ID is not a post id but a rank title
		if ( ! is_numeric( $rank_id ) )
			$rank_id = mycred_get_rank_id_from_title( $rank_id );

		if ( $ctype === NULL ) {
			$type = get_post_meta( $rank_id, 'type', true );
			if ( $type != '' )
				$ctype = $type;
		}

		$output = '';
		$rank = get_post( $rank_id );
		// Make sure rank exist
		if ( $rank !== NULL ) {
			if ( $row_template === NULL || empty( $row_template ) )
				$row_template = '<p class="user-row">%user_profile_link% with %balance% %_plural%</p>';

			// Let others play
			$row_template = apply_filters( 'mycred_users_of_rank', $row_template, $atts, $mycred );

			// Get users of this rank if there are any
			$users = mycred_get_users_of_rank( $rank_id, $number, $order, $ctype );
			if ( ! empty( $users ) ) {
				// Add support for table
				if ( $wrap != 'table' && ! empty( $wrap ) )
					$output .= '<' . $wrap . ' class="mycred-users-of-rank-wrapper">';
				
				// Loop
				foreach ( $users as $user ) {
					$output .= $mycred->template_tags_user( $row_template, $user['user_id'] );
				}
				
				// Add support for table
				if ( $wrap != 'table' && ! empty( $wrap ) )
					$output .= '</' . $wrap . '>' . "\n";
			}
			// No users found
			else {
				// Add support for table
				if ( $wrap == 'table' ) {
					$output .= '<tr><td';
					if ( $col > 1 ) $output .= ' colspan="' . $col . '"';
					$output .= '>' . $nothing . '</td></tr>';
				}
				else {
					if ( empty( $wrap ) ) $wrap = 'p';
					$output .= '<' . $wrap . '>' . $nothing . '</' . $wrap . '>' . "\n";
				}
			}
		}
		
		return do_shortcode( $output );
	}
endif;

/**
 * myCRED Shortcode: mycred_users_of_all_ranks
 * Returns all users fore every registered rank in order.
 * @see http://mycred.me/shortcodes/mycred_users_of_all_ranks/
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_render_users_of_all_ranks' ) ) :
	function mycred_render_users_of_all_ranks( $atts, $row_template = NULL )
	{
		extract( shortcode_atts( array(
			'login'     => '',
			'number'    => NULL,
			'ctype'     => NULL,
			'show_logo' => 1,
			'logo_size' => 'post-thumbnail',
			'wrap'      => 'div',
			'nothing'   => __( 'No users found with this rank', 'mycred' )
		), $atts ) );

		// Prep
		$mycred = mycred();
		
		// User is not logged in
		if ( ! is_user_logged_in() && $login != '' )
			return $mycred->template_tags_general( $login );
		
		// Default template
		if ( $row_template === NULL || empty( $row_template ) )
			$row_template = '<p class="mycred-rank-user-row">%user_profile_link% with %balance% %_plural%</p>';
		
		// Let others play
		$row_template = apply_filters( 'mycred_users_of_all_ranks', $row_template, $atts, $mycred );
		
		$output = '';
		$all_ranks = mycred_get_ranks( 'publish', '-1', 'DESC', $ctype );
		// If we have ranks
		if ( ! empty( $all_ranks ) ) {
			$output .= '<div class="mycred-all-ranks-wrapper">' . "\n";
			// Loop though all ranks
			foreach ( $all_ranks as $rank_id => $rank ) {
				// Prep Slug
				$slug = $rank->post_name;
				if ( empty( $slug ) )
					$slug = str_replace( ' ', '-', strtolower( $rank->post_title ) );

				// Rank wrapper
				$output .= '<div class="mycred-rank rank-' . $slug . ' rank-' . $rank_id . '"><h2>';
				
				// Insert Logo
				if ( $show_logo )
					$output .= mycred_get_rank_logo( $rank_id, $logo_size );

				// Rank title
				$output .= $rank->post_title . '</h2>' . "\n";
				
				$attr = array(
					'rank_id' => $rank_id,
					'number'  => $number,
					'nothing' => $nothing,
					'wrap'    => $wrap,
					'ctype'   => $ctype
				);
				$output .= mycred_render_users_of_rank( $attr, $row_template );
				
				$output .= '</div>' . "\n";
			}
			$output .= '</div>';
		}
		
		return $output;
	}
endif;

/**
 * myCRED Shortcode: mycred_list_ranks
 * Returns a list of ranks with minimum and maximum point requirements.
 * @see http://mycred.me/shortcodes/mycred_list_ranks/
 * @since 1.1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_render_rank_list' ) ) :
	function mycred_render_rank_list( $atts, $content = NULL )
	{
		extract( shortcode_atts( array(
			'order' => 'DESC',
			'ctype' => 'mycred_default',
			'wrap'  => 'div'
		), $atts ) );
		
		if ( $content === NULL || empty( $content ) )
			$content = '<p>%rank% <span class="min">%min%</span> - <span class="max">%max%</span></p>';
		
		$mycred = mycred();

		$output = '';
		$all_ranks = mycred_get_ranks( 'publish', '-1', $order, $ctype );
		if ( ! empty( $all_ranks ) ) {
			$output .= '<' . $wrap . ' class="mycred-rank-list">';
			$content = apply_filters( 'mycred_rank_list', $content, $atts, $mycred );
			foreach ( $all_ranks as $rank_id => $rank ) {
				$row = str_replace( '%rank%', $rank->post_title, $content );
				$row = str_replace( '%rank_logo%', mycred_get_rank_logo( $rank_id ), $row );
				$row = str_replace( '%min%', get_post_meta( $rank_id, 'mycred_rank_min', true ), $row );
				$row = str_replace( '%max%', get_post_meta( $rank_id, 'mycred_rank_max', true ), $row );
				$row = str_replace( '%count%', count( mycred_get_users_of_rank( $rank_id ) ), $row );
				$row = $mycred->template_tags_general( $row );
				$output .= $row . "\n";
			}
			$output .= '</' . $wrap . '>';
		}
		
		return $output;
	}
endif;
?>