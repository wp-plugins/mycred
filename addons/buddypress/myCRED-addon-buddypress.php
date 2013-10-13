<?php
/**
 * Addon: BuddyPress
 * Addon URI: http://mycred.me/add-ons/buddypress/
 * Version: 1.1
 * Description: The BuddyPress add-on extends <strong>my</strong>CRED to work with BuddyPress allowing you to hook into most BuddyPress related actions.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Requires: bp_displayed_user_id
 */
// Translate Header (by Dan bp-fr)
$mycred_addon_header_translate = array(
	__( 'BuddyPress', 'mycred' ),
	__( 'The BuddyPress add-on extends <strong>my</strong>CRED to work with BuddyPress allowing you to hook into most BuddyPress related actions.', 'mycred' )
);

if ( !defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_BP',           __FILE__ );
define( 'myCRED_BP_DIR',       myCRED_ADDONS_DIR . 'buddypress/' );
define( 'myCRED_BP_HOOKS_DIR', myCRED_BP_DIR . 'hooks/' );
/**
 * BuddyPress specific hooks grouped together
 */
require_once( myCRED_BP_HOOKS_DIR . 'bp-groups.php' );
require_once( myCRED_BP_HOOKS_DIR . 'bp-profile.php' );
require_once( myCRED_BP_HOOKS_DIR . 'bp-links.php' );
require_once( myCRED_BP_HOOKS_DIR . 'bp-galleries.php' );
/**
 * myCRED_BuddyPress class
 *
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_BuddyPress' ) ) {
	class myCRED_BuddyPress extends myCRED_Module {
		
		protected $hooks;
		protected $settings;

		/**
		 * Constructor
		 */
		public function __construct() {
			parent::__construct( 'myCRED_BuddyPress', array(
				'module_name' => 'buddypress',
				'defaults'    => array(
					'visibility'         => array(
						'balance' => 0,
						'history' => 0
					),
					'balance_location'   => '',
					'balance_template'   => '%plural% balance: %creds%',
					'history_location'   => '',
					'history_menu_title' => array(
						'me'      => __( "My History", 'mycred' ),
						'others'  => __( "%s's History", 'mycred' )
					),
					'history_menu_pos'   => 99,
					'history_url'        => 'mycred-history',
					'history_num'        => 10
				),
				'register'    => false,
				'add_to_core' => true
			) );
			if ( !is_admin() )
				add_action( 'bp_setup_nav', array( $this, 'setup_nav' ) );
		}
		
		/**
		 * Init
		 * @since 0.1
		 * @version 1.1
		 */
		public function module_init() {
			add_filter( 'mycred_setup_hooks', array( $this, 'register_hooks' ) );
			add_action( 'admin_bar_menu',     array( $this, 'adjust_admin_bar' ), 110 );
			add_filter( 'logout_url',         array( $this, 'adjust_logout' ), 99, 2 );
			
			if ( $this->buddypress['balance_location'] == 'top' || $this->buddypress['balance_location'] == 'both' )
				add_action( 'bp_before_member_header_meta',  array( $this, 'show_balance' ) );
 
 			if ( $this->buddypress['balance_location'] == 'profile_tab' || $this->buddypress['balance_location'] == 'both' )
				add_action( 'bp_after_profile_loop_content', array( $this, 'show_balance_profile' ) );
		}
		
		/**
		 * Adjust Admin Bar
		 * @since 0.1
		 * @version 1.1.1
		 */
		public function adjust_admin_bar() {
			// Bail if this is an ajax request
			if ( !bp_use_wp_admin_bar() || defined( 'DOING_AJAX' ) || $this->core->exclude_user( get_current_user_id() ) )
				return;

			// Only add menu for logged in user
			if ( is_user_logged_in() && $this->buddypress['visibility']['history'] ) {
				global $bp, $wp_admin_bar;
				
				// Add secondary parent item for all BuddyPress components
				$wp_admin_bar->add_menu( array(
					'parent' => 'my-account-xprofile',
					'id'     => 'user-admin-mycred',
					'title'  => $this->buddypress['history_menu_title']['me'],
					'href'   => bp_loggedin_user_domain() . $this->buddypress['history_url'] . '/'
				) );
			}
		}

		/**
		 * Adjust Logout Link
		 * If we are logging out from the points history page, we want to make
		 * sure we are redirected away from this page when we log out. All else
		 * the default logout link is used.
		 * @since 1.3.1
		 * @version 1.0
		 */
		public function adjust_logout( $logouturl, $redirect ) {
			if ( preg_match( '/(' . $this->buddypress['history_url'] . ')/', $redirect, $match ) ) {
				global $bp;
				
				$url = remove_query_arg( 'redirect_to', $logouturl );
				return add_query_arg( array( 'redirect_to' => urlencode( $bp->displayed_user->domain ) ), $url );
			}
			return $logouturl;
		}

		/**
		 * Show Balance in Profile
		 * @since 0.1
		 * @version 1.1
		 */
		public function show_balance_profile() {
			$user_id = bp_displayed_user_id();
			if ( $this->core->exclude_user( $user_id ) ) return;
			
			$balance = $this->core->get_users_cred( $user_id ); ?>

<div class="bp-widget mycred-field">
	<table class="profile-fields">
		<tr id="mycred-users-balance">
			<td class="label"><?php

			// Balance label
			$template = $this->buddypress['balance_template'];
			$template = str_replace( '%number%', '', $template );
			$template = str_replace( '%creds%', '', $template );
			$template = str_replace( '%rank%', '', $template );
			echo $this->core->template_tags_general( trim( $template ) ); ?></td>
			<td class="data">
			<?php echo $this->core->format_creds( $balance ); ?>

			</td>
		</tr>
	</table>
</div>
<?php
		}
		
		/**
		 * Show Balance in Header
		 * @since 0.1
		 * @version 1.2
		 */
		public function show_balance( $table_row = false ) {
			if ( bp_is_my_profile() || ( !bp_is_my_profile() && $this->buddypress['visibility']['balance'] ) || mycred_is_admin() ) {
				$user_id = bp_displayed_user_id();
				if ( $this->core->exclude_user( $user_id ) ) return;
				
				$balance = $this->core->get_users_cred( $user_id );
				
				$template = $this->buddypress['balance_template'];
				$template = str_replace( '%number%', $balance, $template );
				$template = str_replace( '%creds%', $this->core->format_creds( $balance ), $template );
				if ( function_exists( 'mycred_get_users_rank' ) ) {
					$rank_name = mycred_get_users_rank( $user_id );
					$template = str_replace( '%rank%', $rank_name, $template );
					$template = str_replace( '%rank_logo%', mycred_get_rank_logo( $rank_name ), $template );
					$template = str_replace( '%ranking%', mycred_rankings_position( $user_id ), $template );
				}
				else {
					$template = str_replace( array( '%ranking%', '%rank%' ), mycred_rankings_position( $user_id ), $template );
				}
			
				echo '<div id="mycred-my-balance">' . $this->core->template_tags_general( $template ) . '</div>';
			}
		}
		
		/**
		 * Setup Navigation
		 * @since 0.1
		 * @version 1.2
		 */
		public function setup_nav() {
			global $bp;
			
			$user_id = bp_displayed_user_id();
			
			// User is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;
			
			if ( is_user_logged_in() ) {
				$current = get_current_user_id();
				// Admins alway see points history
				if ( !$this->core->can_edit_plugin() ) {
					// If history is not shown in profile
					if ( $this->buddypress['history_location'] != 'top' ) return;
				
					// Allow users to see each others history?
					if ( !$this->buddypress['visibility']['history'] && $user_id != $current ) return;
				}
			}
			else {
				if ( !$this->buddypress['visibility']['history'] ) return;
			}
			
			// Settings for bp menu
			if ( $this->buddypress['visibility']['history'] || $this->core->can_edit_plugin() )
				$show_for_displayed_user = true;
			else
				$show_for_displayed_user = false;
			
			// Top Level Nav Item
			$top_name = bp_word_or_name( $this->buddypress['history_menu_title']['me'], $this->buddypress['history_menu_title']['others'], false, false );
			bp_core_new_nav_item( array(
				'name'                    => $this->core->template_tags_general( $top_name ),
				'slug'                    => $this->buddypress['history_url'],
				'parent_url'              => $bp->displayed_user->domain,
				'default_subnav_slug'     => $this->buddypress['history_url'],
				'screen_function'         => array( $this, 'my_history' ),
				'show_for_displayed_user' => $show_for_displayed_user,
				'position'                => $this->buddypress['history_menu_pos']
			) );
			
			// Date Sorting
			$date_sorting = apply_filters( 'mycred_sort_by_time', array(
				''          => __( 'All', 'mycred' ),
				'today'     => __( 'Today', 'mycred' ),
				'yesterday' => __( 'Yesterday', 'mycred' ),
				'thisweek'  => __( 'This Week', 'mycred' ),
				'thismonth' => __( 'This Month', 'mycred' )
			) );
			// "All" is default
			bp_core_new_subnav_item( array(
				'name'                    => __( 'All', 'mycred' ),
				'slug'                    => $this->buddypress['history_url'],
				'parent_url'              => $bp->displayed_user->domain . $this->buddypress['history_url'] . '/',
				'parent_slug'             => $this->buddypress['history_url'],
				'screen_function'         => array( $this, 'my_history' ),
				'show_for_displayed_user' => $show_for_displayed_user
			) );
			// Loop though and add each filter option as a sub menu item
			if ( !empty( $date_sorting ) ) {
				foreach ( $date_sorting as $sorting_id => $sorting_name ) {
					if ( empty( $sorting_id ) ) continue;
					
					bp_core_new_subnav_item( array(
						'name'                    => $sorting_name,
						'slug'                    => $sorting_id,
						'parent_url'              => $bp->displayed_user->domain . $this->buddypress['history_url'] . '/',
						'parent_slug'             => $this->buddypress['history_url'],
						'screen_function'         => array( $this, 'my_history' ),
						'show_for_displayed_user' => $show_for_displayed_user
					) );
				}
			}
		}
		
		/**
		 * Construct My History Page
		 * @since 0.1
		 * @version 1.0
		 */
		public function my_history() {
			add_action( 'bp_template_title',         array( $this, 'my_history_title' ) );
			add_action( 'bp_template_content',       array( $this, 'my_history_screen' ) );
			add_filter( 'mycred_log_column_headers', array( $this, 'columns' ) );
			bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
		}
		
		/**
		 * Adjust Log Columns
		 * @since 0.1
		 * @version 1.0
		 */
		public function columns( $columns ) {
			unset( $columns['column-username'] );
			return $columns;
		}
		
		/**
		 * My History Title
		 * @since 0.1
		 * @version 1.0
		 */
		public function my_history_title() {
			$title = bp_word_or_name( $this->buddypress['history_menu_title']['me'], $this->buddypress['history_menu_title']['others'], false, false );
			echo $this->core->template_tags_general( $title );
		}
		
		/**
		 * My History Content
		 * @since 0.1
		 * @version 1.1
		 */
		public function my_history_screen() {
			global $bp;
			
			$args = array(
				'user_id' => bp_displayed_user_id(),
				'number'  => apply_filters( 'mycred_bp_history_num_to_show', $this->buddypress['history_num'] )
			);
			
			if ( isset( $bp->canonical_stack['action'] ) && $bp->canonical_stack['action'] != $this->buddypress['history_url'] )
				$args['time'] = $bp->canonical_stack['action'];
			
			$log = new myCRED_Query_Log( $args );
			unset( $log->headers['column-username'] ); ?>

	<div class="wrap" id="myCRED-wrap">
		<form method="get" action="">
			<?php $log->display(); ?>

		</form>
	</div>
<?php
			$log->reset_query();
		}
		
		/**
		 * Register Hooks
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function register_hooks( $installed ) {
			if ( bp_is_active( 'groups' ) ) {
				$installed['hook_bp_groups'] = array(
					'title'       => __( 'BuddyPress: Groups', 'mycred' ),
					'description' => __( 'Awards %_plural% for group related actions. Use minus to deduct %_plural% or zero to disable a specific hook.', 'mycred' ),
					'callback'    => array( 'myCRED_BuddyPress_Groups' )
				);
			}

			if ( bp_is_active( 'xprofile' ) ) {
				$installed['hook_bp_profile'] = array(
					'title'       => __( 'BuddyPress: Members', 'mycred' ),
					'description' => __( 'Awards %_plural% for profile related actions.', 'mycred' ),
					'callback'    => array( 'myCRED_BuddyPress_Profile' )
				);
			}

			if ( function_exists( 'bp_links_setup_root_component' ) ) {
				$installed['hook_bp_links'] = array(
					'title'       => __( 'BuddyPress: Links', 'mycred' ),
					'description' => __( 'Awards %_plural% for link related actions.', 'mycred' ),
					'callback'    => array( 'myCRED_BuddyPress_Links' )
				);
			}

			if ( function_exists( 'bpa_init' ) || function_exists( 'bpgpls_init' ) ) {
				$installed['hook_bp_gallery'] = array(
					'title'       => __( 'BuddyPress: Gallery Actions', 'mycred' ),
					'description' => __( 'Awards %_plural% for creating a new gallery either using BP Album+ or BP Gallery.', 'mycred' ),
					'callback'    => array( 'myCRED_BuddyPress_Gallery' )
				);
			}

			return $installed;
		}
		
		/**
		 * After General Settings
		 * @since 0.1
		 * @version 1.1
		 */
		public function after_general_settings() {
			// Settings
			global $bp;
			
			$settings = $this->buddypress;
			
			$balance_locations = array(
				''            => __( 'Do not show.', 'mycred' ),
				'top'         => __( 'Include in Profile Header.', 'mycred' ),
				'profile_tab' => __( 'Include under the "Profile" tab', 'mycred' ),
				'both'        => __( 'Include under the "Profile" tab and Profile Header.', 'mycred' )
			);
			
			$history_locations = array(
				''    => __( 'Do not show.', 'mycred' ),
				'top' => __( 'Show in Profile', 'mycred' )
			);
			
			$bp_nav_positions = array();
			if ( isset( $bp->bp_nav ) ) {
				foreach ( $bp->bp_nav as $pos => $data ) {
					if ( $data['slug'] == 'mycred-history' ) continue; 
					$bp_nav_positions[] = ucwords( $data['slug'] ) . ' = ' . $pos;
				}
			}?>
			
				<h4><div class="icon icon-hook icon-active"></div><label><?php _e( 'BuddyPress', 'mycred' ); ?></label></h4>
				<div class="body" style="display:none;">
					<label class="subheader" for="<?php echo $this->field_id( 'balance_location' ); ?>"><?php echo $this->core->template_tags_general( __( '%singular% Balance', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<select name="<?php echo $this->field_name( 'balance_location' ); ?>" id="<?php echo $this->field_id( 'balance_location' ); ?>">
<?php
				foreach ( $balance_locations as $location => $description ) { 
					echo '<option value="' . $location . '"';
					if ( isset( $settings['balance_location'] ) && $settings['balance_location'] == $location ) echo ' selected="selected"';
					echo '>' . $description . '</option>';
				}
?>

							</select>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'visibility' => 'balance' ) ); ?>" id="<?php echo $this->field_id( array( 'visibility' => 'balance' ) ); ?>" <?php checked( $settings['visibility']['balance'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'visibility' => 'balance' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Members can view each others %_singular% balance.', 'mycred' ) ); ?></label>
						</li>
					</ol>
					<ol>
						<li>
							<label for="<?php echo $this->field_id( 'balance_template' ); ?>"><?php _e( 'Template', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'balance_template' ); ?>" id="<?php echo $this->field_id( 'balance_template' ); ?>" value="<?php echo $settings['balance_template']; ?>" class="long" /></div>
							<span class="description"><?php _e( 'Available template tags are: %creds%, %number%, %rank%', 'mycred' ); ?></span>
							<?php if ( function_exists( 'mycred_get_users_rank' ) ) echo '<br /><span class="description">' . __( 'Note that you can also use %rank_logo% to show the feature image of the rank.', 'mycred' ) . '</span>'; ?>

						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'history_location' ); ?>"><?php echo $this->core->template_tags_general( __( '%plural% History', 'mycred' ) ); ?></label>
					<ol>
						<li>
							<select name="<?php echo $this->field_name( 'history_location' ); ?>" id="<?php echo $this->field_id( 'history_location' ); ?>">
<?php
				foreach ( $history_locations as $location => $description ) { 
					echo '<option value="' . $location . '"';
					if ( isset( $settings['history_location'] ) && $settings['history_location'] == $location ) echo ' selected="selected"';
					echo '>' . $description . '</option>';
				}
?>

							</select>
						</li>
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( array( 'visibility' => 'history' ) ); ?>" id="<?php echo $this->field_id( array( 'visibility' => 'history' ) ); ?>" <?php checked( $settings['visibility']['history'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( array( 'visibility' => 'history' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Members can view each others %_plural% history.', 'mycred' ) ); ?></label>
						</li>
					</ol>
					<ol class="inline">
						<li>
							<label for="<?php echo $this->field_id( array( 'history_menu_title' => 'me' ) ); ?>"><?php _e( 'Menu Title', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'history_menu_title' => 'me' ) ); ?>" id="<?php echo $this->field_id( array( 'history_menu_title' => 'me' ) ); ?>" value="<?php echo $settings['history_menu_title']['me']; ?>" size="25" /></div>
							<span class="description"><?php _e( 'Title shown to me', 'mycred' ); ?></span>
						</li>
						<li>
							<label>&nbsp;</label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'history_menu_title' => 'others' ) ); ?>" id="<?php echo $this->field_id( array( 'history_menu_title' => 'others' ) ); ?>" value="<?php echo $settings['history_menu_title']['others']; ?>" size="25" /></div>
							<span class="description"><?php _e( 'Title shown to others. Use %s to show the first name.', 'mycred' ); ?></span>
						</li>
					</ol>
					<ol>
						<li>
							<label for="<?php echo $this->field_id( 'history_menu_pos' ); ?>"><?php _e( 'Menu Position', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'history_menu_pos' ); ?>" id="<?php echo $this->field_id( 'history_menu_pos' ); ?>" value="<?php echo $settings['history_menu_pos']; ?>" class="short" /></div>
							<span class="description"><?php echo __( 'Current menu positions:', 'mycred' ) . ' ' . implode( ', ', $bp_nav_positions ); ?></span>
						</li>
					</ol>
					<ol>
						<li>
							<label for="<?php echo $this->field_id( 'history_url' ); ?>"><?php _e( 'History URL slug', 'mycred' ); ?></label>
							<div class="h2">/ <input type="text" name="<?php echo $this->field_name( 'history_url' ); ?>" id="<?php echo $this->field_id( 'history_url' ); ?>" value="<?php echo $settings['history_url']; ?>" class="medium" />/</div>
							<span class="description"><?php echo __( 'Do not use empty spaces!', 'mycred' ); ?></span>
						</li>
					</ol>
					<ol>
						<li>
							<label for="<?php echo $this->field_id( 'history_num' ); ?>"><?php _e( 'Number of history entries to show', 'mycred' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'history_num' ); ?>" id="<?php echo $this->field_id( 'history_num' ); ?>" value="<?php echo $settings['history_num']; ?>" class="short" /></div>
						</li>
					</ol>
				</div>
<?php
		}
		
		/**
		 * Sanitize Core Settings
		 * @since 0.1
		 * @version 1.1
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			
			$new_data['buddypress']['balance_location'] = sanitize_text_field( $data['buddypress']['balance_location'] );
			$new_data['buddypress']['visibility']['balance'] = ( isset( $data['buddypress']['visibility']['balance'] ) ) ? true : false;
			
			$new_data['buddypress']['history_location'] = sanitize_text_field( $data['buddypress']['history_location'] );
			$new_data['buddypress']['balance_template'] = sanitize_text_field( $data['buddypress']['balance_template'] );
			
			$new_data['buddypress']['history_menu_title']['me'] = sanitize_text_field( $data['buddypress']['history_menu_title']['me'] );
			$new_data['buddypress']['history_menu_title']['others'] = sanitize_text_field( $data['buddypress']['history_menu_title']['others'] );
			$new_data['buddypress']['history_menu_pos'] = abs( $data['buddypress']['history_menu_pos'] );
			
			$url = sanitize_text_field( $data['buddypress']['history_url'] );
			$new_data['buddypress']['history_url'] = urlencode( $url );
			$new_data['buddypress']['history_num'] = abs( $data['buddypress']['history_num'] );
			
			$new_data['buddypress']['visibility']['history'] = ( isset( $data['buddypress']['visibility']['history'] ) ) ? true : false;
			
			return $new_data;
		}
	}
	$buddypress = new myCRED_BuddyPress();
	$buddypress->load();
}
?>