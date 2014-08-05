<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Hook class
 * @see http://mycred.me/classes/mycred_hook/
 * @since 0.1
 * @version 1.1.1
 */
if ( ! class_exists( 'myCRED_Hook' ) ) {
	abstract class myCRED_Hook {

		// Hook ID
		public $id;

		// myCRED_Settings Class
		public $core;

		// Multipoint types
		public $is_main_type = true;
		public $mycred_type = 'mycred_default';

		// Hook Prefs
		public $prefs = false;

		/**
		 * Construct
		 */
		function __construct( $args = array(), $hook_prefs = NULL, $type = 'mycred_default' ) {
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
			if ( $hook_prefs !== NULL ) {
				// Assign prefs if set
				if ( isset( $hook_prefs[ $this->id ] ) )
					$this->prefs = $hook_prefs[ $this->id ];

				// Defaults must be set
				if ( ! isset( $this->defaults ) )
					$this->defaults = array();
			}

			// Apply default settings if needed
			if ( ! empty( $this->defaults ) )
				$this->prefs = mycred_apply_defaults( $this->defaults, $this->prefs );
		}

		/**
		 * Run
		 * Must be over-ridden by sub-class!
		 * @since 0.1
		 * @version 1.0
		 */
		function run() {
			wp_die( __( 'function myCRED_Hook::run() must be over-ridden in a sub-class.', 'mycred' ) );
		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.0
		 */
		function preferences() {
			echo '<p>' . __( 'This Hook has no settings', 'mycred' ) . '</p>';
		}

		/**
		 * Sanitise Preference
		 * @since 0.1
		 * @version 1.0
		 */
		function sanitise_preferences( $data ) {
			return $data;
		}

		/**
		 * Get Field Name
		 * Returns the field name for the current hook
		 * @since 0.1
		 * @version 1.1
		 */
		function field_name( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( ! is_numeric( $parent ) )
						$array[] = $parent;

					if ( ! empty( $child ) && ! is_array( $child ) )
						$array[] = $child;
				}
				$field = '[' . implode( '][', $array ) . ']';
			}
			else {
				$field = '[' . $field . ']';
			}
			
			$option_id = 'mycred_pref_hooks';
			if ( ! $this->is_main_type )
				$option_id = $option_id . '_' . $this->mycred_type;

			return $option_id . '[hook_prefs][' . $this->id . ']' . $field;
		}

		/**
		 * Get Field ID
		 * Returns the field id for the current hook
		 * @since 0.1
		 * @version 1.1
		 */
		function field_id( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( ! is_numeric( $parent ) )
						$array[] = str_replace( '_', '-', $parent );

					if ( ! empty( $child ) && ! is_array( $child ) )
						$array[] = str_replace( '_', '-', $child );
				}
				$field = implode( '-', $array );
			}
			else {
				$field = str_replace( '_', '-', $field );
			}

			$option_id = 'mycred_pref_hooks';
			if ( ! $this->is_main_type )
				$option_id = $option_id . '_' . $this->mycred_type;

			$option_id = str_replace( '_', '-', $option_id );
			return $option_id . '-' . str_replace( '_', '-', $this->id ) . '-' . $field;
		}

		/**
		 * Impose Limits Dropdown
		 * @since 0.1
		 * @version 1.2
		 */
		function impose_limits_dropdown( $pref_id = '', $use_select = true ) {
			$limits = array(
				''           => __( 'No limit', 'mycred' ),
				'twentyfour' => __( 'Once every 24 hours', 'mycred' ),
				'twelve'     => __( 'Once every 12 hours', 'mycred' ),
				'sevendays'  => __( 'Once every 7 days', 'mycred' ),
				'daily'      => __( 'Once per day (reset at midnight)', 'mycred' )
			);
			$limits = apply_filters( 'mycred_hook_impose_limits', $limits );

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

			foreach ( $limits as $value => $description ) {
				echo '<option value="' . $value . '"';
				if ( $settings == $value ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Has Entry
		 * Moved to myCRED_Settings
		 * @since 0.1
		 * @version 1.3
		 */
		function has_entry( $action = '', $ref_id = '', $user_id = '', $data = '', $type = '' ) {
			if ( $type == '' )
				$type = $this->mycred_type;

			return $this->core->has_entry( $action, $ref_id, $user_id, $data, $type );
		}

		/**
		 * Available Template Tags
		 * @since 1.4
		 * @version 1.0
		 */
		function available_template_tags( $available = array(), $custom = '' ) {
			return $this->core->available_template_tags( $available, $custom );
		}
		
		/**
		 * Over Daily Limit
		 * @since 1.0
		 * @version 1.0
		 */
		public function is_over_daily_limit( $ref = '', $user_id = 0, $max = 0, $ref_id = NULL ) {
			global $wpdb;

			// Prep
			$reply = true;

			// Times
			$start = date_i18n( 'U', strtotime( 'today midnight' ) );
			$end = date_i18n( 'U' );

			// DB Query
			$total = $this->limit_query( $ref, $user_id, $start, $end, $ref_id );

			if ( $total !== NULL && $total < $max )
				$reply = false;

			return apply_filters( 'mycred_hook_over_daily_limit', $reply, $ref, $user_id, $max );
		}

		/**
		 * Limit Query
		 * Queries the myCRED log for the number of occurances of the specified
		 * refernece and optional reference id for a specific user between two dates.
		 * @param $ref (string) reference to search for, required
		 * @param $user_id (int) user id to search for, required
		 * @param $start (int) unix timestamp for start date, required
		 * @param $end (int) unix timestamp for the end date, required
		 * @param $ref_id (int) optional reference id to include in search
		 * @returns number of entries found (int) or NULL if required params are missing
		 * @since 1.4
		 * @version 1.0
		 */
		public function limit_query( $ref = '', $user_id = 0, $start = 0, $end = 0, $ref_id = NULL ) {
			global $wpdb;

			// Prep
			$reply = true;

			if ( empty( $ref ) || $user_id == 0 || $start == 0 || $end == 0 )
				return NULL;

			$ref = '';
			if ( $ref_id !== NULL )
				$ref = $wpdb->prepare( 'AND ref_id = %d ', $ref_id );

			// DB Query
			$total = $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT( * ) 
				FROM {$this->core->log_table} 
				WHERE ref = %s {$ref}
					AND user_id = %d 
					AND time BETWEEN %d AND %d;", $ref, $user_id, $start, $end ) );

			return apply_filters( 'mycred_hook_limit_query', $total, $ref, $user_id, $ref_id, $start, $end );
		}
	}
}
?>