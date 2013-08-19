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
			add_shortcode( 'mycred_history', array( $this, 'render_my_history' )           );
			
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
		 * @version 1.0
		 */
		public function settings_header() {
			// Since we are overwriting the myCRED_Module::settings_header() we need to enqueue admin styles
			wp_enqueue_style( 'mycred-admin' );

			$user_id = get_current_user_id();
			$screen = get_current_screen();

			// Prep Per Page
			$args = array(
				'label'   => __( 'Entries', 'mycred' ),
				'default' => 10,
				'option'  => 'mycred_entries_per_page'
			);
 			add_screen_option( 'per_page', $args );
			$per_page = get_user_meta( $user_id, $args['option'], true );
			if ( empty( $per_page ) || $per_page < 1 )
				$per_page = $args['default'];

			$this->per_page = $per_page;
			unset( $screen );
		}

		/**
		 * Count Records
		 * Returns the total number of rows from log
		 * @since 0.1
		 * @version 1.1
		 */
		public function count_records() {
			global $wpdb;

			return $wpdb->get_var( "SELECT COUNT(*) AS %s FROM " . $wpdb->prefix . 'myCRED_log' . ";" );
		}

		/**
		 * Get References
		 * Returns all available references in the database.
		 * @since 0.1
		 * @version 1.0
		 */
		protected function get_refs() {
			$refs = wp_cache_get( 'mycred_references' );
			if ( false === $refs ) {
				global $wpdb;

				$sql = "SELECT log.ref FROM " . $wpdb->prefix . 'myCRED_log' . " log WHERE %s <> '' ";
				$refs = $wpdb->get_col( $wpdb->prepare( $sql, 'ref' ) );

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
				echo '<input type="text" name="user_id" id="myCRED-user-filter" size="12" placeholder="' . __( 'Username', 'mycred' ) . '" value="' . ( ( isset( $_GET['user_id'] ) ) ? $_GET['user_id'] : '' ) . '" /> ';
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
			}
			else {
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
					<span class="displaying-num"><?php echo $amount; echo ' ' . _n( 'entry', 'entries', $amount, 'mycred' ); ?></span>
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

			$log = new myCRED_Query_Log( $args );
			$this->results = $log->results; ?>

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
<?php
			unset( $log );
			unset( $this );
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
			$this->results = $log->results;
			unset( $log->headers['column-username'] ); ?>

	<div class="wrap" id="myCRED-wrap">
		<div id="icon-myCRED" class="icon32"><br /></div>
		<h2><?php $this->page_title( __( 'My History', 'mycred' ) ); ?></h2>
		<?php $log->filter_dates( admin_url( 'users.php?page=mycred_my_history' ) ); ?>

		<?php do_action( 'mycred_top_my_log_page', $this ); ?>

		<form method="get" action="">
			<?php $log->search(); ?>

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
<?php
			unset( $log );
		}

		/**
		 * My History Shortcode render
		 * @since 0.1
		 * @version 1.1
		 */
		public function render_my_history( $atts ) {
			extract( shortcode_atts( array(
				'user_id'   => NULL,
				'number'    => NULL,
				'time'      => NULL,
				'ref'       => NULL,
				'order'     => NULL,
				'show_user' => false,
				'login'     => ''
			), $atts ) );

			// If we are not logged in
			if ( !is_user_logged_in() && !empty( $login ) ) return '<p class="mycred-history login">' . $login . '</p>';

			if ( $user_id === NULL )
				$user_id = get_current_user_id();

			$args = array();
			$args['user_id'] = $user_id;

			if ( $number !== NULL )
				$args['number'] = $number;

			if ( $time !== NULL )
				$args['time'] = $time;

			if ( $ref !== NULL )
				$args['ref'] = $ref;

			if ( $order !== NULL )
				$args['order'] = $order;

			$log = new myCRED_Query_Log( $args );
			$this->results = $log->results;

			if ( $show_user !== true )
				unset( $log->headers['column-username'] ); 

			return $log->get_display();
		}
		
		/**
		 * Handle Post Deletions
		 * @since 1.0.9.2
		 * @version 1.0
		 */
		public function post_deletions( $post_id ) {
			global $post_type, $wpdb;
			// Check log
			$sql = "SELECT * FROM " . $wpdb->prefix . 'myCRED_log' . " WHERE ref_id = %d ";
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
								$wpdb->prefix . 'myCRED_log',
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
			$sql = "SELECT * FROM " . $wpdb->prefix . 'myCRED_log' . " WHERE user_id = %d ";
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
						$wpdb->prefix . 'myCRED_log',
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
			$sql = "SELECT * FROM " . $wpdb->prefix . 'myCRED_log' . " WHERE ref_id = %d ";
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
								$wpdb->prefix . 'myCRED_log',
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
/**
 * Query Log
 * @see http://mycred.me/classes/mycred_query_log/ 
 * @since 0.1
 * @version 1.1
 */
if ( !class_exists( 'myCRED_Query_Log' ) ) {
	class myCRED_Query_Log {

		public $args;
		public $request;
		public $prep;
		public $result;
		public $num_rows;
		public $headers;

		/**
		 * Construct
		 */
		public function __construct( $args = array() ) {
			if ( empty( $args ) ) return false;

			global $wpdb;

			$select = $where = $sortby = $limits = '';
			$prep = $wheres = array();

			// Load General Settings
			$this->core = mycred_get_settings();
			if ( $this->core->format['decimals'] > 0 )
				$format = '%f';
			else
				$format = '%d';

			// Prep Defaults
			$defaults = array(
				'user_id'  => NULL,
				'ctype'    => $this->core->get_cred_id(),
				'number'   => 25,
				'time'     => NULL,
				'ref'      => NULL,
				'ref_id'   => NULL,
				'amount'   => NULL,
				's'        => NULL,
				'orderby'  => 'time',
				'order'    => 'DESC',
				'ids'      => false,
				'cache'    => NULL
			);
			$this->args = shortcode_atts( $defaults, $args );

			$data = false;
			if ( $this->args['cache'] !== NULL ) {
				$cache_id = substr( $this->args['cache'], 0, 23 );
				if ( is_multisite() )
					$data = get_site_transient( 'mycred_log_query_' . $cache_id );
				else
					$data = get_transient( 'mycred_log_query_' . $cache_id );
			}
			if ( $data === false ) {
				// Prep return
				if ( $this->args['ids'] === true )
					$select = 'SELECT id';
				else
					$select = 'SELECT *';

				$wheres[] = 'ctype = %s';
				$prep[] = $this->args['ctype'];

				// User ID
				if ( $this->args['user_id'] !== NULL ) {
					$wheres[] = 'user_id = %d';
					$prep[] = abs( $this->args['user_id'] );
				}

				// Reference
				if ( $this->args['ref'] !== NULL ) {
					$wheres[] = 'ref = %s';
					$prep[] = sanitize_text_field( $this->args['ref'] );
				}

				// Reference ID
				if ( $this->args['ref_id'] !== NULL ) {
					$wheres[] = 'ref_id = %d';
					$prep[] = sanitize_text_field( $this->args['ref_id'] );
				}

				// Amount
				if ( $this->args['amount'] !== NULL ) {
					// Range
					if ( is_array( $this->args['amount'] ) && array_key_exists( 'start', $this->args['amount'] ) && array_key_exists( 'end', $this->args['amount'] ) ) {
						$wheres[] = 'creds BETWEEN ' . $format . ' AND ' . $format;
						$prep[] = $this->core->format_number( sanitize_text_field( $this->args['amount']['start'] ) );
						$prep[] = $this->core->format_number( sanitize_text_field( $this->args['amount']['end'] ) );
					}
					// Compare
					elseif ( is_array( $this->args['amount'] ) && array_key_exists( 'num', $this->args['amount'] ) && array_key_exists( 'compare', $this->args['amount'] ) ) {
						$wheres[] = 'creds' . sanitize_text_field( $this->args['amount']['compare'] ) . ' ' . $format;
						$prep[] = $this->core->format_number( sanitize_text_field( $this->args['amount']['num'] ) );
					}
					// Specific amount
					else {
						$wheres[] = 'creds = ' . $format;
						$prep[] = $this->core->format_number( sanitize_text_field( $this->args['amount'] ) );
					}
				}

				// Time
				if ( $this->args['time'] !== NULL ) {
					$today = strtotime( date_i18n( 'Y/m/d' ) );
					$todays_date = date_i18n( 'd' );
					$tomorrow = strtotime( date_i18n( 'Y/m/d', date_i18n( 'U' )+86400 ) );
					$now = date_i18n( 'U' );

					// Show todays entries
					if ( $this->args['time'] == 'today' ) {
						$wheres[] = "time BETWEEN $today AND $now";
					}
					// Show yesterdays entries
					elseif ( $this->args['time'] == 'yesterday' ) {
						$yesterday = strtotime( date_i18n( 'Y/m/d', date_i18n( 'U' )-86400 ) );
						$wheres[] = "time BETWEEN $yesterday AND $today";
					}
					// Show this weeks entries
					elseif ( $this->args['time'] == 'thisweek' ) {
						$start_of_week = get_option( 'start_of_week' );
						$weekday = date_i18n( 'w' );
						// New week started today so show only todays
						if ( $start_of_week == $weekday ) {
							$wheres[] = "time BETWEEN $today AND $now";
						}
						// Show rest of this week
						else {
							$no_days_since_start_of_week = $weekday-$start_of_week;
							$weekstart = $no_days_since_start_of_week*86400;
							$weekstart = $today-$weekstart;
							$wheres[] = "time BETWEEN $weekstart AND $now";
						}
					}
					// Show this months entries
					elseif ( $this->args['time'] == 'thismonth' ) {
						$start_of_month = strtotime( date_i18n( 'Y/m/01' ) );
						$wheres[] = "time BETWEEN $start_of_month AND $now";
					}
				}

				// Search
				if ( $this->args['s'] !== NULL ) {
					$search_query = sanitize_text_field( $this->args['s'] );
					if ( is_int( $search_query ) )
					$search_query = (string) $search_query;

					if ( $this->args['user_id'] !== NULL ) {
						$user_id = $this->args['user_id'];
						$wheres[] = "entry LIKE '%$search_query%' OR user_id = $user_id AND data LIKE '%$search_query%' OR user_id = $user_id AND ref LIKE '%$search_query%'";
					}
					else
						$wheres[] = "entry LIKE '%$search_query%' OR data LIKE '%$search_query%' OR ref LIKE '%$search_query%'";
				}

				// Order by
				if ( !empty( $this->args['orderby'] ) ) {
					// Make sure $sortby is valid
					$sortbys = array( 'id', 'ref', 'ref_id', 'user_id', 'creds', 'ctype', 'entry', 'data', 'time' );
					$allowed = apply_filters( 'mycred_allowed_sortby', $sortbys );
					if ( in_array( $this->args['orderby'], $allowed ) ) {
						$sortby = "ORDER BY " . $this->args['orderby'] . " " . $this->args['order'];
					}
				}

				// Limits
				if ( $this->args['number'] == '-1' )
					$limits = '';
				elseif ( $this->args['number'] > 1 )
					$limits = 'LIMIT 0,' . absint( $this->args['number'] );

				// Filter
				$select = apply_filters( 'mycred_query_log_select', $select, $this->args, $this->core );
				$sortby = apply_filters( 'mycred_query_log_sortby', $sortby, $this->args, $this->core );
				$limits = apply_filters( 'mycred_query_log_limits', $limits, $this->args, $this->core );
				$wheres = apply_filters( 'mycred_query_log_wheres', $wheres, $this->args, $this->core );

				$prep = apply_filters( 'mycred_query_log_prep', $prep, $this->args, $this->core );

				$where = 'WHERE ' . implode( ' AND ', $wheres );

				// Run
				$this->request = "$select FROM " . $wpdb->prefix . 'myCRED_log' . " $where $sortby $limits";
				$this->results = $wpdb->get_results( $wpdb->prepare( $this->request, $prep ) );
				$this->prep = $prep;

				if ( $this->args['cache'] !== NULL ) {
					if ( is_multisite() )
						set_site_transient( 'mycred_log_query_' . $cache_id, $this->results, DAY_IN_SECONDS * 1 );
					else
						set_transient( 'mycred_log_query_' . $cache_id, $this->results, DAY_IN_SECONDS * 1 );
				}

				// Counts
				$this->num_rows = $wpdb->num_rows;
			}

			// Return the transient
			else {
				$this->request = 'transient';
				$this->results = $data;
				$this->prep = '';
				
				$this->num_rows = count( $data );
			}

			$this->headers = $this->table_headers();
		}

		/**
		 * Has Entries
		 * @returns true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function have_entries() {
			if ( !empty( $this->results ) ) return true;
			return false;
		}

		/**
		 * Table Headers
		 * Returns all table column headers.
		 *
		 * @filter mycred_log_column_headers
		 * @since 0.1
		 * @version 1.0
		 */
		public function table_headers() {
			return apply_filters( 'mycred_log_column_headers', array(
				'column-username' => __( 'User', 'mycred' ),
				'column-time'     => __( 'Date', 'mycred' ),
				'column-creds'    => $this->core->plural(),
				'column-entry'    => __( 'Entry', 'mycred' )
			), $this );
		}

		/**
		 * Display
		 * @since 0.1
		 * @version 1.0
		 */
		public function display() {
			echo $this->get_display();
		}

		/**
		 * Get Display
		 * Generates a table for our results.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_display() {
			$output = '
<table class="wp-list-table widefat fixed log-entries" cellspacing="0">
	<thead>
		<tr>';

			// Table header
			foreach ( $this->headers as $col_id => $col_title ) {
				$output .= '<th scope="col" id="' . str_replace( 'column-', '', $col_id ) . '" class="manage-column ' . $col_id . '">' . $col_title . '</th>';
			}

			$output .= '
		</tr>
	</thead>
	<tfoot>';

			// Table footer
			foreach ( $this->headers as $col_id => $col_title ) {
				$output .= '<th scope="col" class="manage-column ' . $col_id . '">' . $col_title . '</th>';
			}

			$output .= '
	</tfoot>
	<tbody id="the-list">';

			// Loop
			if ( $this->have_entries() ) {
				$alt = 0;
				foreach ( $this->results as $log_entry ) {
					$alt = $alt+1;
					if ( $alt % 2 == 0 )
						$class = ' alternate';
					else
						$class = '';

					$output .= '<tr class="myCRED-log-row' . $class . '">';
					$output .= $this->get_the_entry( $log_entry );
					$output .= '</tr>';
				}
			}
			// No log entry
			else {
				$output .= '<tr><td colspan="' . count( $this->headers ) . '" class="no-entries">' . $this->get_no_entries() . '</td></tr>';
			}

			$output .= '
	</tbody>
</table>' . "\n";

			return $output;
		}

		/**
		 * The Entry
		 * @since 0.1
		 * @version 1.1
		 */
		public function the_entry( $log_entry, $wrap = 'td' ) {
			echo $this->get_the_entry( $log_entry, $wrap );
		}

		/**
		 * Get The Entry
		 * Generated a single entry row depending on the columns used / requested.
		 *
		 * @since 0.1
		 * @version 1.2
		 */
		public function get_the_entry( $log_entry, $wrap = 'td' ) {
			$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			$entry_data = '';

			// Run though columns
			foreach ( $this->headers as $column_id => $column_name ) {
				switch ( $column_id ) {
					// Username Column
					case 'column-username':
						$user = get_userdata( $log_entry->user_id );

						if ( $user === false )
							$content = '<span>' . __( 'User Missing', 'mycred' ) . ' (ID: ' . $log_entry->user_id . ')</span>';
						else
							$content = '<span>' . $user->display_name . '</span>';

						unset( $user );
					break;
					// Date & Time Column
					case 'column-time' :
						$content = date_i18n( $date_format, $log_entry->time );
					break;
					// Amount Column
					case 'column-creds' :
						$content = $this->core->format_creds( $log_entry->creds );
					break;
					// Log Entry Column
					case 'column-entry' :
						$content = $this->core->parse_template_tags( $log_entry->entry, $log_entry );
					break;
				}
				$entry_data .= '<' . $wrap . ' class="' . $column_id . '">' . $content . '</' . $wrap . '>';
			}
			return $entry_data;
		}

		/**
		 * No Entries
		 * @since 0.1
		 * @version 1.0
		 */
		public function no_entries() {
			echo $this->get_no_entries();
		}

		/**
		 * Get No Entries
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_no_entries() {
			return __( 'No log entries found', 'mycred' );
		}

		/**
		 * Log Search
		 * @since 0.1
		 * @version 1.0
		 */
		public function search() {
			if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
				$serarch_string = $_GET['s'];
			else
				$serarch_string = ''; ?>

			<p class="search-box">
				<label class="screen-reader-text" for=""><?php _e( 'Search Log', 'mycred' ); ?>:</label>
				<input type="search" name="s" value="<?php echo $serarch_string; ?>" />
				<input type="submit" name="mycred-search-log" id="search-submit" class="button" value="<?php _e( 'Search Log', 'mycred' ); ?>" />
			</p>
<?php
		}

		/**
		 * Filter by Dates
		 * @since 0.1
		 * @version 1.0
		 */
		public function filter_dates( $url = '' ) {
			$date_sorting = apply_filters( 'mycred_sort_by_time', array(
				''          => __( 'All', 'mycred' ),
				'today'     => __( 'Today', 'mycred' ),
				'yesterday' => __( 'Yesterday', 'mycred' ),
				'thisweek'  => __( 'This Week', 'mycred' ),
				'thismonth' => __( 'This Month', 'mycred' )
			) );

			if ( !empty( $date_sorting ) ) {
				$total = count( $date_sorting );
				$count = 0;
				echo '<ul class="subsubsub">';
				foreach ( $date_sorting as $sorting_id => $sorting_name ) {
					$count = $count+1;
					echo '<li class="' . $sorting_id . '"><a href="';

					// Build Query Args
					$url_args = array();
					if ( isset( $_GET['user_id'] ) && !empty( $_GET['user_id'] ) )
						$url_args['user_id'] = $_GET['user_id'];
					if ( isset( $_GET['ref'] ) && !empty( $_GET['ref'] ) )
						$url_args['ref'] = $_GET['ref'];
					if ( isset( $_GET['order'] ) && !empty( $_GET['order'] ) )
						$url_args['order'] = $_GET['order'];
					if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
						$url_args['s'] = $_GET['s'];
					if ( !empty( $sorting_id ) )
						$url_args['show'] = $sorting_id;

					// Build URL
					if ( !empty( $url_args ) )
						echo add_query_arg( $url_args, $url );
					else
						echo $url;

					echo '"';

					if ( isset( $_GET['show'] ) && $_GET['show'] == $sorting_id ) echo ' class="current"';
					elseif ( !isset( $_GET['show'] ) && empty( $sorting_id ) ) echo ' class="current"';

					echo '>' . $sorting_name . '</a>';
					if ( $count != $total ) echo ' | ';
					echo '</li>';
				}
				echo '</ul>';
			}
		}
	}
}
?>