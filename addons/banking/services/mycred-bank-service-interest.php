<?php
/**
 * myCRED Bank Service - Savings
 * @since 1.2
 * @version 1.0
 */
if ( !defined( 'myCRED_VERSION' ) ) exit;

if ( !class_exists( 'myCRED_Banking_Service_Interest' ) ) {
	class myCRED_Banking_Service_Interest extends myCRED_Service {

		/**
		 * Construct
		 */
		function __construct( $service_prefs ) {
			parent::__construct( array(
				'id'       => 'interest',
				'defaults' => array(
					'rate'         => array(
						'amount'       => 2,
						'period'       => 1,
						'compound'     => 'daily',
						'pay_out'      => 'monthly'
					),
					'last_compound' => '',
					'last_payout'   => '',
					'log'           => __( '%plural% interest rate payment', 'mycred' ),
					'min_balance'   => 1
				)
			), $service_prefs );
		}

		/**
		 * Run
		 * @since 1.2
		 * @version 1.0
		 */
		public function run() {
			add_action( 'mycred_bank_compound_interest', array( $this, 'do_compound' ) );
			add_action( 'mycred_bank_payout_interest',   array( $this, 'do_payout' ) );
			
			$unow = date_i18n( 'U' );
			// Cant pay interest on zero
			if ( $this->prefs['amount'] == $this->core->format_number( 0 ) ) return;
			
			// Should we compound
			$compound_now = $this->get_now( $this->prefs['rate']['compound'] );
			if ( empty( $this->prefs['last_compound'] ) || $this->prefs['last_compound'] === NULL ) {
				$last_compound = $this->get_last_run( $unow, $this->prefs['rate']['compound'] );
				$this->save( 'last_compound', $unow );
			}
			else {
				$last_compound = $this->get_last_run( $this->prefs['last_compound'], $this->prefs['rate']['compound'] );
			}
			if ( $compound_now === false || $last_compound == false ) return;
			
			if ( $compound_now != $last_compound ) {
				$this->compound();
				$this->save( 'last_compound', $unow );
			}
			
			// Should we payout
			$payout_now = $this->get_now( $this->prefs['rate']['pay_out'] );
			if ( empty( $this->prefs['last_payout'] ) || $this->prefs['last_payout'] === NULL ) {
				$last_payout = $this->get_last_run( $unow, $this->prefs['rate']['pay_out'] );
				$this->save( 'last_payout', $unow );
			}
			else {
				$last_payout = $this->get_last_run( $this->prefs['last_payout'], $this->prefs['rate']['pay_out'] );
			}
			if ( $payout_now === false || $last_payout == false ) return;
			
			if ( $payout_now != $last_payout ) {
				$this->payout();
				$this->save( 'last_payout', $unow );
			}
		}

		/**
		 * Compound Interest
		 * Schedules the WP Cron to run the compounding on the next page load.
		 * @since 1.2
		 * @version 1.0
		 */
		public function compound() {
			if ( ! wp_next_scheduled( 'mycred_bank_compound_interest' ) ) {
				wp_schedule_event( time(), 'hourly', 'mycred_bank_compound_interest' );
			}
		}

		/**
		 * Do Compound
		 * Runs though all user balances and compounds interest. Will un-schedule it self
		 * once completed.
		 * @since 1.2
		 * @version 1.0
		 */
		public function do_compound() {
			// Get users
			$users = $this->get_user_ids();
			if ( !empty( $users ) ) {
				foreach ( $users as $user_id ) {
					// Current balance
					$balance = $this->core->get_users_creds( $user_id );
					if ( $balance == 0 ) continue;
					$balance = $this->core->number( $balance );
					
					// Get past interest
					$past_interest = get_user_meta( $user_id, $this->core->get_cred_id() . '_comp', true );
					if ( empty( $past_interest ) ) $past_interest = 0;
					
					// Min Balance Limit
					if ( $this->prefs['min_balance'] > $balance && $past_interest == 0 ) continue;
					
					// Convert rate
					$rate = $this->prefs['rate']['amount']/100;
					
					// Period
					$period = $this->prefs['rate']['period']/$this->get_days_in_year();
					
					// Compound
					$interest = ( $balance + $past_interest ) * $rate * $period;
					
					// Save interest
					update_user_meta( $user_id, $this->core->get_cred_id() . '_comp', $interest );

					// Let others play
					do_action( 'mycred_banking_do_compound', $user_id, $this->prefs );
				}
			}
			wp_clear_scheduled_hook( 'mycred_bank_compound_interest' );
		}

		/**
		 * Payout Interest
		 * Schedules the WP Cron to run the payout on the next page load.
		 * @since 1.2
		 * @version 1.0
		 */
		public function payout() {
			if ( ! wp_next_scheduled( 'mycred_bank_payout_interest' ) ) {
				wp_schedule_event( time(), 'hourly', 'mycred_bank_payout_interest' );
			}
		}

		/**
		 * Do Payout
		 * Runs though all user compounded interest and pays. Will un-schedule it self
		 * once completed.
		 * @since 1.2
		 * @version 1.0
		 */
		public function do_payout() {
			// Get users
			$users = $this->get_user_ids();
			if ( !empty( $users ) ) {
				foreach ( $users as $user_id ) {
					// Get past interest
					$past_interest = get_user_meta( $user_id, $this->core->get_cred_id() . '_comp', true );
					if ( empty( $past_interest ) || $past_interest == 0 ) continue;
					
					// Pay / Charge
					$this->core->add_creds(
						'payout',
						$user_id,
						$amount,
						$this->prefs['log'],
						'',
						$past_interest
					);

					// Let others play
					do_action( 'mycred_banking_do_payout', $this->id, $user_id, $this->prefs );
				}
			}
			wp_clear_scheduled_hook( 'mycred_bank_payout_interest' );
		}
		
		/**
		 * Save
		 * Saves the given preference id for rates.
		 * from the active list.
		 * @since 1.2
		 * @version 1.0
		 */
		public function save( $id, $now ) {
			if ( !isset( $this->prefs[ $id ] ) ) return;
			$this->prefs[ $id ] = $now;

			// Get Bank settings
			$bank = get_option( 'mycred_pref_bank' );
			
			// Update settings
			$bank['service_prefs'][$this->id] = $this->prefs;
			// Deactivate this service if this is the last run
			if ( $last ) unset( $bank['active'][$this->id] );

			// Save new settings
			update_option( 'mycred_pref_bank', $bank );
		}

		/**
		 * Preference for Savings
		 * @since 1.2
		 * @version 1.0
		 */
		public function preferences() {
			$prefs = $this->prefs; ?>

					<label class="subheader"><?php _e( 'Interest Rate', 'mycred' ); ?></label>
					<ol class="inline">
						<li>
							<label>&nbsp;</label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'rate' => 'amount' ) ); ?>" id="<?php echo $this->field_id( array( 'rate' => 'amount' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['rate']['amount'] ); ?>" size="4" /> %</div>
						</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'rate' => 'pay_out' ) ); ?>"><?php _e( 'Payed / Charged', 'mycred' ); ?></label><br />
							<?php $this->timeframe_dropdown( array( 'rate' => 'pay_out' ), false ); ?>

						</li>
						<li class="block">
							<span class="description"><?php _e( 'The interest rate can be either positive or negative and is compounded daily.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Minimum Balance', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><?php if ( !empty( $this->core->before ) ) echo $this->core->before . ' '; ?><input type="text" name="<?php echo $this->field_name( 'min_balance' ); ?>" id="<?php echo $this->field_id( 'min_balance' ); ?>" value="<?php echo $this->core->format_number( $prefs['min_balance'] ); ?>" size="8" /><?php if ( !empty( $this->core->after ) ) echo ' ' . $this->core->after; ?></div>
							<span class="description"><?php _e( 'The minimum requires balance for interest to apply.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Log Template', 'mycred' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" style="width: 65%;" /></div>
							<span class="description"><?php _e( 'Available template tags: General, %timeframe%, %rate%, %base%', 'mycred' ); ?></span>
						</li>
					</ol>
					<?php do_action( 'mycred_banking_compound_interest', $this->prefs ); ?>
<?php
		}

		/**
		 * Sanitise Preferences
		 * @since 1.2
		 * @version 1.0
		 */
		function sanitise_preferences( $post ) {
			$new_settings = $post;

			$new_settings['rate']['amount'] = str_replace( ',', '.', trim( $post['rate']['amount'] ) );

			if ( empty( $post['rate']['period'] ) )
				$new_settings['rate']['period'] = $this->get_days_in_year();
			else
				$new_settings['rate']['period'] = abs( $post['rate']['period'] );

			$new_settings['rate']['compound'] = sanitize_text_field( $post['rate']['compound'] );
			$new_settings['rate']['pay_out'] = sanitize_text_field( $post['rate']['pay_out'] );

			$new_settings['min_balance'] = str_replace( ',', '.', trim( $post['min_balance'] ) );

			$new_settings['log'] = trim( $post['log'] );

			return apply_filters( 'mycred_banking_save_interest', $new_settings, $this->prefs );
		}
	}
}

?>