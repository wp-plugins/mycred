<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Shortcode: mycred_my_badges
 * Allows you to show the current users earned badges.
 * @since 1.5
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_render_my_badges' ) ) :
	function mycred_render_my_badges( $atts, $content = '' )
	{
		if ( ! is_user_logged_in() ) return $content;

		$user_id = get_current_user_id();
		$users_badges = mycred_get_users_badges( $user_id );

		extract( shortcode_atts( array(
			'show'   => 'earned',
			'width'  => '',
			'height' => ''
		), $atts ) );

		if ( $width != '' )
			$width = ' width="' . $width . '"';

		if ( $height != '' )
			$height = ' height="' . $height . '"';

		ob_start();

		echo '<div id="mycred-users-badges">';

		// Show only badges that we have earned
		if ( $show == 'earned' ) {

			if ( ! empty( $users_badges ) ) {

				foreach ( $users_badges as $badge_id )
					echo '<img src="' . get_post_meta( $badge_id, 'main_image', true ) . '"' . $width . $height . ' class="mycred-badge earned" alt="' . get_the_title( $badge_id ) . '" title="' . get_the_title( $badge_id ) . '" />';

			}

		}

		// Show all badges highlighting the ones we earned
		elseif ( $show == 'all' ) {

			$all_badges = mycred_get_badges();
			foreach ( $all_badges as $badge ) {

				echo '<div class="the-badge">';

				// User has earned badge
				if ( ! in_array( $badge->ID, $users_badges ) )
					echo '<img src="' . $badge->default_img . '"' . $width . $height . ' class="mycred-badge not-earned" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';

				// User has not earned badge
				else
					echo '<img src="' . $badge->main_img . '"' . $width . $height . ' class="mycred-badge earned" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';

				echo '</div>';

			}

		}
		echo '</div>';

		$output = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'mycred_my_badges', $output, $user_id );
	}
endif;

/**
 * Shortcode: mycred_badges
 * Allows you to show all published badges
 * @since 1.5
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_render_badges' ) ) :
	function mycred_render_badges( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'show'   => 'default',
			'title'  => 0,
			'requires' => 0,
			'width'  => '',
			'height' => ''
		), $atts ) );

		$all_badges = mycred_get_badges();

		if ( $width != '' )
			$width = ' width="' . $width . '"';

		if ( $height != '' )
			$height = ' height="' . $height . '"';

		ob_start();

		echo '<div id="mycred-all-badges">';

		if ( ! empty( $all_badges ) ) {

			foreach ( $all_badges as $badge ) {

				echo '<div class="the-badge">';

				if ( $title == 1 )
					echo '<h3>' . $badge->post_title . '</h3>';

				if ( $requires == 1 )
					echo '<p>' . mycred_display_badge_requirements( $badge->ID ) . '</p>';

				// Show default image
				if ( $show == 'default' )
					echo '<img src="' . $badge->default_img . '"' . $width . $height . ' class="mycred-badge dislay-default" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';

				// Show main image
				elseif ( $show == 'main' )
					echo '<img src="' . $badge->main_img . '"' . $width . $height . ' class="mycred-badge display-main" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';

				// Show both
				else {
					echo '<img src="' . $badge->default_img . '"' . $width . $height . ' class="mycred-badge dislay-default" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';
					echo '<img src="' . $badge->main_img . '"' . $width . $height . ' class="mycred-badge display-main" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';
				}
				echo '</div>';

			}

		}

		echo '</div>';

		$output = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'mycred_badges', $output );
	}
endif;
?>