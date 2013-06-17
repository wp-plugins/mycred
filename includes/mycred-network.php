<?php
if ( !defined( 'myCRED_VERSION' ) || !is_multisite() ) exit;
/**
 * myCRED_Network class
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Network' ) ) {
	class myCRED_Network {

		public $core;
		public $plug;

		/**
		 * Construct
		 */
		function __construct() {
			global $mycred_network;
			$this->core = mycred_get_settings();
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.0
		 */
		public function load() {
			add_action( 'init',       array( $this, 'module_init' )        );
			add_action( 'admin_head', array( $this, 'admin_menu_styling' ) );
		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_init() {
			// Network Settings Update
			if ( isset( $_POST['mycred_network'] ) && isset( $_POST['mycred-token'] ) )
				$this->save_network_prefs();

			// Add Menu
			add_action( 'network_admin_menu', array( &$this, 'add_menu' ) );
		}

		/**
		 * Add Network Menu Items
		 * @since 0.1
		 * @version 1.0
		 */
		public function add_menu() {
			$pages[] = add_menu_page(
				__( 'myCRED', 'mycred' ),
				__( 'myCRED', 'mycred' ),
				'manage_network_options',
				'myCRED',
				'',
				''
			);
			$pages[] = add_submenu_page(
				'myCRED',
				__( 'Network Settings', 'mycred' ),
				__( 'Network Settings', 'mycred' ),
				'manage_network_options',
				'myCRED',
				array( $this, 'admin_page_settings' )
			);

			foreach ( $pages as $page )
				add_action( 'admin_print_styles-' . $page, array( $this, 'admin_print_styles' ) );
		}

		/**
		 * Add Admin Menu Styling
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_menu_styling() {
			$image = plugins_url( 'images/logo-menu.png', myCRED_THIS );
			echo '
<style type="text/css">
#adminmenu .toplevel_page_myCRED div.wp-menu-image { background-image: url(' . $image . '); background-position: 1px -28px; }
#adminmenu .toplevel_page_myCRED:hover div.wp-menu-image, 
#adminmenu .toplevel_page_myCRED.current div.wp-menu-image, 
#adminmenu .toplevel_page_myCRED .wp-menu-open div.wp-menu-image { background-position: 1px 0; }
</style>' . "\n";
		}

		/**
		 * Load Admin Page Styling
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_print_styles() {
			if ( !wp_style_is( 'mycred-admin', 'registered' ) ) {
				wp_register_style(
					'mycred-admin',
					plugins_url( 'css/admin.css', myCRED_THIS ),
					false,
					myCRED_VERSION . '.1',
					'all'
				);
			}

			wp_enqueue_style( 'mycred-admin' );
		}

		/**
		 * Network Settings Page
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_page_settings() {
			// Security
			if ( !current_user_can( 'manage_network_options' ) ) wp_die( __( 'Access Denied', 'mycred' ) );

			global $mycred_network;

			$defaults = array(
				'master' => 0,
				'block'  => ''
			);
			$prefs = get_site_option( 'mycred_network', $defaults, false );
			$name = apply_filters( 'mycred_label', myCRED_NAME ); ?>

	<div class="wrap" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2> <?php echo $name . ' ' . __( 'Network', 'mycred' ); ?></h2>
		<?php

			// Settings Updated
			if ( isset( $mycred_network['update'] ) )
				echo '<div class="updated"><p>' . __( 'Network Settings Updated', 'mycred' ) . '</p></div>'; ?>

		<p><?php echo sprintf( __( 'Configure network settings for %s.', 'mycred' ), $name ); ?></p>
		<form method="post" action="" class="">
			<input type="hidden" name="mycred-token" value="<?php echo wp_create_nonce( 'mycred' ); ?>" />
			<div class="list-items expandable-li" id="accordion">
				<h4 style="color:#333;" class="ui-accordion-header ui-helper-reset ui-state-default ui-accordion-icons ui-accordion-header-active ui-state-active ui-corner-top"><?php _e( 'Settings', 'mycred' ); ?></h4>
				<div class="body ui-accordion-content ui-helper-reset ui-widget-content ui-corner-bottom ui-accordion-content-active" style="display:block;">
					<label class="subheader"><?php _e( 'Master Template', 'mycred' ); ?></label>
					<ol id="myCRED-network-">
						<li>
							<input type="radio" name="mycred_network[master]" id="myCRED-network-overwrite-" <?php checked( $prefs['master'], true ); ?> value="1" /> 
							<label for="myCRED-network-"><?php _e( 'Yes', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="radio" name="mycred_network[master]" id="myCRED-network-overwrite-" <?php checked( $prefs['master'], false ); ?> value="0" /> 
							<label for="myCRED-network-"><?php _e( 'No', 'mycred' ); ?></label>
						</li>
						<li>
							<p class="description"><?php echo sprintf( __( 'If enabled, your main site\'s %s setup will be used for all other sites.', 'mycred' ), $name ); ?></p>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Site Block', 'mycred' ); ?></label>
					<ol id="myCRED-network-">
						<li>
							<div class="h2"><input type="text" name="mycred_network[block]" id="myCRED-network-block" value="<?php echo $prefs['block']; ?>" class="long" /></div>
							<span class="description"><?php echo sprintf( __( 'Comma separated list of blog ids where %s is to be disabled.', 'mycred' ), $name ); ?></span>
						</li>
					</ol>
					<?php do_action( 'mycred_network_prefs', $this ); ?>

				</div>
				<?php do_action( 'mycred_after_network_prefs', $this ); ?>

			</div>
			<p><?php submit_button( __( 'Save Network Settings', 'mycred' ) ); ?></p>
		</form>	
		<?php do_action( 'mycred_bottom_network_page', $this ); ?>

	</div>
<?php
		}

		/**
		 * Save Network Settings
		 * @since 0.1
		 * @version 1.0
		 */
		protected function save_network_prefs() {
			if ( !wp_verify_nonce( $_POST['mycred-token'], 'mycred' ) ) return;

			global $mycred_network;

			$new_settings['master'] = ( isset( $_POST['mycred_network']['master'] ) ) ? (bool) $_POST['mycred_network']['master'] : 0;
			$new_settings['block'] = sanitize_text_field( $_POST['mycred_network']['block'] );

			$new_settings = apply_filters( 'mycred_save_network_prefs', $new_settings, $_POST['mycred_network'], $this->core );

			// Update Network Settings
			update_site_option( 'mycred_network', $new_settings );

			$mycred_network['update'] = true;
		}
	}
	$network = new myCRED_Network();
	$network->load();
}
?>