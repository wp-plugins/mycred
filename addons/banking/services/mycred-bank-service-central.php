<?php
/**
 * myCRED Bank Service - Central Bank
 * @since 1.5.2
 * @version 1.0
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

if ( ! class_exists( 'myCRED_Banking_Service_Central' ) ) :
	class myCRED_Banking_Service_Central extends myCRED_Service {

		/**
		 * Construct
		 */
		function __construct( $service_prefs, $type = 'mycred_default' ) {
			parent::__construct( array(
				'id'       => 'central',
				'defaults' => array(
					'bank_id'       => '',
					'ignore_manual' => 1
				)
			), $service_prefs, $type );
		}

		/**
		 * Run
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function run() {

			if ( ! function_exists( 'charge_bank_account' ) )
				add_filter( 'mycred_add', array( $this, 'mycred_add' ), 1, 3 );

		}

		/**
		 * Add
		 * @since 1.5.2
		 * @version 1.0.1
		 */
		public function mycred_add( $reply, $request, $mycred ) {
			// Make sure we are in the correct point type
			if ( $this->mycred_type != $mycred->cred_id || $reply === false ) return $reply;

			// Check manual
			if ( isset( $this->prefs['ignore_manual'] ) && $this->prefs['ignore_manual'] == 0 && $request['ref'] == 'manual' ) return $reply;

			// Instances to ignore
			$ignore = apply_filters( 'mycred_central_banking_ignore', array( 'interest', 'recurring_payout', 'transfer' ), $this );
			if ( in_array( $request['ref'], $ignore ) ) return $reply;

			extract( $request );

			// Make sure that the request is not for our bank account
			if ( $user_id == $this->prefs['bank_id'] ) return $reply;

			// Get the banks balance
			$bank_balance = $mycred->get_users_cred( $this->prefs['bank_id'], $type );

			// User is to lose points
			if ( $amount < 0 ) {
 				// Add the points getting deducted to our bank account
 				$mycred->update_users_balance( $this->prefs['bank_id'], abs( $amount ), $type );

				// Log event
				$mycred->add_to_log( $ref, $this->prefs['bank_id'], abs( $amount ), $entry, $ref_id, $data, $type );
			}

			// User is to gain points
			else {
				// First make sure the bank is not bust
				if ( $bank_balance <= $mycred->zero() ) return false;

				// Second we check if the bank is solvent
				if ( $bank_balance-$amount < $mycred->zero() ) return false;

				// If we have come this far, the bank has sufficient funds so lets deduct
 				$mycred->update_users_balance( $this->prefs['bank_id'], 0-$amount, $type );

				// Log event
				$mycred->add_to_log( $ref, $this->prefs['bank_id'], 0-$amount, $entry, $ref_id, $data, $type );
			}

			// Return the result
			return $reply;

		}

		/**
		 * Preference for Central Bank
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs;
			if ( function_exists( 'charge_bank_account' ) ) : ?>

<p><strong>Warning!</strong>Please remove the charge_bank_account function from your themes functions.php file to use this service!</p>
<?php

			endif;

?>
<label class="subheader"><?php _e( 'Bank User', 'mycred' ); ?></label>
<ol class="inline">
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'bank_id' ); ?>" id="<?php echo $this->field_id( 'bank_id' ); ?>" value="<?php echo $prefs['bank_id']; ?>" size="8" /></div>
		<span class="description"><?php _e( 'The user ID of the central bank account. This user can not be excluded!', 'mycred' ); ?></span>
	</li>
</ol>
<label class="subheader"><?php _e( 'Ignore Manual Adjustments', 'mycred' ); ?></label>
<ol class="inline">
	<li>
		<div class="h2"><input type="checkbox" name="<?php echo $this->field_name( 'ignore_manual' ); ?>" id="<?php echo $this->field_id( 'ignore_manual' ); ?>" value="1"<?php checked( $prefs['ignore_manual'], 1 ); ?> /></div>
	</li>
</ol>

<?php do_action( 'mycred_banking_central', $this ); ?>

<?php
		}

		/**
		 * Sanitise Preferences
		 * @since 1.5.2
		 * @version 1.0
		 */
		function sanitise_preferences( $post ) {

			$new_settings = $post;
			$new_settings['bank_id'] = absint( $post['bank_id'] );
			$new_settings['ignore_manual'] = ( isset( $post['ignore_manual'] ) ) ? $post['ignore_manual'] : 0;
			return apply_filters( 'mycred_banking_save_central', $new_settings, $this );

		}
	}
endif;
?>