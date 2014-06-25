<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Admin class
 * Manages everything concerning the WordPress admin area.
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_Admin' ) ) {
	class myCRED_Admin {

		public $core;

		/**
		 * Construct
		 * @since 0.1
		 * @version 1.0
		 */
		function __construct( $settings = array() ) {
			$this->core = mycred();
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.1
		 */
		public function load() {
			// Admin Styling
			add_action( 'admin_head',                 array( $this, 'admin_header' ) );
			add_action( 'admin_notices',              array( $this, 'admin_notices' ) );

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
		 * Admin Notices
		 * @since 1.4
		 * @version 1.0
		 */
		public function admin_notices() {
			$notice = array();

			$req_hooks = get_option( 'mycred_update_req_settings', false );
			if ( $req_hooks !== false )
				$notice[] = __( 'Re-save your myCRED Settings & all myCRED widget settings that you are currently using.', 'mycred' );

			$req_settigs = get_option( 'mycred_update_req_hooks', false );
			if ( $req_settigs !== false )
				$notice[] = __( 'Re-save your myCRED Hook Settings.', 'mycred' );

			if ( empty( $notice ) ) return;

			echo '<div class="error"><p>' . __( 'Please complete the following tasks in order to finish updating myCRED to version 1.4:', 'mycred' ) . '</p>';
			echo '<ul><li>' . implode( '</li><li>', $notice ) . '</li></ul></div>';
		}

		/**
		 * Ajax: Inline Edit Users Balance
		 * @since 1.2
		 * @version 1.1
		 */
		public function inline_edit_user_balance() {
			// Security
			check_ajax_referer( 'mycred-update-users-balance', 'token' );

			// Check current user
			$current_user = get_current_user_id();
			if ( ! mycred_is_admin( $current_user ) )
				wp_send_json_error( 'ERROR_1' );

			// Type
			$type = sanitize_text_field( $_POST['type'] );

			$mycred = mycred( $type );

			// User
			$user_id = abs( $_POST['user'] );
			if ( $mycred->exclude_user( $user_id ) )
				wp_send_json_error( array( 'error' => 'ERROR_2', 'message' => __( 'User is excluded', 'mycred' ) ) );

			// Log entry
			$entry = trim( $_POST['entry'] );
			if ( $mycred->can_edit_creds() && ! $mycred->can_edit_plugin() && empty( $entry ) )
				wp_send_json_error( array( 'error' => 'ERROR_3', 'message' => __( 'Log Entry can not be empty', 'mycred' ) ) );

			// Amount
			if ( $_POST['amount'] == 0 || empty( $_POST['amount'] ) )
				wp_send_json_error( array( 'error' => 'ERROR_4', 'message' => __( 'Amount can not be zero', 'mycred' ) ) );
			else
				$amount = $mycred->number( $_POST['amount'] );

			// Data
			$data = apply_filters( 'mycred_manual_change', array( 'ref_type' => 'user' ), $this );

			// Execute
			$result = $mycred->add_creds(
				'manual',
				$user_id,
				$amount,
				$entry,
				$current_user,
				$data,
				$type
			);

			if ( $result !== false )
				wp_send_json_success( $mycred->get_users_cred( $user_id, $type ) );
			else
				wp_send_json_error( array( 'error' => 'ERROR_5', 'message' => __( 'Failed to update this uses balance.', 'mycred' ) ) );
		}

		/**
		 * Admin Header
		 * @since 0.1
		 * @version 1.3
		 */
		public function admin_header() {
			global $wp_version;

			// Old navigation menu
			if ( version_compare( $wp_version, '3.8', '<' ) ) {
				$image = plugins_url( 'assets/images/logo-menu.png', myCRED_THIS ); ?>

<!-- Support for pre 3.8 menus -->
<style type="text/css">
<?php foreach ( $mycred_types as $type => $label ) { if ( $mycred_type == 'mycred_default' ) $name = ''; else $name = '_' . $type; ?>
#adminmenu .toplevel_page_myCRED<?php echo $name; ?> div.wp-menu-image { background-image: url(<?php echo $image; ?>); background-position: 1px -28px; }
#adminmenu .toplevel_page_myCRED<?php echo $name; ?>:hover div.wp-menu-image, 
#adminmenu .toplevel_page_myCRED<?php echo $name; ?>.current div.wp-menu-image, 
#adminmenu .toplevel_page_myCRED<?php echo $name; ?> .wp-menu-open div.wp-menu-image { background-position: 1px 0; }
<?php } ?>
</style>
<?php
			}

			$screen = get_current_screen();
			if ( $screen->id == 'users' ) {
				wp_enqueue_script( 'mycred-inline-edit' );
				wp_enqueue_style( 'mycred-inline-edit' );
			}
		}

		/**
		 * Customize Users Column Headers
		 * @since 0.1
		 * @version 1.1
		 */
		public function custom_user_column( $columns ) {
			global $mycred_types;

			if ( count( $mycred_types ) == 1 )
				$columns['mycred_default'] = $this->core->plural();
			else {
				foreach ( $mycred_types as $type => $label ) {
					if ( $type == 'mycred_default' ) $label = $this->core->plural();
					$columns[ $type ] = $label;
				}
			}

			return $columns;
		}

		/**
		 * Sortable User Column
		 * @since 1.2
		 * @version 1.1
		 */
		public function sortable_points_column( $columns ) {
			$mycred_types = mycred_get_types();

			if ( count( $mycred_types ) == 1 )
				$columns['mycred_default'] = 'mycred_default';
			else {
				foreach ( $mycred_types as $type => $label )
					$columns[ $type ] = $type;
			}

			return $columns;
		}

		/**
		 * Sort by Points
		 * @since 1.2
		 * @version 1.3
		 */
		public function sort_by_points( $query ) {
			if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) return;
			$screen = get_current_screen();
			if ( $screen === NULL || $screen->id != 'users' ) return;

			if ( isset( $query->query_vars['orderby'] ) ) {
				global $wpdb;

				$mycred_types = mycred_get_types();
				$cred_id = $query->query_vars['orderby'];
				
				$order = 'ASC';
				if ( isset( $query->query_vars['order'] ) )
					$order = $query->query_vars['order'];

				$mycred = $this->core;
				if ( isset( $_REQUEST['ctype'] ) && array_key_exists( $_REQUEST['ctype'], $mycred_types ) )
					$mycred = mycred( $_REQUEST['ctype'] );

				// Sort by only showing users with a particular point type
				if ( $cred_id == 'balance' ) {

					$amount = $mycred->zero();
					if ( isset( $_REQUEST['amount'] ) )
						$amount = $mycred->number( $_REQUEST['amount'] );
					
					$query->query_from .= "
LEFT JOIN {$wpdb->usermeta} 
	ON ({$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND {$wpdb->usermeta}.meta_key = '{$mycred->cred_id}')";

					$query->query_where .= " AND meta_value = {$amount}";

				}

				// Sort a particular point type
				elseif ( array_key_exists( $cred_id, $mycred_types ) ) {

					$query->query_from .= "
LEFT JOIN {$wpdb->usermeta} 
	ON ({$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND {$wpdb->usermeta}.meta_key = '{$cred_id}')";

					$query->query_orderby = "ORDER BY {$wpdb->usermeta}.meta_value+0 {$order} ";

				}

			}
		}

		/**
		 * Customize User Columns Content
		 * @filter 'mycred_user_row_actions'
		 * @since 0.1
		 * @version 1.3.1
		 */
		public function custom_user_column_content( $value, $column_name, $user_id ) {
			global $mycred_types;

			if ( ! array_key_exists( $column_name, $mycred_types ) ) return $value;

			$mycred = mycred( $column_name );

			// User is excluded
			if ( $mycred->exclude_user( $user_id ) === true ) return __( 'Excluded', 'mycred' );

			$user = get_userdata( $user_id );

			// Show balance
			$ubalance = $mycred->get_users_cred( $user_id, $column_name );
			$balance = '<div id="mycred-user-' . $user_id . '-balance-' . $column_name . '">' . $mycred->before . ' <span>' . $mycred->format_number( $ubalance ) . '</span> ' . $mycred->after . '</div>';

			// Show total
			if ( isset( $mycred->rank['base'] ) && $mycred->rank['base'] == 'total' ) {
				$key = $column_name;
				if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_central_logging )
					$key .= '_' . $GLOBALS['blog_id'];

				$total = get_user_meta( $user_id, $key . '_total', true );
				if ( $total != '' )
					$balance .= '<small style="display:block;">' . sprintf( __( 'Total: %s', 'mycred' ), $mycred->format_number( $total ) ) . '</small>';
			}

			$page = 'myCRED';
			if ( $column_name != 'mycred_default' )
				$page .= '_' . $column_name;

			// Row actions
			$row = array();
			$row['history'] = '<a href="' . admin_url( 'admin.php?page=' . $page . '&user_id=' . $user_id ) . '">' . __( 'History', 'mycred' ) . '</a>';
			$row['adjust'] = '<a href="javascript:void(0)" class="mycred-open-points-editor" data-userid="' . $user_id . '" data-current="' . $ubalance . '" data-type="' . $column_name . '" data-username="' . $user->display_name . '">' . __( 'Adjust', 'mycred' ) . '</a>';

			$rows = apply_filters( 'mycred_user_row_actions', $row, $user_id, $mycred );
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
		 * @version 1.1
		 */
		public function show_my_balance( $user ) {
			$user_id = $user->ID;
			
			$mycred_types = mycred_get_types();
			
			foreach ( $mycred_types as $type => $label ) {
				
				$mycred = mycred( $type );
				if ( $mycred->exclude_user( $user_id ) ) continue;
				
				$balance = $mycred->get_users_cred( $user_id, $type );
				$balance = $mycred->format_creds( $balance ); ?>

<table class="form-table">
	<tr>
		<th scope="row"><?php echo $mycred->template_tags_general( __( '%singular% balance', 'mycred' ) ); ?></th>
		<td><h2 style="margin:0;padding:0;"><?php echo $balance; ?></h2></td>
	</tr>
</table>
<?php
			}
		}

		/**
		 * Adjust Users Balance
		 * @since 0.1
		 * @version 1.1
		 */
		public function adjust_users_balance( $user ) {
			global $mycred_errors;

			// Editors can not edit their own creds
			if ( ! $this->core->can_edit_creds() ) return;

			// Make sure we do not want to exclude this user
			if ( $this->core->exclude_user( $user->ID ) === true ) return;

			// Label
			if ( $user->ID == get_current_user_id() )
				$label = __( 'Adjust Your Balance', 'mycred' );
			else
				$label = __( 'Adjust Users Balance', 'mycred' );
			
			if ( $this->core->can_edit_creds() && ! $this->core->can_edit_plugin() )
				$req = '(<strong>' . __( 'required', 'mycred' ) . '</strong>)'; 
			else
				$req = '(' . __( 'optional', 'mycred' ) . ')'; ?>

<tr>
<th scope="row"><label for="myCRED-manual-add-points"><?php echo $label; ?></label></th>
<td id="myCRED-adjust-users-points">
<?php _e( 'Amount', 'mycred' ) ?>: <input type="text" name="myCRED-manual-add-points" id="myCRED-manual-add-points" value="<?php echo $this->core->zero(); ?>" size="4" /> <?php mycred_types_select_from_dropdown( 'myCRED-manual-add-type', 'myCRED-manual-add-type', 'mycred_default' ); ?><br /><br />
<label for="myCRED-manual-add-description"><?php _e( 'Log description for adjustment', 'mycred' ); ?> <?php echo $req; ?></label><br />
<input type="text" name="myCRED-manual-add-description" id="myCRED-manual-add-description" value="" class="regular-text" /> 

<?php submit_button( __( 'Update', 'mycred' ), 'primary medium', 'myCRED_update', false ); ?>
<?php if ( $mycred_errors ) echo '<p style="color:red;">' . __( 'Description is required!', 'mycred' ) . '</p>'; ?>
</td>
</tr>
<?php
			if ( IS_PROFILE_PAGE ) return;

			$mycred_types = mycred_get_types();
			
			foreach ( $mycred_types as $type => $label ) {
				
				$mycred = mycred( $type );
				if ( $mycred->exclude_user( $user->ID ) ) continue;
				
				$balance = $mycred->get_users_cred( $user->ID, $type );
				$balance = $mycred->format_creds( $balance ); ?>

<table class="form-table">
	<tr>
		<th scope="row"><?php echo $mycred->template_tags_general( __( '%singular% balance', 'mycred' ) ); ?></th>
		<td><h2 style="margin:0;padding:0;"><?php echo $balance; ?></h2></td>
	</tr>
</table>
<?php
			}

		}

		/**
		 * Save Manual Adjustments
		 * @since 0.1
		 * @version 1.3
		 */
		public function adjust_points_manually( $user_id ) {
			global $mycred_errors;

			// All the reasons we should bail
			if ( ! $this->core->can_edit_creds() ) return false;
			if ( ! isset( $_POST['myCRED-manual-add-points'] ) ) return false;
			
			// Clean up excludes
			if ( $this->core->exclude_user( $user_id ) ) {
				// If excludes has been changed since install we need to delete their points balance
				// meta to avoid them showing up in the leaderboard or other db queries.
				$balance = get_user_meta( $user_id, 'mycred_default', true );
				if ( ! empty( $balance ) )
					delete_user_meta( $user_id, 'mycred_default' );
				
				return false;
			}
			
			// Add new creds
			$cred = $_POST['myCRED-manual-add-points'];
			$cred = $this->core->number( $cred );
			if ( $cred == $this->core->zero() ) return;

			$entry = '';
			if ( isset( $_POST['myCRED-manual-add-description'] ) )
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
			
			$type = sanitize_text_field( $_POST['myCRED-manual-add-type'] );
			
			$this->core->add_creds(
				'manual',
				$user_id,
				$cred,
				$entry,
				get_current_user_id(),
				$data,
				$type
			);
		}

		/**
		 * Admin Footer
		 * Inserts the Inline Edit Form modal.
		 * @since 1.2
		 * @version 1.1
		 */
		public function admin_footer() {
			$screen = get_current_screen();
			if ( $screen->id != 'users' ) return;
			
			if ( $this->core->can_edit_creds() && ! $this->core->can_edit_plugin() )
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
		<input type="hidden" name="mycred_update_users_balance[type]" id="mycred-update-users-balance-type" value="" />
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