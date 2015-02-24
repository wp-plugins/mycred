<?php
/**
 * Addon: Stats
 * Addon URI: http://mycred.me/add-ons/stats/
 * Version: 1.0
 * Description: Gives you access to your myCRED Staticstics based on your users gains and loses.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_STATS',             __FILE__ );
define( 'myCRED_STATS_VERSION',     myCRED_VERSION . '.1' );
define( 'myCRED_STATS_DIR',         myCRED_ADDONS_DIR . 'stats/' );
define( 'myCRED_STATS_WIDGETS_DIR', myCRED_STATS_DIR . 'widgets/' );

/**
 * Required Files
 */
require_once myCRED_STATS_DIR . 'includes/mycred-stats-functions.php';
require_once myCRED_STATS_DIR . 'abstracts/mycred-abstract-stat-widget.php';

/**
 * Core Widgets
 */
require_once myCRED_STATS_WIDGETS_DIR . 'mycred-stats-widget-circulation.php';
require_once myCRED_STATS_WIDGETS_DIR . 'mycred-stats-widget-daily-gains.php';
require_once myCRED_STATS_WIDGETS_DIR . 'mycred-stats-widget-daily-loses.php';

do_action( 'mycred_stats_load_widgets' );

/**
 * myCRED_Stats_Module class
 * @since 1.6
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Stats_Module' ) ) {
	class myCRED_Stats_Module extends myCRED_Module {

		public $user;
		public $screen;
		public $ctypes;
		public $colors;

		/**
		 * Construct
		 */
		function __construct( $type = 'mycred_default' ) {
			parent::__construct( 'myCRED_Stats_Module', array(
				'module_name' => 'stats',
				'register'    => false,
			), $type );

			$this->label = sprintf( '%s %s', mycred_label(), __( 'Statistics', 'mycred' ) );
			
			$this->colors = mycred_get_type_color();
		}

		/**
		 * Init
		 * @since 1.6
		 * @version 1.0
		 */
		public function module_init() {
			add_action( 'admin_menu',            array( $this, 'add_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
		}

		/**
		 * Admin Init
		 * @since 1.6
		 * @version 1.0
		 */
		public function module_admin_init() {
			
		}

		/**
		 * Add Menu
		 * @since 1.6
		 * @version 1.0
		 */
		public function add_menu() {

			$page = add_dashboard_page(
				$this->label,
				$this->label,
				$this->core->edit_creds_cap(),
				'mycred-stats',
				array( $this, 'admin_page' )
			);

			add_action( 'admin_print_styles-' . $page, array( $this, 'admin_page_header' ) );

		}

		/**
		 * Register Scripts
		 * @since 1.6
		 * @version 1.0
		 */
		public function register_scripts() {

			// Scripts
			wp_register_script(
				'chart-js',
				plugins_url( 'assets/js/Chart.js', myCRED_STATS ),
				array( 'jquery' ),
				'1.1'
			);

			// Stylesheets
			wp_register_style(
				'mycred-stats',
				plugins_url( 'assets/css/stats-page.css', myCRED_STATS ),
				array(),
				myCRED_VERSION
			);

		}

		public function get_tabs() {

			$tabs = array();
			$tabs['overview'] = array(
				'label' => __( 'Overview', 'mycred' ),
				'class' => array( $this, 'overview_screen' )
			);

			foreach ( $this->point_types as $type_id => $label ) {
				$mycred = mycred( $type_id );
				$tabs[ $type_id ] = array(
					'label' => $mycred->plural(),
					'class' => array( $this, 'ctype_screen' )
				);
			}

			return apply_filters( 'mycred_statistics_tabs', $tabs );

		}

		/**
		 * Admin Page Header
		 * @since 1.6
		 * @version 1.0
		 */
		public function admin_page_header() {

			wp_enqueue_script( 'chart-js' );
			wp_enqueue_style( 'mycred-stats' );

			do_action( 'mycred_stats_page_header', $this );

		}

		/**
		 * Has Entries
		 * @since 1.6
		 * @version 1.0
		 */
		public function has_entries() {

			global $wpdb;

			$reply = true;
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->core->log_table};" );
			if ( $count === NULL || $count < 1 )
				$reply = false;

			return apply_filters( 'mycred_stats_has_entries', $reply, $this );

		}

		/**
		 * Admin Page
		 * @since 1.6
		 * @version 1.0
		 */
		public function admin_page() {
			// Security
			if ( ! $this->core->can_edit_creds() )
				wp_die( __( 'Access Denied', 'mycred' ) );

			$current = 'overview';
			if ( isset( $_GET['view'] ) )
				$current = $_GET['view'];

			$tabs = $this->get_tabs();

?>
<div id="mycred-stats" class="wrap">
	<h2><?php echo $this->label; ?><a href="javascript:void(0);" onClick="window.location.href=window.location.href" class="add-new-h2" id="refresh-mycred-stats"><?php _e( 'Refresh', 'mycred' ); ?></a></h2>
<?php

			do_action( 'mycred_stats_page_before', $this );

			// No use loading the widgets if no log entries exists
			if ( $this->has_entries() ) {

?>
	<ul id="section-nav" class="nav-tab-wrapper">
<?php

				foreach ( $tabs as $tab_id => $tab ) {

					$classes = 'nav-tab';
					if ( $current == $tab_id ) $classes .= ' nav-tab-active';

					if ( $tab_id != 'general' )
						$url = add_query_arg( array( 'page' => $_GET['page'], 'view' => $tab_id ), admin_url( 'admin.php' ) );
					else
						$url = add_query_arg( array( 'page' => $_GET['page'] ), admin_url( 'admin.php' ) );

					echo '<li class="' . $classes . '"><a href="' . $url . '">' . $tab['label'] . '</a></li>';

				}

?>
	</ul>

	<div id="mycred-stats-body" class="clear clearfix">
		
<?php

				// Render tab
				if ( isset( $tabs[ $current ]['class'] ) && $tabs[ $current ]['class'] != '' ) {

					$method = $tabs[ $current ]['class'];
					if ( is_array( $method ) ) {
						if ( $method[0] == $this )
							$this->$method[1]( $current );

						elseif ( class_exists( $method[0] ) ) {
							$class = new $method[0]();
							$class->$method[1]( $current );
						}
					}
					elseif ( ! is_array( $method ) && function_exists( $method ) )
						$method( $current );

				}

			}
			else {

?>
<div id="mycred-log-is-empty">
	<p><?php _e( 'Your log is empty. No statistics can be shown.', 'mycred' ); ?></p>
</div>
<?php

			}

?>
		</div>
	</div>

</div>
<?php

			do_action( 'mycred_stats_page_after', $this );

		}

		/**
		 * Overview Screen
		 * @since 1.6
		 * @version 1.0
		 */
		public function overview_screen( $current = '' ) {

			$widgets = apply_filters( 'mycred_stats_overview_widgets', array(
				0 => array( 'id' => 'overallcirculation', 'class' => 'myCRED_Stats_Widget_Circulation', 'args' => array( 'ctypes' => 'all' ) ),
				1 => array( 'id' => 'overallgains', 'class' => 'myCRED_Stats_Widget_Daily_Gains', 'args' => array( 'ctypes' => 'all', 'span' => 10, 'number' => 5 ) ),
				2 => array( 'id' => 'overallloses', 'class' => 'myCRED_Stats_Widget_Daily_Loses', 'args' => array( 'ctypes' => 'all', 'span' => 10, 'number' => 5 ) )
			), $this );

			if ( ! empty( $widgets ) ) {
				foreach ( $widgets as $num => $swidget ) {

					$widget = $swidget['class'];
					if ( class_exists( $widget ) ) {
						$w = new $widget( $swidget['id'], $swidget['args'] );
						
						echo '<div class="mycred-stat-widget">';

						$w->widget();

						echo '</div>';

					}

				}
			}

		}

		/**
		 * Point Type Screen
		 * @since 1.6
		 * @version 1.0
		 */
		public function ctype_screen( $current = '' ) {

			$widgets = apply_filters( 'mycred_stats_' . $current . '_widgets', array(
				0 => array( 'id' => $current . 'circulation', 'class' => 'myCRED_Stats_Widget_Circulation', 'args' => array( 'ctypes' => $current ) ),
				1 => array( 'id' => $current . 'gains', 'class' => 'myCRED_Stats_Widget_Daily_Gains', 'args' => array( 'ctypes' => $current, 'span' => 10, 'number' => 5 ) ),
				2 => array( 'id' => $current . 'loses', 'class' => 'myCRED_Stats_Widget_Daily_Loses', 'args' => array( 'ctypes' => $current, 'span' => 10, 'number' => 5 ) )
			), $this );

			if ( ! empty( $widgets ) ) {
				foreach ( $widgets as $num => $swidget ) {

					$widget = $swidget['class'];
					if ( class_exists( $widget ) ) {
						$w = new $widget( $swidget['id'], $swidget['args'] );
						
						echo '<div class="mycred-stat-widget">';

						$w->widget();

						echo '</div>';

					}

				}
			}

		}

	}

	$mycred_stats = new myCRED_Stats_Module();
	$mycred_stats->load();
}
?>