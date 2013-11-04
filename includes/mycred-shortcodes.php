<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED Shortcode: my_balance
 * Returns the current users balance.
 * @see http://mycred.me/shortcodes/mycred_my_balance/
 * @contributor Ian Tasker
 * @since 1.0.9
 * @version 1.1
 */
if ( !function_exists( 'mycred_render_shortcode_my_balance' ) ) {
	function mycred_render_shortcode_my_balance( $atts, $content = NULL )
	{
		extract( shortcode_atts( array(
			'login'      => NULL,
			'title'      => '',
			'title_el'   => 'h1',
			'balance_el' => 'div',
			'wrapper'    => 1,
			'type'       => ''
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
		$mycred = mycred_get_settings();
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
		
		$balance = $mycred->get_users_cred( $user_id );
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
 * @see http://mycred.me/shortcodes/mycred_history/
 * @since 1.0.9
 * @version 1.0
 */
if ( !function_exists( 'mycred_render_shortcode_history' ) ) {
	function mycred_render_shortcode_history( $atts ) {
		extract( shortcode_atts( array(
			'user_id'   => NULL,
			'number'    => NULL,
			'time'      => NULL,
			'ref'       => NULL,
			'order'     => NULL,
			'show_user' => false,
			'login'     => ''
		), $atts ) );

		// If we are not logged in
		if ( !is_user_logged_in() && !empty( $login ) ) return '<p class="mycred-history login">' . $login . '</p>';

		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		$args = array();
		$args['user_id'] = $user_id;

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
 * @since 0.1
 * @version 1.2
 */
if ( !function_exists( 'mycred_render_leaderboard' ) ) {
	function mycred_render_leaderboard( $atts, $content = NULL )
	{
		$attr = shortcode_atts( array(
			'number'   => '-1',
			'order'    => 'DESC',
			'offset'   => 0,
			'type'     => 'mycred_default',
			'wrap'     => 'li',
			'template' => '#%ranking% %user_profile_link% %cred_f%',
			'nothing'  => __( 'Leaderboard is empty.', 'mycred' )
		), $atts );
		
		// Template can also be passed though the content
		if ( empty( $attr['template'] ) || $content !== NULL )
			$attr['template'] = do_shortcode( $content );
		
		$_attr = $attr;
		$_attr['user_fields'] = 'user_login,display_name,user_email,user_nicename,user_url';
		unset( $_attr['wrap'] );
		unset( $_attr['nothing'] );
		$rankings = mycred_rankings( $_attr );

		// Have results
		if ( $rankings->have_results() ) {
			// Default organized list
			if ( $attr['wrap'] == 'li' )
				return $rankings->get_leaderboard();
			// Just the loop for custom header and footer
			else
				return $rankings->loop( $attr['wrap'] );
		}

		// No result template is set
		if ( !empty( $attr['nothing'] ) )
			return '<p class="mycred-leaderboard-none">' . $attr['nothing'] . '</p>';
	}
}

/**
 * myCRED Shortcode: mycred_my_ranking
 * @since 0.1
 * @version 1.1
 */
if ( !function_exists( 'mycred_render_my_ranking' ) ) {
	function mycred_render_my_ranking( $atts, $content )
	{
		extract( shortcode_atts( array(
			'user_id'  => NULL
		), $atts ) );
		
		// If no id is given
		if ( $user_id === NULL ) {
			// Current user must be logged in for this shortcode to work
			if ( !is_user_logged_in() ) return;
			// Get current user id
			$user_id = get_current_user_id();
		}
		
		return mycred_rankings_position( $user_id );
	}
}

/**
 * myCRED Shortcode: mycred_give
 * This shortcode allows you to award or deduct points from a given user or the current user
 * when this shortcode is executed. You can insert this in page/post content
 * or in a template file. Note that users are awarded/deducted points each time
 * this shortcode exectutes!
 * @see 
 * @since 1.1
 * @version 1.1
 */
if ( !function_exists( 'mycred_render_shortcode_give' ) ) {
	function mycred_render_shortcode_give( $atts, $content )
	{
		if ( !is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount'  => NULL,
			'user_id' => '',
			'log'     => '',
			'ref'     => 'gift',
			'limit'   => 0,
			'type'    => 'mycred_default'
		), $atts ) );
		
		if ( $amount === NULL )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Amount missing!', 'mycred' );

		if ( empty( $log ) )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Log Template Missing!', 'mycred' );
		
		$mycred = mycred_get_settings();
		
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
 * @see http://mycred.me/shortcodes/mycred_link/
 * @since 1.1
 * @version 1.1
 */
if ( !function_exists( 'mycred_render_shortcode_link' ) ) {
	function mycred_render_shortcode_link( $atts, $content )
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
			'hreflang' => '',   // for advanced users
			'media'    => '',   // for advanced users
			'type'     => ''    // for advanced users
		), $atts );

		// HREF is required
		if ( empty( $atts['href'] ) )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Anchor missing URL!', 'mycred' );

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
			if ( !empty( $value ) && $attribute != 'amount' ) {
				$attr[] = $attribute . '="' . $value . '"';
			}
		}

		// Add key
		require_once( myCRED_INCLUDES_DIR . 'mycred-protect.php' );
		$protect = new myCRED_Protect();
		$data = $atts['amount'] . ':' . $atts['id'];
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
 * @see 
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_render_shortcode_send' ) ) {
	function mycred_render_shortcode_send( $atts, $content )
	{
		if ( !is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => NULL,
			'to'     => NULL,
			'log'    => '',
			'ref'    => 'gift',
			'type'   => 'mycred_default'
		), $atts ) );
		
		// Amount is required
		if ( $amount === NULL )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Amount missing!', 'mycred' );

		// Recipient is required
		if ( empty( $to ) )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'User ID missing for recipient.', 'mycred' );
		
		// Log template is required
		if ( empty( $log ) )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Log Template Missing!', 'mycred' );
		
		if ( $to == 'author' ) {
			// You can not use this outside the loop
			$author = get_the_author_meta( 'ID' );
			if ( empty( $author ) ) $author = $GLOBALS['post']->post_author;
			$to = $author;
		}
		
		global $mycred_sending_points;
		
		$mycred = mycred_get_settings();
		$user_id = get_current_user_id();
		
		// Make sure current user or recipient is not excluded!
		if ( $mycred->exclude_user( $to ) || $mycred->exclude_user( $user_id ) ) return;
		
		$account_limit = (int) apply_filters( 'mycred_transfer_acc_limit', 0 );
		$balance = $mycred->get_users_cred( $user_id );
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
if ( !function_exists( 'mycred_send_shortcode_footer' ) ) {
	add_action( 'wp_footer', 'mycred_send_shortcode_footer' );
	function mycred_send_shortcode_footer() {
		global $mycred_sending_points;

		if ( $mycred_sending_points === true ) {
			$mycred = mycred_get_settings();
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
 * @version 1.2
 */
if ( !function_exists( 'mycred_shortcode_send_points_ajax' ) ) {
	add_action( 'wp_ajax_mycred-send-points', 'mycred_shortcode_send_points_ajax' );
	function mycred_shortcode_send_points_ajax() {
		// We must be logged in
		if ( !is_user_logged_in() ) die();

		// Security
		check_ajax_referer( 'mycred-send-points', 'token' );
			
		$mycred = mycred_get_settings();
		$user_id = get_current_user_id();
			
		$account_limit = (int) apply_filters( 'mycred_transfer_acc_limit', 0 );
		$balance = $mycred->get_users_cred( $user_id );
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
			array( 'ref_type' => 'user' )
		);
			
		// Then add to recipient
		$mycred->add_creds(
			trim( $_POST['reference'] ),
			$_POST['recipient'],
			$amount,
			trim( $_POST['log'] ),
			$user_id,
			array( 'ref_type' => 'user' )
		);
			
		// Share the good news
		die( json_encode( $reply ) );
	}
}

/**
 * myCRED Shortcode: mycred_video
 * This shortcode allows points to be given to the current user
 * for watchinga YouTube video.
 * @see http://mycred.me/shortcodes/mycred_video/
 * @since 1.2
 * @version 1.0
 */
if ( !function_exists( 'mycred_render_shortcode_video' ) ) {
	function mycred_render_shortcode_video( $atts, $content )
	{
		global $mycred_video_points;

		extract( shortcode_atts( array(
			'id'     => NULL,
			'width'  => 0,
			'height' => 0,
			'amount' => 'def',
			'logic'  => 'def',
			'interval' => 'def'
		), $atts ) );

		// ID is required
		if ( $id === NULL ) return __( 'A video ID is required for this shortcode', 'mycred' );

		// Width
		if ( $width == 0 )
			$width = 560;

		// Height
		if ( $height == 0 )
			$height = 315;

		// Prep Interval by converting it to Miliseconds
		if ( $interval != 'def' )
			$interval = $interval*1000;

		// Video ID
		$video_id = str_replace( '-', '__', $id );
		
		// Construct YouTube Query
		$query = apply_filters( 'mycred_video_query', array(
			'enablejsapi' => 1,
			'version'     => 3,
			'playerapiid' => $video_id,
			'rel'         => 0,
			'controls'    => 1,
			'showinfo'    => 0
		), $atts, $video_id );
		
		// Construct Youtube Query Address
		$url = 'http://www.youtube.com/v/' . $id;
		$url = add_query_arg( $query, $url );
		
		// Construct Flash Embed
		$embed_args = apply_filters( 'mycred_video_embed_args', array(
			'"' . $url . '"', '"' . $video_id . '"', '"' . $width . '"', '"' . $height . '"', '"9.0.0"', 'null', 'null', '{ allowScriptAccess: "always", wmode: "transparent" }', 'null'
		), $atts );

		// Output
		return apply_filters( 'mycred_video_output', '
<div class="mycred-video-wrapper">
<script type="text/javascript">
swfobject.embedSWF(' . implode( ', ', $embed_args ) . ');
</script>
	<div id="' . $video_id . '_container">
		<div id="' . $video_id . '"></div>
	</div>
<script type="text/javascript">
function mycred_video_' . $video_id . '(state) {
	mycred_youtube_state( "' . $video_id . '", state, "' . $amount . '", "' . $logic . '", "' . $interval . '" );
}
</script>
</div>' . "\n", $atts, $embed_args, $video_id );
	}
}
?>