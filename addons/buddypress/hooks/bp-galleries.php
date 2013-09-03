<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_BuddyPress_Gallery class
 *
 * Creds for creating a gallery or deleting gallery
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_BuddyPress_Gallery' ) ) {
	class myCRED_BuddyPress_Gallery extends myCRED_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs ) {
			parent::__construct( array(
				'id'       => 'hook_bp_gallery',
				'defaults' => array(
					'new_gallery' => array(
						'creds'      => 1,
						'log'        => '%plural% for new gallery'
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
			if ( $this->prefs['new_gallery']['creds'] != 0 ) {
				add_action( 'bp_gallplus_data_after_save', array( $this, 'new_gallery' ) );
				add_action( 'bp_album_data_after_save',    array( $this, 'new_gallery' ) );
			}
		}

		/**
		 * New Gallery
		 * @since 0.1
		 * @version 1.0
		 */
		public function new_gallery( $gallery ) {
			// Check if user is excluded
			if ( $this->core->exclude_user( $gallery->owner_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_buddypress_gallery', $gallery->id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_buddypress_gallery',
				$gallery->owner_id,
				$this->prefs['new_gallery']['creds'],
				$this->prefs['new_gallery']['log'],
				$gallery->id,
				'bp_gallery'
			);
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<!-- Creds for New Gallery -->
					<label for="<?php echo $this->field_id( array( 'new_gallery', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for New Gallery', 'mycred' ) ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_gallery', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_gallery', 'creds' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['new_gallery']['creds'] ); ?>" size="8" /></div>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'new_gallery', 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_gallery', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_gallery', 'log' ) ); ?>" value="<?php echo $prefs['new_gallery']['log']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
<?php		unset( $this );
		}
	}
}
?>