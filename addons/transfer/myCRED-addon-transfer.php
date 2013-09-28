<?php
/**
 * Addon: Transfer
 * Addon URI: http://mycred.me/add-ons/transfer/
 * Version: 1.0
 * Description: Allow your users to send or "donate" points to other members by either using the mycred_transfer shortcode or the myCRED Transfer widget.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
// Translate Header (by Dan bp-fr)
$mycred_addon_header_translate = array(
	__( 'Transfer', 'mycred' ),
	__( 'Allow your users to send or "donate" points to other members by either using the mycred_transfer shortcode or the myCRED Transfer widget.', 'mycred' )
);

if ( !defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_TRANSFER',         __FILE__ );
define( 'myCRED_TRANSFER_VERSION', myCRED_VERSION . '.1' );
/**
 * myCRED_Transfer_Creds class
 *
 * Manages this add-on by hooking into myCRED where needed. Regsiters our custom shortcode and widget
 * along with scripts and styles needed. Also adds settings to the myCRED settings page.
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Transfer_Creds' ) ) {
	class myCRED_Transfer_Creds extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Transfer_Creds', array(
				'module_name' => 'transfers',
				'defaults'    => array(
					'logs'       => array(
						'sending'   => 'Transfer of %plural% to %display_name%',
						'receiving' => 'Transfer of %plural% from %display_name%'
					),
					'errors'     => array(
						'low'       => __( 'You do not have enough %plural% to send.', 'mycred' ),
						'over'      => __( 'You have exceeded your %limit% transfer limit.', 'mycred' )
					),
					'templates'  => array(
						'login'     => '',
						'balance'   => 'Your current balance is %balance%',
						'limit'     => 'Your current %limit% transfer limit is %left%',
						'button'    => __( 'Transfer', 'mycred' )
					),
					'limit'      => array(
						'amount'    => 1000,
						'limit'     => 'none'
					)
				),
				'register'    => false,
				'add_to_core' => true
			) );

			add_action( 'mycred_help',              array( $this, 'help' ), 10, 2          );
			add_filter( 'mycred_email_before_send', array( $this, 'email_notices' ), 10, 2 );
		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_init() {
			// Call Scripts & Styles when needed
			add_shortcode( 'mycred_transfer',            'mycred_transfer_render'                 );
			add_action( 'wp_footer',                     array( $this, 'front_footer' )           );

			// Register Scripts & Styles
			add_action( 'mycred_front_enqueue',          array( $this, 'front_enqueue' )          );

			// Ajax Calls
			add_action( 'wp_ajax_mycred-transfer-creds', array( $this, 'ajax_call_transfer' )     );
			add_action( 'wp_ajax_mycred-autocomplete',   array( $this, 'ajax_call_autocomplete' ) );
		}

		/**
		 * Register Widgets
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_widgets_init() {
			register_widget( 'myCRED_Widget_Transfer' );
		}

		/**
		 * Enqueue Front
		 * @filter 'mycred_remove_transfer_css'
		 * @since 0.1
		 * @version 1.0
		 */
		public function front_enqueue() {
			// Register script
			wp_register_script(
				'mycred-transfer-ajax',
				plugins_url( 'js/transfer.js', myCRED_TRANSFER ),
				array( 'jquery', 'jquery-ui-autocomplete' ),
				myCRED_TRANSFER_VERSION . '.2'
			);

			// Register style (can be disabled)
			if ( apply_filters( 'mycred_remove_transfer_css', false ) === false ) {
				wp_register_style(
					'mycred-transfer-front',
					plugins_url( 'css/transfer.css', myCRED_TRANSFER ),
					false,
					myCRED_TRANSFER_VERSION . '.2',
					'all'
				);
			}
		}

		/**
		 * Front Footer
		 * @filter 'mycred_transfer_messages'
		 * @since 0.1
		 * @version 1.0
		 */
		public function front_footer() {
			global $mycred_load;

			if ( !isset( $mycred_load ) || $mycred_load === false ) return;

			wp_enqueue_style( 'mycred-transfer-front' );
			wp_enqueue_script( 'mycred-transfer-ajax' );

			$base = array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'user_id'   => get_current_user_id(),
				'working'   => __( 'Processing...', 'mycred' ),
				'token'     => wp_create_nonce( 'mycred-transfer-creds' ),
				'atoken'    => wp_create_nonce( 'mycred-autocomplete' )
			);

			// Messages
			$messages = apply_filters( 'mycred_transfer_messages', array(
				'completed' => __( 'Transaction completed.', 'mycred' ),
				'error_1'   => __( 'Security token could not be verified. Please contact your site administrator!', 'mycred' ),
				'error_2'   => __( 'Communications error. Please try again later.', 'mycred' ),
				'error_3'   => __( 'Recipient not found. Please try again.', 'mycred' ),
				'error_4'   => __( 'Transaction declined by recipient.', 'mycred' ),
				'error_5'   => __( 'Incorrect amount. Please try again.', 'mycred' ),
				'error_6'   => __( 'This myCRED Add-on has not yet been setup! No transfers are allowed until this has been done!', 'mycred' ),
				'error_7'   => __( 'Insufficient funds. Please enter a lower amount.', 'mycred' ),
				'error_8'   => __( 'Transfer Limit exceeded.', 'mycred' ),
				'error_9'   => __( 'The request amount will exceed your transfer limit. Please try again with a lower amount!', 'mycred' )
			) );

			wp_localize_script(
				'mycred-transfer-ajax',
				'myCRED',
				array_merge_recursive( $base, $messages )
			);
		}

		/**
		 * Settings Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function after_general_settings() {
			// Settings
			$settings = $this->transfers;

			$before = $this->core->before;
			$after = $this->core->after;

			// Limits
			$limit = $settings['limit']['limit'];
			$limits = array(
				'none'   => __( 'No limits.', 'mycred' ),
				'daily'  => __( 'Impose daily limit.', 'mycred' ),
				'weekly' => __( 'Impose weekly limit.', 'mycred' )
			);
			$available_limits = apply_filters( 'mycred_transfer_limits', $limits, $settings ); ?>

				<h4><div class="icon icon-active"></div><?php echo $this->core->template_tags_general( __( 'Transfer %plural%', 'mycred' ) ); ?></h4>
				<div class="body" style="display:none;">
					<label class="subheader"><?php _e( 'Log template for sending', 'mycred' ); ?></label>
					<ol id="myCRED-transfer-logging-send">
						<li>
							<div class="h2"><input type="text" name="mycred_pref_core[transfers][logs][sending]" id="myCRED-transfer-log-sender" value="<?php echo $settings['logs']['sending']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, User', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Log template for receiving', 'mycred' ); ?></label>
					<ol id="myCRED-transfer-logging-receive">
						<li>
							<div class="h2"><input type="text" name="mycred_pref_core[transfers][logs][receiving]" id="myCRED-transfer-log-receiver" value="<?php echo $settings['logs']['receiving']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, User', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Limits', 'mycred' ); ?></label>
					<ol id="myCRED-transfer-limits">
<?php
			// Loop though limits
			if ( !empty( $limits ) ) {
				foreach ( $limits as $key => $description ) { ?>

						<li>
							<input type="radio" name="mycred_pref_core[transfers][limit][limit]" id="myCRED-limit-<?php echo $key; ?>" <?php checked( $limit, $key ); ?> value="<?php echo $key; ?>" />
							<label for="myCRED-limit-<?php echo $key; ?>"><?php echo $description; ?></label>
						</li>
<?php
				}
			} ?>

						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'limit' => 'amount' ) ); ?>"><?php _e( 'Maximum Amount', 'mycred' ); ?></label>
							<div class="h2"><?php echo $before; ?> <input type="text" name="<?php echo $this->field_name( array( 'limit' => 'amount' ) ); ?>" id="<?php echo $this->field_id( array( 'limit' => 'amount' ) ); ?>" value="<?php echo $this->core->number( $settings['limit']['amount'] ); ?>" size="8" /> <?php echo $after; ?></div>
							<span class="description"><?php _e( 'This amount is ignored if no limits are imposed.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Form Templates', 'mycred' ); ?></label>
					<ol id="myCRED-transfer-form-templates">
						<li>
							<label for="<?php echo $this->field_id( array( 'templates' => 'login' ) ); ?>"><?php _e( 'Not logged in Template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'login' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'login' ) ); ?>" value="<?php echo $settings['templates']['login']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Text to show when users are not logged in. Leave empty to hide. No HTML elements allowed!', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'templates' => 'balance' ) ); ?>"><?php _e( 'Balance Template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'balance' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'balance' ) ); ?>" value="<?php echo $settings['templates']['balance']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Template to use when displaying the users balance (if included). No HTML elements allowed!', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'templates' => 'limit' ) ); ?>"><?php _e( 'Limit Template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'limit' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'limit' ) ); ?>" value="<?php echo $settings['templates']['limit']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Template to use when displaying limits (if used). No HTML elements allowed!', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'templates' => 'button' ) ); ?>"><?php _e( 'Button Template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'button' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'button' ) ); ?>" value="<?php echo $settings['templates']['button']; ?>" class="medium" /></div>
							<span class="description"><?php _e( 'Send Transfer button template. No HTML elements allowed!', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Error Messages', 'mycred' ); ?></label>
					<ol id="myCRED-transfer-form-errors">
						<li>
							<label for="<?php echo $this->field_id( array( 'errors' => 'low' ) ); ?>"><?php _e( 'Balance to low to send.', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'errors' => 'low' ) ); ?>" id="<?php echo $this->field_id( array( 'errors' => 'low' ) ); ?>" value="<?php echo $settings['errors']['low']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Text to show when a users balance is to low for transfers. Leave empty to hide. No HTML elements allowed!', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'errors' => 'over' ) ); ?>"><?php _e( 'Transfer Limit Reached.', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'errors' => 'over' ) ); ?>" id="<?php echo $this->field_id( array( 'errors' => 'over' ) ); ?>" value="<?php echo $settings['errors']['over']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Text to show when a user has reached their transfer limit (if used). Leave empty to hide. No HTML elements allowed!', 'mycred' ); ?></span>
						</li>
					</ol>
				</div>
<?php
		}

		/**
		 * Sanitize & Save Settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $general ) {
			// Log
			$new_data['transfers']['logs']['sending'] = sanitize_text_field( $data['transfers']['logs']['sending'] );
			$new_data['transfers']['logs']['receiving'] = sanitize_text_field( $data['transfers']['logs']['receiving'] );

			// Form Templates
			$new_data['transfers']['templates']['login'] = sanitize_text_field( $data['transfers']['templates']['login'] );
			$new_data['transfers']['templates']['balance'] = trim( $data['transfers']['templates']['balance'] );
			$new_data['transfers']['templates']['limit'] = sanitize_text_field( $data['transfers']['templates']['limit'] );
			$new_data['transfers']['templates']['button'] = sanitize_text_field( $data['transfers']['templates']['button'] );

			// Error Messages
			$new_data['transfers']['errors']['low'] = sanitize_text_field( $data['transfers']['errors']['low'] );
			$new_data['transfers']['errors']['over'] = sanitize_text_field( $data['transfers']['errors']['over'] );

			// Limits
			$new_data['transfers']['limit']['limit'] = sanitize_text_field( $data['transfers']['limit']['limit'] );
			$new_data['transfers']['limit']['amount'] = $data['transfers']['limit']['amount'];

			return $new_data;
		}

		/**
		 * AJAX Transfer Creds
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function ajax_call_transfer() {
			// Security
			//check_ajax_referer( 'mycred-transfer-creds', 'token' );
			if ( !isset( $_POST['token'] ) || ( isset( $_POST['token'] ) && !wp_verify_nonce( $_POST['token'], 'mycred-transfer-creds' ) ) )
				die( json_encode( 'error_1' ) );

			// Required
			if ( !isset( $_POST['recipient'] ) || !isset( $_POST['sender'] ) || !isset( $_POST['amount'] ) )
				die( json_encode( 'error_2' ) );

			// Prep
			$to = $_POST['recipient'];
			$from = $_POST['sender'];
			$amount = abs( $_POST['amount'] );

			// Add-on has not been installed
			if ( !isset( $this->transfers ) )
				die( json_encode( 'error_6' ) );

			$prefs = $this->transfers;
			if ( !isset( $prefs['limit']['limit'] ) || !isset( $prefs['logs']['sending'] ) )
				die( json_encode( 'error_6' ) );

			// Get Recipient
			$ruser = get_user_by( 'login', $to );
			if ( $ruser === false ) die( json_encode( 'error_3' ) );
			if ( $this->core->exclude_user( $ruser->ID ) ) die( json_encode( 'error_4' ) );
			$recipient_id = $ruser->ID;

			// Prevent transfers to ourselves
			if ( $recipient_id == $from )
				die( json_encode( 'error_4' ) );

			// Check amount
			$amount = $this->core->number( $amount );
			if ( $amount == $this->core->zero() ) die( json_encode( 'error_5' ) );

			// Check funds
			if ( mycred_user_can_transfer( $from, $amount ) === 'low' ) die( json_encode( 'error_7' ) );

			$today = date_i18n( 'd' );
			$this_week = date_i18n( 'W' );
			$set_limit = $prefs['limit']['limit'];

			// Check limits
			if ( $prefs['limit']['limit'] != 'none' ) {
				// Prep
				$max = $this->core->number( $prefs['limit']['amount'] );

				// Get users "limit log"
				$history = get_user_meta( $from, 'mycred_transactions', true );
				if ( empty( $history ) ) {
					// Add new defaults
					$history = array(
						'frame'  => ( $prefs['limit']['limit'] == 'daily' ) ? $today : $this_week,
						'amount' => $this->core->zero()
					);
					update_user_meta( $from, 'mycred_transactions', $history );
				}

				// Total amount so far
				$current = $this->core->number( $history['amount'] );

				// Daily limit
				if ( $prefs['limit']['limit'] == 'daily' ) {
					// New day, new limits
					if ( $today != $history['frame'] ) {
						$history = array(
							'frame'  => $today,
							'amount' => $this->core->zero()
						);
						update_user_meta( $from, 'mycred_transactions', $history );
					}

					// Make sure user has not reached or exceeded the transfer limit.
					if ( $current >= $max ) die( json_encode( 'error_8' ) );
				}

				// Weekly limit
				elseif ( $prefs['limit']['limit'] == 'weekly' ) {
					// New week, new limits
					if ( $this_week != $history['frame'] ) {
						$history = array(
							'frame'  => $this_week,
							'amount' => $this->core->zero()
						);
						update_user_meta( $from, 'mycred_transactions', $history );
					}

					// Make sure user has not reached or exceeded the transfer limit.
					if ( $current >= $max ) die( json_encode( 'error_8' ) );
				}

				// Make sure the requested amount will not take us over the limit.
				$after_transfer = $amount+$current;
				if ( $after_transfer > $max ) die( json_encode( 'error_9' ) );
			}

			// Let others play before we execute the transfer
			do_action( 'mycred_transfer_ready', $prefs, $this->core );

			// Generate Transaction ID for our records
			$transaction_id = 'TXID' . date_i18n( 'U' );

			// First take the amount from the sender
			$this->core->add_creds(
				'transfer',
				$from,
				0-$amount,
				$prefs['logs']['sending'],
				$recipient_id,
				array( 'ref_type' => 'user', 'tid' => $transaction_id )
			);

			// Update history if limits are imposed
			if ( $prefs['limit']['limit'] != 'none' ) {
				$history['amount'] = $after_transfer;
				update_user_meta( $from, 'mycred_transactions', $history );
			}

			// Then add the amount to the receipient
			$this->core->add_creds(
				'transfer',
				$recipient_id,
				$amount,
				$prefs['logs']['receiving'],
				$from,
				array( 'ref_type' => 'user', 'tid' => $transaction_id )
			);

			// Let others play once transaction is completed
			do_action( 'mycred_transfer_completed', $prefs, $this->core );

			// Clean up and die
			unset( $this );
			unset( $ruser );
			die( json_encode( 'ok' ) );
		}

		/**
		 * AJAX Autocomplete
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function ajax_call_autocomplete() {
			$results = array();

			// Security
			if ( isset( $_REQUEST['token'] ) && wp_verify_nonce( $_REQUEST['token'], 'mycred-autocomplete' ) ) {
				global $wpdb;

				// prep query
				$sql = "SELECT user_login, ID FROM {$wpdb->users} WHERE ID != %d AND user_login LIKE %s;";
				$search = $_REQUEST['string']['term'];
				$me = $_REQUEST['me'];

				// Query
				$blog_users = $wpdb->get_results( $wpdb->prepare( $sql, $me, $search . '%' ) , 'ARRAY_N' );
				if ( $wpdb->num_rows > 0 ) {
					foreach ( $blog_users as $hit ) {
						if ( $this->core->exclude_user( $hit[1] ) ) continue;
						$results[] = $hit[0];
					}
				}
			}

			die( json_encode( $results ) );
		}

		/**
		 * Support for Email Notices
		 * @since 1.1
		 * @version 1.1
		 */
		public function email_notices( $data ) {
			if ( $data['request']['ref'] == 'transfer' ) {
				$message = $data['message'];
				if ( $data['request']['ref_id'] == get_current_user_id() )
					$data['message'] = $this->core->template_tags_user( $message, false, wp_current_user_id() );
				else
					$data['message'] = $this->core->template_tags_user( $message, $data['request']['ref_id'] );
			}
			return $data;
		}

		/**
		 * Contextual Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function help( $screen_id, $screen ) {
			if ( $screen_id != 'mycred_page_myCRED_page_settings' ) return;

			$screen->add_help_tab( array(
				'id'		=> 'mycred-transfer',
				'title'		=> __( 'Transfer', 'mycred' ),
				'content'	=> '
<p>' . $this->core->template_tags_general( __( 'This add-on lets your users transfer %_plural% to each other. Members who are set to be excluded can neither send or receive %_plural%.', 'mycred' ) ) . '</p>
<p><strong>' . __( 'Transfer Limit', 'mycred' ) . '</strong></p>
<p>' . __( 'You can impose a daily-, weekly- or monthly transfer limit for each user. Note, that this transfer limit is imposed on everyone who are not excluded from using myCRED.', 'mycred' ) . '</p>
<p><strong>' . __( 'Usage', 'mycred' ) . '</strong></p>
<p>' . __( 'Transfers can be made by either using the <code>mycred_transfer</code> shortcode or via the myCRED Transfer Widget.<br />For more information on how to use the shortcode, please visit the', 'mycred' ) . ' <a href="http://mycred.me/shortcodes/mycred_transfer/" target="_blank">myCRED Codex</a>.</p>'
			) );
		}
	}
	$transfer = new myCRED_Transfer_Creds();
	$transfer->load();
}

/**
 * Widget: myCRED Transfer
 * @since 0.1
 * @version 1.1
 */
if ( !class_exists( 'myCRED_Widget_Transfer' ) ) {
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
			$this->WP_Widget( 'mycred_widget_transfer', sprintf( __( '%s Transfer', 'mycred' ), apply_filters( 'mycred_label', myCRED_NAME ) ), $widget_ops );
			$this->alt_option_name = 'mycred_widget_transfer';
		}

		/**
		 * Widget Output
		 */
		function widget( $args, $instance ) {
			extract( $args, EXTR_SKIP );

			// Prep
			$title = $instance['title'];
			$mycred = mycred_get_settings();
			if ( !isset( $mycred->transfers ) )
				return '<p>' . __( 'The myCRED Transfer add-on has not yet been setup!', 'mycred' ) . '</p>';

			$pref = $mycred->transfers;

			global $mycred_load;
			// Members
			if ( is_user_logged_in() ) {
				// Excluded users
				$user_id = get_current_user_id();
				if ( $mycred->exclude_user( $user_id ) ) return;

				echo $before_widget;
				// Title
				if ( !empty( $title ) ) {
					echo $before_title;
					echo $mycred->template_tags_general( $title );
					echo $after_title;
				}

				// Prep shortcode
				$attr = array(
					'show_balance' => $instance['show_balance'],
					'show_limit'   => $instance['show_limit']
				);
				echo mycred_transfer_render( $attr, '' );

				$mycred_load = true;
				echo $after_widget;
			}
			// Visitors
			else {
				$mycred_load = false;
				// If login message is set
				if ( !empty( $pref['templates']['login'] ) ) {
					echo $before_widget;
					if ( !empty( $instance['title'] ) ) {
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
			$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : __( 'Transfer %plural%', 'mycred' );
			$show_balance = isset( $instance['show_balance'] ) ? 1 : 0;
			$show_limit = isset( $instance['show_limit'] ) ? 1 : 0; ?>

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
<?php
		}

		/**
		 * Processes widget options to be saved
		 */
		function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
			$instance['title'] = trim( $new_instance['title'] );

			$instance['show_balance'] = ( isset( $new_instance['show_balance'] ) ) ? 1 : 0;
			$instance['show_limit'] = ( isset( $new_instance['show_limit'] ) ) ? 1 : 0;

			mycred_flush_widget_cache( 'mycred_widget_transfer' );
			return $instance;
		}
	}
}

/**
 * Transfer Shortcode Render
 * @see http://mycred.me/functions/mycred_transfer_render/
 * @attribute $charge_from (int) optional user ID from whom the points to be deducted, defaults to current user
 * @attribute $pay_to (int) optional user ID to whom the transfer is made, if left empty the user will be able to search for a user
 * @attribute $show_balance (bool) set to true to show current users balance, defaults to true
 * @attribute $show_limit (bool) set to true to show current users limit. If limit is set to 'none' and $show_limit is set to true nothing will be returned
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_transfer_render' ) ) {
	function mycred_transfer_render( $atts, $content = NULL )
	{
		global $mycred_load;

		// Get Attributes
		extract( shortcode_atts( array(
			'charge_from'  => NULL,
			'pay_to'       => NULL,
			'show_balance' => 0,
			'show_limit'   => 0
		), $atts ) );

		// Settings
		$mycred = mycred_get_settings();
		$pref = $mycred->transfers;

		$output = '';
		$mycred_load = false;

		// If we are not logged in
		if ( !is_user_logged_in() ) {
			if ( isset( $pref['templates']['login'] ) && !empty( $pref['templates']['login'] ) )
				$output .= '<p class="mycred-transfer-login">' . $mycred->template_tags_general( $pref['templates']['login'] ) . '</p>';
			
			return $output;
		}

		// Who to charge
		if ( $charge_from === NULL ) $charge_from = get_current_user_id();

		// Make sure user is not excluded
		if ( $mycred->exclude_user( $charge_from ) ) return;

		$status = mycred_user_can_transfer( $charge_from );
		$my_balance = $mycred->get_users_cred( $charge_from );

		// Error. Not enough creds
		if ( $status === 'low' ) {
			if ( isset( $pref['errors']['low'] )  && !empty( $pref['errors']['low'] ) ) {
				$no_cred = str_replace( '%limit%', $pref['limit']['limit'], $pref['errors']['low'] );
				$no_cred = str_replace( '%Limit%', ucwords( $pref['limit']['limit'] ), $no_cred );
				$no_cred = str_replace( '%left%',  $mycred->format_creds( $status ), $no_cred );
				$output .= '<p class="mycred-transfer-low">' . $mycred->template_tags_general( $no_cred ) . '</p>';
			}
			return $output;
		}

		// Error. Over limit
		if ( $status === 'limit' ) {
			if ( isset( $pref['errors']['over'] ) && !empty( $pref['errors']['over'] ) ) {
				$no_cred = str_replace( '%limit%', $pref['limit']['limit'], $pref['errors']['over'] );
				$no_cred = str_replace( '%Limit%', ucwords( $pref['limit']['limit'] ), $no_cred );
				$no_cred = str_replace( '%left%',  $mycred->format_creds( $status ), $no_cred );
				$output .= '<p class="mycred-transfer-over">' . $mycred->template_tags_general( $no_cred ) . '</p>';
			}
			return $output;
		}

		// Flag for scripts & styles
		$mycred_load = true;

		// If pay to is set
		if ( $pay_to !== NULL ) {
			$user = get_user_by( 'id', $pay_to );
			if ( $user !== false )
				$to_input = '<input type="text" name="mycred-transfer-to" value="' . $user->user_login . '" readonly="readonly" />';
			else
				$to_input = '<input type="text" name="mycred-transfer-to" value="" class="mycred-autofill" />';
			
			unset( $user );
		}
		else $to_input = '<input type="text" name="mycred-transfer-to" value="" class="mycred-autofill" />';

		// If content is passed on.
		if ( $content !== NULL && !empty( $content ) )
			$output .= $content;

		if ( !empty( $mycred->before ) )
			$before = $mycred->before . ' ';
		else
			$before = '';
		
		if ( !empty( $mycred->after ) )
			$after = ' ' . $mycred->after;
		else
			$after = '';

		// Main output
		$output .= '
	<ol>
		<li class="mycred-send-to">
			<label>' . __( 'To:', 'mycred' ) . '</label>
			<div class="transfer-to">' . $to_input . '</div>
		</li>
		<li class="mycred-send-amount">
			<label>' . __( 'Amount:', 'mycred' ) . '</label>
			<div>' . $before . '<input type="text" class="short" name="mycred-transfer-amount" value="' . $mycred->zero() . '" size="8" />' . $after . '</div> 
			<input type="button" class="button large button-large mycred-click" value="' . $pref['templates']['button'] . '" />
		</li>
		';

		$extras = array();

		// Show Balance 
		if ( (bool) $show_balance === true && !empty( $pref['templates']['balance'] ) ) {
			$balance_text = str_replace( '%balance%', $mycred->format_creds( $my_balance ), $pref['templates']['balance'] );
			$extras[] = $mycred->template_tags_general( $balance_text );
		}

		// Show Limits
		if ( (bool) $show_limit === true && !empty( $pref['templates']['limit'] ) && $pref['limit']['limit'] != 'none' ) {
			$limit_text = str_replace( '%_limit%', $pref['limit']['limit'], $pref['templates']['limit'] );
			$limit_text = str_replace( '%limit%',  ucwords( $pref['limit']['limit'] ), $limit_text );
			$limit_text = str_replace( '%left%',   $mycred->format_creds( $status ), $limit_text );
			$extras[] = $mycred->template_tags_general( $limit_text );
		}

		// No need to include this if extras is empty
		if ( !empty( $extras ) ) {
			$output .= '<li class="mycred-transfer-info"><p>' . implode( '</p><p>', $extras ) . '</p></li>';
		}

		$output .= '
	</ol>' . "\n";

		// Return result
		$result = '<div class="mycred-transfer-cred-wrapper">' . $output . '</div>';
		$result = apply_filters( 'mycred_transfer_render', $result, $atts, $mycred );

		unset( $mycred );
		unset( $output );
		return do_shortcode( $result );
	}
}

/**
 * User Can Transfer
 * @see http://mycred.me/functions/mycred_user_can_transfer/
 * @param $user_id (int) requred user id
 * @param $amount (int) optional amount to check against balance
 * @returns true if no limit is set, 'limit' (string) if user is over limit else the amount of creds left
 * @filter 'mycred_user_can_transfer'
 * @filter 'mycred_transfer_acc_limit'
 * @since 0.1
 * @version 1.1
 */
if ( !function_exists( 'mycred_user_can_transfer' ) ) {
	function mycred_user_can_transfer( $user_id = NULL, $amount = NULL )
	{
		if ( $user_id === NULL ) $user_id = get_current_user_id();

		// Grab Settings
		$mycred = mycred_get_settings();
		$pref = $mycred->transfers;
		$set_limit = $pref['limit']['limit'];
		$balance = $mycred->get_users_cred( $user_id );

		// To low balance
		$account_limit = (int) apply_filters( 'mycred_transfer_acc_limit', 0 );
		if ( !is_numeric( $account_limit ) )
			$account_limit = 0;

		if ( $amount !== NULL ) {
			if ( $balance-$amount < $account_limit ) return 'low';
		} else {
			if ( $balance <= $account_limit ) return 'low';
		}

		// No limits imposed
		if ( $set_limit == 'none' ) return true;

		// Else we have a limit to impose
		$today = date_i18n( 'd' );
		$this_week = date_i18n( 'W' );
		$max = $mycred->number( $pref['limit']['amount'] );

		// Get users "limit log"
		$history = get_user_meta( $user_id, 'mycred_transactions', true );
		if ( empty( $history ) ) {
			// Apply defaults if not set
			$history = array(
				'frame'  => '',
				'amount' => $mycred->zero()
			);
		}

		// Daily limit
		if ( $pref['limit']['limit'] == 'daily' ) {
			// New day, new limits
			if ( $today != $history['frame'] ) {
				$new_data = array(
					'frame' => $today,
					'amount' => $mycred->zero()
				);
				update_user_meta( $user_id, 'mycred_transactions', $new_data );
				$current = $new_data['amount'];
			}
			// Same day, check limit
			else {
				$current = $mycred->number( $history['amount'] );
			}

			if ( $current >= $max ) return 'limit';
			else {
				$remaining = $max-$current;
				return $mycred->number( $remaining );
			}
		}

		// Weekly limit
		elseif ( $pref['limit']['limit'] == 'weekly' ) {
			// New week, new limits
			if ( $this_week != $history['frame'] ) {
				$new_data = array(
					'frame' => $this_week,
					'amount' => $mycred->zero()
				);
				update_user_meta( $user_id, 'mycred_transactions', $new_data );
				$current = $new_data['amount'];
			}
			// Same week, check limit
			else {
				$current = $mycred->number( $history['amount'] );
			}

			if ( $current >= $max ) return 'limit';
			else {
				$remaining = $max-$current;
				return $mycred->number( $remaining );
			}
		}

		// others limits
		else {
			return apply_filters( 'mycred_user_can_transfer', $mycred->number( $pref['limit']['amount'] ), $user_id, $balance, $history, $mycred );
		}
	}
}
?>