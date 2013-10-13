<?php
/**
 * Invite Anyone Plugin
 * @since 0.1
 * @version 1.0
 */
if ( defined( 'myCRED_VERSION' ) ) {
	/**
	 * Register Hook
	 * @since 0.1
	 * @version 1.0
	 */
	add_filter( 'mycred_setup_hooks', 'invite_anyone_myCRED_Hook' );
	function invite_anyone_myCRED_Hook( $installed ) {
		$installed['invite_anyone'] = array(
			'title'       => __( 'Invite Anyone Plugin', 'mycred' ),
			'description' => __( 'Awards %_plural% for sending invitations and/or %_plural% if the invite is accepted.', 'mycred' ),
			'callback'    => array( 'myCRED_Invite_Anyone' )
		);
		return $installed;
	}

	/**
	 * Invite Anyone Hook
	 * @since 0.1
	 * @version 1.0
	 */
	if ( !class_exists( 'myCRED_Invite_Anyone' ) && class_exists( 'myCRED_Hook' ) ) {
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
				foreach ( (array) $inviters as $inviter_id ) {
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
			 * @version 1.0.1
			 */
			public function preferences() {
				$prefs = $this->prefs; ?>

					<!-- Creds for Sending Invites -->
					<label for="<?php echo $this->field_id( array( 'send_invite', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Sending An Invite', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'send_invite', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'send_invite', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['send_invite']['creds'] ); ?>" size="8" /></div>
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
							<span class="description"><?php echo $this->core->template_tags_general( __( 'Maximum number of invites that grants %_plural%. Use zero for unlimited.', 'mycred' ) ); ?></span>
						</li>
					</ol>
					<!-- Creds for Accepting Invites -->
					<label for="<?php echo $this->field_id( array( 'accept_invite', 'creds' ) ); ?>" class="subheader"><?php echo $this->core->template_tags_general( __( '%plural% for Accepting An Invite', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'accept_invite', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'accept_invite', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['accept_invite']['creds'] ); ?>" size="8" /></div>
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
							<span class="description"><?php echo $this->core->template_tags_general( __( 'Maximum number of accepted invitations that grants %_plural%. Use zero for unlimited.', 'mycred' ) ); ?></span>
						</li>
					</ol>
<?php			unset( $this );
			}
		}
	}
}
?>