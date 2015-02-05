<?php
/**
 * Addon: Coupons
 * Addon URI: http://mycred.me/add-ons/coupons/
 * Version: 1.0
 * Description: Create coupons that your users can use to add points to their accounts.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_COUPONS',         __FILE__ );
define( 'myCRED_COUPONS_DIR',     myCRED_ADDONS_DIR . 'coupons/' );
define( 'myCRED_COUPONS_VERSION', myCRED_VERSION . '.1' );

include_once( myCRED_COUPONS_DIR . 'includes/mycred-coupon-functions.php' );
include_once( myCRED_COUPONS_DIR . 'includes/mycred-coupon-shortcodes.php' );

/**
 * myCRED_Coupons_Module class
 * @since 1.4
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Coupons_Module' ) ) {
	class myCRED_Coupons_Module extends myCRED_Module {

		public $instances = array();

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Coupons_Module', array(
				'module_name' => 'coupons',
				'defaults'    => array(
					'log'         => 'Coupon redemption',
					'invalid'     => 'This is not a valid coupon',
					'expired'     => 'This coupon has expired',
					'user_limit'  => 'You have already used this coupon',
					'min'         => 'A minimum of %min% is required to use this coupon',
					'max'         => 'A maximum of %max% is required to use this coupon',
					'success'     => 'Coupon successfully deposited into your account'
				),
				'register'    => false,
				'add_to_core' => true
			) );

			add_filter( 'mycred_parse_log_entry_coupon', array( $this, 'parse_log_entry' ), 10, 2 );
		}

		/**
		 * Hook into Init
		 * @since 1.4
		 * @version 1.0
		 */
		public function module_init() {
			$this->register_post_type();

			add_shortcode( 'mycred_load_coupon', 'mycred_render_shortcode_load_coupon' );
		}

		/**
		 * Hook into Admin Init
		 * @since 1.4
		 * @version 1.1
		 */
		public function module_admin_init() {
			add_filter( 'post_updated_messages', array( $this, 'update_messages' ) );

			add_filter( 'manage_mycred_coupon_posts_columns',       array( $this, 'adjust_column_headers' ) );
			add_action( 'manage_mycred_coupon_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );

			add_filter( 'enter_title_here',        array( $this, 'enter_title_here' )      );
			add_filter( 'post_row_actions',        array( $this, 'adjust_row_actions' ), 10, 2 );
			add_action( 'add_meta_boxes',          array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post_mycred_coupon', array( $this, 'update_coupon_details' ) );
		}

		/**
		 * Register Coupons Post Type
		 * @since 1.4
		 * @version 1.0
		 */
		protected function register_post_type() {
			$labels = array(
				'name'               => __( 'Coupons', 'mycred' ),
				'singular_name'      => __( 'Coupon', 'mycred' ),
				'add_new'            => __( 'Create New', 'mycred' ),
				'add_new_item'       => __( 'Create New Coupon', 'mycred' ),
				'edit_item'          => __( 'Edit Coupon', 'mycred' ),
				'new_item'           => __( 'New Coupon', 'mycred' ),
				'all_items'          => __( 'Coupons', 'mycred' ),
				'view_item'          => '',
				'search_items'       => __( 'Search coupons', 'mycred' ),
				'not_found'          => __( 'No coupons found', 'mycred' ),
				'not_found_in_trash' => __( 'No coupons found in Trash', 'mycred' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Email Notices', 'mycred' )
			);
			$args = array(
				'labels'             => $labels,
				'publicly_queryable' => false,
				'show_ui'            => true, 
				'show_in_menu'       => 'myCRED',
				'capability_type'    => 'page',
				'supports'           => array( 'title' )
			);
			register_post_type( 'mycred_coupon', apply_filters( 'mycred_register_coupons', $args ) );
		}

		/**
		 * Adjust Update Messages
		 * @since 1.4
		 * @version 1.0
		 */
		public function update_messages( $messages ) {
			$messages['mycred_coupon'] = array(
				0  => '',
				1  => __( 'Coupon updated.', 'mycred' ),
				2  => __( 'Coupon updated.', 'mycred' ),
				3  => __( 'Coupon updated.', 'mycred' ),
				4  => __( 'Coupon updated.', 'mycred' ),
				5  => false,
				6  => __( 'Coupon published.', 'mycred' ),
				7  => __( 'Coupon saved.', 'mycred' ),
				8  => '',
				9  => '',
				10 => __( 'Draft Coupon saved.', 'mycred' ),
			);

  			return $messages;
		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.4
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {
			global $post_type;
			if ( $post_type == 'mycred_coupon' )
				return __( 'Unique Coupon Code', 'mycred' );

			return $title;
		}

		/**
		 * Adjust Column Header
		 * @since 1.4
		 * @version 1.1
		 */
		public function adjust_column_headers( $defaults ) {

			$columns = array();
			$columns['cb'] = $defaults['cb'];

			// Add / Adjust
			$columns['title']   = __( 'Coupon Code', 'mycred' );
			$columns['value']   = __( 'Value', 'mycred' );
			$columns['usage']   = __( 'Used', 'mycred' );
			$columns['limits']  = __( 'Limits', 'mycred' );
			$columns['expires'] = __( 'Expires', 'mycred' );

			if ( count( $this->point_types ) > 1 )
				$columns['ctype'] = __( 'Point Type', 'mycred' );

			return $columns;
		}

		/**
		 * Adjust Column Body
		 * @since 1.4
		 * @version 1.1
		 */
		public function adjust_column_content( $column_name, $post_id ) {
			global $mycred;

			switch ( $column_name ) {

				case 'value' :

					$value = mycred_get_coupon_value( $post_id );
					if ( empty( $value ) ) $value = 0;
					
					echo $mycred->format_creds( $value );

				break;

				case 'usage' :

					$count = mycred_get_global_coupon_count( $post_id );
					if ( empty( $count ) )
						_e( 'not yet used', 'mycred' );

					else {
						$set_type = get_post_meta( $post_id, 'type', true );
						$page = 'myCRED';
						if ( $set_type != 'mycred_default' && array_key_exists( $set_type, $this->point_types ) )
							$page .= '_' . $set_type;

						$url = add_query_arg( array( 'page' => $page, 'ref' => 'coupon', 'data' => get_the_title( $post_id ) ), admin_url( 'admin.php' ) );
						echo '<a href="' . $url . '">' . sprintf( __( '1 time', '%d times', $count, 'mycred' ), $count ) . '</a>';
					}

				break;

				case 'limits' :

					$total = mycred_get_coupon_global_max( $post_id );
					$user = mycred_get_coupon_user_max( $post_id );
					printf( '%1$s: %2$d<br />%3$s: %4$d', __( 'Total', 'mycred' ), $total, __( 'Per User', 'mycred' ), $user );

				break;

				case 'expires' :

					$expires = mycred_get_coupon_expire_date( $post_id, true );
					if ( empty( $expires ) || $expires === 0 )
						_e( 'Never', 'mycred' );
					else {
						if ( $expires < date_i18n( 'U' ) ) {
							wp_trash_post( $post_id );
							echo '<span style="color:red;">' . __( 'Expired', 'mycred' ) . '</span>';
						}
						else {
							echo sprintf( __( 'In %s time', 'mycred' ), human_time_diff( $expires ) ) . '<br /><small class="description">' . date_i18n( get_option( 'date_format' ), $expires ) . '</small>';
						}
					}

				break;

				case 'ctype' :

					$type = get_post_meta( $post_id, 'type', true );
					if ( isset( $this->point_types[ $type ] ) )
						echo $this->point_types[ $type ];
					else
						echo '-';

				break;

			}
		}

		/**
		 * Adjust Row Actions
		 * @since 1.4
		 * @version 1.0
		 */
		public function adjust_row_actions( $actions, $post ) {
			if ( $post->post_type == 'mycred_coupon' ) {
				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['view'] );
			}

			return $actions;
		}

		/**
		 * Parse Log Entries
		 * @since 1.4
		 * @version 1.0
		 */
		public function parse_log_entry( $content, $log_entry ) {
			return str_replace( '%coupon%', $log_entry->data, $content );
		}

		/**
		 * Add Meta Boxes
		 * @since 1.4
		 * @version 1.0
		 */
		public function add_meta_boxes() {

			global $post;

			add_meta_box(
				'mycred_coupon_setup',
				__( 'Coupon Setup', 'mycred' ),
				array( $this, 'metabox_coupon_setup' ),
				'mycred_coupon',
				'normal',
				'core'
			);

			add_meta_box(
				'mycred_coupon_limits',
				__( 'Coupon Limits', 'mycred' ),
				array( $this, 'metabox_coupon_limits' ),
				'mycred_coupon',
				'normal',
				'core'
			);

			add_meta_box(
				'mycred_coupon_requirements',
				__( 'Coupon Requirements', 'mycred' ),
				array( $this, 'mycred_coupon_requirements' ),
				'mycred_coupon',
				'side',
				'core'
			);

			if ( $post->post_status == 'publish' )
				add_meta_box(
					'mycred_coupon_usage',
					__( 'Usage', 'mycred' ),
					array( $this, 'mycred_coupon_usage' ),
					'mycred_coupon',
					'side',
					'core'
				);

		}

		/**
		 * Metabox: Coupon Setup
		 * @since 1.4
		 * @version 1.1
		 */
		public function metabox_coupon_setup( $post ) {
			global $mycred;

			$value = get_post_meta( $post->ID, 'value', true );
			if ( empty( $value ) )
				$value = 1;

			$expires = get_post_meta( $post->ID, 'expires', true );
			$set_type = get_post_meta( $post->ID, 'type', true ); ?>

<style type="text/css">
table { width: 100%; }
table th { width: 20%; text-align: right; }
table th label { padding-right: 12px; }
table td { width: 80%; padding-bottom: 6px; }
table td textarea { width: 95%; }
#submitdiv .misc-pub-curtime, #submitdiv #visibility, #submitdiv #misc-publishing-actions { display: none; }
#submitdiv #minor-publishing-actions { padding-bottom: 10px; }
<?php if ( $post->post_status == 'publish' ) : ?>
#submitdiv #minor-publishing-actions { padding: 0 0 0 0; }
<?php endif; ?>
</style>
<input type="hidden" name="mycred-coupon-nonce" value="<?php echo wp_create_nonce( 'update-mycred-coupon' ); ?>" />
<table class="table wide-fat">
	<tbody>
		<tr valign="top">
			<th scope="row"><label for="mycred-coupon-value"><?php _e( 'Value', 'mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_coupon[value]" id="mycred-coupon-value" value="<?php echo $mycred->number( $value ); ?>" /><br />
				<span class="description"><?php echo $mycred->template_tags_general( __( 'The amount of %plural% this coupon is worth.', 'mycred' ) ); ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="mycred-coupon-value"><?php _e( 'Point Type', 'mycred' ); ?></label></th>
			<td>
				<?php if ( count( $this->point_types ) > 1 ) : ?>

					<?php mycred_types_select_from_dropdown( 'mycred_coupon[type]', 'mycred-coupon-type', $set_type ); ?><br />
					<span class="description"><?php _e( 'Select the point type that this coupon is applied.', 'mycred' ); ?></span>

				<?php else : ?>

					<?php echo $this->core->plural(); ?>

				<?php endif; ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="mycred-coupon-value"><?php _e( 'Expire', 'mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_coupon[expires]" id="mycred-coupon-expire" value="<?php echo $expires; ?>" placeholder="YYYY-MM-DD" /><br />
				<span class="description"><?php _e( 'Optional date when this coupon expires. Expired coupons will be trashed.', 'mycred' ); ?></span>
			</td>
		</tr>
	</tbody>
</table>
	<?php do_action( 'mycred_coupon_after_setup', $post ); ?>

<?php
		}

		/**
		 * Metabox: Coupon Limits
		 * @since 1.4
		 * @version 1.0
		 */
		public function metabox_coupon_limits( $post ) {
			global $mycred;

			$global_max = get_post_meta( $post->ID, 'global', true );
			if ( empty( $global_max ) )
				$global_max = 1;

			$user_max = get_post_meta( $post->ID, 'user', true );
			if ( empty( $user_max ) )
				$user_max = 1; ?>

<table class="table wide-fat">
	<tbody>
		<tr valign="top">
			<th scope="row"><label for="mycred-coupon-global"><?php _e( 'Global Maximum', 'mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_coupon[global]" id="mycred-coupon-global" value="<?php echo abs( $global_max ); ?>" /><br />
				<span class="description"><?php _e( 'The maximum number of times this coupon can be used. Note that the coupon will be automatically trashed once this maximum is reached!', 'mycred' ); ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="mycred-coupon-user"><?php _e( 'User Maximum', 'mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_coupon[user]" id="mycred-coupon-user" value="<?php echo abs( $user_max ); ?>" /><br />
				<span class="description"><?php _e( 'The maximum number of times this coupon can be used by a user.', 'mycred' ); ?></span>
			</td>
		</tr>
	</tbody>
</table>
	<?php do_action( 'mycred_coupon_after_limits', $post ); ?>

<?php
		}

		/**
		 * Metabox: Coupon Requirements
		 * @since 1.4
		 * @version 1.0
		 */
		public function mycred_coupon_requirements( $post ) {
			global $mycred;

			$min_balance = get_post_meta( $post->ID, 'min_balance', true );
			if ( empty( $min_balance ) )
				$min_balance = 0;

			$max_balance = get_post_meta( $post->ID, 'max_balance', true );
			if ( empty( $max_balance ) )
				$max_balance = 0; ?>

<p>
	<label for="mycred-coupon-min_balance"><?php _e( 'Minimum Balance', 'mycred' ); ?></label><br />
	<input type="text" name="mycred_coupon[min_balance]" id="mycred-coupon-min_balance" value="<?php echo $mycred->number( $min_balance ); ?>" /><br />
	<span class="description"><?php _e( 'Optional minimum balance a user must have in order to use this coupon. Use zero to disable.', 'mycred' ); ?></span>
</p>
<p>
	<label for="mycred-coupon-max_balance"><?php _e( 'Maximum Balance', 'mycred' ); ?></label><br />
	<input type="text" name="mycred_coupon[max_balance]" id="mycred-coupon-max_balance" value="<?php echo $mycred->number( $max_balance ); ?>" /><br />
	<span class="description"><?php _e( 'Optional maximum balance a user can have in order to use this coupon. Use zero to disable.', 'mycred' ); ?></span>
</p>
<?php do_action( 'mycred_coupon_after_requirements', $post ); ?>

<?php
		}

		/**
		 * Metabox: Coupon Usage
		 * @since 1.6
		 * @version 1.0
		 */
		public function mycred_coupon_usage( $post ) {

			$count = mycred_get_global_coupon_count( $post->ID );
			if ( empty( $count ) )
				_e( 'not yet used', 'mycred' );
			else {
				$set_type = get_post_meta( $post->ID, 'type', true );
				$page = 'myCRED';
				if ( $set_type != 'mycred_default' && array_key_exists( $set_type, $this->point_types ) )
					$page .= '_' . $set_type;

				$url = add_query_arg( array( 'page' => $page, 'ref' => 'coupon', 'data' => $post->post_title ), admin_url( 'admin.php' ) );
				echo '<a href="' . $url . '">' . sprintf( __( '1 time', '%d times', $count, 'mycred' ), $count ) . '</a>';
			}

		}

		/**
		 * Update Coupon Details
		 * @since 1.4
		 * @version 1.0
		 */
		public function update_coupon_details( $post_id ) {
			if ( ! isset( $_POST['mycred-coupon-nonce'] ) || ! wp_verify_nonce( $_POST['mycred-coupon-nonce'], 'update-mycred-coupon' ) ) return $post_id;
			if ( isset( $_POST['mycred_coupon'] ) ) {
				foreach ( $_POST['mycred_coupon'] as $key => $value ) {
					$value = sanitize_text_field( $value );
					update_post_meta( $post_id, $key, $value );
				}
			}
		}

		/**
		 * Add to General Settings
		 * @since 1.4
		 * @version 1.0
		 */
		public function after_general_settings( $mycred ) {
			if ( ! isset( $this->coupons ) )
				$prefs = $this->default_prefs;
			else
				$prefs = mycred_apply_defaults( $this->default_prefs, $this->coupons );  ?>

<h4><div class="icon icon-active"></div><?php _e( 'Coupons', 'mycred' ); ?></h4>
<div class="body" style="display:none;">
	<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
	<ol id="myCRED-coupon-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Log entry for successful coupon redemption. Use %coupon% to show the coupon code.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'invalid' ); ?>"><?php _e( 'Invalid Coupon Message', 'mycred' ); ?></label>
	<ol id="myCRED-coupon-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'invalid' ); ?>" id="<?php echo $this->field_id( 'invalid' ); ?>" value="<?php echo $prefs['invalid']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Message to show when users try to use a coupon that does not exists.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'expired' ); ?>"><?php _e( 'Expired Coupon Message', 'mycred' ); ?></label>
	<ol id="myCRED-coupon-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'expired' ); ?>" id="<?php echo $this->field_id( 'expired' ); ?>" value="<?php echo $prefs['expired']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Message to show when users try to use that has expired.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'user_limit' ); ?>"><?php _e( 'User Limit Message', 'mycred' ); ?></label>
	<ol id="myCRED-coupon-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'user_limit' ); ?>" id="<?php echo $this->field_id( 'user_limit' ); ?>" value="<?php echo $prefs['user_limit']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Message to show when the user limit has been reached for the coupon.', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'min' ); ?>"><?php _e( 'Minimum Balance Message', 'mycred' ); ?></label>
	<ol id="myCRED-coupon-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'min' ); ?>" id="<?php echo $this->field_id( 'min' ); ?>" value="<?php echo $prefs['min']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Message to show when a user does not meet the minimum balance requirement. (if used)', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'max' ); ?>"><?php _e( 'Maximum Balance Message', 'mycred' ); ?></label>
	<ol id="myCRED-coupon-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'max' ); ?>" id="<?php echo $this->field_id( 'max' ); ?>" value="<?php echo $prefs['max']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Message to show when a user does not meet the maximum balance requirement. (if used)', 'mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'success' ); ?>"><?php _e( 'Success Message', 'mycred' ); ?></label>
	<ol id="myCRED-coupon-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'success' ); ?>" id="<?php echo $this->field_id( 'success' ); ?>" value="<?php echo $prefs['success']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Message to show when a coupon was successfully deposited to a users account.', 'mycred' ); ?></span>
		</li>
	</ol>
</div>
<?php
		}

		/**
		 * Save Settings
		 * @since 1.4
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			$new_data['coupons']['log'] = sanitize_text_field( $data['coupons']['log'] );
			
			$new_data['coupons']['invalid'] = sanitize_text_field( $data['coupons']['invalid'] );
			$new_data['coupons']['expired'] = sanitize_text_field( $data['coupons']['expired'] );
			$new_data['coupons']['user_limit'] = sanitize_text_field( $data['coupons']['user_limit'] );
			$new_data['coupons']['min'] = sanitize_text_field( $data['coupons']['min'] );
			$new_data['coupons']['max'] = sanitize_text_field( $data['coupons']['max'] );
			$new_data['coupons']['success'] = sanitize_text_field( $data['coupons']['success'] );

			return $new_data;
		}

	}

	$coupons = new myCRED_Coupons_Module();
	$coupons->load();

}

?>