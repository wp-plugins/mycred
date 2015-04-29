<?php
/**
 * Addon: Badges
 * Addon URI: http://mycred.me/add-ons/badges/
 * Version: 1.1.1
 * Description: Give your users badges based on their interaction with your website.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_BADGE',         __FILE__ );
define( 'myCRED_BADGE_DIR',     myCRED_ADDONS_DIR . 'badges/' );
define( 'myCRED_BADGE_VERSION', myCRED_VERSION . '.1' );

// Default badge width
if ( ! defined( 'MYCRED_BADGE_WIDTH' ) )
	define( 'MYCRED_BADGE_WIDTH', 100 );

// Default badge height
if ( ! defined( 'MYCRED_BADGE_HEIGHT' ) )
	define( 'MYCRED_BADGE_HEIGHT', 100 );

require_once myCRED_BADGE_DIR . 'includes/mycred-badge-functions.php';
require_once myCRED_BADGE_DIR . 'includes/mycred-badge-shortcodes.php';

/**
 * myCRED_buyCRED_Module class
 * @since 1.5
 * @version 1.1
 */
if ( ! class_exists( 'myCRED_Badge_Module' ) ) {
	class myCRED_Badge_Module extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct( $type = 'mycred_default' ) {
			parent::__construct( 'myCRED_Badge_Module', array(
				'module_name' => 'badges',
				'defaults'    => array(
					'buddypress'  => '',
					'bbpress'     => '',
					'show_all_bp' => 0,
					'show_all_bb' => 0
				),
				'labels'      => array(
					'menu'        => __( 'Badges', 'mycred' ),
					'page_title'  => __( 'Badges', 'mycred' ),
					'page_header' => __( 'Badges', 'mycred' )
				),
				'add_to_core' => true,
				'register'    => false,
				'menu_pos'    => 90
			), $type );
		}

		/**
		 * Module Pre Init
		 * @version 1.0
		 */
		public function module_pre_init() {
			add_filter( 'mycred_add_finished', array( $this, 'add_finished' ), 30, 3 );
		}

		/**
		 * Module Init
		 * @version 1.0.2
		 */
		public function module_init() {

			$this->register_post_type();

			add_shortcode( 'mycred_my_badges', 'mycred_render_my_badges' );
			add_shortcode( 'mycred_badges',    'mycred_render_badges' );

			add_action( 'admin_head', array( $this, 'remove_user_page' ) );
			add_action( 'admin_menu', array( $this, 'add_user_subpage' ) );

			if ( class_exists( 'BuddyPress' ) )
				add_filter( 'mycred_edit_profile_tabs_bp', array( $this, 'add_user_tabs' ), 10, 2 );
			add_filter( 'mycred_edit_profile_tabs',    array( $this, 'add_user_tabs' ), 10, 2 );

			// Insert into bbPress
			if ( class_exists( 'bbPress' ) ) {
				if ( $this->badges['bbpress'] == 'profile' || $this->badges['bbpress'] == 'both' )
					add_action( 'bbp_template_after_user_profile', array( $this, 'insert_into_bbpress_profile' ) );
				if ( $this->badges['bbpress'] == 'reply' || $this->badges['bbpress'] == 'both' )
					add_action( 'bbp_theme_after_reply_author_details', array( $this, 'insert_into_bbpress_reply' ) );
			}

			// Insert into BuddyPress
			if ( class_exists( 'BuddyPress' ) ) {
				// Insert into header
				if ( $this->badges['buddypress'] == 'header' || $this->badges['buddypress'] == 'both' )
					add_action( 'bp_before_member_header_meta', array( $this, 'insert_into_buddypress' ) );
				// Insert into profile
				if ( $this->badges['buddypress'] == 'profile' || $this->badges['buddypress'] == 'both' )
					add_action( 'bp_after_profile_loop_content', array( $this, 'insert_into_buddypress' ) );
			}

		}

		/**
		 * Module Admin Init
		 * @version 1.0
		 */
		public function module_admin_init() {

			add_action( 'mycred_admin_enqueue',     array( $this, 'enqueue_scripts' ) );

			add_filter( 'manage_mycred_badge_posts_columns',       array( $this, 'adjust_column_headers' ) );
			add_action( 'manage_mycred_badge_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );

			add_filter( 'post_row_actions',           array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'post_updated_messages',      array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',           array( $this, 'enter_title_here' ) );

			add_action( 'add_meta_boxes_mycred_badge', array( $this, 'add_meta_boxes' ) );
			add_action( 'post_submitbox_start',        array( $this, 'publishing_actions' ) );
			add_action( 'save_post_mycred_badge',      array( $this, 'save_badge_post' ) );

			add_action( 'wp_ajax_mycred-assign-badge',       array( $this, 'action_assign_badge' ) );
			add_action( 'wp_ajax_mycred-remove-connections', array( $this, 'action_remove_connections' ) );

		}

		/**
		 * Remove User Badge from Menu
		 * @version 1.0
		 */
		public function remove_user_page() {
			remove_submenu_page( 'users.php', 'mycred-edit-badges' );
		}

		/**
		 * Add User Badge Sub Page
		 * @version 1.0
		 */
		public function add_user_subpage() {

			$page = add_users_page(
				__( 'Badges', 'mycred' ),
				__( 'Badges', 'mycred' ),
				'edit_users',
				'mycred-edit-badges',
				array( $this, 'badge_user_screen' )
			);
			add_action( 'admin_print_styles-' . $page, array( $this, 'badge_user_screen_header' ) );

		}

		/**
		 * Add User Tabs
		 * @version 1.0
		 */
		public function add_user_tabs( $tabs, $user = NULL ) {

			if ( ! isset( $user->ID ) )
				$user_id = $_GET['user_id'];
			else
				$user_id = $user->ID;

			$classes = 'nav-tab';
			if ( isset( $_GET['page'] ) && $_GET['page'] == 'mycred-edit-badges' ) $classes .= ' nav-tab-active';

			$tabs[] = array(
				'label'   => sprintf( __( 'Badges (%d)', 'mycred' ), count( mycred_get_users_badges( $user_id ) ) ),
				'url'     => add_query_arg( array( 'page' => 'mycred-edit-badges', 'user_id' => $user_id ), admin_url( 'users.php' ) ),
				'classes' => $classes
			);

			return $tabs;

		}

		/**
		 * AJAX: Assign Badge
		 * @version 1.1.1
		 */
		public function action_assign_badge() {
			check_ajax_referer( 'mycred-assign-badge', 'token' );

			$badge_id = absint( $_POST['badge_id'] );
			$requirements = mycred_get_badge_requirements( $badge_id );
			if ( empty( $requirements ) )
				wp_send_json_error( 'This badge has no requirements set!' );

			global $wpdb;

			$levels = array();
			foreach ( $requirements as $req_level => $needs ) {

				if ( $needs['type'] == '' )
					$needs['type'] = 'mycred_default';

				$mycred = mycred( $needs['type'] );

				if ( ! array_key_exists( $req_level, $levels ) )
					$levels[ $req_level ] = array();

				$sql = "
					SELECT user_id 
					FROM {$mycred->log_table} 
					WHERE " . $wpdb->prepare( "ctype = %s AND ref = %s ", $needs['type'], $needs['reference'] );

				$sql .= " GROUP by user_id ";

				$amount = $needs['amount'];
				if ( $needs['by'] == 'count' )
					$sql .= "HAVING COUNT( id ) >= {$amount}";
				else
					$sql .= "HAVING SUM( creds ) >= {$amount}";

				// Let others play
				$users = $wpdb->get_col( apply_filters( 'mycred_assign_badge_sql', $sql, $badge_id ) );

				if ( ! empty( $users ) ) {

					$levels[ $req_level ] = $users;
				
					$unique = array();
					foreach ( $levels[ $req_level ] as $user_id ) {

						if ( $req_level == 0 )
							$unique[] = (int) $user_id;

						// If this user has a badge under the previous level we "move" it
						elseif ( isset( $levels[ $req_level - 1 ] ) && in_array( $user_id, $levels[ $req_level - 1 ] ) ) {
							$unique[] = (int) $user_id;
							$prev_key = array_search( $user_id, $levels[ $req_level - 1 ] );
							if ( $prev_key !== false )
								unset( $levels[ $req_level - 1 ][ $prev_key ] );
						}

					}
					$levels[ $req_level ] = $unique;

				}

			}

			if ( ! empty( $levels ) ) {

				$count = 0;
				foreach ( $levels as $level => $user_ids ) {

					if ( empty( $user_ids ) ) continue;

					foreach ( $user_ids as $user_id ) {
						mycred_update_user_meta( $user_id, 'mycred_badge' . $badge_id, '', apply_filters( 'mycred_badge_user_value', $level, $user_id, $badge_id ) );
						$count ++;
					}


				}

				if ( $count > 0 )
					wp_send_json_success( sprintf( __( '%d Users earned this badge.', 'mycred' ), $count ) );

			}

			wp_send_json_error( __( 'No users has yet earned this badge.', 'mycred' ) );

		}

		/**
		 * AJAX: Remove Badge Connections
		 * @version 1.0.1
		 */
		public function action_remove_connections() {
			check_ajax_referer( 'mycred-remove-badge-connection', 'token' );

			$badge_id = absint( $_POST['badge_id'] );

			global $wpdb;

			// Delete connections
			$count = $wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => 'mycred_badge' . $badge_id ),
				array( '%s' )
			);

			if ( $count == 0 )
				wp_send_json_success( __( 'No connections where removed.', 'mycred' ) );

			wp_send_json_success( sprintf( __( '%s connections where removed.', 'mycred' ), $count ) );
		}

		/**
		 * Insert Badges into bbPress profile
		 * @version 1.1
		 */
		public function insert_into_bbpress_profile() {

			$user_id = bbp_get_displayed_user_id();
			if ( isset( $this->badges['show_all_bb'] ) && $this->badges['show_all_bb'] == 1 )
				mycred_render_my_badges( array(
					'show'    => 'all',
					'width'   => MYCRED_BADGE_WIDTH,
					'height'  => MYCRED_BADGE_HEIGHT,
					'user_id' => $user_id
				) );

			else
				mycred_display_users_badges( $user_id );

		}

		/**
		 * Insert Badges into bbPress
		 * @version 1.1
		 */
		public function insert_into_bbpress_reply() {

			$user_id = bbp_get_reply_author_id();
			if ( $user_id > 0 ) {

				if ( isset( $this->badges['show_all_bb'] ) && $this->badges['show_all_bb'] == 1 )
					mycred_render_my_badges( array(
						'show'    => 'all',
						'width'   => MYCRED_BADGE_WIDTH,
						'height'  => MYCRED_BADGE_HEIGHT,
						'user_id' => $user_id
					) );

				else
					mycred_display_users_badges( $user_id );

			}

		}

		/**
		 * Insert Badges in BuddyPress
		 * @version 1.1
		 */
		public function insert_into_buddypress() {

			$user_id = bp_displayed_user_id();
			if ( isset( $this->badges['show_all_bp'] ) && $this->badges['show_all_bp'] == 1 )
				mycred_render_my_badges( array(
					'show'    => 'all',
					'width'   => MYCRED_BADGE_WIDTH,
					'height'  => MYCRED_BADGE_HEIGHT,
					'user_id' => $user_id
				) );

			else
				mycred_display_users_badges( $user_id );

		}

		/**
		 * Add Finished
		 * @version 1.1.1
		 */
		public function add_finished( $ran, $request, $mycred ) {

			if ( $ran !== false ) {

				// Check if this reference has badges
				$badge_ids = mycred_ref_has_badge( $request['ref'], $request );
				if ( $badge_ids !== false ) {

					mycred_check_if_user_gets_badge( absint( $request['user_id'] ), $badge_ids, $request );

				}

			}

			return $ran;
		}

		/**
		 * Register Badge Post Type
		 * @version 1.0
		 */
		public function register_post_type() {
			$labels = array(
				'name'               => __( 'Badges', 'mycred' ),
				'singular_name'      => __( 'Badge', 'mycred' ),
				'add_new'            => __( 'Add New', 'mycred' ),
				'add_new_item'       => __( 'Add New Badge', 'mycred' ),
				'edit_item'          => __( 'Edit Badge', 'mycred' ),
				'new_item'           => __( 'New Badge', 'mycred' ),
				'all_items'          => __( 'Badges', 'mycred' ),
				'view_item'          => __( 'View Badge', 'mycred' ),
				'search_items'       => __( 'Search Badge', 'mycred' ),
				'not_found'          => __( 'No badges found', 'mycred' ),
				'not_found_in_trash' => __( 'No badges found in Trash', 'mycred' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Badges', 'mycred' )
			);
			
			$args = array(
				'labels'             => $labels,
				'public'             => false,
				'has_archive'        => false,
				'show_ui'            => true, 
				'show_in_menu'       => 'myCRED',
				'capability_type'    => 'page',
				'supports'           => array( 'title' )
			);

			register_post_type( 'mycred_badge', apply_filters( 'mycred_register_badge', $args ) );
		}

		/**
		 * Enqueue Scripts
		 * @version 1.0
		 */
		public function enqueue_scripts() {

			$screen = get_current_screen();
			if ( $screen->id == 'mycred_badge' )
				 wp_enqueue_media();

			elseif ( $screen->id == 'edit-mycred_badge' )
				wp_enqueue_style( 'mycred-badge-admin', plugins_url( 'assets/css/admin.css', myCRED_BADGE ) );

			elseif ( $screen->id == 'users_page_mycred-edit-badges' )
				wp_enqueue_style( 'mycred-badge-admin', plugins_url( 'assets/css/admin.css', myCRED_BADGE ) );

		}

		/**
		 * Adjust Badge Column Header
		 * @version 1.0
		 */
		public function adjust_column_headers( $defaults ) {

			$columns = array();
			$columns['cb'] = $defaults['cb'];

			// Add / Adjust
			$columns['title']               = __( 'Badge Name', 'mycred' );
			$columns['badge-default-image'] = __( 'Badge Images', 'mycred' );
			$columns['badge-reqs']          = __( 'Requirements', 'mycred' );
			$columns['badge-users']         = __( 'Users', 'mycred' );

			// Return
			return $columns;

		}

		/**
		 * Adjust Badge Column Content
		 * @version 1.0
		 */
		public function adjust_column_content( $column_name, $post_id ) {

			// Default Image
			if ( $column_name == 'badge-default-image' ) {
				$default_image = get_post_meta( $post_id, 'default_image', true ); 
				if ( $default_image != '' )
					echo '<img src="' . $default_image . '" style="max-width: ' . MYCRED_BADGE_WIDTH . 'px;height: auto;" alt="" />';

				$main_image = get_post_meta( $post_id, 'main_image', true );
				if ( $main_image != '' )
					echo '<img src="' . $main_image . '" style="max-width: ' . MYCRED_BADGE_WIDTH . 'px;height: auto;" alt="" />';

				if ( $default_image == '' && $main_image == '' )
					echo '-';

				$requirements = mycred_get_badge_requirements( $post_id );
				if ( count( $requirements ) > 1 ) {
					foreach ( $requirements as $level => $needs ) {
						$level_image = get_post_meta( $post_id, 'level_image' . $level, true );
						if ( $level_image != '' )
							echo '<img src="' . $level_image . '" style="max-width: ' . MYCRED_BADGE_WIDTH . 'px;height: auto;" alt="" />';
					}
				}

			}

			// Badge Requirements
			elseif ( $column_name == 'badge-reqs' ) {
				echo '<small>' . __( 'A user must have gained or lost:', 'mycred' ) . '</small><br />';
				echo mycred_display_badge_requirements( $post_id );
			}

			// Badge Users
			elseif ( $column_name == 'badge-users' ) {
				echo mycred_count_users_with_badge( $post_id );
			}

		}

		/**
		 * Adjust Row Actions
		 * @version 1.0
		 */
		public function adjust_row_actions( $actions, $post ) {

			if ( $post->post_type == 'mycred_badge' ) {
				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['view'] );
			}

			return $actions;

		}

		/**
		 * Adjust Post Updated Messages
		 * @version 1.0
		 */
		public function post_updated_messages( $messages ) {

			global $post;

			$messages['mycred_badge'] = array(
				0  => '',
				1  => __( 'Badge Updated.', 'mycred' ),
				2  => '',
				3  => '',
				4  => __( 'Badge Updated.', 'mycred' ),
				5  => false,
				6  => __( 'Badge Enabled', 'mycred' ),
				7  => __( 'Badge Saved', 'mycred' ),
				8  => __( 'Badge Updated.', 'mycred' ),
				9  => __( 'Badge Updated.', 'mycred' ),
				10 => __( 'Badge Updated.', 'mycred' )
			);

			return $messages;

		}

		/**
		 * Adjust Enter Title Here
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {

			global $post_type;
			if ( $post_type == 'mycred_badge' )
				return __( 'Badge Name', 'mycred' );

			return $title;

		}

		/**
		 * Add Meta Boxes
		 * @version 1.0
		 */
		public function add_meta_boxes() {

			add_meta_box(
				'mycred_badge_requirements',
				__( 'Badge Setup', 'mycred' ),
				array( $this, 'metabox_badge_requirements' ),
				'mycred_badge',
				'normal',
				'high'
			);

		}

		/**
		 * Badge Publishing Actions
		 * @version 1.0
		 */
		public function publishing_actions() {

			global $post;
			if ( ! isset( $post->post_type ) || $post->post_type != 'mycred_badge' ) return;

			$lock = '';
			if ( $post->post_status != 'publish' )
				$lock = ' disabled="disabled"'; ?>

<div id="mycred-badge-actions">

	<?php do_action( 'mycred_edit_badge_before_actions', $post ); ?>

	<input type="hidden" name="mycred-badge-edit" value="<?php echo wp_create_nonce( 'edit-mycred-badge' ); ?>" />
	<input type="button" id="mycred-assign-badge-connections"<?php echo $lock; ?> value="<?php _e( 'Assign Badge', 'mycred' ); ?>" class="button button-secondary mycred-badge-action-button" data-action="mycred-assign-badge" data-token="<?php echo wp_create_nonce( 'mycred-assign-badge' ); ?>" /> 
	<input type="button" id="mycred-remove-badge-connections"<?php echo $lock; ?> value="<?php _e( 'Remove Connections', 'mycred' ); ?>" class="button button-secondary mycred-badge-action-button" data-action="mycred-remove-connections" data-token="<?php echo wp_create_nonce( 'mycred-remove-badge-connection' ); ?>" />

	<?php do_action( 'mycred_edit_badge_after_actions', $post ); ?>

	<?php if ( $lock == '' ) : ?>
	<script type="text/javascript">
jQuery(function($) {

	$( 'input.mycred-badge-action-button' ).click(function(){
		var button = $(this);
		var label = button.val();

		$.ajax({
			type : "POST",
			data : {
				action   : button.attr( 'data-action' ),
				token    : button.attr( 'data-token' ),
				badge_id : <?php echo $post->ID; ?>
			},
			dataType : "JSON",
			url : ajaxurl,
			beforeSend : function() {
				button.attr( 'value', '<?php echo esc_js( esc_attr__( 'Processing...', 'mycred' ) ); ?>' );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( response ) {
				alert( response.data );
				button.removeAttr( 'disabled' );
				button.val( label );
			}
		});
		return false;

	});

});
	</script>
	<?php endif; ?>
</div>
<?php
		}

		/**
		 * Badge Preferences Metabox
		 * @version 1.1
		 */
		public function metabox_badge_requirements( $post ) {

			$requirements = mycred_get_badge_requirements( $post->ID, true );
			$references = mycred_get_all_references();

			$default_image = get_post_meta( $post->ID, 'default_image', true );
			$main_image = get_post_meta( $post->ID, 'main_image', true );

			$sums = array(
				'count' => __( 'Time(s)', 'mycred' ),
				'sum'   => __( 'In total', 'mycred' )
			); ?>

<style type="text/css">
#mycred-remove-badge-connections { float: right; }
#mycred-badge-actions { padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid #ccc; }
<?php if ( $post->post_status != 'publish' ) : ?>
#minor-publishing-actions { padding-bottom: 12px; }
<?php else : ?>
#minor-publishing-actions { display: none; }
<?php endif; ?>
#misc-publishing-actions { display: none; display: none !important; }
table#setup-badge-reqs { width: 100%; }
table#setup-badge-reqs tr.bodered-row td { padding-bottom: 12px; border-bottom: 1px solid #dedede; }
table#setup-badge-reqs tr.badge-requires td { padding-top: 12px; }
table#setup-badge-reqs td.level { width: 15%; color: #aaa; }
table#setup-badge-reqs td.type { width: 20%; }
table#setup-badge-reqs td.for { width: 5%; }
table#setup-badge-reqs td.reference { width: auto; }
table#setup-badge-reqs td.amount { width: 10%; }
table#setup-badge-reqs td.sum { width: 10%; }
#setup-badge-reqs td label { display: block; margin-bottom: 4px; font-weight: bold; }
p.actions { text-align: right; }
#mycred_badge_images .inside, #mycred_badge_images .inside p { margin: 0; padding: 0; }
#image-wrapper { float: none; clear: both; min-height: 100px; }
#image-wrapper #main-image { border-bottom: none; }
#setup-badge-reqs div.inner-box { float: none; clear: both; min-height: 50px; margin: 0; padding: 0; }
#setup-badge-reqs div.inner-box .thumb { display: block; width: <?php echo MYCRED_BADGE_WIDTH; ?>px; height: <?php echo MYCRED_BADGE_HEIGHT; ?>px; padding: 12px; overflow: hidden; }
#setup-badge-reqs div.inner-box .thumb img { max-width: <?php echo MYCRED_BADGE_WIDTH; ?>px; height: auto; margin: 0 auto; }
</style>
<script type="text/javascript">
jQuery(function($) {
	$( '#postimagediv h3.hndle span' ).empty().text( '<?php echo esc_js( esc_attr__( 'Badge Image', 'mycred' ) ); ?>' );
	$( '#postimagediv div.inside p a' ).attr( 'title', '<?php echo esc_js( esc_attr__( 'Set badge image', 'mycred' ) ); ?>' ).empty().text( '<?php echo esc_js( esc_attr__( 'Set badge image', 'mycred' ) ); ?>' );
});
</script>
<table class="table" style="width: 100%;" id="setup-badge-reqs">

	<?php do_action( 'mycred_edit_badge_before_req', $post ); ?>

	<tr class="bodered-row">
		<td class="level"><div class="inner-box"><div class="thumb"><?php if ( $default_image != '' ) echo '<img src="' . $default_image . '" alt="" />'; ?></div></div></td>
		<td colspan="5">
			<label><?php _e( 'Default Image', 'mycred' ); ?></label>
			<input type="text" name="mycred_badge[default_image]" placeholder="<?php _e( 'image url', 'mycred' ); ?>" id="mycred-default-image-url" class="regular-text" size="30" value="<?php echo $default_image; ?>" /> 
			<input type="button" data-target="default-image" id="mycred-add-default-image" class="button button-primary mycred-badge-load-image" value="<?php _e( 'Add Image', 'mycred' ); ?>" />
			<p><span class="description"><?php _e( 'Optional image to show when a user has not yet earned this badge.', 'mycred' ); ?></span></p>
		</td>
	</tr>
<?php

			foreach ( $requirements as $row => $needs ) {

				// First row
				if ( $row == 0 ) {

					if ( ! isset( $needs['by'] ) )
						$needs = array(
							'type'      => '',
							'reference' => '',
							'amount'    => '',
							'by'        => ''
						);
?>
	<tr class="badge-requires" id="badge-requirement-<?php echo $row; ?>">
		<td class="level"><?php printf( __( 'Level %d', 'mycred' ), $row + 1 ); ?></td>
		<td class="type">
			<?php if ( count( $this->point_types ) == 1 ) echo $this->core->plural(); ?>
			<?php mycred_types_select_from_dropdown( 'mycred_badge[req][' . $row . '][type]', 'default-badge-req-type', $needs['type'] ); ?>
		</td>
		<td class="for"><?php _e( 'for', 'mycred' ); ?></td>
		<td class="reference">
			<select name="mycred_badge[req][<?php echo $row; ?>][reference]" id="default-badge-req-reference"><?php

	
					foreach ( $references as $ref => $label ) {
						echo '<option value="' . $ref . '"';
						if ( $needs['reference'] == $ref ) echo ' selected="selected"';
						echo '>' . $label . '</option>';
					}

?></select>
		</td>
		<td class="amount">
			<input type="text" size="8" name="mycred_badge[req][<?php echo $row; ?>][amount]" id="default-badge-req-amount" value="<?php echo $needs['amount']; ?>" />
		</td>
		<td class="sum">
			<select name="mycred_badge[req][<?php echo $row; ?>][by]" id="default-badge-req-by"><?php

					foreach ( $sums as $sum => $label ) {
						echo '<option value="' . $sum . '"';
						if ( $needs['by'] == $sum ) echo ' selected="selected"';
						echo '>' . $label . '</option>';
					}

?></select>
		</td>
	</tr>
	<tr class="bodered-row">
		<td class="level"><div class="inner-box"><div class="thumb"><?php if ( $main_image != '' ) echo '<img src="' . $main_image . '" alt="" />'; ?></div></div></td>
		<td colspan="5">
			<label><?php _e( 'Main Image', 'mycred' ); ?></label>
			<input type="text" name="mycred_badge[images][<?php echo $row; ?>]" placeholder="<?php _e( 'image url', 'mycred' ); ?>" id="mycred-badge-level<?php echo $row; ?>" class="regular-text" size="30" value="<?php echo $main_image; ?>" /> 
			<input type="button" data-target="mycred-badge-level<?php echo $row; ?>" data-row="<?php echo $row; ?>" class="button button-primary mycred-badge-load-image" value="<?php _e( 'Add Image', 'mycred' ); ?>" />
		</td>
	</tr>
<?php

				}
				else {

					$default = $requirements[0];
					$level_image = get_post_meta( $post->ID, 'level_image' . $row, true );

?>
	<tr class="badge-requires" id="badge-requirement-<?php echo $row; ?>">
		<td class="level">
			<div><?php printf( __( 'Level %d', 'mycred' ), $row + 1 ); ?></div>
		</td>
		<td class="type">
			<div><?php echo $this->point_types[ $default['type'] ]; ?></div>
		</td>
		<td class="for"><?php _e( 'for', 'mycred' ); ?></td>
		<td class="reference"><div><?php echo $references[ $default['reference'] ]; ?></div></td>
		<td class="amount">
			<input type="text" size="8" name="mycred_badge[req][<?php echo $row; ?>][amount]" value="<?php echo $needs['amount']; ?>" />
		</td>
		<td class="sum"><div><?php echo $sums[ $default['by'] ]; ?></div></td>
	</tr>
	<tr class="bodered-row" id="mycred-badge-image<?php echo $row; ?>">
		<td class="level"><div class="inner-box"><div class="thumb"><?php if ( $level_image != '' ) echo '<img src="' . $level_image . '" alt="" />'; ?></div></div></td>
		<td colspan="5">
			<label><?php _e( 'Badge Image', 'mycred' ); ?></label>
			<input type="text" name="mycred_badge[images][<?php echo $row; ?>]" placeholder="<?php _e( 'image url', 'mycred' ); ?>" id="mycred-badge-level<?php echo $row; ?>" class="regular-text" size="30" value="<?php echo $level_image; ?>" /> 
			<input type="button" data-target="mycred-badge-level<?php echo $row; ?>" data-row="<?php echo $row; ?>" class="button button-primary mycred-badge-load-image" value="<?php _e( 'Add Image', 'mycred' ); ?>" />
			<p><span class="description"><?php _e( 'Leave empty if you do not want to assign a custom image for this level.', 'mycred' ); ?></span><button class="button button-secondary button-small pull-right remove-this-row"><?php _e( 'Remove this level', 'mycred' ); ?></button></p>
		</td>
	</tr>
<?php

				}

			}

?>

	<?php do_action( 'mycred_edit_badge_after_req', $post ); ?>

</table>
<p><button id="add-mycred-badge-level" class="pull-right button button-secondary"><?php _e( 'Add Level', 'mycred' ); ?></button></p>
<script type="text/javascript">
(function($) {

	$( 'select#default-badge-req-type' ).change(function(){
	
		$( 'td.type div' ).empty().text( $(this).find( ':selected' ).text() );
	
	});

	$( '#default-badge-req-reference' ).change(function(){
	
		$( 'td.reference div' ).empty().text( $(this).find( ':selected' ).text() );
	
	});

	$( 'select#default-badge-req-by' ).change(function(){
	
		$( 'td.sum div' ).empty().text( $(this).find( ':selected' ).text() );
	
	});

	var custom_uploader;

	var rows = <?php echo count( $requirements ); ?>;

	$( 'table#setup-badge-reqs' ).on( 'click', 'button.remove-this-row', function(){

		var trrow = $(this).parent().parent().parent();
		trrow.prev().remove();
		trrow.remove();
		rows = rows-1;

	});

	$( '#add-mycred-badge-level' ).on( 'click', function(e){
		e.preventDefault();

		var badgetype   = $( '#default-badge-req-type' );
		var badgeref    = $( '#default-badge-req-reference' ).find( ':selected' ).text();
		var badgeamount = $( '#default-badge-req-amount' );
		var badgeby     = $( '#default-badge-req-by' ).find( ':selected' ).text();

		var reqtemplate = '<tr class="badge-requires" id="badge-requirement-' + rows + '"><td class="level"><?php echo esc_js( esc_attr__( 'Level', 'mycred' ) ); ?> ' + ( rows + 1 ) + '</td><td class="type"><div>' + badgetype.find( ':selected' ).text() + '</div></td><td class="for"><?php echo esc_js( esc_attr__( 'for', 'mycred' ) ); ?></td><td class="reference"><div>' + badgeref + '</div></td><td class="amount"><input type="text" size="8" name="mycred_badge[req][' + rows + '][amount]" value="" /></td><td class="sum"><div>' + badgeby + '</div></td></tr><tr class="bodered-row"><td class="level"><div class="inner-box"><div class="thumb"></div></div></td><td colspan="5"><label><?php echo esc_js( esc_attr__( 'Badge Image', 'mycred' ) ); ?></label><input type="text" name="mycred_badge[images][' + rows + ']" placeholder="<?php echo esc_js( esc_attr__( 'image url', 'mycred' ) ); ?>" id="mycred-badge-level' + rows + '" class="regular-text" size="30" value="" />&nbsp;<input type="button" data-target="mycred-badge-level' + rows + '" data-row="' + rows + '" class="button button-primary mycred-badge-load-image" value="<?php echo esc_js( esc_attr__( 'Add Image', 'mycred' ) ); ?>" /><p><span class="description"><?php echo esc_js( esc_attr__( 'Leave empty if you do not want to assign a custom image for this level.', 'mycred' ) ); ?></span><br /><button class="button button-secondary button-small pull-right remove-this-row"><?php echo esc_js( esc_attr__( 'Remove this level', 'mycred' ) ); ?></button></p></td></tr>';

		$( 'table#setup-badge-reqs' ).append( reqtemplate );
		rows = rows+1;

	});

	$( 'table#setup-badge-reqs' ).on( 'click', '.mycred-badge-load-image', function(e){
		e.preventDefault();

		var imagerow = $(this).attr( 'data-target' );
		var button = $(this);

		//Extend the wp.media object
		custom_uploader = wp.media.frames.file_frame = wp.media({
			title    : '<?php echo esc_js( esc_attr__( 'Badge Image', 'mycred' ) ); ?>',
			button   : {
				text     : '<?php echo esc_js( esc_attr__( 'Use as Badge', 'mycred' ) ); ?>'
			},
			multiple : false
		});

		//When a file is selected, grab the URL and set it as the text field's value
		custom_uploader.on( 'select', function(){
			attachment = custom_uploader.state().get('selection').first().toJSON();
			if ( attachment.url != '' ) {
				console.log( attachment );
				button.prev().val( attachment.url );
				button.parent().prev().find( 'div.thumb' ).empty().append( "<img class='show-selected-image' src='" + attachment.url + "' alt='' />" );
			}
		});

		//Open the uploader dialog
		custom_uploader.open();

	});

})( jQuery );
</script>
<div class="clear clearfix"></div>
<?php
		}

		/**
		 * Save Badge Details
		 * @version 1.0
		 */
		public function save_badge_post( $post_id ) {

			// Make sure this is for badges
			if ( ! isset( $_POST['mycred_badge'] ) || ! isset( $_POST['mycred-badge-edit'] ) || ! wp_verify_nonce( $_POST['mycred-badge-edit'], 'edit-mycred-badge' ) ) return;

			if ( isset( $_POST['mycred_badge']['req'][0] ) ) {

				$requirements = array();

				$base_requirements = $_POST['mycred_badge']['req'][0];
				$requirements[0] = $base_requirements;

				if ( count( $_POST['mycred_badge']['req'] ) > 1 ) {
					foreach ( $_POST['mycred_badge']['req'] as $row => $req ) {

						if ( $row == 0 || $req['amount'] == '' ) continue;

						$requirements[ $row ] = array(
							'type'      => $base_requirements['type'],
							'reference' => $base_requirements['reference'],
							'amount'    => $req['amount'],
							'by'        => $base_requirements['by']
						);

					}
				}

			}

			// Requirements
			update_post_meta( $post_id, 'badge_requirements', $requirements );

			// Default Image
			$default_image = sanitize_text_field( $_POST['mycred_badge']['default_image'] );
			update_post_meta( $post_id, 'default_image', $default_image );

			if ( isset( $_POST['mycred_badge']['images'] ) || count( $_POST['mycred_badge']['images'] ) > 0 ) {

				// Main Image
				$main_image = sanitize_text_field( $_POST['mycred_badge']['images'][0] );
				update_post_meta( $post_id, 'main_image', $main_image );

				global $wpdb;

				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s;", $post_id, 'level_image%' ) );

				if ( count( $_POST['mycred_badge']['images'] ) > 1 ) {
					foreach ( $_POST['mycred_badge']['images'] as $row => $image ) {

						if ( $row == 0 || $image == '' ) continue;

						update_post_meta( $post_id, 'level_image' . $row, $image );

					}
				}

			}

			

			// Let others play
			do_action( 'mycred_save_badge', $post_id );
		}

		/**
		 * Add to General Settings
		 * @version 1.0.1
		 */
		public function after_general_settings( $mycred ) {
			$settings = $this->badges;

			$buddypress = false; 
			if ( class_exists( 'BuddyPress' ) )
				$buddypress = true;
			
			$bbpress = false;
			if ( class_exists( 'bbPress' ) )
				$bbpress = true;

			if ( ! $buddypress && ! $bbpress ) return; ?>

<h4><div class="icon icon-hook icon-active"></div><label>Badges</label></h4>
<div class="body" style="display:none;">

	<?php if ( $buddypress ) : ?>
	<label class="subheader" for="<?php echo $this->field_id( 'buddypress' ); ?>">BuddyPress</label>
	<ol>
		<li>
			<select name="<?php echo $this->field_name( 'buddypress' ); ?>" id="<?php echo $this->field_id( 'buddypress' ); ?>">
<?php

			$buddypress_options = array(
				''        => __( 'Do not show', 'mycred' ),
				'header'  => __( 'Include in Profile Header', 'mycred' ),
				'profile' => __( 'Include under the "Profile" tab', 'mycred' ),
				'both'    => __( 'Include under the "Profile" tab and Profile Header', 'mycred' )
			);
			foreach ( $buddypress_options as $location => $description ) { 
				echo '<option value="' . $location . '"';
				if ( isset( $settings['buddypress'] ) && $settings['buddypress'] == $location ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}

?>

			</select>
		</li>
		<li>
			<label for="<?php echo $this->field_id( 'show_all_bp' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_all_bp' ); ?>" id="<?php echo $this->field_id( 'show_all_bp' ); ?>" <?php checked( $settings['show_all_bp'], 1 ); ?> value="1" /> <?php _e( 'Show all badges, including badges users have not yet earned.', 'mycred' ); ?></label>
		</li>
	</ol>
	<?php else : ?>
	<input type="hidden" name="<?php echo $this->field_name( 'buddypress' ); ?>" id="<?php echo $this->field_id( 'buddypress' ); ?>" value="" />
	<?php endif; ?>

	<?php if ( $bbpress ) : ?>
	<label class="subheader" for="<?php echo $this->field_id( 'bbpress' ); ?>">bbPress</label>
	<ol>
		<li>
			<select name="<?php echo $this->field_name( 'bbpress' ); ?>" id="<?php echo $this->field_id( 'bbpress' ); ?>">
<?php

			$bbpress_options = array(
				''        => __( 'Do not show', 'mycred' ),
				'profile' => __( 'Include in Profile', 'mycred' ),
				'reply'   => __( 'Include in Forum Replies', 'mycred' ),
				'both'    => __( 'Include in Profile and Forum Replies', 'mycred' )
			);
			foreach ( $bbpress_options as $location => $description ) { 
				echo '<option value="' . $location . '"';
				if ( isset( $settings['bbpress'] ) && $settings['bbpress'] == $location ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}

?>

			</select>
		</li>
		<li>
			<label for="<?php echo $this->field_id( 'show_all_bb' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_all_bb' ); ?>" id="<?php echo $this->field_id( 'show_all_bb' ); ?>" <?php checked( $settings['show_all_bb'], 1 ); ?> value="1" /> <?php _e( 'Show all badges, including badges users have not yet earned.', 'mycred' ); ?></label>
		</li>
	</ol>
	<?php else : ?>
	<input type="hidden" name="<?php echo $this->field_name( 'bbpress' ); ?>" id="<?php echo $this->field_id( 'bbpress' ); ?>" value="" />
	<?php endif; ?>


</div>
<?php
		}

		/**
		 * Save Settings
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['badges']['show_all_bp'] = ( isset( $new_data['badges']['show_all_bp'] ) ) ? $new_data['badges']['show_all_bp'] : 0;
			$new_data['badges']['show_all_bb'] = ( isset( $new_data['badges']['show_all_bb'] ) ) ? $new_data['badges']['show_all_bb'] : 0;

			$new_data['badges']['buddypress'] = sanitize_text_field( $data['badges']['buddypress'] );
			$new_data['badges']['bbpress'] = sanitize_text_field( $data['badges']['bbpress'] );

			return $new_data;

		}

		/**
		 * User Screen Header
		 * @version 1.0
		 */
		public function badge_user_screen_header() {

			if ( isset( $_POST['mycred_badge_manual']['token'] ) && isset( $_GET['user_id'] ) ) {

				$user_id = absint( $_GET['user_id'] );
				if ( wp_verify_nonce( $_POST['mycred_badge_manual']['token'], 'mycred-adjust-users-badge' . $user_id ) ) {

					$added = $removed = $updated = 0;
					$users_badges = mycred_get_users_badges( $user_id );
					if ( ! empty( $_POST['mycred_badge_manual']['badges'] ) ) {
						foreach ( $_POST['mycred_badge_manual']['badges'] as $badge_id => $badge ) {
							// Give badge
							if ( ! array_key_exists( $badge_id, $users_badges ) && isset( $badge['has'] ) && $badge['has'] == 1 ) {
								$level = 0;
								if ( isset( $badge['level'] ) && $badge['level'] != '' )
									$level = absint( $badge['level'] );

								update_user_meta( $user_id, 'mycred_badge' . $badge_id, $level );
								$added ++;
							}
							// Remove badge
							elseif ( array_key_exists( $badge_id, $users_badges ) && ! isset( $badge['has'] ) ) {
								delete_user_meta( $user_id, 'mycred_badge' . $badge_id );
								$removed ++;
							}
							// Level change
							elseif ( array_key_exists( $badge_id, $users_badges ) && isset( $badge['level'] ) && $badge['level'] != $users_badges[ $badge_id ] ) {
								update_user_meta( $user_id, 'mycred_badge' . $badge_id, absint( $badge['level'] ) );
								$updated ++;
							}
						}
					}

				}

			}

		}

		/**
		 * User Badges Admin Screen
		 * @version 1.0
		 */
		public function badge_user_screen() {

			global $mycred_manual_badges;

			$user_id = absint( $_GET['user_id'] );
			$user = get_userdata( $user_id );

			global $bp;

			$mycred_admin = new myCRED_Admin();

			if ( is_object( $bp ) && isset( $bp->version ) && version_compare( $bp->version, '2.0', '>=' ) && bp_is_active( 'xprofile' ) )
				$mycred_admin->using_bp = true;

			$all_badges = mycred_get_badge_ids();
			$users_badges = mycred_get_users_badges( $user_id );

?>
<div class="wrap" id="edit-badges-page">
	<h2><?php _e( 'User Badges', 'mycred' ); ?></h2>
	<?php if ( isset( $_POST['mycred_badge_manual'] ) ) echo '<div class="updated"><p>Badges successfully updated.</p></div>'; ?>
	<form id="your-profile" action="" method="post">
		<?php $mycred_admin->user_nav( $user, 'badges' ); ?>
		<div class="clear clearfix"></div>
		<p><?php _e( 'Here you can view the badges this user has earned and if needed, manually give or take away a badge from a user.', 'mycred' ); ?></p>
		<div id="badge-wrapper">
<?php

			if ( ! empty( $all_badges ) ) {
				foreach ( $all_badges as $badge_id ) {

					$badge_id = absint( $badge_id );
					$earned = $level = 0;
					$status = '<span class="not-earned">' . __( 'Not earned', 'mycred' ) . '</span>';
					$image = get_post_meta( $badge_id, 'default_image', true );
					$requirements = mycred_get_badge_requirements( $badge_id );

					if ( array_key_exists( $badge_id, $users_badges ) ) {
						$earned = 1;
						$status = '<span class="earned">' . __( 'Earned', 'mycred' ) . '</span>';
						$level = $users_badges[ $badge_id ];

						$image = get_post_meta( $badge_id, 'level_image' . $level, true );
						if ( $image == '' )
							$image = get_post_meta( $badge_id, 'main_image', true );
						
					}

					if ( $image != '' )
						$image = '<img src="' . $image . '" alt="" />';
					else
						$image = '<span>' . __( 'No image', 'mycred' ) . '</span>';

					$level_select = '<input type="hidden" name="mycred_badge_manual[badges][' . $badge_id . '][level]" value="" />';
					if ( count( $requirements ) > 1 ) {
						$level_select = '<li><select name="mycred_badge_manual[badges][' . $badge_id . '][level]">';
						$level_select .= '<option value=""';
						if ( ! $earned ) $level_select .= ' selected="selected"';
						$level_select .= '>' . __( 'Select a level', 'mycred' ) . '</option>';
						foreach ( $requirements as $l => $needs ) {
							$level_select .= '<option value="' . $l . '"';
							if ( $earned && $level == $l ) $level_select .= ' selected="selected"';
							$level_select .= '>' . __( 'Level', 'mycred' ) . ' ' . ( $l + 1 ) . '</option>';
						}
						$level_select .= '</select></li>';
					}

?>
			<div class="the-badge">
				<div class="badge-image-wrap">
					<?php echo $image; ?>
				</div>
				<h4><?php echo get_the_title( $badge_id ); ?></h4>
				<div class="badge-status"><label><?php _e( 'Status', 'mycred' ); ?></label><?php echo $status; ?></div>
				<div class="badge-actions">
					<ul>
						<li><label><input type="checkbox" name="mycred_badge_manual[badges][<?php echo $badge_id; ?>][has]" <?php checked( $earned, 1 );?> value="1" /> <?php _e( 'Earned', 'mycred' ); ?></label></li>
						<?php echo $level_select; ?>
					</ul>
				</div>
			</div>
<?php

				}
			}

?>
			<div class="clear clearfix"></div>
		</div>
		<input type="hidden" name="mycred_badge_manual[token]" value="<?php echo wp_create_nonce( 'mycred-adjust-users-badge' . $user_id ); ?>" />
		<p><input type="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'mycred' ); ?>" /></p>
	</form>
</div>
<?php

		}

	}

	$badge = new myCRED_Badge_Module();
	$badge->load();
}
?>