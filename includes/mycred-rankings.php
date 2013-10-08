<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED Query Rankings Class
 * @see http://mycred.me/features/mycred_query_rankings/
 * @since 1.1.2
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Query_Rankings' ) ) {
	class myCRED_Query_Rankings {

		public $args;
		public $count = 0;
		public $result;

		/**
		 * Constructor
		 */
		public function __construct( $args = '' ) {
			$this->args = shortcode_atts( array(
				'number'      => '-1',
				'order'       => 'DESC',
				'user_fields' => 'user_login,display_name,user_email,user_nicename,user_url',
				'offset'      => 0,
				'type'        => 'mycred_default'
			), $args );
		}
		
		/**
		 * Have Results
		 * @returns true or false
		 * @since 1.1.2
		 * @version 1.0
		 */
		public function have_results() {
			if ( !empty( $this->result ) ) return true;
			
			return false;
		}

		/**
		 * Get Rankings
		 * Queries the DB for all users in order of their point balance.
		 * @since 1.1.2
		 * @version 1.0.1
		 */
		public function get_rankings() {
			global $wpdb;

			// Type can not be empty
			if ( !empty( $this->args['type'] ) )
				$key = $this->args['type'];
			else
				$key = 'mycred_default';
			
			// Order
			if ( !empty( $this->args['order'] ) )
				$order = $this->args['order'];
			else
				$order = 'DESC';

			// Number
			if ( $this->args['number'] != '-1' )
				$limit = 'LIMIT ' . abs( $this->args['offset'] ) . ',' . abs( $this->args['number'] );
			else
				$limit = '';

			// User fields
			if ( empty( $this->args['user_fields'] ) )
				$this->args['user_fields'] = 'display_name,user_login';
			
			$user_fields = trim( $this->args['user_fields'] );
			$user_fields = str_replace( ' ', '', $user_fields );
			$user_fields = explode( ',', $user_fields );
			
			// Start constructing query
			
			$prep = array();
			
			// SELECT
			$selects = array( "{$wpdb->users}.ID" );
			foreach ( $user_fields as $field ) {
				if ( $field == 'ID' ) continue;
				$selects[] = "{$wpdb->users}." . $field;
			}
			$selects[] = "{$wpdb->usermeta}.meta_value AS cred";
			
			$select = implode( ', ', $selects );
			$SQL = "SELECT {$select} 
					FROM {$wpdb->users} 
					LEFT JOIN {$wpdb->usermeta} 
					ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND {$wpdb->usermeta}.meta_key = %s 
					ORDER BY {$wpdb->usermeta}.meta_value+1 {$order} {$limit};";
			
			$this->result = $wpdb->get_results( $wpdb->prepare( $SQL, $key ), 'ARRAY_A' );
			$this->count = $wpdb->num_rows;
		}

		/**
		 * Save
		 * With the option to reset and bypass any set frequency.
		 * If a frequency is set to something other then 'always', then that
		 * setting is enforced.
		 * @since 1.1.2
		 * @version 1.0
		 */
		public function save( $reset = false ) {
			// Will not save no results
			if ( !$this->have_results() ) return;

			// Prep
			$mycred = mycred_get_settings();
			$save = $reset;
			$lifespan = 1 * DAY_IN_SECONDS;

			$history = get_option( 'mycred_transients' );
			// If history has never been set
			if ( $history === false )
				$history = array( $this->args['type'] => '' );

			// If transiet is missing for this type
			if ( !isset( $history[ $this->args['type'] ] ) )
				$history[ $this->args['type'] ] = '';

			// Always updated
			if ( $mycred->frequency['rate'] == 'always' ) {
				$save = true;
			}
			// Daily updates
			elseif ( $mycred->frequency['rate'] == 'daily' ) {
				$today = date_i18n( 'd' );
				if ( $today != $history[ $this->args['type'] ] ) $save = true;
			}
			// Weekly update
			elseif ( $mycred->frequency['rate'] == 'weekly' ) {
				$today = date_i18n( 'W' );
				if ( $today != $history[ $this->args['type'] ] ) $save = true;
				$lifespan = 1 * WEEK_IN_SECONDS;
			}
			// Update on specific date
			elseif ( $mycred->frequency['rate'] == 'date' ) {
				$date = $mycred->frequency['date'];
				$today = date_i18n( 'Y-m-d' );
				if ( $today == $date ) $save = true;
				$lifespan = 12 * HOUR_IN_SECONDS;
			}

			// Save new transient
			if ( $save === true ) {
				delete_transient( $this->args['type'] . '_ranking' );
				set_transient( $this->args['type'] . '_ranking', $this->result, $lifespan );
				
				if ( $mycred->frequency['rate'] != 'always' ) {
					$history[ $this->args['type'] ] = $today;
					update_option( 'mycred_transients', $history );
				}
			}
		}
	}
}

/**
 * myCRED_Rankings class
 * @see http://mycred.me/features/mycred_rankings/
 * @since 0.1
 * @version 2.0
 */
if ( !class_exists( 'myCRED_Rankings' ) ) {
	class myCRED_Rankings {

		public $core;
		public $args;
		public $result;

		/**
		 * Constructor
		 */
		public function __construct( $args = array(), $results = array() ) {
			// Get settings
			$mycred = mycred_get_settings();
			$this->core = $mycred;
			$this->args = $args;
			$this->result = $results;
		}

		/**
		 * Have Results
		 * @returns true or false
		 * @since 0.1
		 * @version 1.1
		 */
		public function have_results() {
			if ( !empty( $this->result ) ) return true;
			return false;
		}

		/**
		 * Users Position
		 * @param $user_id (int) required user id
		 * @returns position (int)
		 * @since 0.1
		 * @version 1.1
		 */
		public function users_position( $user_id = NULL ) {
			if ( $user_id !== NULL ) {
				if ( $this->have_results() ) {
					foreach ( $this->result as $row_id => $row_data ) {
						if ( !isset( $row_data['ID'] ) ) $row_uid = $row_data['user_id'];
						else $row_uid = $row_data['ID'];

						if ( $row_uid == (int) $user_id ) return $row_id+1;
					}
				}
			}

			return 0;
		}

		/**
		 * Users Creds
		 * @param $user_id (int) user id
		 * @returns position (int) or empty
		 * @since 0.1
		 * @version 1.1
		 */
		public function users_creds( $user_id = NULL ) {
			if ( $user_id !== NULL ) {
				if ( $this->have_results() ) {
					foreach ( $this->result as $row_id => $row_data ) {
						if ( !isset( $row_data['ID'] ) ) $row_uid = $row_data['user_id'];
						else $row_uid = $row_data['ID'];

						if ( $row_uid == (int) $user_id ) return $row_data['creds'];
					}
				}
			}

			return 0;
		}

		/**
		 * Leaderboard
		 * @since 0.1
		 * @version 1.0
		 */
		public function leaderboard() {
			echo $this->get_leaderboard();
		}

		/**
		 * Get Leaderboard
		 * @since 0.1
		 * @version 1.1
		 */
		public function get_leaderboard() {
			return '<ol class="myCRED-leaderboard">' . $this->loop( 'li' ) . '</ol>';
		}

		/**
		 * Leaderboard Loop
		 * @since 1.1.2
		 * @version 1.0
		 */
		public function loop( $wrap = '' ) {
			// Default template
			if ( empty( $this->args['template'] ) ) $this->args['template'] = '#%ranking% %user_profile_link% %cred_f%';
			$output = '';

			// Loop
			foreach ( $this->result as $position => $row ) {
				// Prep
				$class = array();

				// Classes
				$class[] = 'item-' . $position;
				if ( $position == 0 )
					$class[] = 'first-item';

				if ( $position % 2 != 0 )
					$class[] = 'alt';

				// Template Tags
				if ( !function_exists( 'mycred_get_users_rank' ) )
					$layout = str_replace( array( '%rank%', '%ranking%' ), $position+1, $this->args['template'] );
				else
					$layout = str_replace( '%ranking%', $position+1, $this->args['template'] );

				$layout = $this->core->template_tags_amount( $layout, $row['cred'] );
				$layout = $this->core->template_tags_user( $layout, false, $row );

				$layout = apply_filters( 'mycred_ranking_row', $layout, $this->args['template'], $row, $position+1 );

				// Wrapper
				if ( !empty( $wrap ) )
					$layout = '<' . $wrap . ' class="%classes%">' . $layout . '</' . $wrap . '>';

				$layout = str_replace( '%classes%', implode( ' ', $class ), $layout );
				$output .= $layout . "\n";
			}

			return $output;
		}
	}
}

/**
 * Get myCRED Rankings
 * Returns the myCRED_Rankings object containing results.
 *
 * @param $args (array) optional array of arguments for the ranking
 * @var number (int) number of results to return
 * @var order (string) ASC to return with lowest creds or DESC to return highest creds first
 * @var user_fields (string) comma seperated list of table columns to return with each user.
 * @var offset (int) optional number to start from when returning records. defaults to zero (first result)
 * @var type (string) optional points type
 * @var template (string) if this function is called to create a leaderboard this string can contain the template
 * for each user
 * @uses myCRED_Query_Rankings()
 * @uses myCRED_Rankings()
 * @returns class object
 * @since 0.1
 * @version 2.0
 */
if ( !function_exists( 'mycred_rankings' ) ) {
	function mycred_rankings( $args = array(), $reset = false )
	{
		$default = array(
			'number'      => '-1',
			'order'       => 'DESC',
			'user_fields' => 'user_login,display_name,user_email,user_nicename,user_url',
			'offset'      => 0,
			'type'        => 'mycred_default',
			'template'    => '#%ranking% %user_profile_link% %cred_f%'
		);
		$args = shortcode_atts( $default, $args );
		$diff = array_diff( $args, $default );

		global $mycred_rankings;

		$_rankings = get_transient( $args['type'] . '_ranking' );
		// Transient is missing or request for reset
		if ( false === $_rankings || true === $reset ) {
			$ranking = new myCRED_Query_Rankings( array( 'type' => $args['type'] ) );
			$ranking->get_rankings();
			$ranking->save( $reset );
		
			$_rankings = $ranking->result;
		}
		// Else if arguments are not the default and a new query is required
		elseif ( !empty( $diff ) ) {
			$ranking = new myCRED_Query_Rankings( $args );
			$ranking->get_rankings();
		
			$_rankings = $ranking->result;
		}
		$mycred_rankings = new myCRED_Rankings( $args, $_rankings );

		return $mycred_rankings;
	}
}

/**
 * Get Users Position
 * Returns a given users position in the ranking list.
 *
 * @param $user_id (int) required user id
 * @param $type (string) optional points type
 * @returns position (int) or empty if no record could be made
 * @since 0.1
 * @version 1.1
 */
if ( !function_exists( 'mycred_rankings_position' ) ) {
	function mycred_rankings_position( $user_id = '', $type = 'mycred_default' )
	{
		$rankings = mycred_rankings( array( 'type' => $type ) );
		return $rankings->users_position( $user_id );
	}
}

/**
 * Force Leaderboard Update
 * @since 1.0.9.1
 * @version 1.1
 */
add_action( 'delete_user', 'mycred_adjust_ranking_delete_user' );
function mycred_adjust_ranking_delete_user( $user_id )
{
	$ranking = new myCRED_Query_Rankings();
	$ranking->get_rankings();
	$ranking->save( true );
}
?>