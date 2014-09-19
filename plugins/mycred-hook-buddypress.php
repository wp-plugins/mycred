<?php

/**
 * BuddyPress Hooks
 * @since 0.1
 * @version 1.0
 */
if ( defined( 'myCRED_VERSION' ) ) {

	/**
	 * Register Hook
	 * @since 0.1
	 * @version 1.0
	 */
	add_filter( 'mycred_setup_hooks', 'BuddyPress_myCRED_Hook' );
	function BuddyPress_myCRED_Hook( $installed ) {

		if ( bp_is_active( 'xprofile' ) ) {
			$installed['hook_bp_profile'] = array(
				'title'       => __( 'BuddyPress: Members', 'mycred' ),
				'description' => __( 'Awards %_plural% for profile related actions.', 'mycred' ),
				'callback'    => array( 'myCRED_BuddyPress_Profile' )
			);
		}

		if ( bp_is_active( 'groups' ) ) {
			$installed['hook_bp_groups'] = array(
				'title'       => __( 'BuddyPress: Groups', 'mycred' ),
				'description' => __( 'Awards %_plural% for group related actions. Use minus to deduct %_plural% or zero to disable a specific hook.', 'mycred' ),
				'callback'    => array( 'myCRED_BuddyPress_Groups' )
			);
		}

		return $installed;
	}

	/**
	 * myCRED_BuddyPress_Profile class
	 *
	 * Creds for profile updates
	 * @since 0.1
	 * @version 1.1
	 */
	if ( ! class_exists( 'myCRED_BuddyPress_Profile' ) && class_exists( 'myCRED_Hook' ) ) {
		class myCRED_BuddyPress_Profile extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = 'mycred_default' ) {
				parent::__construct( array(
					'id'       => 'hook_bp_profile',
					'defaults' => array(
						'update'         => array(
							'creds'         => 1,
							'log'           => '%plural% for updating profile',
							'daily_limit'   => 2
						),
						'avatar'         => array(
							'creds'         => 1,
							'log'           => '%plural% for new avatar'
						),
						'new_friend'     => array(
							'creds'         => 1,
							'log'           => '%plural% for new friendship'
						),
						'leave_friend'   => array(
							'creds'         => '-1',
							'log'           => '%singular% deduction for loosing a friend'
						),
						'new_comment'    => array(
							'creds'         => 1,
							'log'           => '%plural% for new comment'
						),
						'delete_comment' => array(
							'creds'         => '-1',
							'log'           => '%singular% deduction for comment removal'
						),
						'message'        => array(
							'creds'         => 1,
							'log'           => '%plural% for sending a message'
						),
						'send_gift'      => array(
							'creds'         => 1,
							'log'           => '%plural% for sending a gift'
						)
					)
				), $hook_prefs, $type );
			}

			/**
			 * Run
			 * @since 0.1
			 * @version 1.0
			 */
			public function run() {
				if ( $this->prefs['update']['creds'] != 0 )
					add_action( 'bp_activity_posted_update',          array( $this, 'new_update' ), 20, 3      );

				if ( $this->prefs['avatar']['creds'] != 0 )
					add_action( 'xprofile_avatar_uploaded',           array( $this, 'avatar_upload' )          );

				if ( $this->prefs['new_friend']['creds'] < 0 ) {
					add_action( 'wp_ajax_addremove_friend',           array( $this, 'ajax_addremove_friend' ), 0 );
					add_filter( 'bp_get_add_friend_button',           array( $this, 'disable_friendship' ) );
				}

				if ( $this->prefs['new_friend']['creds'] != 0 )
					add_action( 'friends_friendship_accepted',        array( $this, 'friendship_join' ), 20, 3 );

				if ( $this->prefs['leave_friend']['creds'] != 0 )
					add_action( 'friends_friendship_deleted',         array( $this, 'friendship_leave' ), 20, 3 );

				if ( $this->prefs['new_comment']['creds'] != 0 )
					add_action( 'bp_activity_comment_posted',         array( $this, 'new_comment' ), 20, 2     );

				if ( $this->prefs['delete_comment']['creds'] != 0 )
					add_action( 'bp_activity_action_delete_activity', array( $this, 'delete_comment' ), 20, 2  );

				if ( $this->prefs['message']['creds'] != 0 )
					add_action( 'messages_message_sent',              array( $this, 'messages' )               );

				if ( $this->prefs['send_gift']['creds'] != 0 )
					add_action( 'bp_gifts_send_gifts',                array( $this, 'send_gifts' ), 20, 2      );
			}

			/**
			 * New Profile Update
			 * @since 0.1
			 * @version 1.1
			 */
			public function new_update( $content, $user_id, $activity_id ) {
				// Check if user is excluded
				if ( $this->core->exclude_user( $user_id ) ) return;

				// Limit
				if ( isset( $this->prefs['update']['daily_limit'] ) )
					$max = $this->prefs['update']['daily_limit'];
				else
					$max = 2;
			
				if ( $max > 0 ) {
					$max_earning = $this->core->format_number( $max*$this->prefs['update']['creds'] );
					$earned = mycred_get_total_by_time( 'today', 'now', 'new_profile_update', $user_id, $this->mycred_type );
					if ( $earned >= $max_earning ) return;
				}

				// Make sure this is unique event
				if ( $this->core->has_entry( 'new_profile_update', $activity_id, $user_id ) ) return;

				// Execute
				$this->core->add_creds(
					'new_profile_update',
					$user_id,
					$this->prefs['update']['creds'],
					$this->prefs['update']['log'],
					$activity_id,
					'bp_activity',
					$this->mycred_type
				);
			}

			/**
			 * Avatar Upload
			 * @since 0.1
			 * @version 1.0
			 */
			public function avatar_upload() {
				global $bp;

				// Check if user is excluded
				if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

				// Make sure this is unique event
				if ( $this->core->has_entry( 'upload_avatar', $bp->loggedin_user->id ) ) return;

				// Execute
				$this->core->add_creds(
					'upload_avatar',
					$bp->loggedin_user->id,
					$this->prefs['avatar']['creds'],
					$this->prefs['avatar']['log'],
					0,
					'',
					$this->mycred_type
				);
			}

			/**
			 * AJAX: Add/Remove Friend
			 * Intercept addremovefriend ajax call and block
			 * action if the user can not afford new friendship.
			 * @since 1.5.4
			 * @version 1.0
			 */
			public function ajax_addremove_friend() {
				// Bail if not a POST action
				if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
					return;

				$user_id = bp_loggedin_user_id();
				$balance = $this->core->get_users_balance( $user_id, $this->mycred_type );

				$cost = abs( $this->prefs['new_friend']['creds'] );

				// Take into account any existing requests which will be charged when the new
				// friend approves it. Prevents users from requesting more then they can afford.
				$pending_requests = $this->count_pending_requests( $user_id );
				if ( $pending_requests > 0 )
					$cost = $cost + ( $cost * $pending_requests );

				// Prevent BP from running this ajax call
				if ( $balance < $cost ) {
					echo apply_filters( 'mycred_bp_declined_addfriend', __( 'Insufficient Funds', 'mycred' ), $this );
					exit;
				}
				
				return;
			}

			/**
			 * Disable Friendship
			 * If we deduct points from a user for new friendships
			 * we disable the friendship button if the user ca not afford it.
			 * @since 1.5.4
			 * @version 1.0
			 */
			public function disable_friendship( $button ) {
				// Only applicable for Add Friend button
				if ( $button['id'] == 'not_friends' ) {
					$user_id = bp_loggedin_user_id();
					$balance = $this->core->get_users_balance( $user_id, $this->mycred_type );

					$cost = abs( $this->prefs['new_friend']['creds'] );

					// Take into account any existing requests which will be charged when the new
					// friend approves it. Prevents users from requesting more then they can afford.
					$pending_requests = $this->count_pending_requests( $user_id );
					if ( $pending_requests > 0 )
						$cost = $cost + ( $cost * $pending_requests );

					if ( $balance < $cost )
						return array();
				}

				return $button;
			}

			/**
			 * Count Pending Friendship Requests
			 * Counts the given users pending friendship requests sent to
			 * other users.
			 * @since 1.5.4
			 * @version 1.0
			 */
			protected function count_pending_requests( $user_id ) {
				global $wpdb, $bp;

				return $wpdb->get_var( $wpdb->prepare( "
					SELECT COUNT(*) 
					FROM {$bp->friends->table_name} 
					WHERE initiator_user_id = %d 
					AND is_confirmed = 0;", $user_id ) );

			}

			/**
			 * New Friendship
			 * @since 0.1
			 * @version 1.2
			 */
			public function friendship_join( $friendship_id, $initiator_user_id, $friend_user_id ) {
				// Check if user is excluded
				if ( $this->core->exclude_user( $initiator_user_id ) ) return;

				// Check if friend is excluded
				if ( $this->core->exclude_user( $friend_user_id ) ) return;

				// Make sure this is unique event
				if ( $this->core->has_entry( 'new_friendship', $friend_user_id, $initiator_user_id ) ) return;

				// Points to initiator
				$this->core->add_creds(
					'new_friendship',
					$initiator_user_id,
					$this->prefs['new_friend']['creds'],
					$this->prefs['new_friend']['log'],
					$friend_user_id,
					array( 'ref_type' => 'user' ),
					$this->mycred_type
				);

				// Points to friend (ignored if we are deducting points for new friendships)
				if ( $this->prefs['new_friend']['creds'] > 0 )
					$this->core->add_creds(
						'new_friendship',
						$friend_user_id,
						$this->prefs['new_friend']['creds'],
						$this->prefs['new_friend']['log'],
						$initiator_user_id,
						array( 'ref_type' => 'user' )
					);
			}

			/**
			 * Ending Friendship
			 * @since 0.1
			 * @version 1.1
			 */
			public function friendship_leave( $friendship_id, $initiator_user_id, $friend_user_id ) {
				// Check if user is excluded
				if ( $this->core->exclude_user( $initiator_user_id ) ) return;

				// Check if friend is excluded
				if ( $this->core->exclude_user( $friend_user_id ) ) return;

				// Make sure this is unique event
				if ( $this->core->has_entry( 'ended_friendship', $friend_user_id, $initiator_user_id ) ) return;

				// Deduction to initiator
				$this->core->add_creds(
					'ended_friendship',
					$initiator_user_id,
					$this->prefs['leave_friend']['creds'],
					$this->prefs['leave_friend']['log'],
					$friend_user_id,
					array( 'ref_type' => 'user' ),
					$this->mycred_type
				);
			
				// Deduction to friend
				$this->core->add_creds(
					'ended_friendship',
					$friend_user_id,
					$this->prefs['leave_friend']['creds'],
					$this->prefs['leave_friend']['log'],
					$initiator_user_id,
					array( 'ref_type' => 'user' ),
					$this->mycred_type
				);
			}

			/**
			 * New Comment
			 * @since 0.1
			 * @version 1.0
			 */
			public function new_comment( $comment_id, $params ) {
				global $bp;

				// Check if user is excluded
				if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

				// Make sure this is unique event
				if ( $this->core->has_entry( 'new_comment', $comment_id ) ) return;

				// Execute
				$this->core->add_creds(
					'new_comment',
					$bp->loggedin_user->id,
					$this->prefs['new_comment']['creds'],
					$this->prefs['new_comment']['log'],
					$comment_id,
					'bp_comment',
					$this->mycred_type
				);
			}

			/**
			 * Comment Deletion
			 * @since 0.1
			 * @version 1.0
			 */
			public function delete_comment( $activity_id, $user_id ) {
				// Check if user is excluded
				if ( $this->core->exclude_user( $user_id ) ) return;

				// Make sure this is unique event
				if ( $this->core->has_entry( 'comment_deletion', $activity_id ) ) return;

				// Execute
				$this->core->add_creds(
					'comment_deletion',
					$user_id,
					$this->prefs['delete_comment']['creds'],
					$this->prefs['delete_comment']['log'],
					$activity_id,
					'bp_comment',
					$this->mycred_type
				);
			}

			/**
			 * New Message
			 * @since 0.1
			 * @version 1.0
			 */
			public function messages( $message ) {
				// Check if user is excluded
				if ( $this->core->exclude_user( $message->sender_id ) ) return;

				// Make sure this is unique event
				if ( $this->core->has_entry( 'new_message', $message->thread_id ) ) return;

				// Execute
				$this->core->add_creds(
					'new_message',
					$message->sender_id,
					$this->prefs['message']['creds'],
					$this->prefs['message']['log'],
					$message->thread_id,
					'bp_message',
					$this->mycred_type
				);
			}

			/**
			 * Send Gift
			 * @since 0.1
			 * @version 1.0
			 */
			public function send_gifts( $to_user_id, $from_user_id ) {
				// Check if sender is excluded
				if ( $this->core->exclude_user( $from_user_id ) ) return;

				// Check if recipient is excluded
				if ( $this->core->exclude_user( $to_user_id ) ) return;

				// Make sure this is unique event
				if ( $this->core->has_entry( 'sending_gift', $to_user_id ) ) return;

				// Exclude
				$this->core->add_creds(
					'sending_gift',
					$from_user_id,
					$this->prefs['send_gift']['creds'],
					$this->prefs['send_gift']['log'],
					$to_user_id,
					'bp_gifts',
					$this->mycred_type
				);
			}

			/**
			 * Preferences
			 * @since 0.1
			 * @version 1.0
			 */
			public function preferences() {
				$prefs = $this->prefs; ?>

<!-- Creds for Profile Update -->
<label for="<?php echo $this->field_id( array( 'update', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Profile Updates', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['update']['creds'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'update', 'limit' ) ); ?>"><?php _e( 'Daily Limit', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update', 'daily_limit' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'daily_limit' ) ); ?>" value="<?php echo abs( $prefs['update']['daily_limit'] ); ?>" size="8" /></div>
		<span class="description"><?php _e( 'Daily limit. Use zero for unlimited.', 'mycred' ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'update', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['update']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for New Avatar -->
<label for="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Avatar', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'avatar', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['avatar']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'avatar', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['avatar']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for New Friendships -->
<label for="<?php echo $this->field_id( array( 'new_friend', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Friendships', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_friend', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_friend', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_friend']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_friend', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_friend', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_friend', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['new_friend']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'user' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Leaving Friendships -->
<label for="<?php echo $this->field_id( array( 'leave_friend', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Leaving Friendship', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave_friend', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'leave_friend', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['leave_friend']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'leave_friend', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave_friend', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'leave_friend', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['leave_friend']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'user' ) ); ?></span>
	</li>
</ol>
<!-- Creds for New Comment -->
<label for="<?php echo $this->field_id( array( 'new_comment', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Comment', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_comment', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_comment', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_comment']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_comment', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_comment', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_comment', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['new_comment']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Deleting Comment -->
<label for="<?php echo $this->field_id( array( 'delete_comment', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Deleting Comment', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_comment', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_comment', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['delete_comment']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete_comment', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_comment', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_comment', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['delete_comment']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Sending Messages -->
<label for="<?php echo $this->field_id( array( 'message', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Messages', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'message', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'message', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['message']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'message', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'message', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'message', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['message']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Sending Gifts -->
<label for="<?php echo $this->field_id( array( 'send_gift', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Sending Gift', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_gift', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'send_gift', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['send_gift']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'send_gift', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_gift', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'send_gift', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['send_gift']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<?php
			}
		}
	}

	/**
	 * myCRED_BuddyPress_Groups class
	 *
	 * Creds for groups actions such as joining / leaving, creating / deleting, new topics / edit topics or new posts / edit posts
	 * @since 0.1
	 * @version 1.0
	 */
	if ( !class_exists( 'myCRED_BuddyPress_Groups' ) && class_exists( 'myCRED_Hook' ) ) {
		class myCRED_BuddyPress_Groups extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = 'mycred_default' ) {
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
				), $hook_prefs, $type );
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
					'bp_group',
					$this->mycred_type
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
				$balance = $this->core->get_users_cred( $bp->loggedin_user->id, $this->mycred_type );
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
				$balance = $this->core->get_users_cred( $bp->loggedin_user->id, $this->mycred_type );
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
				if ( $bp->loggedin_user->is_super_admin )
					$user_id = $bp->groups->current_group->creator_id;

				// Else if admin but not the creator is removing
				elseif ( $bp->loggedin_user->id != $bp->groups->current_group->creator_id )
					$user_id = $bp->groups->current_group->creator_id;

				// Else deduct from current user
				else
					$user_id = $bp->loggedin_user->id;

				// Check if user should be excluded
				if ( $this->core->exclude_user( $user_id ) ) return;

				// Execute
				$this->core->add_creds(
					'deletion_of_group',
					$user_id,
					$this->prefs['delete']['creds'],
					$this->prefs['delete']['log'],
					$group_id,
					'bp_group',
					$this->mycred_type
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
					'bp_ftopic',
					$this->mycred_type
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
					'bp_ftopic',
					$this->mycred_type
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
					'bp_fpost',
					$this->mycred_type
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
					'bp_fpost',
					$this->mycred_type
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
					if ( $count == $this->prefs['create']['min'] && ! $this->core->has_entry( 'creation_of_new_group', $group_id, $creator ) )
						$this->core->add_creds(
							'creation_of_new_group',
							$creator,
							$this->prefs['create']['creds'],
							$this->prefs['create']['log'],
							$group_id,
							'bp_group',
							$this->mycred_type
						);

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
					'bp_group',
					$this->mycred_type
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
					'bp_group',
					$this->mycred_type
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
					'bp_group',
					$this->mycred_type
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
					'bp_activity',
					$this->mycred_type
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
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'create', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['create']['creds'] ); ?>" size="8" /></div>
		<span class="description"><?php echo $this->core->template_tags_general( __( 'If you use a negative value and the user does not have enough %_plural% the "Create Group" button will be disabled.', 'mycred' ) ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'create', 'min' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Number of members before awarding %_plural%', 'mycred' ) ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'create', 'min' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'min' ) ); ?>" value="<?php echo $this->core->number( $prefs['create']['min'] ); ?>" size="8" /></div>
		<span class="description"><?php echo $this->core->template_tags_general( __( 'Use zero to award %_plural% when group is created.', 'mycred' ) ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'create', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'create', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['create']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Deleting Group -->
<label for="<?php echo $this->field_id( array( 'delete', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Deleting Groups', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['delete']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['delete']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for New Forum Topic -->
<label for="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Forum Topic', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_topic']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['new_topic']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Edit Forum Topic -->
<label for="<?php echo $this->field_id( array( 'edit_topic', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Editing Forum Topic', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'edit_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_topic', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['edit_topic']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['new_topic']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for New Forum Post -->
<label for="<?php echo $this->field_id( array( 'new_post', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Forum Post', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_post', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_post', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_post']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_post', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_post', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_post', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['new_post']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Edit Forum Post -->
<label for="<?php echo $this->field_id( array( 'edit_post', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Editing Forum Post', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'edit_post', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_post', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['edit_post']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'edit_post', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'edit_post', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_post', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['edit_post']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Joining Group -->
<label for="<?php echo $this->field_id( array( 'join', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Joining Groups', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'join', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'join', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['join']['creds'] ); ?>" size="8" /></div>
		<span class="description"><?php echo $this->core->template_tags_general( __( 'If you use a negative value and the user does not have enough %_plural% the "Join Group" button will be disabled.', 'mycred' ) ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'join', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'join', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'join', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['join']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for Leaving Group -->
<label for="<?php echo $this->field_id( array( 'leave', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Leaving Groups', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'leave', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['leave']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'leave', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'leave', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['leave']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for New Group Avatar -->
<label for="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Group Avatar', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'avatar', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['avatar']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'avatar', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['avatar']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<!-- Creds for New Group Comment -->
<label for="<?php echo $this->field_id( array( 'comments', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Group Comment', 'mycred' ) ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'comments', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'comments', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['comments']['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_group_comment', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'comments', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'comments', 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['comments']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<?php
			}
		}
	}

}
?>