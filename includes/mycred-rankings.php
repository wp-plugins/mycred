<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Rankings class
 * @see http://mycred.me/features/mycred_rankings/
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Rankings' ) ) {
	class myCRED_Rankings {

		public $core;
		public $args;
		public $results;

		private $frequency;

		/**
		 * Constructor
		 */
		public function __construct( $args = array(), $reload = false ) {
			// Get settings
			$mycred = mycred_get_settings();
			$this->core = $mycred;

			// Parse arguments
			$this->args = wp_parse_args( $args, array(
				'number'       => '-1',
				'offset'       => 0,
				'order'        => 'DESC',
				'allowed_tags' => '',
				'meta_key'     => $mycred->get_cred_id(),
				'template'     => '#%ranking% %user_profile_link% %cred_f%'
			) );
			$this->frequency = 12 * HOUR_IN_SECONDS;

			// Delete transient forcing a new query.
			$this->_transients( $reload );

			// Get rankings
			$this->get_rankings();
		}

		/**
		 * Transients
		 * Removes the transient if needed.
		 * @since 0.1
		 * @version 1.0
		 */
		public function _transients( $reload = false ) {
			if ( $this->core->frequency['rate'] == 'always' && $reload === false ) return;

			// Get history
			$history = get_option( 'mycred_transients' );

			// Rate
			if ( $this->core->frequency['rate'] == 'daily' )
				$today = date_i18n( 'd' );
			elseif ( $this->core->frequency['rate'] == 'weekly' )
				$today = date_i18n( 'W' );
			else
				$today = date_i18n( 'Y-m-d' );

			// If history is missing create it now
			if ( $history === false ) $history = array( $this->args['meta_key'] => '' );

			// Reset on a specific date
			if ( $this->core->frequency['rate'] == 'date' && $today == $this->core->frequency['date'] && empty( $history[$this->args['meta_key']] ) ) {
				$reload = true;
			}
			// Reset on a regular basis
			elseif ( $this->core->frequency['rate'] != 'date' && $today != $history[$this->args['meta_key']] ) {
				$reload = true;
			}

			// "Reset" by deleting the transient forcing a new database query
			if ( $reload === true ) {
				delete_transient( $this->args['meta_key'] . '_ranking' );
				$history[$this->args['meta_key']] = $today;
				update_option( 'mycred_transients', $history );
			}
		}

		/**
		 * Get Rankings
		 * Returns either the transient copy of the current results or queries a new one.
		 * @since 0.1
		 * @version 1.0
		 */
		protected function get_rankings() {
			// Get any existing copies of our transient data
			if ( false === ( $this->results = get_transient( $this->args['meta_key'] . '_ranking' ) ) ) {
				global $wpdb;

				// Transient missing, run new query
				$wp = $wpdb->prefix;
				$this->results = $wpdb->get_results( $wpdb->prepare( "SELECT {$wp}users.ID AS user_id, {$wp}users.display_name, {$wp}users.user_login, {$wp}usermeta.meta_value AS creds FROM {$wp}users LEFT JOIN {$wp}usermeta ON {$wp}users.ID = {$wp}usermeta.user_id AND {$wp}usermeta.meta_key= %s ORDER BY {$wp}usermeta.meta_value+1 DESC", ( empty( $this->args['meta_key'] ) ) ? 'mycred_default' : $this->args['meta_key'] ), 'ARRAY_A' );

				// Excludes
				foreach ( $this->results as $row_id => $row_data ) {
					if ( $this->core->exclude_user( $row_data['user_id'] ) )
						unset( $this->results[$row_id] );
				}

				// Save new transient
				set_transient( $this->args['meta_key'] . '_ranking', $this->results, $this->frequency );
			}

			// Reverse order if requested
			if ( $this->args['order'] == 'ASC' ) {
				$this->results = array_reverse( $this->results, true );
			}

			// Limit result if requested
			if ( $this->args['number'] != '-1' ) {
				$this->results = array_slice( $this->results, (int) $this->args['offset'], (int) $this->args['number'] );
			}
		}

		/**
		 * Have Results
		 * @returns true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function have_results() {
			if ( !empty( $this->results ) ) return true;
			return false;
		}

		/**
		 * Users Position
		 * @param $user_id (int) required user id
		 * @returns position (int)
		 * @since 0.1
		 * @version 1.0
		 */
		public function users_position( $user_id = '' ) {
			if ( $this->have_results() ) {
				foreach ( $this->results as $row_id => $row_data ) {
					if ( $row_data['user_id'] == $user_id ) return $row_id+1;
				}
			}
			else return 1;
		}

		/**
		 * Users Creds
		 * @param $user_id (int) user id
		 * @returns position (int) or empty
		 * @since 0.1
		 * @version 1.0
		 */
		public function users_creds( $user_id = NULL ) {
			if ( $user_id === NULL ) $user_id = get_current_user_id();
			if ( $this->have_results() ) {
				foreach ( $this->results as $row_id => $row_data ) {
					if ( $row_data['user_id'] == $user_id ) return $row_data['creds'];
				}
			}
			else return '';
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
		 * Generates an organized list for our results.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_display() {
			// Default template
			if ( empty( $this->args['template'] ) ) $this->args['template'] = '#%ranking% %user_profile_link% %cred_f%';

			// Organized list
			$output = '<ol class="myCRED-leaderboard">';

			// Loop
			foreach ( $this->results as $position => $row ) {
				// Prep
				$class = array();
				$url = get_author_posts_url( $row['user_id'] );

				// Classes
				$class[] = 'item-' . $position;
				if ( $position == 0 )
					$class[] = 'first-item';

				if ( $position % 2 != 0 )
					$class[] = 'alt';

				// Template Tags
				$layout = str_replace( '%rank%', $position+1, $this->args['template'] );

				$layout = $this->core->template_tags_amount( $layout, $row['creds'] );
				$layout = $this->core->template_tags_user( $layout, $row['user_id'], $row );

				$layout = apply_filters( 'mycred_ranking_row', $layout, $this->args['template'], $row, $position );
				$output .= '<li class="' . implode( ' ', $class ) . '">' . $layout . '</li>';
			}

			// End
			$output .= '</ol>';
			return $output;
		}
	}
}

/**
 * Get myCRED Rankings
 * Returns the myCRED_Rankings object containing results.
 *
 * @param $args (array) optional array of arguments for the ranking
 * @var $number (int) number of results to return
 * @var $order (string) ASC to return with lowest creds or DESC to return highest creds first
 * @var $meta_key (string) optional cred meta key to check for
 * @var $offset (int) optional number to start from when returning records. defaults to zero (first result)
 * @var $allowed_tags (string) optional string containing all HTML elements that are allowed to used.
 * @returns class object
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_rankings' ) ) {
	function mycred_rankings( $args = array() )
	{
		global $mycred_rankings;
		if ( !isset( $mycred_rankings ) || !empty( $args ) ) {
			$mycred_rankings = new myCRED_Rankings( $args );
		}
		return $mycred_rankings;
	}
}

/**
 * Get Users Position
 * Returns a given users position in the ranking list.
 *
 * @param $user_id (int) required user id
 * @param $args (array) optinal arguments to pass on for the db query
 * @returns position (int) or empty if no record could be made
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_rankings_position' ) ) {
	function mycred_rankings_position( $user_id = '' )
	{
		$rankings = mycred_rankings();
		if ( $rankings->have_results() ) {
			foreach ( $rankings->results as $row_id => $row_data ) {
				if ( $row_data['user_id'] == $user_id ) return $row_id+1;
			}
		}
		return '';
	}
}

/**
 * Force Leaderboard Update
 * @since 1.0.9.1
 * @version 1.0
 */
add_action( 'delete_user', 'mycred_adjust_ranking_delete_user' );
function mycred_adjust_ranking_delete_user( $user_id )
{
	$rankings = mycred_rankings();
	$rankings->_transients( true );
}
?>