<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED Shortcode: my_balance
 * Returns the current users balance.
 * @see http://codex.mycred.me/shortcodes/mycred_my_balance/
 * @contributor Ian Tasker
 * @since 1.0.9
 * @version 1.2.1
 */
if ( ! function_exists( 'mycred_render_shortcode_my_balance' ) ) {
	function mycred_render_shortcode_my_balance( $atts )
	{
		extract( shortcode_atts( array(
			'login'      => NULL,
			'title'      => '',
			'title_el'   => 'h1',
			'balance_el' => 'div',
			'wrapper'    => 1,
			'type'       => 'mycred_default'
		), $atts ) );

		$output = '';

		// Not logged in
		if ( ! is_user_logged_in() ) {
			if ( $login !== NULL ) {
				if ( $wrapper )
					$output .= '<div class="mycred-not-logged-in">';
				
				$output .= $login;
				
				if ( $wrapper )
					$output .= '</div>';
				
				return $output;
			}
			return;
		}

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
}

/**
 * myCRED Shortcode: mycred_history
 * Returns the points history.
 * @see http://codex.mycred.me/shortcodes/mycred_history/
 * @since 1.0.9
 * @version 1.1.1
 */
if ( ! function_exists( 'mycred_render_shortcode_history' ) ) {
	function mycred_render_shortcode_history( $atts ) {
		extract( shortcode_atts( array(
			'user_id'   => NULL,
			'number'    => NULL,
			'time'      => NULL,
			'ref'       => NULL,
			'order'     => NULL,
			'show_user' => false,
			'login'     => '',
			'type'      => 'mycred_default'
		), $atts ) );

		// If we are not logged in
		if ( ! is_user_logged_in() && ! empty( $login ) ) return '<p class="mycred-history login">' . $login . '</p>';

		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		$args = array(
			'user_id' => $user_id,
			'ctype'   => $type
		);

		if ( $number !== NULL )
			$args['number'] = $number;

		if ( $time !== NULL )
			$args['time'] = $time;

		if ( $ref !== NULL )
			$args['ref'] = $ref;

		if ( $order !== NULL )
			$args['order'] = $order;

		$log = new myCRED_Query_Log( $args );

		if ( $show_user !== true )
			unset( $log->headers['column-username'] ); 

		$result = $log->get_display();
		$log->reset_query();
		return $result;
	}
}

/**
 * myCRED Shortcode: mycred_leaderboard
 * @see http://codex.mycred.me/shortcodes/mycred_leaderboard/
 * @since 0.1
 * @version 1.3.3
 */
if ( ! function_exists( 'mycred_render_leaderboard' ) ) {
	function mycred_render_leaderboard( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'number'   => '-1',
			'order'    => 'DESC',
			'offset'   => 0,
			'type'     => 'mycred_default',
			'wrap'     => 'li',
			'template' => '#%position% %user_profile_link% %cred_f%',
			'nothing'  => __( 'Leaderboard is empty.', 'mycred' ),
			'current'  => 0
		), $atts ) );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		if ( $number != '-1' )
			$limit = 'LIMIT ' . absint( $offset ) . ',' . absint( $number );
		else
			$limit = '';

		global $wpdb;
		$leaderboard = $wpdb->get_results( $wpdb->prepare( apply_filters( 'mycred_ranking_sql', "
			SELECT DISTINCT u.ID, um.meta_value AS cred 
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um
				ON ( u.ID = um.user_id )
			WHERE um.meta_key = %s  
			ORDER BY um.meta_value+0 {$order} {$limit};", $atts ), $type ), 'ARRAY_A' );

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

				// Classes
				$class[] = 'item-' . $position;
				if ( $position == 0 )
					$class[] = 'first-item';

				if ( $position % 2 != 0 )
					$class[] = 'alt';

				if ( ! empty( $content ) )
					$template = $content;

				// Template Tags
				if ( ! function_exists( 'mycred_get_users_rank' ) )
					$layout = str_replace( array( '%rank%', '%ranking%', '%position%' ), $position+1, $template );
				else
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
				$full_leaderboard = $wpdb->get_results( $wpdb->prepare( "
					SELECT u.ID 
					FROM {$wpdb->users} u
					INNER JOIN {$wpdb->usermeta} um
						ON ( u.ID = um.user_id )
					WHERE um.meta_key = %s  
					ORDER BY um.meta_value+0 {$order};", $type ), 'ARRAY_A' );

				if ( ! empty( $full_leaderboard ) ) {

					// Get current users position
					$current_position = array_search( array( 'ID' => $current_user->ID ), $full_leaderboard );
					$full_leaderboard = NULL;

					// If position is found
					if ( $current_position !== false ) {

						// Template Tags
						if ( ! function_exists( 'mycred_get_users_rank' ) )
							$layout = str_replace( array( '%rank%', '%ranking%', '%position%' ), $current_position+1, $template );
						else
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
}

/**
 * myCRED Shortcode: mycred_my_ranking
 * @see http://codex.mycred.me/shortcodes/mycred_my_ranking/
 * @since 0.1
 * @version 1.3
 */
if ( ! function_exists( 'mycred_render_my_ranking' ) ) {
	function mycred_render_my_ranking( $atts )
	{
		extract( shortcode_atts( array(
			'user_id'  => NULL,
			'ctype'    => 'mycred_default'
		), $atts ) );
		
		// If no id is given
		if ( $user_id === NULL ) {
			// Current user must be logged in for this shortcode to work
			if ( ! is_user_logged_in() ) return;
			// Get current user id
			$user_id = get_current_user_id();
		}

		// If no type is given
		if ( $ctype == '' )
			$ctype = 'mycred_default';

		global $wpdb;

		// Get a complete leaderboard with just user IDs
		$full_leaderboard = $wpdb->get_results( $wpdb->prepare( "
			SELECT u.ID 
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um
				ON ( u.ID = um.user_id )
			WHERE um.meta_key = %s  
			ORDER BY um.meta_value+0 DESC;", $ctype ), 'ARRAY_A' );

		$position = 0;
		if ( ! empty( $full_leaderboard ) ) {

			// Get current users position
			$current_position = array_search( array( 'ID' => $user_id ), $full_leaderboard );
			$position = $current_position+1;

		}

		$full_leaderboard = NULL;

		return apply_filters( 'mycred_get_leaderboard_position', $position, $user_id, $ctype );
	}
}

/**
 * myCRED Shortcode: mycred_give
 * This shortcode allows you to award or deduct points from a given user or the current user
 * when this shortcode is executed. You can insert this in page/post content
 * or in a template file. Note that users are awarded/deducted points each time
 * this shortcode exectutes!
 * @see http://codex.mycred.me/shortcodes/mycred_give/
 * @since 1.1
 * @version 1.1.1
 */
if ( ! function_exists( 'mycred_render_shortcode_give' ) ) {
	function mycred_render_shortcode_give( $atts )
	{
		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount'  => NULL,
			'user_id' => '',
			'log'     => '',
			'ref'     => 'gift',
			'limit'   => 0,
			'type'    => 'mycred_default'
		), $atts ) );
		
		if ( $amount === NULL )
			return '<strong>' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Amount missing!', 'mycred' );

		if ( empty( $log ) )
			return '<strong>' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Log Template Missing!', 'mycred' );
		
		$mycred = mycred();
		
		if ( empty( $user_id ) )
			$user_id = get_current_user_id();
		
		// Check for exclusion
		if ( $mycred->exclude_user( $user_id ) ) return;

		// Limit
		$limit = abs( $limit );
		if ( $limit != 0 && mycred_count_ref_instances( $ref, $user_id ) >= $limit ) return;

		$amount = $mycred->number( $amount );
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
}

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
if ( ! function_exists( 'mycred_render_shortcode_link' ) ) {
	function mycred_render_shortcode_link( $atts, $content = ''	 )
	{
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
			'hreflang' => '',   // for advanced users
			'media'    => '',   // for advanced users
			'type'     => ''    // for advanced users
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
			if ( !empty( $value ) && ! in_array( $attribute, array( 'amount', 'ctype' ) ) ) {
				$attr[] = $attribute . '="' . $value . '"';
			}
		}

		// Add key
		require_once( myCRED_INCLUDES_DIR . 'mycred-protect.php' );
		$protect = new myCRED_Protect();
		$data = $atts['amount'] . ':' . $atts['ctype'] . ':' . $atts['id'];
		$key = $protect->do_encode( $data );
		$attr[] = 'data-key="' . $key . '"';

		// Make sure jQuery script is called
		$mycred_link_points = true;

		// Return result
		return '<a ' . implode( ' ', $attr ) . '>' . $content . '</a>';
	}
}

/**
 * myCRED Shortcode: mycred_send
 * This shortcode allows the current user to send a pre-set amount of points
 * to a pre-set user. A simpler version of the mycred_transfer shortcode.
 * @see http://codex.mycred.me/shortcodes/mycred_send/ 
 * @since 1.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_render_shortcode_send' ) ) {
	function mycred_render_shortcode_send( $atts, $content = NULL )
	{
		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => NULL,
			'to'     => NULL,
			'log'    => '',
			'ref'    => 'gift',
			'type'   => 'mycred_default'
		), $atts ) );
		
		// Amount is required
		if ( $amount === NULL )
			return '<strong>' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Amount missing!', 'mycred' );

		// Recipient is required
		if ( empty( $to ) )
			return '<strong>' . __( 'error', 'mycred' ) . '</strong> ' . __( 'User ID missing for recipient.', 'mycred' );
		
		// Log template is required
		if ( empty( $log ) )
			return '<strong>' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Log Template Missing!', 'mycred' );
		
		if ( $to == 'author' ) {
			// You can not use this outside the loop
			$author = get_the_author_meta( 'ID' );
			if ( empty( $author ) ) $author = $GLOBALS['post']->post_author;
			$to = $author;
		}
		
		global $mycred_sending_points;
		
		$mycred = mycred( $type );
		$user_id = get_current_user_id();
		
		// Make sure current user or recipient is not excluded!
		if ( $mycred->exclude_user( $to ) || $mycred->exclude_user( $user_id ) ) return;
		
		$account_limit = (int) apply_filters( 'mycred_transfer_acc_limit', 0 );
		$balance = $mycred->get_users_cred( $user_id, $type );
		$amount = $mycred->number( $amount );
		
		// Insufficient Funds	
		if ( $balance-$amount < $account_limit ) return;
		
		// We are ready!
		$mycred_sending_points = true;

		return '<input type="button" class="mycred-send-points-button" data-to="' . $to . '" data-ref="' . $ref . '" data-log="' . $log . '" data-amount="' . $amount . '" data-type="' . $type . '" value="' . $mycred->template_tags_general( $content ) . '" />';
	}
}

/**
 * Load myCRED Send Points Footer
 * @since 0.1
 * @version 1.2
 */
if ( ! function_exists( 'mycred_send_shortcode_footer' ) ) {
	add_action( 'wp_footer', 'mycred_send_shortcode_footer' );
	function mycred_send_shortcode_footer() {
		global $mycred_sending_points;

		if ( $mycred_sending_points === true ) {
			$mycred = mycred();
			$base = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'token'   => wp_create_nonce( 'mycred-send-points' )
			);

			$language = apply_filters( 'mycred_send_language', array(
				'working' => __( 'Processing...', 'mycred' ),
				'done'    => __( 'Sent', 'mycred' ),
				'error'   => __( 'Error - Try Again', 'mycred' )
			) );
			wp_localize_script(
				'mycred-send-points',
				'myCREDsend',
				array_merge_recursive( $base, $language )
			);
			wp_enqueue_script( 'mycred-send-points' );
		}
	}
}

/**
 * myCRED Send Points Ajax
 * @since 0.1
 * @version 1.3
 */
if ( ! function_exists( 'mycred_shortcode_send_points_ajax' ) ) {
	add_action( 'wp_ajax_mycred-send-points', 'mycred_shortcode_send_points_ajax' );
	function mycred_shortcode_send_points_ajax() {
		// We must be logged in
		if ( ! is_user_logged_in() ) die();

		// Security
		check_ajax_referer( 'mycred-send-points', 'token' );

		$mycred_types = mycred_get_types();
		$type = 'mycred_default';
		if ( isset( $_POST['type'] ) )
			$type = sanitize_text_field( $type );
		
		if ( ! array_key_exists( $type, $mycred_types ) ) die();

		$mycred = mycred( $type );
		$user_id = get_current_user_id();
			
		$account_limit = (int) apply_filters( 'mycred_transfer_acc_limit', 0 );
		$balance = $mycred->get_users_cred( $user_id, $type );
		$amount = $mycred->number( $_POST['amount'] );
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
			trim( $_POST['reference'] ),
			$user_id,
			0-$amount,
			trim( $_POST['log'] ),
			$_POST['recipient'],
			array( 'ref_type' => 'user' ),
			$type
		);
			
		// Then add to recipient
		$mycred->add_creds(
			trim( $_POST['reference'] ),
			$_POST['recipient'],
			$amount,
			trim( $_POST['log'] ),
			$user_id,
			array( 'ref_type' => 'user' ),
			$type
		);
			
		// Share the good news
		wp_send_json( $reply );
	}
}

/**
 * myCRED Shortcode: mycred_video
 * This shortcode allows points to be given to the current user
 * for watchinga YouTube video.
 * @see http://codex.mycred.me/shortcodes/mycred_video/
 * @since 1.2
 * @version 1.1.1
 */
if ( ! function_exists( 'mycred_render_shortcode_video' ) ) {
	function mycred_render_shortcode_video( $atts )
	{
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
			'interval' => $prefs['interval']
		), $atts ) );

		// ID is required
		if ( $id === NULL || empty( $id ) ) return __( 'A video ID is required for this shortcode', 'mycred' );

		// Interval
		if ( strlen( $interval ) < 3 )
			$interval = abs( $interval * 1000 );

		// Video ID
		$video_id = str_replace( '-', '__', $id );

		// Create key
		$protect = mycred_protect();
		$key = $protect->do_encode( 'youtube:' . $video_id . ':' . $amount . ':' . $logic . ':' . $interval );

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

		ob_start(); ?>

<div class="mycred-video-wrapper youtube-video">
	<iframe id="mycred_vvideo_v<?php echo $video_id; ?>" class="mycred-video mycred-youtube-video" data-vid="<?php echo $video_id; ?>" src="<?php echo $url; ?>" width="<?php echo $width; ?>" height="<?php echo $height; ?>" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	<script type="text/javascript">
function mycred_vvideo_v<?php echo $video_id; ?>( state ) {
	duration[ "<?php echo $video_id; ?>" ] = state.target.getDuration();
	mycred_view_video( "<?php echo $video_id; ?>", state.data, "<?php echo $logic; ?>", "<?php echo $interval; ?>", "<?php echo $key; ?>" );
}
	</script>
</div>
<?php
		$output = ob_get_contents();
		ob_end_clean();
		
		// Return the shortcode output
		return apply_filters( 'mycred_video_output', $output, $atts );
	}
}

/**
 * myCRED Shortcode: mycred_total_balance
 * This shortcode will return either the current user or a given users
 * total balance based on either all point types or a comma seperated list
 * of types.
 * @see http://codex.mycred.me/shortcodes/mycred_total_balance/
 * @since 1.4.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_render_shortcode_total' ) ) {
	function mycred_render_shortcode_total( $atts, $content = '' ) {
		
		extract( shortcode_atts( array(
			'user_id' => NULL,
			'types'   => 'mycred_default',
			'raw'     => 0
		), $atts ) );

		// If user ID is not set, get the current users ID
		if ( $user_id === NULL ) {
			// If user is not logged in bail now
			if ( ! is_user_logged_in() ) return $content;
			$user_id = get_current_user_id();
		}

		// Get types
		$_types = array();
		$all = false;
		if ( ! empty( $types ) ) {
			// If we set types to "all" we get all registered types now
			if ( $types == 'all' ) {
				$types = mycred_get_types();
				$all = true;
			}

			// Compile an array of type ids
			foreach ( $types as $key => $value ) {
				if ( $all )
					$_types[] = $key;
				else {
					$type = sanitize_text_field( $value );
					if ( $type != '' )
						$_types[] = $type;
				}
			}
		}

		// In case we still have no types, we add the default one
		if ( empty( $_types ) )
			$_types[] = 'mycred_default';

		// Add up all point type balances
		$total = 0;
		foreach ( $_types as $type ) {
			// Get the balance for this type
			$balance = get_user_meta( $user_id, $type, true );
			// Continue if the balance is not set
			if ( empty( $balance ) ) continue;

			$total = $total+$balance;
		}

		// If we want the total unformatted return this now
		if ( $raw )
			return $total;

		// Return formatted
		return apply_filters( 'mycred_total_balances_output', mycred_format_creds( $total ), $atts );
	}
}
?>