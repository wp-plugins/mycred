<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Log class
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Log' ) ) {
	class myCRED_Log extends myCRED_Module {

		public $user;
		public $screen;

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Log', array(
				'module_name' => 'log',
				'labels'      => array(
					'menu'        => __( 'Log', 'mycred' ),
					'page_title'  => __( 'Log', 'mycred' ),
					'page_header' => __( 'Activity Log', 'mycred' )
				),
				'screen_id'   => 'myCRED',
				'cap'         => 'editor',
				'accordion'   => true,
				'register'    => false,
				'menu_pos'    => 10
			) );
		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_init() {
			add_filter( 'set-screen-option', array( $this, 'set_entries_per_page' ), 10, 3 );
			add_action( 'mycred_add_menu',   array( $this, 'my_history_menu' )             );
			
			// Handle deletions
			add_action( 'before_delete_post', array( $this, 'post_deletions' )    );
			add_action( 'delete_user',        array( $this, 'user_deletions' )    );
			add_action( 'delete_comment',     array( $this, 'comment_deletions' ) );
		}

		/**
		 * Save Log Entries per page
		 * @since 0.1
		 * @version 1.0
		 */
		public function set_entries_per_page( $status, $option, $value ) {
			if ( 'mycred_entries_per_page' == $option ) return $value;
		}

		/**
		 * Add "Creds History" to menu
		 * @since 0.1
		 * @version 1.1
		 */
		public function my_history_menu() {
			// Check if user should be excluded
			if ( $this->core->exclude_user( get_current_user_id() ) ) return;

			// Add Points History to Users menu
			$page = add_users_page(
				__( 'My History', 'mycred' ),
				$this->core->template_tags_general( __( '%plural% History', 'mycred' ) ),
				'read',
				'mycred_my_history',
				array( $this, 'my_history_page' )
			);
			// Load styles for this page
			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_header' ) );
		}

		/**
		 * Log Header
		 * @since 0.1
		 * @version 1.2
		 */
		public function settings_header() {
			// Since we are overwriting the myCRED_Module::settings_header() we need to enqueue admin styles
			wp_dequeue_script( 'bpge_admin_js_acc' );
			wp_enqueue_style( 'mycred-admin' );
			$screen = get_current_screen();

			// Prep Per Page
			$args = array(
				'label'   => __( 'Entries', 'mycred' ),
				'default' => 10,
				'option'  => 'mycred_entries_per_page'
			);
			add_screen_option( 'per_page', $args );
			$per_page = get_user_meta( get_current_user_id(), $args['option'], true );
			if ( empty( $per_page ) || $per_page < 1 ) $per_page = $args['default'];
			$this->per_page = $per_page; ?>

<style type="text/css">
#icon-myCRED, .icon32-posts-mycred_email_notice, .icon32-posts-mycred_rank { background-image: url(<?php echo apply_filters( 'mycred_icon', plugins_url( 'assets/images/cred-icon32.png', myCRED_THIS ) ); ?>); }
</style>
<?php
		}

		/**
		 * Count Records
		 * Returns the total number of rows from log
		 * @since 0.1
		 * @version 1.1
		 */
		public function count_records() {
			$count = get_transient( 'mycred_log_entries' );
			if ( $count === false ) {
				global $wpdb;
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->core->log_table};" );
				set_transient( 'mycred_log_entries', $count, DAY_IN_SECONDS*1 );
			}
			return $count;
		}

		/**
		 * Get References
		 * Returns all available references in the database.
		 * @since 0.1
		 * @version 1.0.1
		 */
		protected function get_refs() {
			$refs = wp_cache_get( 'mycred_references' );
			if ( false === $refs ) {
				global $wpdb;
				$sql = "SELECT log.ref FROM {$this->core->log_table} log WHERE %s <> %s;";
				$refs = $wpdb->get_col( $wpdb->prepare( $sql, 'ref', '' ) );
				if ( $refs ) {
					$refs = array_unique( $refs );
					wp_cache_set( 'mycred_references', $refs );
				}
			}
			return $refs;
		}

		/**
		 * Get Users
		 * Returns an array of user id's and display names.
		 * @since 0.1
		 * @version 1.0
		 */
		protected function get_users() {
			$users = wp_cache_get( 'mycred_users' );
			if ( false === $users ) {
				$users = array();
				$blog_users = get_users( array( 'orderby' => 'display_name' ) );
				foreach ( $blog_users as $user ) {
					if ( false === $this->core->exclude_user( $user->ID ) )
						$users[$user->ID] = $user->display_name;
				}
				wp_cache_set( 'mycred_users', $users );
			}
			return $users;
		}

		/**
		 * Filter Log options
		 * @since 0.1
		 * @version 1.2
		 */
		public function filter_options( $is_profile = false ) {
			echo '<div class="alignleft actions">';
			$show = false;

			// Filter by reference
			$references = $this->get_refs();
			if ( !empty( $references ) ) {
				echo '<select name="ref" id="myCRED-reference-filter"><option value="">' . __( 'Show all references', 'mycred' ) . '</option>';
				foreach ( $references as $ref_id ) {
					$name = str_replace( array( '_', '-' ), ' ', $ref_id );
					echo '<option value="' . $ref_id . '"';
					if ( isset( $_GET['ref'] ) && $_GET['ref'] == $ref_id ) echo ' selected="selected"';
					echo '>' . ucwords( $name ) . '</option>';
				}
				echo '</select>';
				$show = true;
			}

			// Filter by user
			if ( $this->core->can_edit_creds() && !$is_profile && $this->count_records() > 0 ) {
				echo '<input type="text" name="user_id" id="myCRED-user-filter" size="12" placeholder="' . __( 'User ID', 'mycred' ) . '" value="' . ( ( isset( $_GET['user_id'] ) ) ? $_GET['user_id'] : '' ) . '" /> ';
				$show = true;
			}

			// Filter Order
			if ( $this->count_records() > 0 ) {
				echo '<select name="order" id="myCRED-order-filter"><option value="">' . __( 'Show in order', 'mycred' ) . '</option>';
				$options = array( 'ASC' => __( 'Ascending', 'mycred' ), 'DESC' => __( 'Descending', 'mycred' ) );
				foreach ( $options as $value => $label ) {
					echo '<option value="' . $value . '"';
					if ( !isset( $_GET['order'] ) && $value == 'DESC' ) echo ' selected="selected"';
					elseif ( isset( $_GET['order'] ) && $_GET['order'] == $value ) echo ' selected="selected"';
					echo '>' . $label . '</option>';
				}
				echo '</select>';
				$show = true;
			}

			if ( $show === true )
				echo '<input type="submit" class="button medium" value="' . __( 'Filter', 'mycred' ) . '" />';

			echo '</div>';
		}

		/**
		 * Table Nav
		 * @since 0.1
		 * @version 1.0
		 */
		public function table_nav( $location = 'top', $is_profile = false, $amount = '' ) {
			if ( $location == 'top' ) {
				$this->filter_options( $is_profile );
				$this->item_count( $amount );
			} else {
				$this->item_count( $amount );
			}
		}

		/**
		 * Item Count
		 * @since 0.1
		 * @version 1.0
		 */
		public function item_count( $amount ) { ?>
				<div class="tablenav-pages one-page">
					<span class="displaying-num"><?php echo sprintf( __( 'Showing %d %s', 'mycred' ), $amount, _n( 'entry', 'entries', $amount, 'mycred' ) ); ?></span>
				</div>
<?php
		}

		/**
		 * Page Title
		 * @since 0.1
		 * @version 1.0
		 */
		public function page_title( $title = 'Log' ) {
			// Settings Link
			if ( $this->core->can_edit_plugin() )
				$link = '<a href="' . admin_url( 'admin.php?page=myCRED_page_settings' ) . '" class="add-new-h2">' . __( 'Settings', 'mycred' ) . '</a>';
			else
				$link = '';

			// Search Results
			if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
				$search_for = ' <span class="subtitle">' . __( 'Search results for', 'mycred' ) . ' "' . $_GET['s'] . '"</span>';
			else
				$search_for = '';

			echo apply_filters( 'mycred_label', myCRED_NAME ) . ' ' . $title . ' ' . $link . $search_for;
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page() {
			// Security
			if ( !$this->core->can_edit_creds( get_current_user_id() ) ) wp_die( __( 'Access Denied', 'mycred' ) );

			// Prep
			$args = array(
				'number' => $this->per_page
			);

			if ( isset( $_GET['user_id'] ) && !empty( $_GET['user_id'] ) )
				$args['user_id'] = $_GET['user_id'];

			if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
				$args['s'] = $_GET['s'];

			if ( isset( $_GET['ref'] ) && !empty( $_GET['ref'] ) )
				$args['ref'] = $_GET['ref'];

			if ( isset( $_GET['show'] ) && !empty( $_GET['show'] ) )
				$args['time'] = $_GET['show'];

			if ( isset( $_GET['order'] ) && !empty( $_GET['order'] ) )
				$args['order'] = $_GET['order'];

			$log = new myCRED_Query_Log( $args ); ?>

	<div class="wrap" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php $this->page_title(); ?></h2>
		<?php $log->filter_dates( admin_url( 'admin.php?page=myCRED' ) ); ?>

		<?php do_action( 'mycred_top_log_page', $this ); ?>

		<form method="get" action="">
			<?php

			if ( isset( $_GET['user_id'] ) && !empty( $_GET['user_id'] ) )
				echo '<input type="hidden" name="user_id" value="' . $_GET['user_id'] . '" />';

			if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
				echo '<input type="hidden" name="s" value="' . $_GET['s'] . '" />';

			if ( isset( $_GET['ref'] ) && !empty( $_GET['ref'] ) )
				echo '<input type="hidden" name="ref" value="' . $_GET['ref'] . '" />';

			if ( isset( $_GET['show'] ) && !empty( $_GET['show'] ) )
				echo '<input type="hidden" name="show" value="' . $_GET['show'] . '" />';

			if ( isset( $_GET['order'] ) && !empty( $_GET['order'] ) )
				echo '<input type="hidden" name="order" value="' . $_GET['order'] . '" />';

			$log->search(); ?>

			<input type="hidden" name="page" value="myCRED" />
			<?php do_action( 'mycred_above_log_table', $this ); ?>

			<div class="tablenav top">
				<?php $this->table_nav( 'top', false, $log->num_rows ); ?>

			</div>
			<?php $log->display(); ?>

			<div class="tablenav bottom">
				<?php $this->table_nav( 'bottom', false, $log->num_rows ); ?>

			</div>
			<?php do_action( 'mycred_bellow_log_table', $this ); ?>

		</form>
		<?php do_action( 'mycred_bottom_log_page', $this ); ?>

	</div>
<?php		$log->reset_query();
			unset( $log );
		}

		/**
		 * My History Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function my_history_page() {
			if ( !is_user_logged_in() ) wp_die( __( 'Access Denied', 'mycred' ) );

			$args = array(
				'user_id' => get_current_user_id(),
				'number'  => $this->per_page
			);

			if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
				$args['s'] = $_GET['s'];

			if ( isset( $_GET['ref'] ) && !empty( $_GET['ref'] ) )
				$args['ref'] = $_GET['ref'];

			if ( isset( $_GET['show'] ) && !empty( $_GET['show'] ) )
				$args['time'] = $_GET['show'];

			if ( isset( $_GET['order'] ) && !empty( $_GET['order'] ) )
				$args['order'] = $_GET['order'];

			$log = new myCRED_Query_Log( $args );
			unset( $log->headers['column-username'] ); ?>

	<div class="wrap" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php $this->page_title( __( 'My History', 'mycred' ) ); ?></h2>
		<?php $log->filter_dates( admin_url( 'users.php?page=mycred_my_history' ) ); ?>

		<?php do_action( 'mycred_top_my_log_page', $this ); ?>

		<form method="get" action="">
			<?php

			if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
				echo '<input type="hidden" name="s" value="' . $_GET['s'] . '" />';

			if ( isset( $_GET['ref'] ) && !empty( $_GET['ref'] ) )
				echo '<input type="hidden" name="ref" value="' . $_GET['ref'] . '" />';

			if ( isset( $_GET['show'] ) && !empty( $_GET['show'] ) )
				echo '<input type="hidden" name="show" value="' . $_GET['show'] . '" />';

			if ( isset( $_GET['order'] ) && !empty( $_GET['order'] ) )
				echo '<input type="hidden" name="order" value="' . $_GET['order'] . '" />';
			
			$log->search(); ?>

			<input type="hidden" name="page" value="mycred_my_history" />
			<?php do_action( 'mycred_above_my_log_table', $this ); ?>

			<div class="tablenav top">
				<?php $this->table_nav( 'top', true, $log->num_rows ); ?>

			</div>
			<?php $log->display(); ?>

			<div class="tablenav bottom">
				<?php $this->table_nav( 'bottom', true, $log->num_rows ); ?>

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
						if (
							( isset( $data['ref_type'] ) && $data['ref_type'] == 'post' ) || 
							( isset( $data['post_type'] ) && $post_type == $data['post_type'] )
						) {
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