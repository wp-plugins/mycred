<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Shortcode: mycred_my_badges
 * Allows you to show the current users earned badges.
 * @since 1.5
 * @version 1.1.1
 */
if ( ! function_exists( 'mycred_render_my_badges' ) ) :
	function mycred_render_my_badges( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'show'    => 'earned',
			'width'   => MYCRED_BADGE_WIDTH,
			'height'  => MYCRED_BADGE_HEIGHT,
			'user_id' => ''
		), $atts ) );

		if ( ! is_user_logged_in() && $user_id == '' ) return $content;

		if ( $user_id == '' )
			$user_id = get_current_user_id();

		$users_badges = mycred_get_users_badges( $user_id );

		if ( $width != '' )
			$width = ' width="' . $width . '"';

		if ( $height != '' )
			$height = ' height="' . $height . '"';

		ob_start();

		echo '<div id="mycred-users-badges">';

		// Show only badges that we have earned
		if ( $show == 'earned' ) {

			if ( ! empty( $users_badges ) ) {

				foreach ( $users_badges as $badge_id => $level ) {

					$level_image = get_post_meta( $badge_id, 'level_image' . $level, true );
					if ( $level_image == '' )
						$level_image = get_post_meta( $badge_id, 'main_image', true );

					echo apply_filters( 'mycred_my_badge', '<img src="' . $level_image . '"' . $width . $height . ' class="mycred-badge earned" alt="' . get_the_title( $badge_id ) . '" title="' . get_the_title( $badge_id ) . '" />', $badge_id, $level, $user_id, $atts );

				}

			}

		}

		// Show all badges highlighting the ones we earned
		elseif ( $show == 'all' ) {

			$all_badges = mycred_get_badges();
			foreach ( $all_badges as $badge ) {

				echo '<div class="the-badge">';

				// User has not earned badge
				if ( ! array_key_exists( $badge->ID, $users_badges ) ) {

					if ( $badge->default_img != '' )
						echo '<img src="' . $badge->default_img . '"' . $width . $height . ' class="mycred-badge not-earned" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';

				}

				// User has  earned badge
				else {

					$level_image = get_post_meta( $badge->ID, 'level_image' . $users_badges[ $badge->ID ], true );
					if ( $level_image == '' )
						$level_image = $badge->main_img;

					echo '<img src="' . $badge->main_img . '"' . $width . $height . ' class="mycred-badge earned" alt="' . $badge->post_title . '" title="' . $badge->post_title . '" />';
				}

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
 * @version 1.0.2
 */
if ( ! function_exists( 'mycred_render_badges' ) ) :
	function mycred_render_badges( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'show'     => 'default',
			'title'    => 0,
			'requires' => 0,
			'show_count' => 0,
			'width'    => MYCRED_BADGE_WIDTH,
			'height'   => MYCRED_BADGE_HEIGHT
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
					echo '<h3 class="badge-title">' . $badge->post_title . '</h3>';

				if ( $requires == 1 )
					echo '<div class="badge-requirements">' . mycred_display_badge_requirements( $badge->ID ) . '</div>';

				if ( $show_count == 1 )
					echo '<div class="users-with-badge">' . mycred_count_users_with_badge( $badge->ID ) . '</div>';

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