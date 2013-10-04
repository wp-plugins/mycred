<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Hook class
 * @see http://mycred.me/classes/mycred_hook/
 * @since 0.1
 * @version 1.1
 */
if ( !class_exists( 'myCRED_Hook' ) ) {
	abstract class myCRED_Hook {

		// Hook ID
		public $id;

		// myCRED_Settings Class
		public $core;

		// Hook Prefs
		public $prefs = false;

		/**
		 * Construct
		 */
		function __construct( $args = array(), $hook_prefs = NULL ) {
			if ( !empty( $args ) ) {
				foreach ( $args as $key => $value ) {
					$this->$key = $value;
				}
			}
			
			// Grab myCRED Settings
			$this->core = mycred_get_settings();

			// Grab settings
			if ( $hook_prefs !== NULL ) {
				// Assign prefs if set
				if ( isset( $hook_prefs[$this->id] ) )
					$this->prefs = $hook_prefs[$this->id];

				// Defaults must be set
				if ( !isset( $this->defaults ) || empty( $this->defaults ) )
					$this->defaults = array();
			}

			// Apply default settings if needed
			if ( !empty( $this->defaults ) )
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
			return 'mycred_pref_hooks[hook_prefs][' . $this->id . ']' . $field;
		}

		/**
		 * Get Field ID
		 * Returns the field id for the current hook
		 * @since 0.1
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
			return 'mycred-hook-prefs-' . str_replace( '_', '-', $this->id ) . '-' . $field;
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
		 * @version 1.2
		 */
		function has_entry( $action = '', $ref_id = '', $user_id = '', $data = '' ) {
			return $this->core->has_entry( $action, $ref_id, $user_id, $data );
		}
	}
}
?>