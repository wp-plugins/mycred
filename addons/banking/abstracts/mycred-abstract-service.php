<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Service class
 * @see http://mycred.me/classes/mycred_service/
 * @since 1.2
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_Service' ) ) {
	abstract class myCRED_Service {

		// Service ID
		public $id;

		// myCRED_Settings Class
		public $core;

		// Multipoint types
		public $is_main_type = true;
		public $mycred_type = 'mycred_default';

		// Service Prefs
		public $prefs = false;

		/**
		 * Construct
		 */
		function __construct( $args = array(), $service_prefs = NULL, $type = 'mycred_default' ) {
			if ( ! empty( $args ) ) {
				foreach ( $args as $key => $value ) {
					$this->$key = $value;
				}
			}
			
			// Grab myCRED Settings
			$this->core = mycred( $type );

			if ( $type != '' ) {
				$this->core->cred_id = sanitize_text_field( $type );
				$this->mycred_type = $this->core->cred_id;
			}

			if ( $this->mycred_type != 'mycred_default' )
				$this->is_main_type = false;

			// Grab settings
			if ( $service_prefs !== NULL ) {
				// Assign prefs if set
				if ( isset( $service_prefs[ $this->id ]  ) )
					$this->prefs = $service_prefs[ $this->id ];

				// Defaults must be set
				if ( ! isset( $this->defaults ) )
					$this->defaults = array();
			}

			// Apply default settings if needed
			if ( ! empty( $this->defaults ) )
				$this->prefs = mycred_apply_defaults( $this->defaults,  $this->prefs );
		}

		/**
		 * Run
		 * Must be over-ridden by sub-class!
		 * @since 1.2
		 * @version 1.0
		 */
		function run() {
			wp_die( 'function myCRED_Service::run() must be over-ridden in a sub-class.' );
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		function preferences() {
			echo '<p>' . __( 'This Service has no settings', 'mycred' ) . '</p>';
		}

		/**
		 * Sanitise Preference
		 * @since 1.2
		 * @version 1.0
		 */
		function sanitise_preferences( $post ) {
			return $post;
		}

		/**
		 * Activate
		 * @since 1.5.2
		 * @version 1.0
		 */
		function activate() { }

		/**
		 * Deactivate
		 * @since 1.2
		 * @version 1.0
		 */
		function deactivate() { }

		/**
		 * User Override
		 * @since 1.5.2
		 * @version 1.0
		 */
		function user_override( $user = NULL, $type = 'mycred_default' ) { }

		/**
		 * Save User Override
		 * @since 1.5.2
		 * @version 1.0
		 */
		function save_user_override() { }

		/**
		 * User Override Notice
		 * @since 1.5.2
		 * @version 1.0
		 */
		function user_override_notice() { }

		/**
		 * Get Field Name
		 * Returns the field name for the current service
		 * @since 1.2
		 * @version 1.1
		 */
		function field_name( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( ! is_numeric( $parent ) )
						$array[] = $parent;

					if ( ! empty( $child ) && !is_array( $child ) )
						$array[] = $child;
				}
				$field = '[' . implode( '][', $array ) . ']';
			}
			else {
				$field = '[' . $field . ']';
			}
			
			$option_id = 'mycred_pref_bank';
			if ( ! $this->is_main_type )
				$option_id = $option_id . '_' . $this->mycred_type;

			return $option_id . '[service_prefs][' . $this->id . ']' . $field;
		}

		/**
		 * Get Field ID
		 * Returns the field id for the current service
		 * @since 1.2
		 * @version 1.1
		 */
		function field_id( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( ! is_numeric( $parent ) )
						$array[] = str_replace( '_', '-', $parent );

					if ( ! empty( $child ) && !is_array( $child ) )
						$array[] = str_replace( '_', '-', $child );
				}
				$field = implode( '-', $array );
			}
			else {
				$field = str_replace( '_', '-', $field );
			}
			
			$option_id = 'mycred_pref_bank';
			if ( ! $this->is_main_type )
				$option_id = $option_id . '_' . $this->mycred_type;

			$option_id = str_replace( '_', '-', $option_id );
			return $option_id . '-' . str_replace( '_', '-', $this->id ) . '-' . $field;
		}

		/**
		 * Exclude User Check
		 * @since 1.5.2
		 * @version 1.0
		 */
		function exclude_user( $user_id = NULL ) {

			$reply = false;

			// Check if we are excluded based on ID
			if ( isset( $this->prefs['exclude_ids'] ) && $this->prefs['exclude_ids'] != '' ) {

				$excluded_ids = explode( ',', $this->prefs['exclude_ids'] );
				if ( ! empty( $excluded_ids ) ) {
					$clean_ids = array();
					foreach ( $excluded_ids as $id )
						$clean_ids[] = (int) trim( $id );

					if ( in_array( $user_id, $clean_ids ) )
						$reply = 'list';
				}

			}

			// Check if we are excluded based on role
			if ( $reply === false && isset( $this->prefs['exclude_roles'] ) && ! empty( $this->prefs['exclude_roles'] ) ) {

				$user = new WP_User( $user_id );
				if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
					foreach ( $user->roles as $role ) {
						if ( in_array( $role, $this->prefs['exclude_roles'] ) )
							$reply = 'role';
					}
				}

			}

			return apply_filters( 'mycred_banking_exclude_user', $reply, $user_id, $this );
		}

		/**
		 * Get Timeframes
		 * @since 1.2
		 * @version 1.0
		 */
		function get_timeframes() {
			$timeframes = array(
				'hourly'    => array(
					'label'       => __( 'Hourly', 'mycred' ),
					'date_format' => 'G'
				),
				'daily'     => array(
					'label'       => __( 'Daily', 'mycred' ),
					'date_format' => 'z'
				),
				'weekly'    => array(
					'label'       => __( 'Weekly', 'mycred' ),
					'date_format' => 'W'
				),
				'monthly'   => array(
					'label'       => __( 'Monthly', 'mycred' ),
					'date_format' => 'M'
				),
				'quarterly'  => array(
					'label'       => __( 'Quarterly', 'mycred' ),
					'date_format' => 'Y'
				),
				'semiannually'  => array(
					'label'       => __( 'Semiannually', 'mycred' ),
					'date_format' => 'Y'
				),
				'annually'  => array(
					'label'       => __( 'Annually', 'mycred' ),
					'date_format' => 'Y'
				)
			);
			return apply_filters( 'mycred_banking_timeframes', $timeframes );
		}

		/**
		 * Get Now
		 * @since 1.2
		 * @version 1.0
		 */
		public function get_now( $rate = '' ) {

			$timeframes = $this->get_timeframes();
			if ( array_key_exists( $rate, $timeframes ) ) {
				// Quarterly
				if ( $rate == 'quarterly' ) {
					$month = date_i18n( 'n' );
					return 'Q' . ceil( $month/3 );
				}
				elseif ( $rate == 'semiannually' ) {
					$month = date_i18n( 'n' );
					return ( $month <= 6 ) ? 'first' : 'second';
				}
				else {
					return date_i18n( $timeframes[ $rate ]['date_format'] );
				}
			}
			
			return false;

		}

		/**
		 * Get Run Count
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function get_run_count( $instance = '' ) {
			$key = 'mycred_brc_' . $instance . '_' . $this->mycred_type;
			return mycred_get_option( $key, 0 );
		}

		/**
		 * Update Run Count
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function update_run_count( $instance = '' ) {

			$count = $this->get_run_count( $instance );
			$count ++;
			mycred_update_option( 'mycred_brc_' . $instance . '_' . $this->mycred_type, $count );

		}

		/**
		 * Last Run
		 * @since 1.2
		 * @version 1.1
		 */
		public function get_last_run( $instance = '' ) {
			$key = 'mycred_banking_' . $this->id . '_' . $instance . $this->mycred_type;
			return mycred_get_option( $key, 'n/a' );
		}

		/**
		 * Save Last Run
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function save_last_run( $instance = '', $time = NULL ) {

			if ( $time === NULL ) $time = date_i18n( 'U' );
			mycred_update_option( 'mycred_banking_' . $this->id . '_' . $instance . $this->mycred_type, $time );

		}

		/**
		 * Display Last Run
		 * @since 1.5.2
		 * @version 1.0
		 */
		public function display_last_run( $instance = '' ) {
			$last_run = $this->get_last_run( $instance );
			if ( $last_run != 'n/a' )
				$last_run = date( 'Y-m-d G:i', $last_run );
			
			return $last_run;
		}

		/**
		 * Time To Run
		 * @since 1.2.2
		 * @version 1.1
		 */
		public function time_to_run( $rate, $last_run ) {
			$now = $this->get_now( $rate );
			if ( $last_run == 'n/a' ) return false;

			$timeframes = $this->get_timeframes();
			$last_run = date_i18n( $timeframes[ $rate ]['date_format'], $last_run );

			switch ( $rate ) {

				case 'hourly' :

					if ( $now == 0 && $last_run == 23 ) return true;
					elseif ( $now-1 == $last_run ) return true;

				break;

				case 'daily' :

					if ( $now == 0 && $last_run >= 365 ) return true;
					elseif ( $now-1 == $last_run ) return true;

				break;

				case 'weekly' :

					if ( $now == 0 && $last_run >= 52 ) return true;
					elseif ( $now-1 == $last_run ) return true;

				break;

				case 'monthly' :

					if ( $now == 1 && $last_run == 12 ) return true;
					elseif ( $now-1 == $last_run ) return true;

				break;

				case 'quarterly' :

					$current_quarter = substr( $now, 0, -1 );
					if ( $current_quarter == 1 )
						$last_quarter = 4;
					else
						$last_quarter = $current_quarter-1;
					if ( 'Q' . $last_quarter == $last_run ) return true;

				break;

				case 'semiannually' :

					if ( $now != $last_run ) return true;

				break;

				case 'annually' :

					if ( $now-1 == $last_run ) return true;

				break;

				default :

					return apply_filters( 'mycred_banking_time_to_run', false, $rate, $last_run );

				break;

			}

			return false;
		}

		/**
		 * Get Users
		 * Returns all blog users IDs either from a daily transient or
		 * by making a fresh SQL Query.
		 * @since 1.2
		 * @version 1.2
		 */
		public function get_users() {
			// Get daily transient 
			$data = get_transient( 'mycred_banking_payout_ids' );
			
			// If the user count does not equal the total number of users, get a
			// new result, else run the same.
			if ( $data !== false ) {
				$user_count = $this->core->count_members();
				$cached_count = count( $data );
				if ( $cached_count != $user_count ) {
					unset( $data );
					$data = false;
				}
			}
			
			// New Query
			if ( $data === false ) {
				global $wpdb;
				$data = $wpdb->get_col( "SELECT ID FROM {$wpdb->users};" );
				
				foreach ( $data as $num => $user_id ) {
					$user_id = intval( $user_id );
					if ( isset( $this->prefs['excludes'] ) ) {
						if ( ! empty( $this->prefs['excludes'] ) ) {
							if ( ! is_array( $this->prefs['excludes'] ) ) $excludes = explode( ',', $this->prefs['excludes'] );
							if ( in_array( $user_id, $excludes )  ) unset( $data[ $num ] );
						}
					}
					
					if ( $this->core->exclude_user( $user_id ) ) unset( $data[ $num ] );
				}
				
				set_transient( 'mycred_banking_payout_ids', $data, DAY_IN_SECONDS );
				$wpdb->flush();
			}
			
			return $data;
		}

		/**
		 * Get Days in Year
		 * @since 1.2
		 * @version 1.0.1
		 */
		public function get_days_in_year() {
			if ( date_i18n( 'L' ) )
				$days = 366;
			else
				$days = 365;
			return apply_filters( 'mycred_banking_days_in_year', $days, $this );
		}

		/**
		 * Timeframe Dropdown
		 * @since 1.2
		 * @version 1.0
		 */
		function timeframe_dropdown( $pref_id = '', $use_select = true, $hourly = true ) {
			
			$timeframes = $this->get_timeframes();
			if ( ! $hourly )
				unset( $timeframes['hourly'] );
			
			echo '<select name="' . $this->field_name( $pref_id ) . '" id="' . $this->field_id( $pref_id ) . '">';
			
			if ( $use_select )
				echo '<option value="">' . __( 'Select', 'mycred' ) . '</option>';

			$settings = '';
			if ( is_array( $pref_id ) ) {
				reset( $pref_id );
				$key = key( $pref_id );
				$settings = $this->prefs[ $key ][ $pref_id[ $key ] ];
			}
			elseif ( isset( $this->prefs[ $pref_id ] ) ) {
				$settings = $this->prefs[ $pref_id ];
			}
			
			foreach ( $timeframes as $value => $details ) {
				echo '<option value="' . $value . '"';
				if ( $settings == $value ) echo ' selected="selected"';
				echo '>' . $details['label'] . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Get Eligeble Users
		 * Returns an array of user IDs that are not excluded
		 * from this service.
		 * @since 1.5.2
		 * @version 1.1
		 */
		public function get_eligeble_users() {

			global $wpdb;
			$joins = $wheres = array();

			$format = '%d';
			if ( $this->core->format['decimals'] > 0 )
				$format = '%f';

			// Minimum Balance
			if ( isset( $this->prefs['min_balance'] ) && $this->prefs['min_balance'] != '' && $this->prefs['min_balance'] != 0 ) {

				$balance_key = $this->mycred_type;
				if ( is_multisite() && $GLOBALS['blog_id'] > 1 && ! $this->core->use_central_logging )
					$balance_key .= '_' . $GLOBALS['blog_id'];

				$joins[] = $wpdb->prepare( "INNER JOIN {$wpdb->usermeta} balance ON ( users.ID = balance.user_id AND balance.meta_key = %s )", $balance_key );
				$wheres[] = $wpdb->prepare( "balance.meta_value > {$format}", $this->prefs['min_balance'] );

			}

			// Exclude IDs
			if ( isset( $this->prefs['exclude_ids'] ) && $this->prefs['exclude_ids'] != '' ) {
				$clean_ids = array();
				$the_list = explode( ',', $this->prefs['exclude_ids'] );
				foreach ( $the_list as $user_id ) {
					$user_id = trim( $user_id );
					if ( $user_id == '' || $user_id == 0 ) continue;
					$clean_ids[] = (int) $user_id;
				}
				
				if ( count( $clean_ids ) > 0 )
					$wheres[] = "users.ID NOT IN (" . implode( ', ', $clean_ids ) . ")";
			}

			// Core Excludes
			if ( $this->core->exclude['list'] != '' ) {
				$clean_ids = array();
				$the_list = explode( ',', $this->core->exclude['list'] );
				foreach ( $the_list as $user_id ) {
					$user_id = trim( $user_id );
					if ( $user_id == '' || $user_id == 0 ) continue;
					$clean_ids[] = (int) $user_id;
				}
				
				if ( count( $clean_ids ) > 0 )
					$wheres[] = "users.ID NOT IN (" . implode( ', ', $clean_ids ) . ")";
			}

			// Exclude roles
			if ( isset( $this->prefs['exclude_roles'] ) && ! empty( $this->prefs['exclude_roles'] ) ) {
				$cap_id = $wpdb->prefix . 'capabilities';
				
				$joins[] = $wpdb->prepare( "INNER JOIN {$wpdb->usermeta} role ON ( users.ID = role.user_id AND role.meta_key = %s )", $cap_id );
				
				$excluded_roles = array();
				foreach ( $this->prefs['exclude_roles'] as $role_id )
					$excluded_roles[] = "'%" . $role_id . "%'";

				$wheres[] = "role.meta_value NOT LIKE " . implode( " AND role.meta_value NOT LIKE ", $excluded_roles );
			}

			// Construct Query
			$SQL = "SELECT DISTINCT users.ID FROM {$wpdb->users} users ";

			if ( ! empty( $joins ) )
				$SQL .= implode( " ", $joins ) . " ";

			if ( ! empty( $wheres ) )
				$SQL .= "WHERE " . implode( " AND ", $wheres ) . " ";

			// The Query
			$users = $wpdb->get_col( $SQL );
			if ( $users === NULL )
				$users = array();

			return $users;

		}

	}
}
?>