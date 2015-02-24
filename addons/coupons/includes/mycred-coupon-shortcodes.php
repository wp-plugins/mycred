<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Load Coupon Shortcode
 * @filter mycred_get_coupon_by_code
 * @action mycred_load_coupon
 * @since 1.4
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_render_shortcode_load_coupon' ) ) :
	function mycred_render_shortcode_load_coupon( $atts, $content = NULL ) {
		if ( ! is_user_logged_in() )
			return $content;

		$mycred = mycred();
		if ( ! isset( $mycred->coupons ) )
			return '<p><strong>Coupon Add-on settings are missing! Please visit the myCRED > Settings page to save your settings before using this shortcode.</strong></p>';

		// Prep
		$output = '
<div class="mycred-coupon-form">';
		$user_id = get_current_user_id();

		// No show for excluded users
		if ( $mycred->exclude_user( $user_id ) ) return '';

		// On submits
		if ( isset( $_POST['mycred_coupon_load']['token'] ) && wp_verify_nonce( $_POST['mycred_coupon_load']['token'], 'mycred-load-coupon' . $user_id ) ) {

			$coupon = mycred_get_coupon_post( $_POST['mycred_coupon_load']['couponkey'] );
			$load = mycred_use_coupon( $_POST['mycred_coupon_load']['couponkey'], $user_id );

			// Coupon does not exist
			if ( $load === 'missing' )
				$output .= '<p class="mycred-coupon-status">' . $mycred->coupons['invalid'] . '</p>';

			// Coupon has expired
			elseif ( $load === 'expired' )
				$output .= '<p class="mycred-coupon-status">' . $mycred->coupons['expired'] . '</p>';

			// User limit reached
			elseif ( $load === 'max' )
				$output .= '<p class="mycred-coupon-status">' . $mycred->coupons['user_limit'] . '</p>';

			// Failed minimum balance requirement
			elseif ( $load === 'min_balance' ) {
				$min = get_post_meta( $coupon->ID, 'min', true );
				$template = str_replace( '%min%', $min, $mycred->coupons['min'] );
				$output .= '<p class="mycred-coupon-status">' . $template . '</p>';
			}

			// Failed maximum balance requirement
			elseif ( $load === 'max_balance' ) {
				$max = get_post_meta( $coupon->ID, 'max', true );
				$template = str_replace( '%max%', $max, $mycred->coupons['max'] );
				$output .= '<p class="mycred-coupon-status">' . $template . '</p>';
			}

			// Success
			else
				$output .= '<p class="mycred-coupon-status">' . $mycred->coupons['success'] . '</p>';

		}

		$output .= '
	<form action="" method="post">
		<p>
			<label for="mycred-coupon-code">' . __( 'Coupon', 'mycred' ) . '</label><br />
			<input type="text" name="mycred_coupon_load[couponkey]" id="mycred-coupon-couponkey" value="" /> 
			<input type="hidden" name="mycred_coupon_load[token]" value="' . wp_create_nonce( 'mycred-load-coupon' . $user_id ) . '" />
			<input type="submit" class="btn btn-primary btn-large button button-large button-primary" value="' . __( 'Apply Coupon', 'mycred' ) . '" />
		</p>
	</form>
</div>';

		return apply_filters( 'mycred_load_coupon', $output, $atts, $content );
	}
endif;
?>