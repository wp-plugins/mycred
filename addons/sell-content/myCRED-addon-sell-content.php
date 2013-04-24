<?php
/**
 * Addon: Sell Content
 * Addon URI: http://mycred.merovingi.com
 * Version: 1.0
 * Description: This add-on allows you to sell posts, pages or any public post types on your website. You can either sell the entire content or using our shortcode, sell parts of your content allowing you to offer "teasers".
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( !defined( 'myCRED_VERSION' ) ) exit;
define( 'myCRED_SELL',         __FILE__ );
define( 'myCRED_SELL_VERSION', myCRED_VERSION . '.1' );
/**
 * myCRED_Sell_Content class
 *
 * 
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Sell_Content' ) ) {
	class myCRED_Sell_Content extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Sell_Content', array(
				'module_name' => 'sell_content',
				'register'    => false,
				'defaults'    => array(
					'post_types' => 'post,page',
					'pay'        => 'none',
					'pay_percent' => 100,
					'templates'  => array(
						'members'    => __( '<p>Buy this %post_type% for only %price% %buy_button%</p>', 'mycred' ),
						'visitors'   => __( '<p><a href="%login_url_here%">Login</a> to buy access to this %post_type%.</p>', 'mycred' ),
						'cantafford' => __( "<p>You do not have enough %plural% to buy access to this %post_type%.</p>\n<p><strong>Price</strong>: %price%</p>", 'mycred' )
					),
					'defaults'   => array(
						'price'                 => 10,
						'overwrite_price'       => 0,
						'button_label'          => __( 'Buy Now', 'mycred' ),
						'overwrite_buttonlabel' => 0
					),
					'logs'       => array(
						'buy'  => __( 'Purchase of %link_with_title%', 'mycred' ),
						'sell' => __( 'Sale of %link_with_title%', 'mycred' )
					)
				),
				'add_to_core' => true
			) );

			add_action( 'mycred_help',           array( $this, 'help' ), 10, 2 );
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_init() {
			$this->make_purchase();

			add_filter( 'the_content',          array( $this, 'the_content' ), 30  );
			add_shortcode( 'mycred_sell_this',  array( $this, 'render_shortcode' ) );

			add_action( 'add_meta_boxes',       array( $this, 'add_metabox' )      );
			add_action( 'save_post',            array( $this, 'save_metabox' )     );

			add_action( 'mycred_admin_enqueue', array( $this, 'admin_enqueue' )    );
		}

		/**
		 * Make Purchase
		 * @since 0.1
		 * @version 1.0
		 */
		public function make_purchase() {
			global $mycred_content_purchase;

			$mycred_content_purchase = false;
			if ( !$this->is_installed() ) return;
			if ( !isset( $_POST['mycred_purchase_token'] ) || !isset( $_POST['mycred_purchase'] ) || !isset( $_POST['mycred_purchase']['action'] ) || !isset( $_POST['mycred_purchase']['author'] ) || !isset( $_POST['mycred_purchase']['post_id'] ) || !isset( $_POST['mycred_purchase']['post_type'] ) || !isset( $_POST['mycred_purchase']['user_id'] ) ) return;
			if ( !wp_verify_nonce( $_POST['mycred_purchase_token'], 'buy-content' ) ) return;

			$action = $_POST['mycred_purchase']['action'];
			$post_id = $_POST['mycred_purchase']['post_id'];
			$post_type = $_POST['mycred_purchase']['post_type'];
			$user_id = $_POST['mycred_purchase']['user_id'];
			$author = $_POST['mycred_purchase']['author'];

			$sell_content = $this->sell_content;
			$prefs = $this->get_sale_prefs( $post_id );

			$request = compact( 'action', 'post_id', 'user_id', 'author', 'post_type', 'settings', 'sales_preference' );
			do_action( 'mycred_sell_content_purchase_request', $request );

			if ( is_user_logged_in() && !$this->user_paid( $user_id, $post_id ) && $this->user_can_buy( $user_id, $prefs['price'] ) ) {
				// Charge
				$log = $sell_content['logs']['buy'];
				$data = array(
					'ref_type'    => 'post',
					'purchase_id' => 'TXID' . date_i18n( 'U' ),
					'seller'      => $author
				);
				$this->core->add_creds( 'buy_content', $user_id, '-' . $prefs['price'], $log, $post_id, $data );

				do_action( 'mycred_sell_content_purchase_ready', $request );

				// Pay
				if ( $sell_content['pay'] == 'author' ) {
					$content_price = $prefs['price'];
					// If we are paying the author less then 100%
					if ( (int) $sell_content['pay_percent'] != 100 ) {
						$percent = (int) $sell_content['pay_percent']/100;
						$price = $percent*$content_price;
						$content_price = number_format( $price, $this->core->format['decimals'] );
					}
					$log = $sell_content['logs']['sell'];
					$data = array(
						'ref_type'    => 'post',
						'purchase_id' => 'TXID' . date_i18n( 'U' ),
						'buyer'       => $user_id
					);
					$this->core->add_creds( 'buy_content', $author, $content_price, $log, $post_id, $data );
				}

				$mycred_content_purchase = true;
				do_action( 'mycred_sell_content_payment_complete', $request );
			}
		}

		/**
		 * Enqueue Admin
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_enqueue() {
			wp_register_style(
				'mycred-buy-edit',
				plugins_url( 'css/edit.css', myCRED_SELL ),
				false,
				myCRED_SELL_VERSION . '.1',
				'all'
			);

			$screen = get_current_screen();
			$sell_content = $this->sell_content;
			$post_types = $sell_content['post_types'];
			if ( !empty( $post_types ) ) {
				$pts = explode( ',', $post_types );
				if ( !in_array( $screen->id , $pts ) ) return;

				wp_enqueue_style( 'mycred-buy-edit' );
			}
		}

		/**
		 * Settings Page
		 * @since 0.1
		 * @version 1.0
		 */
		public function after_general_settings( $all ) {
			$sell_content = $this->sell_content;

			$before = $this->core->before;
			$after = $this->core->after;

			$payees = array(
				'none'   => __( 'No Payout. Just charge.' ),
				'author' => __( 'Pay Content Author.' )
			);
			$available_payees = apply_filters( 'mycred_sell_content_payees', $payees, $sell_content ); ?>

				<h4 style="color:#BBD865;"><?php _e( 'Sell Content', 'mycred' ); ?></h4>
				<div class="body" style="display:none;">
					<label class="subheader" for="<?php echo $this->field_id( 'post_types' ); ?>"><?php _e( 'Post Types', 'mycred' ); ?></label>
					<ol id="myCRED-buy-postypes">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'post_types' ); ?>" id="<?php echo $this->field_id( 'post_types' ); ?>" value="<?php echo $sell_content['post_types']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Comma separated list of post types that can be sold.', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Payments', 'mycred' ); ?></label>
					<ol id="myCRED-buy-payments">
<?php
			if ( !empty( $available_payees ) ) {
				foreach ( $available_payees as $key => $description ) { ?>

						<li>
							<input type="radio" name="<?php echo $this->field_name( 'pay' ); ?>" id="<?php echo $this->field_id( array( 'pay' => $key ) ); ?>" <?php checked( $sell_content['pay'], $key ); ?> value="<?php echo $key; ?>" />
							<label for="<?php echo $this->field_id( array( 'pay' => $key ) ); ?>"><?php echo $description; ?></label>
						</li>
<?php
					if ( $key == 'author' ) { ?>

						<li>
							<label for="<?php echo $this->field_id( 'pay_percent' ); ?>"><?php _e( 'Percentage to pay Author', 'mycred' ); ?></label>
							<div class="h2"><input type="text" size="5" maxlength="3" name="<?php echo $this->field_name( 'pay_percent' ); ?>" id="<?php echo $this->field_id( 'pay_percent' ); ?>" value="<?php echo $sell_content['pay_percent']; ?>" /> %</div>
							<span class="description"><?php _e( 'Percentage of the price to pay the author. Can not be zero and is ignored if authors are not paid.', 'mycred' ); ?></span>
						</li>
<?php
					}
				}
			} ?>

					</ol>
					<label class="subheader"><?php _e( 'Defaults', 'mycred' ); ?></label>
					<ol id="myCRED-buy-defaults">
						<li>
							<label for="<?php echo $this->field_id( array( 'defaults' => 'price' ) ); ?>"><?php _e( 'Price', 'mycred' ); ?></label>
							<div class="h2"><?php echo $before; ?> <input type="text" name="<?php echo $this->field_name( array( 'defaults' => 'price' ) ); ?>" id="<?php echo $this->field_id( array( 'defaults' => 'price' ) ); ?>" value="<?php echo $sell_content['defaults']['price']; ?>" size="8" /> <?php echo $after; ?></div>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'defaults' => 'overwrite_price' ) ); ?>" id="<?php echo $this->field_id( array( 'defaults' => 'overwrite_price' ) ); ?>" <?php checked( $sell_content['defaults']['overwrite_price'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'defaults' => 'overwrite_price' ) ); ?>"><?php _e( 'Allow authors to change price.', 'mycred' ); ?></label>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'defaults' => 'button_label' ) ); ?>"><?php _e( 'Button Label', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'defaults' => 'button_label' ) ); ?>" id="<?php echo $this->field_id( array( 'defaults' => 'button_label' ) ); ?>" value="<?php echo $sell_content['defaults']['button_label']; ?>" size="12" /></div>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'defaults' => 'overwrite_buttonlabel' ) ); ?>" id="<?php echo $this->field_id( array( 'defaults' => 'overwrite_buttonlabel' ) ); ?>" <?php checked( $sell_content['defaults']['overwrite_buttonlabel'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'defaults' => 'overwrite_buttonlabel' ) ); ?>"><?php _e( 'Allow authors to change button label.', 'mycred' ); ?></label>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'templates' => 'visitors' ) ); ?>"><?php _e( 'Sale Template for non members', 'mycred' ); ?></label>
					<ol id="myCRED-buy-template-visitors">
						<li>
							<textarea rows="10" cols="50" name="<?php echo $this->field_name( array( 'templates' => 'visitors' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'visitors' ) ); ?>" class="large-text code"><?php echo $sell_content['templates']['visitors']; ?></textarea>
							<span class="description"><?php _e( 'Do <strong>not</strong> use the %buy_button% in this template as a user must be logged in to buy content!', 'mycred' ); ?><br />
							<?php _e( 'Available template tags are: %singular%, %plural%, %post_title%, %post_url%, %link_with_title%, %price%', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'templates' => 'members' ) ); ?>"><?php _e( 'Sale Template for members', 'mycred' ); ?></label>
					<ol id="myCRED-buy-template-members">
						<li>
							<textarea rows="10" cols="50" name="<?php echo $this->field_name( array( 'templates' => 'members' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'members' ) ); ?>" class="large-text code"><?php echo $sell_content['templates']['members']; ?></textarea>
							<span class="description"><?php _e( 'Your template must contain the %buy_button% tag for purchases to work!', 'mycred' ); ?><br />
							<?php _e( 'Available template tags are: %singular%, %plural%, %post_title%, %post_url%, %link_with_title%, %buy_button%, %price%', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'templates' => 'cantafford' ) ); ?>"><?php _e( 'Insufficient funds template', 'mycred' ); ?></label>
					<ol id="myCRED-buy-template-insufficient">
						<li>
							<textarea rows="10" cols="50" name="<?php echo $this->field_name( array( 'templates' => 'cantafford' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'cantafford' ) ); ?>" class="large-text code"><?php echo $sell_content['templates']['cantafford']; ?></textarea>
							<span class="description"><?php _e( 'Your template must contain the %buy_button% tag for purchases to work!', 'mycred' ); ?><br />
							<?php _e( 'Available template tags are: %singular%, %plural%, %post_title%, %post_url%, %link_with_title%, %buy_button%, %price%', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'logs' => 'buy' ) ); ?>"><?php _e( 'Log template for Purchases', 'mycred' ); ?></label>
					<ol id="myCRED-buy-template-purchase">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'logs' => 'buy' ) ); ?>" id="<?php echo $this->field_id( array( 'logs' => 'buy' ) ); ?>" value="<?php echo $sell_content['logs']['buy']; ?>" class="long" /></div>
								<span class="description"><?php _e( 'Available template tags are: %singular%, %plural%, %post_title%, %post_url% or %link_with_title%', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( array( 'logs' => 'sell' ) ); ?>"><?php _e( 'Log template for Sales', 'mycred' ); ?></label>
					<ol id="myCRED-buy-template-sale">
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'logs' => 'sell' ) ); ?>" id="<?php echo $this->field_id( array( 'logs' => 'sell' ) ); ?>" value="<?php echo $sell_content['logs']['sell']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags are: %singular%, %plural%, %post_title%, %post_url% or %link_with_title%', 'mycred' ); ?></span>
						</li>
					</ol>
				</div>
<?php
		}

		/**
		 * Sanitize & Save Settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $general ) {
			// Post Types
			$settings = $data['sell_content'];

			$new_data['sell_content']['post_types'] = sanitize_text_field( $settings['post_types'] );
			$new_data['sell_content']['pay'] = sanitize_text_field( $settings['pay'] );
			$new_data['sell_content']['pay_percent'] = abs( $settings['pay_percent'] );
			if ( $new_data['sell_content']['pay_percent'] == 0 || $new_data['sell_content']['pay_percent'] > 100 )
				$new_data['sell_content']['pay_percent'] = 100;

			$new_data['sell_content']['defaults']['price'] = $this->core->number( $settings['defaults']['price'] );
			$new_data['sell_content']['defaults']['overwrite_price'] = ( isset( $settings['defaults']['overwrite_price'] ) ) ? 1 : 0;
			$new_data['sell_content']['defaults']['button_label'] = sanitize_text_field( $settings['defaults']['button_label'] );
			$new_data['sell_content']['defaults']['overwrite_buttonlabel'] = ( isset( $settings['defaults']['overwrite_buttonlabel'] ) ) ? 1 : 0;

			$new_data['sell_content']['templates']['members'] = trim( $settings['templates']['members'] );
			$new_data['sell_content']['templates']['visitors'] = trim( $settings['templates']['visitors'] );
			$new_data['sell_content']['templates']['cantafford'] = trim( $settings['templates']['cantafford'] );

			$new_data['sell_content']['logs']['buy'] = sanitize_text_field( $settings['logs']['buy'] );
			$new_data['sell_content']['logs']['sell'] = sanitize_text_field( $settings['logs']['sell'] );

			unset( $settings );
			return $new_data;
		}

		/**
		 * Add Meta Box to Content
		 * @since 0.1
		 * @version 1.0
		 */
		public function add_metabox() {
			$sell_content = $this->sell_content;
			$post_types = explode( ',', $sell_content['post_types'] );
			foreach ( (array) $post_types as $post_type ) {
				$post_type = trim( $post_type );
				add_meta_box(
					'mycred_sell_content',
					__( 'myCRED Sell', 'mycred' ),
					array( $this, 'metabox' ),
					$post_type,
					'side',
					'high'
				);
			}
		}

		/**
		 * Sale Preference
		 * Returns a given posts sale preferences. If none exists a new one is buildt and returned.
		 * 
		 * @return (array) sales settings
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_sale_prefs( $post_id ) {
			$sell_content = $this->sell_content;
			$prefs = get_post_meta( $post_id, 'myCRED_sell_content', true );
			if ( empty( $prefs ) ) {
				$sales_data = array(
					'status'       => 'disabled',
					'price'        => $sell_content['defaults']['price'],
					'button_label' => $sell_content['defaults']['button_label']
				);
			}
			else {
				$sales_data = $prefs;
			}

			return $sales_data;
		}

		/**
		 * Sell Meta Box
		 * @since 0.1
		 * @version 1.0
		 */
		public function metabox( $post ) {
			// Make sure add-on has been setup
			if ( !$this->is_installed() ) {
				echo __( '<strong>my</strong>CRED Sell Content needs to be setup before you can use this feature.', 'mycred' );
				// Settings Link
				if ( $this->core->can_edit_plugin( get_current_user_id() ) )
					echo ' <a href="' . admin_url( 'admin.php?page=myCRED_page_settings' ) . '" title="' . __( 'Setup add-on', 'mycred' ) . '">' . __( 'Lets do it', 'mycred' ) . '</a>';

				return;
			}
			$admin = false;
			$post_id = $post->ID;
			$post_type = $post->post_type;

			$user_id = get_current_user_id();
			$sell_content = $this->sell_content;
			$sales_data = $this->get_sale_prefs( $post_id );

			// Mark admins
			if ( $this->core->can_edit_plugin( $user_id ) )
				$admin = true;

			// Empty $sales_data means disabled same if the status is actually set to "disabled"
			if ( empty( $sales_data ) || ( isset( $sales_data['status'] ) && $sales_data['status'] == 'disabled' ) ) {
				$style = 'display:none;';
				$status = 'disabled';
			}
			else {
				$style = 'display:block;';
				$status = 'enabled';
			}

			$op = (bool) $sell_content['defaults']['overwrite_price'];
			$ob = (bool) $sell_content['defaults']['overwrite_buttonlabel']; ?>

	<p><input type="checkbox" name="mycred_sell_this" id="mycred-sell-this"<?php checked( $status, 'enabled' ); ?> value="enabled" /><label for="mycred-sell-this"><?php echo __( 'Enable sale of this ', 'mycred' ) . $post_type . '.'; ?></label></p>
	<div id="mycred-sale-settings" style="<?php echo $style; ?>">
		<input type="hidden" name="mycred-sell-this-token" value="<?php echo wp_create_nonce( 'mycred-sell-this' ); ?>" />
		<input type="hidden" name="mycred-sell-this-status" value="<?php echo $status; ?>" />
		<ul>
			<li>
				<label for="mycred-buy-prefs-"><?php _e( 'Price', 'mycred' ); ?></label>
				<div class="formated"><?php echo $this->core->before; ?> <input type="text" name="myCRED_sell_content[price]" id="mycred-buy-prefs-price" value="<?php echo $sales_data['price']; ?>" <?php if ( $op === false && !$admin ) echo 'disabled="disabled" class="disabled"'; ?> size="5" /> <?php echo $this->core->after; ?></div>
			</li>
			<li>
				<label for="mycred-buy-prefs-"><?php _e( 'Button Label', 'mycred' ); ?></label>
				<input type="text" name="myCRED_sell_content[button_label]" id="mycred-buy-prefs-" value="<?php echo $sales_data['button_label']; ?>" <?php if ( $ob === false && !$admin ) echo 'disabled="disabled" class="disabled"'; ?> />
			</li>
		</ul>
	</div>
	<script type="text/javascript">//<![CDATA[
		jQuery(function($) {
			$('#mycred-sell-this').click(function(){
					$('#mycred-sale-settings').toggle();
				});
		});//]]>
	</script>
<?php
		}

		/**
		 * Save Sell Meta Box
		 * @since 0.1
		 * @version 1.0
		 */
		public function save_metabox( $post_id ) {
			// Make sure sale is enabled
			if ( !isset( $_POST['mycred-sell-this-status'] ) || !isset( $_POST['mycred-sell-this-token'] ) ) return $post_id;

			// Verify token
			if ( wp_verify_nonce( $_POST['mycred-sell-this-token'], 'mycred-sell-this' ) === false ) return $post_id;

			// Status
			if ( !isset( $_POST['mycred_sell_this'] ) )
				$status = 'disabled';
			else
				$status = 'enabled';

			$prefs = get_post_meta( $post_id, 'myCRED_sell_content', true );
			// If sale has never been set and is not enabled bail
			if ( empty( $prefs ) && $status == 'disabled' ) return $post_id;

			$sell_content = $this->sell_content;
			$is_admin = $this->core->can_edit_plugin();

			// Status
			$prefs['status'] = $status;

			// Prefs
			$op = (bool) $sell_content['defaults']['overwrite_price'];
			$prefs['price'] = ( $op === true || $is_admin === true ) ? $_POST['myCRED_sell_content']['price'] : $sell_content['defaults']['price'];

			$ob = (bool) $sell_content['defaults']['overwrite_buttonlabel'];
			$prefs['button_label'] = ( $ob === true || $is_admin === true ) ? $_POST['myCRED_sell_content']['button_label'] : $sell_content['defaults']['button_label'];

			update_post_meta( $post_id, 'myCRED_sell_content', $prefs );
		}

		/**
		 * For Sale
		 * Checks if a given post is for sale.
		 * 
		 * @param $post_id (int) required post id
		 * @returns (bool) true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function for_sale( $post_id ) {
			$prefs = get_post_meta( $post_id, 'myCRED_sell_content', true );
			if ( !empty( $prefs ) && isset( $prefs['status'] ) && $prefs['status'] == 'enabled' ) return true;

			return false;
		}

		/**
		 * User Paid
		 * Checks if a given user has paid for a specific post.
		 * Will return true if the user can edit this plugin or creds.
		 *
		 * @param $user_id (int) required user id
		 * @param $post_id (int) required post id
		 * @returns (bool) true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function user_paid( $user_id, $post_id ) {
			if ( $this->core->can_edit_plugin( $user_id ) || $this->core->can_edit_creds( $user_id ) ) return true;

			global $wpdb;

			$sql = "SELECT ID FROM " . $wpdb->prefix . 'myCRED_log' . " WHERE user_id = %d AND ref = %s AND ref_id = %d ";
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, 'buy_content', $post_id ) );
			if ( $wpdb->num_rows == 1 ) return true;

			return false;
		}

		/**
		 * User Can Buy
		 * Checks if a given user can afford the given price.
		 *
		 * @param $user_id (int) required user id
		 * @param $price (int|float) required price to check
		 * @returns (bool) true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function user_can_buy( $user_id, $price ) {
			$balance = $this->core->get_users_cred( $user_id );
			if ( $balance-$price < 0 ) return false;
			return true;
		}

		/**
		 * Get Button
		 * Replaces the %buy_button% template tag with the submit button along
		 * with the set button label. If no template tag is found one is inserted in the end of the given string.
		 *
		 * @param $text (string) text to check for template tag.
		 * @param $post (object) optional post object to allow post template tags.
		 * @returns (string) formated string.
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_button( $text, $post ) {
			$sell_content = $this->sell_content;
			$prefs = $this->get_sale_prefs( $post->ID );

			// Button Label
			if ( isset( $prefs['button_label'] ) )
				$button_text = $prefs['button_label'];
			else
				$button_text = $sell_content['defaults']['button_label'];

			// Button element
			$button = '<input type="submit" name="mycred-buy-button" id="mycred-buy-button" value="' . $this->core->template_tags_post( $button_text, $post ) . '" class="button large" />';

			// Make sure there is a button
			if ( !preg_match( '/%buy_button%/', $text ) )
				$text .= ' %buy_button% ';

			$content = str_replace( '%buy_button%', $button, $text );

			return $content;
		}

		/**
		 * The Content Overwrite
		 * If the current post is set for sale we apply the appropirate template.
		 * Uses 3 different templates. a) Visitors Template b) Members Template and c) Cant Afford Template
		 *
		 * @returns (string) content
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function the_content( $content ) {
			global $mycred_content_purchase;

			// If content is for sale
			if ( isset( $GLOBALS['post']->ID ) && $this->for_sale( $GLOBALS['post']->ID ) ) {
				// Prep
				$post_id = $GLOBALS['post']->ID;
				$user_id = get_current_user_id();
				$sell_content = $this->sell_content;
				$prefs = $this->get_sale_prefs( $post_id );

				// Visitors
				if ( !is_user_logged_in() ) {
					$template = $sell_content['templates']['visitors'];
					
					$template = str_replace( '%price%', $this->core->format_creds( $prefs['price'] ), $template );
					$template = $this->core->template_tags_post( $template, $GLOBALS['post'] );
					return '<div class="mycred-content-forsale">' . $template . '</div>';
				}

				// We are logged in, have not purchased this item and can make a purchase
				elseif ( is_user_logged_in() && !$this->user_paid( $user_id, $post_id ) && $this->user_can_buy( $user_id, $prefs['price'] ) ) {
					$template = $sell_content['templates']['members'];

					$template = str_replace( '%price%', $this->core->format_creds( $prefs['price'] ), $template );
					$template = $this->core->template_tags_post( $template, $GLOBALS['post'] );
					$template = $this->get_button( $template, $GLOBALS['post'] );
					return '
<form action="" method="post">
	<input type="hidden" name="mycred_purchase[post_id]" id="" value="' . $post_id . '" />
	<input type="hidden" name="mycred_purchase[post_type]" id="" value="' . $GLOBALS['post']->post_type . '" />
	<input type="hidden" name="mycred_purchase[user_id]" id="" value="' . get_current_user_id() . '" />
	<input type="hidden" name="mycred_purchase[author]" id="" value="' . $GLOBALS['post']->post_author . '" />
	<input type="hidden" name="mycred_purchase_token" id="" value="' . wp_create_nonce( 'buy-content' ) . '" />
	<input type="hidden" name="mycred_purchase[action]" id="" value="buy" />
	<div class="mycred-content-forsale">' . $template . '</div>
</form>';
				}
				// We are logged in, have not purchased this item and can not afford to buy this
				elseif ( is_user_logged_in() && !$this->user_paid( $user_id, $post_id ) && !$this->user_can_buy( $user_id, $prefs['price'] ) ) {
					$template = $sell_content['templates']['cantafford'];

					$template = str_replace( '%price%', $this->core->format_creds( $prefs['price'] ), $template );
					$template = $this->core->template_tags_post( $template, $GLOBALS['post'] );
					return '<div class="mycred-content-forsale">' . $template . '</div>';
				}
			}

			// Mark purchases
			if ( $mycred_content_purchase === true ) {
				$thank_you = __( 'Thank you for your purchase!', 'mycred' );
				$wrapper = '<div id="mycred-thank-you"><p>' . $thank_you . '</p></div>';
				$content = $wrapper . $content;
			}

			return $content;
		}

		/**
		 * Render Shortcode
		 * Just as protecting the entire content, the sell_this_myCRED shortcode protects
		 * parts of the content.
		 *
		 * @returns (string) content
		 * @since 0.1
		 * @version 1.0
		 */
		public function render_shortcode( $atts, $content ) {
			$post_id = $GLOBALS['post']->ID;
			$user_id = get_current_user_id();
			$sell_content = $this->sell_content;

			$prefs = shortcode_atts( array(
				'price'        => $sell_content['defaults']['price'],
				'button_label' => $sell_content['defaults']['button_label']
			), $atts );
			$sales_prefs = $this->get_sale_prefs( $post_id );

			// If we are not using defaults save these settings.
			if ( $sales_prefs['price'] != $prefs['price'] || $sales_prefs['button_label'] != $prefs['button_label'] ) {
				update_post_meta( $post_id, 'myCRED_sell_content', array(
					'price'        => $prefs['price'],
					'status'       => $sales_prefs['status'],
					'button_label' => $prefs['button_label']
				) );
			}

			// Not logged in
			if ( !is_user_logged_in() ) {
				$template = $sell_content['templates']['visitors'];

				$template = str_replace( '%price%', $this->core->format_creds( $prefs['price'] ), $template );
				$template = $this->core->template_tags_post( $template, $GLOBALS['post'] );
				unset( $content );
				return '<div class="mycred-content-forsale">' . $template . '</div>';
			}

			// Can buy
			elseif ( is_user_logged_in() && !$this->user_paid( $user_id, $post_id ) && $this->user_can_buy( $user_id, $prefs['price'] ) ) {
				$template = $sell_content['templates']['members'];

				$template = str_replace( '%price%', $this->core->format_creds( $prefs['price'] ), $template );
				$template = $this->core->template_tags_post( $template, $GLOBALS['post'] );
				$template = $this->get_button( $template, $GLOBALS['post'] );
				unset( $content );
				return '
<form action="" method="post">
	<input type="hidden" name="mycred_purchase[post_id]" id="" value="' . $post_id . '" />
	<input type="hidden" name="mycred_purchase[post_type]" id="" value="' . $GLOBALS['post']->post_type . '" />
	<input type="hidden" name="mycred_purchase[user_id]" id="" value="' . get_current_user_id() . '" />
	<input type="hidden" name="mycred_purchase[author]" id="" value="' . $GLOBALS['post']->post_author . '" />
	<input type="hidden" name="mycred_purchase_token" id="" value="' . wp_create_nonce( 'buy-content' ) . '" />
	<input type="hidden" name="mycred_purchase[action]" id="" value="buy" />
	<div class="mycred-content-forsale">' . $template . '</div>
</form>';
			}

			// We are logged in, have not purchased this item and can not afford to buy this
			elseif ( is_user_logged_in() && !$this->user_paid( $user_id, $post_id ) && !$this->user_can_buy( $user_id, $prefs['price'] ) ) {
				$template = $sell_content['templates']['cantafford'];
					
				$template = str_replace( '%price%', $this->core->format_creds( $prefs['price'] ), $template );
				$template = $this->core->template_tags_post( $template, $GLOBALS['post'] );
				unset( $content );
				return '<div class="mycred-content-forsale">' . $template . '</div>';
			}

			return $content;
		}

		/**
		 * Contextual Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function help( $screen_id, $screen ) {
			if ( $screen_id != 'mycred_page_myCRED_page_settings' ) return;

			$screen->add_help_tab( array(
				'id'		=> 'mycred-sell-content',
				'title'		=> __( 'Sell Content', 'mycred' ),
				'content'	=> '
<p>' . $this->core->template_tags_general( __( 'This add-on lets you sell either entire contents or parts of it. You can select if you want to just charge users or share a percentage of the sale with the post author.', 'mycred' ) ) . '</p>
<p><strong>' . __( 'Defaults', 'mycred' ) . '</strong></p>
<p>' . __( 'The default price and button label is applied to all content that is set for sale. You can select if you want to enforce these settings or let the content authors set their own.', 'mycred' ) . '</p>
<p><strong>' . __( 'Usage', 'mycred' ) . '</strong></p>
<p>' . __( 'You can either sell entire posts via the Sell Content Meta Box or by using the <code>mycred_sell_this</code> shortcode.<br />For more information on how to use the shortcode, please visit the', 'mycred' ) . ' <a href="http://mycred.merovingi.com/shortcodes/mycred_sell_this/" target="_blank">myCRED Codex</a>.</p>'
			) );
		}
	}
	$sell_content = new myCRED_Sell_Content();
	$sell_content->load();
}
?>