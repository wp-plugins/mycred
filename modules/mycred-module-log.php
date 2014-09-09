<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Log_Module class
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_Log_Module' ) ) {
	class myCRED_Log_Module extends myCRED_Module {

		public $user;
		public $screen;

		/**
		 * Construct
		 */
		function __construct( $type = 'mycred_default' ) {
			parent::__construct( 'myCRED_Log_Module', array(
				'module_name' => 'log',
				'labels'      => array(
					'menu'        => __( 'Log', 'mycred' ),
					'page_title'  => __( 'Log', 'mycred' )
				),
				'screen_id'   => 'myCRED',
				'cap'         => 'editor',
				'accordion'   => true,
				'register'    => false,
				'menu_pos'    => 10
			), $type );
		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.1
		 */
		public function module_init() {
			$this->download_export_log();

			add_action( 'mycred_add_menu',   array( $this, 'my_history_menu' ) );

			// Handle deletions
			add_action( 'before_delete_post', array( $this, 'post_deletions' ) );
			add_action( 'delete_comment',     array( $this, 'comment_deletions' ) );

			// If we do not want to delete log entries, attempt to hardcode the users
			// details with their last known details.
			if ( isset( $this->core->delete_user ) && ! $this->core->delete_user )
				add_action( 'delete_user', array( $this, 'user_deletions' ) );
		}

		/**
		 * Admin Init
		 * @since 1.4
		 * @version 1.0
		 */
		public function module_admin_init() {
			add_action( 'wp_ajax_mycred-delete-log-entry', array( $this, 'action_delete_log_entry' ) );
			add_action( 'wp_ajax_mycred-update-log-entry', array( $this, 'action_update_log_entry' ) );
		}

		/**
		 * Create CSV File Export
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function download_export_log() {
			if ( ! isset( $_REQUEST['mycred-export'] ) || $_REQUEST['mycred-export'] != 'do' ) return;

			// Must be logged in
			if ( ! is_user_logged_in() ) return;

			// Make sure current user can export
			if ( ! apply_filters( 'mycred_user_can_export', false ) && ! $this->core->can_edit_creds() ) return;

			// Security for front export
			if ( apply_filters( 'mycred_allow_front_export', false ) === true ) {
				if ( ! isset( $_REQUEST['token'] ) || ! wp_verify_nonce( $_REQUEST['token'], 'mycred-run-log-export' ) ) return;
			}
			// Security for admin export
			else {
				check_admin_referer( 'mycred-run-log-export', 'token' );
			}

			$type = '';
			$data = array();

			// Sanitize the log query
			foreach ( (array) $_POST as $key => $value ) {
				if ( $key == 'action' ) continue;
				$_value = sanitize_text_field( $value );
				if ( $_value != '' )
					$data[ $key ] = $_value;
			}

			// Get exports
			$exports = mycred_get_log_exports();
			if ( empty( $exports ) ) return;

			// Identify the export type by the action button
			foreach ( $exports as $id => $info ) {
				if ( $info['label'] == $_POST['action'] ) {
					$type = $id;
					break;
				}
			}

			// Act according to type
			switch ( $type ) {
				case 'all'    :

					$old_data = $data;
					unset( $data );
					$data = array();
					$data['ctype']  = $old_data['ctype'];
					$data['number'] = -1;

				break;
				case 'search' :

					$data['number'] = -1;

				break;
				case 'displayed' :
				default :

					$data = apply_filters( 'mycred_export_log_args', $data );

				break;
			}

			// Custom Exports
			if ( has_action( 'mycred_export_' . $type ) ) {
				do_action( 'mycred_export_' . $type, $data );
			}
			// Built-in Exports
			else {

				// Query the log
				$log = new myCRED_Query_Log( $data, true );

				// If there are entries
				if ( $log->have_entries() ) {

					$export = array();
					// Loop though results
					foreach ( $log->results as $entry ) {
						// Remove the row id
						unset( $entry['id'] );

						// Make sure entry and data does not contain any commas that could brake this
						$entry['entry'] = str_replace( ',', '', $entry['entry'] );
						$entry['data'] = str_replace( ',', '.', $entry['data'] );

						// Add to export array
						$export[] = $entry;
					}

					$log->reset_query();

					// Load parseCSV
					require_once( myCRED_ASSETS_DIR . 'libs/parsecsv.lib.php' );
					$csv = new parseCSV();

					// Run output and lets create a CSV file
					$date = date_i18n( 'Y-m-d' );
					$csv->output( true, 'mycred-log-' . $date . '.csv', $export, array( 'ref', 'ref_id', 'user_id', 'creds', 'ctype', 'time', 'entry', 'data' ) );
					die();

				}

				$log->reset_query();

			}
		}

		/**
		 * Delete Log Entry Action
		 * @since 1.4
		 * @version 1.0
		 */
		public function action_delete_log_entry() {
			// Security
			check_ajax_referer( 'mycred-delete-log-entry', 'token' );

			// Access
			if ( ! is_user_logged_in() || ! $this->core->can_edit_plugin() )
				wp_send_json_error(  __( 'Access denied for this action', 'mycred' ) );

			// Delete Row
			global $wpdb;
			$wpdb->delete( $this->core->log_table, array( 'id' => absint( $_POST['row'] ) ), array( '%d' ) );

			// Respond
			wp_send_json_success( __( 'Row Deleted', 'mycred' ) );
		}

		/**
		 * Update Log Entry Action
		 * @since 1.4
		 * @version 1.0
		 */
		public function action_update_log_entry() {
			// Security
			check_ajax_referer( 'mycred-update-log-entry', 'token' );

			// Access
			if ( ! is_user_logged_in() || ! $this->core->can_edit_plugin() )
				wp_send_json_error(  __( 'Access denied for this action', 'mycred' ) );

			// Get new entry
			$new_entry = trim( $_POST['new_entry'] );
			$new_entry = esc_attr( $new_entry );
			
			global $wpdb;
			
			// Get row
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->core->log_table} WHERE id = %d;", absint( $_POST['row'] ) ) );

			// If row is not found
			if ( empty( $row ) || $row === NULL )
				wp_send_json_error( __( 'Log entry not found', 'mycred' ) );

			// Update row
			$wpdb->update(
				$this->core->log_table,
				array( 'entry' => $new_entry ),
				array( 'id' => $row->id ),
				array( '%s' ),
				array( '%d' )
			);

			// Respond
			wp_send_json_success( array(
				'label'         => __( 'Entry Updated' ),
				'row_id'        => $row->id,
				'new_entry_raw' => $new_entry,
				'new_entry'     => $this->core->parse_template_tags( $new_entry, $row )
			) );
		}

		/**
		 * Add "Creds History" to menu
		 * @since 0.1
		 * @version 1.1
		 */
		public function my_history_menu() {
			// Check if user should be excluded
			if ( $this->core->exclude_user() ) return;

			// Add Points History to Users menu
			$page = add_users_page(
				$this->core->plural() . ' ' . __( 'History', 'mycred' ),
				$this->core->plural() . ' ' . __( 'History', 'mycred' ),
				'read',
				$this->mycred_type . '_history',
				array( $this, 'my_history_page' )
			);

			// Load styles for this page
			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_header' ) );
			add_action( 'load-' . $page,               array( $this, 'screen_options' ) );
		}

		/**
		 * Log Header
		 * @since 0.1
		 * @version 1.3
		 */
		public function settings_header() {
			wp_enqueue_script( 'mycred-edit-log' );
			wp_enqueue_style( 'mycred-inline-edit' );
		}

		/**
		 * Screen Options
		 * @since 1.4
		 * @version 1.0
		 */
		public function screen_options() {
			$this->set_entries_per_page();

			$settings_key = 'mycred_epp_' . $_GET['page'];
			if ( ! $this->is_main_type )
				$settings_key .= '_' . $this->mycred_type;
			
			// Prep Per Page
			$args = array(
				'label'   => __( 'Entries', 'mycred' ),
				'default' => 10,
				'option'  => $settings_key
			);
			add_screen_option( 'per_page', $args );
		}

		/**
		 * Page Title
		 * @since 0.1
		 * @version 1.0
		 */
		public function page_title( $title = 'Log' ) {
			// Settings Link
			if ( $this->core->can_edit_plugin() )
				$link = '<a href="javascript:void(0)" class="toggle-exporter add-new-h2" data-toggle="export-log-history">' . __( 'Export', 'mycred' ) . '</a>';
			else
				$link = '';

			// Search Results
			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) )
				$search_for = ' <span class="subtitle">' . __( 'Search results for', 'mycred' ) . ' "' . $_GET['s'] . '"</span>';
			else
				$search_for = '';

			echo $title . ' ' . $link . $search_for;
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.3
		 */
		public function admin_page() {
			// Security
			if ( ! $this->core->can_edit_creds() )
				wp_die( __( 'Access Denied', 'mycred' ) );

			$settings_key = 'mycred_epp_' . $_GET['page'];
			if ( ! $this->is_main_type )
				$settings_key .= '_' . $this->mycred_type;

			$per_page = mycred_get_user_meta( get_current_user_id(), $settings_key, '', true );
			if ( $per_page == '' ) $per_page = 10;

			// Prep
			$args = array( 'number' => absint( $per_page ) );

			if ( isset( $_GET['type'] ) && $_GET['type'] != '' )
				$args['ctype'] = $_GET['type'];
			else
				$args['ctype'] = $this->mycred_type;

			if ( isset( $_GET['user_id'] ) && $_GET['user_id'] != '' )
				$args['user_id'] = $_GET['user_id'];

			if ( isset( $_GET['s'] ) && $_GET['s'] != '' )
				$args['s'] = $_GET['s'];

			if ( isset( $_GET['ref'] ) && $_GET['ref'] != '' )
				$args['ref'] = $_GET['ref'];

			if ( isset( $_GET['show'] ) && $_GET['show'] != '' )
				$args['time'] = $_GET['show'];

			if ( isset( $_GET['order'] ) && $_GET['order'] != '' )
				$args['order'] = $_GET['order'];
			
			if ( isset( $_GET['start'] ) && isset( $_GET['end'] ) )
				$args['amount'] = array( 'start' => $_GET['start'], 'end' => $_GET['end'] );
			
			elseif ( isset( $_GET['num'] ) && isset( $_GET['compare'] ) )
				$args['amount'] = array( 'num' => $_GET['num'], 'compare' => urldecode( $_GET['compare'] ) );

			elseif ( isset( $_GET['amount'] ) )
				$args['amount'] = $_GET['amount'];

			if ( isset( $_GET['data'] ) && $_GET['data'] != '' )
				$args['data'] = $_GET['data'];

			if ( isset( $_GET['paged'] ) && $_GET['paged'] != '' )
				$args['paged'] = $_GET['paged'];

			$log = new myCRED_Query_Log( $args );
			
			$log->headers['column-actions'] = __( 'Actions', 'mycred' ); ?>

<div class="wrap" id="myCRED-wrap">
	<h2><?php $this->page_title( sprintf( __( '%s Log', 'mycred' ), $this->core->plural() ) ); ?></h2>
	<?php $log->filter_dates( admin_url( 'admin.php?page=' . $this->screen_id ) ); ?>

	<?php do_action( 'mycred_top_log_page', $this ); ?>

	<div class="clear"></div>
	<?php $log->exporter( __( 'Export', 'mycred' ) ); ?>

	<form method="get" action="">
<?php

			if ( isset( $_GET['type'] ) && $_GET['type'] != '' )
				echo '<input type="hidden" name="type" value="' . $_GET['type'] . '" />';

			if ( isset( $_GET['user_id'] ) && $_GET['user_id'] != '' )
				echo '<input type="hidden" name="user_id" value="' . $_GET['user_id'] . '" />';

			if ( isset( $_GET['s'] ) && $_GET['s'] != '' )
				echo '<input type="hidden" name="s" value="' . $_GET['s'] . '" />';

			if ( isset( $_GET['ref'] ) && $_GET['ref'] != '' )
				echo '<input type="hidden" name="ref" value="' . $_GET['ref'] . '" />';

			if ( isset( $_GET['show'] ) && $_GET['show'] != '' )
				echo '<input type="hidden" name="show" value="' . $_GET['show'] . '" />';

			if ( isset( $_GET['order'] ) && $_GET['order'] != '' )
				echo '<input type="hidden" name="order" value="' . $_GET['order'] . '" />';

			if ( isset( $_GET['data'] ) && $_GET['data'] != '' )
				echo '<input type="hidden" name="data" value="' . $_GET['data'] . '" />';

			if ( isset( $_GET['paged'] ) && $_GET['paged'] != '' )
				echo '<input type="hidden" name="paged" value="' . $_GET['paged'] . '" />';

			$log->search(); ?>

		<input type="hidden" name="page" value="<?php echo $this->screen_id; ?>" />
		<?php do_action( 'mycred_above_log_table', $this ); ?>

		<div class="tablenav top">
			<?php $log->table_nav( 'top', false ); ?>

		</div>
		<table class="table wp-list-table widefat mycred-table log-entries" cellspacing="0">
			<thead>
				<tr>
<?php
			foreach ( $log->headers as $col_id => $col_title )
				echo '<th scope="col" id="' . str_replace( 'column-', '', $col_id ) . '" class="manage-column ' . $col_id . '">' . $col_title . '</th>';
?>
				</tr>
			</thead>
			<tfoot>
				<tr>
<?php
			foreach ( $log->headers as $col_id => $col_title )
				echo '<th scope="col" class="manage-column ' . $col_id . '">' . $col_title . '</th>';
?>
				</tr>
			</tfoot>
			<tbody id="the-list">
<?php
			if ( $log->have_entries() ) {
			
				$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				$entry_data = '';
				$alt = 0;
				
				foreach ( $log->results as $log_entry ) {
					$alt = $alt+1;
					if ( $alt % 2 == 0 )
						$class = ' alt';
					else
						$class = '';

					echo '<tr class="myCRED-log-row' . $class . '" id="mycred-log-entry-' . $log_entry->id . '">';
					
					// Run though columns
					foreach ( $log->headers as $column_id => $column_name ) {

						echo '<td class="' . $column_id . '">';

						switch ( $column_id ) {

							// Username Column
							case 'column-username' :

								$user = get_userdata( $log_entry->user_id );
								if ( $user === false )
									$content = '<span>' . __( 'User Missing', 'mycred' ) . ' (ID: ' . $log_entry->user_id . ')</span>';
								else 
									$content = '<span>' . $user->display_name . '</span>';
								
								if ( $user !== false && $this->core->can_edit_creds() )
									$content .= ' <em><small>(ID: ' . $log_entry->user_id . ')</small></em>';

								echo apply_filters( 'mycred_log_username', $content, $log_entry->user_id, $log_entry );

							break;

							// Date & Time Column
							case 'column-time' :

								echo apply_filters( 'mycred_log_date', date_i18n( $date_format, $log_entry->time ), $log_entry->time );

							break;

							// Amount Column
							case 'column-creds' :

								$content = $this->core->format_creds( $log_entry->creds );
								echo apply_filters( 'mycred_log_creds', $content, $log_entry->creds, $log_entry );

							break;

							// Log Entry Column
							case 'column-entry' :

								$content = '<div style="display:none;" class="raw">' . htmlentities( $log_entry->entry ) . '</div>';
								$content .= '<div class="entry">' . $this->core->parse_template_tags( $log_entry->entry, $log_entry ) . '</div>';
								echo apply_filters( 'mycred_log_entry', $content, $log_entry->entry, $log_entry );

							break;

							// Log Action Column
							case 'column-actions' :

								$content = '<a href="javascript:void(0)" class="mycred-open-log-entry-editor" data-id="' . $log_entry->id . '">' . __( 'Edit', 'mycred' ) . '</a> &bull; <span class="delete"><a href="javascript:void(0);" class="mycred-delete-row" data-id="' . $log_entry->id . '">' . __( 'Delete', 'mycred' ) . '</a></span>';
								echo apply_filters( 'mycred_log_actions', $content, $log_entry );

							break;

							// Let others add their own columns to this particular log page
							default :

								echo apply_filters( 'mycred_log_' . $column_id, '', $log_entry );

							break;

						}

						echo '</td>';

					}
					
					echo '</tr>';
				}
			}
			// No log entry
			else {
				echo '<tr><td colspan="' . count( $log->headers ) . '" class="no-entries">' . $log->get_no_entries() . '</td></tr>';
			}
?>

			</tbody>
		</table>
		<div class="tablenav bottom">
			<?php $log->table_nav( 'bottom', false ); ?>

		</div>
		<?php do_action( 'mycred_bellow_log_table', $this ); ?>

	</form>
	<?php do_action( 'mycred_bottom_log_page', $this ); ?>

	<div id="edit-mycred-log-entry" style="display: none;">
		<div class="mycred-adjustment-form">
			<p class="row inline" style="width: 40%;"><label><?php _e( 'User', 'mycred' ); ?>:</label><span id="mycred-username"></span></p>
			<p class="row inline" style="width: 40%;"><label><?php _e( 'Time', 'mycred' ); ?>:</label> <span id="mycred-time"></span></p>
			<p class="row inline" style="width: 20%;"><label><?php echo $this->core->plural(); ?>:</label> <span id="mycred-creds"></span></p>
			<div class="clear"></div>
			<p class="row">
				<label for="mycred-update-users-balance-amount"><?php _e( 'Current Log Entry', 'mycred' ); ?>:</label>
				<input type="text" name="mycred-raw-entry" id="mycred-raw-entry" value="" disabled="disabled" /><br />
				<span class="description"><?php _e( 'The current saved log entry', 'mycred' ); ?>.</span>
			</p>
			<p class="row">
				<label for="mycred-update-users-balance-entry"><?php _e( 'Adjust Log Entry', 'mycred' ); ?>:</label>
				<input type="text" name="mycred-new-entry" id="mycred-new-entry" value="" /><br />
				<span class="description"><?php _e( 'The new log entry', 'mycred' ); ?>.</span>
			</p>
			<p class="row">
				<input type="button" id="mycred-update-log-entry"  class="button button-primary button-large" value="<?php _e( 'Update Log Entry', 'mycred' ); ?>" />
				<input type="hidden" id="mycred-log-row-id" value="" />
			</p>
			<div class="clear"></div>
		</div>
		<div class="clear"></div>
	</div>
</div>
<?php		$log->reset_query();
			unset( $log );
		}

		/**
		 * My History Page
		 * @since 0.1
		 * @version 1.2
		 */
		public function my_history_page() {
			// Security
			if ( ! is_user_logged_in() )
				wp_die( __( 'Access Denied', 'mycred' ) );

			$settings_key = 'mycred_epp_' . $_GET['page'];
			if ( ! $this->is_main_type )
				$settings_key .= '_' . $this->mycred_type;

			$per_page = mycred_get_user_meta( get_current_user_id(), $settings_key, '', true );
			if ( $per_page == '' ) $per_page = 10;

			$args = array(
				'user_id' => get_current_user_id(),
				'number'  => $per_page
			);

			if ( isset( $_GET['type'] ) && ! empty( $_GET['type'] ) )
				$args['ctype'] = $_GET['type'];
			else
				$args['ctype'] = $this->mycred_type;

			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) )
				$args['s'] = $_GET['s'];

			if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) )
				$args['ref'] = $_GET['ref'];

			if ( isset( $_GET['show'] ) && ! empty( $_GET['show'] ) )
				$args['time'] = $_GET['show'];

			if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) )
				$args['order'] = $_GET['order'];
			
			if ( isset( $_GET['start'] ) && isset( $_GET['end'] ) )
				$args['amount'] = array( 'start' => $_GET['start'], 'end' => $_GET['end'] );
			
			elseif ( isset( $_GET['num'] ) && isset( $_GET['compare'] ) )
				$args['amount'] = array( 'num' => $_GET['num'], 'compare' => $_GET['compare'] );

			elseif ( isset( $_GET['amount'] ) )
				$args['amount'] = $_GET['amount'];

			if ( isset( $_GET['paged'] ) && ! empty( $_GET['paged'] ) )
				$args['paged'] = $_GET['paged'];

			$log = new myCRED_Query_Log( $args );
			unset( $log->headers['column-username'] ); ?>

<div class="wrap" id="myCRED-wrap">
	<h2><?php $this->page_title( sprintf( __( 'My %s History', 'mycred' ),  $this->core->plural() ) ); ?></h2>
	<?php $log->filter_dates( admin_url( 'users.php?page=' . $_GET['page'] ) ); ?>

	<?php do_action( 'mycred_top_my_log_page', $this ); ?>

	<div class="clear"></div>
	<?php $log->exporter( __( 'Export', 'mycred' ), true ); ?>

	<form method="get" action="">
<?php

			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) )
				echo '<input type="hidden" name="s" value="' . $_GET['s'] . '" />';

			if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) )
				echo '<input type="hidden" name="ref" value="' . $_GET['ref'] . '" />';

			if ( isset( $_GET['show'] ) && ! empty( $_GET['show'] ) )
				echo '<input type="hidden" name="show" value="' . $_GET['show'] . '" />';

			if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) )
				echo '<input type="hidden" name="order" value="' . $_GET['order'] . '" />';

			if ( isset( $_GET['paged'] ) && ! empty( $_GET['paged'] ) )
				echo '<input type="hidden" name="paged" value="' . $_GET['paged'] . '" />';

			$log->search(); ?>

		<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
		<?php do_action( 'mycred_above_my_log_table', $this ); ?>

		<div class="tablenav top">
			<?php $log->table_nav( 'top', true ); ?>

		</div>
		<?php $log->display(); ?>

		<div class="tablenav bottom">
			<?php $log->table_nav( 'bottom', true ); ?>

		</div>
		<?php do_action( 'mycred_bellow_my_log_table', $this ); ?>

	</form>
	<?php do_action( 'mycred_bottom_my_log_page', $this ); ?>

</div>
<?php		$log->reset_query();
			unset( $log );
		}

		/**
		 * Handle Post Deletions
		 * @since 1.0.9.2
		 * @version 1.0
		 */
		public function post_deletions( $post_id ) {
			global $post_type, $wpdb;

			// Check log
			$sql = "SELECT * FROM {$this->core->log_table} WHERE ref_id = %d;";
			$records = $wpdb->get_results( $wpdb->prepare( $sql, $post_id ) );

			// If we have results
			if ( $wpdb->num_rows > 0 ) {
				// Loop though them
				foreach ( $records as $row ) {

					// Check if the data column has a serialized array
					$check = @unserialize( $row->data );
					if ( $check !== false && $row->data !== 'b:0;' ) {
						// Unserialize
						$data = unserialize( $row->data );
						// If this is a post
						if ( ( isset( $data['ref_type'] ) && $data['ref_type'] == 'post' ) || ( isset( $data['post_type'] ) && $post_type == $data['post_type'] ) ) {

							// If the entry is blank continue on to the next
							if ( trim( $row->entry ) === '' ) continue;
							// Construct a new data array
							$new_data = array( 'ref_type' => 'post' );
							// Add details that will no longer be available
							$post = get_post( $post_id );
							$new_data['ID'] = $post->ID;
							$new_data['post_title'] = $post->post_title;
							$new_data['post_type'] = $post->post_type;
							// Save
							$wpdb->update(
								$this->core->log_table,
								array( 'data' => serialize( $new_data ) ),
								array( 'id'   => $row->id ),
								array( '%s' ),
								array( '%d' )
							);

						}
					}

				}
			}
		}

		/**
		 * Handle User Deletions
		 * @since 1.0.9.2
		 * @version 1.0
		 */
		public function user_deletions( $user_id ) {
			global $wpdb;

			// Check log
			$sql = "SELECT * FROM {$this->core->log_table} WHERE user_id = %d;";
			$records = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );

			// If we have results
			if ( $wpdb->num_rows > 0 ) {
				// Loop though them
				foreach ( $records as $row ) {

					// Construct a new data array
					$new_data = array( 'ref_type' => 'user' );
					// Add details that will no longer be available
					$user = get_userdata( $user_id );
					$new_data['ID'] = $user->ID;
					$new_data['user_login'] = $user->user_login;
					$new_data['display_name'] = $user->display_name;
					// Save
					$wpdb->update(
						$this->core->log_table,
						array( 'data' => serialize( $new_data ) ),
						array( 'id'   => $row->id ),
						array( '%s' ),
						array( '%d' )
					);
				}

			}
		}

		/**
		 * Handle Comment Deletions
		 * @since 1.0.9.2
		 * @version 1.0
		 */
		public function comment_deletions( $comment_id ) {
			global $wpdb;

			// Check log
			$sql = "SELECT * FROM {$this->core->log_table} WHERE ref_id = %d;";
			$records = $wpdb->get_results( $wpdb->prepare( $sql, $comment_id ) );

			// If we have results
			if ( $wpdb->num_rows > 0 ) {
				// Loop though them
				foreach ( $records as $row ) {

					// Check if the data column has a serialized array
					$check = @unserialize( $row->data );
					if ( $check !== false && $row->data !== 'b:0;' ) {
						// Unserialize
						$data = unserialize( $row->data );
						// If this is a post
						if ( isset( $data['ref_type'] ) && $data['ref_type'] == 'comment' ) {
							// If the entry is blank continue on to the next
							if ( trim( $row->entry ) === '' ) continue;
							// Construct a new data array
							$new_data = array( 'ref_type' => 'comment' );
							// Add details that will no longer be available
							$comment = get_comment( $comment_id );
							$new_data['comment_ID'] = $comment->comment_ID;
							$new_data['comment_post_ID'] = $comment->comment_post_ID;
							// Save
							$wpdb->update(
								$this->core->log_table,
								array( 'data' => serialize( $new_data ) ),
								array( 'id'   => $row->id ),
								array( '%s' ),
								array( '%d' )
							);
						}
					}

				}
			}

		}
	}
}
?>