<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_General class
 * @since 0.1
 * @version 1.1
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
				add_filter( 'mycred_add', array( $this, 'action_remove_reset' ) );
		}

		/**
		 * Admin Init
		 * @since 1.3
		 * @version 1.0
		 */
		public function module_admin_init() {
			add_action( 'wp_ajax_mycred-action-empty-log',      array( $this, 'action_empty_log' ) );
			add_action( 'wp_ajax_mycred-action-reset-accounts', array( $this, 'action_reset_balance' ) );
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

			$table_name = $wpdb->prefix . 'myCRED_Log';
			$wpdb->query( "TRUNCATE TABLE $table_name;" );
			$total_rows = $wpdb->get_var( "SELECT COUNT(1) FROM $table_name;" );
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

			$table_name = $wpdb->prefix . 'myCRED_Log';
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = %d WHERE meta_key = %s;", 0, 'mycred_default' ) );

			set_transient( 'mycred-accounts-reset', time(), (60*60*24) );
			die( json_encode( array( 'status' => 'OK', 'rows' => __( 'Accounts successfully reset', 'mycred' ) ) ) );
		}

		/**
		 * Remove Reset Block
		 * @since 1.3
		 * @version 1.0
		 */
		public function action_remove_reset() {
			delete_transient( 'mycred-accounts-reset' );
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

			// Updated settings
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
				echo '<div class="updated settings-error"><p>' . __( 'Settings Updated', 'mycred' ) . '</p></div>';
			} ?>

	<div class="wrap list" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php echo apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . __( 'Settings', 'mycred' ); ?> <?php echo myCRED_VERSION; ?></h2>
		<p><?php echo __( 'Adjust your core or add-on settings. Follow us on:', 'mycred' ) . ' '; ?><a href="https://www.facebook.com/myCRED" class="facebook" target="_blank"><?php _e( 'Facebook', 'mycred' ); ?></a>, <a href="https://plus.google.com/b/102981932999764129220/102981932999764129220/posts" class="googleplus" target="_blank"><?php _e( 'Google Plus', 'mycred' ); ?></a></p>
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
							<div class="h2"><input type="date" name="<?php echo $this->field_name( array( 'frequency' => 'date' ) ); ?>" id="<?php echo $this->field_id( array( 'frequency' => 'date' ) ); ?>" value="<?php echo $this->core->frequency['date'] ?>" class="medium" /></div>
						</li>
					</ol>
					<?php do_action( 'mycred_core_prefs', $this ); ?>

				</div>
				<?php

			global $wpdb;

			$table_name = $wpdb->prefix . 'myCRED_Log';
			$total_rows = $wpdb->get_var( "SELECT COUNT(1) FROM $table_name;" );

			$reset_block = false;
			if ( get_transient( 'mycred-accounts-reset' ) !== false )
				$reset_block = true; ?>

				<h4><div class="icon icon-inactive core"></div><label><?php _e( 'Management', 'mycred' ); ?></label></h4>
				<div class="body" style="display:none;">
					<label class="subheader"><?php _e( 'The Log', 'mycred' ); ?></label>
					<ol id="myCRED-actions-log" class="inline">
						<li style="min-width:280px;">
							<label><?php _e( 'Table Name', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="mycred-manage-table-name" disabled="disabled" value="<?php echo $table_name; ?>" class="readonly" /></div>
							<div class="description"><?php _e( 'The name of the database log table.', 'mycred' ); ?></div>
						</li>
						<li>
							<label><?php _e( 'Entries', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="mycred-manage-table-rows" disabled="disabled" value="<?php echo $total_rows; ?>" class="readonly short" /></div>
						</li>
						<li>
							<label><?php _e( 'Actions', 'mycred' ); ?></label>
							<div class="h2"><input type="button" id="mycred-manage-action-empty-log" value="<?php _e( 'Empty Log', 'mycred' ); ?>" class="button button-large large <?php if ( $total_rows == 0 ) echo '"disabled="disabled'; else echo 'button-primary'; ?>" /></div>
						</li>
					</ol>
					<label class="subheader"><?php echo $this->core->plural(); ?></label>
					<ol id="myCRED-actions-cred" class="inline">
						<li style="min-width:280px;">
							<label><?php _e( 'User Meta Key', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="" disabled="disabled" value="<?php echo $this->core->cred_id; ?>" class="readonly" /></div>
							<div class="description"><?php echo sprintf( __( 'The user meta key used to store users %s.', 'mycred' ), $this->core->plural() ); ?></div>
						</li>
						<li>
							<label><?php _e( 'Users', 'mycred' ); ?></label>
							<div class="h2"><input type="text" id="" disabled="disabled" value="<?php echo $this->core->count_members(); ?>" class="readonly short" /></div>
						</li>
						<li>
							<label><?php _e( 'Actions', 'mycred' ); ?></label>
							<div class="h2"><input type="button" id="mycred-manage-action-reset-accounts" value="<?php _e( 'Set all to zero', 'mycred' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; else echo 'button-primary'; ?>" /> <input type="button" id="" value="<?php _e( 'CSV Export', 'mycred' ); ?>" class="button button-large large"<?php if ( $reset_block ) echo ' disabled="disabled"'; ?> /></div>
						</li>
					</ol>
					<?php do_action( 'mycred_management_prefs', $this ); ?>

				</div>
				<?php do_action( 'mycred_after_core_prefs', $this ); ?>

			</div>
			<?php submit_button( __( 'Update Settings', 'mycred' ), 'primary large', 'submit', false ); ?>

		</form>
		<?php do_action( 'mycred_bottom_settings_page', $this ); ?>

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
		 * @version 1.0
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