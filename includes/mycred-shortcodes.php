<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED Shortcode: my_balance
 * Returns the current users balance.
 * @see http://codex.mycred.me/shortcodes/mycred_my_balance/
 * @contributor Ian Tasker
 * @since 1.0.9
 * @version 1.2.2
 */
if ( ! function_exists( 'mycred_render_shortcode_my_balance' ) ) :
	function mycred_render_shortcode_my_balance( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id'    => '',
			'title'      => '',
			'title_el'   => 'h1',
			'balance_el' => 'div',
			'wrapper'    => 1,
			'type'       => 'mycred_default'
		), $atts ) );

		$output = '';

		// Not logged in
		if ( ! is_user_logged_in() && $user_id == '' )
			return $content;

		if ( $user_id == '' )
			$user_id = get_current_user_id();

		$mycred = mycred( $type );

		// Check for exclusion
		if ( $mycred->exclude_user( $user_id ) ) return;

		if ( ! empty( $type ) )
			$mycred->cred_id = $type;

		if ( $wrapper )
			$output .= '<div class="mycred-my-balance-wrapper">';

		// Title
		if ( ! empty( $title ) ) {
			if ( ! empty( $title_el ) )
				$output .= '<' . $title_el . '>';

			$output .= $title;

			if ( ! empty( $title_el ) )
				$output .= '</' . $title_el . '>';
		}

		// Balance
		if ( ! empty( $balance_el ) )
			$output .= '<' . $balance_el . '>';

		$balance = $mycred->get_users_cred( $user_id, $type );
		$output .= $mycred->format_creds( $balance );

		if ( ! empty( $balance_el ) )
			$output .= '</' . $balance_el . '>';

		if ( $wrapper )
			$output .= '</div>';

		return $output;

	}
endif;

/**
 * myCRED Shortcode: mycred_history
 * Returns the points history.
 * @see http://codex.mycred.me/shortcodes/mycred_history/
 * @since 1.0.9
 * @version 1.2.2
 */
if ( ! function_exists( 'mycred_render_shortcode_history' ) ) :
	function mycred_render_shortcode_history( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id'   => '',
			'number'    => '',
			'time'      => '',
			'ref'       => '',
			'order'     => '',
			'show_user' => 0,
			'show_nav'  => 1,
			'login'     => '',
			'type'      => 'mycred_default'
		), $atts ) );

		// If we are not logged in
		if ( ! is_user_logged_in() && $login != '' )
			return $login . $content;

		if ( $user_id == 'current' )
			$user_id = get_current_user_id();

		$args = array( 'ctype' => $type );

		if ( $user_id != '' )
			$args['user_id'] = $user_id;

		if ( $number != '' )
			$args['number'] = $number;

		if ( $time != '' )
			$args['time'] = $time;

		if ( $ref != '' )
			$args['ref'] = $ref;

		if ( $order != '' )
			$args['order'] = $order;

		if ( isset( $_GET['paged'] ) && $_GET['paged'] != '' )
			$args['paged'] = absint( $_GET['paged'] );

		$log = new myCRED_Query_Log( $args );

		if ( $show_user != 1 )
			unset( $log->headers['column-username'] ); 

		ob_start();

?>

<form class="form" role="form" method="get" action="">
	<div class="tablenav top">

		<?php if ( $log->have_entries() && $show_nav == 1 && $log->max_num_pages > 1 ) $log->navigation( 'top' ); ?>

	</div>

	<?php $log->display(); ?>

	<div class="tablenav bottom">

		<?php if ( $log->have_entries() && $show_nav == 1 && $log->max_num_pages > 1 ) $log->navigation( 'bottom' ); ?>

	</div>
</form>
<?php

		$content = ob_get_contents();
		ob_end_clean();

		$log->reset_query();

		return $content;

	}
endif;

/**
 * myCRED Shortcode: mycred_leaderboard
 * @see http://codex.mycred.me/shortcodes/mycred_leaderboard/
 * @since 0.1
 * @version 1.4.3
 */
if ( ! function_exists( 'mycred_render_shortcode_leaderboard' ) ) :
	function mycred_render_shortcode_leaderboard( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'number'       => '-1',
			'order'        => 'DESC',
			'offset'       => 0,
			'type'         => 'mycred_default',
			'based_on'     => 'balance',
			'wrap'         => 'li',
			'template'     => '#%position% %user_profile_link% %cred_f%',
			'nothing'      => __( 'Leaderboard is empty.', 'mycred' ),
			'current'      => 0,
			'exclude_zero' => 1
		), $atts ) );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		if ( $number != '-1' )
			$limit = 'LIMIT ' . absint( $offset ) . ',' . absint( $number );
		else
			$limit = '';

		$mycred = mycred( $type );

		global $wpdb;

		// Option to exclude zero balances
		$excludes = '';
		if ( $exclude_zero == 1 ) {
			$balance_format = '%d';
			if ( isset( $mycred->format['decimals'] ) && $mycred->format['decimals'] > 0 )
				$balance_format = 'CAST( %f AS DECIMAL( 10, ' . $mycred->format['decimals'] . ' ) )';

			$excludes = $wpdb->prepare( "AND um.meta_value != {$balance_format}", $mycred->zero() );

		}

		$based_on = sanitize_text_field( $based_on );

		// Leaderboard based on balance
		if ( $based_on == 'balance' )
			$SQL = $wpdb->prepare( "
				SELECT DISTINCT u.ID, um.meta_value AS cred 
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um
					ON ( u.ID = um.user_id )
				WHERE um.meta_key = %s 
				{$excludes}
				ORDER BY um.meta_value+0 {$order} {$limit};", $type );

		// Leaderboard based on reference
		else
			$SQL = $wpdb->prepare( "
				SELECT DISTINCT user_id AS ID, SUM( creds ) AS cred 
				FROM {$mycred->log_table} 
				WHERE ref = %s 
				GROUP BY user_id 
				ORDER BY SUM( creds ) {$order} {$limit};", $based_on );

		$leaderboard = $wpdb->get_results( apply_filters( 'mycred_ranking_sql', $SQL, $atts ), 'ARRAY_A' );

		$output = '';
		$in_list = false;

		// Get current users object
		$current_user = wp_get_current_user();

		if ( ! empty( $leaderboard ) ) {

			// Check if current user is in the leaderboard
			if ( $current == 1 && is_user_logged_in() ) {

				// Find the current user in the leaderboard
				foreach ( $leaderboard as $position => $user ) {
					if ( $user['ID'] == $current_user->ID ) {
						$in_list = true;
						break;
					}
				}

			}

			// Load myCRED
			$mycred = mycred( $type );

			// Wrapper
			if ( $wrap == 'li' )
				$output .= '<ol class="myCRED-leaderboard">';

			// Loop
			foreach ( $leaderboard as $position => $user ) {

				// Prep
				$class = array();

				// Position
				if ( $offset != '' && $offset > 0 )
					$position = $position + $offset;

				// Classes
				$class[] = 'item-' . $position;
				if ( $position == 0 )
					$class[] = 'first-item';

				if ( $position % 2 != 0 )
					$class[] = 'alt';

				if ( ! empty( $content ) )
					$template = $content;

				// Template Tags
				$layout = str_replace( array( '%ranking%', '%position%' ), $position+1, $template );

				$layout = $mycred->template_tags_amount( $layout, $user['cred'] );
				$layout = $mycred->template_tags_user( $layout, $user['ID'] );

				// Wrapper
				if ( ! empty( $wrap ) )
					$layout = '<' . $wrap . ' class="%classes%">' . $layout . '</' . $wrap . '>';

				$layout = str_replace( '%classes%', apply_filters( 'mycred_ranking_classes', implode( ' ', $class ) ), $layout );
				$layout = apply_filters( 'mycred_ranking_row', $layout, $template, $user, $position+1 );

				$output .= $layout . "\n";

			}

			$leaderboard = NULL;

			// Current user is not in list but we want to show his position
			if ( ! $in_list && $current == 1 && is_user_logged_in() ) {

				// Flush previous query
				$wpdb->flush();

				// Get a complete leaderboard with just user IDs
				if ( $based_on == 'balance' )
					$full_SQL = $wpdb->prepare( "
						SELECT DISTINCT u.ID 
						FROM {$wpdb->users} u
						INNER JOIN {$wpdb->usermeta} um
							ON ( u.ID = um.user_id )
						WHERE um.meta_key = %s 
						{$excludes} 
						ORDER BY um.meta_value+0 {$order};", $type );
				else
					$full_SQL = $wpdb->prepare( "
						SELECT DISTINCT user_id AS ID, SUM( creds ) AS cred 
						FROM {$mycred->log_table} 
						WHERE ref = %s 
						GROUP BY user_id 
						ORDER BY SUM( creds ) {$order} {$limit};", $based_on );

				$full_leaderboard = $wpdb->get_results( $full_SQL, 'ARRAY_A' );

				if ( ! empty( $full_leaderboard ) ) {

					// Get current users position
					$current_position = array_search( array( 'ID' => $current_user->ID ), $full_leaderboard );
					$full_leaderboard = NULL;

					// If position is found
					if ( $current_position !== false ) {

						// Template Tags
						$layout = str_replace( array( '%ranking%', '%position%' ), $current_position+1, $template );

						$layout = $mycred->template_tags_amount( $layout, $mycred->get_users_cred( $current_user->ID, $type ) );
						$layout = $mycred->template_tags_user( $layout, false, $current_user );

						// Wrapper
						if ( ! empty( $wrap ) )
							$layout = '<' . $wrap . ' class="%classes%">' . $layout . '</' . $wrap . '>';

						$layout = str_replace( '%classes%', apply_filters( 'mycred_ranking_classes', implode( ' ', $class ) ), $layout );
						$layout = apply_filters( 'mycred_ranking_row', $layout, $template, $current_user, $current_position+1 );

						$output .= $layout . "\n";
						
					}
				}

			}

			if ( $wrap == 'li' )
				$output .= '</ol>';

		}

		// No result template is set
		else {

			$output .= '<p class="mycred-leaderboard-none">' . $nothing . '</p>';

		}

		return do_shortcode( apply_filters( 'mycred_leaderboard', $output, $atts ) );

	}
endif;

/**
 * myCRED Shortcode: mycred_my_ranking
 * @see http://codex.mycred.me/shortcodes/mycred_my_ranking/
 * @since 0.1
 * @version 1.4.3
 */
if ( ! function_exists( 'mycred_render_shortcode_my_ranking' ) ) :
	function mycred_render_shortcode_my_ranking( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id'  => '',
			'ctype'    => 'mycred_default',
			'based_on' => 'balance',
			'missing'  => 0
		), $atts ) );

		// If no id is given
		if ( ! is_user_logged_in() && $user_id == '' )
			return $content;

		if ( $user_id == '' )
			$user_id = get_current_user_id();

		// If no type is given
		if ( $ctype == '' )
			$ctype = 'mycred_default';

		$mycred = mycred( $ctype );

		global $wpdb;

		$based_on = sanitize_text_field( $based_on );

		// Get a complete leaderboard with just user IDs
		if ( $based_on == 'balance' )
			$full_SQL = $wpdb->prepare( "
				SELECT DISTINCT u.ID 
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um
					ON ( u.ID = um.user_id )
				WHERE um.meta_key = %s  
				ORDER BY um.meta_value+0 DESC;", $ctype );
		else
			$full_SQL = $wpdb->prepare( "
				SELECT DISTINCT user_id AS ID, SUM( creds ) AS cred 
				FROM {$mycred->log_table} 
				WHERE ref = %s 
				GROUP BY user_id 
				ORDER BY SUM( creds ) DESC;", $based_on );

		$full_leaderboard = $wpdb->get_results( $full_SQL, 'ARRAY_A' );

		$position = 0;
		if ( ! empty( $full_leaderboard ) ) {

			// Get current users position
			$current_position = array_search( array( 'ID' => $user_id ), $full_leaderboard );
			$position = ( $current_position === false ) ? $missing : $current_position+1;

		}
		else $position = $missing;

		$full_leaderboard = NULL;

		return apply_filters( 'mycred_get_leaderboard_position', $position, $user_id, $ctype );

	}
endif;

/**
 * myCRED Shortcode: mycred_give
 * This shortcode allows you to award or deduct points from a given user or the current user
 * when this shortcode is executed. You can insert this in page/post content
 * or in a template file. Note that users are awarded/deducted points each time
 * this shortcode exectutes!
 * @see http://codex.mycred.me/shortcodes/mycred_give/
 * @since 1.1
 * @version 1.2.2
 */
if ( ! function_exists( 'mycred_render_shortcode_give' ) ) :
	function mycred_render_shortcode_give( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'amount'  => '',
			'user_id' => '',
			'log'     => '',
			'ref'     => 'gift',
			'limit'   => 0,
			'type'    => 'mycred_default'
		), $atts ) );

		if ( ! is_user_logged_in() && $user_id == '' )
			return $content;

		$mycred = mycred( $type );

		if ( $user_id == '' )
			$user_id = get_current_user_id();

		else
			$user_id = absint( $user_id );

		// Check for exclusion
		if ( $mycred->exclude_user( $user_id ) ) return;

		// Limit
		$limit = absint( $limit );
		if ( $limit != 0 && mycred_count_ref_instances( $ref, $user_id, $type ) >= $limit ) return;

		$mycred->add_creds(
			$ref,
			$user_id,
			$amount,
			$log,
			'',
			'',
			$type
		);

	}
endif;

/**
 * myCRED Shortcode: mycred_link
 * This shortcode allows you to award or deduct points from the current user
 * when their click on a link. The shortcode will generate an anchor element
 * and call the mycred-click-link jQuery script which will award the points.
 *
 * Note! Only HTML5 anchor attributes are supported and this shortcode is only
 * available if the hook is enabled!
 *
 * @see http://codex.mycred.me/shortcodes/mycred_link/
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_render_shortcode_link' ) ) :
	function mycred_render_shortcode_link( $atts, $content = ''	 ) {

		global $mycred_link_points;

		$atts = shortcode_atts( array(
			'id'       => '',
			'rel'      => '',
			'class'    => '',
			'href'     => '',
			'title'    => '',
			'target'   => '',
			'style'    => '',
			'amount'   => 0,
			'ctype'    => 'mycred_default',
			'hreflang' => '',
			'media'    => '',
			'type'     => '',
			'onclick'  => ''
		), $atts );

		// HREF is required
		if ( empty( $atts['href'] ) )
			return '<strong>' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Anchor missing URL!', 'mycred' );

		// All links must contain the 'mycred-points-link' class
		if ( empty( $atts['class'] ) )
			$atts['class'] = 'mycred-points-link';
		else
			$atts['class'] = 'mycred-points-link ' . $atts['class'];

		// If no id exists, make one
		if ( empty( $atts['id'] ) ) {
			$id = str_replace( array( 'http://', 'https://', 'http%3A%2F%2F', 'https%3A%2F%2F' ), 'hs', $atts['href'] );
			$id = str_replace( array( '/', '-', '_', ':', '.', '?', '=', '+', '\\', '%2F' ), '', $id );
			$atts['id'] = $id;
		}

		// Construct anchor attributes
		$attr = array();
		foreach ( $atts as $attribute => $value ) {
			if ( ! empty( $value ) && ! in_array( $attribute, array( 'amount', 'ctype' ) ) ) {
				$attr[] = $attribute . '="' . $value . '"';
			}
		}

		// Add key
		$token = mycred_create_token( array( $atts['amount'], $atts['ctype'], $atts['id'] ) );
		$attr[] = 'data-token="' . $token . '"';

		// Make sure jQuery script is called
		$mycred_link_points = true;

		// Return result
		return '<a ' . implode( ' ', $attr ) . '>' . $content . '</a>';

	}
endif;

/**
 * myCRED Shortcode: mycred_send
 * This shortcode allows the current user to send a pre-set amount of points
 * to a pre-set user. A simpler version of the mycred_transfer shortcode.
 * @see http://codex.mycred.me/shortcodes/mycred_send/ 
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_render_shortcode_send' ) ) :
	function mycred_render_shortcode_send( $atts, $content = '' ) {

		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => 0,
			'to'     => '',
			'log'    => '',
			'ref'    => 'gift',
			'type'   => 'mycred_default'
		), $atts ) );

		if ( $to == 'author' ) {
			// You can not use this outside the loop
			$author = get_the_author_meta( 'ID' );
			if ( empty( $author ) ) $author = $GLOBALS['post']->post_author;
			$to = $author;
		}

		// We will not render for ourselves.
		$user_id = get_current_user_id();
		if ( $to == $user_id ) return;

		global $mycred_sending_points;

		$mycred_sending_points = false;

		$mycred = mycred( $type );

		// Make sure current user or recipient is not excluded!
		if ( $mycred->exclude_user( $to ) || $mycred->exclude_user( $user_id ) ) return;

		$account_limit = $mycred->number( apply_filters( 'mycred_transfer_acc_limit', 0 ) );
		$balance = $mycred->get_users_cred( $user_id, $type );
		$amount = $mycred->number( $amount );

		// Insufficient Funds
		if ( $balance-$amount < $account_limit ) return;

		// We are ready!
		$mycred_sending_points = true;

		$render = '<input type="button" class="mycred-send-points-button button button-primary btn btn-primary" data-to="' . $to . '" data-ref="' . $ref . '" data-log="' . $log . '" data-amount="' . $amount . '" data-type="' . $type . '" value="' . $mycred->template_tags_general( $content ) . '" />';
		return apply_filters( 'mycred_send', $render, $atts, $content );

	}
endif;

/**
 * Load myCRED Send Points Footer
 * @since 0.1
 * @version 1.3
 */
if ( ! function_exists( 'mycred_send_shortcode_footer' ) ) :
	function mycred_send_shortcode_footer() {

		global $mycred_sending_points;

		if ( $mycred_sending_points === true || apply_filters( 'mycred_enqueue_send_js', false ) === true ) {

			$base = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'token'   => wp_create_nonce( 'mycred-send-points' )
			);

			$language = apply_filters( 'mycred_send_language', array(
				'working' => esc_attr__( 'Processing...', 'mycred' ),
				'done'    => esc_attr__( 'Sent', 'mycred' ),
				'error'   => esc_attr__( 'Error - Try Again', 'mycred' )
			) );

			wp_localize_script(
				'mycred-send-points',
				'myCREDsend',
				array_merge_recursive( $base, $language )
			);
			wp_enqueue_script( 'mycred-send-points' );

		}

	}
endif;

/**
 * myCRED Send Points Ajax
 * @since 0.1
 * @version 1.4
 */
if ( ! function_exists( 'mycred_shortcode_send_points_ajax' ) ) :
	function mycred_shortcode_send_points_ajax() {

		// Security
		check_ajax_referer( 'mycred-send-points', 'token' );

		$type = 'mycred_default';
		if ( isset( $_POST['type'] ) )
			$type = sanitize_text_field( $type );

		// Make sure the type exists
		$mycred_types = mycred_get_types();
		if ( ! array_key_exists( $type, $mycred_types ) ) die();

		// Prep
		$user_id = get_current_user_id();
		$recipient = (int) sanitize_text_field( $_POST['recipient'] );
		$reference = sanitize_text_field( $_POST['reference'] );
		$log_entry = strip_tags( trim( $_POST['log'] ), '<a>' );

		// No sending to ourselves
		if ( $user_id == $recipient )
			wp_send_json( 'error' );

		// Prep amount
		$mycred = mycred( $type );
		$amount = sanitize_text_field( $_POST['amount'] );
		$amount = $mycred->number( abs( $amount ) );

		// Check solvency
		$account_limit = $mycred->number( apply_filters( 'mycred_transfer_acc_limit', $mycred->zero() ) );
		$balance = $mycred->get_users_balance( $user_id, $type );
		$new_balance = $balance-$amount;

		// Insufficient Funds
		if ( $new_balance < $account_limit )
			die();

		// After this transfer our account will reach zero
		elseif ( $new_balance == $account_limit )
			$reply = 'zero';

		// Check if this is the last time we can do these kinds of amounts
		elseif ( $new_balance-$amount < $account_limit )
			$reply = 'minus';

		// Else everything is fine
		else
			$reply = 'done';

		// First deduct points
		$mycred->add_creds(
			$reference,
			$user_id,
			0-$amount,
			$log_entry,
			$recipient,
			array( 'ref_type' => 'user' ),
			$type
		);

		// Then add to recipient
		$mycred->add_creds(
			$reference,
			$recipient,
			$amount,
			$log_entry,
			$user_id,
			array( 'ref_type' => 'user' ),
			$type
		);

		// Share the good news
		wp_send_json( $reply );

	}
endif;

/**
 * myCRED Shortcode: mycred_video
 * This shortcode allows points to be given to the current user
 * for watchinga YouTube video.
 * @see http://codex.mycred.me/shortcodes/mycred_video/
 * @since 1.2
 * @version 1.2.1
 */
if ( ! function_exists( 'mycred_render_shortcode_video' ) ) :
	function mycred_render_shortcode_video( $atts ) {

		global $mycred_video_points;

		$hooks = mycred_get_option( 'mycred_pref_hooks', false );
		if ( $hooks === false ) return;
		$prefs = $hooks['hook_prefs']['video_view'];

		extract( shortcode_atts( array(
			'id'       => NULL,
			'width'    => 560,
			'height'   => 315,
			'amount'   => $prefs['creds'],
			'logic'    => $prefs['logic'],
			'interval' => $prefs['interval'],
			'ctype'    => 'mycred_default'
		), $atts ) );

		// ID is required
		if ( $id === NULL || empty( $id ) ) return __( 'A video ID is required for this shortcode', 'mycred' );

		// Interval
		if ( strlen( $interval ) < 3 )
			$interval = abs( $interval * 1000 );

		// Video ID
		$video_id = str_replace( '-', '__', $id );

		// Create key
		$key = mycred_create_token( array( 'youtube', $video_id, $amount, $logic, $interval ) );

		if ( ! isset( $mycred_video_points ) || ! is_array( $mycred_video_points ) )
			$mycred_video_points = array();

		// Construct YouTube Query
		$query = apply_filters( 'mycred_video_query_youtube', array(
			'enablejsapi' => 1,
			'version'     => 3,
			'playerapiid' => 'mycred_vvideo_v' . $video_id,
			'rel'         => 0,
			'controls'    => 1,
			'showinfo'    => 0
		), $atts, $video_id );

		// Construct Youtube Query Address
		$url = 'https://www.youtube.com/embed/' . $id;
		$url = add_query_arg( $query, $url );

		$mycred_video_points[] = 'youtube';

		// Make sure video source ids are unique
		$mycred_video_points = array_unique( $mycred_video_points );

		ob_start();

?>
<div class="mycred-video-wrapper youtube-video">
	<iframe id="mycred_vvideo_v<?php echo $video_id; ?>" class="mycred-video mycred-youtube-video" data-vid="<?php echo $video_id; ?>" src="<?php echo esc_url( $url ); ?>" width="<?php echo $width; ?>" height="<?php echo $height; ?>" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	<script type="text/javascript">
function mycred_vvideo_v<?php echo $video_id; ?>( state ) {
	duration[ "<?php echo $video_id; ?>" ] = state.target.getDuration();
	mycred_view_video( "<?php echo $video_id; ?>", state.data, "<?php echo $logic; ?>", "<?php echo $interval; ?>", "<?php echo $key; ?>", "<?php echo $ctype; ?>" );
}
	</script>
</div>
<?php

		$output = ob_get_contents();
		ob_end_clean();

		// Return the shortcode output
		return apply_filters( 'mycred_video_output', $output, $atts );

	}
endif;

/**
 * myCRED Shortcode: mycred_total_balance
 * This shortcode will return either the current user or a given users
 * total balance based on either all point types or a comma seperated list
 * of types.
 * @see http://codex.mycred.me/shortcodes/mycred_total_balance/
 * @since 1.4.3
 * @version 1.2.2
 */
if ( ! function_exists( 'mycred_render_shortcode_total' ) ) :
	function mycred_render_shortcode_total( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id' => '',
			'types'   => 'mycred_default',
			'raw'     => 0,
			'total'   => 0
		), $atts ) );

		// If user ID is not set, get the current users ID
		if ( ! is_user_logged_in() && $user_id == '' )
			return $content;

		if ( $user_id == '' )
			$user_id = get_current_user_id();

		// Get types
		$types_to_addup = array();
		$all = false;
		$existing_types = mycred_get_types();

		if ( $types == 'all' )
			$types_to_addup = array_keys( $existing_types );

		else {

			$types = explode( ',', $types );
			if ( ! empty( $types ) ) {
				foreach ( $types as $type_key ) {
					$type_key = sanitize_text_field( $type_key );
					if ( ! array_key_exists( $type_key, $existing_types ) ) continue;

					if ( ! in_array( $type_key, $types_to_addup ) )
						$types_to_addup[] = $type_key;
				}
			}

		}

		// In case we still have no types, we add the default one
		if ( empty( $types_to_addup ) )
			$types_to_addup = array( 'mycred_default' );

		// Add up all point type balances
		$total_balance = 0;
		foreach ( $types_to_addup as $type ) {

			// Get the balance for this type
			$mycred = mycred( $type );
			if ( $total == 1 )
				$balance = mycred_query_users_total( $user_id, $type );
			else
				$balance = $mycred->get_users_balance( $user_id, $type );

			$total_balance = $total_balance+$balance;

		}

		// If results should be formatted
		if ( $raw == 0 ) {

			$mycred = mycred();
			$total_balance = $mycred->format_number( $total_balance );

		}

		return apply_filters( 'mycred_total_balances_output', $total_balance, $atts );

	}
endif;

/**
 * myCRED Shortcode: mycred_exchange
 * This shortcode will return an exchange form allowing users to
 * exchange one point type for another.
 * @see http://codex.mycred.me/shortcodes/mycred_exchange/
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'mycred_render_shortcode_exchange' ) ) :
	function mycred_render_shortcode_exchange( $atts, $content = '' ) {

		if ( ! is_user_logged_in() ) return $content;

		extract( shortcode_atts( array(
			'from' => '',
			'to'   => '',
			'rate' => 1,
			'min'  => 1
		), $atts ) );

		if ( $from == '' || $to == '' ) return '';

		$types = mycred_get_types();
		if ( ! array_key_exists( $from, $types ) || ! array_key_exists( $to, $types ) ) return __( 'Point types not found.', 'mycred' );

		$user_id = get_current_user_id();

		$mycred_from = mycred( $from );
		if ( $mycred_from->exclude_user( $user_id ) )
			return sprintf( __( 'You are excluded from using %s.', 'mycred' ), $mycred_from->plural() );

		$balance = $mycred_from->get_users_balance( $user_id, $from );
		if ( $balance < $mycred_from->number( $min ) )
			return __( 'Your balance is too low to use this feature.', 'mycred' );

		$mycred_to = mycred( $to );
		if ( $mycred_to->exclude_user( $user_id ) )
			return sprintf( __( 'You are excluded from using %s.', 'mycred' ), $mycred_to->plural() );

		global $mycred_exchange;

		$token = mycred_create_token( array( $from, $to, $user_id, $rate, $min ) );

		ob_start();

?>
<style type="text/css">
#mycred-exchange table tr td { width: 50%; }
#mycred-exchange table tr td label { display: block; font-weight: bold; font-size: 12px; }
#mycred-exchange { margin-bottom: 24px; }
.alert-success { color: green; }
.alert-warning { color: red; }
</style>
<div class="mycred-exchange">
	<form action="" method="post">
		<h3><?php printf( __( 'Convert <span>%s</span> to <span>%s</span>', 'mycred' ), $mycred_from->plural(), $mycred_to->plural() ); ?></h3>

		<?php if ( isset( $mycred_exchange['message'] ) ) : ?>
		<div class="alert alert-<?php if ( $mycred_exchange['success'] ) echo 'success'; else echo 'warning'; ?>"><?php echo $mycred_exchange['message']; ?></div>
		<?php endif; ?>

		<table class="table">
			<tr>
				<td colspan="2">
					<label><?php printf( __( 'Your current %s balance', 'mycred' ), $mycred_from->singular() ); ?></label>
					<p><?php echo $mycred_from->format_creds( $balance ); ?></p>
				</td>
			</tr>
			<tr>
				<td>
					<label for="mycred-exchange-amount"><?php _e( 'Amount', 'mycred' ); ?></label>
					<input type="text" size="12" value="0" id="mycred-exchange-amount" name="mycred_exchange[amount]" />
					<?php if ( $min != 0 ) : ?><p><small><?php printf( __( 'Minimum %s', 'mycred' ), $mycred_from->format_creds( $min ) ); ?></small></p><?php endif; ?>
				</td>
				<td>
					<label for="exchange-rate"><?php _e( 'Exchange Rate', 'mycred' ); ?></label>
					<p><?php printf( __( '1 %s = <span class="rate">%s</span> %s', 'mycred' ), $mycred_from->singular(), $rate, $mycred_to->plural() ); ?></p>
				</td>
			</tr>
		</table>
		<input type="hidden" name="mycred_exchange[token]" value="<?php echo $token; ?>" />
		<input type="hidden" name="mycred_exchange[nonce]" value="<?php echo wp_create_nonce( 'mycred-exchange' ); ?>" />
		<input type="submit" class="btn btn-primary button button-primary" value="<?php _e( 'Exchange', 'mycred' ); ?>" />
		<div class="clear clearfix"></div>
	</form>
</div>
<?php

		$output = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'mycred_exchange_output', $output, $atts );

	}
endif;

/**
 * Affiliate Link
 * @since 1.5.3
 * @version 
 */
if ( ! function_exists( 'mycred_render_affiliate_link' ) ) :
	function mycred_render_affiliate_link( $atts, $content = '' ) {

		$type = 'mycred_default';
		if ( isset( $atts['type'] ) && $atts['type'] != '' )
			$type = $atts['type'];

		return apply_filters( 'mycred_affiliate_link_' . $type, '', $atts, $content );

	}
endif;

/**
 * Affiliate ID
 * @since 1.5.3
 * @version 
 */
if ( ! function_exists( 'mycred_render_affiliate_id' ) ) :
	function mycred_render_affiliate_id( $atts, $content = '' ) {

		$type = 'mycred_default';
		if ( isset( $atts['type'] ) && $atts['type'] != '' )
			$type = $atts['type'];

		return apply_filters( 'mycred_affiliate_id_' . $type, '', $atts, $content );

	}
endif;

/**
 * Hook Table
 * Renders a table of all the active hooks and how much a user can
 * earn / lose from each hook.
 * @since 1.6
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_render_shortcode_hook_table' ) ) :
	function mycred_render_shortcode_hook_table( $atts ) {

		extract( shortcode_atts( array(
			'type'    => 'mycred_default',
			'gains'   => 1,
			'user'    => '-user-',
			'post'    => '-post-',
			'comment' => '-comment-',
			'amount'  => '',
			'nothing' => __( 'No instances found for this point type', 'mycred' )
		), $atts ) );

		$types = mycred_get_types();
		if ( ! array_key_exists( $type, $types ) ) return __( 'Invalid point type', 'mycred' );

		$mycred = mycred( $type );

		$id = str_replace( '_', '-', $type );

		$prefs_key = 'mycred_pref_hooks';
		if ( $type != 'mycred_default' )
			$prefs_key .= '_' . $type;

		$applicable = array();

		$hooks = get_option( $prefs_key, false );
		if ( isset( $hooks['active'] ) && ! empty( $hooks['active'] ) ) {

			foreach ( $hooks['active'] as $active_hook_id ) {

				$hook_prefs = $hooks['hook_prefs'][ $active_hook_id ];

				// Single Instance
				if ( isset( $hook_prefs['creds'] ) ) {

					if ( ( $gains == 1 && $hook_prefs['creds'] > 0 ) || ( $gains == 0 && $hook_prefs['creds'] < 0 ) )
						$applicable[ $active_hook_id ] = $hook_prefs;

				}

				// Multiple Instances
				else {

					foreach ( $hook_prefs as $instance_id => $instance_prefs ) {

						if ( ! isset( $instance_prefs['creds'] ) ) continue;

						if ( ( $gains == 1 && $instance_prefs['creds'] > 0 ) || ( $gains == 0 && $instance_prefs['creds'] < 0 ) )
							$applicable[ $instance_id ] = $instance_prefs;

					}

				}

			}

		}


		ob_start();

		if ( ! empty( $applicable ) ) {

?>
<style type="text/css">
table.mycred-hook-table { width: 100%; }
table.mycred-hook-table th { font-weight: bold; }
.column-instance { width: auto; }
.column-amount { width: 20%; }
.column-limit { width: 20%; }
</style>
<table class="mycred-hook-table hook-table-<?php echo $id; ?>">
	<thead>
		<tr>
			<th class="column-instance"><?php _e( 'Instance', 'mycred' ); ?></th>
			<th class="column-amount"><?php _e( 'Amount', 'mycred' ); ?></th>
			<th class="column-limit"><?php _e( 'Limit', 'mycred' ); ?></th>
		</tr>
	</thead>
	<tbody>
<?php

			foreach ( $applicable as $id => $prefs ) {

				$log = $mycred->template_tags_general( $prefs['log'] );

				$log = strip_tags( $log );
				$log = str_replace( array( '%user_id%', '%user_name%', '%user_name_en%', '%display_name%', '%user_profile_url%', '%user_profile_link%', '%user_nicename%', '%user_email%', '%user_url%', '%balance%', '%balance_f%' ), $user, $log );
				$log = str_replace( array( '%post_title%', '%post_url%', '%link_with_title%', '%post_type%' ), $post, $log );
				$log = str_replace( array( 'comment_id', 'c_post_id', 'c_post_title', 'c_post_url', 'c_link_with_title' ), $comment, $log );
				$log = str_replace( array( '%cred%', '%cred_f%' ), $amount, $log );
				$log = apply_filters( 'mycred_hook_table_log', $log, $id, $prefs, $atts );

				$limit = '';
				if ( isset( $prefs['limit'] ) )
					$limit = $prefs['limit'];

				$creds = apply_filters( 'mycred_hook_table_creds', $mycred->format_creds( $prefs['creds'] ), $id, $prefs, $atts );

?>
		<tr>
			<td class="column-instance"><?php echo $log; ?></td>
			<td class="column-amount"><?php echo $creds; ?></td>
			<td class="column-limit"><?php echo mycred_translate_limit_code( $limit ); ?></td>
		</tr>
<?php

			}

?>
	</tbody>
</table>
<?php

		}
		else {
			echo '<p>' . $nothing . '</p>';
		}

		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'mycred_render_hook_table', $content, $atts );

	}
endif;

?>