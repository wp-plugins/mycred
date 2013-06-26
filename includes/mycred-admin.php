<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Admin class
 * Manages everything concerning the WordPress admin area.
 * @since 0.1
 * @version 1.0
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
		 * @version 1.0
		 */
		public function load() {
			add_action( 'admin_head',                 array( $this, 'admin_header' )                      );
			add_filter( 'manage_users_columns',       array( $this, 'custom_user_column' )                );
			add_action( 'manage_users_custom_column', array( $this, 'custom_user_column_content' ), 10, 3 );
			add_action( 'profile_personal_options',   array( $this, 'show_my_balance' ), 1                );
			add_action( 'personal_options',           array( $this, 'adjust_users_balance' ), 1           );
			add_action( 'personal_options_update',    array( $this, 'adjust_points_manually' )            );
			add_action( 'edit_user_profile_update',   array( $this, 'adjust_points_manually' )            );
		}

		/**
		 * Admin Header
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_header() {
			$image = plugins_url( 'assets/images/logo-menu.png', myCRED_THIS );
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
		 * Customize User Columns Content
		 * @filter 'mycred_user_row_actions'
		 * @since 0.1
		 * @version 1.0
		 */
		public function custom_user_column_content( $value, $column_name, $user_id ) {
			if ( 'mycred-balance' != $column_name ) return $value;

			// User is excluded
			if ( $this->core->exclude_user( $user_id ) === true ) return __( 'Excluded', 'mycred' );

			$balance = $this->core->get_users_cred( $user_id );
			$balance = $this->core->format_creds( $balance );

			// Row actions
			$row = array();
			$row['history'] = '<a href="' . admin_url( 'admin.php?page=myCRED&user_id=' . $user_id ) . '">' . __( 'History', 'mycred' ) . '</a>';
			if ( $this->core->can_edit_creds( get_current_user_id() ) )
				$row['adjust'] = '<a href="' . admin_url( 'user-edit.php?user_id=' . $user_id ) . '">' . __( 'Adjust', 'mycred' ) . '</a>';

			$rows = apply_filters( 'mycred_user_row_actions', $row, $user_id, $this->core );
			$balance .= '<br /><div class="row-actions">' . $this->row_actions( $rows ) . '</div>';
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
		 * @version 1.0
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
				$req = '(optional)'; ?>

<tr>
<th scope="row"><label for="myCRED-manual-add-points"><?php echo $label; ?></label></th>
<td id="myCRED-adjust-users-points">
<?php echo $this->core->plural(); ?>: <input type="text" name="myCRED-manual-add-points" id="myCRED-manual-add-points" value="<?php echo $this->core->number( 0 ); ?>" size="4" /><br /><br />
<label for="myCRED-manual-add-description"><?php _e( 'Log description for adjustment', 'mycred' ); ?> <?php echo $req; ?></label><br />
<input type="text" name="myCRED-manual-add-description" id="myCRED-manual-add-description" value="" class="regular-text" /> <?php submit_button( __( 'Update', 'mycred' ), 'primary medium', 'myCRED_update', '' ); ?>
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
		 * @version 1.1
		 */
		public function adjust_points_manually( $user_id ) {
			global $mycred_errors;

			// All the reasons we should bail
			if ( !$this->core->can_edit_creds() || $this->core->exclude_user( $user_id ) ) return false;
			if ( !isset( $_POST['myCRED-manual-add-points'] ) || !isset( $_POST['myCRED-manual-add-description'] ) ) return false;
			
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
	}
}
?>