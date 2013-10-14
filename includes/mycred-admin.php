<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Admin class
 * Manages everything concerning the WordPress admin area.
 * @since 0.1
 * @version 1.1
 */
if ( !class_exists( 'myCRED_Admin' ) ) {
	class myCRED_Admin {

		public $core;

		/**
		 * Construct
		 * @since 0.1
		 * @version 1.0
		 */
		function __construct( $settings = array() ) {
			$this->core = mycred_get_settings();
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.1
		 */
		public function load() {
			// Admin Styling
			add_action( 'admin_head',                 array( $this, 'admin_header' )                      );
			
			// Custom Columns
			add_filter( 'manage_users_columns',       array( $this, 'custom_user_column' )                );
			add_action( 'manage_users_custom_column', array( $this, 'custom_user_column_content' ), 10, 3 );
			
			// User Edit
			add_action( 'profile_personal_options',   array( $this, 'show_my_balance' ), 1                );
			add_action( 'personal_options',           array( $this, 'adjust_users_balance' ), 1           );
			add_action( 'personal_options_update',    array( $this, 'adjust_points_manually' )            );
			add_action( 'edit_user_profile_update',   array( $this, 'adjust_points_manually' )            );
			
			// Sortable Column
			add_filter( 'manage_users_sortable_columns', array( $this, 'sortable_points_column' ) );
			add_action( 'pre_user_query',                array( $this, 'sort_by_points' )         );
			
			// Inline Editing
			add_action( 'wp_ajax_mycred-inline-edit-users-balance', array( $this, 'inline_edit_user_balance' ) );
			add_action( 'in_admin_footer',                          array( $this, 'admin_footer' )             );
		}

		/**
		 * Ajax: Inline Edit Users Balance
		 * @since 1.2
		 * @version 1.0
		 */
		public function inline_edit_user_balance() {
			// Security
			check_ajax_referer( 'mycred-update-users-balance', 'token' );

			// Check current user
			$current_user = get_current_user_id();
			if ( !mycred_is_admin( $current_user ) )
				die( json_encode( array( 'status' => 'ERROR_1' ) ) );

			// User
			$user_id = abs( $_POST['user'] );
			if ( $this->core->exclude_user( $user_id ) )
				die( json_encode( array( 'status' => 'ERROR_2', 'current' => __( 'User is excluded', 'mycred' ) ) ) );

			// Log entry
			$entry = trim( $_POST['entry'] );
			if ( $this->core->can_edit_creds() && !$this->core->can_edit_plugin() && empty( $entry ) )
				die( json_encode( array( 'status' => 'ERROR_3', 'current' => __( 'Log Entry can not be empty', 'mycred' ) ) ) );

			// Amount
			if ( $_POST['amount'] == 0 || empty( $_POST['amount'] ) )
				die( json_encode( array( 'status' => 'ERROR_4', 'current' => __( 'Amount can not be zero', 'mycred' ) ) ) );
			else
				$amount = $this->core->number( $_POST['amount'] );

			// Data
			$data = apply_filters( 'mycred_manual_change', array( 'ref_type' => 'user' ), $this );

			// Execute
			$this->core->add_creds(
				'manual',
				$user_id,
				$amount,
				$entry,
				$current_user,
				$data
			);
			
			
			die( json_encode( array( 'status' => 'OK', 'current' => $this->core->get_users_cred( $user_id ) ) ) );
		}

		/**
		 * Admin Header
		 * @filter mycred_icon_menu
		 * @since 0.1
		 * @version 1.2
		 */
		public function admin_header() {
			$screen = get_current_screen();
			if ( $screen->id == 'users' && mycred_is_admin() ) {
				wp_enqueue_script( 'mycred-inline-edit' );
				wp_enqueue_style( 'mycred-inline-edit' );
			}

			$image = apply_filters( 'mycred_icon_menu', plugins_url( 'assets/images/logo-menu.png', myCRED_THIS ) );
			echo '
<style type="text/css">
#adminmenu .toplevel_page_myCRED div.wp-menu-image { background-image: url(' . $image . '); background-position: 1px -28px; }
#adminmenu .toplevel_page_myCRED:hover div.wp-menu-image, 
#adminmenu .toplevel_page_myCRED.current div.wp-menu-image, 
#adminmenu .toplevel_page_myCRED .wp-menu-open div.wp-menu-image { background-position: 1px 0; }
</style>' . "\n";
		}

		/**
		 * Customize Users Column Headers
		 * @since 0.1
		 * @version 1.0
		 */
		public function custom_user_column( $columns ) {
			$user_id = get_current_user_id();
			if ( !$this->core->can_edit_creds( $user_id ) || !$this->core->can_edit_plugin( $user_id ) ) return $columns;

			$columns['mycred-balance'] = $this->core->plural();
			return $columns;
		}

		/**
		 * Sortable User Column
		 * @since 1.2
		 * @version 1.0
		 */
		public function sortable_points_column( $columns ) {
			$columns['mycred-balance'] = 'mycred';
			return $columns;
		}

		/**
		 * Sort by Points
		 * @since 1.2
		 * @version 1.2
		 */
		public function sort_by_points( $query ) {
			if ( !is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) return;
			$screen = get_current_screen();
			if ( $screen->id != 'users' ) return;

			if ( isset( $query->query_vars['orderby'] ) && isset( $query->query_vars['order'] ) && $query->query_vars['orderby'] == 'mycred' ) {
				global $wpdb;

				$cred_id = $this->core->get_cred_id();
				$order = $query->query_vars['order'];
				$query->query_from .= " LEFT JOIN {$wpdb->usermeta} ON ({$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND {$wpdb->usermeta}.meta_key = '$cred_id')";
				$query->query_orderby = "ORDER BY {$wpdb->usermeta}.meta_value+0 $order ";
			}
		}

		/**
		 * Customize User Columns Content
		 * @filter 'mycred_user_row_actions'
		 * @since 0.1
		 * @version 1.1
		 */
		public function custom_user_column_content( $value, $column_name, $user_id ) {
			if ( 'mycred-balance' != $column_name ) return $value;

			// User is excluded
			if ( $this->core->exclude_user( $user_id ) === true ) return __( 'Excluded', 'mycred' );

			$ubalance = $this->core->get_users_cred( $user_id );
			$balance = '<div id="mycred-user-' . $user_id . '-balance">' . $this->core->before . ' <span>' . $this->core->format_number( $ubalance ) . '</span> ' . $this->core->after . '</div>';

			// Row actions
			$row = array();
			$row['history'] = '<a href="' . admin_url( 'admin.php?page=myCRED&user_id=' . $user_id ) . '">' . __( 'History', 'mycred' ) . '</a>';
			if ( $this->core->can_edit_creds( get_current_user_id() ) )
				$row['adjust'] = '<a href="javascript:void(0)" class="mycred-open-points-editor" data-userid="' . $user_id . '" data-current="' . $ubalance . '">' . __( 'Adjust', 'mycred' ) . '</a>';

			$rows = apply_filters( 'mycred_user_row_actions', $row, $user_id, $this->core );
			$balance .= $this->row_actions( $rows );
			return $balance;
		}

		/**
		 * Generate row actions div
		 *
		 * @since 3.1.0
		 * @access protected
		 *
		 * @param array $actions The list of actions
		 * @param bool $always_visible Whether the actions should be always visible
		 * @return string
		 */
		protected function row_actions( $actions, $always_visible = false ) {
			$action_count = count( $actions );
			$i = 0;

			if ( !$action_count )
				return '';

			$out = '<div class="' . ( $always_visible ? 'row-actions-visible' : 'row-actions' ) . '">';
			foreach ( $actions as $action => $link ) {
				++$i;
				( $i == $action_count ) ? $sep = '' : $sep = ' | ';
				$out .= "<span class='$action'>$link$sep</span>";
			}
			$out .= '</div>';

			return $out;
		}
		
		/**
		 * Insert Ballance into Profile
		 * @since 0.1
		 * @version 1.0
		 */
		public function show_my_balance( $user ) {
			$user_id = $user->ID;
			// Bail if this user is to be excluded
			if ( $this->core->exclude_user( $user_id ) === true ) return;

			// Users balance
			$balance = $this->core->get_users_cred( $user_id );
			$balance = $this->core->format_creds( $balance ); ?>

<table class="form-table">
<tr>
<th scope="row"><?php echo $this->core->template_tags_general( __( 'My current %singular% balance', 'mycred' ) ); ?></th>
<td>
<h2 style="margin:0;padding:0;"><?php echo $balance; ?></h2>
</td>
</tr>
</table>
<?php
		}

		/**
		 * Adjust Users Balance
		 * @since 0.1
		 * @version 1.1
		 */
		public function adjust_users_balance( $user ) {
			global $mycred_errors;
			// Editors can not edit their own creds
			if ( !$this->core->can_edit_creds() ) return;
			// Make sure we do not want to exclude this user
			if ( $this->core->exclude_user( $user->ID ) === true ) return;

			// Label
			if ( $user->ID == get_current_user_id() )
				$label = __( 'Adjust Your Balance', 'mycred' );
			else
				$label = __( 'Adjust Users Balance', 'mycred' );

			// Balance
			$balance = $this->core->get_users_cred( $user->ID );
			$balance = $this->core->format_creds( $balance );
			
			if ( $this->core->can_edit_creds() && !$this->core->can_edit_plugin() )
				$req = '(<strong>' . __( 'required', 'mycred' ) . '</strong>)'; 
			else
				$req = '(' . __( 'optional', 'mycred' ) . ')'; ?>

<tr>
<th scope="row"><label for="myCRED-manual-add-points"><?php echo $label; ?></label></th>
<td id="myCRED-adjust-users-points">
<?php echo $this->core->plural(); ?>: <input type="text" name="myCRED-manual-add-points" id="myCRED-manual-add-points" value="<?php echo $this->core->zero(); ?>" size="4" /><br /><br />
<label for="myCRED-manual-add-description"><?php _e( 'Log description for adjustment', 'mycred' ); ?> <?php echo $req; ?></label><br />
<input type="text" name="myCRED-manual-add-description" id="myCRED-manual-add-description" value="" class="regular-text" /> <?php submit_button( __( 'Update', 'mycred' ), 'primary medium', 'myCRED_update', false ); ?>
<?php if ( $mycred_errors ) echo '<p style="color:red;">' . __( 'Description is required!', 'mycred' ) . '</p>'; ?>
</td>
</tr>
<?php if ( IS_PROFILE_PAGE ) return; ?>
<tr>
<th scope="row"><?php _e( 'Users Current Balance', 'mycred' ); ?></th>
<td id="myCRED-users-balance">
<h2 style="margin:0;padding:0;"><?php echo $balance; ?></h2>
</td>
</tr>
<?php
		}

		/**
		 * Save Manual Adjustments
		 * @since 0.1
		 * @version 1.2
		 */
		public function adjust_points_manually( $user_id ) {
			global $mycred_errors;

			// All the reasons we should bail
			if ( !$this->core->can_edit_creds() ) return false;
			if ( !isset( $_POST['myCRED-manual-add-points'] ) || !isset( $_POST['myCRED-manual-add-description'] ) ) return false;
			
			// Clean up excludes
			if ( $this->core->exclude_user( $user_id ) ) {
				// If excludes has been changed since install we need to delete their points balance
				// meta to avoid them showing up in the leaderboard or other db queries.
				$balance = get_user_meta( $user_id, 'mycred_default', true );
				if ( !empty( $balance ) )
					delete_user_meta( $user_id, 'mycred_balance' );
				
				return false;
			}
			
			// Add new creds
			$cred = $_POST['myCRED-manual-add-points'];
			$entry = $_POST['myCRED-manual-add-description'];
			$data = apply_filters( 'mycred_manual_change', array( 'ref_type' => 'user' ), $this );
			
			// If person editing points can edit points but can not edit the plugin
			// a description must be set!
			if ( $this->core->can_edit_creds() && !$this->core->can_edit_plugin() ) {
				if ( empty( $entry ) ) {
					$mycred_errors = true;
					return false;
				}
			}
			
			$this->core->add_creds( 'manual', $user_id, $cred, $entry, get_current_user_id(), $data );
		}

		/**
		 * Admin Footer
		 * Inserts the Inline Edit Form modal.
		 * @since 1.2
		 * @version 1.0
		 */
		public function admin_footer() {
			if ( $this->core->can_edit_creds() && !$this->core->can_edit_plugin() )
				$req = '(<strong>' . __( 'required', 'mycred' ) . '</strong>)'; 
			else
				$req = '(' . __( 'optional', 'mycred' ) . ')'; ?>

<div id="edit-mycred-balance" style="display: none;">
	<div class="mycred-adjustment-form">
		<p class="row inline" style="width: 20%"><label><?php _e( 'ID', 'mycred' ); ?>:</label><span id="mycred-userid"></span></p>
		<p class="row inline" style="width: 40%"><label><?php _e( 'User', 'mycred' ); ?>:</label><span id="mycred-username"></span></p>
		<p class="row inline" style="width: 40%"><label><?php _e( 'Current Balance', 'mycred' ); ?>:</label> <span id="mycred-current"></span></p>
		<div class="clear"></div>
		<input type="hidden" name="mycred_update_users_balance[token]" id="mycred-update-users-balance-token" value="<?php echo wp_create_nonce( 'mycred-update-users-balance' ); ?>" />
		<p class="row"><label for="mycred-update-users-balance-amount"><?php _e( 'Amount', 'mycred' ); ?>:</label><input type="text" name="mycred_update_users_balance[amount]" id="mycred-update-users-balance-amount" value="" /><br /><span class="description"><?php _e( 'A positive or negative value', 'mycred' ); ?>.</span></p>
		<p class="row"><label for="mycred-update-users-balance-entry"><?php _e( 'Log Entry', 'mycred' ); ?>:</label><input type="text" name="mycred_update_users_balance[entry]" id="mycred-update-users-balance-entry" value="" /><br /><span class="description"><?php echo $req; ?></span></p>
		<p class="row"><input type="button" name="mycred-update-users-balance-submit" id="mycred-update-users-balance-submit" value="<?php _e( 'Update Balance', 'mycred' ); ?>" class="button button-primary button-large" /></p>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>
</div>
<?php
		}
	}
}
?>