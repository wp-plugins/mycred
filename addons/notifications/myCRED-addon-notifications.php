<?php
/**
 * Addon: Notifications
 * Addon URI: http://mycred.me/add-ons/notifications/
 * Version: 1.1
 * Description: Notify your users when their balances changes.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_NOTE',         __FILE__ );
define( 'myCRED_NOTE_VERSION', myCRED_VERSION . '.1' );

/**
 * myCRED_Notifications class
 * @since 1.2.3
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_Notifications_Module' ) ) {
	class myCRED_Notifications_Module extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Notifications_Module', array(
				'module_name' => 'notifications',
				'defaults'    => array(
					'life'      => 7,
					'template'  => '<p>%entry%</p><h1>%cred_f%</h1>',
					'use_css'   => 1,
					'duration'  => 3000
				),
				'register'    => false,
				'add_to_core' => true
			) );
			
			add_filter( 'mycred_add', array( $this, 'mycred_add' ), 999, 2 );
		}

		/**
		 * Module Init
		 * @since 1.2.3
		 * @version 1.0
		 */
		public function module_init() {
			if ( ! is_user_logged_in() ) return;

			add_action( 'mycred_front_enqueue', array( $this, 'register_assets' ) );
			add_action( 'wp_footer',            array( $this, 'get_notices' ), 1 );
			add_action( 'wp_footer',            array( $this, 'wp_footer' ), 99 );
		}

		/**
		 * Load Notice in Footer
		 * @since 1.2.3
		 * @version 1.2
		 */
		public function wp_footer() {
			$notices = apply_filters( 'mycred_notifications', array() );
			if ( empty( $notices ) ) return;

			if ( $this->notifications['duration'] == 0 ) $stay = 'true';
			else $stay = 'false';

			do_action_ref_array( 'mycred_before_notifications', array( &$notices ) );

			// Loop Notifications
			foreach ( (array) $notices as $notice ) {
				$notice = $this->core->template_tags_general( $notice );
				$notice = str_replace( array( "\r", "\n", "\t" ), '', $notice );
				echo '<!-- Notice --><script type="text/javascript">(function(jQuery){jQuery.noticeAdd({ text: "' . $notice . '",stay: ' . $stay . '});})(jQuery);</script>';
			}

			do_action_ref_array( 'mycred_after_notifications', array( &$notices ) );
		}

		/**
		 * Register Assets
		 * @since 1.2.3
		 * @version 1.0
		 */
		public function register_assets() {
			// Register script
			wp_register_script(
				'mycred-notifications',
				plugins_url( 'js/notify.js', myCRED_NOTE ),
				array( 'jquery' ),
				myCRED_NOTE_VERSION . '.1',
				true
			);

			if ( (bool) $this->notifications['use_css'] )
				wp_register_style(
					'mycred-notifications',
					plugins_url( 'css/notify.css', myCRED_NOTE ),
					false,
					myCRED_NOTE_VERSION . '.2',
					'all',
					true
				);
			
			if ( (bool) $this->notifications['use_css'] )
				wp_enqueue_style( 'mycred-notifications' );

			wp_enqueue_script( 'mycred-notifications' );
			wp_localize_script(
				'mycred-notifications',
				'myCRED_Notice',
				array(
					'ajaxurl'  => admin_url( 'admin-ajax.php' ),
					'duration' => $this->notifications['duration']
				)
			);
		}

		/**
		 * myCRED Add
		 * @since 1.2.3
		 * @version 1.2
		 */
		public function mycred_add( $reply, $request ) {
			if ( $reply === false ) return $reply;

			$mycred = mycred( $request['type'] );

			$template = $this->notifications['template'];
			$template = str_replace( '%entry%', $request['entry'], $template );
			$template = str_replace( '%amount%', $request['amount'], $template );
			$template = $mycred->template_tags_amount( $template, $request['amount'] );
			$template = $mycred->parse_template_tags( $template, (object) $request );
			$template = apply_filters( 'mycred_notifications_note', $template, $request, $mycred );

			if ( ! empty( $template ) )
				mycred_add_new_notice( array( 'user_id' => $request['user_id'], 'message' => $template ), $this->notifications['life'] );

			return $reply;
		}

		/**
		 * Get Notices
		 * @since 1.2.3
		 * @version 1.0.1
		 */
		public function get_notices() {
			$user_id = get_current_user_id();
			$data = get_transient( 'mycred_notice_' . $user_id );

			if ( $data === false || !is_array( $data ) ) return;

			foreach ( $data as $notice )
				add_filter( 'mycred_notifications', create_function( '$query', '$query[]=\'' . $notice . '\'; return $query;' ) );

			delete_transient( 'mycred_notice_' . $user_id );
		}

		/**
		 * Settings Page
		 * @since 1.2.3
		 * @version 1.0
		 */
		public function after_general_settings() {
			$settings = $this->notifications; ?>

<h4><div class="icon icon-active"></div><?php _e( 'Notifications', 'mycred' ); ?></h4>
<div class="body" style="display:none;">
	<label class="subheader"><?php _e( 'Styling', 'mycred' ); ?></label>
	<ol>
		<li>
			<input type="checkbox" name="mycred_pref_core[notifications][use_css]" id="myCRED-notifications-use-css" <?php checked( $settings['use_css'], 1 ); ?> value="1" />
			<label for="myCRED-notifications-use-css"><?php _e( 'Use the included CSS Styling for notifications.', 'mycred' ); ?></label>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Template', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-send">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[notifications][template]" id="myCRED-notifications-template" value="<?php echo $settings['template']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Use %entry% to show the log entry in the notice and %amount% for the amount.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Transient Lifespan', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-send">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[notifications][life]" id="myCRED-notifications-life" value="<?php echo $settings['life']; ?>" class="short" /></div>
			<span class="description"><?php _e( 'The number of days a users notification is saved before being automatically deleted.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader"><?php _e( 'Duration', 'mycred' ); ?></label>
	<ol id="myCRED-transfer-logging-send">
		<li>
			<div class="h2"><input type="text" name="mycred_pref_core[notifications][duration]" id="myCRED-notifications-duration" value="<?php echo $settings['duration']; ?>" class="short" /></div>
			<span class="description"><?php _e( 'The number of milliseconds a notice should be visible.<br />Use zero to require that the user closes the notice manually. 1000 milliseconds = 1 second.', 'mycred' ); ?></span>
		</li>
	</ol>
</div>
<?php
		}

		/**
		 * Sanitize & Save Settings
		 * @since 1.2.3
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $general ) {
			$new_data['notifications']['use_css'] = ( isset( $data['notifications']['use_css'] ) ) ? 1: 0;
			$new_data['notifications']['template'] = trim( $data['notifications']['template'] );
			$new_data['notifications']['life'] = abs( $data['notifications']['life'] );
			$new_data['notifications']['duration'] = abs( $data['notifications']['duration'] );

			return $new_data;
		}
	}

	$notice = new myCRED_Notifications_Module();
	$notice->load();
}

/**
 * Add Notice
 * @since 1.2.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_add_new_notice' ) ) {
	function mycred_add_new_notice( $notice = array(), $life = 1 ) {
		// Minimum requirements
		if ( ! isset( $notice['user_id'] ) || ! isset( $notice['message'] ) ) return false;

			// Get transient
		$data = get_transient( 'mycred_notice_' . $notice['user_id'] );

		// If none exists create a new array
		if ( $data === false || ! is_array( $data ) )
			$notices = array();
		else
			$notices = $data;

		// Add new notice
		$notices[] = addslashes( $notice['message'] );

		// Save as a transient
		set_transient( 'mycred_notice_' . $notice['user_id'], $notices, 86400*$life );
	}
}
?>