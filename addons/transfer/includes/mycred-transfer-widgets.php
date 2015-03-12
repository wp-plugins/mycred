<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Widget: myCRED Transfer
 * @since 0.1
 * @version 1.1.2
 */
if ( ! class_exists( 'myCRED_Widget_Transfer' ) ) :
	class myCRED_Widget_Transfer extends WP_Widget {

		/**
		 * Construct
		 */
		function myCRED_Widget_Transfer() {

			// Basic details about our widget
			$widget_ops = array( 
				'classname'   => 'widget-my-cred-transfer',
				'description' => __( 'Allow transfers between users.', 'mycred' )
			);

			$this->WP_Widget( 'mycred_widget_transfer', sprintf( __( '(%s) Transfer', 'mycred' ), mycred_label( true ) ), $widget_ops );
			$this->alt_option_name = 'mycred_widget_transfer';

		}

		/**
		 * Widget Output
		 */
		function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			// Prep
			$title = $instance['title'];
			$mycred = mycred();

			if ( ! isset( $mycred->transfers ) )
				return '<p>' . __( 'The myCRED Transfer add-on has not yet been setup!', 'mycred' ) . '</p>';

			$pref = $mycred->transfers;

			global $mycred_load_transfer;

			// Members
			if ( is_user_logged_in() ) {

				// Excluded users
				$user_id = get_current_user_id();
				if ( $mycred->exclude_user( $user_id ) ) return;

				echo $before_widget;

				// Title
				if ( ! empty( $title ) ) {
					echo $before_title;
					echo $mycred->template_tags_general( $title );
					echo $after_title;
				}

				// Prep shortcode
				$attr = array(
					'show_balance' => $instance['show_balance'],
					'show_limit'   => $instance['show_limit']
				);

				if ( isset( $instance['button'] ) && ! empty( $instance['button'] ) )
					$attr['button'] = $instance['button'];

				echo mycred_transfer_render( $attr, '' );

				$mycred_load_transfer = true;
				echo $after_widget;

			}

			// Visitors
			else {

				$mycred_load = false;

				// If login message is set
				if ( ! empty( $pref['templates']['login'] ) ) {

					echo $before_widget;
					if ( ! empty( $instance['title'] ) ) {
						echo $before_title;
						echo $mycred->template_tags_general( $title );
						echo $after_title;
					}

					// Show login message
					echo '<p>' . $mycred->template_tags_general( $pref['templates']['login'] ) . '</p>';
					echo $after_widget;

				}
				return;

			}

		}

		/**
		 * Outputs the options form on admin
		 */
		function form( $instance ) {

			// Defaults
			$title        = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : 'Transfer %plural%';
			$show_balance = isset( $instance['show_balance'] ) ? $instance['show_balance'] : 0;
			$show_limit   = isset( $instance['show_limit'] ) ? $instance['show_balance'] : 0;
			$button       = isset( $instance['button'] ) ? esc_attr( $instance['button'] ) : 'Transfer';

?>
<!-- Widget Options -->
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
</p>
<p class="myCRED-widget-field">
	<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_balance' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_balance' ) ); ?>" value="1"<?php checked( $show_balance, true ); ?> class="checkbox" /> 
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_balance' ) ); ?>"><?php _e( 'Show users balance', 'mycred' ); ?></label>
</p>
<p class="myCRED-widget-field">
	<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_limit' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_limit' ) ); ?>" value="1"<?php checked( $show_balance, true ); ?> class="checkbox" /> 
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_limit' ) ); ?>"><?php _e( 'Show users limit', 'mycred' ); ?></label>
</p>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>"><?php _e( 'Button Label', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'button' ) ); ?>" type="text" value="<?php echo esc_attr( $button ); ?>" class="widefat" />
</p>
<?php

		}

		/**
		 * Processes widget options to be saved
		 */
		function update( $new_instance, $old_instance ) {

			global $mycred;

			$instance = $old_instance;
			$allowed = $mycred->allowed_html_tags();

			$instance['title']        = wp_kses( $new_instance['title'], $allowed );
			$instance['show_balance'] = ( isset( $new_instance['show_balance'] ) ) ? $new_instance['show_balance'] : 0;
			$instance['show_limit']   = ( isset( $new_instance['show_limit'] ) ) ? $new_instance['show_balance'] : 0;
			$instance['button']       = sanitize_text_field( $new_instance['button'] );

			mycred_flush_widget_cache( 'mycred_widget_transfer' );

			return $instance;

		}

	}
endif;

?>