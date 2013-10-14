<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_BuddyPress_Links class
 *
 * Creds for new links, voting on links, updating links and deleting links
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_BuddyPress_Links' ) ) {
	class myCRED_BuddyPress_Links extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'hook_bp_links',
				'defaults' => array(
					'new_link'    => array(
						'creds'      => 1,
						'log'        => '%plural% for new Link'
					),
					'vote_link'   => array(
						'creds'      => 1,
						'log'        => '%plural% for voting on a link'
					),
					'update_link' => array(
						'creds'      => 1,
						'log'        => '%plural% for updating link'
					),
					'delete_link' => array(
						'creds'      => '-1',
						'log'        => '%singular% deduction for deleting a link'
					),
				)
			), $hook_prefs );
		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {
			if ( $this->prefs['new_link']['creds'] != 0 )
				add_action( 'bp_links_create_complete',   array( $this, 'create_link' )        );

			if ( $this->prefs['vote_link']['creds'] != 0 )
				add_action( 'bp_links_cast_vote_success', array( $this, 'vote_link' )          );

			if ( $this->prefs['update_link']['creds'] != 0 )
				add_action( 'bp_links_posted_update',     array( $this, 'update_link' ), 20, 4 );

			if ( $this->prefs['delete_link']['creds'] != 0 )
				add_action( 'bp_links_delete_link',       array( $this, 'delete_link' )        );
		}

		/**
		 * New Link
		 * @since 0.1
		 * @version 1.0
		 */
		public function create_link( $link_id ) {
			global $bp;

			// Check if user is excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_link', $link_id, $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_link',
				$bp->loggedin_user->id,
				$this->prefs['new_link']['creds'],
				$this->prefs['new_link']['log'],
				$link_id,
				'bp_links'
			);
		}

		/**
		 * Vote on Link
		 * @since 0.1
		 * @version 1.0
		 */
		public function vote_link( $link_id ) {
			global $bp;

			// Check if user is excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'link_voting', $link_id, $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'link_voting',
				$bp->loggedin_user->id,
				$this->prefs['vote_link']['creds'],
				$this->prefs['vote_link']['log'],
				$link_id,
				'bp_links'
			);
		}

		/**
		 * Update Link
		 * @since 0.1
		 * @version 1.0
		 */
		public function update_link( $content, $user_id, $link_id, $activity_id ) {
			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'update_link', $activity_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'update_link',
				$user_id,
				$this->prefs['update_link']['creds'],
				$this->prefs['update_link']['log'],
				$activity_id,
				'bp_links'
			);
		}

		/**
		 * Delete Link
		 * @since 0.1
		 * @version 1.0
		 */
		public function delete_link( $link_id ) {
			global $bp;

			// Check if user is excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'link_deletion', $link_id, $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'link_deletion',
				$bp->loggedin_user->id,
				$this->prefs['delete_link']['creds'],
				$this->prefs['delete_link']['log'],
				$link_id,
				'bp_links'
			);
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<!-- Creds for New Link -->
					<label for="<?php echo $this->field_id( array( 'new_link', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Links', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_link', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_link', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_link']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_link', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_link', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_link', 'log' ) ); ?>" value="<?php echo $prefs['new_link']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Vote Link -->
					<label for="<?php echo $this->field_id( array( 'vote_link', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Vote on Link', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'vote_link', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'vote_link', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['vote_link']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'vote_link', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'vote_link', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'vote_link', 'log' ) ); ?>" value="<?php echo $prefs['vote_link']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Update Link -->
					<label for="<?php echo $this->field_id( array( 'update_link', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Updating Links', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update_link', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'update_link', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['update_link']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'update_link', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'update_link', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'update_link', 'log' ) ); ?>" value="<?php echo $prefs['update_link']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<!-- Creds for Deleting Links -->
					<label for="<?php echo $this->field_id( array( 'delete_link', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Deleting Links', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_link', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_link', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['delete_link']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'delete_link', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_link', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_link', 'log' ) ); ?>" value="<?php echo $prefs['delete_link']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}
?>