<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_BuddyPress_bbPress class
 *
 * Creds for bbPress 2.0
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_BuddyPress_bbPress' ) ) {
	class myCRED_BuddyPress_bbPress extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'hook_bp_bbpress',
				'defaults' => array(
					'new_topic' => array(
						'creds'    => 1,
						'log'      => '%plural% for new forum topic'
					),
					'new_reply' => array(
						'creds'    => 1,
						'log'      => '%plural% for new forum reply'
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
			if ( $this->prefs['new_topic']['creds'] != 0 )
				add_action( 'bbp_new_topic', array( $this, 'new_topic' ), 20, 4 );

			if ( $this->prefs['new_reply']['creds'] != 0 )
				add_action( 'bbp_new_reply', array( $this, 'new_reply' ), 20, 5 );
		}

		/**
		 * New Topic
		 * @since 0.1
		 * @version 1.0
		 */
		public function new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {
			// Check if user is excluded
			if ( $this->core->exclude_user( $topic_author ) ) return;

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

			// Clean up
			unset( $this );
		}

		/**
		 * New Reply
		 * @since 0.1
		 * @version 1.0
		 */
		public function new_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
			// Check if user is excluded
			if ( $this->core->exclude_user( $reply_author ) ) return;

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
					</ol>
<?php		unset( $this );
		}
	}
}
?>