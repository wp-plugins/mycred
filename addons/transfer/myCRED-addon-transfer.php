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
define( 'myCRED_TRANSFER_DIR',     myCRED_ADDONS_DIR . 'transfer/' );
define( 'myCRED_TRANSFER_VERSION', myCRED_VERSION . '.1' );

require_once myCRED_TRANSFER_DIR . 'includes/mycred-transfer-functions.php';
require_once myCRED_TRANSFER_DIR . 'includes/mycred-transfer-shortcodes.php';
require_once myCRED_TRANSFER_DIR . 'includes/mycred-transfer-widgets.php';

/**
 * myCRED_Transfer_Module class
 * Manages this add-on by hooking into myCRED where needed. Regsiters our custom shortcode and widget
 * along with scripts and styles needed. Also adds settings to the myCRED settings page.
 * @since 0.1
 * @version 1.3.1
 */
if ( ! class_exists( 'myCRED_Transfer_Module' ) ) :
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

			add_filter( 'mycred_get_email_events',  array( $this, 'email_notice_instance' ), 10, 2 );
			add_filter( 'mycred_email_before_send', array( $this, 'email_notices' ), 10, 2 );

		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_init() {

			// Call Scripts & Styles when needed
			add_shortcode( 'mycred_transfer',            'mycred_transfer_render' );
			add_action( 'wp_footer',                     array( $this, 'front_footer' ) );

			// Register Scripts & Styles
			add_action( 'mycred_front_enqueue',          array( $this, 'front_enqueue' ) );

			// Ajax Calls
			add_action( 'wp_ajax_mycred-transfer-creds', array( $this, 'ajax_call_transfer' ) );
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
				plugins_url( 'assets/js/transfer.js', myCRED_TRANSFER ),
				array( 'jquery', 'jquery-ui-autocomplete' ),
				myCRED_TRANSFER_VERSION . '.2'
			);

			// Register style (can be disabled)
			if ( apply_filters( 'mycred_remove_transfer_css', false ) === false )
				wp_register_style(
					'mycred-transfer-front',
					plugins_url( 'assets/css/transfer.css', myCRED_TRANSFER ),
					false,
					myCRED_TRANSFER_VERSION . '.2',
					'all'
				);

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
				'working'   => esc_attr__( 'Processing...', 'mycred' ),
				'token'     => wp_create_nonce( 'mycred-transfer-creds' ),
				'atoken'    => wp_create_nonce( 'mycred-autocomplete' ),
				'reload'    => $this->transfers['reload']
			);

			// Messages
			$messages = apply_filters( 'mycred_transfer_messages', array(
				'completed' => esc_attr__( 'Transaction completed.', 'mycred' ),
				'error_1'   => esc_attr__( 'Security token could not be verified. Please contact your site administrator!', 'mycred' ),
				'error_2'   => esc_attr__( 'Communications error. Please try again later.', 'mycred' ),
				'error_3'   => esc_attr__( 'Recipient not found. Please try again.', 'mycred' ),
				'error_4'   => esc_attr__( 'Transaction declined by recipient.', 'mycred' ),
				'error_5'   => esc_attr__( 'Incorrect amount. Please try again.', 'mycred' ),
				'error_6'   => esc_attr__( 'This myCRED Add-on has not yet been setup! No transfers are allowed until this has been done!', 'mycred' ),
				'error_7'   => esc_attr__( 'Insufficient Funds. Please try a lower amount.', 'mycred' ),
				'error_8'   => esc_attr__( 'Transfer Limit exceeded.', 'mycred' )
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
		public function after_general_settings( $mycred ) {

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

			if ( ! isset( $settings['types'] ) )
				$settings['types'] = $this->default_prefs['types'];

?>
<h4><div class="icon icon-active"></div><?php _e( 'Transfers', 'mycred' ); ?></h4>
<div class="body" style="display:none;">
	<?php if ( count( $this->point_types ) > 1 ) : ?>

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
			<div class="h2"><input type="text" name="mycred_pref_core[transfers][logs][sending]" id="myCRED-transfer-log-sender" value="<?php echo esc_attr( $settings['logs']['sending'] ); ?>" class="long" /></div>
			<span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'user' ) ); ?></span>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Log template for receiving', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-receive">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[transfers][logs][receiving]" id="myCRED-transfer-log-receiver" value="<?php echo esc_attr( $settings['logs']['receiving'] ); ?>" class="long" /></div>
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
			<label for="<?php echo $this->field_id( array( 'limit' => 'amount' ) ); ?>"><?php _e( 'Limit Amount', 'mycred' ); ?></label>
			<div class="h2"><?php echo $before; ?> <input type="text" name="<?php echo $this->field_name( array( 'limit' => 'amount' ) ); ?>" id="<?php echo $this->field_id( array( 'limit' => 'amount' ) ); ?>" value="<?php echo $this->core->number( $settings['limit']['amount'] ); ?>" size="8" /> <?php echo $after; ?></div>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Form Templates', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-form-templates">
		<li>
			<label for="<?php echo $this->field_id( array( 'templates' => 'login' ) ); ?>"><?php _e( 'Not logged in Template', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'login' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'login' ) ); ?>" value="<?php echo esc_attr( $settings['templates']['login'] ); ?>" class="long" /></div>
			<span class="description"><?php _e( 'Text to show when users are not logged in. Leave empty to hide. No HTML elements allowed!', 'mycred' ); ?></span>
		</li>
		<li class="empty">&nbsp;</li>
		<li>
			<label for="<?php echo $this->field_id( array( 'templates' => 'balance' ) ); ?>"><?php _e( 'Balance Template', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'balance' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'balance' ) ); ?>" value="<?php echo esc_attr( $settings['templates']['balance'] ); ?>" class="long" /></div>
			<span class="description"><?php _e( 'Template to use when displaying the users balance (if included). No HTML elements allowed!', 'mycred' ); ?></span>
		</li>
		<li class="empty">&nbsp;</li>
		<li>
			<label for="<?php echo $this->field_id( array( 'templates' => 'limit' ) ); ?>"><?php _e( 'Limit Template', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'limit' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'limit' ) ); ?>" value="<?php echo esc_attr( $settings['templates']['limit'] ); ?>" class="long" /></div>
			<span class="description"><?php _e( 'Template to use when displaying limits (if used). No HTML elements allowed!', 'mycred' ); ?></span>
		</li>
		<li class="empty">&nbsp;</li>
		<li>
			<label for="<?php echo $this->field_id( array( 'templates' => 'button' ) ); ?>"><?php _e( 'Button Template', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'templates' => 'button' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'button' ) ); ?>" value="<?php echo esc_attr( $settings['templates']['button'] ); ?>" class="medium" /></div>
			<span class="description"><?php _e( 'Send Transfer button template. No HTML elements allowed!', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Error Messages', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-form-errors">
		<li>
			<label for="<?php echo $this->field_id( array( 'errors' => 'low' ) ); ?>"><?php _e( 'Balance to low to send.', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'errors' => 'low' ) ); ?>" id="<?php echo $this->field_id( array( 'errors' => 'low' ) ); ?>" value="<?php echo esc_attr( $settings['errors']['low'] ); ?>" class="long" /></div>
			<span class="description"><?php _e( 'Text to show when a users balance is to low for transfers. Leave empty to hide. No HTML elements allowed!', 'mycred' ); ?></span>
		</li>
		<li class="empty">&nbsp;</li>
		<li>
			<label for="<?php echo $this->field_id( array( 'errors' => 'over' ) ); ?>"><?php _e( 'Transfer Limit Reached.', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'errors' => 'over' ) ); ?>" id="<?php echo $this->field_id( array( 'errors' => 'over' ) ); ?>" value="<?php echo esc_attr( $settings['errors']['over'] ); ?>" class="long" /></div>
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

			global $mycred;

			$allowed = $mycred->allowed_html_tags();

			$new_data['transfers']['types']                = $data['transfers']['types'];
			$new_data['transfers']['logs']['sending']      = wp_kses( $data['transfers']['logs']['sending'], $allowed );
			$new_data['transfers']['logs']['receiving']    = wp_kses( $data['transfers']['logs']['receiving'], $allowed );
			$new_data['transfers']['autofill']             = sanitize_text_field( $data['transfers']['autofill'] );
			$new_data['transfers']['reload']               = ( isset( $data['transfers']['reload'] ) ) ? 1 : 0;
			$new_data['transfers']['templates']['login']   = sanitize_text_field( $data['transfers']['templates']['login'] );
			$new_data['transfers']['templates']['balance'] = wp_kses( $data['transfers']['templates']['balance'], $allowed );
			$new_data['transfers']['templates']['limit']   = sanitize_text_field( $data['transfers']['templates']['limit'] );
			$new_data['transfers']['templates']['button']  = sanitize_text_field( $data['transfers']['templates']['button'] );
			$new_data['transfers']['errors']['low']        = sanitize_text_field( $data['transfers']['errors']['low'] );
			$new_data['transfers']['errors']['over']       = sanitize_text_field( $data['transfers']['errors']['over'] );
			$new_data['transfers']['limit']['limit']       = sanitize_text_field( $data['transfers']['limit']['limit'] );
			$new_data['transfers']['limit']['amount']      = absint( $data['transfers']['limit']['amount'] );

			return $new_data;

		}

		/**
		 * AJAX Transfer Creds
		 * @since 0.1
		 * @version 1.5
		 */
		public function ajax_call_transfer() {

			// Security
			if ( ! check_ajax_referer( 'mycred-transfer-creds', 'token', false ) )
				die( json_encode( 'error_1' ) );

			parse_str( $_POST['form'], $post );
			unset( $_POST );

			// Required
			if ( ! isset( $post['mycred-transfer-to'] ) || ! isset( $post['mycred-transfer-amount'] ) )
				die( json_encode( $post ) );

			// Prep
			$to = $post['mycred-transfer-to'];

			if ( ! isset( $post['mycred-sender'] ) )
				$from = get_current_user_id();

			else {
				$from = absint( $post['mycred-sender'] );
				$from_user = get_userdata( $from );
				if ( $from_user === false ) die( -1 );
			}

			$ref = 'transfer';
			if ( isset( $post['mycred-transfer-ref'] ) )
				$ref = sanitize_key( $post['mycred-transfer-ref'] );

			$amount = abs( $post['mycred-transfer-amount'] );

			// Type
			$type = '';
			if ( isset( $post['mycred-transfer-type'] ) && array_key_exists( $post['mycred-transfer-type'], $this->point_types ) )
				$type = sanitize_text_field( $post['mycred-transfer-type'] );

			if ( $type == '' )
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
			$transfer = mycred_user_can_transfer( $from, $amount, $type, $ref );

			// Insufficient funds
			if ( $transfer === 'low' ) die( json_encode( 'error_7' ) );

			// Transfer limit reached
			elseif ( $transfer === 'limit' ) die( json_encode( 'error_8' ) );

			// Generate Transaction ID for our records
			$transaction_id = 'TXID' . date_i18n( 'U' ) . $from;

			// Let others play before we execute the transfer
			do_action( 'mycred_transfer_ready', $transaction_id, $post, $prefs, $this, $type );

			$data = apply_filters( 'mycred_transfer_data', array( 'ref_type' => 'user', 'tid' => $transaction_id ), $transaction_id, $post, $prefs, $type );

			// First take the amount from the sender
			$mycred->add_creds(
				$ref,
				$from,
				0-$amount,
				$prefs['logs']['sending'],
				$recipient_id,
				$data,
				$type
			);

			// Then add the amount to the receipient
			$mycred->add_creds(
				$ref,
				$recipient_id,
				$amount,
				$prefs['logs']['receiving'],
				$from,
				$data,
				$type
			);

			// Let others play once transaction is completed
			do_action( 'mycred_transfer_completed', $transaction_id, $post, $prefs, $this, $type );

			// Return the good news
			die( json_encode( 'ok' ) );

		}

		/**
		 * Get Recipient
		 * @since 1.3.2
		 * @version 1.1
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

					$user_id = apply_filters( 'mycred_transfer_autofill_get', false, $to );
					if ( $user_id === false ) return false;

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
		 * Add Email Notice Instance
		 * @since 1.5.4
		 * @version 1.0
		 */
		public function email_notice_instance( $events, $request ) {

			if ( $request['ref'] == 'transfer' ) {

				if ( $request['amount'] < 0 )
					$events[] = 'transfer|negative';

				elseif ( $request['amount'] > 0 )
					$events[] = 'transfer|positive';

			}

			return $events;

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

endif;

?>