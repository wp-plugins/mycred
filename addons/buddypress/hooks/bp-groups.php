<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_BuddyPress_Groups class
 *
 * Creds for groups actions such as joining / leaving, creating / deleting, new topics / edit topics or new posts / edit posts
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_BuddyPress_Groups' ) ) {
	class myCRED_BuddyPress_Groups extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'hook_bp_groups',
				'defaults' => array(
					'create'     => array(
						'creds'     => 10,
						'log'       => '%plural% for creating a new group',
						'min'       => 0
					),
					'delete'     => array(
						'creds'     => '-10',
						'log'       => '%singular% deduction for deleting a group'
					),
					'new_topic'  => array(
						'creds'     => 1,
						'log'       => '%plural% for new group topic'
					),
					'edit_topic' => array(
						'creds'     => 1,
						'log'       => '%plural% for updating group topic'
					),
					'new_post'   => array(
						'creds'     => 1,
						'log'       => '%plural% for new group post'
					),
					'edit_post'  => array(
						'creds'     => 1,
						'log'       => '%plural% for updating group post'
					),
					'join'       => array(
						'creds'     => 1,
						'log'       => '%plural% for joining new group'
					),
					'leave'      => array(
						'creds'     => '-5',
						'log'       => '%singular% deduction for leaving group'
					),
					'avatar'     => array(
						'creds'     => 1,
						'log'       => '%plural% for new group avatar'
					),
					'comments'   => array(
						'creds'     => 1,
						'log'       => '%plural% for new group comment'
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
			if ( $this->prefs['create']['creds'] != 0 && $this->prefs['create']['min'] == 0 )
				add_action( 'groups_group_create_complete',     array( $this, 'create_group' )                   );

			if ( $this->prefs['create']['creds'] < 0 )
				add_filter( 'bp_user_can_create_groups',        array( $this, 'restrict_group_creation' ), 99, 2 );

			if ( $this->prefs['delete']['creds'] != 0 )
				add_action( 'groups_group_deleted',             array( $this, 'delete_group' )                   );

			if ( $this->prefs['new_topic']['creds'] != 0 )
				add_action( 'bp_forums_new_topic',              array( $this, 'new_topic' )                      );

			if ( $this->prefs['edit_topic']['creds'] != 0 )
				add_action( 'groups_edit_forum_topic',          array( $this, 'edit_topic' )                     );

			if ( $this->prefs['new_post']['creds'] != 0 )
				add_action( 'bp_forums_new_post',               array( $this, 'new_post' )                       );

			if ( $this->prefs['edit_post']['creds'] != 0 )
				add_action( 'groups_edit_forum_post',           array( $this, 'edit_post' )                      );

			if ( $this->prefs['join']['creds'] != 0 || ( $this->prefs['create']['creds'] != 0 && $this->prefs['create']['min'] != 0 ) )
				add_action( 'groups_join_group',                array( $this, 'join_group' ), 20, 2              );
			
			if ( $this->prefs['join']['creds'] < 0 )
				add_filter( 'bp_get_group_join_button', array( $this, 'restrict_joining_group' ) );

			if ( $this->prefs['leave']['creds'] != 0 )
				add_action( 'groups_leave_group',               array( $this, 'leave_group' ), 20, 2             );

			if ( $this->prefs['avatar']['creds'] != 0 )
				add_action( 'groups_screen_group_admin_avatar', array( $this, 'avatar_upload_group' )            );

			if ( $this->prefs['comments']['creds'] != 0 )
				add_action( 'bp_groups_posted_update',          array( $this, 'new_group_comment' ), 20, 4       );
		}

		/**
		 * Creating Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function create_group( $group_id ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'creation_of_new_group',
				$bp->loggedin_user->id,
				$this->prefs['create']['creds'],
				$this->prefs['create']['log'],
				$group_id,
				'bp_group'
			);
		}

		/**
		 * Restrict Group Creation
		 * If creating a group costs and the user does not have enough points, we restrict creations.
		 * @since 0.1
		 * @version 1.0
		 */
		public function restrict_group_creation( $can_create, $restricted ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return $can_create;

			// Check if user has enough to create a group
			$cost = abs( $this->prefs['create']['creds'] );
			$balance = $this->core->get_users_cred( $bp->loggedin_user->id );
			if ( $cost > $balance ) return false;

			return $can_create;
		}

		/**
		 * Restrict Group Join
		 * If joining a group costs and the user does not have enough points, we restrict joining of groups.
		 * @since 0.1
		 * @version 1.0
		 */
		public function restrict_joining_group( $button ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return $button;

			// Check if user has enough to join group
			$cost = abs( $this->prefs['join']['creds'] );
			$balance = $this->core->get_users_cred( $bp->loggedin_user->id );
			if ( $cost > $balance ) return false;

			return $button;
		}

		/**
		 * Deleting Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function delete_group( $group_id ) {
			global $bp;

			// If admin is removing deduct from creator
			if ( $bp->loggedin_user->is_super_admin ) {
				$user_id = $bp->groups->current_group->creator_id;
			}

			// Else if admin but not the creator is removing
			elseif ( $bp->loggedin_user->id != $bp->groups->current_group->creator_id ) {
				$user_id = $bp->groups->current_group->creator_id;
			}

			// Else deduct from current user
			else {
				$user_id = $bp->loggedin_user->id;
			}

			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'deletion_of_group',
				$user_id,
				$this->prefs['delete']['creds'],
				$this->prefs['delete']['log'],
				$group_id,
				'bp_group'
			);
		}

		/**
		 * New Group Forum Topic
		 * @since 0.1
		 * @version 1.0
		 */
		public function new_topic( $topic_id ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_group_forum_topic', $topic_id, $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_group_forum_topic',
				$bp->loggedin_user->id,
				$this->prefs['new_topic']['creds'],
				$this->prefs['new_topic']['log'],
				$topic_id,
				'bp_ftopic'
			);
		}

		/**
		 * Edit Group Forum Topic
		 * @since 0.1
		 * @version 1.0
		 */
		public function edit_topic( $topic_id ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'edit_group_forum_topic',
				$bp->loggedin_user->id,
				$this->prefs['edit_topic']['creds'],
				$this->prefs['edit_topic']['log'],
				$topic_id,
				'bp_ftopic'
			);
		}

		/**
		 * New Group Forum Post
		 * @since 0.1
		 * @version 1.0
		 */
		public function new_post( $post_id ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_group_forum_post', $post_id, $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_group_forum_post',
				$bp->loggedin_user->id,
				$this->prefs['new_post']['creds'],
				$this->prefs['new_post']['log'],
				$post_id,
				'bp_fpost'
			);
		}

		/**
		 * Edit Group Forum Post
		 * @since 0.1
		 * @version 1.0
		 */
		public function edit_post( $post_id ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'edit_group_forum_post',
				$bp->loggedin_user->id,
				$this->prefs['edit_post']['creds'],
				$this->prefs['edit_post']['log'],
				$post_id,
				'bp_fpost'
			);
		}

		/**
		 * Joining Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function join_group( $group_id, $user_id ) {
			// Minimum members limit
			if ( $this->prefs['create']['min'] != 0 ) {
				$group = groups_get_group( array( 'group_id' => $group_id ) );
				$count = $group->total_member_count;
				$creator = $group->creator_id;

				// Award creator if we have reached the minimum number of members and we have not yet been awarded
				if ( $count == $this->prefs['create']['min'] && !$this->core->has_entry( 'creation_of_new_group', $group_id, $creator ) ) {
					$this->core->add_creds( 'creation_of_new_group', $creator, $this->prefs['create']['creds'], $this->prefs['create']['log'], $group_id, 'bp_group' );
				}

				// Clean up
				unset( $group );
			}

			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'joining_group', $group_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'joining_group',
				$user_id,
				$this->prefs['join']['creds'],
				$this->prefs['join']['log'],
				$group_id,
				'bp_group'
			);
		}

		/**
		 * Leaving Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function leave_group( $group_id, $user_id ) {
			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'leaving_group', $group_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'leaving_group',
				$user_id,
				$this->prefs['leave']['creds'],
				$this->prefs['leave']['log'],
				$group_id,
				'bp_group'
			);
		}

		/**
		 * Avatar Upload for Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function avatar_upload_group( $group_id ) {
			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'upload_group_avatar', $group_id ) ) return;

			// Execute
			$this->core->add_creds(
				'upload_group_avatar',
				$bp->loggedin_user->id,
				$this->prefs['avatar']['creds'],
				$this->prefs['avatar']['log'],
				$group_id,
				'bp_group'
			);
		}

		/**
		 * New Group Comment
		 * @since 0.1
		 * @version 1.0
		 */
		public function new_group_comment( $content, $user_id, $group_id, $activity_id ) {
			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_group_comment', $activity_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_group_comment',
				$user_id,
				$this->prefs['comments']['creds'],
				$this->prefs['comments']['log'],
				$activity_id,
				'bp_activity'
			);
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<!-- Creds for New Group -->
					<label for="<?php echo $this->field_id( array( 'create', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Creating Groups', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'create', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['create']['creds'] ); ?>" size="8" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( 'If you use a negative value and the user does not have enough %_plural% the "Create Group" button will be disabled.', 'mycred' ) ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for=""><?php echo $this->core->template_tags_general( __( 'Number of members before awarding %_plural%', 'mycred' ) ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'create', 'min' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'min' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['create']['min'] ); ?>" size="8" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( 'Use zero to award %_plural% when group is created.', 'mycred' ) ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'create', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'create', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'log' ) ); ?>" value="<?php echo $prefs['create']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Deleting Group -->
					<label for="<?php echo $this->field_id( array( 'delete', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Deleting Groups', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['delete']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'delete', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete', 'log' ) ); ?>" value="<?php echo $prefs['delete']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Forum Topic -->
					<label for="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Forum Topic', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_topic']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>" value="<?php echo $prefs['new_topic']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Edit Forum Topic -->
					<label for="<?php echo $this->field_id( array( 'edit_topic', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Editing Forum Topic', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'edit_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_topic', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['edit_topic']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>" value="<?php echo $prefs['new_topic']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Forum Post -->
					<label for="<?php echo $this->field_id( array( 'new_post', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Forum Post', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_post', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_post', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_post']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_post', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_post', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_post', 'log' ) ); ?>" value="<?php echo $prefs['new_post']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Edit Forum Post -->
					<label for="<?php echo $this->field_id( array( 'edit_post', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Editing Forum Post', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'edit_post', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_post', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['edit_post']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'edit_post', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'edit_post', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_post', 'log' ) ); ?>" value="<?php echo $prefs['edit_post']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Joining Group -->
					<label for="<?php echo $this->field_id( array( 'join', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Joining Groups', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'join', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'join', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['join']['creds'] ); ?>" size="8" /></div>
							<span class="description"><?php echo $this->core->template_tags_general( __( 'If you use a negative value and the user does not have enough %_plural% the "Join Group" button will be disabled.', 'mycred' ) ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'join', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'join', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'join', 'log' ) ); ?>" value="<?php echo $prefs['join']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Leaving Group -->
					<label for="<?php echo $this->field_id( array( 'leave', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Leaving Groups', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'leave', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['leave']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'leave', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'leave', 'log' ) ); ?>" value="<?php echo $prefs['leave']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Group Avatar -->
					<label for="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Group Avatar', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'avatar', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['avatar']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'avatar', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>" value="<?php echo $prefs['avatar']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Group Comment -->
					<label for="<?php echo $this->field_id( array( 'comments', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Group Comment', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'comments', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'comments', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['comments']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_group_comment', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'comments', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'comments', 'log' ) ); ?>" value="<?php echo $prefs['comments']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}
?>