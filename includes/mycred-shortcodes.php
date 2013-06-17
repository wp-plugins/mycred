<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED Shortcode: my_balance
 * Returns the current users balance.
 * @see http://mycred.me/shortcodes/mycred_my_balance/
 * @since 1.0.9
 * @version 1.0
 */
if ( !function_exists( 'mycred_render_shortcode_my_balance' ) ) {
	function mycred_render_shortcode_my_balance( $atts, $content = NULL )
	{
		extract( shortcode_atts( array(
			'login'      => NULL,
			'title'      => '',
			'title_el'   => 'h1',
			'balance_el' => 'div',
			'type'       => ''
		), $atts ) );

		// Not logged in
		if ( !is_user_logged_in() ) {
			if ( $login != NULL )
				return '<div class="mycred-not-logged-in">' . $login . '</div>';

			return;
		}

		$user_id = get_current_user_id();
		$mycred = mycred_get_settings();
		if ( $mycred->exclude_user( $user_id ) ) return;

		if ( !empty( $type ) )
			$mycred->cred_id = $type;
	
		$output = '<div class="mycred-my-balance-wrapper">';

		// Title
		if ( !empty( $title ) ) {
			$output .= '<' . $title_el . '>' . $title . '</' . $title_el . '>';
		}

		// Balance
		$balance = $mycred->get_users_cred( $user_id );
		$output .= '<' . $balance_el . '>' . $mycred->format_creds( $balance ) . '</' . $balance_el . '>';
		$output .= '</div>';

		return $output;
	}
}

/**
 * myCRED Shortcode: mycred_leaderboard
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_render_leaderboard' ) ) {
	function mycred_render_leaderboard( $atts, $content = NULL )
	{
		$attr = shortcode_atts( array(
			'number'   => '-1',
			'offset'   => 0,
			'order'    => 'DESC',
			'template' => '',
			'type'     => '',
			'nothing'  => __( 'Leaderboard is empty.', 'mycred' )
		), $atts );
		
		// Template can also be passed though the content
		if ( empty( $attr['template'] ) && $content !== NULL )
			$attr['template'] = do_shortcode( $content );
		
		// Points type
		if ( !empty( $attr['type'] ) ) {
			$attr['meta_key'] = $attr['type'];
			unset( $attr['type'] );
		}
		
		$rankings = mycred_rankings( $attr );

		// Have results
		if ( $rankings->have_results() )
			return $rankings->get_display();

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
 * This shortcode allows you to award or deduct points from the current user
 * when this shortcode is executed. You can insert this in page/post content
 * or in a template file. Note that users are awarded/deducted points each time
 * this shortcode exectutes!
 * @see 
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_render_shortcode_give' ) ) {
	function mycred_render_shortcode_give( $atts, $content )
	{
		if ( !is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => NULL,
			'log'    => '',
			'ref'    => 'gift',
			'limit'  => 0,
			'type'   => 'mycred_default'
		), $atts ) );
		
		if ( $amount === NULL )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Amount missing!', 'mycred' );

		if ( empty( $log ) )
			return '<strong>' . apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'error', 'mycred' ) . '</strong> ' . __( 'Log Template Missing!', 'mycred' );
		
		$mycred = mycred_get_settings();
		$user_id = get_current_user_id();
		
		// Check for exclusion
		if ( $mycred->exclude_user( $user_id ) ) return;

		// Limit
		$limit = abs( $limit );
		if ( $limit != 0 && mycred_count_ref_instances( $ref, $user_id ) >= $limit ) return;

		$amount = $mycred->number( $amount );
		$mycred->add_creds(
			$reference,
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
 * @version 1.0
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

		// Construct anchor attributes
		$attr = array();
		foreach ( $atts as $attribute => $value ) {
			if ( !empty( $value ) && $attribute != 'amount' ) {
				$attr[] = $attribute . '="' . $value . '"';
			}
		}
		// Add amount
		$attr[] = 'data-amount="' . $atts['amount'] . '"';

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
			if ( !is_single() ) return;
			$to = $GLOBALS['post']->post_author;
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
?>