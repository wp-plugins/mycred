<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Module class
 * @see http://mycred.merovingi.com/classes/mycred_module/
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Module' ) ) {
	abstract class myCRED_Module {

		// Module ID (unique)
		public $module_id;

		// Core settings & functions
		public $core;

		// Module name (string)
		public $module_name;

		// Option ID (string|array)
		public $option_id;

		// Labels (array)
		public $labels;

		// Register (bool)
		public $register;

		// Screen ID (string)
		public $screen_id;

		// Menu Position (int)
		public $menu_pos;

		/**
		 * Construct
		 */
		function __construct( $module_id = '', $args = array() ) {
			// Module ID is required
			if ( empty( $module_id ) )
				wp_die( __( 'myCRED_Module() Error. A Module ID is required!', 'mycred' ) );

			$this->module_id = $module_id;
			$this->core = mycred_get_settings();

			// Default arguments
			$defaults = array(
				'module_name' => '',
				'option_id'   => '',
				'defaults'    => array(),
				'labels'      => array(
					'menu'        => '',
					'page_title'  => ''
				),
				'register'    => true,
				'screen_id'   => '',
				'add_to_core' => false,
				'accordion'   => false,
				'cap'         => 'plugin',
				'menu_pos'    => 10
			);
			$args = wp_parse_args( $args, $defaults );

			$this->module_name = $args['module_name'];
			$this->option_id = $args['option_id'];
			$this->labels = $args['labels'];
			$this->register = $args['register'];
			$this->screen_id = $args['screen_id'];

			$this->add_to_core = $args['add_to_core'];
			$this->accordion = $args['accordion'];
			$this->cap = $args['cap'];
			$this->menu_pos = $args['menu_pos'];

			$this->set_settings( $args['defaults'] );
			unset( $args );
		}

		/**
		 * Set Settings
		 * @since 0.1
		 * @version 1.0
		 */
		function set_settings( $defaults ) {
			$module = $this->module_name;

			// Reqest not to register any settings
			if ( $this->register === false ) {
				// If settings does not exist apply defaults
				if ( !isset( $this->core->$module ) )
					$this->$module = $defaults;
				// Else append settings
				else
					$this->$module = $this->core->$module;
			}
			// Request to register settings
			else {
				// Option IDs must be provided
				if ( !empty( $this->option_id ) ) {
					// Array = more then one
					if ( is_array( $this->option_id ) ) {
						// General settings needs not to be loaded
						if ( array_key_exists( 'mycred_pref_core', $this->option_id ) ) {
							$this->$module = $this->core;
						}
						// Loop and grab
						foreach ( $this->option_id as $option_id => $option_name ) {
							if ( mycred_overwrite() === false )
								$settings = get_option( $option_id );
							else
								$settings = get_blog_option( 1, $option_id );

							if ( $settings === false && array_key_exists( $option_id, $defaults ) )
								$this->$module[$option_name] = $defaults[$option_id];
							else
								$this->$module[$option_name] = $settings;
						}
					}
					// String = one
					else {
						// General settings needs not to be loaded
						if ( $this->option_id == 'mycred_pref_core' ) {
							$this->$module = $this->core;
						}
						// Grab the requested option
						else {
							if ( mycred_overwrite() === false )
								$this->$module = get_option( $this->option_id );
							else
								$this->$module = get_blog_option( 1, $this->option_id );

							if ( $this->$module === false && !empty( $defaults ) )
								$this->$module = $defaults;
						}
					}

					if ( is_array( $this->$module ) ) {
						foreach ( $this->$module as $key => $value ) {
							$this->$key = $value;
						}
					}
				}
			}
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.0
		 */
		function load() {
			if ( !empty( $this->screen_id ) && !empty( $this->labels ) ) {
				add_action( 'mycred_add_menu',         array( $this, 'add_menu' ), $this->menu_pos      );
			}

			if ( $this->register === true && !empty( $this->option_id ) )
				add_action( 'mycred_admin_init',       array( $this, 'register_settings' )              );

			if ( $this->add_to_core === true ) {
				add_action( 'mycred_after_core_prefs', array( $this, 'after_general_settings' )         );
				add_filter( 'mycred_save_core_prefs',  array( $this, 'sanitize_extra_settings' ), 90, 3 );
			}

			add_action( 'mycred_pre_init',             array( $this, 'module_pre_init' )                );
			add_action( 'mycred_init',                 array( $this, 'module_init' )                    );
			add_action( 'mycred_admin_init',           array( $this, 'module_admin_init' )              );
			add_action( 'mycred_widgets_init',         array( $this, 'module_widgets_init' )            );
		}

		/**
		 * Pre Init
		 * @since 0.1
		 * @version 1.0
		 */
		function module_pre_init() { }

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0
		 */
		function module_init() { }

		/**
		 * Admin Init
		 * @since 0.1
		 * @version 1.0
		 */
		function module_admin_init() { }

		/**
		 * Widgets Init
		 * @since 0.1
		 * @version 1.0
		 */
		function module_widgets_init() { }

		/**
		 * Get
		 * @since 0.1
		 * @version 1.0
		 */
		function get() { }

		/**
		 * Call
		 * Either runs a given class method or function.
		 * @since 0.1
		 * @version 1.0
		 */
		function call( $call, $callback, $return = NULL ) {
			// Class
			if ( is_array( $callback ) && class_exists( $callback[0] ) ) {
				$class = $callback[0];
				$methods = get_class_methods( $class );
				if ( in_array( $call, $methods ) ) {
					$new = new $class( $this );
					return $new->$call( $return );
				}
			}
			// Function
			if ( !is_array( $callback ) ) {
				if ( function_exists( $callback ) ) {
					if ( $return !== NULL )
						return call_user_func( $callback, $return, $this );
					else
						return call_user_func( $callback, $this );
				}
			}
		}

		/**
		 * If Installed
		 * Checks if hooks have been installed
		 *
		 * @returns (bool) true or false
		 * @since 0.1
		 * @version 1.0
		 */
		function is_installed() {
			$module_name = $this->module_name;
			if ( $this->$module_name === false ) return false;
			return true;
		}

		/**
		 * Is Active
		 * @param $key (string) required key to check for
		 * @returns (bool) true or false
		 * @since 0.1
		 * @version 1.0
		 */
		function is_active( $key = '' ) {
			$module = $this->module_name;
			if ( !isset( $this->active ) && !empty( $key ) ) {
				if ( isset( $this->$module['active'] ) )
					$active = $this->$module['active'];
				else
					return false;

				if ( in_array( $key, $active ) ) return true;
			}
			elseif ( isset( $this->active ) && !empty( $key ) ) {
				if ( in_array( $key, $this->active ) ) return true;
			}

			return false;
		}

		/**
		 * Add Admin Menu Item
		 * @since 0.1
		 * @version 1.0
		 */
		function add_menu() {
			// Network Setting for Multisites
			if ( is_multisite() && mycred_overwrite() === true && $this->screen_id != 'myCRED' && $GLOBALS['blog_id'] != 1 ) return;

			if ( !empty( $this->labels ) && !empty( $this->screen_id ) ) {
				// Menu Label
				if ( !isset( $this->labels['page_title'] ) && !isset( $this->labels['menu'] ) )
					$label_menu = __( 'Surprise', 'mycred' );
				elseif ( isset( $this->labels['menu'] ) )
					$label_menu = $this->labels['menu'];
				else
					$label_menu = $this->labels['page_title'];

				// Page Title
				if ( !isset( $this->labels['page_title'] ) && !isset( $this->labels['menu'] ) )
					$label_title = __( 'Surprise', 'mycred' );
				elseif ( isset( $this->labels['page_title'] ) )
					$label_title = $this->labels['page_title'];
				else
					$label_title = $this->labels['menu'];

				if ( $this->cap != 'plugin' )
					$cap = $this->core->edit_creds_cap();
				else
					$cap = $this->core->edit_plugin_cap();

				// Add Submenu Page
				$page = add_submenu_page(
					'myCRED',
					$label_menu,
					$label_title,
					$cap,
					$this->screen_id,
					array( $this, 'admin_page' )
				);
				add_action( 'admin_print_styles-' . $page, array( $this, 'settings_header' ) );
			}
		}

		/**
		 * Register Settings
		 * @since 0.1
		 * @version 1.0
		 */
		function register_settings() {
			if ( empty( $this->option_id ) || $this->register === false ) return;
			register_setting( 'myCRED-' . $this->module_name, $this->option_id, array( $this, 'sanitize_settings' ) );
		}

		/**
		 * Settings Header
		 * Outputs the "click to open" and "click to close" text to the accordion.
		 *
		 * @since 0.1
		 * @version 1.0
		 */
		function settings_header() {
			if ( $this->accordion === true )
				wp_enqueue_script( 'mycred-admin' );

			wp_enqueue_style( 'mycred-admin' );

			if ( $this->accordion === false ) return;
			$click_to_open = __( 'click to open', 'mycred' );
			$click_to_close = __( 'click to close', 'mycred' ); ?>

<style type="text/css">
h4:before { float:right; padding-right: 12px; font-size: 14px; font-weight: normal; color: silver; }
h4.ui-accordion-header.ui-state-active:before { content: "<?php echo $click_to_close; ?>";  }
h4.ui-accordion-header:before { content: "<?php echo $click_to_open; ?>"; }
</style>
<?php
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.0
		 */
		function admin_page() { }

		/**
		 * Sanitize Settings
		 * @since 0.1
		 * @version 1.0
		 */
		function sanitize_settings( $post ) {
			return $post;
		}

		/**
		 * After General Settings
		 * @since 0.1
		 * @version 1.0
		 */
		function after_general_settings() { }

		/**
		 * Sanitize Core Settings
		 * @since 0.1
		 * @version 1.0
		 */
		function sanitize_extra_settings( $new_data, $data, $core ) {
			return $new_data;
		}

		/**
		 * Input Field Name Value
		 * @since 0.1
		 * @version 1.0
		 */
		function field_name( $name = '' ) {
			if ( is_array( $name ) ) {
				$array = array();
				foreach ( $name as $parent => $child ) {
					if ( !is_numeric( $parent ) )
						$array[] = $parent;

					if ( !empty( $child ) && !is_array( $child ) )
						$array[] = $child;
				}
				$name = '[' . implode( '][', $array ) . ']';
			}
			else {
				$name = '[' . $name . ']';
			}

			if ( $this->add_to_core === true )
				$name = '[' . $this->module_name . ']' . $name;

			if ( !empty( $this->option_id ) )
				return $this->option_id . $name;
			else
				return 'mycred_pref_core' . $name;
		}

		/**
		 * Input Field Id Value
		 * @since 0.1
		 * @version 1.0
		 */
		function field_id( $id = '' ) {
			if ( is_array( $id ) ) {
				$array = array();
				foreach ( $id as $parent => $child ) {
					if ( !is_numeric( $parent ) )
						$array[] = str_replace( '_', '-', $parent );

					if ( !empty( $child ) && !is_array( $child ) )
						$array[] = str_replace( '_', '-', $child );
				}
				$id = implode( '-', $array );
			}
			else {
				$id = str_replace( '_', '-', $id );
			}

			if ( $this->add_to_core === true )
				$id = $this->module_name . '-' . $id;

			return str_replace( '_', '-', $this->module_id ) . '-' . $id;
		}
	}
}
?>