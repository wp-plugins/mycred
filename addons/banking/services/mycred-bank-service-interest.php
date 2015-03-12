<?php
/**
 * myCRED Bank Service - Interest
 * @since 1.2
 * @version 1.2
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

if ( ! class_exists( 'myCRED_Banking_Service_Interest' ) ) :
	class myCRED_Banking_Service_Interest extends myCRED_Service {

		/**
		 * Construct
		 */
		function __construct( $service_prefs, $type = 'mycred_default' ) {
			parent::__construct( array(
				'id'       => 'interest',
				'defaults' => array(
					'rate'         => array(
						'amount'       => 2,
						'period'       => 1,
						'pay_out'      => 'monthly'
					),
					'log'           => __( '%plural% interest rate payment', 'mycred' ),
					'min_balance'   => 1,
					'exclude_ids'   => '',
					'exclude_roles' => array()
				)
			), $service_prefs, $type );

			add_action( 'mycred_bank_interest_comp' . $this->mycred_type, array( $this, 'do_compounding' ) );
			add_action( 'mycred_bank_interest_pay' . $this->mycred_type, array( $this, 'do_interest_payout' ) );
		}

		/**
		 * Activate Service
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function activate() {

			$this->save_last_run( 'compound' );
			$this->save_last_run( 'payout' );

		}

		/**
		 * Deactivate Service
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function deactivate() {

			$option_id = 'mycred_bank_interest_comp' . $this->mycred_type;
			$timestamp = wp_next_scheduled( $option_id );
			if ( $timestamp !== false )
				wp_clear_scheduled_hook( $timestamp, $option_id );

			$option_id = 'mycred_bank_interest_pay' . $this->mycred_type;
			$timestamp = wp_next_scheduled( $option_id );
			if ( $timestamp !== false )
				wp_clear_scheduled_hook( $timestamp, $option_id );

		}

		/**
		 * Run
		 * @since 1.2
		 * @version 1.1
		 */
		public function run() {

			// Time to compound interest?
			$compound_rate = apply_filters( 'mycred_compound_interest', 'daily', $this );
			$time_to_compound = $this->time_to_run( $compound_rate, $this->get_last_run( 'compound' ) );
			if ( $time_to_compound ) {

				// Get Work
				$option_id = 'mycred_bank_interest_comp' . $this->mycred_type;
				$work_marker = 'MYCRED_BANK_COMPOUND_' . $this->mycred_type;

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
							$this->update_run_count( 'compound' );
							$this->save_last_run( 'compound' );

						}

					}

				}
				else {

					// Mark completed
					$this->update_run_count( 'compound' );
					$this->save_last_run( 'compound' );

				}

			}

			// Time to Payout?
			$time_to_payout = $this->time_to_run( $this->prefs['rate']['pay_out'], $this->get_last_run( 'payout' ) );
			if ( $time_to_payout ) {

				// Get Work
				$option_id = 'mycred_bank_interest_pay' . $this->mycred_type;
				$work_marker = 'MYCRED_BANK_COMPPAY_' . $this->mycred_type;

				$eligeble_users = $this->get_eligeble_users_payout();

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
							$this->update_run_count( 'payout' );
							$this->save_last_run( 'payout' );

						}

					}

				}
				else {

					// Mark completed
					$this->update_run_count( 'payout' );
					$this->save_last_run( 'payout' );

				}

			}

		}

		/**
		 * Do Interest Compounding
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function do_compounding() {

			$work_marker = 'MYCRED_BANK_COMPOUND_' . $this->mycred_type;
			define( $work_marker, time() );

			$option_id = 'mycred_bank_interest_comp' . $this->mycred_type;
			$current_work = mycred_get_option( $option_id, false );
			if ( $current_work === false ) {
				$this->update_run_count( 'compound' );
				$this->save_last_run( 'compound' );
				return;
			}

			$work = $current_work;
			foreach ( $current_work as $row => $user_id ) {

				// Get users balance
				$balance = mycred_get_user_meta( $user_id, $this->mycred_type );

				// Get past interest to add up to
				$past_interest = mycred_get_user_meta( $user_id, $this->mycred_type . '_comp' );
				if ( $past_interest == '' ) $past_interest = 0;

				// Get interest rate
				$user_override = mycred_get_user_meta( $user_id, 'mycred_banking_rate_' . $this->mycred_type );
				if ( $user_override != '' )
					$rate = $user_override / 100;
				else
					$rate = $this->prefs['rate']['amount'] / 100;

				// Period
				$period = $this->prefs['rate']['period'] / $this->get_days_in_year();

				// Compound
				$interest = ( $balance + $past_interest ) * $rate * $period;
				$interest = round( $interest, 2 );

				// Save interest
				mycred_update_user_meta( $user_id, $this->mycred_type . '_comp', '', $interest );

				// Remove from workload
				unset( $work[ $row ] );

				if ( ! empty( $work ) )
					mycred_update_option( $option_id, $work );
				else {
					mycred_delete_option( $option_id );
					$this->update_run_count( 'compound' );
					$this->save_last_run( 'compound' );
				}

			}

		}

		/**
		 * Do Interest Payout
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function do_interest_payout() {

			$work_marker = 'MYCRED_BANK_COMPPAY_' . $this->mycred_type;
			define( $work_marker, time() );

			$option_id = 'mycred_bank_interest_pay' . $this->mycred_type;
			$current_work = mycred_get_option( $option_id, false );
			if ( $current_work === false ) {
				$this->update_run_count( 'payout' );
				$this->save_last_run( 'payout' );
				return;
			}

			$now = date_i18n( 'U' );
			$work = $current_work;
			foreach ( $current_work as $row => $user_id ) {

				// Get past interest to add up to
				$accumulated_interest = mycred_get_user_meta( $user_id, $this->mycred_type . '_comp', '', true );
				if ( $accumulated_interest != '' ) {

					// Add a unique Payout ID
					$data = array( 'payout_id' => $now . $user_id );

					// Prevent duplicates
					if ( ! $this->core->has_entry( 'interest', 0, $user_id, $data, $this->mycred_type ) )
						$this->core->add_creds(
							'interest',
							$user_id,
							$accumulated_interest,
							$this->prefs['log'],
							0,
							$data,
							$this->mycred_type
						);

				}

				// Remove from workload
				unset( $work[ $row ] );

				if ( ! empty( $work ) )
					mycred_update_option( $option_id, $work );
				else {
					mycred_delete_option( $option_id );
					$this->update_run_count( 'payout' );
					$this->save_last_run( 'payout' );
				}

			}

		}

		/**
		 * Get Eligeble User Payouts
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function get_eligeble_users_payout() {

			global $wpdb;

			$key = $this->mycred_type . '_comp';
			if ( is_multisite() && $GLOBALS['blog_id'] > 1 && ! $this->core->use_central_logging )
				$key .= '_' . $GLOBALS['blog_id'];

			$users = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT user_id 
				FROM {$wpdb->usermeta} 
				WHERE meta_key = %s;", $key ) );

			if ( $users === NULL )
				$users = array();

			return $users;

		}

		/**
		 * Get Total Pending for Payout
		 * Returns the total amount of compounded interest that is currently
		 * pending to be paid out.
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function get_total_pending_payout() {

			global $wpdb;
			$key = $this->mycred_type . '_comp';

			$total = $wpdb->get_var( "
				SELECT SUM( meta_value ) 
				FROM {$wpdb->usermeta} 
				WHERE meta_key = '{$key}';" );

			if ( $total === NULL )
				$total = $this->core->zero();

			return $total;

		}

		/**
		 * Get Total Payouts
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function get_total_interest_payouts() {

			global $wpdb;
			$log = $this->core->log_table;

			$total = $wpdb->get_var( "
				SELECT SUM( creds ) 
				FROM {$log} 
				WHERE ref = 'interest' 
				AND ctype = '{$this->mycred_type}';" );

			if ( $total === NULL )
				$total = $this->core->zero();

			return $total;

		}

		/**
		 * Preference for interest rates
		 * @since 1.2
		 * @version 1.3
		 */
		public function preferences() {

			$prefs = $this->prefs;
			$editable_roles = array_reverse( get_editable_roles() );

			// Inform user when compounding is running
			$comp_work_marker = 'MYCRED_BANK_COMPOUND_' . $this->mycred_type;
			if ( defined( $comp_work_marker ) ) :
				$current_work = mycred_get_option( 'mycred_bank_interest_comp' . $this->mycred_type, false ); ?>

<p><strong><?php _e( 'Compounding Interest', 'mycred' ); ?></strong> <?php print_r( __( '%d Users are left to process.', 'mycred' ), count( $current_work ) ); ?></p>
<?php

			endif;

			$pay_count = $this->get_run_count( 'payout' );
			$comp_count = $this->get_run_count( 'compound' );

?>
<label class="subheader"><?php _e( 'Payout History', 'mycred' ); ?></label>
<ol class="inline">
	<?php if ( $pay_count > 0 ) : ?>
	<li style="min-width: 100px;">
		<label><?php _e( 'Run Count', 'mycred' ); ?></label>
		<div class="h2"><?php echo $pay_count; ?></div>
	</li>
	<?php endif; ?>
	<li style="min-width: 200px;">
		<label><?php if ( $pay_count > 0 ) _e( 'Last Payout', 'mycred' ); else _e( 'Activated', 'mycred' ); ?></label>
		<div class="h2"><?php echo $this->display_last_run( 'payout' ); ?></div>
	</li>
	<li>
		<label><?php _e( 'Total Payed Interest', 'mycred' ); ?></label>
		<div class="h2"><?php echo $this->core->format_creds( $this->get_total_interest_payouts() ); ?></div>
	</li>
</ol>
<label class="subheader"><?php _e( 'Compound History', 'mycred' ); ?></label>
<ol class="inline">
	<?php if ( $comp_count > 0 ) : ?>
	<li style="min-width: 100px;">
		<label><?php _e( 'Run Count', 'mycred' ); ?></label>
		<div class="h2"><?php echo $comp_count; ?></div>
	</li>
	<?php endif; ?>
	<li style="min-width: 200px;">
		<label><?php if ( $comp_count > 0 ) _e( 'Last Interest Compound', 'mycred' ); else _e( 'Activated', 'mycred' ); ?></label>
		<div class="h2"><?php echo $this->display_last_run( 'compound' ); ?></div>
	</li>
	<li>
		<label><?php _e( 'Total Compounded Interest', 'mycred' ); ?></label>
		<div class="h2"><?php echo $this->core->format_creds( $this->get_total_pending_payout() ); ?></div>
	</li>
</ol>
<label class="subheader"><?php _e( 'Interest Rate', 'mycred' ); ?></label>
<ol class="inline">
	<li>
		<label><?php _e( 'Default Rate', 'mycred' ); ?></label>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'rate' => 'amount' ) ); ?>" id="<?php echo $this->field_id( array( 'rate' => 'amount' ) ); ?>" value="<?php echo $this->core->format_number( $prefs['rate']['amount'] ); ?>" size="8" />%</div>
		<span class="description"><?php _e( 'Can not be zero.', 'mycred' ); ?></span>
	</li>
	<li>
		<label for="<?php echo $this->field_id( 'rate' ); ?>"><?php _e( 'Payout', 'mycred' ); ?></label><br />
		<?php $this->timeframe_dropdown( array( 'rate' => 'pay_out' ), false, false ); ?>

	</li>
</ol>
<label class="subheader"><?php _e( 'Log Template', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" style="width: 65%;" /></div>
		<span class="description"><?php echo $this->core->available_template_tags( array( 'general' ), '%timeframe%, %rate%, %base%' ); ?></span>
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
<?php do_action( 'mycred_banking_compound_interest', $this ); ?>

<?php
		}

		/**
		 * Sanitise Preferences
		 * @since 1.2
		 * @version 1.3
		 */
		function sanitise_preferences( $post ) {

			$new_settings = $post;
			$new_settings['rate']['amount'] = str_replace( ',', '.', trim( $post['rate']['amount'] ) );
			$new_settings['rate']['period'] = $this->get_days_in_year();
			$new_settings['rate']['pay_out'] = sanitize_text_field( $post['rate']['pay_out'] );

			$new_settings['log'] = trim( $post['log'] );
			$new_settings['min_balance'] = str_replace( ',', '.', trim( $post['min_balance'] ) );

			$new_settings['exclude_ids'] = sanitize_text_field( $post['exclude_ids'] );

			if ( ! isset( $post['exclude_roles'] ) )
				$post['exclude_roles'] = array();

			$new_settings['exclude_roles'] = $post['exclude_roles'];
			return apply_filters( 'mycred_banking_save_interest', $new_settings, $this );

		}

		/**
		 * User Override
		 * @since 1.5.2
		 * @version 1.0
		 */
		function user_override( $user = NULL, $type = 'mycred_default' ) {

			$users_rate = mycred_get_user_meta( $user->ID, 'mycred_banking_rate_' . $type );
			$excluded = $this->exclude_user( $user->ID ); ?>

<h3><?php _e( 'Compound Interest', 'mycred' ); ?></h3>

<?php

			if ( $excluded == 'list' ) :

?>

<table class="form-table">
	<tr>
		<td colspan="2"><?php _e( 'This user is excluded from receiving interest on this balance.', 'mycred' ); ?></td>
	</tr>
	<tr>
		<td colspan="2"><?php submit_button( __( 'Remove from Excluded List', 'mycred' ), 'primary medium', 'mycred_include_users_interest_rate', false ); ?></td>
	</tr>
</table>

<?php

			elseif ( $excluded == 'role' ) :

?>

<table class="form-table">
	<tr>
		<td colspan="2"><?php _e( 'This user role is excluded from receiving interest on this balance.', 'mycred' ); ?></td>
	</tr>
</table>

<?php

			else :

?>

<table class="form-table">
	<tr>
		<th scope="row"><?php _e( 'Interest Rate', 'mycred' ); ?></th>
		<td>
			<input type="text" name="mycred_adjust_users_interest_rate" id="mycred-adjust-users-interest-rate" value="<?php echo $users_rate; ?>" placeholder="<?php echo $this->prefs['rate']['amount']; ?>" size="8" /> %<br />
			<span class="description"><?php _e( 'Leave empty to use the default value.', 'mycred' ); ?></span>
		</td>
	</tr>
	<tr>
		<th scope="row"></th>
		<td>
			<?php submit_button( __( 'Save Interest Rate', 'mycred' ), 'primary medium', 'mycred_adjust_users_interest_rate_run', false ); ?> 
			<?php submit_button( __( 'Exclude from receiving interest', 'mycred' ), 'primary medium', 'mycred_exclude_users_interest_rate', false ); ?>
		</td>
	</tr>
</table>
<?php
			endif;

		}

		/**
		 * Save User Override
		 * @since 1.5.2
		 * @version 1.0.1
		 */
		function save_user_override() {

			// Save interest rate
			if ( isset( $_POST['mycred_adjust_users_interest_rate_run'] ) && isset( $_POST['mycred_adjust_users_interest_rate'] ) ) {

				$ctype = sanitize_key( $_GET['ctype'] );
				$user_id = absint( $_GET['user_id'] );

				$rate = $_POST['mycred_adjust_users_interest_rate'];
				if ( $rate != '' ) {
					if ( isfloat( $rate ) )
						$rate = (float) $rate;
					else
						$rate = (int) $rate;

					mycred_update_user_meta( $user_id, 'mycred_banking_rate_' . $ctype, '', $rate );
				}
				else {
					mycred_delete_user_meta( $user_id, 'mycred_banking_rate_' . $ctype );
				}

				wp_safe_redirect( add_query_arg( array( 'result' => 'banking_interest_rate' ) ) );
				exit;

			}

			// Exclude
			elseif ( isset( $_POST['mycred_exclude_users_interest_rate'] ) ) {

				$ctype = sanitize_key( $_GET['ctype'] );
				$user_id = absint( $_GET['user_id'] );

				$excluded = explode( ',', $this->prefs['exclude_ids'] );
				$clean_ids = array();
				if ( ! empty( $excluded ) ) {
					foreach ( $excluded as $id ) {
						if ( $id == 0 ) continue;
						$clean_ids[] = (int) trim( $id );
					}
				}

				if ( ! in_array( $user_id, $clean_ids ) && $user_id != 0 )
					$clean_ids[] = $user_id;

				$option_id = 'mycred_pref_bank';
				if ( ! $this->is_main_type )
					$option_id .= '_' . $ctype;

				$data = mycred_get_option( $option_id );
				$data['service_prefs'][ $this->id ]['exclude_ids'] = implode( ',', $clean_ids );

				mycred_update_option( $option_id, $data );

				wp_safe_redirect( add_query_arg( array( 'result' => 'banking_interest_excluded' ) ) );
				exit;

			}

			// Include
			elseif ( isset( $_POST['mycred_include_users_interest_rate'] ) ) {

				$ctype = sanitize_key( $_GET['ctype'] );
				$user_id = absint( $_GET['user_id'] );

				$excluded = explode( ',', $this->prefs['exclude_ids'] );
				if ( ! empty( $excluded ) ) {
					$clean_ids = array();
					foreach ( $excluded as $id ) {
						$clean_id = (int) trim( $id );
						if ( $clean_id != $user_id && $user_id != 0 )
							$clean_ids[] = $clean_id;
					}

					$option_id = 'mycred_pref_bank';
					if ( ! $this->is_main_type )
						$option_id .= '_' . $ctype;

					$data = mycred_get_option( $option_id );
					$data['service_prefs'][ $this->id ]['exclude_ids'] = implode( ',', $clean_ids );

					mycred_update_option( $option_id, $data );

					wp_safe_redirect( add_query_arg( array( 'result' => 'banking_interest_included' ) ) );
					exit;
				}

			}

		}

		/**
		 * User Override Notice
		 * @since 1.5.2
		 * @version 1.0
		 */
		function user_override_notice() {

			if ( isset( $_GET['page'] ) && $_GET['page'] == 'mycred-edit-balance' && isset( $_GET['result'] ) ) {

				if ( $_GET['result'] == 'banking_interest_rate' )
					echo '<div class="updated"><p>' . __( 'Compound interest rate saved.', 'mycred' ) . '</p></div>';
				elseif ( $_GET['result'] == 'banking_interest_excluded' )
					echo '<div class="updated"><p>' . __( 'User excluded from receiving interest.', 'mycred' ) . '</p></div>';
				elseif ( $_GET['result'] == 'banking_interest_included' )
					echo '<div class="updated"><p>' . __( 'User included in receiving interest.', 'mycred' ) . '</p></div>';

			}

		}

	}
endif;
?>