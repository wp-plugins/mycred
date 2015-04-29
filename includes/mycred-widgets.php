<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Widget: myCRED Balance
 * @since 0.1
 * @version 1.4
 */
if ( ! class_exists( 'myCRED_Widget_Balance' ) ) :
	class myCRED_Widget_Balance extends WP_Widget {

		/**
		 * Construct
		 */
		function myCRED_Widget_Balance() {

			$name = mycred_label( true );

			// Basic details about our widget
			$widget_ops = array( 
				'classname'   => 'widget-my-cred',
				'description' => sprintf( __( 'Show the current users %s balance', 'mycred' ), $name )
			);

			$this->WP_Widget( 'mycred_widget_balance', sprintf( __( '(%s) My Balance', 'mycred' ), $name ), $widget_ops );
			$this->alt_option_name = 'mycred_widget_balance';

		}

		/**
		 * Widget Output
		 */
		function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			// If we are logged in
			if ( is_user_logged_in() ) {

				// Current user id
				$user_id = get_current_user_id();

				// Load myCRED Now
				if ( ! isset( $instance['type'] ) || $instance['type'] == '' )
					$instance['type'] = 'mycred_default';

				$mycred = mycred( $instance['type'] );

				// If this is an excluded user we bail
				if ( $mycred->exclude_user( $user_id ) ) return;

				// Start
				echo $before_widget;

				// Title
				if ( ! empty( $instance['title'] ) ) {
					echo $before_title;
					echo $mycred->template_tags_general( $instance['title'] );
					echo $after_title;
				}

				// Balance
				$balance = $mycred->get_users_cred( $user_id, $instance['type'] );
				if ( empty( $balance ) ) $balance = 0;

				$layout = $mycred->template_tags_amount( $instance['cred_format'], $balance );
				$layout = $mycred->template_tags_user( $layout, false, wp_get_current_user() );

				echo '<div class="myCRED-balance">' . $layout . '</div>';

				// If we want to include history
				if ( $instance['show_history'] ) {

					echo '<div class="myCRED-widget-history">';

					// Query Log
					$log = new myCRED_Query_Log( array(
						'user_id' => $user_id,
						'number'  => $instance['number'],
						'ctype'   => $instance['type']
					) );

					// Have results
					if ( $log->have_entries() ) {

						// Title
						if ( !empty( $instance['history_title'] ) ) {
							$history_title = $instance['history_title'];
							echo $before_title . $mycred->template_tags_general( $history_title ) . $after_title;
						}

						// Organized List
						echo '<ol class="myCRED-history">';
						$alt = 0;
						$date_format = get_option( 'date_format' );
						foreach ( $log->results as $entry ) {

							// Row Layout
							$layout = $instance['history_format'];
							$layout = str_replace( '%date%',  '<span class="date">' . date_i18n( $date_format, $entry->time ) . '</span>', $layout );
							$layout = str_replace( '%entry%', $mycred->parse_template_tags( $entry->entry, $entry ), $layout );

							$layout = $mycred->allowed_tags( $layout );
							$layout = $mycred->template_tags_general( $layout );
							$layout = $mycred->template_tags_amount( $layout, $entry->creds );

							// Alternating rows
							$alt = $alt+1;
							if ( $alt % 2 == 0 ) $class = 'row alternate';
							else $class = 'row';

							// Output list item
							echo '<li class="' . $class . '">' . $layout . '</li>';

						}
						echo '</ol>';

					}
					$log->reset_query();

					echo '</div>';
				}

				// End
				echo $after_widget;

			}

			// Visitor
			else {

				// If we want to show a message, then do so
				if ( $instance['show_visitors'] ) {

					echo $before_widget;

					$mycred = mycred( $instance['type'] );

					// Title
					if ( ! empty( $instance['title'] ) ) {
						echo $before_title;
						echo $mycred->template_tags_general( $instance['title'] );
						echo $after_title;
					}

					$message = $instance['message'];
					$message = $mycred->template_tags_general( $message );
					$message = $mycred->allowed_tags( $message );

					echo '<div class="myCRED-my-balance-message"><p>' . nl2br( $message ) . '</p></div>';
					echo $after_widget;

				}

			}

		}

		/**
		 * Outputs the options form on admin
		 */
		function form( $instance ) {

			// Defaults
			$title          = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : 'My Balance';
			$type           = isset( $instance['type'] ) ? $instance['type'] : 'mycred_default';
			$cred_format    = isset( $instance['cred_format'] ) ? esc_attr( $instance['cred_format'] ) : '%cred_f%';
			$show_history   = isset( $instance['show_history'] ) ? $instance['show_history'] : 0;
			$history_title  = isset( $instance['history_title'] ) ? $instance['history_title'] : '%plural% History';
			$history_entry  = isset( $instance['history_format'] ) ? esc_attr( $instance['history_format'] ) : '%entry% <span class="creds">%cred_f%</span>';
			$history_length = isset( $instance['number'] ) ? abs( $instance['number'] ) : 5;
			$show_visitors  = isset( $instance['show_visitors'] ) ? $instance['show_visitors'] : 0;
			$message        = isset( $instance['message'] ) ? esc_attr( $instance['message'] ) : '<a href="%login_url_here%">Login</a> to view your balance.';

			$mycred       = mycred( $type );
			$mycred_types = mycred_get_types();

			// CSS to help with show/hide
			$history_option_class = $visitor_option_class = '';
			if ( $show_history )
				$history_option_class = ' ex-field';

			if ( $show_visitors )
				$visitor_option_class = ' ex-field';

?>
<!-- Widget Admin Styling -->
<style type="text/css">
	p.myCRED-widget-field span { display: none; }
	p.myCRED-widget-field span.ex-field { display: block; padding: 6px 0; }
	p.myCRED-widget-field span textarea { width: 98%; min-height: 80px; }
</style>

<!-- Widget Options -->
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
</p>

<!-- Point Type -->
<?php if ( count( $mycred_types ) > 1 ) : ?>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php _e( 'Point Type', 'mycred' ); ?>:</label>
	<?php mycred_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
</p>
<?php else : ?>
	<?php mycred_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
<?php endif; ?>

<!-- Balance layout -->
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>"><?php _e( 'Layout', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'cred_format' ) ); ?>" type="text" value="<?php echo esc_attr( $cred_format ); ?>" class="widefat" /><br />
	<small><?php echo $mycred->available_template_tags( array( 'general', 'amount' ) ); ?></small>
</p>

<!-- History -->
<p class="myCRED-widget-field">
	<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_history' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>" value="1"<?php checked( $show_history, 1 ); ?> class="checkbox" /> 
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>"><?php _e( 'Include history', 'mycred' ); ?></label><br />
	<span class="mycred-hidden<?php echo $history_option_class; ?>">
		<label for="<?php echo esc_attr( $this->get_field_id( 'history_title' ) ); ?>"><?php _e( 'History Title', 'mycred' ); ?>:</label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'history_title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'history_title' ) ); ?>" type="text" value="<?php echo esc_attr( $history_title ); ?>" class="widefat" />
		<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Number of entires', 'mycred' ); ?>:</label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo $history_length; ?>" size="3" class="align-right" /><br />
		<label for="<?php echo esc_attr( $this->get_field_id( 'history_format' ) ); ?>"><?php _e( 'Row layout', 'mycred' ); ?>:</label>
		<textarea name="<?php echo esc_attr( $this->get_field_name( 'history_format' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'history_format' ) ); ?>" rows="3"><?php echo esc_attr( $history_entry ); ?></textarea>
		<small><?php echo $mycred->available_template_tags( array( 'general', 'widget' ) ); ?></small>
	</span>
</p>
<!-- Show to Visitors -->
<p class="myCRED-widget-field">
	<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> 
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><?php _e( 'Show message when not logged in', 'mycred' ); ?></label><br />
	<span class="mycred-hidden<?php echo $visitor_option_class; ?>">
		<label for="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>"><?php _e( 'Message', 'mycred' ); ?>:</label><br />
		<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'message' ) ); ?>"><?php echo $message; ?></textarea><br />
		<small><?php echo $mycred->available_template_tags( array( 'general', 'amount' ) ); ?></small>
	</span>
</p>
<!-- Widget Admin Scripting -->
<script type="text/javascript">//<![CDATA[
jQuery(function($) {
	$(document).ready(function(){

		$('#<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>').click(function(){
			$(this).next().next().next().toggleClass( 'ex-field' );
		});

		$('#<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>').click(function(){
			$(this).next().next().next().toggleClass( 'ex-field' );
		});
	});
});//]]>
</script>
<?php

		}

		/**
		 * Processes widget options to be saved
		 */
		function update( $new_instance, $old_instance ) {

			global $mycred;

			$instance = $old_instance;
			$allowed = $mycred->allowed_html_tags();

			$instance['title']          = wp_kses( $new_instance['title'], $allowed );
			$instance['type']           = sanitize_text_field( $new_instance['type'] );
			$instance['cred_format']    = wp_kses( $new_instance['cred_format'], $allowed );
			$instance['show_history']   = ( isset( $new_instance['show_history'] ) ) ? 1 : 0;
			$instance['history_title']  = wp_kses( $new_instance['history_title'], $allowed );
			$instance['history_format'] = wp_kses( $new_instance['history_format'], $allowed );
			$instance['number']         = absint( $new_instance['number'] );
			$instance['show_visitors']  = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
			$instance['message']        = wp_kses( $new_instance['message'], $allowed );

			mycred_flush_widget_cache( 'mycred_widget_balance' );
			return $instance;

		}

	}
endif;

/**
 * Widget: Leaderboard
 * @since 0.1
 * @version 1.3
 */
if ( ! class_exists( 'myCRED_Widget_Leaderboard' ) ) :
	class myCRED_Widget_Leaderboard extends WP_Widget {

		/**
		 * Construct
		 */
		function myCRED_Widget_Leaderboard() {

			$name = mycred_label( true );

			// Basic details about our widget
			$widget_ops = array( 
				'classname'   => 'widget-mycred-list',
				'description' => sprintf( __( 'Show a list of users sorted by their %s balance', 'mycred' ), $name )
			);

			$this->WP_Widget( 'mycred_widget_list', sprintf( __( '(%s) Leaderboard', 'mycred' ), $name ), $widget_ops );
			$this->alt_option_name = 'mycred_widget_list';

		}

		/**
		 * Widget Output
		 */
		function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			// Check if we want to show this to visitors
			if ( ! $instance['show_visitors'] && ! is_user_logged_in() ) return;

			if ( ! isset( $instance['type'] ) || empty( $instance['type'] ) )
				$instance['type'] = 'mycred_default';

			$mycred = mycred( $instance['type'] );

			// Get Rankings
			$args = array(
				'number'   => $instance['number'],
				'template' => $instance['text'],
				'type'     => $instance['type'],
				'based_on' => $instance['based_on']
			);

			if ( isset( $instance['order'] ) )
				$args['order'] = $instance['order'];

			if ( isset( $instance['offset'] ) )
				$args['offset'] = $instance['offset'];

			if ( isset( $instance['current'] ) )
				$args['current'] = 1;

			echo $before_widget;

			// Title
			if ( ! empty( $instance['title'] ) ) {
				echo $before_title;
				echo $mycred->template_tags_general( $instance['title'] );
				echo $after_title;
			}

			echo mycred_render_shortcode_leaderboard( $args );

			// Footer
			echo $after_widget;

		}

		/**
		 * Outputs the options form on admin
		 */
		function form( $instance ) {

			// Defaults
			$title         = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : 'Leaderboard';
			$type          = isset( $instance['type'] ) ? $instance['type'] : 'mycred_default';
			$based_on      = isset( $instance['based_on'] ) ? esc_attr( $instance['based_on'] ) : 'balance';

			$number        = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
			$show_visitors = isset( $instance['show_visitors'] ) ? $instance['show_visitors'] : 0;
			$text          = isset( $instance['text'] ) ? esc_attr( $instance['text'] ) : '#%position% %user_profile_link% %cred_f%';
			$offset        = isset( $instance['offset'] ) ? esc_attr( $instance['offset'] ) : 0;
			$order         = isset( $instance['order'] ) ? esc_attr( $instance['order'] ) : 'DESC';
			$current       = isset( $instance['current'] ) ? $instance['current'] : 0;

			$mycred       = mycred( $type );
			$mycred_types = mycred_get_types();

?>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title', 'mycred' ); ?>:</label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
</p>

<?php if ( count( $mycred_types ) > 1 ) : ?>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php _e( 'Point Type', 'mycred' ); ?>:</label>
	<?php mycred_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
</p>
<?php else : ?>
	<?php mycred_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
<?php endif; ?>

<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'based_on' ) ); ?>"><?php _e( 'Based On', 'mycred' ); ?>:</label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'based_on' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'based_on' ) ); ?>" type="text" value="<?php echo esc_attr( $based_on ); ?>" />
	<small><?php _e( 'Use "balance" to base the leaderboard on your users current balances or use a specific reference.', 'mycred' ); ?> <a href="http://codex.mycred.me/reference-guide/log-references/" target="_blank"><?php _e( 'Reference Guide', 'mycred' ); ?></a></small>
</p>

<p class="myCRED-widget-field">
	<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> 
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><?php _e( 'Visible to non-members', 'mycred' ); ?></label>	</p>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Number of users', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo $number; ?>" size="3" class="align-right" />
</p>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>"><?php _e( 'Row layout', 'mycred' ); ?>:</label>
	<textarea class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'text' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>" rows="3"><?php echo esc_attr( $text ); ?></textarea>
	<small><?php echo $mycred->available_template_tags( array( 'general', 'balance' ) ); ?></small>
</p>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>"><?php _e( 'Offset', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'offset' ) ); ?>" type="text" value="<?php echo $offset; ?>" size="3" class="align-right" /><br />
	<small><?php _e( 'Optional offset of order. Use zero to return the first in the list.', 'mycred' ); ?></small>
</p>
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"><?php _e( 'Order', 'mycred' ); ?>:</label> 
	<select name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>">
<?php

			$options = array(
				'ASC' => __( 'Ascending', 'mycred' ),
				'DESC' => __( 'Descending', 'mycred' )
			);

			foreach ( $options as $value => $label ) {
				echo '<option value="' . $value . '"';
				if ( $order == $value ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>
	</select>
</p>
<p class="myCRED-widget-field">
	<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'current' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'current' ) ); ?>" value="1"<?php checked( $current, 1 ); ?> class="checkbox" /> 
	<label for="<?php echo esc_attr( $this->get_field_id( 'current' ) ); ?>"><?php _e( 'Append current users position', 'mycred' ); ?></label><br />
	<small><?php _e( 'If the current user is not in this leaderboard, you can select to append them at the end with their current position.', 'mycred' ); ?></small>
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

			$instance['number']        = absint( $new_instance['number'] );
			$instance['title']         = wp_kses( $new_instance['title'], $allowed );
			$instance['type']          = sanitize_key( $new_instance['type'] );
			$instance['based_on']      = sanitize_key( $new_instance['based_on'] );
			$instance['show_visitors'] = ( isset( $new_instance['show_visitors'] ) ) ? $new_instance['show_visitors'] : 0;
			$instance['text']          = wp_kses( $new_instance['text'], $allowed );
			$instance['offset']        = sanitize_text_field( $new_instance['offset'] );
			$instance['order']         = sanitize_text_field( $new_instance['order'] );
			$instance['current']       = ( isset( $new_instance['current'] ) ) ? absint( $new_instance['current'] ) : 0;

			mycred_flush_widget_cache( 'mycred_widget_list' );

			return $instance;

		}

	}
endif;

/**
 * Widget: myCRED Wallet
 * @since 1.4
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Widget_Wallet' ) ) :
	class myCRED_Widget_Wallet extends WP_Widget {

		/**
		 * Construct
		 */
		function myCRED_Widget_Wallet() {

			// Basic details about our widget
			$widget_ops = array( 
				'classname'   => 'widget-my-wallet',
				'description' => __( 'Shows the current users balances for each point type.', 'mycred' )
			);

			$this->WP_Widget( 'mycred_widget_wallet', sprintf( __( '(%s) Wallet', 'mycred' ), mycred_label( true ) ), $widget_ops );
			$this->alt_option_name = 'mycred_widget_wallet';

		}

		/**
		 * Widget Output
		 */
		function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			$mycred = mycred();

			// If we are logged in
			if ( is_user_logged_in() ) {

				// Current user id
				$user_id = get_current_user_id();

				// Start
				echo $before_widget;

				// Title
				if ( ! empty( $instance['title'] ) ) {
					echo $before_title;
					echo $mycred->template_tags_general( $instance['title'] );
					echo $after_title;
				}

				$mycred_types = mycred_get_types();
				if ( ! empty( $mycred_types ) && ! empty( $instance['types'] ) ) {
					foreach ( $mycred_types as $type => $label ) {

						$type_setup = mycred( $type );

						// If user is excluded from using this point type
						if ( $type_setup->exclude_user( $user_id ) ) continue;

						// If type is selected
						if ( ! in_array( $type, (array) $instance['types'] ) ) continue;

						$balance = $type_setup->get_users_balance( $user_id, $type );
						$row_template = $type_setup->template_tags_general( $instance['row'] );
						$row_template = $type_setup->template_tags_amount( $row_template, $balance );
						$row_template = str_replace( '%label%', $label, $row_template );
						echo '<div class="balance ' . $type . '">' . $row_template . '</div>';

					}
				}

				// End
				echo $after_widget;

			}

			// Visitor
			elseif ( ! is_user_logged_in() && $instance['show_visitors'] ) {

				echo $before_widget;

				// Title
				if ( ! empty( $instance['title'] ) ) {
					echo $before_title;
					echo $mycred->template_tags_general( $instance['title'] );
					echo $after_title;
				}

				$message = $instance['message'];
				$message = $mycred->template_tags_general( $message );

				echo '<div class="myCRED-wallet-message"><p>' . nl2br( $message ) . '</p></div>';
				echo $after_widget;

			}

		}

		/**
		 * Outputs the options form on admin
		 */
		function form( $instance ) {

			$mycred = mycred();

			// Defaults
			$title         = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : 'My Wallet';
			$types         = isset( $instance['types'] ) ? $instance['types'] : array();
			$row_template  = isset( $instance['row'] ) ? $instance['row'] : '%label%: %cred_f%';
			$show_visitors = isset( $instance['show_visitors'] ) ? $instance['show_visitors'] : 0;
			$message       = isset( $instance['message'] ) ? esc_attr( $instance['message'] ) : '<a href="%login_url_here%">Login</a> to view your balance.';

?>

<!-- Widget Options -->
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
</p>

<!-- Point Type -->
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'types' ) ); ?>"><?php _e( 'Point Types', 'mycred' ); ?>:</label><br />
	<?php mycred_types_select_from_checkboxes( $this->get_field_name( 'types' ) . '[]', $this->get_field_id( 'types' ), $types ); ?>
</p>

<!-- Row layout -->
<p class="myCRED-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'row' ) ); ?>"><?php _e( 'Row Layout', 'mycred' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'row' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'row' ) ); ?>" type="text" value="<?php echo esc_attr( $row_template ); ?>" class="widefat" /><br />
	<small><?php echo $mycred->available_template_tags( array( 'general', 'amount' ) ); ?></small>
</p>

<!-- Show to Visitors -->
<p class="myCRED-widget-field">
	<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> 
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><?php _e( 'Show message when not logged in', 'mycred' ); ?></label><br />
	<label for="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>"><?php _e( 'Message', 'mycred' ); ?>:</label><br />
	<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'message' ) ); ?>"><?php echo $message; ?></textarea><br />
	<small><?php echo $mycred->available_template_tags( array( 'general', 'amount' ) ); ?></small>
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

			$instance['title']         = wp_kses( $new_instance['title'], $allowed );
			$instance['types']         = (array) $new_instance['types'];
			$instance['row']           = wp_kses( $new_instance['row'], $allowed );
			$instance['show_visitors'] = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
			$instance['message']       = wp_kses( $new_instance['message'], $allowed );

			mycred_flush_widget_cache( 'mycred_widget_wallet' );

			return $instance;

		}

	}
endif;
?>