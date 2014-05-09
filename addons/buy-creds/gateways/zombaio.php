<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Zombaio class
 * Zombaio Payment Gateway
 * @since 1.1
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_Zombaio' ) ) {
	class myCRED_Zombaio extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			parent::__construct( array(
				'id'               => 'zombaio',
				'label'            => 'Zombaio',
				'gateway_logo_url' => plugins_url( 'assets/images/zombaio.png', myCRED_PURCHASE ),
				'defaults'         => array(
					'sandbox'          => 0,
					'site_id'          => '',
					'pricing_id'       => '',
					'gwpass'           => '',
					'logo_url'         => '',
					'lang'             => 'ZOM',
					'bypass_ipn'       => 0
				)
			), $gateway_prefs );
		}

		/**
		 * Process
		 * @since 1.1
		 * @version 1.0
		 */
		public function process() {
			if ( isset( $_GET['wp_zombaio_ips'] ) && $_GET['wp_zombaio_ips'] == 1 ) {
				$ips = $this->load_ipn_ips();
				if ( isset( $_GET['csv'] ) && $_GET['csv'] == 1 ) {
					echo '<textarea style="width: 270px;" rows="10" readonly="readonly">' . implode( ',', $ips ) . '</textarea>';
					exit;
				}
				echo '<ul>';
				foreach ( $ips as $ip ) {
					echo '<li><input type="text" readonly="readonly" value="' . $ip . '" size="15" /></li>';
				}
				echo '</ul>';
				exit;
			}
			$this->handle_call();
		}

		/**
		 * Verify IPN IP
		 * @since 1.1
		 * @version 1.0
		 */
		public function verify_ipn_ip() {
			if ( $this->prefs['bypass_ipn'] ) return true;

			$ips = $this->load_ipn_ips();
			if ( $ips && in_array( $_SERVER['REMOTE_ADDR'], $ips ) ) return true;

			return false;
		}

		/**
		 * Load IPN IP List
		 * @since 1.1
		 * @version 1.0
		 */
		public function load_ipn_ips() {
			$request = new WP_Http();
			$data = $request->request( 'http://www.zombaio.com/ip_list.txt' );
			$data = explode( '|', $data['body'] );
			return $data;
		}

		/**
		 * IPN - Is Valid Call
		 * Replaces the default check
		 * @since 1.4
		 * @version 1.0
		 */
		public function IPN_is_valid_call() {
			$result = true;

			// Check password
			if ( $_GET['ZombaioGWPass'] != $this->prefs['gwpass'] )
				$result = false;

			// Check IPN
			if ( $result === true && ! $this->verify_ipn_ip() )
				$result = false;

			// Check Site ID
			if ( $_GET['SiteID'] != $this->prefs['site_id'] )
				$result = false;

			if ( ! $result )
				$this->new_log_entry( ' > ' . __( 'Invalid Call', 'mycred' ) );
			else
				$this->new_log_entry( sprintf( __( 'Caller verified as "%s"', 'mycred' ), $this->id ) );

			return $result;
		}

		/**
		 * Handle IPN Call
		 * @since 1.1
		 * @version 1.1
		 */
		public function handle_call() {
			$outcome = 'FAILED';
			$valid_call = false;
			$valid_sale = false;

			// ZOA Validation
			if ( isset( $_GET['username'] ) && substr( $_GET['username'], 0, 4 ) == 'Test' ) {
				if ( ! headers_sent() )
					header( 'HTTP/1.1 200 OK' );

				echo 'OK';
				die;
			}

			// In case this is a true Zombaio call but for other actions, return now
			// to allow other plugins to take over.
			if ( isset( $_GET['Action'] ) && $_GET['Action'] != 'user.addcredits' )
				return;

			$this->start_log();

			// VALIDATION OF CALL
			$required_fields = array(
				'ZombaioGWPass',
				'SiteID',
				'Action',
				'Credits',
				'TransactionID',
				'Identifier'
			);

			// All required fields exists
			if ( $this->IPN_has_required_fields( $required_fields ) ) {

				// Validate call
				if ( $this->IPN_is_valid_call( $site_id ) ) {

					$valid_call = true;
					
					$this->new_log_entry( __( 'Checking Transaction ID', 'mycred' ) );

					if ( ( $this->sandbox_mode && $_GET['TransactionID'] == '0000' ) || $this->transaction_id_is_unique( $_GET['TransactionID'] ) ) {
						$valid_sale = true;
					}

					else $this->new_log_entry( ' > ' . __( 'Duplicate Transaction ID', 'mycred' ) );

				}

			}

			else $this->new_log_entry( __( 'Failed to verify caller', 'mycred' ) );

			// EXECUTION
			if ( $valid_call === true && $valid_sale === true ) {

				$this->new_log_entry( sprintf( __( 'Attempting to credit %s to users account', 'mycred' ), $this->core->plural() ) );

				list( $buyer_id, $payer_id ) = explode( '|', $_GET['Identifier'] );

				if ( $this->sandbox_mode && $_GET['TransactionID'] == '0000' ) {
					$sales_data = array(
						$buyer_id,
						$payer_id,
						$_GET['Credits'],
						'',
						'USD',
						'#'
					);
				}
				else {
					$sales_data = array(
						$buyer_id,
						$payer_id,
						$_GET['Credits'],
						( isset( $_GET['Amount'] ) ) ? $_GET['Amount'] : '',
						( isset( $_GET['Amount_Currency'] ) ) ? $_GET['Amount_Currency'] : '',
						'#'
					);
				}

				$visitor_ip = ( isset( $_GET['VISITOR_IP']  ) ) ? $_GET['VISITOR_IP'] : 'missing';
				$data = array(
					'transaction_id' => $_GET['TransactionID'],
					'site_id'        => $_GET['SiteID'],
					'visitor_ip'     => $visitor_ip,
					'sales_data'     => implode( '|', $sales_data )
				);

				// Add creds
				if ( $this->complete_payment( $buyer_id, $payer_id, $_GET['Credits'], $data ) ) {

					$this->new_log_entry( sprintf( __( '%s was successfully credited to users account', 'mycred' ), $this->core->format_creds( $_GET['Credits'] ) ) );
					$outcome = 'COMPLETED';

					do_action( "mycred_buycred_{$this->id}_approved", $this->processing_log, $_REQUEST );

				}

				else $this->new_log_entry( __( 'Failed to credit the users account', 'mycred' ) );

			}
			else {
				$this->new_log_entry( __( 'Hanging up on caller', 'mycred' ) );
				do_action( "mycred_buycred_{$this->id}_error", $this->processing_log, $_REQUEST );
			}

			$this->save_log_entry( $_GET['TransactionID'], $outcome );

			do_action( "mycred_buycred_{$this->id}_end", $this->processing_log, $_REQUEST );

			if ( $outcome == 'COMPLETED' )
				echo 'OK';
			else
				echo 'ERROR';

			die;
		}

		/**
		 * Buy Handler
		 * @since 1.1
		 * @version 1.0.1
		 */
		public function buy() {
			if ( ! isset( $this->prefs['site_id'] ) || empty( $this->prefs['site_id'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$token = $this->create_token();

			// Construct location
			$location = 'https://secure.zombaio.com/?' . $this->prefs['site_id'] . '.' . $this->prefs['pricing_id'] . '.' . $this->prefs['lang'];

			// Thank you page
			$thankyou_url = $this->get_thankyou();

			// Cancel page
			$cancel_url = $this->get_cancelled();

			// Return to a url
			if ( isset( $_REQUEST['return_to'] ) ) {
				$thankyou_url = $_REQUEST['return_to'];
				$cancel_url = $_REQUEST['return_to'];
			}

			$to = $this->get_to();
			$from = $this->current_user_id;
			unset( $_REQUEST );

			$hidden_fields = array(
				'identifier'    => $to . '|' . $from,
				'approve_url'   => $thankyou_url,
				'decline_url'   => $cancel_url
			);

			// Generate processing page
			$this->get_page_header( __( 'Processing payment &hellip;', 'mycred' ) );
			$this->get_page_redirect( $hidden_fields, $location );
			$this->get_page_footer();

			// Exit
			unset( $this );
			exit;
		}

		/**
		 * Preferences
		 * @since 1.1
		 * @version 1.0
		 */
		function preferences( $buy_creds = NULL ) {
			$prefs = $this->prefs; ?>

<label class="subheader" for="<?php echo $this->field_id( 'site_id' ); ?>"><?php _e( 'Site ID', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'site_id' ); ?>" id="<?php echo $this->field_id( 'site_id' ); ?>" value="<?php echo $prefs['site_id']; ?>" class="long" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'gwpass' ); ?>"><?php _e( 'GW Password', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'gwpass' ); ?>" id="<?php echo $this->field_id( 'gwpass' ); ?>" value="<?php echo $prefs['gwpass']; ?>" class="long" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'site_id' ); ?>"><?php _e( 'Pricing ID', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'pricing_id' ); ?>" id="<?php echo $this->field_id( 'pricing_id' ); ?>" value="<?php echo $prefs['pricing_id']; ?>" class="long" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'logo_url' ); ?>"><?php _e( 'Logo URL', 'mycred' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'logo_url' ); ?>" id="<?php echo $this->field_id( 'logo_url' ); ?>" value="<?php echo $prefs['logo_url']; ?>" class="long" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'bypass_ipn' ); ?>"><?php _e( 'IP Verification', 'mycred' ); ?></label>
<ol>
	<li>
			<input type="checkbox" name="<?php echo $this->field_name( 'bypass_ipn' ); ?>" id="<?php echo $this->field_id( 'bypass_ipn' ); ?>" value="1"<?php checked( $prefs['bypass_ipn'], 1 ); ?> /> <?php _e( 'Do not verify that callbacks are coming from Zombaio.', 'mycred' ); ?>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'lang' ); ?>"><?php _e( 'Language', 'mycred' ); ?></label>
<ol>
	<li>
		<?php $this->lang_dropdown( 'lang' ); ?>

	</li>
</ol>
<label class="subheader"><?php _e( 'Postback URL (ZScript)', 'mycred' ); ?></label>
<ol>
	<li>
		<code style="padding: 12px;display:block;"><?php echo get_bloginfo( 'url' ); ?></code>
		<p><?php _e( 'For this gateway to work, login to ZOA and set the Postback URL to the above address and click validate.', 'mycred' ); ?></p>
	</li>
</ol>
<?php
		}

		/**
		 * Sanatize Prefs
		 * @since 1.1
		 * @version 1.0
		 */
		public function sanitise_preferences( $data ) {
			$new_data = array();

			$new_data['sandbox']    = ( isset( $data['sandbox'] ) ) ? 1 : 0;
			$new_data['site_id']    = sanitize_text_field( $data['site_id'] );
			$new_data['gwpass']     = sanitize_text_field( $data['gwpass'] );
			$new_data['pricing_id'] = sanitize_text_field( $data['pricing_id'] );
			$new_data['logo_url']   = sanitize_text_field( $data['logo_url'] );
			$new_data['bypass_ipn'] = ( isset( $data['bypass_ipn'] ) ) ? 1 : 0;
			$new_data['lang']       = sanitize_text_field( $data['lang'] );

			return $new_data;
		}

		/**
		 * Language Dropdown
		 * @since 1.1
		 * @version 1.0
		 */
		public function lang_dropdown( $name ) {
			$languages = array(
				'ZOM' => 'Let Zombaio Detect Language',
				'US'  => 'English',
				'FR'  => 'French',
				'DE'  => 'German',
				'IT'  => 'Italian',
				'JP'  => 'Japanese',
				'ES'  => 'Spanish',
				'SE'  => 'Swedish',
				'KR'  => 'Korean',
				'CH'  => 'Traditional Chinese',
				'HK'  => 'Simplified Chinese'
			);

			echo '<select name="' . $this->field_name( $name ) . '" id="' . $this->field_id( $name ) . '">';
			echo '<option value="">' . __( 'Select', 'mycred' ) . '</option>';
			foreach ( $languages as $code => $cname ) {
				echo '<option value="' . $code . '"';
				if ( isset( $this->prefs[ $name ] ) && $this->prefs[ $name ] == $code ) echo ' selected="selected"';
				echo '>' . $cname . '</option>';
			}
			echo '</select>';
		}
	}
}
?>