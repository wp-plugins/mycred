<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Service class
 * @see http://mycred.me/classes/mycred_service/
 * @since 1.2
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Service' ) ) {
	abstract class myCRED_Service {

		// Service ID
		public $id;

		// myCRED_Settings Class
		public $core;

		// Service Prefs
		public $prefs = false;

		/**
		 * Construct
		 */
		function __construct( $args = array(), $service_prefs = NULL ) {
			if ( !empty( $args ) ) {
				foreach ( $args as $key => $value ) {
					$this->$key = $value;
				}
			}
			
			// Grab myCRED Settings
			$this->core = mycred_get_settings();

			// Grab settings
			if ( $service_prefs !== NULL ) {
				// Assign prefs if set
				if ( isset( $service_prefs[$this->id] ) )
					$this->prefs = $service_prefs[$this->id];

				// Defaults must be set
				if ( !isset( $this->defaults ) || empty( $this->defaults ) )
					$this->defaults = array();
			}

			// Apply default settings if needed
			if ( !empty( $this->defaults ) )
				$this->prefs = wp_parse_args( $this->prefs, $this->defaults );
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
		 * Deactivate
		 * @since 1.2
		 * @version 1.0
		 */
		function deactivate() {
			
		}

		/**
		 * Get Field Name
		 * Returns the field name for the current service
		 * @since 1.2
		 * @version 1.0
		 */
		function field_name( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( !is_numeric( $parent ) )
						$array[] = $parent;

					if ( !empty( $child ) && !is_array( $child ) )
						$array[] = $child;
				}
				$field = '[' . implode( '][', $array ) . ']';
			}
			else {
				$field = '[' . $field . ']';
			}
			return 'mycred_pref_bank[service_prefs][' . $this->id . ']' . $field;
		}

		/**
		 * Get Field ID
		 * Returns the field id for the current service
		 * @since 1.2
		 * @version 1.0
		 */
		function field_id( $field = '' ) {
			if ( is_array( $field ) ) {
				$array = array();
				foreach ( $field as $parent => $child ) {
					if ( !is_numeric( $parent ) )
						$array[] = str_replace( '_', '-', $parent );

					if ( !empty( $child ) && !is_array( $child ) )
						$array[] = str_replace( '_', '-', $child );
				}
				$field = implode( '-', $array );
			}
			else {
				$field = str_replace( '_', '-', $field );
			}
			return 'mycred-bank-service-prefs-' . str_replace( '_', '-', $this->id ) . '-' . $field;
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
			else
				return false;
		}

		/**
		 * Last Run
		 * @since 1.2
		 * @version 1.0
		 */
		public function get_last_run( $timestamp, $rate ) {
			$timeframes = $this->get_timeframes();
			if ( array_key_exists( $rate, $timeframes ) ) {
				// Quarterly
				if ( $rate == 'quarterly' ) {
					$month = date_i18n( 'n', $timestamp );
					return 'Q' . ceil( $month/3 );
				}
				elseif ( $rate == 'semiannually' ) {
					$month = date_i18n( 'm', $timestamp );
					return ( $month <= 5 ) ? 'first' : 'second';
				}
				else {
					return date_i18n( $timeframes[ $rate ]['date_format'], $timestamp );
				}
			}
			else
				return false;
		}

		/**
		 * Get User IDs
		 * Returns all registered members user id with optional excludes.
		 * @since 1.2
		 * @version 1.0
		 */
		public function get_user_ids( $exclude = '' ) {
			$args = array();
			$args['fields'] = 'ID';
			
			$excludes = $this->core->exclude['list'];
			if ( !empty( $exclude ) )
				$excludes .= $exclude;

			if ( !empty( $excludes ) )
				$args['exclude'] = explode( ',', $excludes );
			
			return get_users( $args );
		}

		/**
		 * Get Days in Year
		 * @since 1.2
		 * @version 1.0
		 */
		public function get_days_in_year() {
			if ( date_i18n( 'L' ) )
				return 366;
			else
				return 365;
		}

		/**
		 * Timeframe Dropdown
		 * @since 1.2
		 * @version 1.0
		 */
		function timeframe_dropdown( $pref_id = '', $use_select = true, $hourly = true ) {
			
			$timeframes = $this->get_timeframes();
			if ( !$hourly )
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
	}
}
?>