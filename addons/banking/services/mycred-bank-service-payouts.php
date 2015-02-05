<?php
/**
 * myCRED Bank Service - Recurring Payouts
 * @since 1.2
 * @version 1.2
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

if ( ! class_exists( 'myCRED_Banking_Service_Payouts' ) ) :
	class myCRED_Banking_Service_Payouts extends myCRED_Service {

		/**
		 * Construct
		 */
		function __construct( $service_prefs, $type = 'mycred_default' ) {
			parent::__construct( array(
				'id'       => 'payouts',
				'defaults' => array(
					'amount'        => 10,
					'rate'          => 'daily',
					'log'           => __( 'Daily %_plural%', 'mycred' ),
					'cycles'        => 0,
					'min_balance'   => 1,
					'exclude_ids'   => '',
					'exclude_roles' => array()
				)
			), $service_prefs, $type );

			add_action( 'mycred_bank_recurring_pay' . $this->mycred_type, array( $this, 'do_masspayout' ) );
		}

		/**
		 * Activate Service
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function activate() {

			$this->save_last_run( 'masspayout' );

		}

		/**
		 * Deactivation
		 * @since 1.2
		 * @version 1.0.1
		 */
		public function deactivate() {

			// Unschedule payouts
			wp_clear_scheduled_hook( 'mycred_bank_recurring_pay' . $this->mycred_type );

		}

		/**
		 * Run
		 * @since 1.2
		 * @version 1.1
		 */
		public function run() {

			// Max Cycles
			if ( $this->prefs['cycles'] == 0 ) return;

			// Time to Payout?
			$time_to_payout = $this->time_to_run( $this->prefs['rate'], $this->get_last_run( 'masspayout' ) );
			if ( $time_to_payout ) {

				// Get Work
				$option_id = 'mycred_bank_recurring_pay' . $this->mycred_type;
				$work_marker = 'MYCRED_BANK_RECPAY_' . $this->mycred_type;

				$eligeble_users = $this->get_eligeble_users();

				// Work to do?
				if ( ! empty( $eligeble_users ) ) {

					// Get current workload
					$current_work = mycred_get_option( $option_id, false );

					// Currently working?
					if ( ! defined( $work_marker ) ) {

						// Cron is scheduled?
						if ( wp_next_scheduled( $option_id ) === false ) {
							if ( $current_work === false )
								mycred_update_option( $option_id, $eligeble_users );

							wp_schedule_single_event( time(), $option_id );
						}

						// Work left to do?
						elseif ( $current_work === false ) {

							// Mark completed
							$this->update_run_count( 'masspayout' );
							$this->save_last_run( 'masspayout' );
							$this->update_cycles();

						}

					}

				}
				else {

					// Mark completed
					$this->update_run_count( 'masspayout' );
					$this->save_last_run( 'masspayout' );
					$this->update_cycles();

				}

			}

		}

		/**
		 * Do Payout
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function do_masspayout() {

			$work_marker = 'MYCRED_BANK_RECPAY_' . $this->mycred_type;
			if ( ! defined( $work_marker ) )
				define( $work_marker, time() );

			$option_id = 'mycred_bank_recurring_pay' . $this->mycred_type;
			$current_work = mycred_get_option( $option_id, false );
			if ( $current_work === false ) {
				$this->update_run_count( 'masspayout' );
				$this->save_last_run( 'masspayout' );
				$this->update_cycles();
				return;
			}

			$now = date_i18n( 'U' );
			$work = $current_work;
			foreach ( $current_work as $row => $user_id ) {

				// Add a unique Payout ID
				$data = array( 'payout_id' => $now . $user_id );

				// Prevent duplicates
				if ( ! $this->core->has_entry( 'interest', 0, $user_id, $data, $this->mycred_type ) )
					$this->core->add_creds(
						'recurring_payout',
						$user_id,
						$this->prefs['amount'],
						$this->prefs['log'],
						0,
						$data,
						$this->mycred_type
					);

				// Remove from workload
				unset( $work[ $row ] );

				if ( ! empty( $work ) )
					mycred_update_option( $option_id, $work );
				else {
					mycred_delete_option( $option_id );
					$this->update_run_count( 'masspayout' );
					$this->save_last_run( 'masspayout' );
					$this->update_cycles();
				}

			}

		}

		/**
		 * Update Cycles
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function update_cycles() {

			// Manage cycles if it's not set to unlimited
			if ( $this->prefs['cycles'] != '-1' && $this->prefs['cycles'] != 0 ) {

				// Prep option id for this addon.
				$option_id = 'mycred_pref_bank';
				if ( ! $this->is_main_type )
					$option_id .= '_' . $this->mycred_type;

				// Get addon settings
				$addon = mycred_get_option( $option_id );

				// Deduct cycle
				$current_cycle = (int) $this->prefs['cycles'];
				$current_cycle --;
				$addon['service_prefs'][ $this->id ]['cycles'] = $current_cycle;

				// If we reach zero, disable this service
				if ( $current_cycle == 0 && in_array( $this->id, $addon['active'] ) ) {
					$new_active = array();
					foreach ( $addon['active'] as $active ) {
						if ( $active == $this->id || in_array( $active, $new_active ) ) continue;
						$new_active[] = $active;
					}
					$addon['active'] = $new_active;
				}

				// Update settings
				mycred_update_option( $option_id, $addon );

			}

		}

		/**
		 * Get Total Payed out
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function get_total_payedout() {

			global $wpdb;
			$log = $this->core->log_table;

			$total = $wpdb->get_var( "
				SELECT SUM( creds ) 
				FROM {$log} 
				WHERE ref = 'recurring_payout' 
				AND ctype = '{$this->mycred_type}';" );

			if ( $total === NULL )
				$total = $this->core->zero();

			return $total;

		}

		/**
		 * Preference for recurring payouts
		 * @since 1.2
		 * @version 1.1
		 */
		public function preferences() {
			$prefs = $this->prefs;
			$editable_roles = array_reverse( get_editable_roles() );
			$pay_count = $this->get_run_count( 'masspayout' ); ?>

<label class="subheader"><?php _e( 'History', 'mycred' ); ?></label>
<ol class="inline">
	<?php if ( $pay_count > 0 ) : ?>
	<li style="min-width: 100px;">
		<label><?php _e( 'Run Count', 'mycred' ); ?></label>
		<div class="h2"><?php echo $pay_count; ?></div>
	</li>
	<?php endif; ?>
	<li style="min-width: 200px;">
		<label><?php _e( 'Last Run', 'mycred' ); ?></label>
		<div class="h2"><?php echo $this->display_last_run( 'masspayout' ); ?></div>
	</li>
	<li>
		<label><?php _e( 'Total Payouts', 'mycred' ); ?></label>
		<div class="h2"><?php echo $this->core->format_creds( $this->get_total_payedout() ); ?></div>
	</li>
</ol>
<label class="subheader"><?php _e( 'Pay Users', 'mycred' ); ?></label>
<ol class="inline">
	<li>
		<label><?php _e( 'Amount', 'mycred' ); ?></label>
		<div class="h2"><?php if ( !empty( $this->core->before ) ) echo $this->core->before . ' '; ?><input type="text" name="<?php echo $this->field_name( 'amount' ); ?>" id="<?php echo $this->field_id( 'amount' ); ?>" value="<?php echo $this->core->format_number( $prefs['amount'] ); ?>" size="8" /><?php if ( !empty( $this->core->after ) ) echo ' ' . $this->core->after; ?></div>
		<span class="description"><?php _e( 'Can not be zero.', 'mycred' ); ?></span>
	</li>
	<li>
		<label for="<?php echo $this->field_id( 'rate' ); ?>"><?php _e( 'Interval', 'mycred' ); ?></label><br />
		<?php $this->timeframe_dropdown( 'rate', false ); ?>

	</li>
	<li>
		<label><?php _e( 'Cycles', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'cycles' ); ?>" id="<?php echo $this->field_id( 'cycles' ); ?>" value="<?php echo $prefs['cycles']; ?>" size="8" /></div>
		<span class="description"><?php _e( 'Set to -1 for unlimited', 'mycred' ); ?></span>
	</li>
	<li>
	<li class="block"><strong><?php _e( 'Important', 'mycred' ); ?></strong><br /><?php _e( 'You can always stop payouts by deactivating this service. Just remember that if you deactivate while there are cycles left, this service will continue on when it gets re-activated. Set cycles to zero to reset.', 'mycred' ); ?></li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" style="width: 65%;" /></div>
		<span class="description"><?php echo $this->core->available_template_tags( array( 'general' ) ); ?></span>
	</li>
</ol>
<label class="subheader"><?php _e( 'Minimum Balance', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><?php if ( $this->core->before != '' ) echo $this->core->before . ' '; ?><input type="text" name="<?php echo $this->field_name( 'min_balance' ); ?>" id="<?php echo $this->field_id( 'min_balance' ); ?>" value="<?php echo $this->core->format_number( $prefs['min_balance'] ); ?>" size="8" placeholder="<?php echo $this->core->zero(); ?>" /><?php if ( $this->core->after != '' ) echo ' ' . $this->core->after; ?></div>
		<span class="description"><?php _e( 'Optional minimum balance requirement.', 'mycred' ); ?></span>
	</li>
</ol>
<label class="subheader"><?php _e( 'Exclude', 'mycred' ); ?></label>
<ol>
	<li>
		<label><?php _e( 'Comma separated list of user IDs', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'exclude_ids' ); ?>" id="<?php echo $this->field_id( 'exclude_ids' ); ?>" value="<?php echo $prefs['exclude_ids']; ?>" class="long" /></div>
	</li>
	<li>
		<label><?php _e( 'Roles', 'mycred' ); ?></label><br />

<?php

			foreach ( $editable_roles as $role => $details ) {
				$name = translate_user_role( $details['name'] );

				echo '<label for="' . $this->field_id( 'exclude-roles-' . $role ) . '"><input type="checkbox" name="' . $this->field_name( 'exclude_roles][' ) . '" id="' . $this->field_id( 'exclude-roles-' . $role ) . '" value="' . esc_attr( $role ) . '"';
				if ( in_array( $role, (array) $prefs['exclude_roles'] ) ) echo ' checked="checked"';
				echo ' />' . $name . '</label><br />';
			}

?>

	</li>
</ol>

<?php do_action( 'mycred_banking_recurring_payouts', $this ); ?>

<?php
		}

		/**
		 * Sanitise Preferences
		 * @since 1.2
		 * @version 1.1
		 */
		function sanitise_preferences( $post ) {

			$new_settings = $post;
			$new_settings['amount'] = trim( $post['amount'] );
			$new_settings['rate'] = sanitize_text_field( $post['rate'] );
			$new_settings['cycles'] = sanitize_text_field( $post['cycles'] );

			$new_settings['log'] = trim( $post['log'] );
			$new_settings['min_balance'] = str_replace( ',', '.', trim( $post['min_balance'] ) );

			$new_settings['exclude_ids'] = sanitize_text_field( $post['exclude_ids'] );

			if ( ! isset( $post['exclude_roles'] ) )
				$post['exclude_roles'] = array();

			$new_settings['exclude_roles'] = $post['exclude_roles'];
			return apply_filters( 'mycred_banking_save_recurring', $new_settings, $this );

		}
	}
endif;
?>