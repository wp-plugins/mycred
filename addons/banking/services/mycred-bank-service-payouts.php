<?php
/**
 * myCRED Bank Service - Recurring Payouts
 * @since 1.2
 * @version 1.0
 */
if ( !defined( 'myCRED_VERSION' ) ) exit;

if ( !class_exists( 'myCRED_Banking_Service_Payouts' ) ) {
	class myCRED_Banking_Service_Payouts extends myCRED_Service {

		/**
		 * Construct
		 */
		function __construct( $service_prefs ) {
			parent::__construct( array(
				'id'       => 'payouts',
				'defaults' => array(
					'amount'     => 10,
					'rate'       => 'daily',
					'log'        => __( 'Daily %_plural%', 'mycred' ),
					'excludes'   => '',
					'cycles'     => 0,
					'last_run'   => ''
				)
			), $service_prefs );
		}

		/**
		 * Run
		 * @since 1.2
		 * @version 1.0
		 */
		public function run() {
			add_action( 'wp_loaded',                       array( $this, 'process' ) );
			add_action( 'mycred_banking_recurring_payout', array( $this, 'do_payouts' ) );
		}

		/**
		 * Deactivation
		 * @since 1.2
		 * @version 1.0
		 */
		public function deactivate() {
			// Unschedule payouts
			wp_clear_scheduled_hook( 'mycred_banking_recurring_payout' );
		}

		/**
		 * Process
		 * @since 1.2
		 * @version 1.0
		 */
		public function process() {
			// Get cycles
			$cycles = (int) $this->prefs['cycles'];
			// Zero cycles left, bail
			if ( $cycles == 0 ) return;
				
			// No amount = no payout
			if ( !isset( $this->prefs['amount'] ) || $this->prefs['amount'] == 0 ) return;

			$unow = date_i18n( 'U' );
			$now = $this->get_now( $this->prefs['rate'] );
			// No last run, save now as last run
			if ( empty( $this->prefs['last_run'] ) || $this->prefs['last_run'] === NULL ) {
				$last_run = $this->get_last_run( $unow, $this->prefs['rate'] );
				$this->save( $unow, $cycles );
			}
			// Last run
			else {
				$last_run = $this->get_last_run( $this->prefs['last_run'], $this->prefs['rate'] );
			}
			// If now or last run returns false bail
			if ( $now === false || $last_run === false ) return;

			// Mismatch means new run
			if ( $last_run != $now ) {
				// Cycles (-1 means no limit)
				if ( $cycles > 0-1 ) {
					$cycles = $cycles-1;
				}
				// Save
				$this->save( $unow, $cycles );

				// Schedule payouts
				if ( ! wp_next_scheduled( 'mycred_banking_recurring_payout' ) )
					wp_schedule_single_event( time(), 'mycred_banking_recurring_payout' );
			}
		}

		/**
		 * Payout
		 * Gathers all eligeble users and award / deducts the amount given.
		 * @since 1.2
		 * @version 1.0
		 */
		public function do_payouts() {
			// Query
			$users = $this->get_user_ids( $this->prefs['excludes'] );
			$now = $this->get_now( $this->prefs['rate'] );
			$last_run = $this->get_last_run( $this->prefs['last_run'], $this->prefs['rate'] );
			if ( !empty( $users ) ) {
				$users = array_unique( $users );
				foreach( $users as $user_id ) {
					// Add / Deduct points
					$this->core->add_creds(
						'payout',
						$user_id,
						$this->prefs['amount'],
						$this->prefs['log']
					);
					
					// Let others play
					do_action( 'mycred_banking_do_payout', $this->id, $user_id, $this->prefs );
				}
			}
			// Make sure to clear any stray schedules to prevent duplicates
			wp_clear_scheduled_hook( 'mycred_banking_recurring_payout' );
		}

		/**
		 * Save
		 * Saves the last run and the number of cycles run.
		 * If this is the last cycle, this method will remove this service
		 * from the active list.
		 * @since 1.2
		 * @version 1.0
		 */
		public function save( $now = 0, $cycles = 0 ) {
			// Update last run
			$this->prefs['last_run'] = $now;
			// Update cycles count
			$this->prefs['cycles'] = $cycles;

			// Get Bank settings
			$bank = get_option( 'mycred_pref_bank' );
			
			// Update settings
			$bank['service_prefs'][ $this->id ] = $this->prefs;

			// Deactivate this service if this is the last run
			if ( $cycles == 0 ) {
				// Should return the service id as a key for us to unset
				if ( ( $key = array_search( $this->id, $bank['active'] ) ) !== false ) {
					unset( $bank['active'][ $key ] );
				}
			}

			// Save new settings
			update_option( 'mycred_pref_bank', $bank );
		}

		/**
		 * Preference for Savings
		 * @since 1.2
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs;

			// Last run
			$last_run = $prefs['last_run'];
			if ( empty( $last_run ) )
				$last_run = __( 'Not yet run', 'mycred' );
			else
				$last_run = date_i18n( get_option( 'date_format' ) . ' : ' . get_option( 'time_format' ), $last_run ); ?>

					
					<label class="subheader"><?php _e( 'Pay Users', 'mycred' ); ?></label>
					<ol class="inline">
						<li>
							<label><?php _e( 'Amount', 'mycred' ); ?></label>
							<div class="h2"><?php if ( !empty( $this->core->before ) ) echo $this->core->before . ' '; ?><input type="text" name="<?php echo $this->field_name( 'amount' ); ?>" id="<?php echo $this->field_id( 'amount' ); ?>" value="<?php echo $this->core->format_number( $prefs['amount'] ); ?>" size="8" /><?php if ( !empty( $this->core->after ) ) echo ' ' . $this->core->after; ?></div>
							<span class="description"><?php _e( 'Can not be zero.', 'mycred' ); ?></span>
							<input type="hidden" name="<?php echo $this->field_name( 'last_run' ); ?>" value="<?php echo $prefs['last_run']; ?>" />
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
							<label><?php _e( 'Last Run / Activated', 'mycred' ); ?></label><br />
							<div class="h2"><?php echo $last_run; ?></div>
						</li>
						<li class="block"><strong><?php _e( 'Interval', 'mycred' ); ?></strong><br /><?php echo $this->core->template_tags_general( __( 'Select how often you want to award %_plural%. Note that when this service is enabled, the first payout will be in the beginning of the next period. So with a "Daily" interval, the first payout will occur first thing in the morning.', 'mycred' ) ); ?></li>
						<li class="block"><strong><?php _e( 'Cycles', 'mycred' ); ?></strong><br /><?php _e( 'Cycles let you choose how many intervals this service should run. Each time a cycle runs, the value will decrease until it hits zero, in which case this service will deactivate itself. Use -1 to run unlimited times.', 'mycred' ); ?></li>
						<li class="block"><strong><?php _e( 'Important', 'mycred' ); ?></strong><br /><?php _e( 'You can always stop payouts by deactivating this service. Just remember that if you deactivate while there are cycles left, this service will continue on when it gets re-activated. Set cycles to zero to reset.', 'mycred' ); ?></li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'excludes' ); ?>"><?php _e( 'Excludes', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'excludes' ); ?>" id="<?php echo $this->field_id( 'excludes' ); ?>" value="<?php echo $prefs['excludes']; ?>" style="width: 65%;" /></div>
							<span class="description"><?php _e( 'Comma separated list of user IDs to exclude from this service. No spaces allowed!', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" style="width: 65%;" /></div>
							<span class="description"><?php _e( 'Available template tags: General', 'mycred' ); ?></span>
						</li>
					</ol>
					<?php do_action( 'mycred_banking_recurring_payouts', $this->prefs ); ?>
<?php
		}

		/**
		 * Sanitise Preferences
		 * @since 1.2
		 * @version 1.0
		 */
		function sanitise_preferences( $post ) {
			// Amount
			$new_settings['amount'] = trim( $post['amount'] );

			// Rate
			$new_settings['rate'] = sanitize_text_field( $post['rate'] );

			// Cycles
			$new_settings['cycles'] = sanitize_text_field( $post['cycles'] );

			// Last Run
			$new_settings['last_run'] = $post['last_run'];
			$current_cycles = $this->prefs['cycles'];
			// Moving from -1 or 0 to any higher number indicates a new start. In these cases, we will
			// reset the last run timestamp to prevent this service from running right away.
			if ( ( $current_cycles == 0 || $current_cycles == 0-1 ) && $new_settings['cycles'] > 0 )
				$new_settings['last_run'] = '';

			// Excludes
			$excludes = str_replace( ' ', '', $post['excludes'] );
			$new_settings['excludes'] = sanitize_text_field( $excludes );

			// Log
			$new_settings['log'] = trim( $post['log'] );

			return apply_filters( 'mycred_banking_save_recurring', $new_settings, $this->prefs );
		}
	}
}
?>