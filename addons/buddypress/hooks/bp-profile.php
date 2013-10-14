<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_BuddyPress_Profile class
 *
 * Creds for profile updates
 * @since 0.1
 * @version 1.1
 */
if ( !class_exists( 'myCRED_BuddyPress_Profile' ) ) {
	class myCRED_BuddyPress_Profile extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
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
			), $hook_prefs );
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

			if ( $this->prefs['new_friend']['creds'] != 0 )
				add_action( 'friends_friendship_accepted',        array( $this, 'friendship_join' ), 20, 3 );

			if ( $this->prefs['leave_friend']['creds'] != 0 )
				add_action( 'friends_friendship_deleted',         array( $this, 'friendship_leave' )       );

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
				$earned = mycred_get_total_by_time( 'today', 'now', 'new_profile_update', $user_id );
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
				'bp_activity'
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
				$this->prefs['avatar']['log']
			);
		}

		/**
		 * New Friendship
		 * @since 0.1
		 * @version 1.1
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
				array( 'ref_type' => 'user' )
			);

			// Points to friend
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
				array( 'ref_type' => 'user' )
			);
			
			// Deduction to friend
			$this->core->add_creds(
				'ended_friendship',
				$friend_user_id,
				$this->prefs['leave_friend']['creds'],
				$this->prefs['leave_friend']['log'],
				$initiator_user_id,
				array( 'ref_type' => 'user' )
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
				'bp_comment'
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
				'bp_comment'
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
				'bp_message'
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
				'bp_gifts'
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
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['update']['creds'] ); ?>" size="8" /></div>
						</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'update', 'limit' ) ); ?>"><?php _e( 'Daily Limit', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update', 'daily_limit' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'daily_limit' ) ); ?>" value="<?php echo abs( $prefs['update']['daily_limit'] ); ?>" size="8" /></div>
							<span class="description"><?php _e( 'Daily limit. User zero for unlimited.', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'update', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'log' ) ); ?>" value="<?php echo $prefs['update']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Avatar -->
					<label for="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Avatar', 'mycred' ) ); ?></label>
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
					<!-- Creds for New Friendships -->
					<label for="<?php echo $this->field_id( array( 'new_friend', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Friendships', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_friend', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_friend', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_friend']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_friend', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_friend', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_friend', 'log' ) ); ?>" value="<?php echo $prefs['new_friend']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, User', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Leaving Friendships -->
					<label for="<?php echo $this->field_id( array( 'leave_friend', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Leaving Friendship', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave_friend', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'leave_friend', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['leave_friend']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'leave_friend', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'leave_friend', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'leave_friend', 'log' ) ); ?>" value="<?php echo $prefs['leave_friend']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General, User', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for New Comment -->
					<label for="<?php echo $this->field_id( array( 'new_comment', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Comment', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_comment', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_comment', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_comment']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_comment', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_comment', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_comment', 'log' ) ); ?>" value="<?php echo $prefs['new_comment']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Deleting Comment -->
					<label for="<?php echo $this->field_id( array( 'delete_comment', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Deleting Comment', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_comment', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_comment', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['delete_comment']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'delete_comment', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_comment', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_comment', 'log' ) ); ?>" value="<?php echo $prefs['delete_comment']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Sending Messages -->
					<label for="<?php echo $this->field_id( array( 'message', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Messages', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'message', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'message', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['message']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'message', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'message', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'message', 'log' ) ); ?>" value="<?php echo $prefs['message']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Sending Gifts -->
					<label for="<?php echo $this->field_id( array( 'send_gift', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Sending Gift', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_gift', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'send_gift', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['send_gift']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'send_gift', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_gift', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'send_gift', 'log' ) ); ?>" value="<?php echo $prefs['send_gift']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}
?>