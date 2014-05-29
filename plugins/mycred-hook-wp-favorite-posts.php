<?php

/**
 * WP Favorite Posts
 * @since 1.1
 * @version 1.1
 */
if ( defined( 'myCRED_VERSION' ) ) {

	/**
	 * Register Hook
	 * @since 1.1
	 * @version 1.0
	 */
	add_filter( 'mycred_setup_hooks', 'WP_Favorite_myCRED_Hook' );
	function WP_Favorite_myCRED_Hook( $installed ) {
		$installed['wpfavorite'] = array(
			'title'       => __( 'WP Favorite Posts', 'mycred' ),
			'description' => __( 'Awards %_plural% for users adding posts to their favorites.', 'mycred' ),
			'callback'    => array( 'myCRED_Hook_WPFavorite' )
		);
		return $installed;
	}

	/**
	 * WP Favorite Hook
	 * @since 1.1
	 * @version 1.1
	 */
	if ( ! class_exists( 'myCRED_Hook_WPFavorite' ) && class_exists( 'myCRED_Hook' ) ) {
		class myCRED_Hook_WPFavorite extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = 'mycred_default' ) {
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
				), $hook_prefs, $type );
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
			 * @version 1.1
			 */
			public function add_favorite( $post_id ) {
				// Must be logged in
				if ( ! is_user_logged_in() ) return;

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
					array( 'ref_type' => 'post' ),
					$this->mycred_type
				);
			}

			/**
			 * Remove Favorite
			 * @since 1.1
			 * @version 1.1
			 */
			public function remove_favorite( $post_id ) {
				// Must be logged in
				if ( ! is_user_logged_in() ) return;

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
					array( 'ref_type' => 'post' ),
					$this->mycred_type
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
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'add' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'add' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['add']['creds'] ); ?>" size="8" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'add' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'add' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'add' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['add']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'remove' => 'creds' ) ); ?>"><?php _e( 'Removing Content from Favorites', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'remove' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'remove' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['remove']['creds'] ); ?>" size="8" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'remove' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'remove' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'remove' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['remove']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
	</li>
</ol>
<?php
			}
		}
	}
}
?>