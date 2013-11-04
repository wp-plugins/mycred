<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_General class
 * @since 0.1
 * @version 1.1.1
 */
if ( !class_exists( 'myCRED_General' ) ) {
	class myCRED_General extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_General', array(
				'module_name' => 'general',
				'option_id'   => 'mycred_pref_core',
				'labels'      => array(
					'menu'        => __( 'Settings', 'mycred' ),
					'page_title'  => __( 'Settings', 'mycred' ),
					'page_header' => __( 'Settings', 'mycred' )
				),
				'screen_id'   => 'myCRED_page_settings',
				'accordion'   => true,
				'menu_pos'    => 99
			) );
			
			if ( get_transient( 'mycred-accounts-reset' ) !== false )
				add_filter( 'mycred_add', array( $this, 'action_remove_reset' ), 10, 3 );
		}

		/**
		 * Admin Init
		 * @since 1.3
		 * @version 1.0
		 */
		public function module_admin_init() {
			if ( isset( $_GET['do'] ) && $_GET['do'] == 'export' )
				$this->load_export();

			add_action( 'wp_ajax_mycred-action-empty-log',       array( $this, 'action_empty_log' ) );
			add_action( 'wp_ajax_mycred-action-reset-accounts',  array( $this, 'action_reset_balance' ) );
			add_action( 'wp_ajax_mycred-action-export-balances', array( $this, 'action_export_balances' ) );
			add_action( 'wp_ajax_mycred-action-generate-key',    array( $this, 'action_generate_key' ) );
		}

		/**
		 * Empty Log Action
		 * @since 1.3
		 * @version 1.0
		 */
		public function action_empty_log() {
			check_ajax_referer( 'mycred-management-actions', 'token' );

			if ( !is_user_logged_in() || !$this->core->can_edit_plugin() )
				die( json_encode( array( 'status' => 'ERROR', 'rows' => __( 'Access denied for this action', 'mycred' ) ) ) );

			global $wpdb;

			$wpdb->query( "TRUNCATE TABLE {$this->core->log_table};" );
			$total_rows = $wpdb->get_var( "SELECT COUNT(1) FROM {$this->core->log_table};" );
			$wpdb->flush();

			die( json_encode( array( 'status' => 'OK', 'rows' => $total_rows ) ) );
		}

		/**
		 * Reset All Balances Action
		 * @since 1.3
		 * @version 1.0
		 */
		public function action_reset_balance() {
			check_ajax_referer( 'mycred-management-actions', 'token' );

			if ( !is_user_logged_in() || !$this->core->can_edit_plugin() )
				die( json_encode( array( 'status' => 'ERROR', 'rows' => __( 'Access denied for this action', 'mycred' ) ) ) );

			global $wpdb;

			if ( !isset( $this->core->format['decimals'] ) )
				$decimals = $this->core->core['format']['decimals'];
			else
				$decimals = $this->core->format['decimals'];

			if ( $decimals > 0 )
				$format = '%f';
			else
				$format = '%d';

			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = {$format} WHERE meta_key = %s;", $this->core->zero(), 'mycred_default' ) );

			set_transient( 'mycred-accounts-reset', time(), (60*60*24) );
			die( json_encode( array( 'status' => 'OK', 'rows' => __( 'Accounts successfully reset', 'mycred' ) ) ) );
		}

		/**
		 * Export User Balances
		 * @filter mycred_export_raw
		 * @since 1.3
		 * @version 1.0
		 */
		public function action_export_balances() {
			check_ajax_referer( 'mycred-management-actions', 'token' );
			
			global $wpdb;
			
			$log = sanitize_text_field( $_POST['log_temp'] );
			
			switch ( $_POST['identify'] ) {
				case 'ID' :
					$SQL = "SELECT user_id AS user, meta_value AS balance FROM {$wpdb->usermeta} WHERE meta_key = %s;";
				break;
				case 'email' :
					$SQL = "SELECT user_email AS user, meta_value AS balance FROM {$wpdb->usermeta} LEFT JOIN {$wpdb->users} ON {$wpdb->usermeta}.user_id = {$wpdb->users}.ID WHERE {$wpdb->usermeta}.meta_key = %s;";
				break;
				case 'login' :
					$SQL = "SELECT user_login AS user, meta_value AS balance FROM {$wpdb->usermeta} LEFT JOIN {$wpdb->users} ON {$wpdb->usermeta}.user_id = {$wpdb->users}.ID WHERE {$wpdb->usermeta}.meta_key = %s;";
				break;
			}
			
			$query = $wpdb->get_results( $wpdb->prepare( $SQL, 'mycred_default' ) );
			
			if ( empty( $query ) )
				die( json_encode( array( 'status' => 'ERROR', 'string' => __( 'No users found to export', 'mycred' ) ) ) );
			
			$array = array();
			foreach ( $query as $result ) {
				$data = array(
					'mycred_user'   => $result->user,
					'mycred_amount' => $this->core->number( $result->balance )
				);
				
				if ( ! empty( $log ) )
					$data = array_merge_recursive( $data, array( 'mycred_log' => $log ) );
				
				$array[] = $data;
			}
			
			set_transient( 'mycred-export-raw', apply_filters( 'mycred_export_raw', $array ), 3000 );
			
			die( json_encode( array( 'status' => 'OK', 'string' => admin_url( 'admin.php?page=myCRED_page_settings&do=export' ) ) ) );
		}

		public function action_generate_key() {
			check_ajax_referer( 'mycred-management-actions', 'token' );
			
			die( json_encode( wp_generate_password( 14, true, true ) ) );
		}

		/**
		 * Load Export
		 * Creates a CSV export file of the 'mycred-export-raw' transient.
		 * @since 1.3
		 * @version 1.0
		 */
		public function load_export() {
			if ( $this->core->can_edit_plugin( get_current_user_id() ) ) {
				
				$export = get_transient( 'mycred-export-raw' );
				if ( $export === false ) return;
				
				if ( isset( $export[0]['mycred_log'] ) )
					$headers = array( 'mycred_user', 'mycred_amount', 'mycred_log' );
				else
					$headers = array( 'mycred_user', 'mycred_amount' );	
				
				require_once( myCRED_ASSETS_DIR . 'libs/parsecsv.lib.php' );
				$csv = new parseCSV();
				
				delete_transient( 'mycred-export-raw' );
				$csv->output( true, 'mycred-balance-export.csv', $export, $headers );
				die();
			}
		}

		/**
		 * Remove Reset Block
		 * @since 1.3
		 * @version 1.0
		 */
		public function action_remove_reset( $reply, $request, $mycred ) {
			delete_transient( 'mycred-accounts-reset' );
			return $reply;
		}

		/**
		 * Settings Header
		 * Outputs the "click to open" and "click to close" text to the accordion.
		 *
		 * @since 1.3
		 * @version 1.0
		 */
		public function settings_header() {
			wp_dequeue_script( 'bpge_admin_js_acc' );
			wp_enqueue_script( 'mycred-manage' );

			wp_enqueue_style( 'mycred-admin' ); ?>

<style type="text/css">
#icon-myCRED, .icon32-posts-mycred_email_notice, .icon32-posts-mycred_rank { background-image: url(<?php echo apply_filters( 'mycred_icon', plugins_url( 'assets/images/cred-icon32.png', myCRED_THIS ) ); ?>); }
h4:before { float:right; padding-right: 12px; font-size: 14px; font-weight: normal; color: silver; }
h4.ui-accordion-header.ui-state-active:before { content: "<?php _e( 'click to close', 'mycred' ); ?>"; }
h4.ui-accordion-header:before { content: "<?php _e( 'click to open', 'mycred' ); ?>"; }
.mycred-export-points { background-color:white; }.mycred-export-points>div { padding:12px; }.mycred-export-points .ui-dialog-titlebar { line-height:24px; border-bottom: 1px solid #dedede; }.mycred-export-points .ui-dialog-titlebar:hover { cursor:move; }.mycred-export-points .ui-dialog-titlebar-close { float:right; }body.mycred_page_myCRED_page_settings .ui-widget-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background: repeat-x scroll 50% 50% #AAA; opacity:0.3; overflow:hidden; }body.mp6 .ui-widget-overlay { background: repeat-x scroll 50% 50% #333; }#export-points ul { display: block; margin: 0; padding: 0; }#export-points ul li { margin: 0 0 6px 0; padding: 0; list-style-type: none; }#export-points .action input { float: right; }
</style>
<?php
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.3
		 */
		public function admin_page() {
			if ( !$this->core->can_edit_plugin( get_current_user_id() ) ) wp_die( __( 'Access Denied', 'mycred' ) );

			// General Settings
			$general = $this->general;

			$plugin_name = apply_filters( 'mycred_label', myCRED_NAME ); ?>

	<div class="wrap list" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php echo $plugin_name . ' ' . __( 'Settings', 'mycred' ); ?> <?php echo myCRED_VERSION; ?></h2>
		<?php
			// Updated settings
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
				echo '<div class="updated settings-error"><p>' . __( 'Settings Updated', 'mycred' ) . '</p></div>';
			} ?>

		<p><?php echo __( 'Adjust your core or add-on settings. Follow us on:', 'mycred' ) . ' '; ?><a href="https://www.facebook.com/myCRED" class="facebook" target="_blank"><?php _e( 'Facebook', 'mycred' ); ?></a>, <a href="https://plus.google.com/+MycredMe/posts" class="googleplus" target="_blank"><?php _e( 'Google Plus', 'mycred' ); ?></a></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'myCRED-general' ); ?>

			<div class="list-items expandable-li" id="accordion">
				<h4><div class="icon icon-inactive core"></div><label><?php _e( 'Core Settings', 'mycred' ); ?></label></h4>
				<div class="body" style="display:none;">
					<label class="subheader"><?php _e( 'Name', 'mycred' ); ?></label>
					<ol id="myCRED-settings-name" class="inline">
						<li>
							<label for="<?php echo $this->field_id( array( 'name' => 'singular' ) ); ?>"><?php _e( 'Name (Singular)', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'name' => 'singular' ) ); ?>" id="<?php echo $this->field_id( array( 'name' => 'singular' ) ); ?>" value="<?php echo $this->core->name['singular']; ?>" /></div>
							<div class="description"><?php _e( 'Accessible though the %singular% template tag.', 'mycred' ); ?></div>
						</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'name' => 'plural' ) ); ?>"><?php _e( 'Name (Plural)', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'name' => 'plural' ) ); ?>" id="<?php echo $this->field_id( array( 'name' => 'plural' ) ); ?>" value="<?php echo $this->core->name['plural']; ?>" /></div>
							<div class="description"><?php _e( 'Accessible though the %plural% template tag.', 'mycred' ); ?></div>
						</li>
						<li class="block">
							<span class="description"><strong><?php _e( 'Tip', 'mycred' ); ?>:</strong> <?php _e( 'Adding an underscore at the beginning of template tag for names will return them in lowercase. i.e. %_singular%', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Presentation', 'mycred' ); ?></label>
					<ol id="myCRED-settings-layout" class="inline">
						<li>
							<label for="<?php echo $this->field_id( 'before' ); ?>"><?php _e( 'Prefix', 'mycred' ); ?></label>
							<div class="h2"><input type="text" size="5" name="<?php echo $this->field_name( 'before' ); ?>" id="<?php echo $this->field_id( 'before' ); ?>" value="<?php echo $this->core->before; ?>" /></div>
						</li>
						<li>
							<label>&nbsp;</label>
							<div class="h2"><?php echo $this->core->format_number( 1000 ); ?></div>
						</li>
						<li>
							<label for="<?php echo $this->field_id( 'after' ); ?>"><?php _e( 'Suffix', 'mycred' ); ?></label>
							<div class="h2"><input type="text" size="5" name="<?php echo $this->field_name( 'after' ); ?>" id="<?php echo $this->field_id( 'after' ); ?>" value="<?php echo $this->core->after; ?>" /></div>
						</li>
						<li class="block">
							<label for="myCRED-prefix"><?php echo _n( 'Separator', 'Separators', ( (int) $this->core->format['decimals'] > 0 ) ? 2 : 1, 'mycred' ); ?></label>
							<div class="h2">1 <input type="text" size="1" maxlength="1" name="<?php echo $this->field_name( array( 'format' => 'separators' ) ); ?>[thousand]" id="<?php echo $this->field_id( array( 'format' => 'separators' ) ); ?>-thousand" value="<?php echo $this->core->format['separators']['thousand']; ?>" /> 000 <input type="<?php if ( (int) $this->core->format['decimals'] > 0 ) echo 'text'; else echo 'hidden'; ?>" size="1" maxlength="1" name="<?php echo $this->field_name( array( 'format' => 'separators' ) ); ?>[decimal]" id="<?php echo $this->field_id( array( 'format' => 'separators' ) ); ?>-decimal" value="<?php echo $this->core->format['separators']['decimal']; ?>" /><?php if ( (int) $this->core->format['decimals'] > 0 ) echo ' ' . str_repeat( '0', $this->core->format['decimals'] ); ?></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Security', 'mycred' ); ?></label>
					<ol id="myCRED-settings-security" class="inline">
						<li>
							<label for="<?php echo $this->field_id( array( 'caps' => 'plugin' ) ); ?>"><?php _e( 'Edit Settings', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'caps' => 'plugin' ) ); ?>" id="<?php echo $this->field_id( array( 'caps' => 'plugin' ) ); ?>" value="<?php echo $this->core->caps['plugin']; ?>" /></div>
							<div class="description"><?php _e( 'Capability to check for.', 'mycred' ); ?></div>
						</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'caps' => 'creds' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Edit Users %plural%', 'mycred' ) ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'caps' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'caps' => 'creds' ) ); ?>" value="<?php echo $this->core->caps['creds']; ?>" /></div>
							<div class="description"><?php _e( 'Capability to check for.', 'mycred' ); ?></div>
						</li>
						<li class="block"><?php if ( ! isset( $this->core->max ) ) $this->core->max(); ?>
							<label for="<?php echo $this->field_id( 'max' ); ?>"><?php echo $this->core->template_tags_general( __( 'Maximum %plural% payouts', 'mycred' ) ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'max' ); ?>" id="<?php echo $this->field_id( 'max' ); ?>" value="<?php echo $this->core->max; ?>" size="8" /></div>
							<div class="description"><?php _e( 'As an added security, you can set the maximum amount a user can gain or loose in a single instance. If used, make sure this is the maximum amount a user would be able to transfer, buy, or spend in your store. Use zero to disable.', 'mycred' ); ?></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Excludes', 'mycred' ); ?></label>
					<ol id="myCRED-settings-excludes">
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'exclude' => 'plugin_editors' ) ); ?>" id="<?php echo $this->field_id( array( 'exclude' => 'plugin_editors' ) ); ?>" <?php checked( $this->core->exclude['plugin_editors'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'exclude' => 'plugin_editors' ) ); ?>"><?php _e( 'Exclude those who can "Edit Settings".', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'exclude' => 'cred_editors' ) ); ?>" id="<?php echo $this->field_id( array( 'exclude' => 'cred_editors' ) ); ?>" <?php checked( $this->core->exclude['cred_editors'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'exclude' => 'cred_editors' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Exclude those who can "Edit Users %plural%".', 'mycred' ) ); ?></label>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'exclude' => 'list' ) ); ?>"><?php _e( 'Exclude the following user IDs:', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'exclude' => 'list' ) ); ?>" id="<?php echo $this->field_id( array( 'exclude' => 'list' ) ); ?>" value="<?php echo $this->core->exclude['list']; ?>" class="long" /></div>
							<div class="description"><?php _e( 'Comma separated list of user ids to exclude. No spaces allowed!', 'mycred' ); ?></div>
						</li>
					</ol>
					<label class="subheader"><?php _e( 'Rankings', 'mycred' ); ?></label>
					<ol id="myCRED-settings-excludes">
						<li>
							<input type="radio" name="<?php echo $this->field_name( array( 'frequency' => 'rate' ) ); ?>" id="<?php echo $this->field_id( array( 'frequency' => 'always' ) ); ?>" <?php checked( $this->core->frequency['rate'], 'always' ); ?> value="always" /> 
							<label for="<?php echo $this->field_id( array( 'frequency' => 'always' ) ); ?>"><?php _e( 'Update rankings each time a users balance changes.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="radio" name="<?php echo $this->field_name( array( 'frequency' => 'rate' ) ); ?>" id="<?php echo $this->field_id( array( 'frequency' => 'daily' ) ); ?>" <?php checked( $this->core->frequency['rate'], 'daily' ); ?> value="daily" /> 
							<label for="<?php echo $this->field_id( array( 'frequency' => 'daily' ) ); ?>"><?php _e( 'Update rankings once a day.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="radio" name="<?php echo $this->field_name( array( 'frequency' => 'rate' ) ); ?>" id="<?php echo $this->field_id( array( 'frequency' => 'weekly' ) ); ?>" <?php checked( $this->core->frequency['rate'], 'weekly' ); ?> value="weekly" /> 
							<label for="<?php echo $this->field_id( array( 'frequency' => 'weekly' ) ); ?>"><?php _e( 'Update rankings once a week.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="radio" name="<?php echo $this->field_name( array( 'frequency' => 'rate' ) ); ?>" id="<?php echo $this->field_id( array( 'frequency' => 'ondate' ) ); ?>" <?php checked( $this->core->frequency['rate'], 'date' ); ?> value="date" /> 
							<label for="<?php echo $this->field_id( array( 'frequency' => 'ondate' ) ); ?>"><?php _e( 'Update rankings on a specific date.', 'mycred' ); ?></label>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( array( 'frequency' => 'date' ) ); ?>"><?php _e( 'Date', 'mycred' ); ?></label>
							<div class="h2"><input type="date" name="<?php echo $this->field_name( array( 'frequency' => 'date' ) ); ?>" id="<?php echo $this->field_id( array( 'frequency' => 'date' ) ); ?>" placeholder="YYYY-MM-DD" value="<?php echo $this->core->frequency['date'] ?>" class="medium" /></div>
						</li>
					</ol>
					<?php do_action( 'mycred_core_prefs', $this ); ?>

				</div>
				<?php

			global $wpdb;

			$total_rows = $wpdb->get_var( "SELECT COUNT(1) FROM {$this->core->log_table};" );

			$reset_block = false;
			if ( get_transient( 'mycred-accounts-reset' ) !== false )
				$reset_block = true; ?>

				<h4><div class="icon icon-active core"></div><label><?php _e( 'Management', 'mycred' ); ?></label></h4>
				<div class="body" style="display:none;">
					<label class="subheader"><?php _e( 'The Log', 'mycred' ); ?></label>
					<ol id="myCRED-actions-log" class="inline">
						<li>
							<label><?php _e( 'Table Name', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="mycred-manage-table-name" disabled="disabled" value="<?php echo $this->core->log_table; ?>" class="readonly" /></div>
						</li>
						<li>
							<label><?php _e( 'Entries', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="mycred-manage-table-rows" disabled="disabled" value="<?php echo $total_rows; ?>" class="readonly short" /></div>
						</li>
						<li>
							<label><?php _e( 'Actions', 'mycred' ); ?></label>
							<div class="h2"><?php if ( ( ! is_multisite() ) || ( is_multisite() && $GLOBALS['blog_id'] == 1 ) ) { ?><input type="button" id="mycred-manage-action-empty-log" value="<?php _e( 'Empty Log', 'mycred' ); ?>" class="button button-large large <?php if ( $total_rows == 0 ) echo '"disabled="disabled'; else echo 'button-primary'; ?>" /><?php } ?></div>
						</li>
					</ol>
					<label class="subheader"><?php echo $this->core->plural(); ?></label>
					<ol id="myCRED-actions-cred" class="inline">
						<li>
							<label><?php _e( 'User Meta Key', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="" disabled="disabled" value="<?php echo $this->core->cred_id; ?>" class="readonly" /></div>
						</li>
						<li>
							<label><?php _e( 'Users', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="" disabled="disabled" value="<?php echo $this->core->count_members(); ?>" class="readonly short" /></div>
						</li>
						<li>
							<label><?php _e( 'Actions', 'mycred' ); ?></label>
							<div class="h2"><input type="button" id="mycred-manage-action-reset-accounts" value="<?php _e( 'Set all to zero', 'mycred' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; else echo 'button-primary'; ?>" /> <input type="button" id="mycred-export-users-points" value="<?php _e( 'CSV Export', 'mycred' ); ?>" class="button button-large large"<?php if ( $reset_block ) echo ' disabled="disabled"'; ?> /></div>
						</li>
					</ol>
					<?php do_action( 'mycred_management_prefs', $this ); ?>

				</div>
				<?php do_action( 'mycred_after_management_prefs', $this ); ?>
				<?php do_action( 'mycred_after_core_prefs', $this ); ?>

			</div>
			<?php submit_button( __( 'Update Settings', 'mycred' ), 'primary large', 'submit', false ); ?>

		</form>
		<?php do_action( 'mycred_bottom_settings_page', $this ); ?>

		<div id="export-points" style="display:none;">
			<ul>
				<li>
					<label><?php _e( 'Identify users by', 'mycred' ); ?>:</label><br />
					<select id="mycred-export-identify-by">
						<?php
			
			$identify = apply_filters( 'mycred_export_by', array(
				'ID'    => __( 'User ID', 'mycred' ),
				'email' => __( 'User Email', 'mycred' ),
				'login' => __( 'User Login', 'mycred' )
			) );
			
			foreach ( $identify as $id => $label ) {
				echo '<option value="' . $id . '">' . $label . '</option>';
			} ?>
					</select><br />
					<span class="description"><?php _e( 'Use ID if you intend to use this export as a backup of your current site while Email is recommended if you want to export to a different site.', 'mycred' ); ?></span>
				</li>
				<li>
					<label><?php _e( 'Import Log Entry', 'mycred' ); ?>:</label><br />
					<input type="text" id="mycred-export-log-template" value="" class="regular-text" /><br />
					<span class="description"><?php echo sprintf( __( 'Optional log entry to use if you intend to import this file in a different %s installation.', 'mycred' ), $plugin_name ); ?></span>
				</li>
				<li class="action">
					<input type="button" id="mycred-run-exporter" value="<?php _e( 'Export', 'mycred' ); ?>" class="button button-large button-primary" />
				</li>
			</ul>
			<div class="clear"></div>
		</div>
	</div>
<?php
		}

		/**
		 * Maybe Whitespace
		 * Since we want to allow a single whitespace in the string and sanitize_text_field() removes this whitespace
		 * this little method will make sure that whitespace is still there and that we still can sanitize the field.
		 * @since 0.1
		 * @version 1.0
		 */
		public function maybe_whitespace( $string ) {
			if ( strlen( $string ) > 1 )
				return '';

			return $string;
		}

		/**
		 * Sanititze Settings
		 * @filter 'mycred_save_core_prefs'
		 * @since 0.1
		 * @version 1.2
		 */
		public function sanitize_settings( $post ) {
			$new_data = array();

			// Format
			$new_data['cred_id'] = $this->core->cred_id;
			$new_data['format'] = $this->core->format;

			$new_data['format']['separators']['decimal'] = $this->maybe_whitespace( $post['format']['separators']['decimal'] );
			$new_data['format']['separators']['thousand'] = $this->maybe_whitespace( $post['format']['separators']['thousand'] );

			// Name
			$new_data['name'] = array(
				'singular' => sanitize_text_field( $post['name']['singular'] ),
				'plural'   => sanitize_text_field( $post['name']['plural'] )
			);

			// Look
			$new_data['before'] = sanitize_text_field( $post['before'] );
			$new_data['after'] = sanitize_text_field( $post['after'] );

			// Capabilities
			$new_data['caps'] = array(
				'plugin' => sanitize_text_field( $post['caps']['plugin'] ),
				'creds'  => sanitize_text_field( $post['caps']['creds'] )
			);

			// Max
			$new_data['max'] = $this->core->number( $post['max'] );

			// Make sure multisites uses capabilities that exists
			if ( in_array( $new_data['caps']['creds'], array( 'create_users', 'delete_themes', 'edit_plugins', 'edit_themes', 'edit_users' ) ) && is_multisite() )
				$new_data['caps']['creds'] = 'delete_users';

			// Excludes
			$new_data['exclude'] = array(
				'plugin_editors' => ( isset( $post['exclude']['plugin_editors'] ) ) ? true : false,
				'cred_editors'   => ( isset( $post['exclude']['cred_editors'] ) ) ? true : false,
				'list'           => sanitize_text_field( $post['exclude']['list'] )
			);

			// Frequency
			$new_data['frequency'] = array(
				'rate' => sanitize_text_field( $post['frequency']['rate'] ),
				'date' => $post['frequency']['date']
			);

			$new_data = apply_filters( 'mycred_save_core_prefs', $new_data, $post, $this );
			return $new_data;
		}
	}
}
?>