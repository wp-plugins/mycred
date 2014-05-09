<?php

/**
 * Gravity Forms
 * @since 1.4
 * @version 1.0
 */
if ( defined( 'myCRED_VERSION' ) ) {

	/**
	 * Register Hook
	 * @since 1.4
	 * @version 1.0
	 */
	add_filter( 'mycred_setup_hooks', 'gravity_forms_myCRED_Hook' );
	function gravity_forms_myCRED_Hook( $installed ) {
		$installed['gravityform'] = array(
			'title'       => __( 'Gravityform Submissions', 'mycred' ),
			'description' => __( 'Awards %_plural% for successful form submissions.', 'mycred' ),
			'callback'    => array( 'myCRED_Gravity_Forms' )
		);
		return $installed;
	}

	/**
	 * Gravity Forms Hook
	 * @since 1.4
	 * @version 1.0
	 */
	if ( ! class_exists( 'myCRED_Gravity_Forms' ) && class_exists( 'myCRED_Hook' ) ) {
		class myCRED_Gravity_Forms extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = 'mycred_default' ) {
				parent::__construct( array(
					'id'       => 'gravityform',
					'defaults' => array()
				), $hook_prefs, $type );
			}

			/**
			 * Run
			 * @since 1.4
			 * @version 1.0
			 */
			public function run() {
				add_action( 'gform_after_submission', array( $this, 'form_submission' ), 10, 2 );
			}

			/**
			 * Successful Form Submission
			 * @since 1.4
			 * @version 1.0
			 */
			public function form_submission( $lead, $form ) {
				// Login is required
				if ( ! is_user_logged_in() ) return;

				$form_id = $form['id'];
				if ( ! isset( $this->prefs[ $form['id'] ] ) || ! $this->prefs[ $form['id'] ]['creds'] != 0 ) return;

				$this->core->add_creds(
					'gravity_form_submission',
					get_current_user_id(),
					$this->prefs[ $form['id'] ]['creds'],
					$this->prefs[ $form['id'] ]['log'],
					$form['id'],
					'',
					$this->mycred_type
				);
			}

			/**
			 * Preferences for Gravityforms Hook
			 * @since 1.4
			 * @version 1.0
			 */
			public function preferences() {
				$prefs = $this->prefs;
				$forms = RGFormsModel::get_forms();

				// No forms found
				if ( empty( $forms ) ) {
					echo '<p>' . __( 'No forms found.', 'mycred' ) . '</p>';
					return;
				}

				// Loop though prefs to make sure we always have a default setting
				foreach ( $forms as $form ) {
					if ( ! isset( $prefs[ $form->id ] ) ) {
						$prefs[ $form->id ] = array(
							'creds' => 1,
							'log'   => ''
						);
					}
				}

				// Set pref if empty
				if ( empty( $prefs ) ) $this->prefs = $prefs;

				// Loop for settings
				foreach ( $forms as $form ) { ?>

<label for="<?php echo $this->field_id( array( $form->id, 'creds' ) ); ?>" class="subheader"><?php echo $form->title; ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $form->id, 'creds' ) ); ?>" id="<?php echo $this->field_id( array( $form->id, 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs[ $form->id ]['creds'] ); ?>" size="8" /></div>
	</li>
	<li class="empty">&nbsp;</li>
	<li>
		<label for="<?php echo $this->field_id( array( $form->id, 'log' ) ); ?>"><?php _e( 'Log template', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $form->id, 'log' ) ); ?>" id="<?php echo $this->field_id( array( $form->id, 'log' ) ); ?>" value="<?php echo esc_attr( $prefs[ $form->id ]['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<?php			}
			}
		}
	}
}
?>