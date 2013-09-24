<?php
/**
 * Addon: Email Notices
 * Addon URI: http://mycred.me/add-ons/email-notices/
 * Version: 1.0
 * Description: Create email notices for any type of myCRED instance.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
// Translate Header (by Dan bp-fr)
$mycred_addon_header_translate = array(
	__( 'Email Notices', 'mycred' ),
	__( 'Create email notices for any type of myCRED instance.', 'mycred' )
);

if ( !defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_EMAIL',         __FILE__ );
define( 'myCRED_EMAIL_VERSION', myCRED_VERSION . '.1' );
/**
 * myCRED_Email_Notices class
 *
 * 
 * @since 1.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Email_Notices' ) ) {
	class myCRED_Email_Notices extends myCRED_Module {

		public $instances = array();

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Email_Notices', array(
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
					'styling'     => ''
				),
				'register'    => false,
				'add_to_core' => true
			) );

			//add_action( 'mycred_help',           array( $this, 'help' ), 10, 2 );
		}

		/**
		 * Hook into Init
		 * @since 1.1
		 * @version 1.0.1
		 */
		public function module_init() {
			$this->register_post_type();
			$this->setup_instances();
			add_action( 'mycred_admin_enqueue', array( $this, 'enqueue_scripts' )    );
			add_filter( 'mycred_add',           array( $this, 'email_check' ), 20, 3 );
		}

		/**
		 * Hook into Admin Init
		 * @since 1.1
		 * @version 1.0
		 */
		public function module_admin_init() {
			add_action( 'admin_head',            array( $this, 'admin_header' )              );
			add_filter( 'post_row_actions',      array( $this, 'adjust_row_actions' ), 10, 2 );
			
			add_filter( 'manage_mycred_email_notice_posts_columns',       array( $this, 'adjust_column_headers' )        );
			add_action( 'manage_mycred_email_notice_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );
			
			add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',      array( $this, 'enter_title_here' )      );
			add_filter( 'default_content',       array( $this, 'default_content' )       );
			
			add_action( 'add_meta_boxes',        array( $this, 'add_meta_boxes' )        );
			add_action( 'post_submitbox_start',  array( $this, 'publish_warning' )       );
			add_action( 'save_post',             array( $this, 'save_email_notice' )     );
			
			if ( $this->emailnotices['use_html'] === false )
				add_filter( 'user_can_richedit', array( $this, 'disable_richedit' )      );
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
				echo '
<style type="text/css">
#ed_toolbar { display: none !important; }
</style>';
			}
		}

		/**
		 * Enqueue Scripts & Styles
		 * @since 1.1
		 * @version 1.0
		 */
		public function enqueue_scripts() {
			// Register Email List Styling
			wp_register_style(
				'mycred-email-notices',
				plugins_url( 'css/email-notice.css', myCRED_EMAIL ),
				false,
				myCRED_EMAIL_VERSION . '.1',
				'all'
			);
			// Register Edit Email Notice Styling
			wp_register_style(
				'mycred-email-edit-notice',
				plugins_url( 'css/edit-email-notice.css', myCRED_EMAIL ),
				false,
				myCRED_EMAIL_VERSION . '.1',
				'all'
			);

			$screen = get_current_screen();
			// Commonly used
			if ( $screen->id == 'edit-mycred_email_notice' || $screen->id == 'mycred_email_notice' ) {
				wp_enqueue_style( 'mycred-admin' );
			}

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
		protected function register_post_type() {
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
				'capability_type'    => 'page',
				'supports'           => array( 'title', 'editor' )
			);
			register_post_type( 'mycred_email_notice', $args );
		}

		/**
		 * Setup Instances
		 * @since 1.1
		 * @version 1.1
		 */
		protected function setup_instances() {
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

			if ( class_exists( 'myCRED_Sell_Content' ) ) {
				$instances['buy_content'] = array(
					'label'    => __( 'Sell Content Add-on', 'mycred' ),
					'negative' => __( 'user buys content', 'mycred' ),
					'positive' => __( 'authors content gets sold', 'mycred' ),
					'end'      => ''
				);
			}

			if ( class_exists( 'myCRED_Buy_CREDs' ) ) {
				$instances['buy_creds'] = array(
					'label'    => __( 'buyCREDs Add-on', 'mycred' ),
					'positive' => __( 'user buys %_plural%', 'mycred' ),
					'end'      => ''
				);
			}

			if ( class_exists( 'myCRED_Transfer_Creds' ) ) {
				$instances['transfer'] = array(
					'label'    => __( 'Transfer Add-on', 'mycred' ),
					'negative' => __( 'user sends %_plural%', 'mycred' ),
					'positive' => __( 'user receives %_plural%', 'mycred' ),
					'end'      => ''
				);
			}

			if ( class_exists( 'myCRED_Ranks' ) ) {
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
		protected function get_instance( $key = '', $detail = NULL ) {
			$instance_keys = explode( '|', $key );
			if ( $instance_keys === false || empty( $instance_keys ) || count( $instance_keys ) != 2 ) return NULL;

			// By default we return the entire array for the given key
			if ( $detail === NULL && array_key_exists( $instance_keys[0], $this->instances ) )
				return $this->core->template_tags_general( $this->instances[$instance_keys[0]][$instance_keys[1]] );

			if ( $detail !== NULL && array_key_exists( $detail, $this->instances[$instance_keys[0]] ) )
				return $this->core->template_tags_general( $this->instances[$instance_keys[0]][$detail] );

			return NULL;
		}

		/**
		 * Add to General Settings
		 * @since 1.1
		 * @version 1.0
		 */
		public function after_general_settings() {
			if ( $this->emailnotices['use_html'] === true )
				$use_html = 1;
			else
				$use_html = 0; ?>

				<h4><div class="icon icon-active"></div><?php _e( 'Email Notices', 'mycred' ); ?></h4>
				<div class="body" style="display:none;">
					<p><?php _e( 'Settings that apply to all email notices and can not be overridden for individual emails.', 'mycred' ); ?></p>
					<label class="subheader" for="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>"><?php _e( 'Email Format', 'mycred' ); ?></label>
					<ol id="myCRED-email-notice-use-html">
						<li>
							<input type="radio" name="<?php echo $this->field_name( 'use_html' ); ?>" id="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>" <?php checked( $use_html, 0 ); ?> value="0" /> 
							<label for="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>"><?php _e( 'Plain text emails only.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="radio" name="<?php echo $this->field_name( 'use_html' ); ?>" id="<?php echo $this->field_id( array( 'use_html' => 'yes' ) ); ?>" <?php checked( $use_html, 1 ); ?> value="1" /> 
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
							<textarea rows="10" cols="50" name="<?php echo $this->field_name( 'content' ); ?>" id="<?php echo $this->field_id( 'content' ); ?>" class="large-text code"><?php echo $this->emailnotices['content']; ?></textarea>
							<span class="description"><?php _e( 'Default email content.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'styling' ); ?>"><?php _e( 'Default Email Styling', 'mycred' ); ?></label>
					<ol>
						<li>
							<textarea rows="10" cols="50" name="<?php echo $this->field_name( 'styling' ); ?>" id="<?php echo $this->field_id( 'styling' ); ?>" class="large-text code"><?php echo $this->emailnotices['styling']; ?></textarea>
							<span class="description"><?php _e( 'Ignored if HTML is not allowed in emails.', 'mycred' ); ?></span>
						</li>
					</ol>
				</div>
<?php
		}

		/**
		 * Save Settings
		 * @since 1.1
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			$new_data['emailnotices']['use_html'] = ( $data['emailnotices']['use_html'] == 1 ) ? true : false;

			$new_data['emailnotices']['filter']['subject'] = ( isset( $data['emailnotices']['filter']['subject'] ) ) ? true : false;
			$new_data['emailnotices']['filter']['content'] = ( isset( $data['emailnotices']['filter']['content'] ) ) ? true : false;

			$new_data['emailnotices']['from']['name'] = sanitize_text_field( $data['emailnotices']['from']['name'] );
			$new_data['emailnotices']['from']['email'] = sanitize_text_field( $data['emailnotices']['from']['email'] );
			$new_data['emailnotices']['from']['reply_to'] = sanitize_text_field( $data['emailnotices']['from']['reply_to'] );

			$new_data['emailnotices']['content'] = sanitize_text_field( $data['emailnotices']['content'] );
			$new_data['emailnotices']['styling'] = sanitize_text_field( $data['emailnotices']['styling'] );

			return $new_data;
		}

		/**
		 * Email Notice Check
		 * @since 1.1
		 * @version 1.1
		 */
		public function email_check( $reply, $request, $mycred ) {
			// Override - something has already determaned that this should not be executed
			if ( $reply === false || $reply === 'done' ) return $reply;
			
			// Construct events
			$event = array( 'all' );
			$amount = $request['amount'];

			// Event: Account gains or loses amount
			if ( $amount < 0 )
				$event[] = 'negative';
			else
				$event[] = 'positive';

			// Event: Account reaches zero or goes minus
			$balance = $mycred->get_users_cred( $request['user_id'] );
			if ( $amount < 0 && $balance-$amount < 0 )
				$event[] = 'minus';
			elseif ( $balance-$amount == 0 )
				$event[] = 'zero';

			// Do Ranks first
			if ( function_exists( 'mycred_get_users_rank' ) ) {
				// get users current rank ID
				$current_rank = mycred_get_users_rank( $request['user_id'] );
				$maybe_new_rank = mycred_find_users_rank( $request['user_id'], false, $request['amount'], $request['type'] );
				if ( $current_rank != $maybe_new_rank ) {
					$this->do_email_notices( 'ranks', $event, $request );
				}
			}

			// Before we send a notice, lets execute the request
			// so that emails show the correct details
			$mycred->update_users_balance( $request['user_id'], $request['amount'] );
			$mycred->add_to_log( $request['ref'], $request['user_id'], $request['amount'], $request['entry'], $request['ref_id'], $request['data'], $request['type'] );

			// Start with general events
			$this->do_email_notices( 'general', $event, $request );

			// At this stage, remove 'all'
			unset( $event[0] );

			// Add-on specific events
			$this->do_email_notices( $request['ref'], $event, $request );

			// Return the reply that we have already done this
			return 'done';
		}

		/**
		 * Do Email Notices
		 * @since 1.1
		 * @version 1.0
		 */
		protected function do_email_notices( $reference = NULL, $events = array(), $request = array() ) {
			if ( $reference === NULL || ( !is_array( $events ) || empty( $events ) ) || ( !is_array( $request ) || empty( $request ) ) ) return;

			$args = array(
				'post_type'      => 'mycred_email_notice',
				'posts_per_page' => 1,
				'post_status'    => 'publish'
			);

			foreach ( $events as $event ) {
				// Add meta query to main args
				$args['meta_query'] = array(
					array(
						'key'     => 'mycred_email_instance',
						'value'   => $reference . '|' . $event,
						'compare' => '='
					)
				);

				$test[] = $args;
				$query = new WP_Query( $args );
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						$settings = $this->get_email_settings( $query->post->ID );
						
						// Send to user
						if ( $settings['recipient'] == 'user' || $settings['recipient'] == 'both' ) {
							$user = get_user_by( 'id', $request['user_id'] );
							$to = $user->user_email;
							unset( $user );
						}
						// Send to admin
						elseif ( $settings['recipient'] == 'admin' ) {
							$to = get_option( 'admin_email' );
						}

						// Filtered Subject
						if ( $this->emailnotices['filter']['subject'] === true ) {
							$subject = get_the_title();
						}
						// Unfiltered Subject
						else {
							$subject = $query->post->post_title;
						}

						// Filtered Content
						if ( $this->emailnotices['filter']['content'] === true ) {
							$message = get_the_content();
						}
						// Unfiltered Content
						else {
							$message = $query->post->post_content;
						}

						$headers = array();
						$attachments = '';

						// Construct headers
						if ( $this->emailnotices['use_html'] === true ) {
							$headers[] = 'MIME-Version: 1.0';
							$headers[] = 'Content-Type: text/HTML; charset="' . get_option( 'blog_charset' ) . '"';
						}
						$headers[] = 'From: ' . $settings['senders_name'] . ' <' . $settings['senders_email'] . '>';

						// Reply-To
						if ( !empty( $settings['reply_to'] ) )
							$headers[] = 'Reply-To: ' . $settings['reply_to'];

						// Both means we blank carbon copy the admin so the user does not see email
						if ( $settings['recipient'] == 'both' )
							$headers[] = 'Bcc: ' . get_option( 'admin_email' );

						// If email was successfully sent we update 'last_run'
						if ( $this->wp_mail( $to, $subject, $message, $headers, $attachments, $request, $query->post->ID ) === true )
							update_post_meta( $query->post->ID, 'mycred_email_last_run', date_i18n( 'U' ) );
					}
				}
				wp_reset_postdata();
			}
		}

		/**
		 * WP Mail
		 * @since 1.1
		 * @version 1.1
		 */
		public function wp_mail( $to, $subject, $message, $headers, $attachments, $request, $email_id ) {
			// Let others play before we do our thing
			$filtered = apply_filters( 'mycred_email_before_send', compact( 'to', 'subject', 'message', 'headers', 'attachments', 'request', 'email_id' ) );

			// Unset everything so only filtered remains
			unset( $to );
			unset( $subject );
			unset( $message );
			unset( $headers );
			unset( $attachments );
			unset( $request );

			// Parse Subject Template Tags
			$subject = $this->core->template_tags_general( $filtered['subject'] );
			$subject = $this->core->template_tags_amount( $subject, $filtered['request']['amount'] );
			if ( $filtered['request']['user_id'] == get_current_user_id() )
				$subject = $this->core->template_tags_user( $subject, false, wp_get_current_user() );
			else
				$subject = $this->core->template_tags_user( $subject, $filtered['request']['user_id'] );

			// Parse Message Template Tags
			$message = $this->core->template_tags_general( $filtered['message'] );
			$message = $this->core->template_tags_amount( $message, $filtered['request']['amount'] );
			if ( $filtered['request']['user_id'] == get_current_user_id() )
				$message = $this->core->template_tags_user( $message, false, wp_get_current_user() );
			else
				$message = $this->core->template_tags_user( $message, $filtered['request']['user_id'] );
			$message = $this->template_tags_request( $message, $filtered['request'] );

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
		 * @version 1.0
		 */
		public function template_tags_request( $content, $request ) {
			$content = $this->core->template_tags_amount( $content, $request['amount'] );
			
			$content = str_replace( '%amount%', $request['amount'], $content );
			$content = str_replace( '%entry%',  $request['entry'], $content );
			$content = str_replace( '%data%',   print_r( $request['data'], true ), $content );
			
			return $content;
		}

		/**
		 * Get Email Settings
		 * @since 1.1
		 * @version 1.0
		 */
		protected function get_email_settings( $post_id ) {
			$settings = get_post_meta( $post_id, 'mycred_email_settings', true );
			// Defaults
			if ( empty( $settings ) )
				return array(
					'recipient'     => 'user',
					'senders_name'  => $this->emailnotices['from']['name'],
					'senders_email' => $this->emailnotices['from']['email'],
					'reply_to'      => $this->emailnotices['from']['reply_to']
				);

			return $settings;
		}

		/**
		 * Get Email Styling
		 * @since 1.1
		 * @version 1.0
		 */
		protected function get_email_styling( $post_id ) {
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
		 * @version 1.0
		 */
		public function adjust_column_headers( $defaults ) {
			// Remove
			unset( $defaults['date'] );

			// Add / Adjust
			$defaults['title'] = __( 'Email Subject', 'mycred' );
			$defaults['mycred-email-status'] = __( 'Status', 'mycred' );
			$defaults['mycred-email-reference'] = __( 'Setup', 'mycred' );

			// Return
			return $defaults;
		}

		/**
		 * Adjust Column Content
		 * @since 1.1
		 * @version 1.0
		 */
		public function adjust_column_content( $column_name, $post_id ) {
			// Get the post
			if ( $column_name == 'mycred-email-status' || $column_name == 'mycred-email-reference' )
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
				if ( !empty( $instance_key ) && !empty( $label ) )
					echo '<em>' . __( 'Email is sent when', 'mycred' ) .' ' . $label . '.</em></br />';
				else
					echo '<em>' . __( 'Missing instance for this notice!', 'mycred' ) . '</em><br />';

				$settings = get_post_meta( $post->ID, 'mycred_email_settings', true );
				if ( !empty( $settings ) && isset( $settings['recipient'] ) )
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
		 * @version 1.0
		 */
		public function email_settings( $post ) {
			// Get instance
			$instance = get_post_meta( $post->ID, 'mycred_email_instance', true );
			// Get settings
			$settings = $this->get_email_settings( $post->ID ); ?>

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
			} ?>

		</select><br />
		<label for="mycred-email-recipient-user"><?php _e( 'Recipient:', 'mycred' ); ?></label><br />
		<div class="mycred-inline">
			<input type="radio" name="mycred_email[recipient]" id="mycred-email-recipient-user" value="user" <?php checked( $settings['recipient'], 'user' ); ?> /> <label for="mycred-email-recipient-user"><?php _e( 'User', 'mycred' ); ?></label>
			<input type="radio" name="mycred_email[recipient]" id="mycred-email-recipient-admin" value="admin" <?php checked( $settings['recipient'], 'admin' ); ?> /> <label for="mycred-email-recipient-admin"><?php _e( 'Administrator', 'mycred' ); ?></label>
			<input type="radio" name="mycred_email[recipient]" id="mycred-email-recipient-both" value="both" <?php checked( $settings['recipient'], 'both' ); ?> /> <label for="mycred-email-recipient-both"><?php _e( 'Both', 'mycred' ); ?></label>
		</div>
	</div>
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
		<?php submit_button( __( 'Save', 'mycred' ), 'primary', 'mycred-save-email', false ); ?>
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
		 * @version 1.0
		 */
		public function template_tags( $post ) {
			echo '
<ul>
<li class="title">' . __( 'Site Related', 'mycred' ) . '</li>
<li><strong>%blog_name%</strong> ' . __( 'Your websites title', 'mycred' ) . '.</li>
<li><strong>%blog_url%</strong> ' . __( 'Your websites address', 'mycred' ) . '.</li>
<li><strong>%blog_info%</strong> ' . __( 'Your websites tagline (description)', 'mycred' ) . '.</li>
<li><strong>%admin_email%</strong> ' . __( 'Your websites admin email', 'mycred' ) . '.</li>
<li><strong>%num_members%</strong> ' . __( 'Total number of blog members', 'mycred' ) . '.</li>
<li class="empty">&nbsp;</li>
<li class="title">' . __( 'General', 'mycred' ) . '</li>
<li><strong>%singular%</strong> ' . __( 'Points name in singular format', 'mycred' ) . '.</li>
<li><strong>%plural%</strong> ' . __( 'Points name in plural', 'mycred' ) . '.</li>
<li><strong>%login_url%</strong> ' . __( 'Login URL', 'mycred' ) . '.</li>
</ul>
<ul>
<li class="title">' . __( 'User Related', 'mycred' ) . '</li>
<li><strong>%user_id%</strong> ' . __( 'The users ID', 'mycred' ) . '.</li>
<li><strong>%user_name%</strong> ' . __( 'The users login name (username)', 'mycred' ) . '.</li>
<li><strong>%display_name%</strong> ' . __( 'The users display name', 'mycred' ) . '.</li>
<li><strong>%user_profile_url%</strong> ' . __( 'The users profile address', 'mycred' ) . '.</li>
<li><strong>%user_profile_link%</strong> ' . __( 'Link to the users profile address with their display name as title', 'mycred' ) . '.</li>
<li><strong>%balance%</strong> ' . __( 'The users current balance unformated', 'mycred' ) . '.</li>
<li><strong>%balance_f%</strong> ' . __( 'The users current balance formated', 'mycred' ) . '.</li>
<li class="empty">&nbsp;</li>
<li class="title">' . __( 'Post Related', 'mycred' ) . '</li>
<li><strong>%post_title%</strong> ' . __( 'Post Title', 'mycred' ) . '.</li>
<li><strong>%post_url%</strong> ' . __( 'Post URL address', 'mycred' ) . '.</li>
<li><strong>%link_with_title%</strong> ' . __( 'Link to post Post title', 'mycred' ) . '.</li>
<li><strong>%post_type%</strong> ' . __( 'The post type', 'mycred' ) . '.</li>
</ul>
<div class="clear"></div>';
		}

		/**
		 * Save Email Notice Details
		 * @since 1.1
		 * @version 1.0
		 */
		public function save_email_notice( $post_id ) {
			// Make sure this is the correct post type
			if ( get_post_type( $post_id ) != 'mycred_email_notice' ) return;
			// Make sure we can edit
			elseif ( !mycred_is_admin( get_current_user_id() ) ) return;
			// Make sure fields exists
			elseif ( !isset( $_POST['mycred_email'] ) || !is_array( $_POST['mycred_email'] ) ) return;
			// Finally check token
			elseif ( !wp_verify_nonce( $_POST['mycred_email']['token'], 'mycred-edit-email' ) ) return;

			// Update Instance
			if ( !empty( $_POST['mycred_email']['instance'] ) ) {
				// Lets make sure the value is properly formatted otherwise things could go uggly later
				$instance_key = trim( $_POST['mycred_email']['instance'] );
				$keys = explode( '|', $instance_key );
				if ( $keys !== false && !empty( $keys ) );
					update_post_meta( $post_id, 'mycred_email_instance', $instance_key );
			}

			// Construct new settings
			$settings = array();
			// If recipient is set but differs from the default, use the posted one else use default
			if ( !empty( $_POST['mycred_email']['recipient'] ) )
				$settings['recipient'] = $_POST['mycred_email']['recipient'];
			else
				$settings['recipient'] = 'user';

			// If senders name is set but differs from the default, use the posted one else use default
			if ( !empty( $_POST['mycred_email']['senders_name'] ) )
				$settings['senders_name'] = $_POST['mycred_email']['senders_name'];
			else
				$settings['senders_name'] = $this->emailnotices['from']['name'];

			// If senders email is set but differs from the default, use the posted one else use default
			if ( !empty( $_POST['mycred_email']['senders_email'] ) )
				$settings['senders_email'] = $_POST['mycred_email']['senders_email'];
			else
				$settings['senders_email'] = $this->emailnotices['from']['email'];

			// If senders email is set but differs from the default, use the posted one else use default
			if ( !empty( $_POST['mycred_email']['reply_to'] ) )
				$settings['reply_to'] = $_POST['mycred_email']['reply_to'];
			else
				$settings['reply_to'] = $this->emailnotices['from']['reply_to'];

			// Save settings
			update_post_meta( $post_id, 'mycred_email_settings', $settings );

			// If rich editing is disabled bail now
			if ( $this->emailnotices['use_html'] === false ) return;

			// Save styling
			if ( !empty( $_POST['mycred_email']['styling'] ) )
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
				1 => sprintf( __( 'Email Notice Updated. View <a href="%1$s">All Notices</a>.', 'mycred' ), admin_url( 'edit.php?post_type=mycred_email_notice' ) ),
				2 => __( 'Custom field updated', 'mycred' ),
				3 => __( 'Custom filed updated', 'mycred' ),
				4 => sprintf( __( 'Email Notice Updated. View <a href="%1$s">All Notices</a>.', 'mycred' ), admin_url( 'edit.php?post_type=mycred_email_notice' ) ),
				5 => false,
				6 => __( 'Email Notice Activated', 'mycred' ),
				7 => __( 'Email Notice Saved', 'mycred' ),
				8 => sprintf( __( 'Email Notice Submitted for approval. View <a href="%1$s">All Notices</a>.', 'mycred' ), admin_url( 'edit.php?post_type=mycred_email_notice' ) ),
				9 => sprintf(
					__( 'Email Notice scheduled for: <strong>%1$s</strong>.', 'mycred' ),
					date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $post->post_date ) )
					),
				10 => ''
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
				echo '<p>' . __( 'Once a notice is "published" it becomes active! Select "Save Draft" if you are not yet ready to use this email notice!', 'mycred' ) . '</p>';
			elseif ( $post->post_status == 'future' )
				echo '<p>' . sprintf( __( 'This notice will become active on:<br /><strong>%1$s</strong>', 'mycred' ), date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $post->post_date ) ) ) . '</p>';
			else
				echo '<p>' . __( 'This email notice is active.', 'mycred' ) . '</p>';
		}
	}
	$email_notice = new myCRED_Email_Notices();
	$email_notice->load();
}
?>