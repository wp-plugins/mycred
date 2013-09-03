<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Zombaio class
 * Zombaio Payment Gateway
 * 
 * @since 1.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Zombaio' ) ) {
	class myCRED_Zombaio extends myCRED_Payment_Gateway {

		/**
		 * Construct
		 */
		function __construct( $gateway_prefs ) {
			$name = apply_filters( 'mycred_label', myCRED_NAME );
			parent::__construct( array(
				'id'       => 'zombaio',
				'defaults' => array(
					'sandbox'    => 0,
					'site_id'    => '',
					'pricing_id' => '',
					'gwpass'     => '',
					'logo_url'   => '',
					'lang'       => 'ZOM',
					'bypass_ipn' => 0
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
		
		public function verify_ipn_ip() {
			if ( $this->prefs['bypass_ipn'] ) return true;
			
			$ips = $this->load_ipn_ips();		
			if ( $ips ) {
				if ( in_array( $_SERVER['REMOTE_ADDR'], $ips ) ) return true;
			}
			return false;
		}

		public function load_ipn_ips() {
			$request = new WP_Http();
			$data = $request->request( 'http://www.zombaio.com/ip_list.txt' );
			$data = explode( '|', $data['body'] );
			return $data;
		}
		
		public function handle_call() {
			// Prep
			$id = $this->id;
			$error = false;
			$log_entry = array();
			
			// Password check
			$gw_pass = isset( $_GET['ZombaioGWPass'] ) ? $_GET['ZombaioGWPass'] : false;
			if ( !$gw_pass ) return;
			
			// Password missmatch
			if ( $gw_pass != $this->prefs['gwpass'] ) {
				$log_entry[] = 'GWPassword mismatch: [' . $gw_pass . ']';
				$error = 1;
			}

			// Can not verify IPN IP
			if ( !$this->verify_ipn_ip() ) {
				$log_entry[] = 'IP Verification failed: [' . $_SERVER['REMOTE_ADDR'] . ']';
				$error = 2;
			}

			// Verify Site ID
			$site_id = isset( $_GET['SITE_ID'] ) ? $_GET['SITE_ID'] : ( isset($_GET['SiteID'] ) ? $_GET['SiteID'] : false );
			if ( !$site_id ) {
				if ( isset( $_GET['username'] ) && substr( $_GET['username'], 0, 4 ) == 'Test' ) {
					header('HTTP/1.1 200 OK');
					echo 'OK';
					exit;
				}
				$log_entry[] = 'Site ID missing';
				$error = true;
			}
			if ( $site_id != $this->prefs['site_id'] ) {
				$log_entry[] = 'Site ID mismatch: [' . $site_id . ']';
				$error = true;
			}

			// Get Action
			$action = isset( $_GET['Action'] ) ? $_GET['Action'] : false;
			if ( !$action ) {
				$log_entry[] = 'Missing Action';
				$error = true;
			}
			
			// Action handler
			if ( $error === false ) {
				$action = strtolower( $action );
				switch ( $action ) {
					case 'user.addcredits':
						// Identifier $_to|$_from
						$identifier = isset( $_GET['Identifier'] ) ? $_GET['Identifier'] : false;
						// Missing
						if ( !$identifier ) {
							$log_entry[] = 'Missing Identifier';
							$error = true;
						}
						else {
							$_identifier = explode( '|', $identifier );
							// Incorrect format
							if ( !$_identifier ) {
								$log_entry[] = 'Incorrect Identifier format: [' . $identifier . ']';
								$error = true;
							}
							// All good
							else {
								$_to = abs( $_identifier[0] );
								$_from = abs( $_identifier[1] );
							}
						}
					
						// Amount
						$amount = isset( $_GET['Credits'] ) ? abs( $_GET['Credits'] ) : false;
						if ( !$amount ) {
							$log_entry[] = 'Missing Credit Amount';
							$error = true;
						}
						if ( !is_numeric( $amount ) ) {
							$log_entry[] = 'Incorrect Amount format: [' . $amount . ']';
							$error = true;
						}
						
						// Make sure this is a unique purchase
						$transaction_id = ( isset( $_GET['TransactionID'] ) ) ? $_GET['TransactionID'] : 'Zombaio|' . $_to . '|' . $_from . '|' . time();
						if ( $transaction_id !== '0000' ) {
							if ( !$this->transaction_id_is_unique( $transaction_id ) ) {
								$log_entry[] = 'Transaction ID previously used: [' . $transaction_id . ']';
								$error = true;
							}
						}
						else {
							$this->prefs['sandbox'] = true;
						}
					
						// No errors
						if ( $error === false ) {
							// Log Entry
							$entry = $this->get_entry( $_to, $_from );
							$entry = str_replace( '%gateway%', 'Zombaio', $entry );
							if ( $this->prefs['sandbox'] ) $entry = 'TEST ' . $entry;
					
							// Data
							
							$visitor_ip = ( isset( $_GET['VISITOR_IP']  ) ) ? $_GET['VISITOR_IP'] : 'missing';
							$data = array(
								'transaction_id' => $transaction_id,
								'sales_data'     => $identifier,
								'site_id'        => $site_id,
								'visitor_ip'     => $visitor_ip
							);
					
							// Execute
							$this->core->add_creds(
								'buy_creds_with_zombaio',
								$_to,
								$amount,
								$entry,
								$_from,
								$data
							);
						
							$log_entry[] = 'CREDs Added';
							do_action( "mycred_buy_cred_{$id}_approved", $this->prefs );
						}
					
					break;
				}
			}
			
			do_action( "mycred_buy_cred_{$id}_end", $log_entry, $this->prefs );

			// No errors = success
			if ( $error === false ) {
				echo 'OK';
			}
			// GW Pass issue
			elseif ( $error === 1 ) {
				header( 'HTTP/1.0 401 Unauthorized' );
				echo 'myCRED ERROR 100';
			}
			// IP Verification issue
			elseif ( $error === 2 ) {
				header( 'HTTP/1.0 403 Forbidden' );
				echo 'myCRED ERROR 200';
			}
			// All other issues
			else {
				header( 'HTTP/1.0 401 Unauthorized' );
				echo 'myCRED ERROR 300';
			}
			
			exit;
		}
		
		/**
		 * Buy Handler
		 * @since 1.1
		 * @version 1.0
		 */
		public function buy() {
			if ( !isset( $this->prefs['site_id'] ) || empty( $this->prefs['site_id'] ) )
				wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

			$home = get_bloginfo( 'url' );
			$token = $this->create_token();
			
			// Construct location
			$location = 'https://secure.zombaio.com/?' . $this->prefs['site_id'] . '.' . $this->prefs['pricing_id'] . '.' . $this->prefs['lang'];
			
			// Finance
			$amount = $this->core->number( $_REQUEST['amount'] );
			// Enforce minimum
			if ( $amount < $this->core->buy_creds['minimum'] )
				$amount = $this->core->buy_creds['minimum'];
			// No negative amounts please
			$amount = abs( $amount );
			
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
			$this->purchase_header( __( 'Processing payment &hellip;', 'mycred' ) );
			$this->form_with_redirect( $hidden_fields, $location, $this->prefs['logo_url'] );
			$this->purchase_footer();

			// Exit
			unset( $this );
			exit();
		}
		
		/**
		 * Preferences
		 * @since 1.1
		 * @version 1.0
		 */
		public function preferences( $buy_creds ) {
			$prefs = $this->prefs;
			if ( empty( $prefs['logo_url'] ) )
				$prefs['logo_url'] = plugins_url( 'images/zombaio.png', myCRED_PURCHASE ); ?>

					<label class="subheader" for="<?php echo $this->field_id( 'sandbox' ); ?>"><?php _e( 'Sandbox Mode', 'mycred' ); ?></label>
					<ol>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'sandbox' ); ?>" id="<?php echo $this->field_id( 'sandbox' ); ?>" value="1"<?php checked( $prefs['sandbox'], 1 ); ?> />
						</li>
					</ol>
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
			$data['sandbox'] = ( !isset( $data['sandbox'] ) ) ? 0 : 1;
			
			$data['site_id'] = sanitize_text_field( $data['site_id'] );
			$data['gwpass'] = sanitize_text_field( $data['gwpass'] );
			$data['pricing_id'] = sanitize_text_field( $data['pricing_id'] );
			$data['logo_url'] = sanitize_text_field( $data['logo_url'] );
			$data['bypass_ipn'] = ( isset( $data['bypass_ipn'] ) ) ? 1 : 0;
			$data['lang'] = sanitize_text_field( $data['lang'] );
			
			return $data;
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
				if ( $this->prefs[$name] == $code ) echo ' selected="selected"';
				echo '>' . $cname . '</option>';
			}
			echo '</select>';
		}
	}
}
?>