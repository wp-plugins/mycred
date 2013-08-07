<?php
/**
 * Third-Party Plugin Hooks
 *
 * @since 1.1
 * @version 1.0
 */
if ( !defined( 'myCRED_VERSION' ) ) exit;

/**
 * bbPress
 * @since 0.1
 * @version 1.2
 */
if ( class_exists( 'bbPress' ) ) {
	/**
	 * Insert Points Balance in Profile
	 * @since 1.1.1
	 * @version 1.0
	 */
	add_action( 'bbp_template_after_user_profile', 'mycred_bbp_add_balance_in_profile' );
	function mycred_bbp_add_balance_in_profile() {
		$user_id = bbp_get_displayed_user_id();
		$mycred = mycred_get_settings();

		if ( $mycred->exclude_user( $user_id ) ) return;

		$balance = $mycred->get_users_cred( $user_id );
		echo '<div class="users-mycred-balance">' . $mycred->plural() . ': ' . $mycred->format_creds( $balance ) . '</div>';
	}

	/**
	 * bbPress Hook
	 * @since 1.1.1
	 * @version 1.2
	 */
	if ( !class_exists( 'myCRED_bbPress' ) ) {
		class myCRED_bbPress extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs ) {
				parent::__construct( array(
					'id'       => 'hook_bbpress',
					'defaults' => array(
						'new_forum' => array(
							'creds'    => 1,
							'log'      => '%plural% for new forum'
						),
						'delete_forum' => array(
							'creds'    => 0-1,
							'log'      => '%singular% deduction for deleted forum'
						),
						'new_topic' => array(
							'creds'    => 1,
							'log'      => '%plural% for new forum topic',
							'author'   => 0
						),
						'delete_topic' => array(
							'creds'    => 0-1,
							'log'      => '%singular% deduction for deleted topic'
						),
						'fav_topic' => array(
							'creds'    => 1,
							'log'      => '%plural% for someone favorited your forum topic',
							'limit'    => 1
						),
						'new_reply' => array(
							'creds'    => 1,
							'log'      => '%plural% for new forum reply',
							'author'   => 0,
							'limit'    => 10,
						),
						'delete_reply' => array(
							'creds'    => 0-1,
							'log'      => '%singular% deduction for deleted reply'
						),
						'show_points_in_reply' => 0
					)
				), $hook_prefs );
			}

			/**
			 * Run
			 * @since 0.1
			 * @version 1.2
			 */
			public function run() {
				// Insert Points balance in profile
				if ( isset( $this->prefs['show_points_in_reply'] ) && $this->prefs['show_points_in_reply'] == 1 )
					add_action( 'bbp_theme_after_reply_author_details', array( $this, 'insert_balance' ) );

				// New Forum
				if ( $this->prefs['new_forum']['creds'] != 0 )
					add_action( 'bbp_new_forum', array( $this, 'new_forum' ), 20 );
				// Delete Forum
				if ( $this->prefs['delete_forum']['creds'] != 0 )
					add_action( 'bbp_delete_forum', array( $this, 'delete_forum' ) );
				// New Topic
				if ( $this->prefs['new_topic']['creds'] != 0 )
					add_action( 'bbp_new_topic', array( $this, 'new_topic' ), 20, 4 );
				// Delete Topic
				if ( $this->prefs['delete_topic']['creds'] != 0 )
					add_action( 'bbp_delete_topic', array( $this, 'delete_topic' ) );
				// Fave Topic
				if ( $this->prefs['fav_topic']['creds'] != 0 )
					add_action( 'bbp_add_user_favorite', array( $this, 'fav_topic' ), 10, 2 );
				// New Reply
				if ( $this->prefs['new_reply']['creds'] != 0 )
					add_action( 'bbp_new_reply', array( $this, 'new_reply' ), 20, 5 );
				// Delete Reply
				if ( $this->prefs['delete_reply']['creds'] != 0 )
					add_action( 'bbp_delete_reply', array( $this, 'delete_reply' ) );
			}

			/**
			 * New Forum
			 * @since 1.1.1
			 * @version 1.1
			 */
			public function new_forum( $forum ) {
				// Forum id
				$forum_id = $forum['forum_id'];

				// Forum author
				$forum_author = $forum['forum_author'];

				// Check if user is excluded
				if ( $this->core->exclude_user( $forum_author ) ) return;

				// Make sure this is unique event
				if ( $this->has_entry( 'new_forum', $forum_id, $forum_author ) ) return;

				// Execute
				$this->core->add_creds(
					'new_forum',
					$forum_author,
					$this->prefs['new_forum']['creds'],
					$this->prefs['new_forum']['log'],
					$forum_id,
					array( 'ref_type' => 'post' )
				);
			}
			
			/**
			 * Delete Forum
			 * @since 1.2
			 * @version 1.0
			 */
			public function delete_forum( $forum_id ) {
				// Get Author
				$forum_author = bbp_get_forum_author_id( $forum_id );
				
				// If gained, points, deduct
				if ( $this->has_entry( 'new_forum', $forum_id, $forum_author ) ) {

					// Execute
					$this->core->add_creds(
						'deleted_forum',
						$forum_author,
						$this->prefs['delete_forum']['creds'],
						$this->prefs['delete_forum']['log'],
						$forum_id,
						array( 'ref_type' => 'post' )
					);

				}
			}

			/**
			 * New Topic
			 * @since 0.1
			 * @version 1.1
			 */
			public function new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {
				// Check if user is excluded
				if ( $this->core->exclude_user( $topic_author ) ) return;

				// Check if forum author is allowed to get points for their own topics
				if ( (bool) $this->prefs['new_topic']['author'] == false ) {
					if ( bbp_get_forum_author_id( $forum_id ) == $topic_author ) return;
				}

				// Make sure this is unique event
				if ( $this->has_entry( 'new_forum_topic', $topic_id, $topic_author ) ) return;

				// Execute
				$this->core->add_creds(
					'new_forum_topic',
					$topic_author,
					$this->prefs['new_topic']['creds'],
					$this->prefs['new_topic']['log'],
					$topic_id,
					array( 'ref_type' => 'post' )
				);
			}
			
			/**
			 * Delete Topic
			 * @since 1.2
			 * @version 1.0
			 */
			public function delete_topic( $topic_id ) {
				// Get Author
				$topic_author = bbp_get_topic_author_id( $topic_id );
				
				// If gained, points, deduct
				if ( $this->has_entry( 'new_forum_topic', $topic_id, $topic_author ) ) {

					// Execute
					$this->core->add_creds(
						'deleted_topic',
						$topic_author,
						$this->prefs['delete_topic']['creds'],
						$this->prefs['delete_topic']['log'],
						$topic_id,
						array( 'ref_type' => 'post' )
					);

				}
			}

			/**
			 * Topic Added to Favorites
			 * @by Fee (http://wordpress.org/support/profile/wdfee)
			 * @since 1.1.1
			 * @version 1.2
			 */
			public function fav_topic( $user_id, $topic_id ) {

				// $user_id is loggedin_user, not author, so get topic author
				$topic_author = get_post_field( 'post_author', $topic_id );

				// Enforce Daily Limit
				if ( $this->reached_daily_limit( $topic_author, 'fav_topic' ) ) return;

				// Check if user is excluded (required)
				if ( $this->core->exclude_user( $topic_author ) || $topic_author == $user_id ) return;

				// Make sure this is a unique event (favorite not from same user)
				if ( $this->has_entry( 'topic_favorited', $topic_id, $topic_author, 's:8:"ref_user";i:' . $user_id . ';' ) ) return;

				// Execute
				$this->core->add_creds(
					'topic_favorited',
					$topic_author,
					$this->prefs['fav_topic']['creds'],
					$this->prefs['fav_topic']['log'],
					$topic_id,
					array( 'ref_user' => $user_id, 'ref_type' => 'post' )
				);
				
				// Update Limit
				$this->update_daily_limit( $topic_author, 'fav_topic' );
			}

			/**
			 * New Reply
			 * @since 0.1
			 * @version 1.2
			 */
			public function new_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
				// Check if user is excluded
				if ( $this->core->exclude_user( $reply_author ) ) return;

				// Check if topic author gets points for their own replies
				if ( (bool) $this->prefs['new_reply']['author'] === false ) {
					if ( bbp_get_topic_author_id( $topic_id ) == $reply_author ) return;
				}
				
				// Check daily limit
				if ( $this->reached_daily_limit( $reply_author, 'new_reply' ) ) return;

				// Make sure this is unique event
				if ( $this->has_entry( 'new_forum_reply', $reply_id, $reply_author ) ) return;

				// Execute
				$this->core->add_creds(
					'new_forum_reply',
					$reply_author,
					$this->prefs['new_reply']['creds'],
					$this->prefs['new_reply']['log'],
					$reply_id,
					array( 'ref_type' => 'post' )
				);
				
				// Update Limit
				$this->update_daily_limit( $topic_author, 'new_reply' );
			}
			
			/**
			 * Delete Reply
			 * @since 1.2
			 * @version 1.0
			 */
			public function delete_reply( $reply_id ) {
				// Get Author
				$reply_author = bbp_get_reply_author_id( $reply_id );
				
				// If gained, points, deduct
				if ( $this->has_entry( 'new_forum_reply', $reply_id, $reply_author ) ) {

					// Execute
					$this->core->add_creds(
						'deleted_reply',
						$reply_author,
						$this->prefs['delete_reply']['creds'],
						$this->prefs['delete_reply']['log'],
						$reply_id,
						array( 'ref_type' => 'post' )
					);

				}
			}

			/**
			 * Insert Balance
			 * @since 0.1
			 * @version 1.1
			 */
			public function insert_balance() {
				$reply_id = bbp_get_reply_id();
				if ( bbp_is_reply_anonymous( $reply_id ) ) return;

				$balance = $this->core->get_users_cred( bbp_get_reply_author_id( $reply_id ) );
				echo '<div class="mycred-balance">' . $this->core->plural() . ': ' . $this->core->format_creds( $balance ) . '</div>';
			}
			
			/**
			 * Reched Daily Limit
			 * Checks if a user has reached their daily limit.
			 * @since 1.2
			 * @version 1.0
			 */
			public function reached_daily_limit( $user_id, $id ) {
				// No limit used
				if ( $this->prefs[$id]['limit'] == 0 ) return false;
				
				$today = date( 'Y-m-d' );
				$current = get_user_meta( $user_id, 'mycred_bbp_limits_' . $id, true );
				if ( empty( $current ) || !array_key_exists( $today, (array) $current ) )
					$current[$today] = 0;
				
				if ( $current[ $today ] < $this->prefs[$id]['limit'] ) return false;
				
				return true;
			}
			
			/**
			 * Update Daily Limit
			 * Updates a given users daily limit.
			 * @since 1.2
			 * @version 1.0
			 */
			public function update_daily_limit( $user_id, $id ) {
				// No limit used
				if ( $this->prefs[$id]['limit'] == 0 ) return;

				$today = date( 'Y-m-d' );
				$current = get_user_meta( $user_id, 'mycred_bbp_limits_' . $id, true );
				if ( empty( $current ) || !array_key_exists( $today, (array) $current ) )
					$current[$today] = 0;
				
				$current[ $today ] = $current[ $today ]+1;
				
				update_user_meta( $user_id, 'mycred_bbp_limits_' . $id, $current );
			}

			/**
			 * Preferences
			 * @since 0.1
			 * @version 1.1
			 */
			public function preferences() {
				$prefs = $this->prefs;

				// Update
				if ( !isset( $prefs['show_points_in_reply'] ) )
					$prefs['show_points_in_reply'] = 0;
				if ( !isset( $prefs['new_topic']['author'] ) )
					$prefs['new_topic']['author'] = 0;
				if ( !isset( $prefs['fav_topic'] ) )
					$prefs['fav_topic'] = array( 'creds' => 1, 'log' => '%plural% for someone favorited your forum topic' );
				if ( !isset( $prefs['new_reply']['author'] ) )
					$prefs['new_reply']['author'] = 0;
				
				if ( !isset( $prefs['fav_topic']['limit'] ) )
					$prefs['fav_topic']['limit'] = 0;
				if ( !isset( $prefs['new_reply']['limit'] ) )
					$prefs['new_reply']['limit'] = 0; ?>

					<!-- Creds for New Forums -->
					<label for="<?php echo $this->field_id( array( 'new_forum', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Forum', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_forum', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_forum', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_forum']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_forum', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_forum', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_forum', 'log' ) ); ?>" value="<?php echo $prefs['new_forum']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Deleting Forums -->
					<label for="<?php echo $this->field_id( array( 'delete_forum', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Forum Deletion', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_forum', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_forum', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['delete_forum']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'delete_forum', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_forum', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_forum', 'log' ) ); ?>" value="<?php echo $prefs['delete_forum']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Topic -->
					<label for="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Topic', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_topic']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>" value="<?php echo $prefs['new_topic']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'new_topic' => 'author' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic' => 'author' ) ); ?>" <?php checked( $prefs['new_topic']['author'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'new_topic' => 'author' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Forum authors can receive %_plural% for creating new topics.', 'mycred' ) ); ?></label>
						</li>
					</ol>
					<!-- Creds for Deleting Topic -->
					<label for="<?php echo $this->field_id( array( 'delete_topic', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Topic Deletion', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_topic', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['delete_topic']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'delete_topic', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_topic', 'log' ) ); ?>" value="<?php echo $prefs['delete_topic']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Faved Topic -->
					<label for="<?php echo $this->field_id( array( 'fav_topic', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Favorited Topic', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'fav_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'fav_topic', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['fav_topic']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'fav_topic', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'fav_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'fav_topic', 'log' ) ); ?>" value="<?php echo $prefs['fav_topic']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'fav_topic', 'limit' ) ); ?>"><?php _e( 'Daily Limit', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'fav_topic', 'limit' ) ); ?>" id="<?php echo $this->field_id( array( 'fav_topic', 'limit' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['fav_topic']['limit'] ); ?>" size="8" /></div>
							<span class="description"><?php _e( 'Use zero for unlimited', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Reply -->
					<label for="<?php echo $this->field_id( array( 'new_reply', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Reply', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_reply', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_reply', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_reply']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_reply', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_reply', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_reply', 'log' ) ); ?>" value="<?php echo $prefs['new_reply']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, Post', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'new_reply' => 'author' ) ); ?>" id="<?php echo $this->field_id( array( 'new_reply' => 'author' ) ); ?>" <?php checked( $prefs['new_reply']['author'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'new_reply' => 'author' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Topic authors can receive %_plural% for replying to their own Topic', 'mycred' ) ); ?></label>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_reply', 'limit' ) ); ?>"><?php _e( 'Daily Limit', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_reply', 'limit' ) ); ?>" id="<?php echo $this->field_id( array( 'new_reply', 'limit' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_reply']['limit'] ); ?>" size="8" /></div>
							<span class="description"><?php _e( 'Use zero for unlimited', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'show_points_in_reply' ); ?>" id="<?php echo $this->field_id( 'show_points_in_reply' ); ?>" <?php checked( $prefs['show_points_in_reply'], 1 ); ?> value="1" /> <label for="<?php echo $this->field_id( 'show_points_in_reply' ); ?>"><?php echo $this->core->template_tags_general( __( 'Show users %_plural% balance in replies', 'mycred' ) ); ?>.</label>
						</li>
					</ol>
<?php			unset( $this );
			}

			/**
			 * Sanitise Preference
			 * @since 1.1.1
			 * @version 1.0
			 */
			function sanitise_preferences( $data ) {
				$new_data = $data;

				$new_data['new_topic']['author'] = ( isset( $data['new_topic']['author'] ) ) ? $data['new_topic']['author'] : 0;
				$new_data['new_reply']['author'] = ( isset( $data['new_reply']['author'] ) ) ? $data['new_reply']['author'] : 0;

				return $new_data;
			}
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
		 * @version 1.1
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
			$mycred = mycred_get_settings();
			$achievement_data = get_post_meta( $post->ID, '_mycred_values', true );
			if ( empty( $achievement_data ) )
				$achievement_data = $prefs[$post->post_type]; ?>

			<p><strong><?php echo $mycred->template_tags_general( __( '%plural% to Award', 'mycred' ) ); ?></strong></p>
			<p>
				<label class="screen-reader-text" for="mycred-values-creds"><?php echo $mycred->template_tags_general( __( '%plural% to Award', 'mycred' ) ); ?></label>
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
		 * @version 1.1
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
			if ( $this->prefs[$post_type]['deduct'] == 1 ) {
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

			// Get achievemen data
			$achievement_data = get_post_meta( $achievement_id, '_mycred_values', true );
			if ( empty( $achievement_data ) )
				$achievement_data = $this->prefs[$post_type];

			// Make sure its not disabled
			if ( $achievement_data['creds'] == 0 ) return;

			// Execute
			$post_type_object = get_post_type_object( $post_type );
			$this->core->add_creds(
				$post_type_object->labels->name,
				$user_id,
				$achievement_data['creds'],
				$achievement_data['log'],
				$achievement_id,
				array( 'ref_type' => 'post' )
			);
		}

		/**
		 * Revoke Achievement
		 * Run by BadgeOS when a users achievement is revoed.
		 * @since 1.0.8
		 * @version 1.1
		 */
		public function revoke_achievement( $user_id, $achievement_id ) {
			$post_type = get_post_type( $achievement_id );
			// Settings are not set
			if ( !isset( $this->prefs[$post_type]['creds'] ) ) return;

			// Get achievemen data
			$achievement_data = get_post_meta( $achievement_id, '_mycred_values', true );
			if ( empty( $achievement_data ) )
				$achievement_data = $this->prefs[$post_type];

			// Make sure its not disabled
			if ( $achievement_data['creds'] == 0 ) return;

			// Execute
			$post_type_object = get_post_type_object( $post_type );
			$this->core->add_creds(
				$post_type_object->labels->name,
				$user_id,
				0-$achievement_data['creds'],
				$achievement_data['deduct_log'],
				$achievement_id,
				array( 'ref_type' => 'post' )
			);
		}

		/**
		 * Preferences for BadgeOS
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

/**
 * Hook for WP-Polls Plugin
 * @since 1.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_WPPolls' ) && function_exists( 'vote_poll' ) ) {
	class myCRED_Hook_WPPolls extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'wppolls',
				'defaults' => array(
					'creds' => 1,
					'log'   => '%plural% for voting'
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 1.1
		 * @version 1.0
		 */
		public function run() {
			add_action( 'wp_ajax_polls',          array( $this, 'vote_poll' ), 1 );
			add_filter( 'mycred_parse_tags_poll', array( $this, 'parse_custom_tags' ), 10, 2 );
		}

		/**
		 * Poll Voting
		 * @since 1.1
		 * @version 1.0
		 */
		public function vote_poll() {
			if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'polls' && is_user_logged_in() ) {
				// Get Poll ID
				$poll_id = ( isset( $_REQUEST['poll_id'] ) ? intval( $_REQUEST['poll_id'] ) : 0 );

				// Ensure Poll ID Is Valid
				if ( $poll_id != 0 ) {
					// Verify Referer
					if ( check_ajax_referer( 'poll_' . $poll_id . '-nonce', 'poll_' . $poll_id . '_nonce', false ) ) {
						// Which View
						switch ( $_REQUEST['view'] ) {
							case 'process':
								$poll_aid = $_POST["poll_$poll_id"];
								$poll_aid_array = array_unique( array_map( 'intval', explode( ',', $poll_aid ) ) );
								if ( $poll_id > 0 && !empty( $poll_aid_array ) && check_allowtovote() ) {
									$check_voted = check_voted( $poll_id );
									if ( $check_voted == 0 ) {
										$user_id = get_current_user_id();
										// Make sure we are not excluded
										if ( !$this->core->exclude_user( $user_id ) ) {
											$this->core->add_creds(
												'poll_voting',
												$user_id,
												$this->prefs['creds'],
												$this->prefs['log'],
												$poll_id,
												array( 'ref_type' => 'poll' )
											);
										}
									}
								}
							break;
						}
					}
				}
			}
		}

		/**
		 * Parse Custom Tags in Log
		 * @since 1.1
		 * @version 1.0
		 */
		public function parse_custom_tags( $content, $log_entry ) {
			$poll_id = $log_entry->ref_id;
			$content = str_replace( '%poll_id%', $poll_id, $content );
			$content = str_replace( '%poll_question%', $this->get_poll_name( $poll_id ), $content );

			return $content;
		}

		/**
		 * Get Poll Name (Question)
		 * @since 1.1
		 * @version 1.0
		 */
		protected function get_poll_name( $poll_id ) {
			global $wpdb;

			$sql = "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d ";
			return $wpdb->get_var( $wpdb->prepare( $sql, $poll_id ) );
		}

		/**
		 * Preferences for WP-Polls
		 * @since 1.1
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
							<span class="description"><?php _e( 'Available template tags: General. You can also use %poll_id% and %poll_question%.', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}

/**
 * Hook for WP Favorite Posts
 * @since 1.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_WPFavorite' ) && function_exists( 'wp_favorite_posts' ) ) {
	class myCRED_Hook_WPFavorite extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'wpfavorite',
				'defaults' => array(
					'add'    => array(
						'creds' => 1,
						'log'   => '%plural% for adding a post as favorite'
					),
					'remove' => array(
						'creds' => 1,
						'log'   => '%plural% deduction for removing a post from favorites'
					)
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 1.1
		 * @version 1.0
		 */
		public function run() {
			if ( $this->prefs['add']['creds'] != 0 )
				add_action( 'wpfp_after_add',    array( $this, 'add_favorite' ) );

			if ( $this->prefs['remove']['creds'] != 0 )
				add_action( 'wpfp_after_remove', array( $this, 'remove_favorite' ) );
		}

		/**
		 * Add Favorite
		 * @since 1.1
		 * @version 1.0
		 */
		public function add_favorite( $post_id ) {
			// Must be logged in
			if ( !is_user_logged_in() ) return;

			$user_id = get_current_user_id();
			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'add_favorite_post', $post_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'add_favorite_post',
				$user_id,
				$this->prefs['add']['creds'],
				$this->prefs['add']['log'],
				$post_id,
				array( 'ref_type' => 'post' )
			);
		}

		/**
		 * Remove Favorite
		 * @since 1.1
		 * @version 1.0
		 */
		public function remove_favorite( $post_id ) {
			// Must be logged in
			if ( !is_user_logged_in() ) return;

			$user_id = get_current_user_id();
			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'favorite_post_removed', $post_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'favorite_post_removed',
				$user_id,
				$this->prefs['remove']['creds'],
				$this->prefs['remove']['log'],
				$post_id,
				array( 'ref_type' => 'post' )
			);
		}

		/**
		 * Preferences for WP-Polls
		 * @since 1.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader" for="<?php echo $this->field_id( array( 'add' => 'creds' ) ); ?>"><?php _e( 'Adding Content to Favorites', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'add' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'add' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['add']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'add' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'add' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'add' => 'log' ) ); ?>" value="<?php echo $prefs['add']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General and Post Related', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'remove' => 'creds' ) ); ?>"><?php _e( 'Removing Content from Favorites', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'remove' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'remove' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['remove']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'remove' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'remove' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'remove' => 'log' ) ); ?>" value="<?php echo $prefs['remove']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General and Post Related', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}

/**
 * Hook for Events Manager
 * @since 1.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_Events_Manager' ) && function_exists( 'bp_em_init' ) ) {
	class myCRED_Hook_Events_Manager extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'eventsmanager',
				'defaults' => array(
					'attend' => array(
						'creds' => 1,
						'log'   => '%plural% for attending an %link_with_title%'
					),
					'cancel' => array(
						'creds' => 1,
						'log'   => '%plural% for cancelled attendance at %link_with_title%'
					)
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 1.1
		 * @version 1.0
		 */
		public function run() {
			if ( $this->prefs['attend']['creds'] != 0 && get_option( 'dbem_bookings_approval' ) != 0 )
				add_filter( 'em_bookings_add',       array( $this, 'new_booking' ), 10, 2 );

			add_filter( 'em_booking_set_status', array( $this, 'adjust_booking' ), 10, 2 );
		}
		
		/**
		 * New Booking
		 * When users can make their own bookings.
		 * @since 1.1
		 * @version 1.1
		 */
		public function new_booking( $result, $booking ) {
			$user_id = $booking->person->id;
			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) ) return $result;
			
			// Successfull Booking
			if ( $result === true ) {
				// Execute
				$this->core->add_creds(
					'event_booking',
					$user_id,
					$this->prefs['attend']['creds'],
					$this->prefs['attend']['log'],
					$booking->event->post_id,
					array( 'ref_type' => 'post' )
				);
			}
			
			return $result;
		}
		
		/**
		 * Adjust Booking
		 * Incase an administrator needs to approve bookings first or if booking gets
		 * cancelled.
		 * @since 1.1
		 * @version 1.1
		 */
		public function adjust_booking( $result, $booking ) {
			$user_id = $booking->person->id;
			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) ) return $result;
			
			// If the new status is 'approved', add points
			if ( $booking->booking_status == 1 && $booking->previous_status != 1 ) {
				// If we do not award points for attending an event bail now
				if ( $this->prefs['attend']['creds'] == 0 ) return $result;
				
				// Execute
				$this->core->add_creds(
					'event_attendance',
					$user_id,
					$this->prefs['attend']['creds'],
					$this->prefs['attend']['log'],
					$booking->event->post_id,
					array( 'ref_type' => 'post' )
				);
			}
			// Else if status got changed from previously 'approved', remove points given
			elseif ( $booking->booking_status != 1 && $booking->previous_status == 1 ) {
				// If we do not deduct points for cancellation bail now
				if ( $this->prefs['cancel']['creds'] == 0 ) return $result;
				
				// Execute
				$this->core->add_creds(
					'cancelled_event_attendance',
					$user_id,
					$this->prefs['cancel']['creds'],
					$this->prefs['cancel']['log'],
					$booking->event->post_id,
					array( 'ref_type' => 'post' )
				);
			}
			
			return $result;
		}
		
		/**
		 * Preferences for Events Manager
		 * @since 1.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader" for="<?php echo $this->field_id( array( 'attend' => 'creds' ) ); ?>"><?php _e( 'Attending Event', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'attend' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'attend' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['attend']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'attend' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'attend' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'attend' => 'log' ) ); ?>" value="<?php echo $prefs['attend']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General and Post Related', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'cancel' => 'creds' ) ); ?>"><?php _e( 'Cancelling Attendance', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'cancel' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'cancel' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['cancel']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'cancel' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'cancel' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'cancel' => 'log' ) ); ?>" value="<?php echo $prefs['cancel']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General and Post Related', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}

/**
 * Hook for GD Star Rating
 * @since 1.2
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Hook_GD_Star_Rating' ) && defined( 'STARRATING_DEBUG' ) ) {
	class myCRED_Hook_GD_Star_Rating extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'gdstars',
				'defaults' => array(
					'star_rating' => array(
						'creds' => 1,
						'log'   => '%plural% for rating'
					),
					'up_down' => array(
						'creds' => 1,
						'log'   => '%plural% for rating'
					)
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 1.2
		 * @version 1.0
		 */
		public function run() {
			add_action( 'gdsr_vote', array( $this, 'vote' ), 10, 4 );
		}

		/**
		 * Vote
		 * @since 1.2
		 * @version 1.0
		 */
		public function vote( $vote_value, $post_id, $vote_tpl, $vote_size ) {
			if ( !is_user_logged_in() ) return;
			
			if ( is_string( $vote_value ) && $this->prefs['up_down']['creds'] == 0 ) return;
			elseif ( !is_string( $vote_value ) && $this->prefs['star_rating']['creds'] == 0 ) return;
			
			if ( is_string( $vote_value ) ) {
				$vote = 'up_down';
				$star = false;
			}
			else {
				$vote = 'star_rating';
				$star = true;
			}
			$user_id = get_current_user_id();
			
			if ( $this->core->has_entry( 'rating', $post_id, $user_id, $vote ) ) return;

			// Execute
			$this->core->add_creds(
				'rating',
				$user_id,
				( $star ) ? $this->prefs['star_rating']['creds'] : $this->prefs['up_down']['creds'],
				( $star ) ? $this->prefs['star_rating']['log'] : $this->prefs['up_down']['log'],
				$post_id,
				$vote
			);
		}

		/**
		 * Preferences for GD Star Rating
		 * @since 1.2
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader" for="<?php echo $this->field_id( array( 'star_rating' => 'creds' ) ); ?>"><?php _e( 'Rating', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'star_rating' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'star_rating' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['star_rating']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'star_rating' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'star_rating' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'star_rating' => 'log' ) ); ?>" value="<?php echo $prefs['star_rating']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'up_down' => 'creds' ) ); ?>"><?php _e( 'Up / Down Vote', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'up_down' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'up_down' => 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['up_down']['creds'] ); ?>" size="8" /></div>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'up_down' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'up_down' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'up_down' => 'log' ) ); ?>" value="<?php echo $prefs['up_down']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}
?>