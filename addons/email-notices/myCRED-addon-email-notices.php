<?php
/**
 * Addon: Email Notices
 * Addon URI: http://mycred.me/add-ons/email-notices/
 * Version: 1.3
 * Description: Create email notices for any type of myCRED instance.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_EMAIL',         __FILE__ );
define( 'myCRED_EMAIL_VERSION', myCRED_VERSION . '.1' );

/**
 * myCRED_Email_Notice_Module class
 * @since 1.1
 * @version 1.2
 */
if ( ! class_exists( 'myCRED_Email_Notice_Module' ) ) {
	class myCRED_Email_Notice_Module extends myCRED_Module {

		public $instances = array();

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Email_Notice_Module', array(
				'module_name' => 'emailnotices',
				'defaults'    => array(
					'from'        => array(
						'name'        => get_bloginfo( 'name' ),
						'email'       => get_bloginfo( 'admin_email' ),
						'reply_to'    => get_bloginfo( 'admin_email' )
					),
					'filter'      => array(
						'subject'     => 0,
						'content'     => 0
					),
					'use_html'    => true,
					'content'     => '',
					'styling'     => '',
					'send'        => '',
					'override'    => 0
				),
				'register'    => false,
				'add_to_core' => true
			) );
		}

		/**
		 * Hook into Init
		 * @since 1.1
		 * @version 1.2.1
		 */
		public function module_init() {
			$this->register_post_type();
			$this->setup_instances();

			add_action( 'mycred_admin_enqueue',      array( $this, 'enqueue_scripts' )    );
			add_filter( 'mycred_add_finished',       array( $this, 'email_check' ), 50, 3 );
			add_action( 'mycred_send_email_notices', 'mycred_email_notice_cron_job' );

			add_shortcode( 'mycred_email_subscriptions', array( $this, 'render_subscription_shortcode' ) );

			// Schedule Cron
			if ( ! isset( $this->emailnotices['send'] ) ) return;

			if ( $this->emailnotices['send'] == 'hourly' && wp_next_scheduled( 'mycred_send_email_notices' ) === false )
				wp_schedule_event( time(), 'hourly', 'mycred_send_email_notices' );

			elseif ( $this->emailnotices['send'] == 'daily' && wp_next_scheduled( 'mycred_send_email_notices' ) === false )
				wp_schedule_event( time(), 'daily', 'mycred_send_email_notices' );

			elseif ( $this->emailnotices['send'] == '' && wp_next_scheduled( 'mycred_send_email_notices' ) !== false )
				wp_clear_scheduled_hook( 'mycred_send_email_notices' );

		}

		/**
		 * Hook into Admin Init
		 * @since 1.1
		 * @version 1.1
		 */
		public function module_admin_init() {
			add_action( 'admin_head',            array( $this, 'admin_header' ) );
			add_filter( 'post_row_actions',      array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'manage_mycred_email_notice_posts_columns',       array( $this, 'adjust_column_headers' ), 50 );
			add_action( 'manage_mycred_email_notice_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );

			add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',      array( $this, 'enter_title_here' ) );
			add_filter( 'default_content',       array( $this, 'default_content' ) );

			add_action( 'add_meta_boxes',                array( $this, 'add_meta_boxes' ) );
			add_action( 'post_submitbox_start',          array( $this, 'publish_warning' ) );
			add_action( 'save_post_mycred_email_notice', array( $this, 'save_email_notice' ) );

			if ( $this->emailnotices['use_html'] === false )
				add_filter( 'user_can_richedit', array( $this, 'disable_richedit' ) );
		}

		/**
		 * Admin Header
		 * @since 1.1
		 * @version 1.0
		 */
		public function admin_header() {
			$screen = get_current_screen();
			if ( $screen->id == 'mycred_email_notice' && $this->emailnotices['use_html'] === false ) {
				remove_action( 'media_buttons', 'media_buttons' );
				echo '<style type="text/css">#ed_toolbar { display: none !important; }</style>';
			}
		}

		/**
		 * Enqueue Scripts & Styles
		 * @since 1.1
		 * @version 1.1
		 */
		public function enqueue_scripts() {

			// Register Email List Styling
			wp_register_style(
				'mycred-email-notices',
				plugins_url( 'assets/css/email-notice.css', myCRED_EMAIL ),
				false,
				myCRED_EMAIL_VERSION . '.1',
				'all'
			);

			// Register Edit Email Notice Styling
			wp_register_style(
				'mycred-email-edit-notice',
				plugins_url( 'assets/css/edit-email-notice.css', myCRED_EMAIL ),
				false,
				myCRED_EMAIL_VERSION . '.1',
				'all'
			);

			$screen = get_current_screen();
			// Commonly used
			if ( $screen->id == 'edit-mycred_email_notice' || $screen->id == 'mycred_email_notice' )
				wp_enqueue_style( 'mycred-admin' );

			// Edit Email Notice Styling
			if ( $screen->id == 'mycred_email_notice' )
				wp_enqueue_style( 'mycred-email-edit-notice' );

			// Email Notice List Styling
			elseif ( $screen->id == 'edit-mycred_email_notice' )
				wp_enqueue_style( 'mycred-email-notices' );

		}

		/**
		 * Register Email Notice Post Type
		 * @since 1.1
		 * @version 1.0
		 */
		public function register_post_type() {
			$labels = array(
				'name'               => __( 'Email Notices', 'mycred' ),
				'singular_name'      => __( 'Email Notice', 'mycred' ),
				'add_new'            => __( 'Add New', 'mycred' ),
				'add_new_item'       => __( 'Add New Notice', 'mycred' ),
				'edit_item'          => __( 'Edit Notice', 'mycred' ),
				'new_item'           => __( 'New Notice', 'mycred' ),
				'all_items'          => __( 'Email Notices', 'mycred' ),
				'view_item'          => __( 'View Notice', 'mycred' ),
				'search_items'       => __( 'Search Email Notices', 'mycred' ),
				'not_found'          => __( 'No email notices found', 'mycred' ),
				'not_found_in_trash' => __( 'No email notices found in Trash', 'mycred' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Email Notices', 'mycred' )
			);
			$args = array(
				'labels'             => $labels,
				'publicly_queryable' => false,
				'show_ui'            => true, 
				'show_in_menu'       => 'myCRED',
				'hierarchical'       => true,
				'capability_type'    => 'page',
				'supports'           => array( 'title', 'editor' )
			);
			register_post_type( 'mycred_email_notice', apply_filters( 'mycred_register_emailnotices', $args ) );
		}

		/**
		 * Setup Instances
		 * @since 1.1
		 * @version 1.1
		 */
		public function setup_instances() {

			$instances[''] = __( 'Select', 'mycred' );
			$instances['general'] = array(
				'label'    => __( 'General', 'mycred' ),
				'all'      => __( 'users balance changes', 'mycred' ),
				'positive' => __( 'user gains %_plural%', 'mycred' ),
				'negative' => __( 'user lose %_plural%', 'mycred' ),
				'zero'     => __( 'users balance reaches zero', 'mycred' ),
				'minus'    => __( 'users balance goes minus', 'mycred' ),
				'end'      => ''
			);

			if ( class_exists( 'myCRED_Badge_Module' ) ) {
				$instances['badges'] = array(
					'label'    => __( 'Badge Add-on', 'mycred' ),
					'positive' => __( 'user gains a badge', 'mycred' ),
					'end'      => ''
				);
			}

			if ( class_exists( 'myCRED_Sell_Content_Module' ) ) {
				$instances['buy_content'] = array(
					'label'    => __( 'Sell Content Add-on', 'mycred' ),
					'negative' => __( 'user buys content', 'mycred' ),
					'positive' => __( 'authors content gets sold', 'mycred' ),
					'end'      => ''
				);
			}

			if ( class_exists( 'myCRED_buyCRED_Module' ) ) {
				$instances['buy_creds'] = array(
					'label'    => __( 'buyCREDs Add-on', 'mycred' ),
					'positive' => __( 'user buys %_plural%', 'mycred' ),
					'end'      => ''
				);
			}

			if ( class_exists( 'myCRED_Transfer_Module' ) ) {
				$instances['transfer'] = array(
					'label'    => __( 'Transfer Add-on', 'mycred' ),
					'negative' => __( 'user sends %_plural%', 'mycred' ),
					'positive' => __( 'user receives %_plural%', 'mycred' ),
					'end'      => ''
				);
			}

			if ( class_exists( 'myCRED_Ranks_Module' ) ) {
				$instances['ranks'] = array(
					'label'    => __( 'Ranks Add-on', 'mycred' ),
					'negative' => __( 'user is demoted', 'mycred' ),
					'positive' => __( 'user is promoted', 'mycred' ),
					'end'      => ''
				);
			}

			$this->instances = apply_filters( 'mycred_email_instances', $instances );
		}

		/**
		 * Get Instance
		 * @since 1.1
		 * @version 1.0
		 */
		public function get_instance( $key = '', $detail = NULL ) {
			$instance_keys = explode( '|', $key );
			if ( $instance_keys === false || empty( $instance_keys ) || count( $instance_keys ) != 2 ) return NULL;

			// By default we return the entire array for the given key
			if ( $detail === NULL && array_key_exists( $instance_keys[0], $this->instances ) )
				return $this->core->template_tags_general( $this->instances[ $instance_keys[0] ][ $instance_keys[1] ] );

			if ( $detail !== NULL && array_key_exists( $detail, $this->instances[ $instance_keys[0] ] ) )
				return $this->core->template_tags_general( $this->instances[ $instance_keys[0] ][ $detail ] );

			return NULL;
		}

		/**
		 * Add to General Settings
		 * @since 1.1
		 * @version 1.1
		 */
		public function after_general_settings( $mycred ) {

			$this->emailnotices = mycred_apply_defaults( $this->default_prefs, $this->emailnotices ); ?>

<h4><div class="icon icon-active"></div><?php _e( 'Email Notices', 'mycred' ); ?></h4>
<div class="body" style="display:none;">
	<p><?php _e( 'Settings that apply to all email notices and can not be overridden for individual emails.', 'mycred' ); ?></p>
	<label class="subheader" for="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>"><?php _e( 'Email Format', 'mycred' ); ?></label>
	<ol id="myCRED-email-notice-use-html">
		<li>
			<input type="radio" name="<?php echo $this->field_name( 'use_html' ); ?>" id="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>" <?php checked( $this->emailnotices['use_html'], 0 ); ?> value="0" /> 
			<label for="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>"><?php _e( 'Plain text emails only.', 'mycred' ); ?></label>
		</li>
		<li>
			<input type="radio" name="<?php echo $this->field_name( 'use_html' ); ?>" id="<?php echo $this->field_id( array( 'use_html' => 'yes' ) ); ?>" <?php checked( $this->emailnotices['use_html'], 1 ); ?> value="1" /> 
			<label for="<?php echo $this->field_id( array( 'use_html' => 'yes' ) ); ?>"><?php _e( 'HTML or Plain text emails.', 'mycred' ); ?></label>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( array( 'filter' => 'subject' ) ); ?>"><?php _e( 'Filters', 'mycred' ); ?></label>
	<ol id="myCRED-email-notice-allow-filters">
		<li>
			<input type="checkbox" name="<?php echo $this->field_name( array( 'filter' => 'subject' ) ); ?>" id="<?php echo $this->field_id( array( 'filter' => 'subject' ) ); ?>" <?php checked( $this->emailnotices['filter']['subject'], 1 ); ?> value="1" />
			<label for="<?php echo $this->field_id( array( 'filter' => 'subject' ) ); ?>"><?php _e( 'Allow WordPress and Third Party Plugins to filter the email subject before an email is sent.', 'mycred' ); ?></label>
		</li>
		<li>
			<input type="checkbox" name="<?php echo $this->field_name( array( 'filter' => 'content' ) ); ?>" id="<?php echo $this->field_id( array( 'filter' => 'content' ) ); ?>" <?php checked( $this->emailnotices['filter']['content'], 1 ); ?> value="1" />
			<label for="<?php echo $this->field_id( array( 'filter' => 'content' ) ); ?>"><?php _e( 'Allow WordPress and Third Party Plugins to filter the email content before an email is sent.', 'mycred' ); ?></label>
		</li>
	</ol>
	<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>

	<label class="subheader" for="<?php echo $this->field_id( 'send' ); ?>"><?php _e( 'Email Schedule', 'mycred' ); ?></label>
	<ol id="myCRED-email-notice-schedule">
		<li><?php _e( 'WordPress Cron is disabled. Emails will be sent immediately.', 'mycred' ); ?><input type="hidden" name="<?php echo $this->field_name( 'send' ); ?>" value="" /></li>

	<?php else : ?>

	<label class="subheader" for="<?php echo $this->field_id( 'send' ); ?>"><?php _e( 'Email Schedule', 'mycred' ); ?></label>
	<ol id="myCRED-email-notice-schedule">
		<li>
			<input type="radio" name="<?php echo $this->field_name( 'send' ); ?>" id="<?php echo $this->field_id( 'send' ); ?>-hourly" <?php checked( $this->emailnotices['send'], '' ); ?> value="" />
			<label for="<?php echo $this->field_id( 'send' ); ?>-hourly"><?php _e( 'Send emails immediately', 'mycred' ); ?></label>
		</li>
		<li>
			<input type="radio" name="<?php echo $this->field_name( 'send' ); ?>" id="<?php echo $this->field_id( 'send' ); ?>" <?php checked( $this->emailnotices['send'], 'hourly' ); ?> value="hourly" />
			<label for="<?php echo $this->field_id( 'send' ); ?>"><?php _e( 'Send emails once an hour', 'mycred' ); ?></label>
		</li>
		<li>
			<input type="radio" name="<?php echo $this->field_name( 'send' ); ?>" id="<?php echo $this->field_id( 'send' ); ?>-daily" <?php checked( $this->emailnotices['send'], 'daily' ); ?> value="daily" />
			<label for="<?php echo $this->field_id( 'send' ); ?>-daily"><?php _e( 'Send emails once a day', 'mycred' ); ?></label>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'send' ); ?>"><?php _e( 'Subscriptions', 'mycred' ); ?></label>
	<ol id="myCRED-email-notice-schedule">
		<li><?php printf( __( 'Use the %s shortcode to allow users to subscribe / unsubscribe to email updates.', 'mycred' ), '<a href="http://codex.mycred.me/shortcodes/mycred_email_subscriptions/">mycred_email_subscriptions</a>' ); ?></p></li>
	</ol>

	<?php endif; ?>

	<label class="subheader" for="<?php echo $this->field_id( 'override' ); ?>"><?php _e( 'SMTP Override', 'mycred' ); ?></label>
	<ol id="myCRED-email-notice-override">
		<li>
			<input type="checkbox" name="<?php echo $this->field_name( 'override' ); ?>" id="<?php echo $this->field_id( 'override' ); ?>" <?php checked( $this->emailnotices['override'], 1 ); ?> value="1" />
			<label for="<?php echo $this->field_id( 'override' ); ?>"><?php _e( 'SMTP Debug. Enable if you are experiencing issues with wp_mail() or if you use a SMTP plugin for emails.', 'mycred' ); ?></label>
		</li>
	</ol>
	<p><?php _e( 'Default email settings. These settings can be individually overridden when editing emails.', 'mycred' ); ?></p>
	<label class="subheader"><?php _e( 'Email Settings', 'mycred' ); ?></label>
	<ol id="myCRED-email-default-sender">
		<li>
			<label for="<?php echo $this->field_id( array( 'from' => 'name' ) ); ?>"><?php _e( 'Senders Name:', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'from' => 'name' ) ); ?>" id="<?php echo $this->field_id( array( 'from' => 'name' ) ); ?>" value="<?php echo $this->emailnotices['from']['name']; ?>" class="long" /></div>
		</li>
		<li>
			<label for="<?php echo $this->field_id( array( 'from' => 'email' ) ); ?>"><?php _e( 'Senders Email:', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'from' => 'email' ) ); ?>" id="<?php echo $this->field_id( array( 'from' => 'email' ) ); ?>" value="<?php echo $this->emailnotices['from']['email']; ?>" class="long" /></div>
		</li>
		<li>
			<label for="<?php echo $this->field_id( array( 'from' => 'reply_to' ) ); ?>"><?php _e( 'Reply-To:', 'mycred' ); ?></label>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'from' => 'reply_to' ) ); ?>" id="<?php echo $this->field_id( array( 'from' => 'reply_to' ) ); ?>" value="<?php echo $this->emailnotices['from']['reply_to']; ?>" class="long" /></div>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'content' ); ?>"><?php _e( 'Default Email Content', 'mycred' ); ?></label>
	<ol id="myCRED-email-notice-defaults">
		<li>
			<textarea rows="10" cols="50" name="<?php echo $this->field_name( 'content' ); ?>" id="<?php echo $this->field_id( 'content' ); ?>" class="large-text code"><?php echo esc_attr( $this->emailnotices['content'] ); ?></textarea>
			<span class="description"><?php _e( 'Default email content.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'styling' ); ?>"><?php _e( 'Default Email Styling', 'mycred' ); ?></label>
	<ol>
		<li>
			<textarea rows="10" cols="50" name="<?php echo $this->field_name( 'styling' ); ?>" id="<?php echo $this->field_id( 'styling' ); ?>" class="large-text code"><?php echo esc_attr( $this->emailnotices['styling'] ); ?></textarea>
			<span class="description"><?php _e( 'Ignored if HTML is not allowed in emails.', 'mycred' ); ?></span>
		</li>
	</ol>
</div>
<?php
		}

		/**
		 * Save Settings
		 * @since 1.1
		 * @version 1.1
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			if ( ! isset( $data['emailnotices']['use_html'] ) )
				$data['emailnotices']['use_html'] = 0;

			$new_data['emailnotices']['use_html'] = ( $data['emailnotices']['use_html'] == 1 ) ? true : false;

			$new_data['emailnotices']['filter']['subject'] = ( isset( $data['emailnotices']['filter']['subject'] ) ) ? true : false;
			$new_data['emailnotices']['filter']['content'] = ( isset( $data['emailnotices']['filter']['content'] ) ) ? true : false;

			$new_data['emailnotices']['from']['name'] = sanitize_text_field( $data['emailnotices']['from']['name'] );
			$new_data['emailnotices']['from']['email'] = sanitize_text_field( $data['emailnotices']['from']['email'] );
			$new_data['emailnotices']['from']['reply_to'] = sanitize_text_field( $data['emailnotices']['from']['reply_to'] );

			$new_data['emailnotices']['content'] = sanitize_text_field( $data['emailnotices']['content'] );
			$new_data['emailnotices']['styling'] = sanitize_text_field( $data['emailnotices']['styling'] );

			$new_data['emailnotices']['send'] = sanitize_text_field( $data['emailnotices']['send'] );

			if ( ! isset( $data['emailnotices']['override'] ) )
				$data['emailnotices']['override'] = 0;

			$new_data['emailnotices']['override'] = ( $data['emailnotices']['override'] == 1 ) ? true : false;

			return $new_data;
		}

		/**
		 * Email Notice Check
		 * @since 1.4.6
		 * @version 1.1
		 */
		public function get_events_from_instance( $request, $mycred ) {

			extract( $request );

			$events = array( 'general|all' );

			// Events based on amount being given or taken
			if ( $amount < $mycred->zero() )
				$events[] = 'general|negative';
			else
				$events[] = 'general|positive';

			// Events based on this transaction leading to the users balance
			// reaching or surpassing zero
			$users_current_balance = $mycred->get_users_balance( $user_id, $type );
			if ( ( $users_current_balance - $amount ) < $mycred->zero() )
				$events[] = 'general|minus';
			elseif ( ( $users_current_balance - $amount ) == $mycred->zero() )
				$events[] = 'general|zero';

			// Ranks Related
			if ( function_exists( 'mycred_get_users_rank' ) ) {
				$rank_id = mycred_find_users_rank( $user_id, false, $type );
				if ( $rank_id !== NULL && mycred_user_got_demoted( $user_id, $rank_id ) )
					$events[] = 'ranks|negative';

				elseif ( $rank_id !== NULL && mycred_user_got_promoted( $user_id, $rank_id ) )
					$events[] = 'ranks|positive';
			}

			// Let others play
			return apply_filters( 'mycred_get_email_events', $events, $request, $mycred );

		}

		/**
		 * Email Notice Check
		 * @since 1.1
		 * @version 1.5
		 */
		public function email_check( $ran, $request, $mycred ) {

			// Exit now if $ran is false or new settings is not yet saved.
			if ( $ran === false || ! isset( $this->emailnotices['send'] ) ) return $ran;

			$user_id = absint( $request['user_id'] );

			// Construct events
			$events = $this->get_events_from_instance( $request, $mycred );

			// Badge Related
			if ( function_exists( 'mycred_ref_has_badge' ) ) {

				/*
					In order for us to save on database queries down the line, we will
					check if the user got any badges for this instnace and save the badge ids
					under "badges".
					
					Since the process is already completed and we are simply "reacting" to the
					event, we can manipulte $request without it having any effect on other features.
				*/
				$badge_ids = mycred_ref_has_badge( $request['ref'] );
				if ( ! empty( $badge_ids ) ) {
					$badges = mycred_check_if_user_gets_badge( $user_id, $badge_ids, false );
					if ( ! empty( $badges ) ) {
						$events[] = 'badges|positive';
						$request['badges'] = $badges;
					}
				}

			}

			// Do not send emails now
			if ( $this->emailnotices['send'] != '' ) {

				// Save for cron job
				mycred_add_user_meta( $user_id, 'mycred_scheduled_email_notices', '', array(
					'events'  => $events,
					'request' => $request
				) );

			}

			// Send emails now
			else {

				$this->do_email_notices( $events, $request );

			}

			return $ran;

		}

		/**
		 * Do Email Notices
		 * @since 1.1
		 * @version 1.2
		 */
		public function do_email_notices( $events = array(), $request = array() ) {

			if ( ! isset( $request['user_id'] ) || empty( $events ) ) return;

			extract( $request );

			// Get all notices that a user has unsubscribed to
			$unsubscriptions = (array) mycred_get_user_meta( $user_id, 'mycred_email_unsubscriptions', '', true );

			global $wpdb;

			// Loop though events
			foreach ( $events as $event ) {

				// Get the email notice post object
				$notice = $wpdb->get_row( $wpdb->prepare( "
					SELECT * 
					FROM {$wpdb->posts} notices

					LEFT JOIN {$wpdb->postmeta} instances 
						ON ( notices.ID = instances.post_id AND instances.meta_key = 'mycred_email_instance' )

					LEFT JOIN {$wpdb->postmeta} pointtype 
						ON ( notices.ID = pointtype.post_id AND pointtype.meta_key = 'mycred_email_ctype' )

					WHERE instances.meta_value = %s 
						AND pointtype.meta_value IN (%s,'all') 
						AND notices.post_type = 'mycred_email_notice' 
						AND notices.post_status = 'publish';", $event, $request['type'] ) );

				// Notice found
				if ( $notice !== NULL ) {

					// Ignore unsubscribed events
					if ( in_array( $notice->ID, $unsubscriptions ) ) continue;

					// Get notice setup
					$settings = $this->get_email_settings( $notice->ID );

					// Send to user
					if ( $settings['recipient'] == 'user' || $settings['recipient'] == 'both' ) {
						$user = get_user_by( 'id', $user_id );
						$to = $user->user_email;
						unset( $user );
					}
					
					elseif ( $settings['recipient'] == 'admin' ) {
						$to = get_option( 'admin_email' );
					}

					// Filtered Subject
					if ( $this->emailnotices['filter']['subject'] === true )
						$subject = get_the_title( $notice->ID );

					// Unfiltered Subject
					else $subject = $notice->post_title;

					// Filtered Content
					if ( $this->emailnotices['filter']['content'] === true )
						$message = apply_filters( 'the_content', $notice->post_content );

					// Unfiltered Content
					else $message = $notice->post_content;

					$headers = array();
					$attachments = '';

					if ( ! $this->emailnotices['override'] ) {

						// Construct headers
						if ( $this->emailnotices['use_html'] === true ) {
							$headers[] = 'MIME-Version: 1.0';
							$headers[] = 'Content-Type: text/HTML; charset="' . get_option( 'blog_charset' ) . '"';
						}
						$headers[] = 'From: ' . $settings['senders_name'] . ' <' . $settings['senders_email'] . '>';

						// Reply-To
						if ( $settings['reply_to'] != '' )
							$headers[] = 'Reply-To: ' . $settings['reply_to'];

						// Both means we blank carbon copy the admin so the user does not see email
						if ( $settings['recipient'] == 'both' )
							$headers[] = 'Bcc: ' . get_option( 'admin_email' );

						// If email was successfully sent we update 'last_run'
						if ( $this->wp_mail( $to, $subject, $message, $headers, $attachments, $request, $notice->ID ) === true )
							update_post_meta( $notice->ID, 'mycred_email_last_run', time() );

					}
					else {

						// If email was successfully sent we update 'last_run'
						if ( $this->wp_mail( $to, $subject, $message, $headers, $attachments, $request, $notice->ID ) === true ) {
							update_post_meta( $notice->ID, 'mycred_email_last_run', time() );

							if ( $settings['recipient'] == 'both' )
								$this->wp_mail( get_option( 'admin_email' ), $subject, $message, $headers, $attachments, $request, $notice->ID );
						}

					}

				}
			}
		}

		/**
		 * WP Mail
		 * @since 1.1
		 * @version 1.3.1
		 */
		public function wp_mail( $to, $subject, $message, $headers, $attachments, $request, $email_id ) {

			// Let others play before we do our thing
			$filtered = apply_filters( 'mycred_email_before_send', compact( 'to', 'subject', 'message', 'headers', 'attachments', 'request', 'email_id' ) );

			if ( ! isset( $filtered['request'] ) || ! is_array( $filtered['request'] ) ) return false;

			$subject = $this->template_tags_request( $filtered['subject'], $filtered['request'] );
			$message = $this->template_tags_request( $filtered['message'], $filtered['request'] );

			$entry = new stdClass();
			foreach ( $filtered['request'] as $key => $value )
				$entry->$key = $value;

			$mycred = mycred( $filtered['request']['type'] );

			$subject = $mycred->template_tags_user( $subject, $filtered['request']['user_id'] );
			$message = $mycred->template_tags_user( $message, $filtered['request']['user_id'] );

			$subject = $mycred->template_tags_amount( $subject, $filtered['request']['amount'] );
			$message = $mycred->template_tags_amount( $message, $filtered['request']['amount'] );
			
			$subject = $mycred->parse_template_tags( $subject, $entry );
			$message = $mycred->parse_template_tags( $message, $entry );

			// Construct HTML Content
			if ( $this->emailnotices['use_html'] === true ) {
				$styling = $this->get_email_styling( $email_id );
				$message = '<html><head><title>' . $subject . '</title><style type="text/css" media="all"> ' . trim( $styling ) . '</style></head><body>' . nl2br( $message ) . '</body></html>';
			}

			// Send Email
			add_filter( 'wp_mail_content_type', array( $this, 'get_email_format' ) );
			$result = wp_mail( $filtered['to'], $subject, $message, $filtered['headers'], $filtered['attachments'] );
			remove_filter( 'wp_mail_content_type', array( $this, 'get_email_format' ) );

			// Let others play
			do_action( 'mycred_email_sent', $filtered );

			return $result;

		}

		/**
		 * Get Email Format
		 * @since 1.1
		 * @version 1.0
		 */
		public function get_email_format() {
			if ( $this->emailnotices['use_html'] === false )
				return 'text/plain';
			else
				return 'text/html';
		}

		/**
		 * Request Related Template Tags
		 * @since 1.1
		 * @version 1.2
		 */
		public function template_tags_request( $content, $request ) {

			$type = $this->core;
			if ( $request['type'] != 'mycred_default' )
				$type = mycred( $request['type'] );

			$content = str_replace( '%new_balance%',   $new_balance, $content );
			$content = str_replace( '%new_balance_f%', $type->format_creds( $new_balance ), $content );

			if ( $request['amount'] > 0 )
				$old_balance = $type->number( $new_balance - $request['amount'] );
			else
				$old_balance = $type->number( $new_balance + $request['amount'] );

			$content = str_replace( '%old_balance%',   $old_balance, $content );
			$content = str_replace( '%old_balance_f%', $type->format_creds( $old_balance ), $content );

			$content = str_replace( '%amount%', $request['amount'], $content );
			$content = str_replace( '%entry%',  $request['entry'], $content );
			$content = str_replace( '%data%',   $request['data'], $content );

			$content = str_replace( '%blog_name%',   get_option( 'blogname' ), $content );
			$content = str_replace( '%blog_url%',    get_option( 'home' ), $content );
			$content = str_replace( '%blog_info%',   get_option( 'blogdescription' ), $content );
			$content = str_replace( '%admin_email%', get_option( 'admin_email' ), $content );
			$content = str_replace( '%num_members%', $this->core->count_members(), $content );

			// Badges related
			if ( function_exists( 'mycred_ref_has_badge' ) && isset( $request['badges'] ) ) {

				$titles = array();
				$images = array();
				foreach ( $request['badges'] as $level => $badge_id ) {

					$badge_id = absint( $badge_id );
					$title = sprintf( _x( '%s - Level %d', 'Badge Title - Level 1,2,3..', 'mycred' ), get_the_title( $badge_id ), $level );
					$titles[] = $title;

					// Level image first
					$level_image = get_post_meta( $badge_id, 'level_image' . $level, true );

					// Default to main image
					if ( $level_image == '' )
						$level_image = get_post_meta( $badge_id, 'main_image', true );

					if ( $level_image == '' )
						$images[] = '<img src="' . $level_image . '" alt="' . $title . '" />';

				}
					
				$title = implode( ', ', $titles );
				$content = str_replace( '%badge_title%', $title, $content );

				$image = implode( ' ', $images );
				$content = str_replace( '%badge_image%', $image, $content );

			}

			return $content;
		}

		/**
		 * Get Email Settings
		 * @since 1.1
		 * @version 1.1
		 */
		public function get_email_settings( $post_id ) {
			$settings = get_post_meta( $post_id, 'mycred_email_settings', true );
			if ( $settings == '' )
				$settings = array();

			// Defaults
			$default = array(
				'recipient'     => 'user',
				'senders_name'  => $this->emailnotices['from']['name'],
				'senders_email' => $this->emailnotices['from']['email'],
				'reply_to'      => $this->emailnotices['from']['reply_to'],
				'label'         => ''
			);

			$settings = mycred_apply_defaults( $default, $settings );

			return apply_filters( 'mycred_email_notice_settings', $settings, $post_id );
		}

		/**
		 * Get Email Styling
		 * @since 1.1
		 * @version 1.0
		 */
		public function get_email_styling( $post_id ) {
			if ( $this->emailnotices['use_html'] === false ) return '';
			$style = get_post_meta( $post_id, 'mycred_email_styling', true );
			// Defaults
			if ( empty( $style ) )
				return $this->emailnotices['styling'];

			return $style;
		}

		/**
		 * Adjust Row Actions
		 * @since 1.1
		 * @version 1.0
		 */
		public function adjust_row_actions( $actions, $post ) {
			if ( $post->post_type == 'mycred_email_notice' ) {
				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['view'] );
			}

			return $actions;
		}

		/**
		 * Adjust Column Header
		 * @since 1.1
		 * @version 1.1
		 */
		public function adjust_column_headers( $defaults ) {

			$columns = array();
			$columns['cb'] = $defaults['cb'];

			// Add / Adjust
			$columns['title']                  = __( 'Email Subject', 'mycred' );
			$columns['mycred-email-status']    = __( 'Status', 'mycred' );
			$columns['mycred-email-reference'] = __( 'Setup', 'mycred' );

			if ( count( $this->point_types ) > 1 )
				$columns['mycred-email-ctype'] = __( 'Point Type', 'mycred' );

			// Return
			return $columns;

		}

		/**
		 * Adjust Column Content
		 * @since 1.1
		 * @version 1.0
		 */
		public function adjust_column_content( $column_name, $post_id ) {

			// Get the post
			if ( in_array( $column_name, array( 'mycred-email-status', 'mycred-email-reference', 'mycred-email-ctype' ) ) )
				$post = get_post( $post_id );

			// Email Status Column
			if ( $column_name == 'mycred-email-status' ) {
				if ( $post->post_status != 'publish' && $post->post_status != 'future' )
					echo '<p>' . __( 'Not Active', 'mycred' ) . '</p>';

				elseif ( $post->post_status == 'future' )
					echo '<p>' . sprintf( __( 'Scheduled:<br /><strong>%1$s</strong>', 'mycred' ), date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $post->post_date ) ) ) . '</p>';

				else {
					$date = get_post_meta( $post_id, 'mycred_email_last_run', true );
					if ( empty( $date ) )
						echo '<p>' . __( 'Active', 'mycred' ) . '</p>';
					else
						echo '<p>' . sprintf( __( 'Active - Last run:<br /><strong>%1$s</strong>', 'mycred' ), date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $date ) ) . '</p>';
				}
			}

			// Email Setup Column
			elseif ( $column_name == 'mycred-email-reference' ) {
				echo '<p>';
				$instance_key = get_post_meta( $post->ID, 'mycred_email_instance', true );
				$label = $this->get_instance( $instance_key );
				if ( ! empty( $instance_key ) && ! empty( $label ) )
					echo '<em>' . __( 'Email is sent when', 'mycred' ) .' ' . $label . '.</em></br />';
				else
					echo '<em>' . __( 'Missing instance for this notice!', 'mycred' ) . '</em><br />';

				$settings = get_post_meta( $post->ID, 'mycred_email_settings', true );
				if ( ! empty( $settings ) && isset( $settings['recipient'] ) )
					$recipient = $settings['recipient'];
				else
					$recipient = 'user';

				if ( $recipient == 'user' )
					echo '<strong>' . __( 'Sent To', 'mycred' ) . '</strong>: ' . __( 'User', 'mycred' ) . '</p>';
				elseif ( $recipient == 'admin' )
					echo '<strong>' . __( 'Sent To', 'mycred' ) . '</strong>: ' . __( 'Administrator', 'mycred' ) . '</p>';
				else
					echo '<strong>' . __( 'Sent To', 'mycred' ) . '</strong>: ' . __( 'Both Administrator and User', 'mycred' ) . '</p>';
			}

			// Email Setup Column
			elseif ( $column_name == 'mycred-email-ctype' ) {

				$type = get_post_meta( $post_id, 'mycred_email_ctype', true );
				if ( $type == '' ) $type = 'all';

				if ( $type == 'all' )
					echo __( 'All types', 'mycred' );

				elseif ( array_key_exists( $type, $this->point_types ) )
					echo $this->point_types[ $type ];

				else
					echo '-';

			}

		}

		/**
		 * Add Meta Boxes
		 * @since 1.1
		 * @version 1.0
		 */
		public function add_meta_boxes() {
			add_meta_box(
				'mycred_email_settings',
				__( 'Email Settings', 'mycred' ),
				array( $this, 'email_settings' ),
				'mycred_email_notice',
				'side',
				'high'
			);

			add_meta_box(
				'mycred_email_template_tags',
				__( 'Available Template Tags', 'mycred' ),
				array( $this, 'template_tags' ),
				'mycred_email_notice',
				'normal',
				'core'
			);

			if ( $this->emailnotices['use_html'] === false ) return;

			add_meta_box(
				'mycred_email_header',
				__( 'Email Header', 'mycred' ),
				array( $this, 'email_header' ),
				'mycred_email_notice',
				'normal',
				'high'
			);
		}

		/**
		 * Disable WYSIWYG Editor
		 * @since 1.1
		 * @version 1.0
		 */
		public function disable_richedit( $default ) {
			global $post;
			if ( $post->post_type == 'mycred_email_notice' )
				return false;

			return $default;
		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.1
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {
			global $post_type;
			if ( $post_type == 'mycred_email_notice' )
				return __( 'Email Subject', 'mycred' );

			return $title;
		}

		/**
		 * Apply Default Content
		 * @since 1.1
		 * @version 1.0
		 */
		public function default_content( $content ) {
			global $post_type;
			if ( $post_type == 'mycred_email_notice' && !empty( $this->emailnotices['content'] ) )
				$content = $this->emailnotices['content'];

			return $content;
		}

		/**
		 * Email Settings Metabox
		 * @since 1.1
		 * @version 1.1
		 */
		public function email_settings( $post ) {

			// Get instance
			$instance = get_post_meta( $post->ID, 'mycred_email_instance', true );
			// Get settings
			$settings = $this->get_email_settings( $post->ID );

			$set_type = get_post_meta( $post->ID, 'mycred_email_ctype', true );
			if ( $set_type == '' )
				$set_type = 'mycred_default';
?>

<div class="misc-pub-section">
	<input type="hidden" name="mycred_email[token]" value="<?php echo wp_create_nonce( 'mycred-edit-email' ); ?>" />
	<label for="mycred-email-instance"<?php if ( $post->post_status == 'publish' && empty( $instance ) ) echo ' style="color:red;font-weight:bold;"'; ?>><?php _e( 'Send this email notice when...', 'mycred' ); ?></label><br />
	<select name="mycred_email[instance]" id="mycred-email-instance">
<?php
			// Default
			echo '<option value=""';
			if ( empty( $instance ) ) echo ' selected="selected"';
			echo '>' . __( 'Select', 'mycred' ) . '</option>';

			// Loop though instances
			foreach ( $this->instances as $hook_ref => $values ) {
				if ( is_array( $values ) ) {
					foreach ( $values as $key => $value ) {
						// Make sure that the submitted value is unique
						$key_value = $hook_ref . '|' . $key;
						// Option group starts with 'label'
						if ( $key == 'label' )
							echo '<optgroup label="' . $value . '">';
						// Option group ends with 'end'
						elseif ( $key == 'end' )
							echo '</optgroup>';
						// The selectable options
						else {
							echo '<option value="' . $key_value . '"';
							if ( $instance == $key_value ) echo ' selected="selected"';
							echo '>... ' . $this->core->template_tags_general( $value ) . '</option>';
						}
					}
				}
			} ?>

	</select><br />
	<label for="mycred-email-recipient-user"><?php _e( 'Recipient:', 'mycred' ); ?></label><br />
	<div class="mycred-inline">
		<label for="mycred-email-recipient-user"><input type="radio" name="mycred_email[recipient]" id="mycred-email-recipient-user" value="user" <?php checked( $settings['recipient'], 'user' ); ?> /> <?php _e( 'User', 'mycred' ); ?></label>
		<label for="mycred-email-recipient-admin"><input type="radio" name="mycred_email[recipient]" id="mycred-email-recipient-admin" value="admin" <?php checked( $settings['recipient'], 'admin' ); ?> /> <?php _e( 'Administrator', 'mycred' ); ?></label>
		<label for="mycred-email-recipient-both"><input type="radio" name="mycred_email[recipient]" id="mycred-email-recipient-both" value="both" <?php checked( $settings['recipient'], 'both' ); ?> /> <?php _e( 'Both', 'mycred' ); ?></label>
	</div>
</div>
<div class="misc-pub-section">
	<label for="mycred-email-label"><?php _e( 'Label', 'mycred' ); ?></label><br />
	<input type="text" name="mycred_email[label]" id="mycred-email-label" value="<?php echo $settings['label']; ?>" />
</div>

<?php if ( count( $this->point_types ) > 1 ) : ?>
<div class="misc-pub-section">
	<label for="mycred-email-ctype"><?php _e( 'Point Type', 'mycred' ); ?></label><br />
	<select name="mycred_email[ctype]" id="mycred-email-ctype">
<?php

			echo '<option value="all"';
			if ( $set_type == 'all' ) echo ' selected="selected"';
			echo '>' . __( 'All types', 'mycred' ) . '</option>';

			foreach ( $this->point_types as $type_id => $label ) {
				echo '<option value="' . $type_id . '"';
				if ( $set_type == $type_id ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>
	</select>
</div>
<?php else : ?>
<input type="hidden" name="mycred_email[ctype]" id="mycred-email-ctype" value="mycred_default" />
<?php endif; ?>

<div class="misc-pub-section">
	<label for="mycred-email-senders-name"><?php _e( 'Senders Name:', 'mycred' ); ?></label><br />
	<input type="text" name="mycred_email[senders_name]" id="mycred-email-senders-name" value="<?php echo $settings['senders_name']; ?>" /><br />
	<label for="mycred-email-senders-email"><?php _e( 'Senders Email:', 'mycred' ); ?></label><br />
	<input type="text" name="mycred_email[senders_email]" id="mycred-email-senders-email" value="<?php echo $settings['senders_email']; ?>" /><br />
	<label for="mycred-email-reply-to"><?php _e( 'Reply-To Email:', 'mycred' ); ?></label><br />
	<input type="text" name="mycred_email[reply_to]" id="mycred-email-reply-to" value="<?php echo $settings['reply_to']; ?>" />
</div>
<?php do_action( 'mycred_email_settings_box', $this ); ?>

<div class="mycred-save">
	<?php submit_button( __( 'Save', 'mycred' ), 'primary', 'save', false ); ?>
</div>
<?php
		}

		/**
		 * Email Header Metabox
		 * @since 1.1
		 * @version 1.0
		 */
		public function email_header( $post ) { ?>

<p><label for="mycred-email-styling"><?php _e( 'CSS Styling', 'mycred' ); ?></label></p>
<textarea name="mycred_email[styling]" id="mycred-email-styling"><?php echo $this->get_email_styling( $post->ID ); ?></textarea>
<?php do_action( 'mycred_email_header_box', $this ); ?>

<?php
		}

		/**
		 * Template Tags Metabox
		 * @since 1.1
		 * @version 1.2
		 */
		public function template_tags( $post ) {

			echo '
<ul>
	<li class="title">' . __( 'Site Related', 'mycred' ) . '</li>
	<li><strong>%blog_name%</strong><div>' . __( 'Your websites title', 'mycred' ) . '</div></li>
	<li><strong>%blog_url%</strong><div>' . __( 'Your websites address', 'mycred' ) . '</div></li>
	<li><strong>%blog_info%</strong><div>' . __( 'Your websites tagline (description)', 'mycred' ) . '</div></li>
	<li><strong>%admin_email%</strong><div>' . __( 'Your websites admin email', 'mycred' ) . '</div></li>
	<li><strong>%num_members%</strong><div>' . __( 'Total number of blog members', 'mycred' ) . '</div></li>
</ul>
<ul>
	<li class="title">Instance Related</li>
	<li><strong>%new_balance%</strong><div>' . __( 'The users new balance', 'mycred' ) . '</div></li>
	<li><strong>%old_balance%</strong><div>' . __( 'The users old balance', 'mycred' ) . '</div></li>
	<li><strong>%amount%</strong><div>' . __( 'The amount of points gained or lost in this instance', 'mycred' ) . '</div></li>
	<li><strong>%entry%</strong><div>' . __( 'The log entry', 'mycred' ) . '</div></li>
</ul>
<div class="clear"></div>';
		}

		/**
		 * Save Email Notice Details
		 * @since 1.1
		 * @version 1.2
		 */
		public function save_email_notice( $post_id ) {

			if ( ! isset( $_POST['mycred_email'] ) || ! is_array( $_POST['mycred_email'] ) || ! wp_verify_nonce( $_POST['mycred_email']['token'], 'mycred-edit-email' ) ) return;

			// Update Instance
			if ( ! empty( $_POST['mycred_email']['instance'] ) ) {
				// Lets make sure the value is properly formatted otherwise things could go uggly later
				$instance_key = trim( $_POST['mycred_email']['instance'] );
				$keys = explode( '|', $instance_key );
				if ( $keys !== false && !empty( $keys ) );
					update_post_meta( $post_id, 'mycred_email_instance', $instance_key );
			}

			// Construct new settings
			$settings = array();
			// If recipient is set but differs from the default, use the posted one else use default
			if ( ! empty( $_POST['mycred_email']['recipient'] ) )
				$settings['recipient'] = $_POST['mycred_email']['recipient'];
			else
				$settings['recipient'] = 'user';

			// If senders name is set but differs from the default, use the posted one else use default
			if ( ! empty( $_POST['mycred_email']['senders_name'] ) )
				$settings['senders_name'] = $_POST['mycred_email']['senders_name'];
			else
				$settings['senders_name'] = $this->emailnotices['from']['name'];

			// If senders email is set but differs from the default, use the posted one else use default
			if ( ! empty( $_POST['mycred_email']['senders_email'] ) )
				$settings['senders_email'] = $_POST['mycred_email']['senders_email'];
			else
				$settings['senders_email'] = $this->emailnotices['from']['email'];

			// If senders email is set but differs from the default, use the posted one else use default
			if ( ! empty( $_POST['mycred_email']['reply_to'] ) )
				$settings['reply_to'] = $_POST['mycred_email']['reply_to'];
			else
				$settings['reply_to'] = $this->emailnotices['from']['reply_to'];

			$settings['label'] = sanitize_text_field( $_POST['mycred_email']['label'] );

			// Save settings
			update_post_meta( $post_id, 'mycred_email_settings', $settings );

			$point_type = sanitize_text_field( $_POST['mycred_email']['ctype'] );
			update_post_meta( $post_id, 'mycred_email_ctype', $point_type );

			// If rich editing is disabled bail now
			if ( $this->emailnotices['use_html'] === false ) return;

			// Save styling
			if ( ! empty( $_POST['mycred_email']['styling'] ) )
				update_post_meta( $post_id, 'mycred_email_styling', trim( $_POST['mycred_email']['styling'] ) );
		}

		/**
		 * Adjust Post Updated Messages
		 * @since 1.1
		 * @version 1.0
		 */
		public function post_updated_messages( $messages ) {
			global $post;

			$messages['mycred_email_notice'] = array(
				0 => '',
				1 => __( 'Email Notice Updated.', 'mycred' ),
				2 => '',
				3 => '',
				4 => __( 'Email Notice Updated.', 'mycred' ),
				5 => false,
				6 => __( 'Email Notice Activated', 'mycred' ),
				7 => __( 'Email Notice Saved', 'mycred' ),
				8 => '',
				9 => '',
				10 => __( 'Email Notice Updated.', 'mycred' )
			);

			return $messages;
		}

		/**
		 * Add Publish Notice
		 * @since 1.1
		 * @version 1.0
		 */
		public function publish_warning() {
			global $post;
			if ( $post->post_type != 'mycred_email_notice' ) return;

			if ( $post->post_status != 'publish' && $post->post_status != 'future' )
				echo '<p id="mycred-email-notice">' . __( 'Once a notice is "published" it becomes active! Select "Save Draft" if you are not yet ready to use this email notice!', 'mycred' ) . '</p>';
			elseif ( $post->post_status == 'future' )
				echo '<p id="mycred-email-notice">' . sprintf( __( 'This notice will become active on:<br /><strong>%1$s</strong>', 'mycred' ), date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $post->post_date ) ) ) . '</p>';
			else
				echo '<p id="mycred-email-notice">' . __( 'This email notice is active.', 'mycred' ) . '</p>';
		}

		/**
		 * Subscription Shortcode
		 * @since 1.4.6
		 * @version 1.0
		 */
		public function render_subscription_shortcode( $attr, $content = NULL ) {

			extract( shortcode_atts( array(
				'success' => __( 'Settings saved.', 'mycred' )
			), $attr ) );

			if ( ! is_user_logged_in() ) return $content;

			$user_id = get_current_user_id();

			$unsubscriptions = mycred_get_user_meta( $user_id, 'mycred_email_unsubscriptions', '', true );
			if ( $unsubscriptions == '' )
				$unsubscriptions = array();

			// Save
			$saved = false;
			if ( isset( $_REQUEST['do'] ) && $_REQUEST['do'] == 'mycred-unsubscribe' && wp_verify_nonce( $_REQUEST['token'], 'update-mycred-email-subscriptions' ) ) {

				if ( isset( $_POST['mycred_email_unsubscribe'] ) && ! empty( $_POST['mycred_email_unsubscribe'] ) )
					$new_selection = $_POST['mycred_email_unsubscribe'];
				else
					$new_selection = array();

				mycred_update_user_meta( $user_id, 'mycred_email_unsubscriptions', '', $new_selection );
				$unsubscriptions = $new_selection;
				$saved = true;

			}

			global $wpdb;

			$email_notices = $wpdb->get_results( $wpdb->prepare( "
				SELECT * 
				FROM {$wpdb->posts} notices

				LEFT JOIN {$wpdb->postmeta} prefs 
					ON ( notices.ID = prefs.post_id AND prefs.meta_key = 'mycred_email_settings' )

				WHERE notices.post_type = 'mycred_email_notice' 
					AND notices.post_status = 'publish'
					AND ( prefs.meta_value LIKE %s OR prefs.meta_value LIKE %s );", '%s:9:"recipient";s:4:"user";%', '%s:9:"recipient";s:4:"both";%' ) );

			ob_start();
			
			if ( $saved )
				echo '<p class="updated-email-subscriptions">' . $success . '</p>'; ?>

<form action="<?php echo add_query_arg( array( 'do' => 'mycred-unsubscribe', 'user' => get_current_user_id(), 'token' => wp_create_nonce( 'update-mycred-email-subscriptions' ) ) ); ?>" id="mycred-email-subscriptions" method="post">
	<table class="table">
		<thead>
			<tr>
				<th class="check"><?php _e( 'Unsubscribe', 'mycred' ); ?></th>
				<th class="notice-title"><?php _e( 'Email Notice', 'mycred' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! empty( $email_notices ) ) : ?>
		
			<?php foreach ( $email_notices as $notice ) : $settings = $this->get_email_settings( $notice->ID ); ?>

			<?php if ( $settings['label'] == '' ) continue; ?>

			<tr>
				<td class="check"><input type="checkbox" name="mycred_email_unsubscribe[]"<?php if ( in_array( $notice->ID, $unsubscriptions ) ) echo ' checked="checked"'; ?> value="<?php echo $notice->ID; ?>" /></td>
				<td class="notice-title"><?php echo $settings['label']; ?></td>
			</tr>

			<?php endforeach; ?>
		
		<?php else : ?>

			<tr>
				<td colspan="2"><?php _e( 'There are no email notifications yet.', 'mycred' ); ?></td>
			</tr>

		<?php endif; ?>
		</tbody>
	</table>
	<input type="submit" class="btn btn-primary button button-primary pull-right" value="<?php _e( 'Save Changes', 'mycred' ); ?>" />
</form>
<?php
			$content = ob_get_contents();
			ob_end_clean();

			return apply_filters( 'mycred_render_email_subscriptions', $content, $attr );

		}

	}

	$email_notice = new myCRED_Email_Notice_Module();
	$email_notice->load();
}

/**
 * myCRED Email Notifications Cron Job
 * @since 1.2
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_email_notice_cron_job' ) ) :
	function mycred_email_notice_cron_job() {
		if ( ! class_exists( 'myCRED_Email_Notice_Module' ) ) return;

		$email_notice = new myCRED_Email_Notice_Module();

		global $wpdb;

		$pending = $wpdb->get_results( "
			SELECT * 
			FROM {$wpdb->usermeta} 
			WHERE meta_key = 'mycred_scheduled_email_notices';" );

		if ( $pending ) {

			foreach ( $pending as $instance ) {

				$_instance = maybe_unserialize( $instance->meta_value );
				$email_notice->do_email_notices( $_instance['events'], $_instance['request'] );

				$wpdb->delete(
					$wpdb->usermeta,
					array( 'umeta_id' => $instance->umeta_id ),
					array( '%d' )
				);

			}

		}
	}
endif;
?>