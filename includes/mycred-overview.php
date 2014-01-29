<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * Dashboard Widget: Overview
 * @see https://codex.wordpress.org/Example_Dashboard_Widget
 * @since 1.3.3
 * @version 1.1
 */
add_action( 'wp_dashboard_setup', array( 'myCRED_Dashboard_Widget_Overview', 'init' ) );
add_action( 'admin_head',         array( 'myCRED_Dashboard_Widget_Overview', 'style' ) );
if ( ! class_exists( 'myCRED_Dashboard_Widget_Overview' ) ) {
	class myCRED_Dashboard_Widget_Overview {

		const wid = 'mycred_overview';

		/**
		 * Init Widget
		 */
		public static function init() {
			// Update settings
			self::update_settings();

			// Add widget
			wp_add_dashboard_widget(
				self::wid,
				sprintf( __( '%s Right Now', 'mycred' ), mycred_label() ),
				array( 'myCRED_Dashboard_Widget_Overview', 'widget' ),
				array( 'myCRED_Dashboard_Widget_Overview', 'config' )
			);
		}

		/**
		 * Get Modules
		 */
		public static function get_modules() {
			$modules = array(
				'refs'   => array(
					'title'   => __( 'Reference Occurrences', 'mycred' ),
					'call'    => 'myCRED_Overview_Widget_Module_Refs',
					'prefs'   => true,
					'default' => array(
						'number'  => 5
					)
				),
				'totals' => array(
					'title' => __( '%singular% Totals', 'mycred' ),
					'call'  => 'myCRED_Overview_Widget_Module_Totals',
					'prefs' => false
				)
			);

			return apply_filters( 'mycred_overview_modules', $modules );
		}

		/**
		 * Widget Styling
		 */
		public static function style() {
			$screen = get_current_screen();
			if ( ! isset( $screen->id ) || $screen->id != 'dashboard' ) return;
			wp_enqueue_style( 'mycred-dashboard-overview' );
		}

		/**
		 * Widget output
		 */
		public static function widget() {
			$mycred = mycred_get_settings();
			$modules = self::get_modules();
			$prefs = self::get_dashboard_widget_options( self::wid );
			if ( ! isset( $prefs['active'] ) ) $prefs['active'] = array(); ?>

<div class="overview-module-wrap">
<?php
			// No modules exists
			if ( empty( $modules ) || empty( $prefs['active'] ) ) {
				echo '<p class="no-modules">' . __( 'no modules shown', 'mycred' ) . '</p>';
			}

			// Modules exists
			else {

				// Loop though modules
				$count = 0;
				foreach ( $modules as $module_id => $module ) {
					if ( ! in_array( $module_id, $prefs['active'] ) ) continue;

					if ( class_exists( $module['call'] ) ) {
						$count++;
						
						if ( $count % 2 != 0 )
							$class = ' push';
						else
							$class = '';

						echo '<div class="module table table_content' . $class . '"><p class="sub">' . $mycred->template_tags_general( $module['title'] ) . '</p>';
						
						if ( isset( $prefs[ $module_id ] ) && isset( $module['default'] ) )
							$_prefs = mycred_apply_defaults( $module['default'], $prefs[ $module_id ] );
						else
							$_prefs = array();

						
						$module = new $module['call']();
						$module->display( $_prefs );

						echo '</div>';
					}
				}

			} ?>

	<div class="clear"></div>
</div>
<?php
		}

		/**
		 * Widget Settings
		 */
		public static function config() {
			$mycred = mycred_get_settings();
			$modules = self::get_modules();
			$prefs = self::get_dashboard_widget_options( self::wid );
			if ( ! isset( $prefs['active'] ) ) $prefs['active'] = array(); ?>

<div class="overview-module-wrap">
<?php
			// If modules exists
			if ( ! empty( $modules ) ) {
			
				$count = 0;
				foreach ( $modules as $module_id => $module ) {
					$count++;

					if ( $count % 2 != 0 )
						$class = ' push';
					else
						$class = ''; ?>

	<div class="module table table_content<?php echo $class; ?>">
		<p class="sub"><?php echo $mycred->template_tags_general( $module['title'] ); ?></p>
		<table>
			<tbody>
				<tr class="first">
					<td class="first b"><input type="checkbox" name="<?php echo self::wid; ?>[active][]" id="mycred-overview-module-<?php echo $module_id; ?>"<?php if ( in_array( $module_id, $prefs['active'] ) ) echo ' checked="checked"'; ?> value="<?php echo $module_id; ?>" /></td>
					<td class="t posts"><label for="mycred-overview-module-<?php echo $module_id; ?>"><?php _e( 'Show', 'mycred' ); ?></label></td>
				</tr>
				<?php

				// Module Preferencs
				if ( isset( $module['prefs'] ) && $module['prefs'] && class_exists( $module['call'] ) ) {
					if ( isset( $prefs[ $module_id ] ) && isset( $module['default'] ) )
						$_prefs = mycred_apply_defaults( $module['default'], $prefs[ $module_id ] );
					else
						$_prefs = $module['default'];

					$module = new $module['call']();
					$module->config( self::wid, $_prefs );
				} ?>

			</tbody>
		</table>
	</div>
<?php			}
			} else { ?>

	<p><?php _e( 'No modules found', 'mycred' ); ?></p>
<?php		} ?>

	<div class="clear"></div>
</div>
<?php
		}

		/**
		 * Save Settings
		 */
		public static function update_settings() {
			if ( isset( $_POST[ self::wid ] ) ) {
				if ( ! isset( $_POST[ self::wid ]['active'] ) )
					$_POST[ self::wid ]['active'] = array();

				$defaults = array( 'active' => array() );
				$modules = self::get_modules();

				if ( ! empty( $modules ) ) {
					foreach ( $modules as $module_id => $module ) {
						if ( ! isset( $module['default'] ) ) continue;
						$defaults[ $module_id ] = $module['default'];
					}
				}

				self::update_dashboard_widget_options(
					self::wid,
					$_POST[ self::wid ],
					$defaults
				);
			}
		}

		/**
		 * Get Widget Options
		 */
		public static function get_dashboard_widget_options( $widget_id = '' ) {
			// Fetch ALL dashboard widget options from the db...
			$opts = get_option( 'dashboard_widget_options' );

			// If no widget is specified, return everything
			if ( empty( $widget_id ) )
				return $opts;

			// If we request a widget and it exists, return it
			if ( isset( $opts[ $widget_id ] ) )
				return $opts[ $widget_id ];

			// Something went wrong...
			return false;
		}

		/**
		 * Get Widget Option
		 */
		public static function get_dashboard_widget_option( $widget_id, $option, $default = NULL ) {
			$opts = self::get_dashboard_widget_options( $widget_id );

			// If widget opts dont exist, return false
			if ( ! $opts )
				return false;

			// Otherwise fetch the option or use default
			if ( isset( $opts[ $option ] ) && ! empty( $opts[ $option ] ) )
				return $opts[ $option ];
			else
				return ( isset( $default ) ) ? $default : false;
		}

		/**
		 * Update Widget Option
		 */
		public static function update_dashboard_widget_options( $widget_id , $args = array(), $default = array() ) {
			// Fetch ALL dashboard widget options from the db...
			$opts = get_option( 'dashboard_widget_options' );

			$opts[ $widget_id ] = mycred_apply_defaults( $default, $args );

			// Save the entire widgets array back to the db
			return update_option( 'dashboard_widget_options', $opts );
		}
	}
}

/**
 * Overview Module: Reference Occurences
 * @since 1.3.3
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Overview_Widget_Module_Refs' ) ) {
	class myCRED_Overview_Widget_Module_Refs {

		public function __construct() { }

		/**
		 * Display Module
		 */
		public function display( $prefs ) {
			if ( ! isset( $prefs['number'] ) ) $prefs['number'] = 5;
			$reference_count = mycred_count_all_ref_instances( $prefs['number'] );
			$ref_count = count( $reference_count ); ?>

<table>
	<tbody>
<?php
			// References exists
			if ( $ref_count > 0 ) {
				$count = 0;
				foreach ( $reference_count as $reference => $occurrence ) {
					$name = str_replace( array( '_', '-' ), ' ', $reference );
					$name = ucwords( $name );

					$url = add_query_arg( array( 'page' => 'myCRED', 'ref' => $reference ), admin_url( 'admin.php' ) ); ?>

		<tr<?php if ( $count == 0 ) echo ' class="first"'; ?>>
			<td class="first b"><a href="<?php echo $url; ?>"><?php echo $occurrence; ?></a></td>
			<td class="t posts"><?php echo $name; ?></td>
		</tr>
<?php				$count++;
				}
			}

			// No references = empty log
			else { ?>

	<tr class="first">
		<td class="" colspan="2"><?php _e( 'Your log is empty', 'mycred' ); ?></td>
	</tr>
<?php		} ?>

	</tbody>
</table>
<?php
		}

		/**
		 * Module Config
		 */
		public function config( $widget_id, $prefs ) { ?>

<tr class="first">
	<td class="first b"><input type="text" name="<?php echo $widget_id; ?>[refs][number]" id="mycred-overview-module-refs-number" value="<?php echo $prefs['number']; ?>" size="2" /></td>
	<td class="t posts"><label for="mycred-overview-module-refs-number"><?php _e( 'Number', 'mycred' ); ?></label></td>
</tr>
<?php
		}
	}
}

/**
 * Overview Module: Totals
 * @since 1.3.3
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Overview_Widget_Module_Totals' ) ) {
	class myCRED_Overview_Widget_Module_Totals {

		public function __construct() { }

		/**
		 * Display Module
		 */
		public function display() {
			$name = apply_filters( 'mycred_label', myCRED_NAME );
			$url = add_query_arg( array( 'page' => 'myCRED' ), admin_url( 'admin.php' ) );

			global $wpdb;
			$mycred = mycred_get_settings();

			$gains = $wpdb->get_var( "SELECT SUM(creds) FROM {$mycred->log_table} WHERE creds > 0;" );
			$loses = $wpdb->get_var( "SELECT SUM(creds) FROM {$mycred->log_table} WHERE creds < 0;" ); ?>

<table>
	<tbody>
		<tr class="first">
			<td class="first b"><a href="<?php echo add_query_arg( array( 'num' => 0, 'compare' => '>' ), $url ); ?>"><?php echo $mycred->format_number( $gains ); ?></a></td>
			<td class="t approved"><?php _e( 'Earned by users', 'mycred' ); ?></td>
		</tr>
		<tr>
			<td class="first b"><a href="<?php echo add_query_arg( array( 'num' => 0, 'compare' => '<' ), $url ); ?>"><?php echo $mycred->format_number( abs( $loses ) ); ?></a></td>
			<td class="t spam"><?php _e( 'Taken from users', 'mycred' ); ?></td>
		</tr>
<?php
			// buyCRED Add-on
			if ( defined( 'myCRED_PURCHASE' ) ) :
				$purchase = $wpdb->get_var( "SELECT SUM(creds) FROM {$mycred->log_table} WHERE ref IN ('buy_creds_with_paypal_standard','buy_creds_with_skrill','buy_creds_with_zombaio','buy_creds_with_netbilling');" ); ?>

		<tr>
			<td class="first b"><a href="<?php echo add_query_arg( array( 'ref' => 'buy_creds_with_paypal_standard,buy_creds_with_skrill,buy_creds_with_zombaio,buy_creds_with_netbilling' ), $url ); ?>"><?php echo $mycred->format_number( abs( $purchase ) ); ?></a></td>
			<td class="t"><?php _e( 'Purchased by users', 'mycred' ); ?></td>
		</tr>
<?php
			endif;

			// Transfer Add-on
			if ( defined( 'myCRED_TRANSFER' ) ) :
			$transfers = $wpdb->get_var( "SELECT SUM(creds) FROM {$mycred->log_table} WHERE creds > 0 AND ref = 'transfer';" ); ?>

		<tr>
			<td class="first b"><a href="<?php echo add_query_arg( array( 'ref' => 'transfer' ), $url ); ?>"><?php echo $mycred->format_number( abs( $transfers ) ); ?></a></td>
			<td class="t"><?php _e( 'Transferred between users', 'mycred' ); ?></td>
		</tr>
<?php
			endif;

			// Gateway Add-on
			if ( defined( 'myCRED_GATE' ) ) :
				$store = $wpdb->get_var( "SELECT SUM(creds) FROM {$mycred->log_table} WHERE ref IN ('woocommerce_payment','marketpress_payment','wpecom_payment');" ); ?>

		<tr>
			<td class="first b"><a href="<?php echo add_query_arg( array( 'ref' => 'woocommerce_payment,marketpress_payment,wpecom_payment,ticket_purchase,event_payment' ), $url ); ?>"><?php echo $mycred->format_number( abs( $store ) ); ?></a></td>
			<td class="t"><?php _e( 'Used as payment', 'mycred' ); ?></td>
		</tr>
<?php		endif;

			// Let others play
			do_action( 'mycred_ow_totals_display', $mycred ); ?>

	</tbody>
</table>
<p><span class="description"><?php _e( 'Note that manual balance adjustments without a log entry are not counted.', 'mycred' ); ?></span></p>
<?php
		}
		
		/**
		 * Module Config
		 */
		public function config( $widget_id, $prefs ) { }
	}
}
?>