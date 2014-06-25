<?php
/**
 * Addon: Transfer
 * Addon URI: http://mycred.me/add-ons/transfer/
 * Version: 1.3
 * Description: Allow your users to send or "donate" points to other members by either using the mycred_transfer shortcode or the myCRED Transfer widget.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_TRANSFER',         __FILE__ );
define( 'myCRED_TRANSFER_VERSION', myCRED_VERSION . '.1' );

/**
 * myCRED_Transfer_Module class
 * Manages this add-on by hooking into myCRED where needed. Regsiters our custom shortcode and widget
 * along with scripts and styles needed. Also adds settings to the myCRED settings page.
 * @since 0.1
 * @version 1.3
 */
if ( ! class_exists( 'myCRED_Transfer_Module' ) ) {
	class myCRED_Transfer_Module extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Transfer_Module', array(
				'module_name' => 'transfers',
				'defaults'    => array(
					'types'      => array( 'mycred_default' ),
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
					'autofill'   => 'user_login',
					'reload'     => 1,
					'limit'      => array(
						'amount'    => 1000,
						'limit'     => 'none'
					)
				),
				'register'    => false,
				'add_to_core' => true
			) );

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
		 * @version 1.1
		 */
		public function front_footer() {
			global $mycred_load_transfer;

			if ( ! isset( $mycred_load_transfer ) || $mycred_load_transfer === false ) return;

			wp_enqueue_style( 'mycred-transfer-front' );
			wp_enqueue_script( 'mycred-transfer-ajax' );

			$base = array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'user_id'   => get_current_user_id(),
				'working'   => __( 'Processing...', 'mycred' ),
				'token'     => wp_create_nonce( 'mycred-transfer-creds' ),
				'atoken'    => wp_create_nonce( 'mycred-autocomplete' ),
				'reload'    => $this->transfers['reload']
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
				'error_8'   => __( 'Transfer Limit exceeded.', 'mycred' )
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
		 * @version 1.3
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
			$available_limits = apply_filters( 'mycred_transfer_limits', $limits, $settings );

			// Autofill by
			$autofill = $settings['autofill'];
			$autofills = array(
				'user_login'   => __( 'User Login (user_login)', 'mycred' ),
				'user_email'   => __( 'User Email (user_email)', 'mycred' )
			);
			$available_autofill = apply_filters( 'mycred_transfer_autofill_by', $autofills, $settings );
			
			$mycred_types = mycred_get_types();
			if ( ! isset( $settings['types'] ) )
				$settings['types'] = $this->default_prefs['types']; ?>

<h4><div class="icon icon-active"></div><?php echo $this->core->template_tags_general( __( 'Transfer %plural%', 'mycred' ) ); ?></h4>
<div class="body" style="display:none;">
	<?php if ( count( $mycred_types ) > 1 ) : ?>

	<label class="subheader"><?php _e( 'Point Types', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-send">
		<li>
			<?php mycred_types_select_from_checkboxes( 'mycred_pref_core[transfers][types][]', 'mycred-transfer-type', $settings['types'] ); ?>

			<span class="description"><?php _e( 'Select the point types that users can transfer.', 'mycred' ); ?></span>
		</li>
	</ol>
	<?php else : ?>

	<input type="hidden" name="mycred_pref_core[transfers][types][]" value="mycred_default" />
	<?php endif; ?>

	<label class="subheader"><?php _e( 'Log template for sending', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-send">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[transfers][logs][sending]" id="myCRED-transfer-log-sender" value="<?php echo $settings['logs']['sending']; ?>" class="long" /></div>
			<span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'user' ) ); ?></span>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Log template for receiving', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-receive">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[transfers][logs][receiving]" id="myCRED-transfer-log-receiver" value="<?php echo $settings['logs']['receiving']; ?>" class="long" /></div>
			<span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'user' ) ); ?></span>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Autofill Recipient', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-autofill-by">
		<li>
			<select name="mycred_pref_core[transfers][autofill]" id="myCRED-transfer-autofill">
<?php
			foreach ( $available_autofill as $key => $label ) {
				echo '<option value="' . $key . '"';
				if ( $settings['autofill'] == $key ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			} ?>

			</select><br />
			<span class="description"><?php _e( 'Select what user details recipients should be autofilled by.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Reload', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-receive">
		<li>
			<input type="checkbox" name="mycred_pref_core[transfers][reload]" id="myCRED-transfer-reload" <?php checked( $settings['reload'], 1 ); ?> value="1" /> <label for="myCRED-transfer-reload"><?php _e( 'Reload page on successful transfers.', 'mycred' ); ?></label>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Limits', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-limits">
<?php
			// Loop though limits
			if ( ! empty( $limits ) ) {
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
		 * @version 1.2
		 */
		public function sanitize_extra_settings( $new_data, $data, $general ) {
			// Types
			$new_data['transfers']['types'] = $data['transfers']['types'];

			// Log
			$new_data['transfers']['logs']['sending'] = trim( $data['transfers']['logs']['sending'] );
			$new_data['transfers']['logs']['receiving'] = trim( $data['transfers']['logs']['receiving'] );

			// Autofill
			$new_data['transfers']['autofill'] = sanitize_text_field( $data['transfers']['autofill'] );

			// Reload
			$new_data['transfers']['reload'] = ( isset( $data['transfers']['reload'] ) ) ? 1 : 0;

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
		 * @version 1.2
		 */
		public function ajax_call_transfer() {
			// Security
			if ( ! check_ajax_referer( 'mycred-transfer-creds', 'token', false ) )
				die( json_encode( 'error_1' ) );

			// Required
			if ( ! isset( $_POST['recipient'] ) || ! isset( $_POST['sender'] ) || ! isset( $_POST['amount'] ) )
				die( json_encode( 'error_2' ) );

			// Prep
			$to = $_POST['recipient'];
			$from = $_POST['sender'];
			$amount = abs( $_POST['amount'] );

			// Type
			$mycred_types = mycred_get_types();
			$type = 'mycred_default';
			if ( isset( $_POST['type'] ) && in_array( $_POST['type'], $mycred_types ) )
				$type = sanitize_text_field( $_POST['type'] );

			if ( empty( $type ) )
				$type = 'mycred_default';

			$mycred = mycred( $type );

			// Add-on has not been installed
			if ( ! isset( $this->transfers ) )
				die( json_encode( 'error_6' ) );

			$prefs = $this->transfers;
			if ( ! isset( $prefs['limit']['limit'] ) || ! isset( $prefs['logs']['sending'] ) )
				die( json_encode( 'error_6' ) );

			// Get Recipient
			$recipient_id = $this->get_recipient( $to );
			if ( $recipient_id === false ) die( json_encode( 'error_3' ) );
			if ( $mycred->exclude_user( $recipient_id ) ) die( json_encode( 'error_4' ) );

			// Prevent transfers to ourselves
			if ( $recipient_id == $from )
				die( json_encode( 'error_4' ) );

			// Check amount
			$amount = $mycred->number( $amount );
			if ( $amount == $mycred->zero() ) die( json_encode( 'error_5' ) );

			// Check if user can transfer
			$transfer = mycred_user_can_transfer( $from, $amount, $type );
			
			// Insufficient funds
			if ( $transfer == 'low' ) die( json_encode( 'error_7' ) );
			
			// Transfer limit reached
			elseif ( $transfer == 'limit' ) die( json_encode( 'error_8' ) );
			
			// All good
			$after_transfer = $transfer;

			// Let others play before we execute the transfer
			do_action( 'mycred_transfer_ready', $prefs, $this->core, $type );

			// Generate Transaction ID for our records
			$transaction_id = 'TXID' . date_i18n( 'U' ) . $from;

			// First take the amount from the sender
			$mycred->add_creds(
				'transfer',
				$from,
				0-$amount,
				$prefs['logs']['sending'],
				$recipient_id,
				array( 'ref_type' => 'user', 'tid' => $transaction_id ),
				$type
			);

			// Update history if limits are imposed
			if ( $prefs['limit']['limit'] != 'none' ) {
				$history = mycred_get_users_transfer_history( $from, $type );
				mycred_update_users_transfer_history( $from, array(
					'amount' => $mycred->number( $amount+$history['amount'] )
				), $type );
			}

			// Then add the amount to the receipient
			$mycred->add_creds(
				'transfer',
				$recipient_id,
				$amount,
				$prefs['logs']['receiving'],
				$from,
				array( 'ref_type' => 'user', 'tid' => $transaction_id ),
				$type
			);

			// Let others play once transaction is completed
			do_action( 'mycred_transfer_completed', $prefs, $this->core, $type );

			// Return the good news
			die( json_encode( 'ok' ) );
		}

		/**
		 * Get Recipient
		 * @since 1.3.2
		 * @version 1.0
		 */
		public function get_recipient( $to = '' ) {
			if ( empty( $to ) ) return false;

			switch ( $this->transfers['autofill'] ) {
				case 'user_login' :

					$user = get_user_by( 'login', $to );
					if ( $user === false ) return false;
					$user_id = $user->ID;

				break;
				case 'user_email' :

					$user = get_user_by( 'email', $to );
					if ( $user === false ) return false;
					$user_id = $user->ID;

				break;
				default :

					$user_id = apply_filters( 'mycred_transfer_autofill_get', false );
					if ( $user === false ) return false;

				break;
			}

			return $user_id;
		}

		/**
		 * AJAX Autocomplete
		 * @since 0.1
		 * @version 1.1
		 */
		public function ajax_call_autocomplete() {
			// Security
			check_ajax_referer( 'mycred-autocomplete' , 'token' );

			if ( ! is_user_logged_in() ) die;

			$results = array();
			$user_id = get_current_user_id();
			$prefs = $this->transfers;

			// Let other play
			do_action( 'mycred_transfer_autofill_find', $prefs, $this->core );

			global $wpdb;

			// Query
			$select = $prefs['autofill'];
			$blog_users = $wpdb->get_results( $wpdb->prepare( "
SELECT {$select}, ID 
FROM {$wpdb->users} 
WHERE ID != %d 
	AND {$select} LIKE %s;", $user_id, '%' . $_REQUEST['string']['term'] . '%' ), 'ARRAY_N' );

			if ( $wpdb->num_rows > 0 ) {
				foreach ( $blog_users as $hit ) {
					if ( $this->core->exclude_user( $hit[1] ) ) continue;
					$results[] = $hit[0];
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
					$data['message'] = $this->core->template_tags_user( $message, false, wp_get_current_user() );
				else
					$data['message'] = $this->core->template_tags_user( $message, $data['request']['ref_id'] );
			}

			return $data;
		}
	}

	$transfer = new myCRED_Transfer_Module();
	$transfer->load();
}

/**
 * Widget: myCRED Transfer
 * @since 0.1
 * @version 1.1.1
 */
if ( ! class_exists( 'myCRED_Widget_Transfer' ) ) {
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
			$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : __( 'Transfer %plural%', 'mycred' );
			$show_balance = isset( $instance['show_balance'] ) ? $instance['show_balance'] : 0;
			$show_limit = isset( $instance['show_limit'] ) ? $instance['show_balance'] : 0; ?>

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

			$instance['show_balance'] = ( isset( $new_instance['show_balance'] ) ) ? $new_instance['show_balance'] : 0;
			$instance['show_limit'] = ( isset( $new_instance['show_limit'] ) ) ? $new_instance['show_balance'] : 0;

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
 * @version 1.2
 */
if ( ! function_exists( 'mycred_transfer_render' ) ) {
	function mycred_transfer_render( $atts, $content = NULL )
	{
		global $mycred_load_transfer;

		// Settings
		$mycred = mycred();
		$pref = $mycred->transfers;

		// Get Attributes
		extract( shortcode_atts( array(
			'charge_from'  => NULL,
			'pay_to'       => NULL,
			'show_balance' => 0,
			'show_limit'   => 0,
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

		// Who to charge
		if ( $charge_from === NULL ) $charge_from = get_current_user_id();

		// Point Types
		if ( ! is_array( $types ) )
			$raw = explode( ',', $types );
		else
			$raw = $types;

		$clean = array();
		foreach ( $raw as $id ) {
			$clean[] = sanitize_text_field( $id );
		}

		// Default
		if ( count( $clean ) == 1 && in_array( 'mycred_default', $clean ) ) {
			// Make sure user is not excluded
			if ( $mycred->exclude_user( $charge_from ) ) return '';

			$status = mycred_user_can_transfer( $charge_from, NULL );
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
		}
		// Multiple
		else {
			$available_types = array();
			foreach ( $clean as $point_type ) {
				
				$points = mycred( $point_type );
				if ( $points->exclude_user( $charge_from ) ) continue;
				
				$status = mycred_user_can_transfer( $charge_from, NULL, $point_type );
				if ( in_array( $status, array( 'low', 'limit' ) ) ) continue;
				
				$available_types[] = $point_type;
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
		$to_input = '<input type="text" name="mycred-transfer-to" value="" class="mycred-autofill" placeholder="' . $placeholder . '" />';

		// If recipient is set, pre-populate it with the recipients details
		if ( $pay_to !== NULL ) {
			$user = get_user_by( 'id', $pay_to );
			if ( $user !== false ) {
				$value = $user->user_login;
				if ( isset( $user->$pref['autofill'] ) )
					$value = $user->$pref['autofill'];

				$to_input = '<input type="text" name="mycred-transfer-to" value="' . $value . '" readonly="readonly" />';
			}
		}

		if ( count( $clean ) == 1 && in_array( 'mycred_default', $clean ) ) {
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
		if ( count( $clean ) == 1 )
			$type_input = '<input type="hidden" name="mycred-transfer-type" value="' . $clean[0] . '" />';
		else
			$type_input = mycred_types_select_from_dropdown( 'mycred-transfer-type', 'mycred-transfer-type', array(), true );

		$extras = array();

		// Show Balance 
		if ( (bool) $show_balance === true && ! empty( $pref['templates']['balance'] ) ) {
			$balance_text = str_replace( '%balance%', $mycred->format_creds( $my_balance ), $pref['templates']['balance'] );
			$extras[] = $mycred->template_tags_general( $balance_text );
		}

		// Show Limits
		if ( (bool) $show_limit === true && ! empty( $pref['templates']['limit'] ) && $pref['limit']['limit'] != 'none' ) {
			$limit_text = str_replace( '%_limit%', $pref['limit']['limit'], $pref['templates']['limit'] );
			$limit_text = str_replace( '%limit%',  ucwords( $pref['limit']['limit'] ), $limit_text );
			$limit_text = str_replace( '%left%',   $mycred->format_creds( $status ), $limit_text );
			$extras[] = $mycred->template_tags_general( $limit_text );
		}

		// Main output
		ob_start(); ?>

<div class="mycred-transfer-cred-wrapper">
	<ol>
		<li class="mycred-send-to">
			<label><?php _e( 'To:', 'mycred' ); ?></label>
			<div class="transfer-to"><?php echo $to_input; ?></div>
			<?php do_action( 'mycred_transfer_form_to', $atts, $pref ); ?>

		</li>
		<li class="mycred-send-amount">
			<label><?php _e( 'Amount:', 'mycred' ); ?></label>
			<div class="transfer-amount"><?php echo $before; ?><input type="text" class="short" name="mycred-transfer-amount" value="<?php echo $mycred->zero(); ?>" size="8" /><?php echo $after . ' ' . $type_input; ?></div> 
			<input type="button" class="button large button-large mycred-click" value="<?php echo $pref['templates']['button']; ?>" />
			<?php do_action( 'mycred_transfer_form_amount', $atts, $pref ); ?>

		</li>
<?php	if ( ! empty( $extras ) ) { ?>

		<li class="mycred-transfer-info">
			<p><?php echo implode( '</p><p>', $extras ); ?></p>
			<?php do_action( 'mycred_transfer_form_extra', $atts, $pref ); ?>

		</li>
<?php	} ?>

	</ol>
	<div class="clear clearfix clr"></div>
</div>
<?php
		$output = ob_get_contents();
		ob_end_clean();

		return do_shortcode( apply_filters( 'mycred_transfer_render', $output, $atts, $mycred ) );
	}
}

/**
 * User Can Transfer
 * @see http://mycred.me/functions/mycred_user_can_transfer/
 * @param $user_id (int) requred user id
 * @param $amount (int) optional amount to check against balance
 * @returns true if no limit is set, 'limit' (string) if user is over limit else the amount of creds left
 * @filter 'mycred_user_can_transfer'
 * @filter 'mycred_transfer_limit'
 * @filter 'mycred_transfer_acc_limit'
 * @since 0.1
 * @version 1.2
 */
if ( ! function_exists( 'mycred_user_can_transfer' ) ) {
	function mycred_user_can_transfer( $user_id = NULL, $amount = NULL, $type = 'mycred_default' )
	{
		if ( $user_id === NULL ) $user_id = get_current_user_id();

		// Grab Settings (from main type where the settings are saved)
		$mycred = mycred();
		$pref = $mycred->transfers;
		$zero = $mycred->zero();
		
		// Get users balance
		$balance = $mycred->get_users_cred( $user_id, $type );

		// Get Transfer Max
		$max = apply_filters( 'mycred_transfer_limit', $mycred->number( $pref['limit']['amount'] ), $user_id, $amount, $pref, $mycred );

		// If an amount is given, deduct this amount to see if the transaction
		// brings us over the account limit
		if ( $amount !== NULL )
			$balance = $mycred->number( $balance-$amount );

		// Account Limit
		// The lowest amount a user can have on their account. By default, this
		// is zero. But you can override this via the mycred_transfer_acc_limit hook.
		$account_limit = $mycred->number( apply_filters( 'mycred_transfer_acc_limit', $zero ) );

		// Check if users balance is below the account limit
		if ( $balance < $account_limit ) return 'low';

		// If there are no limits, return the current balance
		if ( $pref['limit']['limit'] == 'none' ) return $balance;

		// Else we have a limit to impose
		$today = date_i18n( 'd' );
		$this_week = date_i18n( 'W' );
		$max = $mycred->number( $pref['limit']['amount'] );

		// Get users "limit log"
		$history = mycred_get_users_transfer_history( $user_id );

		// Get Current amount
		$current = $mycred->number( $history['amount'] );

		// Daily limit
		if ( $pref['limit']['limit'] == 'daily' ) {
			// New day, new limits
			if ( $today != $history['frame'] ) {
				mycred_update_users_transfer_history( $user_id, array(
					'frame'  => $today,
					'amount' => $mycred->zero()
				), $type );
				$current = $zero;
			}
		}

		// Weekly limit
		elseif ( $pref['limit']['limit'] == 'weekly' ) {
			// New week, new limits
			if ( $this_week != $history['frame'] ) {
				mycred_update_users_transfer_history( $user_id, array(
					'frame'  => $this_week,
					'amount' => $mycred->zero()
				), $type );
				$current = $zero;
			}
		}

		// Custom limits will need to return the result
		// here and now. Accepted answers are 'limit', 'low' or the amount left on limit.
		else {
			return apply_filters( 'mycred_user_can_transfer', 'limit', $user_id, $amount, $pref, $mycred );
		}

		// Transfer limit reached
		if ( $current >= $max ) return 'limit';

		// Return whats remaining of limit
		$remaining = $max-$current;
		return $mycred->number( $remaining );
	}
}

/**
 * Get Users Transfer History
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_users_transfer_history' ) ) {
	function mycred_get_users_transfer_history( $user_id, $type = 'mycred_default' )
	{
		$key = 'mycred_transactions';
		if ( $type != 'mycred_default' && ! empty( $type ) )
			$key .= '_' . $type;

		$default = array(
			'frame'  => '',
			'amount' => 0
		);
		return mycred_apply_defaults( $default, get_user_meta( $user_id, $key, true ) );
	}
}

/**
 * Update Users Transfer History
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_update_users_transfer_history' ) ) {
	function mycred_update_users_transfer_history( $user_id, $history, $type = 'mycred_default' )
	{
		$key = 'mycred_transactions';
		if ( $type != 'mycred_default' && ! empty( $type ) )
			$key .= '_' . $type;

		// Get current history
		$current = mycred_get_users_transfer_history( $user_id, $type );

		// Reset
		if ( $history === true )
			$new_history = array(
				'frame'  => '',
				'amount' => 0
			);

		// Update
		else $new_history = mycred_apply_defaults( $current, $history );

		update_user_meta( $user_id, $key, $new_history );
	}
}
?>