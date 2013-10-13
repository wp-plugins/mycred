<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;

/**
 * Query Log
 * @see http://mycred.me/classes/mycred_query_log/ 
 * @since 0.1
 * @version 1.2
 */
if ( !class_exists( 'myCRED_Query_Log' ) ) {
	class myCRED_Query_Log {

		public $args;
		public $request;
		public $prep;
		public $results;
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
				
					$wheres[] = "entry LIKE %s OR data LIKE %s OR ref LIKE %s";
					$prep[] = "%$search_query%";
					$prep[] = "%$search_query%";
					$prep[] = "%$search_query%";
					
					if ( $this->args['user_id'] !== NULL ) {
						$wheres[] = 'AND user_id = %d';
						$prep[] = $user_id;
					}
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
				elseif ( $this->args['number'] > 0 )
					$limits = 'LIMIT 0,' . absint( $this->args['number'] );

				// Filter
				$select = apply_filters( 'mycred_query_log_select', $select, $this->args, $this->core );
				$sortby = apply_filters( 'mycred_query_log_sortby', $sortby, $this->args, $this->core );
				$limits = apply_filters( 'mycred_query_log_limits', $limits, $this->args, $this->core );
				$wheres = apply_filters( 'mycred_query_log_wheres', $wheres, $this->args, $this->core );

				$prep = apply_filters( 'mycred_query_log_prep', $prep, $this->args, $this->core );

				$where = 'WHERE ' . implode( ' AND ', $wheres );

				// Run
				$this->request = "{$select} FROM {$this->core->log_table} {$where} {$sortby} {$limits};";
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
						$class = ' alt';
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
		 * @filter mycred_log_date
		 * @since 0.1
		 * @version 1.2.1
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
						$content = apply_filters( 'mycred_log_date', date_i18n( $date_format, $log_entry->time ), $log_entry->time );
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
		 * @version 1.0.1
		 */
		public function search() {
			if ( isset( $_GET['s'] ) && !empty( $_GET['s'] ) )
				$serarch_string = $_GET['s'];
			else
				$serarch_string = ''; ?>

			<p class="search-box">
				<label class="screen-reader-text" for=""><?php _e( 'Search Log', 'mycred' ); ?>:</label>
				<input type="search" name="s" value="<?php echo $serarch_string; ?>" placeholder="<?php _e( 'search log entries', 'mycred' ); ?>" />
				<input type="submit" name="mycred-search-log" id="search-submit" class="button button-medium button-secondary" value="<?php _e( 'Search Log', 'mycred' ); ?>" />
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
		
		/**
		 * Reset Query
		 * @since 1.3
		 * @version 1.0
		 */
		public function reset_query() {
			$this->args = NULL;
			$this->request = NULL;
			$this->prep = NULL;
			$this->results = NULL;
			$this->num_rows = NULL;
			$this->headers = NULL;
		}
	}
}
?>