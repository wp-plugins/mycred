<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
include_once( myCRED_MODULES_DIR . 'mycred-module-subscriptions.php' );
/**
 * myCRED_Hooks class
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hooks' ) ) {
	class myCRED_Hooks extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Hooks', array(
				'module_name' => 'hooks',
				'option_id'   => 'mycred_pref_hooks',
				'defaults'    => array(
					'installed'   => array(),
					'active'      => array(),
					'hook_prefs'  => array()
				),
				'labels'      => array(
					'menu'        => __( 'Hooks', 'mycred' ),
					'page_title'  => __( 'Hooks', 'mycred' ),
					'page_header' => __( 'Hooks', 'mycred' )
				),
				'screen_id'   => 'myCRED_page_hooks',
				'accordion'   => true,
				'menu_pos'    => 20
			) );
		}

		/**
		 * Load Hooks
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_pre_init() {
			if ( !empty( $this->installed ) ) {
				foreach ( $this->installed as $key => $gdata ) {
					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'run', $gdata['callback'] );
					}
				}
			}
		}

		/**
		 * Call
		 * Either runs a given class method or function.
		 * @since 0.1
		 * @version 1.0
		 */
		public function call( $call, $callback, $return = NULL ) {
			// Class
			if ( is_array( $callback ) && class_exists( $callback[0] ) ) {
				$class = $callback[0];
				$methods = get_class_methods( $class );
				if ( in_array( $call, $methods ) ) {
					$new = new $class( ( isset( $this->hook_prefs ) ) ? $this->hook_prefs : $this );
					return $new->$call( $return );
				}
			}
			// Function
			if ( !is_array( $callback ) ) {
				if ( function_exists( $callback ) ) {
					if ( $return !== NULL )
						return call_user_func( $callback, $return, $this );
					else
						return call_user_func( $callback, $this );
				}
			}
		}

		/**
		 * Get Hooks
		 * @since 0.1
		 * @version 1.0.5
		 */
		public function get( $save = false ) {
			// Defaults
			$installed['registration'] = array(
				'title'        => __( '%plural% for registrations', 'mycred' ),
				'description'  => __( 'Award %_plural% for users joining your website.', 'mycred' ),
				'callback'     => array( 'myCRED_Hook_Registration' )
			);
			$installed['logging_in'] = array(
				'title'       => __( '%plural% for logins', 'mycred' ),
				'description' => __( 'Award %_plural% for logging in to your website. You can also set an optional limit.', 'mycred' ),
				'callback'    => array( 'myCRED_Hook_Logging_In' )
			);
			$installed['publishing_content'] = array(
				'title'       => __( '%plural% for publishing content', 'mycred' ),
				'description' => __( 'Award %_plural% for publishing content on your website. If your custom post type is not shown bellow, make sure it is set to "Public".', 'mycred' ),
				'callback'    => array( 'myCRED_Hook_Publishing_Content' )
			);
			$installed['comments'] = array(
				'title'       => __( '%plural% for comments', 'mycred' ),
				'description' => __( 'Award %_plural% for making comments.', 'mycred' ),
				'callback'    => array( 'myCRED_Hook_Comments' )
			);

			// Prep for Invite Anyone Plugin
			if ( function_exists( 'invite_anyone_init' ) ) {
				$installed['invite_anyone'] = array(
					'title'       => __( 'Invite Anyone Plugin', 'mycred' ),
					'description' => __( 'Awards %_plural% for sending invitations and/or %_plural% if the invite is accepted.', 'mycred' ),
					'callback'    => array( 'myCRED_Invite_Anyone' )
				);
			}

			// Prep for Contact Form 7
			if ( function_exists( 'wpcf7' ) ) {
				$installed['contact_form7'] = array(
					'title'       => __( 'Contact Form 7 Form Submissions', 'mycred' ),
					'description' => __( 'Awards %_plural% for successful form submissions (by logged in users).', 'mycred' ),
					'callback'    => array( 'myCRED_Contact_Form7' )
				);
			}

			// Prep for Jetpack
			if ( class_exists( 'Jetpack' ) ) {
				$installed['jetpack'] = array(
					'title'       => __( 'Jetpack Subscriptions', 'mycred' ),
					'description' => __( 'Awards %_plural% for users signing up for site or comment updates using Jetpack.', 'mycred' ),
					'callback'    => array( 'myCRED_Hook_Jetpack' )
				);
			}
			
			// Prep for BadgeOS
			if ( class_exists( 'BadgeOS' ) ) {
				$installed['badgeos'] = array(
					'title'       => __( 'BadgeOS', 'mycred' ),
					'description' => __( 'Default settings for each BadgeOS Achievement type. These settings may be overridden for individual achievement type.', 'mycred' ),
					'callback'    => array( 'myCRED_Hook_BadgeOS' )
				);
			}

			$installed = apply_filters( 'mycred_setup_hooks', $installed );

			if ( $save === true && $this->core->can_edit_plugin() ) {
				$new_data = array(
					'active'     => $this->active,
					'installed'  => $installed,
					'hook_prefs' => $this->hook_prefs
				);
				update_option( 'mycred_pref_hooks', $new_data );
			}

			$this->installed = $installed;
			return $installed;
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_page() {
			// Security
			if ( !$this->core->can_edit_plugin( get_current_user_id() ) ) wp_die( __( 'Access Denied' ) );

			// Get installed
			$installed = $this->get( true );
	
			// Message
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
				echo '<div class="updated settings-error"><p>' . __( 'Settings Updated', 'mycred' ) . '</p></div>';
			} ?>

	<div class="wrap" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php echo '<strong>my</strong>CRED ' . __( 'Hooks', 'mycred' ); ?></h2>
		<p><?php echo $this->core->template_tags_general( __( 'Hooks are instances where %_plural% are awarded or deducted from a user, depending on their actions around your website.', 'mycred' ) ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'myCRED-hooks' ); ?>

			<!-- Loop though Hooks -->
			<div class="list-items expandable-li" id="accordion">
<?php		if ( !empty( $installed ) ) {
				foreach ( $installed as $key => $data ) { ?>

				<h4 class="<?php if ( $this->is_active( $key ) ) echo 'active'; else echo 'inactive'; ?>"><label><?php echo $this->core->template_tags_general( $data['title'] ); ?></label></h4>
				<div class="body" style="display:none;">
					<p><?php echo nl2br( $this->core->template_tags_general( $data['description'] ) ); ?></p>
					<label class="subheader"><?php _e( 'Enable', 'mycred' ); ?></label>
					<ol>
						<li>
							<input type="checkbox" name="mycred_pref_hooks[active][]" id="mycred-hook-<?php echo $key; ?>" value="<?php echo $key; ?>"<?php if ( $this->is_active( $key ) ) echo ' checked="checked"'; ?> />
						</li>
					</ol>
					<?php echo $this->call( 'preferences', $data['callback'] ); ?>

				</div>
<?php			}
			} ?>

			</div>
			<?php submit_button( __( 'Update Changes', 'mycred' ), 'primary large' ); ?>

		</form>
	</div>
<?php
			unset( $installed );
			unset( $this );
		}

		/**
		 * Sanititze Settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function sanitize_settings( $data ) {
			$installed = $this->get();
			if ( !empty( $installed ) ) {
				foreach ( $installed as $key => $gdata ) {
					if ( isset( $gdata['callback'] ) && isset( $data['hook_prefs'][$key] ) ) {
						$data['hook_prefs'][$key] = $this->call( 'sanitise_preferences', $gdata['callback'], $data['hook_prefs'][$key] );
					}
				}
			}
			unset( $installed );
			return $data;
		}
	}
}

/**
 * Hook for registrations
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_Registration' ) ) {
	class myCRED_Hook_Registration extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'registration',
				'defaults' => array(
					'creds'   => 10,
					'log'     => '%plural% for becoming a member'
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {
			if ( $this->prefs['creds'] != 0 )
				add_action( 'user_register', array( $this, 'registration' ) );
		}

		/**
		 * Registration Hook
		 * @since 0.1
		 * @version 1.0
		 */
		public function registration( $user_id ) {
			// Make sure user is not excluded
			if ( $this->core->exclude_user( $user_id ) === true ) return;

			// Execute
			$this->core->add_creds(
				'registration',
				$user_id,
				$this->prefs['creds'],
				$this->prefs['log'],
				$user_id,
				array( 'ref_type' => 'user' )
			);

			// Clean up
			unset( $this );
		}

		/**
		 * Preference for Registration Hook
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader"><?php echo $this->core->plural(); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'creds' ); ?>" id="<?php echo $this->field_id( 'creds' ); ?>" value="<?php echo $this->core->format_number( $prefs['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Log template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, User', 'mycred' ); ?></span>
						</li>
					</ol>
<?php
			unset( $this );
		}
	}
}

/**
 * Hook for loggins
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_Logging_In' ) ) {
	class myCRED_Hook_Logging_In extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'logging_in',
				'defaults' => array(
					'creds'   => 1,
					'log'     => '%plural% for logging in',
					'limit'   => 'daily'
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.1
		 */
		public function run() {
			if ( $this->prefs['creds'] != 0 )
				add_action( 'wp_login', array( $this, 'logging_in' ), 10, 2 );
		}

		/**
		 * Login Hook
		 * @since 0.1
		 * @version 1.1
		 */
		public function logging_in( $user_login, $user ) {
			if ( $this->core->exclude_user( $user->ID ) === true ) return;

			if ( !$this->reward_login( $user->ID ) ) return;

			// Execute
			$this->core->add_creds(
				'logging_in',
				$user->ID,
				$this->prefs['creds'],
				$this->prefs['log']
			);

			// Clean up
			unset( $this );
		}

		/**
		 * Reward Login Check
		 * Checks to see if the given user id should be rewarded for logging in.
		 * @returns true or false
		 * @since 1.0.6
		 * @version 1.1
		 */
		protected function reward_login( $user_id ) {
			$now = date_i18n( 'U' );
			$today = date_i18n( 'Y-m-d' );
			$past = get_user_meta( $user_id, 'mycred_last_login', true );

			// Even if there is no limit set we will always impose a 1 min limit
			// to prevent users from just logging in and out for points.
			if ( $past >= $now-apply_filters( 'mycred_hook_login_min_limit', 60 ) ) return false;

			// If limit is set
			if ( !empty( $this->prefs['limit'] ) ) {
				// If logged in before
				if ( !empty( $past ) ) {
					if ( $this->prefs['limit'] == 'twentyfour' ) {
						$mark = 86400;
						$next = $past+$mark;
						// Check if next time we can get points is in future; if thats the case, bail
						if ( $next > $now ) return false;
					}
					elseif ( $this->prefs['limit'] == 'twelve' ) {
						$mark = 43200;
						$next = $past+$mark;
						// Check if next time we can get points is in future; if thats the case, bail
						if ( $next > $now ) return false;
					}
					elseif ( $this->prefs['limit'] == 'sevendays' ) {
						$mark = 604800;
						$next = $past+$mark;
						// Check if next time we can get points is in future; if thats the case, bail
						if ( $next > $now ) return false;
					}
					elseif ( $this->prefs['limit'] == 'daily' ) {
						if ( $today == $past ) return false;
					}
				}
			}
			
			// Update new login time
			if ( $this->prefs['limit'] == 'daily' )
				update_user_meta( $user_id, 'mycred_last_login', $today );
			else
				update_user_meta( $user_id, 'mycred_last_login', $now );

			return true;
		}

		/**
		 * Preference for Login Hook
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader"><?php echo $this->core->plural(); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'creds' ); ?>" id="<?php echo $this->field_id( 'creds' ); ?>" value="<?php echo $this->core->format_number( $prefs['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Limit', 'mycred' ); ?></label>
					<ol>
						<li>
							<?php $this->impose_limits_dropdown( 'limit' ); ?>

						</li>
					</ol>
<?php
			unset( $this );
		}
	}
}

/**
 * Hook for publishing content
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_Publishing_Content' ) ) {
	class myCRED_Hook_Publishing_Content extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'publishing_content',
				'defaults' => array(
					'post'    => array(
						'creds'  => 1,
						'log'    => '%plural% for new Post'
					),
					'page'    => array(
						'creds'  => 1,
						'log'    => '%plural% for new Page'
					)
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {
			add_action( 'transition_post_status', array( $this, 'publishing_content' ), 10, 3 );
		}

		/**
		 * Publish Content Hook
		 * @since 0.1
		 * @version 1.0
		 */
		public function publishing_content( $new_status, $old_status, $post ) {
			$user_id = $post->post_author;
			if ( $this->core->exclude_user( $user_id ) === true ) return;

			$post_id = $post->ID;
			$post_type = $post->post_type;
			if ( !isset( $this->prefs[$post_type]['creds'] ) ) return;
			if ( empty( $this->prefs[$post_type]['creds'] ) || $this->prefs[$post_type]['creds'] == 0 ) return;

			// We want to fire when content get published or when it gets privatly published
			if ( 
				( $old_status == 'auto-draft' && $new_status == 'publish' && array_key_exists( $post_type, $this->prefs ) ) ||
				( $old_status == 'draft' && $new_status == 'publish' && array_key_exists( $post_type, $this->prefs ) ) ||
				( $old_status == 'private' && $new_status == 'publish' && array_key_exists( $post_type, $this->prefs ) ) ) {

				// Make sure this is unique
				if ( $this->has_entry( 'publishing_content', $post_id, $user_id ) ) return;

				// Prep
				$entry = $this->prefs[$post_type]['log'];
				$data = array( 'ref_type' => 'post' ) ;

				// Add Creds
				$this->core->add_creds(
					'publishing_content',
					$user_id,
					$this->prefs[$post_type]['creds'],
					$entry,
					$post_id,
					$data
				);
			}
			unset( $this );
		}

		/**
		 * Preference for Publish Content Hook
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Posts', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'post' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'post' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['post']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Log template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'post' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'post' => 'log' ) ); ?>" value="<?php echo $prefs['post']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Pages', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'page' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'page' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['page']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Log template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'page' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'page' => 'log' ) ); ?>" value="<?php echo $prefs['page']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		// Get all not built-in post types (excludes posts, pages, media)
			$post_type_args = array(
				'public'   => true,
				'_builtin' => false
			);
			$post_types = get_post_types( $post_type_args, 'objects', 'and' ); 
			foreach ( $post_types as $post_type ) {
				// Start by checking if this post type should be excluded
				if ( !$this->include_post_type( $post_type->name ) ) continue;
				
				// Points to award/deduct
				if ( isset( $prefs[$post_type->name]['creds'] ) )
					$_creds = $prefs[$post_type->name]['creds'];
				else
					$_creds = 0;

				// Log template
				if ( isset( $prefs[$post_type->name]['log'] ) )
					$_log = $prefs[$post_type->name]['log'];
				else
					$_log = ''; ?>

					<label class="subheader"><?php echo sprintf( $this->core->template_tags_general( __( '%plural% for %s', 'mycred' ) ),  $post_type->labels->name ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $post_type->name => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( $post_type->name => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $_creds ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Log template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $post_type->name => 'log' ) ); ?>" id="<?php echo $this->field_id( array( $post_type->name => 'log' ) ); ?>" value="<?php echo $_log; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		}
			unset( $this );
		}

		/**
		 * Include Post Type
		 * Checks if a given post type should be excluded
		 * @since 0.1
		 * @version 1.0
		 */
		protected function include_post_type( $post_type ) {
			if ( in_array( $post_type, apply_filters( 'mycred_post_type_excludes', array( 'post', 'page' ) ) ) ) return false;
			
			return true;
		}
	}
}

/**
 * Hook for comments
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_Comments' ) ) {
	class myCRED_Hook_Comments extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'comments',
				'defaults' => array(
					'approved' => array(
						'creds'   => 1,
						'log'     => '%plural% for Approved Comment'
					),
					'spam'     => array(
						'creds'   => '-5',
						'log'     => '%plural% deduction for Comment marked as SPAM'
					),
					'trash'    => array(
						'creds'   => '-1',
						'log'     => '%plural% deduction for deleted / unapproved Comment'
					)
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {
			add_action( 'comment_post',                       array( $this, 'new_comment' ), 10, 2 );

			if ( $this->prefs['approved'] != 0 ) {
				add_action( 'comment_unapproved_to_approved', array( $this, 'approved_comments' ) );
				add_action( 'comment_trash_to_approved',      array( $this, 'approved_comments' ) );
				add_action( 'comment_spam_to_approved',       array( $this, 'approved_comments' ) );
			}

			if ( $this->prefs['spam'] != 0 ) {
				add_action( 'comment_approved_to_spam',       array( $this, 'spam_comments' ) );
				add_action( 'comment_unapproved_to_spam',     array( $this, 'spam_comments' ) );
			}
			
			if ( $this->prefs['trash'] != 0 ) {
				add_action( 'comment_approved_to_unapproved', array( $this, 'trash_comments' ) );
				add_action( 'comment_approved_to_trash',      array( $this, 'trash_comments' ) );
				add_action( 'comment_unapproved_to_trash',    array( $this, 'trash_comments' ) );
			}
		}

		/**
		 * New Comment
		 * If comments are approved without moderation, we apply the corresponding method
		 * or else we will wait till the appropriate instance.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		public function new_comment( $comment_id, $comment_status ) {
			// Marked SPAM
			if ( $comment_status === 'spam' && $this->prefs['spam'] != 0 )
				$this->spam_comments( $comment_id );
			// Approved comment
			elseif ( $comment_status == '1' && $this->prefs['approved'] != 0 )
				$this->approved_comments( $comment_id );

			// All else comments are moderated and we will award / deduct points in a different instance
			return;
		}

		/**
		 * Approved Comments
		 * Validate and execute our settings for approved comments.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		public function approved_comments( $comment ) {
			// Passing an integer instead of an object means we need to grab the comment object ourselves
			if ( !is_object( $comment ) )
				$comment = get_comment( $comment );

			// Logged out users miss out
			if ( $comment->user_id == 0 ) return;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $comment->user_id ) === true ) return;

			// Make sure this is unique event
			if ( $this->has_entry( 'approved_comment', $comment->comment_ID, $comment->user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'approved_comment',
				$comment->user_id,
				$this->prefs['approved']['creds'],
				$this->prefs['approved']['log'],
				$comment->comment_ID,
				array( 'ref_type' => 'comment' )
			);

			// Clean up
			unset( $this );
		}

		/**
		 * SPAM Comments
		 * Validate and execute our settings for comments marked as SPAM.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		public function spam_comments( $comment ) {
			// Passing an integer instead of an object means we need to grab the comment object ourselves
			if ( !is_object( $comment ) )
				$comment = get_comment( $comment );

			// Logged out users miss out
			if ( $comment->user_id == 0 ) return;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $comment->user_id ) === true ) return;

			// Make sure this is unique event
			if ( $this->has_entry( 'spam_comment', $comment->comment_ID, $comment->user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'spam_comment',
				$comment->user_id,
				$this->prefs['spam']['creds'],
				$this->prefs['spam']['log'],
				$comment->comment_ID,
				array( 'ref_type' => 'comment' )
			);

			// Clean up
			unset( $this );
		}

		/**
		 * Trashed Comments
		 * Validate and execute our settings for trashed or unapproved comments.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		public function trash_comments( $comment ) {
			// Passing an integer instead of an object means we need to grab the comment object ourselves
			if ( !is_object( $comment ) )
				$comment = get_comment( $comment );

			// Logged out users miss out
			if ( $comment->user_id == 0 ) return;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $comment->user_id ) === true ) return;

			// Make sure this is unique event
			if ( $this->has_entry( 'deleted_comment', $comment->comment_ID, $comment->user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'deleted_comment',
				$comment->user_id,
				$this->prefs['trash']['creds'],
				$this->prefs['trash']['log'],
				$comment->comment_ID,
				array( 'ref_type' => 'comment' )
			);

			// Clean up
			unset( $this );
		}

		/**
		 * Preferences for Commenting Hook
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader" for="<?php echo $this->field_id( array( 'approved' => 'creds' ) ); ?>"><?php _e( 'Approved Comment', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'approved' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'approved' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['approved']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'approved' => 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'approved' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'approved' => 'log' ) ); ?>" value="<?php echo $prefs['approved']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Comment', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'spam' => 'creds' ) ); ?>"><?php _e( 'Comment Marked SPAM', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'spam' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'spam' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['spam']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'spam' => 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'spam' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'spam' => 'log' ) ); ?>" value="<?php echo $prefs['spam']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Comment', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'trash' => 'creds' ) ); ?>"><?php _e( 'Trashed / Unapproved Comments', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'trash' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'trash' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['trash']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'trash' => 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'trash' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'trash' => 'log' ) ); ?>" value="<?php echo $prefs['trash']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Comment', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}
/**
 * Hooks for Invite Anyone Plugin
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Invite_Anyone' ) && function_exists( 'invite_anyone_init' ) ) {
	class myCRED_Invite_Anyone extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'invite_anyone',
				'defaults' => array(
					'send_invite'   => array(
						'creds'        => 1,
						'log'          => '%plural% for sending an invitation',
						'limit'        => 0
					),
					'accept_invite' => array(
						'creds'        => 1,
						'log'          => '%plural% for accepted invitation',
						'limit'        => 0
					)
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {
			if ( $this->prefs['send_invite']['creds'] != 0 ) {
				add_action( 'sent_email_invite',     array( $this, 'send_invite' ), 10, 3 );
			}
			if ( $this->prefs['accept_invite']['creds'] != 0 ) {
				add_action( 'accepted_email_invite', array( $this, 'accept_invite' ), 10, 2 );
			}
		}

		/**
		 * Sending Invites
		 * @since 0.1
		 * @version 1.0
		 */
		public function send_invite( $user_id, $email, $group ) {
			// Limit Check
			if ( $this->prefs['send_invite']['limit'] != 0 ) {
				$user_log = get_user_meta( $user_id, 'mycred_invite_anyone', true );
				if ( empty( $user_log['sent'] ) ) $user_log['sent'] = 0;
				// Return if limit is reached
				if ( $user_log['sent'] >= $this->prefs['send_invite']['limit'] ) return;
			}

			// Award Points
			$this->core->add_creds(
				'sending_an_invite',
				$user_id,
				$this->prefs['send_invite']['creds'],
				$this->prefs['send_invite']['log']
			);

			// Update limit
			if ( $this->prefs['send_invite']['limit'] != 0 ) {
				$user_log['sent'] = $user_log['sent']+1;
				update_user_meta( $user_id, 'mycred_invite_anyone', $user_log );
			}

			// Clean up
			unset( $this );
		}

		/**
		 * Accepting Invites
		 * @since 0.1
		 * @version 1.0
		 */
		public function accept_invite( $invited_user_id, $inviters ) {
			// Invite Anyone will pass on an array of user IDs of those who have invited this user which we need to loop though
			foreach ( $inviters as $inviter_id ) {
				// Limit Check
				if ( $this->prefs['accept_invite']['limit'] != 0 ) {
					$user_log = get_user_meta( $inviter_id, 'mycred_invite_anyone', true );
					if ( empty( $user_log['accepted'] ) ) $user_log['accepted'] = 0;
					// Continue to next inviter if limit is reached
					if ( $user_log['accepted'] >= $this->prefs['accept_invite']['limit'] ) continue;
				}

				// Award Points
				$this->core->add_creds(
					'accepting_an_invite',
					$inviter_id,
					$this->prefs['accept_invite']['creds'],
					$this->prefs['accept_invite']['log']
				);

				// Update Limit
				if ( $this->prefs['accept_invite']['limit'] != 0 ) {
					$user_log['accepted'] = $user_log['accepted']+1;
					update_user_meta( $inviter_id, 'mycred_invite_anyone', $user_log );
				}
			}
			
			// Clean up
			unset( $this );
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<!-- Creds for Sending Invites -->
					<label for="<?php echo $this->field_id( array( 'send_invite', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Sending An Invite', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_invite', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'send_invite', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['send_invite']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'send_invite', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_invite', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'send_invite', 'log' ) ); ?>" value="<?php echo $prefs['send_invite']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<label for="<?php echo $this->field_id( array( 'send_invite', 'limit' ) ); ?>" class="subheader"><?php _e( 'Limit', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_invite', 'limit' ) ); ?>" id="<?php echo $this->field_id( array( 'send_invite', 'limit' ) ); ?>" value="<?php echo $prefs['send_invite']['limit']; ?>" size="8" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( 'Maximum number of invites that grants %_plural%. User zero for unlimited.', 'mycred' ) ); ?></span>
						</li>
					</ol>
					<!-- Creds for Accepting Invites -->
					<label for="<?php echo $this->field_id( array( 'accept_invite', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Accepting An Invite', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'accept_invite', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'accept_invite', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['accept_invite']['creds'] ); ?>" size="8" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( '%plural% for each invited user that accepts an invitation.', 'mycred' ) ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'accept_invite', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'accept_invite', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'accept_invite', 'log' ) ); ?>" value="<?php echo $prefs['accept_invite']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<label for="<?php echo $this->field_id( array( 'accept_invite', 'limit' ) ); ?>" class="subheader"><?php _e( 'Limit', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'accept_invite', 'limit' ) ); ?>" id="<?php echo $this->field_id( array( 'accept_invite', 'limit' ) ); ?>" value="<?php echo $prefs['accept_invite']['limit']; ?>" size="8" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( 'Maximum number of accepted invitations that grants %_plural%. User zero for unlimited.', 'mycred' ) ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}
/**
 * Hook for Contact Form 7 Plugin
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Contact_Form7' ) && function_exists( 'wpcf7' ) ) {
	class myCRED_Contact_Form7 extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'contact_form7',
				'defaults' => ''
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {
			add_action( 'wpcf7_mail_sent', array( $this, 'form_submission' ) );
		}

		/**
		 * Get Forms
		 * Queries all Contact Form 7 forms.
		 * @uses WP_Query()
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_forms() {
			$forms = new WP_Query( array(
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => '-1',
				'orderby'        => 'ID',
				'order'          => 'ASC'
			) );

			$result = array();
			if ( $forms->have_posts() ) {
				while ( $forms->have_posts() ) : $forms->the_post();
					$result[get_the_ID()] = get_the_title();
				endwhile;
			}
			wp_reset_postdata();

			return $result;
		}
		
		/**
		 * Successful Form Submission
		 * @since 0.1
		 * @version 1.0
		 */
		public function form_submission( $cf7_form ) {
			// Login is required
			if ( !is_user_logged_in() ) return;

			$form_id = $cf7_form->id;
			if ( isset( $this->prefs[$form_id] ) && $this->prefs[$form_id]['creds'] != 0 ) {
				$this->core->add_creds(
					'contact_form_submission',
					get_current_user_id(),
					$this->prefs[$form_id]['creds'],
					$this->prefs[$form_id]['log'],
					$form_id,
					array( 'ref_type' => 'post' )
				);
			}

			// Clean up
			unset( $this );
		}

		/**
		 * Preferences for Commenting Hook
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs;
			$forms = $this->get_forms();

			// No forms found
			if ( empty( $forms ) ) {
				echo '<p>' . __( 'No forms found.', 'mycred' ) . '</p>';
				return;
			}

			// Loop though prefs to make sure we always have a default settings (happens when a new form has been created)
			foreach ( $forms as $form_id => $form_title ) {
				if ( !isset( $prefs[$form_id] ) ) {
					$prefs[$form_id] = array(
						'creds' => 1,
						'log'   => ''
					);
				}
			}

			// Set pref if empty
			if ( empty( $prefs ) ) $this->prefs = $prefs;

			// Loop for settings
			foreach ( $forms as $form_id => $form_title ) { ?>

					<!-- Creds for  -->
					<label for="<?php echo $this->field_id( array( $form_id, 'creds' ) ); ?>" class="subheader"><?php echo $form_title; ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $form_id, 'creds' ) ); ?>" id="<?php echo $this->field_id( array( $form_id, 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs[$form_id]['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( $form_id, 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $form_id, 'log' ) ); ?>" id="<?php echo $this->field_id( array( $form_id, 'log' ) ); ?>" value="<?php echo $prefs[$form_id]['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		}
			unset( $this );
		}
	}
}
/**
 * Hook for BadgeOS Plugin
 * @since 1.0.8
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_BadgeOS' ) && class_exists( 'BadgeOS' ) ) {
	class myCRED_Hook_BadgeOS extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'badgeos',
				'defaults' => ''
			), $hook_prefs );
		}
		
		/**
		 * Run
		 * @since 1.0.8
		 * @version 1.0
		 */
		public function run() {
			add_action( 'add_meta_boxes',             array( $this, 'add_metaboxes' )             );
			add_action( 'save_post',                  array( $this, 'save_achivement_data' )      );
			
			add_action( 'badgeos_award_achievement',  array( $this, 'award_achievent' ), 10, 2    );
			add_action( 'badgeos_revoke_achievement', array( $this, 'revoke_achievement' ), 10, 2 );
		}
		
		/**
		 * Add Metaboxes
		 * @since 1.0.8
		 * @version 1.0
		 */
		public function add_metaboxes() {
			// Get all Achievement Types
			$badge_post_types = badgeos_get_achievement_types_slugs();
			foreach ( $badge_post_types as $post_type ) {
				// Add Meta Box
				add_meta_box(
					'mycred_badgeos_' . $post_type,
					__( 'myCRED', 'mycred' ),
					array( $this, 'render_meta_box' ),
					$post_type,
					'side',
					'core'
				);
			}
		}
		
		/**
		 * Render Meta Box
		 * @since 1.0.8
		 * @version 1.0
		 */
		public function render_meta_box( $post ) {
			// Setup is needed
			if ( !isset( $this->prefs[$post->post_type] ) ) {
				$message = sprintf( __( 'Please setup your <a href="%s">default settings</a> before using this feature.', 'mycred' ), admin_url( 'admin.php?page=myCRED_page_hooks' ) );
				echo '<p>' . $message . '</p>';
			}
			
			// Prep Achievement Data
			$prefs = $this->prefs;
			$achievement_data = get_post_meta( $post->ID, '_mycred_values', true );
			if ( empty( $achievement_data ) )
				$achievement_data = $prefs[$post->post_type]; ?>

			<p><strong><?php _e( 'Tokens to Award', 'mycred' ); ?></strong></p>
			<p>
				<label class="screen-reader-text" for="mycred-values-creds"><?php _e( 'Tokens to Award', 'mycred' ); ?></label>
				<input type="text" name="mycred_values[creds]" id="mycred-values-creds" value="<?php echo $achievement_data['creds']; ?>" size="8" />
				<span class="description"><?php _e( 'Use zero to disable', 'mycred' ); ?></span>
			</p>
			<p><strong><?php _e( 'Log Template', 'mycred' ); ?></strong></p>
			<p>
				<label class="screen-reader-text" for="mycred-values-log"><?php _e( 'Log Template', 'mycred' ); ?></label>
				<input type="text" name="mycred_values[log]" id="mycred-values-log" value="<?php echo $achievement_data['log']; ?>" style="width:99%;" />
			</p>
<?php
			// If deduction is enabled
			if ( $this->prefs[$post->post_type]['deduct'] == 1 ) { ?>

			<p><strong><?php _e( 'Deduction Log Template', 'mycred' ); ?></strong></p>
			<p>
				<label class="screen-reader-text" for="mycred-values-log"><?php _e( 'Log Template', 'mycred' ); ?></label>
				<input type="text" name="mycred_values[deduct_log]" id="mycred-values-deduct-log" value="<?php echo $achievement_data['deduct_log']; ?>" style="width:99%;" />
			</p>
<?php
			}
		}
		
		/**
		 * Save Achievement Data
		 * @since 1.0.8
		 * @version 1.0
		 */
		public function save_achivement_data( $post_id ) {
			// Post Type
			$post_type = get_post_type( $post_id );

			// Make sure this is a BadgeOS Object
			if ( !in_array( $post_type, badgeos_get_achievement_types_slugs() ) ) return;

			// Make sure preference is set
			if ( !isset( $this->prefs[$post_type] ) || !isset( $_POST['mycred_values']['creds'] ) || !isset( $_POST['mycred_values']['log'] ) ) return;

			// Only save if the settings differ, otherwise we default
			if ( $_POST['mycred_values']['creds'] == $this->prefs[$post_type]['creds'] &&
				 $_POST['mycred_values']['log'] == $this->prefs[$post_type]['log'] ) return;

			$data = array();

			// Creds
			if ( !empty( $_POST['mycred_values']['creds'] ) && $_POST['mycred_values']['creds'] != $this->prefs[$post_type]['creds'] )
				$data['creds'] = $this->core->format_number( $_POST['mycred_values']['creds'] );
			else
				$data['creds'] = $this->core->format_number( $this->prefs[$post_type]['creds'] );

			// Log template
			if ( !empty( $_POST['mycred_values']['log'] ) && $_POST['mycred_values']['log'] != $this->prefs[$post_type]['log'] )
				$data['log'] = strip_tags( $_POST['mycred_values']['log'] );
			else
				$data['log'] = strip_tags( $this->prefs[$post_type]['log'] );

			// If deduction is enabled save log template
			if ( $this->prefs[$post->post_type]['deduct'] == 1 ) {
				if ( !empty( $_POST['mycred_values']['deduct_log'] ) && $_POST['mycred_values']['deduct_log'] != $this->prefs[$post_type]['deduct_log'] )
					$data['deduct_log'] = strip_tags( $_POST['mycred_values']['deduct_log'] );
				else
					$data['deduct_log'] = strip_tags( $this->prefs[$post_type]['deduct_log'] );
			}

			// Update sales values
			update_post_meta( $post_id, '_mycred_values', $data );
		}
		
		/**
		 * Award Achievement
		 * Run by BadgeOS when ever needed, we make sure settings are not zero otherwise
		 * award points whenever this hook fires.
		 * @since 1.0.8
		 * @version 1.0
		 */
		public function award_achievent( $user_id, $achievement_id ) {
			$post_type = get_post_type( $achievement_id );
			// Settings are not set
			if ( !isset( $this->prefs[$post_type]['creds'] ) ) return;

			// All the reasons to bail
			$achievement_data = get_post_meta( $achievement_id, '_mycred_values', true );
			if ( empty( $achievement_data ) && $this->prefs[$post_type]['creds'] == 0 ) return;
			elseif ( !empty( $achievement_data ) && $achievement_data['creds'] == 0 ) return;

			// Creds
			if ( $this->prefs[$post_type]['creds'] == $achievement_data['creds'] )
				$creds = $this->prefs[$post_type]['creds'];
			else
				$creds = $achievement_data['creds'];

			// Log Template
			if ( $this->prefs[$post_type]['log'] == $achievement_data['log'] )
				$entry = $this->prefs[$post_type]['log'];
			else
				$entry = $achievement_data['log'];

			// Execute
			$post_type_object = get_post_type_object( $post_type );
			$this->core->add_creds(
				$post_type_object->labels->name,
				$user_id,
				$creds,
				$entry,
				$post_type,
				array( 'ref_type' => 'post' )
			);
		}
		
		/**
		 * Revoke Achievement
		 * Run by BadgeOS when a users achievement is revoed.
		 * @since 1.0.8
		 * @version 1.0
		 */
		public function revoke_achievement( $user_id, $achievement_id ) {
			$post_type = get_post_type( $achievement_id );
			// Make sure settings are set or that deduction is enabled
			if ( !isset( $this->prefs[$post_type]['deduct'] ) || $this->prefs[$post_type]['deduct'] == 0 ) return;

			// All the reasons to bail
			$achievement_data = get_post_meta( $achievement_id, '_mycred_values', true );
			if ( empty( $achievement_data ) && $this->prefs[$post_type]['creds'] == 0 ) return;
			elseif ( !empty( $achievement_data ) && $achievement_data['creds'] == 0 ) return;

			// Creds
			if ( $this->prefs[$post_type]['creds'] == $achievement_data['creds'] )
				$creds = 0-$this->prefs[$post_type]['creds'];
			else
				$creds = 0-$achievement_data['creds'];

			// Log Template
			if ( $this->prefs[$post_type]['deduct_log'] == $achievement_data['deduct_log'] )
				$entry = $this->prefs[$post_type]['deduct_log'];
			else
				$entry = $achievement_data['deduct_log'];

			// Execute
			$post_type_object = get_post_type_object( $post_type );
			$this->core->add_creds(
				$post_type_object->labels->name,
				$user_id,
				$creds,
				$entry,
				$post_type,
				array( 'ref_type' => 'post' )
			);
		}
		
		/**
		 * Preferences for Commenting Hook
		 * @since 1.0.8
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs;
			$badge_post_types = badgeos_get_achievement_types_slugs();
			foreach ( $badge_post_types as $post_type ) {
				if ( in_array( $post_type, apply_filters( 'mycred_badgeos_excludes', array( 'step' ) ) ) ) continue;
				if ( !isset( $prefs[$post_type] ) )
					$prefs[$post_type] = array(
						'creds'      => 10,
						'log'        => '',
						'deduct'     => 1,
						'deduct_log' => '%plural% deduction'
					);
				
				$post_type_object = get_post_type_object( $post_type );
				$title = sprintf( __( 'Default %s for %s', 'mycred' ), $this->core->plural(), $post_type_object->labels->singular_name ); ?>

					<!-- Creds for  -->
					<label for="<?php echo $this->field_id( array( $post_type, 'creds' ) ); ?>" class="subheader"><?php echo $title; ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $post_type, 'creds' ) ); ?>" id="<?php echo $this->field_id( array( $post_type, 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs[$post_type]['creds'] ); ?>" size="8" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( 'User zero to disable users gaining %_plural%', 'mycred' ) ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( $post_type, 'log' ) ); ?>"><?php _e( 'Default Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $post_type, 'log' ) ); ?>" id="<?php echo $this->field_id( array( $form_id, 'log' ) ); ?>" value="<?php echo $prefs[$post_type]['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( $post_type, 'deduct' ) ); ?>" id="<?php echo $this->field_id( array( $post_type, 'deduct' ) ); ?>" <?php checked( $prefs[$post_type]['deduct'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( $post_type, 'deduct' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Deduct %_plural% if user looses ' . $post_type_object->labels->singular_name, 'mycred' ) ); ?></label>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( $post_type, 'deduct_log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $post_type, 'deduct_log' ) ); ?>" id="<?php echo $this->field_id( array( $form_id, 'deduct_log' ) ); ?>" value="<?php echo $prefs[$post_type]['deduct_log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
<?php
			}
		}
	}
}
?>