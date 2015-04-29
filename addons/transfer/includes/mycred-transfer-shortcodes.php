<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Transfer Shortcode Render
 * @see http://mycred.me/functions/mycred_transfer_render/
 * @attribute $charge_from (int) optional user ID from whom the points to be deducted, defaults to current user
 * @attribute $pay_to (int) optional user ID to whom the transfer is made, if left empty the user will be able to search for a user
 * @attribute $show_balance (bool) set to true to show current users balance, defaults to true
 * @attribute $show_limit (bool) set to true to show current users limit. If limit is set to 'none' and $show_limit is set to true nothing will be returned
 * @since 0.1
 * @version 1.5
 */
if ( ! function_exists( 'mycred_transfer_render' ) ) :
	function mycred_transfer_render( $atts, $content = NULL ) {

		global $mycred_load_transfer;

		// Settings
		$mycred = mycred();
		$pref = $mycred->transfers;

		// Get Attributes
		extract( shortcode_atts( array(
			'button'       => '',
			'charge_from'  => '',
			'pay_to'       => '',
			'show_balance' => 0,
			'show_limit'   => 0,
			'ref'          => '',
			'placeholder'  => '',
			'types'        => $pref['types'],
			'excluded'     => ''
		), $atts ) );

		$output = '';
		$mycred_load_transfer = false;

		// If we are not logged in
		if ( ! is_user_logged_in() ) {

			if ( isset( $pref['templates']['login'] ) && ! empty( $pref['templates']['login'] ) )
				$output .= '<p class="mycred-transfer-login">' . $mycred->template_tags_general( $pref['templates']['login'] ) . '</p>';

			return $output;

		}

		if ( $ref == '' )
			$ref = 'transfer';

		// Who to charge
		$charge_other = false;
		if ( $charge_from == '' ) {
			$charge_other = true;
			$charge_from = get_current_user_id();
		}

		// Point Types
		if ( ! is_array( $types ) )
			$raw = explode( ',', $types );
		else
			$raw = $types;

		$clean = array();
		foreach ( $raw as $id ) {
			$clean[] = sanitize_text_field( $id );
		}
		$available_types = array();

		// Default
		if ( count( $clean ) == 1 && in_array( 'mycred_default', $clean ) ) {

			// Make sure user is not excluded
			if ( $mycred->exclude_user( $charge_from ) ) return '';

			$status = mycred_user_can_transfer( $charge_from, NULL, 'mycred_default', $ref );
			$my_balance = $mycred->get_users_cred( $charge_from );

			// Error. Not enough creds
			if ( $status === 'low' ) {
				if ( isset( $pref['errors']['low'] )  && ! empty( $pref['errors']['low'] ) ) {
					$no_cred = str_replace( '%limit%', $pref['limit']['limit'], $pref['errors']['low'] );
					$no_cred = str_replace( '%Limit%', ucwords( $pref['limit']['limit'] ), $no_cred );
					$no_cred = str_replace( '%left%',  $mycred->format_creds( $status ), $no_cred );
					$output .= '<p class="mycred-transfer-low">' . $mycred->template_tags_general( $no_cred ) . '</p>';
				}
				return $output;
			}

			// Error. Over limit
			if ( $status === 'limit' ) {
				if ( isset( $pref['errors']['over'] ) && ! empty( $pref['errors']['over'] ) ) {
					$no_cred = str_replace( '%limit%', $pref['limit']['limit'], $pref['errors']['over'] );
					$no_cred = str_replace( '%Limit%', ucwords( $pref['limit']['limit'] ), $no_cred );
					$no_cred = str_replace( '%left%',  $mycred->format_creds( $status ), $no_cred );
					$output .= '<p class="mycred-transfer-over">' . $mycred->template_tags_general( $no_cred ) . '</p>';
				}
				return $output;
			}

			$available_types['mycred_default'] = $mycred->plural();

		}

		// Multiple
		else {

			foreach ( $clean as $point_type ) {

				$points = mycred( $point_type );
				if ( $points->exclude_user( $charge_from ) ) continue;

				$status = mycred_user_can_transfer( $charge_from, NULL, $point_type, $ref );
				if ( $status === 'low' || $status === 'limit' ) continue;

				$available_types[ $point_type ] = $points->plural();
			}

			// User does not have access
			if ( count( $available_types ) == 0 )
				return $excluded;
		}

		// Flag for scripts & styles
		$mycred_load_transfer = true;

		// Placeholder
		if ( $pref['autofill'] == 'user_login' )
			$pln = __( 'username', 'mycred' );

		elseif ( $pref['autofill'] == 'user_email' )
			$pln = __( 'email', 'mycred' );

		$placeholder = apply_filters( 'mycred_transfer_to_placeholder', __( 'recipients %s', 'mycred' ), $pref, $mycred );
		$placeholder = sprintf( $placeholder, $pln );

		// Recipient Input field
		$to_input = '<input type="text" name="mycred-transfer-to" value="" aria-required="true" class="mycred-autofill" placeholder="' . $placeholder . '" />';

		// If recipient is set, pre-populate it with the recipients details
		if ( $pay_to != '' ) {

			$user = get_user_by( 'id', $pay_to );
			if ( $user !== false ) {
				$value = $user->display_name;
				if ( isset( $user->$pref['autofill'] ) )
					$value = $user->$pref['autofill'];

				$to_input = '<input type="text" name="mycred-transfer-to" value="' . $value . '" readonly="readonly" />';
			}

		}

		// If we only use one type, we might as well reload the myCRED_Settings object
		// since formating might differ
		if ( count( $clean ) == 1 )
			$mycred = mycred( $clean[0] );

		// Only use prefix / suffix if we have 1 type.
		if ( count( $clean ) == 1 ) {

			if ( ! empty( $mycred->before ) )
				$before = $mycred->before . ' ';

			else
				$before = '';

			if ( ! empty( $mycred->after ) )
				$after = ' ' . $mycred->after;

			else
				$after = '';

		}

		else {
			$before = $after = '';
		}

		// Select Point type
		if ( count( $available_types ) == 1 )
			$type_input = '<input type="hidden" name="mycred-transfer-type" value="' . $clean[0] . '" />';

		else {

			$type_input = '<select name="mycred-transfer-type" id="mycred-transfer-type" class="form-control">';
			foreach ( $available_types as $type => $plural ) {
				$type_input .= '<option value="' . $type . '">' . $plural . '</option>';
			}
			$type_input .= '</select>';

		}

		$extras = array();

		// Show Balance 
		if ( (bool) $show_balance === true && ! empty( $pref['templates']['balance'] ) && count( $available_types ) == 1 ) {
			$balance_text = str_replace( '%balance%', $mycred->format_creds( $my_balance ), $pref['templates']['balance'] );
			$extras[] = $mycred->template_tags_general( $balance_text );
		}

		// Show Limits
		if ( (bool) $show_limit === true && ! empty( $pref['templates']['limit'] ) && $pref['limit']['limit'] != 'none' && count( $available_types ) == 1 ) {
			$limit_text = str_replace( '%_limit%', $pref['limit']['limit'], $pref['templates']['limit'] );
			$limit_text = str_replace( '%limit%',  ucwords( $pref['limit']['limit'] ), $limit_text );
			$limit_text = str_replace( '%left%',   $mycred->format_creds( $status ), $limit_text );
			$extras[] = $mycred->template_tags_general( $limit_text );
		}

		if ( $button == '' )
			$button = $pref['templates']['button'];

		// Main output
		ob_start();

?>
<div class="mycred-transfer-cred-wrapper"<?php if ( $ref != '' ) echo ' id="transfer-form-' . $ref . '"'; ?>>
	<form class="mycred-transfer" method="post" action="">

		<?php do_action( 'mycred_transfer_form_start', $atts, $pref ); ?>

		<ol style="list-style-type:none;">
			<li class="mycred-send-to">
				<label><?php _e( 'To:', 'mycred' ); ?></label>
				<div class="transfer-to"><?php echo $to_input; ?></div>
				<?php do_action( 'mycred_transfer_form_to', $atts, $pref ); ?>

			</li>
			<li class="mycred-send-amount">
				<label><?php _e( 'Amount:', 'mycred' ); ?></label>
				<div class="transfer-amount"><?php echo $before; ?><input type="text" class="short" name="mycred-transfer-amount" value="<?php echo $mycred->zero(); ?>" size="8" aria-required="true" /><?php echo $after . ' ' . $type_input; ?></div> 
				<?php if ( $charge_other ) : ?><input type="hidden" name="mycred-charge-other" value="<?php absint( $charge_from ); ?>" /><?php endif; ?>
				<?php if ( $ref != '' ) : ?><input type="hidden" name="mycred-transfer-ref" value="<?php echo esc_attr( $ref ); ?>" /><?php endif; ?>
				<input type="submit" class="button button-primary button-large mycred-click btn btn-primary btn-lg"<?php if ( $pay_to == get_current_user_id() ) echo ' disabled="disabled"'; ?> value="<?php echo esc_attr( $button ); ?>" />

				<?php do_action( 'mycred_transfer_form_amount', $atts, $pref ); ?>

			</li>

			<?php if ( ! empty( $extras ) ) { ?>

			<li class="mycred-transfer-info">
				<p><?php echo implode( '</p><p>', $extras ); ?></p>
				<?php do_action( 'mycred_transfer_form_extra', $atts, $pref ); ?>

			</li>

			<?php } ?>

		</ol>

		<?php do_action( 'mycred_transfer_form_end', $atts, $pref ); ?>

		<div class="clear clearfix"></div>
	</form>
	<div class="clear clearfix clr"></div>
</div>
<?php

		$output = ob_get_contents();
		ob_end_clean();

		return do_shortcode( apply_filters( 'mycred_transfer_render', $output, $atts, $mycred ) );

	}
endif;

?>