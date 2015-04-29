<?php

/**
 * rtMedia
 * @since 1.4
 * @version 1.0.2
 */
if ( defined( 'myCRED_VERSION' ) ) {

	/**
	 * Register Hook
	 * @since 1.4
	 * @version 1.0
	 */
	add_filter( 'mycred_setup_hooks', 'rtMedia_myCRED_Hook' );
	function rtMedia_myCRED_Hook( $installed ) {
		$installed['rtmedia'] = array(
			'title'       => __( 'rtMedia Galleries', 'mycred' ),
			'description' => __( 'Award / Deduct %_plural% for users creating albums or uploading new photos.', 'mycred' ),
			'callback'    => array( 'myCRED_rtMedia' )
		);
		return $installed;
	}

	/**
	 * rtMedia Hook
	 * @since 1.4
	 * @version 1.0
	 */
	if ( ! class_exists( 'myCRED_rtMedia' ) && class_exists( 'myCRED_Hook' ) ) {
		class myCRED_rtMedia extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = 'mycred_default' ) {
				parent::__construct( array(
					'id'       => 'rtmedia',
					'defaults' => array(
						'new_media'      => array(
							'photo'          => 0,
							'photo_log'      => '%plural% for new photo',
							'photo_limit'    => '0/x',
							'video'          => 0,
							'video_log'      => '%plural% for new video',
							'video_limit'    => '0/x',
							'music'          => 0,
							'music_log'      => '%plural% for new music',
							'music_limit'    => '0/x',
						),
						'delete_media'   => array(
							'photo'          => 0,
							'photo_log'      => '%plural% for deleting photo',
							'video'          => 0,
							'video_log'      => '%plural% for deleting video',
							'music'          => 0,
							'music_log'      => '%plural% for deleting music'
						)
					)
				), $hook_prefs, $type );
			}

			/**
			 * Run
			 * @since 1.4
			 * @version 1.0
			 */
			public function run() {
				add_action( 'rtmedia_after_add_media',     array( $this, 'new_media' ), 10, 3 );
				add_action( 'rtmedia_before_delete_media', array( $this, 'delete_media' ) );
			}

			/**
			 * New Media
			 * @since 1.4
			 * @version 1.1
			 */
			public function new_media( $media_ids, $file_object, $uploaded ) {
				// Check for exclusion
				if ( $this->core->exclude_user( $uploaded['media_author'] ) === true ) return;

				foreach ( $media_ids as $id ) {

					// Get media details from id
					$model = new RTMediaModel();
					$media = $model->get_media( array( 'id' => $id ), 0, 1 );
					if ( ! isset( $media[0]->media_type ) ) continue;

					// If this media type awards zero, bail
					if ( $this->prefs['new_media'][ $media[0]->media_type ] == $this->core->zero() ) continue;

					// Limit
					if ( $this->over_hook_limit( $media[0]->media_type, $media[0]->media_type . '_upload', $uploaded['media_author'] ) ) continue;

					// Make sure this is unique
					if ( $this->core->has_entry( $media[0]->media_type . '_upload', $media[0]->media_author, $id ) ) continue;

					// Execute
					$this->core->add_creds(
						$media[0]->media_type . '_upload',
						$media[0]->media_author,
						$this->prefs['new_media'][ $media[0]->media_type ],
						$this->prefs['new_media'][ $media[0]->media_type . '_log' ],
						$id,
						array( 'ref_type' => 'media', 'attachment_id' => $media[0]->media_id ),
						$this->mycred_type
					);

				}
			}

			/**
			 * Delete Media
			 * @since 1.4
			 * @version 1.0.1
			 */
			public function delete_media( $media_id ) {
				// Get media details from id
				$model = new RTMediaModel();
				$media = $model->get_media( array( 'id' => $id ), 0, 1 );
				if ( ! isset( $media[0]->media_type ) ) return;

				// If this media type awards zero, bail
				if ( $this->prefs['delete_media'][ $media[0]->media_type ] == $this->core->zero() ) return;

				// Check for exclusion
				if ( $this->core->exclude_user( $media->media_author ) === true ) return;

				// Only deduct if user gained points for this
				if ( $this->core->has_entry( $media[0]->media_type . '_upload', $media[0]->media_author, $media_id ) ) {

					// Execute
					$this->core->add_creds(
						$media[0]->media_type . '_deletion',
						$media[0]->media_author,
						$this->prefs['delete_media'][ $media[0]->media_type ],
						$this->prefs['delete_media'][ $media[0]->media_type . '_log' ],
						$media_id,
						array( 'ref_type' => 'media', 'attachment_id' => $media[0]->media_id ),
						$this->mycred_type
					);

				}

			}

			/**
			 * Adjust Limit Name
			 * @since 1.6
			 * @version 1.0
			 */
			public function hook_limit_name( $name ) {

				$name = str_replace( '[photo_limit]', '[photo_limit_by]', $name );
				$name = str_replace( '[video_limit]', '[video_limit_by]', $name );
				$name = str_replace( '[music_limit]', '[music_limit_by]', $name );
				return $name;

			}

			/**
			 * Adjust Limit ID
			 * @since 1.6
			 * @version 1.0
			 */
			public function hook_limit_id( $id ) {

				$id = str_replace( 'photo-limit', 'photo-limit-by', $id );
				$id = str_replace( 'video-limit', 'video-limit-by', $id );
				$id = str_replace( 'music-limit', 'music-limit-by', $id );
				return $id;

			}

			/**
			 * Preferences for rtMedia Gallery Hook
			 * @since 1.4
			 * @version 1.0
			 */
			public function preferences() {
				$prefs = $this->prefs;
				
				global $rtmedia;
				
				$photos = ' readonly="readonly"';
				if ( array_key_exists( 'allowedTypes_photo_enabled', $rtmedia->options ) && $rtmedia->options['allowedTypes_photo_enabled'] == 1 )
					$photos = '';
				
				$videos = ' readonly="readonly"';
				if ( array_key_exists( 'allowedTypes_video_enabled', $rtmedia->options ) && $rtmedia->options['allowedTypes_video_enabled'] == 1 )
					$videos = '';
				
				$music = ' readonly="readonly"';
				if ( array_key_exists( 'allowedTypes_music_enabled', $rtmedia->options ) && $rtmedia->options['allowedTypes_music_enabled'] == 1 )
					$music = '';

				add_filter( 'mycred_hook_limit_name_by', array( $this, 'hook_limit_name' ) );
				add_filter( 'mycred_hook_limit_id_by',   array( $this, 'hook_limit_id' ) );

?>

<label class="subheader"><?php _e( 'New Media Upload', 'mycred' ); ?></label>
<ol>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'photo' ) ); ?>"><?php _e( 'Photo Upload', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_media', 'photo' ) ); ?>" id="<?php echo $this->field_id( array( 'new_media', 'photo' ) ); ?>"<?php echo $photos; ?> value="<?php echo $this->core->number( $prefs['new_media']['photo'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'photo_limit' ) ); ?>"><?php _e( 'Limit', 'mycred' ); ?></label>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_media', 'photo_limit' ) ), $this->field_id( array( 'new_media', 'photo_limit' ) ), $prefs['new_media']['photo_limit'] ); ?>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'photo_log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_media', 'photo_log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_media', 'photo_log' ) ); ?>"<?php echo $photos; ?> value="<?php echo esc_attr( $prefs['new_media']['photo_log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'video' ) ); ?>"><?php _e( 'Video Upload', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_media', 'video' ) ); ?>" id="<?php echo $this->field_id( array( 'new_media', 'video' ) ); ?>"<?php echo $videos; ?> value="<?php echo $this->core->number( $prefs['new_media']['video'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'video_limit' ) ); ?>"><?php _e( 'Limit', 'mycred' ); ?></label>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_media', 'video_limit' ) ), $this->field_id( array( 'new_media', 'video_limit' ) ), $prefs['new_media']['video_limit'] ); ?>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'video_log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_media', 'video_log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_media', 'video_log' ) ); ?>"<?php echo $videos; ?> value="<?php echo esc_attr( $prefs['new_media']['video_log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'music' ) ); ?>"><?php _e( 'Music Upload', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_media', 'music' ) ); ?>" id="<?php echo $this->field_id( array( 'new_media', 'music' ) ); ?>"<?php echo $music; ?> value="<?php echo $this->core->number( $prefs['new_media']['music'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'music_limit' ) ); ?>"><?php _e( 'Limit', 'mycred' ); ?></label>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_media', 'music_limit' ) ), $this->field_id( array( 'new_media', 'music_limit' ) ), $prefs['new_media']['music_limit'] ); ?>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'new_media', 'music_log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'new_media', 'music_log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_media', 'music_log' ) ); ?>"<?php echo $music; ?> value="<?php echo esc_attr( $prefs['new_media']['music_log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>

<label for="<?php echo $this->field_id( array( 'delete_media', 'creds' ) ); ?>" class="subheader"><?php _e( 'Delete Media', 'mycred' ); ?></label>
<ol>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete_media', 'photo' ) ); ?>"><?php _e( 'Delete Photo', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_media', 'photo' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_media', 'photo' ) ); ?>"<?php echo $photos; ?> value="<?php echo $this->core->number( $prefs['delete_media']['photo'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete_media', 'photo_log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_media', 'photo_log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_media', 'photo_log' ) ); ?>"<?php echo $photos; ?> value="<?php echo esc_attr( $prefs['delete_media']['photo_log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete_media', 'video' ) ); ?>"><?php _e( 'Delete Video', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_media', 'video' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_media', 'video' ) ); ?>"<?php echo $videos; ?> value="<?php echo $this->core->number( $prefs['delete_media']['video'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete_media', 'video_log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_media', 'video_log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_media', 'video_log' ) ); ?>"<?php echo $videos; ?> value="<?php echo esc_attr( $prefs['delete_media']['video_log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete_media', 'music' ) ); ?>"><?php _e( 'Delete Music', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_media', 'music' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_media', 'music' ) ); ?>"<?php echo $music; ?> value="<?php echo $this->core->number( $prefs['delete_media']['music'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'delete_media', 'music_log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'delete_media', 'music_log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_media', 'music_log' ) ); ?>"<?php echo $music; ?> value="<?php echo esc_attr( $prefs['delete_media']['music_log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<?php
			}
			
			/**
			 * Sanitise Preferences
			 * @since 1.6
			 * @version 1.0
			 */
			function sanitise_preferences( $data ) {

				if ( isset( $data['new_media']['photo_limit'] ) && isset( $data['new_media']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['new_media']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['new_media']['photo_limit'] = $limit . '/' . $data['new_media']['limit_by'];
					unset( $data['new_media']['limit_by'] );
				}

				if ( isset( $data['new_media']['limit'] ) && isset( $data['new_media']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['new_media']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['new_media']['limit'] = $limit . '/' . $data['new_media']['limit_by'];
					unset( $data['new_media']['limit_by'] );
				}

				if ( isset( $data['new_media']['limit'] ) && isset( $data['new_media']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['new_media']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['new_media']['limit'] = $limit . '/' . $data['new_media']['limit_by'];
					unset( $data['new_media']['limit_by'] );
				}

				return $data;

			}
		}
	}
}
?>