<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Addons_Module class
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_Addons_Module' ) ) {
	class myCRED_Addons_Module extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct( $type = 'mycred_default' ) {
			parent::__construct( 'myCRED_Addons_Module', array(
				'module_name' => 'addons',
				'option_id'   => 'mycred_pref_addons',
				'defaults'    => array(
					'installed'     => array(),
					'active'        => array()
				),
				'labels'      => array(
					'menu'        => __( 'Add-ons', 'mycred' ),
					'page_title'  => __( 'Add-ons', 'mycred' )
				),
				'screen_id'   => 'myCRED_page_addons',
				'accordion'   => true,
				'menu_pos'    => 30
			), $type );
		}

		/**
		 * Run Addons
		 * Catches all add-on activations and deactivations and loads addons
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function run_addons() {
			$addons = $this->addons;
			$active = $addons['active'];
			$installed = $this->get();
			$num = 0;

			// Make sure each active add-on still exists. If not delete.
			if ( ! empty( $active ) ) {
				$active = array_unique( $active );
				$_active = array();
				foreach ( $active as $pos => $active_id ) {
					if ( array_key_exists( $active_id, $installed ) ) {
						$_active[] = $active_id;
						$num = $num+1;
					}
				}
				unset( $active );
				$active = $_active;
				$this->active = $active;
			}

			// Handle actions
			if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) ) {
				$addon_id = $_GET['addon_id'];
				$action = $_GET['addon_action'];

				// Activation
				if ( $action == 'activate' ) {
					$active[ $num ] = $addon_id;
				}

				// Deactivation
				if ( $action == 'deactivate' ) {
					$index = array_search( $addon_id, $active );
					if ( $index !== false ) {
						unset( $active[ $index ] );
					}

					// Run deactivation now before the file is no longer included
					do_action( 'mycred_addon_deactivation_' . $addon_id );
				}

				$new_settings = array(
					'installed' => $installed,
					'active'    => $active
				);

				if ( ! function_exists( 'update_option' ) )
					include_once( ABSPATH . 'wp-includes/option.php' );

				mycred_update_option( 'mycred_pref_addons', $new_settings );

				$this->addons = $new_settings;
				$this->installed = $installed;
				$this->active = $active;
			}

			// Load addons
			foreach ( $installed as $key => $data ) {
				if ( $this->is_active( $key ) ) {

					// If path is set, load the file
					if ( isset( $data['path'] ) && file_exists( $data['path'] ) )
						include_once( $data['path'] );

					// Check for activation
					if ( $this->is_activation( $key ) )
						do_action( 'mycred_addon_activation_' . $key );

				}
			}
		}

		/**
		 * Is Activation
		 * @since 0.1
		 * @version 1.0
		 */
		public function is_activation( $key ) {
			if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) && $_GET['addon_action'] == 'activate' && $_GET['addon_id'] == $key )
				return true;

			return false;
		}

		/**
		 * Is Deactivation
		 * @since 0.1
		 * @version 1.0
		 */
		public function is_deactivation( $key ) {
			if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) && $_GET['addon_action'] == 'deactivate' && $_GET['addon_id'] == $key )
				return true;

			return false;
		}

		/**
		 * Get Addons
		 * @since 0.1
		 * @version 1.4
		 */
		public function get( $save = false ) {
			$installed = array();

			// Banking Add-on
			$installed['banking'] = array(
				'name'        => __( 'Banking', 'mycred' ),
				'description' => __( 'Setup recurring payouts or offer / charge interest on user account balances.', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/banking/',
				'version'     => '1.0',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'banking/myCRED-addon-banking.php'
			);

			// buyCRED Add-on
			$installed['buy-creds'] = array(
				'name'        => '<strong>buy</strong>CRED',
				'description' => __( 'The <strong>buy</strong>CRED Add-on allows your users to buy points using PayPal, Skrill (Moneybookers) or NETbilling. <strong>buy</strong>CRED can also let your users buy points for other members.', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/buycred/',
				'version'     => '1.2',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'buy-creds/myCRED-addon-buy-creds.php'
			);

			// Coupons Add-on
			$installed['coupons'] = array(
				'name'        => __( 'Coupons', 'mycred' ),
				'description' => __( 'The coupons add-on allows you to create coupons that users can use to add points to their accounts.', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/coupons/',
				'version'     => '1.0',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'coupons/myCRED-addon-coupons.php'
			);

			// Email Notices Add-on
			$installed['email-notices'] = array(
				'name'        => __( 'Email Notices', 'mycred' ),
				'description' => __( 'Create email notices for any type of myCRED instance.', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/email-notices/',
				'version'     => '1.3',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'email-notices/myCRED-addon-email-notices.php'
			);

			// Gateway Add-on
			$installed['gateway'] = array(
				'name'        => __( 'Gateway', 'mycred' ),
				'description' => __( 'Let your users pay using their <strong>my</strong>CRED points balance. Supported Carts: WooCommerce, MarketPress and WP E-Commerce. Supported Event Bookings: Event Espresso and Events Manager (free & pro).', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/gateway/',
				'version'     => '1.4',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'gateway/myCRED-addon-gateway.php'
			);

			// Notifications Add-on
			$installed['notifications'] = array(
				'name'        => __( 'Notifications', 'mycred' ),
				'description' => __( 'Create pop-up notifications for when users gain or loose points.', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/notifications/',
				'version'     => '1.1',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'notifications/myCRED-addon-notifications.php',
				'pro_url'     => 'http://mycred.me/add-ons/notifications-plus/'
			);

			// Ranks Add-on
			$installed['ranks'] = array(
				'name'        => __( 'Ranks', 'mycred' ),
				'description' => __( 'Create ranks for users reaching a certain number of %_plural% with the option to add logos for each rank.', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/ranks/',
				'version'     => '1.2',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'ranks/myCRED-addon-ranks.php'
			);

			// Sell Content Add-on
			$installed['sell-content'] = array(
				'name'        => __( 'Sell Content', 'mycred' ),
				'description' => __( 'This add-on allows you to sell posts, pages or any public post types on your website. You can either sell the entire content or using our shortcode, sell parts of your content allowing you to offer "teasers".', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/sell-content/',
				'version'     => '1.2.1',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'sell-content/myCRED-addon-sell-content.php'
			);

			// Transfer Add-on
			$installed['transfer'] = array(
				'name'        => __( 'Transfers', 'mycred' ),
				'description' => __( 'Allow your users to send or "donate" points to other members by either using the mycred_transfer shortcode or the myCRED Transfer widget.', 'mycred' ),
				'addon_url'   => 'http://mycred.me/add-ons/transfer/',
				'version'     => '1.2',
				'author'      => 'Gabriel S Merovingi',
				'author_url'  => 'http://www.merovingi.com',
				'path'        => myCRED_ADDONS_DIR . 'transfer/myCRED-addon-transfer.php'
			);

			$installed = apply_filters( 'mycred_setup_addons', $installed );

			if ( $save === true && $this->core->can_edit_plugin() ) {
				$new_data = array(
					'active'    => $this->active,
					'installed' => $installed
				);
				update_option( 'mycred_pref_addons', $new_data );
			}

			$this->installed = $installed;
			return $installed;
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page() {
			// Security
			if ( ! $this->core->can_edit_creds() )
				wp_die( __( 'Access Denied', 'mycred' ) );

			// Get installed
			$installed = $this->get( true ); ?>

<div class="wrap" id="myCRED-wrap">
	<h2><?php echo sprintf( __( '%s Add-ons', 'mycred' ), mycred_label() ); ?></h2>

<?php
			// Message
			if ( isset( $_GET['addon_action'] ) ) {
				if ( $_GET['addon_action'] == 'activate' )
					echo '<div class="updated"><p>' . __( 'Add-on Activated', 'mycred' ) . '</p></div>';

				elseif ( $_GET['addon_action'] == 'deactivate' )
					echo '<div class="error"><p>' . __( 'Add-on Deactivated', 'mycred' ) . '</p></div>';
			}
?>

	<p><?php _e( 'Add-ons can expand your current installation with further features.', 'mycred' ); ?></p>
	<div class="list-items expandable-li" id="accordion">
<?php
			// Loop though installed
			if ( ! empty( $installed ) ) {
				foreach ( $installed as $key => $data ) { ?>

		<h4><div class="icon icon-<?php if ( $this->is_active( $key ) ) echo 'active'; else echo 'inactive'; echo ' ' . $key; ?>"></div><label><?php _e( $this->core->template_tags_general( $data['name'] ), 'mycred' ); ?></label></h4>
		<div class="body" style="display:none;">
			<div class="wrapper">
				<div class="description h2"><?php _e( $this->core->template_tags_general( $data['description'] ), 'mycred' ); ?></div>
				<p class="links"><?php echo $this->addon_links( $data ); ?></p>
				<p><?php echo $this->activate_deactivate( $key ); ?></p>
				<div class="clear">&nbsp;</div>
			</div>
		</div>
<?php
				}
			} ?>

	</div>
	<p style="text-align:right;"><?php echo sprintf( __( 'You can find more add-ons in our %s.', 'mycred' ), sprintf( '<a href="http://mycred.me/store/" target="_blank">%s</a>', __( 'online store', 'mycred' ) ) ); ?></p>
</div>
<?php
		}

		/**
		 * Activate / Deactivate Button
		 * @since 0.1
		 * @version 1.0
		 */
		public function activate_deactivate( $key ) {
			$url = admin_url( 'admin.php' );
			$args = array(
				'page'     => 'myCRED_page_addons',
				'addon_id' => $key
			);

			// Active
			if ( $this->is_active( $key ) ) {
				$args['addon_action'] = 'deactivate';

				$link_title = __( 'Deactivate Add-on', 'mycred' );
				$link_text = __( 'Deactivate', 'mycred' );
			}

			// Inactive
			else {
				$args['addon_action'] = 'activate';

				$link_title = __( 'Activate Add-on', 'mycred' );
				$link_text = __( 'Activate', 'mycred' );
			}

			return '<a href="' . add_query_arg( $args, $url ) . '" title="' . $link_title . '" class="button button-large button-primary mycred-action">' . $link_text . '</a>';
		}

		/**
		 * Add-on Details
		 * @since 0.1
		 * @version 1.1
		 */
		public function addon_links( $data ) {
			$info = array();

			// Version
			if ( isset( $data['version'] ) )
				$info[] = __( 'Version', 'mycred' ) . ' ' . $data['version'];

			// Author URL
			if ( isset( $data['author_url'] ) && ! empty( $data['author_url'] ) && isset( $data['author'] ) && ! empty( $data['author'] ) )
				$info[] = __( 'By', 'mycred' ) . ' <a href="' . $data['author_url'] . '" target="_blank">' . $data['author'] . '</a>';

			// Add-on URL
			if ( isset( $data['addon_url'] ) && ! empty( $data['addon_url'] ) )
				$info[] = '<a href="' . $data['addon_url'] . '" target="_blank">' . __( 'About', 'mycred' ) . '</a>';

			// Pro URL
			if ( isset( $data['pro_url'] ) && ! empty( $data['pro_url'] ) )
				$info[] = '<a href="' . $data['pro_url'] . '" target="_blank">' . __( 'Get Pro', 'mycred' ) . '</a>';

			if ( ! empty( $info ) )
				return implode( ' | ', $info );

			return '';
		}
	}
}
?>