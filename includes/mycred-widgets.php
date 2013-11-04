<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * Widget: myCRED Balance
 * @since 0.1
 * @version 1.3
 */
if ( !class_exists( 'myCRED_Widget_Balance' ) ) {
	class myCRED_Widget_Balance extends WP_Widget {

		/**
		 * Construct
		 */
		function myCRED_Widget_Balance() {
			$name = apply_filters( 'mycred_label', myCRED_NAME );
			// Basic details about our widget
			$widget_ops = array( 
				'classname'   => 'widget-my-cred',
				'description' => sprintf( __( 'Show the current users %s balance', 'mycred' ), strip_tags( $name ) )
			);
			$this->WP_Widget( 'mycred_widget_balance', sprintf( __( '%s Balance', 'mycred' ), $name ), $widget_ops );
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

				// Settings
				$mycred = mycred_get_settings();

				// If this is an excluded user we bail
				if ( $mycred->exclude_user( $user_id ) ) return;

				// Start
				echo $before_widget;

				// Title
				if ( !empty( $instance['title'] ) ) {
					echo $before_title;
					echo $mycred->template_tags_general( $instance['title'] );
					echo $after_title;
				}

				// Balance
				$balance = $mycred->get_users_cred( $user_id );
				if ( empty( $balance ) ) $balance = 0;

				$layout = $mycred->template_tags_amount( $instance['cred_format'], $balance );
				$layout = $mycred->template_tags_user( $layout, false, wp_get_current_user() );
				
				// Include Ranking
				if ( $instance['show_rank'] ) {
					if ( function_exists( 'mycred_get_users_rank' ) ) {
						$rank_name = mycred_get_users_rank( $user_id );
						$ranking = str_replace( '%rank%', $rank_name, $instance['rank_format'] );
						$ranking = str_replace( '%rank_logo%', mycred_get_rank_logo( $rank_name ), $ranking );
						$ranking = str_replace( '%ranking%', mycred_rankings_position( $user_id ), $ranking );
					}
					else {
						$ranking = str_replace( array( '%rank%', '%ranking%' ), mycred_rankings_position( $user_id ), $instance['rank_format'] );
					}
					$ranking = '<div class="myCRED-rank">' . $ranking . '</div>';
					$layout .= $ranking;
				}
				echo '<div class="myCRED-balance">' . $layout . '</div>';

				// If we want to include history
				if ( $instance['show_history'] ) {
					echo '<div class="myCRED-widget-history">';

					// Query Log
					$log = new myCRED_Query_Log( array(
						'user_id' => $user_id,
						'number'  => $instance['number']
					) );
					
					// Have results
					if ( $log->have_entries() ) {
						// Title
						if ( !empty( $instance['history_title'] ) ) {
							$history_title = $instance['history_title'];
							echo '<h3 class="widget-title">' . $mycred->template_tags_general( $history_title ) . '</h3>';
						}
						
						// Organized List
						echo '<ol class="myCRED-history">';
						$alt = 0;
						$date_format = get_option( 'date_format' );
						foreach ( $log->results as $entry ) {
							// Row Layout
							$layout = $instance['history_format'];
							$layout = str_replace( '%date%',  '<span class="date">' . date_i18n( $date_format ) . '</span>', $layout );
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

					// Settings
					$mycred = mycred_get_settings();

					// Title
					if ( !empty( $instance['title'] ) ) {
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
			$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : __( 'My Balance', 'mycred' );
			$cred_format = isset( $instance['cred_format'] ) ? esc_attr( $instance['cred_format'] ) : '%cred_f%';

			$show_history = isset( $instance['show_history'] ) ? $instance['show_history'] : 0;
			$history_title = isset( $instance['history_title'] ) ? $instance['history_title'] : __( '%plural% History', 'mycred' );
			$history_entry = isset( $instance['history_format'] ) ? esc_attr( $instance['history_format'] ) : '%entry% <span class="creds">%cred_f%</span>';
			$history_length = isset( $instance['number'] ) ? abs( $instance['number'] ) : 5;

			$show_rank = isset( $instance['show_rank'] ) ? $instance['show_rank'] : 0;
			$rank_format = isset( $instance['rank_format'] ) ? $instance['rank_format'] : '#%ranking%';
			$show_visitors = isset( $instance['show_visitors'] ) ? $instance['show_visitors'] : 0;
			$message = isset( $instance['message'] ) ? esc_attr( $instance['message'] ) : __( '<a href="%login_url_here%">Login</a> to view your balance.', 'mycred' );

			// CSS to help with show/hide
			$rank_format_class = $history_option_class = $visitor_option_class = '';
			if ( $show_rank )
				$rank_format_class = ' ex-field';
			if ( $show_history )
				$history_option_class = ' ex-field';
			if ( $show_visitors )
				$visitor_option_class = ' ex-field'; ?>

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
		<!-- Balance layout -->
		<p class="myCRED-widget-field">
			<label for="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>"><?php _e( 'Layout', 'mycred' ); ?>:</label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'cred_format' ) ); ?>" type="text" value="<?php echo esc_attr( $cred_format ); ?>" class="widefat" /><br />
			<small><?php _e( 'See the help tab for available template tags.', 'mycred' ); ?></small>
		</p>
		<!-- Ranking -->
		<p class="myCRED-widget-field">
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_rank' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_rank' ) ); ?>" value="1"<?php checked( $show_rank, 1 ); ?> class="checkbox" /> 
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_rank' ) ); ?>"><?php _e( 'Include users ranking', 'mycred' ); ?></label><br />
			<span class="mycred-hidden<?php echo $rank_format_class; ?>">
				<label for="<?php echo esc_attr( $this->get_field_id( 'rank_format' ) ); ?>"><?php _e( 'Format', 'mycred' ); ?>:</label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'rank_format' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'rank_format' ) ); ?>" type="text" value="<?php echo $rank_format; ?>" class="widefat" /><br />
				<small><?php _e( 'This will be appended after the balance. See the help tab for available template tags.', 'mycred' ); ?></small>
			</span>
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
				<small><?php _e( 'See the help tab for available template tags.', 'mycred' ); ?></small>
			</span>
		</p>
		<!-- Show to Visitors -->
		<p class="myCRED-widget-field">
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> 
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><?php _e( 'Show message when not logged in', 'mycred' ); ?></label><br />
			<span class="mycred-hidden<?php echo $visitor_option_class; ?>">
				<label for="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>"><?php _e( 'Message', 'mycred' ); ?>:</label><br />
				<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'message' ) ); ?>"><?php echo $message; ?></textarea><br />
				<small><?php _e( 'See the help tab for available template tags.', 'mycred' ); ?></small>
			</span>
		</p>
		<!-- Widget Admin Scripting -->
		<script type="text/javascript">//<![CDATA[
		jQuery(function($) {
			$(document).ready(function(){
				$('#<?php echo esc_attr( $this->get_field_id( 'show_rank' ) ); ?>').click(function(){
					// This > <label> > <br> > <span>
					$(this).next().next().next().toggleClass( 'ex-field' );
				});
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
			$mycred = mycred_get_settings();
			$instance = $old_instance;

			$instance['title'] = trim( $new_instance['title'] );
			$instance['cred_format'] = trim( $new_instance['cred_format'] );

			$instance['show_rank'] = ( isset( $new_instance['show_rank'] ) ) ? 1 : 0;
			$rank_format = trim( $new_instance['rank_format'] );

			if ( !function_exists( 'mycred_get_users_rank' ) ) 
				$instance['rank_format'] = str_replace( '%rank%', '%ranking%', $rank_format );
			else
				$instance['rank_format'] = $rank_format;

			$instance['show_history'] = ( isset( $new_instance['show_history'] ) ) ? 1 : 0;
			$instance['history_title'] = trim( $new_instance['history_title'] );
			$instance['history_format'] = trim( $new_instance['history_format'] );
			$instance['number'] = (int) $new_instance['number'];

			$instance['show_visitors'] = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
			$instance['message'] = $mycred->allowed_tags( trim( $new_instance['message'] ) );

			mycred_flush_widget_cache( 'mycred_widget_list' );
			return $instance;
		}
	}
}

/**
 * Widget: User List
 * @since 0.1
 * @version 1.1
 */
if ( !class_exists( 'myCRED_Widget_List' ) ) {
	class myCRED_Widget_List extends WP_Widget {

		/**
		 * Construct
		 */
		function myCRED_Widget_List() {
			$name = apply_filters( 'mycred_label', myCRED_NAME );
			// Basic details about our widget
			$widget_ops = array( 
				'classname'   => 'widget-mycred-list',
				'description' => sprintf( __( 'Show a list of users sorted by their %s balance', 'mycred' ), strip_tags( $name ) )
			);
			$this->WP_Widget( 'mycred_widget_list', sprintf( __( '%s List', 'mycred' ), $name ), $widget_ops );
			$this->alt_option_name = 'mycred_widget_list';
		}

		/**
		 * Widget Output
		 */
		function widget( $args, $instance ) {
			extract( $args, EXTR_SKIP );

			// Check if we want to show this to visitors
			if ( !$instance['show_visitors'] && !is_user_logged_in() ) return;

			// Get Rankings
			$args = array(
				'number'   => $instance['number'],
				'template' => $instance['text']
			);

			if ( isset( $instance['order'] ) )
				$args['order'] = $instance['order'];

			if ( isset( $instance['offset'] ) )
				$args['offset'] = $instance['offset'];

			$rankings = mycred_rankings( $args );
			if ( $rankings->have_results() ) {
				// Settings
				$mycred = mycred_get_settings();

				// Header
				echo $before_widget;

				// Title
				if ( !empty( $instance['title'] ) ) {
					echo $before_title;
					echo $mycred->template_tags_general( $instance['title'] );
					echo $after_title;
				}

				// Result
				$rankings->leaderboard();

				// Footer
				echo $after_widget;
			}
		}

		/**
		 * Outputs the options form on admin
		 */
		function form( $instance ) {
			// Defaults
			$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : __( 'Leaderboard', 'mycred' );
			$number = isset( $instance['number'] ) ? abs( $instance['number'] ) : 5;
			$show_visitors = isset( $instance['show_visitors'] ) ? 1 : 0;
			$text = isset( $instance['text'] ) ? esc_attr( $instance['text'] ) : '#%ranking% %user_profile_link% %cred_f%';
			$offset = isset( $instance['offset'] ) ? esc_attr( $instance['offset'] ) : 0;
			$order = isset( $instance['order'] ) ? esc_attr( $instance['order'] ) : 'DESC'; ?>

		<p class="myCRED-widget-field">
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title', 'mycred' ); ?>:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p class="myCRED-widget-field">
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> 
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><?php _e( 'Visible to non-members', 'mycred' ); ?></label>
		</p>
		<p class="myCRED-widget-field">
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Number of users', 'mycred' ); ?>:</label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo $number; ?>" size="3" class="align-right" />
		</p>
		<p class="myCRED-widget-field">
			<label for="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>"><?php _e( 'Row layout', 'mycred' ); ?>:</label>
			<textarea class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'text' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>" rows="3"><?php echo esc_attr( $text ); ?></textarea>
			<small><?php _e( 'See the help tab for available template tags.', 'mycred' ); ?></small>
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
<?php
		}

		/**
		 * Processes widget options to be saved
		 */
		function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
			$instance['number'] = (int) $new_instance['number'];
			$instance['title'] = trim( $new_instance['title'] );
			$instance['show_visitors'] = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
			
			$rank_format = trim( $new_instance['text'] );
			$instance['text'] = str_replace( '%rank%', '%ranking%', $rank_format );
			
			$instance['offset'] = $new_instance['offset'];
			$instance['order'] = $new_instance['order'];

			mycred_flush_widget_cache( 'mycred_widget_list' );
			return $instance;
		}
	}
}
?>