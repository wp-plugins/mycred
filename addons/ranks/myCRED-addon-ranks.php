<?php
/**
 * Addon: Ranks
 * Addon URI: http://mycred.me/add-ons/ranks/
 * Version: 1.1
 * Description: Create ranks for users reaching a certain number of %_plural% with the option to add logos for each rank. 
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
// Translate Header (by Dan bp-fr)
$mycred_addon_header_translate = array(
	__( 'Ranks', 'mycred' ),
	__( 'Create ranks for users reaching a certain number of points with the option to add logos for each rank.', 'mycred' )
);

if ( !defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_RANKS',         __FILE__ );
define( 'myCRED_RANKS_DIR',     myCRED_ADDONS_DIR . 'ranks/' );
define( 'myCRED_RANKS_VERSION', myCRED_VERSION . '.1' );

include_once( myCRED_RANKS_DIR . 'includes/mycred-rank-functions.php' );
include_once( myCRED_RANKS_DIR . 'includes/mycred-rank-shortcodes.php' );
/**
 * myCRED_Ranks class
 * While myCRED rankings just ranks users according to users total amount of
 * points, ranks are titles that can be given to users when their reach a certain
 * amount.
 * @since 1.1
 * @version 1.1
 */
if ( !class_exists( 'myCRED_Ranks' ) ) {
	class myCRED_Ranks extends myCRED_Module {

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'myCRED_Ranks', array(
				'module_name' => 'rank',
				'defaults'    => array(
					'public'      => 0,
					'base'        => 'current',
					'slug'        => 'mycred_rank',
					'bb_location' => 'top',
					'order'       => 'ASC',
					'support'     => array(
						'content'         => 0,
						'excerpt'         => 0,
						'comments'        => 0,
						'page-attributes' => 0,
						'custom-fields'   => 0
					)
				),
				'register'    => false,
				'add_to_core' => true
			) );

			if ( !isset( $this->rank['order'] ) ) {
				$this->rank['order'] = 'ASC';
			}
			if ( !isset( $this->rank['support'] ) )
				$this->rank['support'] = array(
					'content'         => 0,
					'excerpt'         => 0,
					'comments'        => 0,
					'page-attributes' => 0,
					'custom-fields'   => 0
				);

			add_action( 'mycred_parse_tags_user',    array( $this, 'parse_rank' ), 10, 3 );
			add_action( 'mycred_post_type_excludes', array( $this, 'exclude_ranks' ) );
		}

		/**
		 * Hook into Init
		 * @since 1.1
		 * @version 1.3
		 */
		public function module_init() {
			global $mycred_ranks;

			$this->register_post_type();
			$this->add_default_rank();

			add_filter( 'pre_get_posts',          array( $this, 'adjust_wp_query' ), 20       );
			add_action( 'mycred_admin_enqueue',   array( $this, 'enqueue_scripts' )           );

			// Instances to update ranks
			add_action( 'transition_post_status',     array( $this, 'post_status_change' ), 99, 3  );
			add_action( 'user_register',              array( $this, 'registration' ), 999          );
			add_filter( 'mycred_add',                 array( $this, 'update_balance' ), 99, 3      );

			// BuddyPress
			if ( function_exists( 'bp_displayed_user_id' ) && isset( $this->rank['bb_location'] ) && !empty( $this->rank['bb_location'] ) ) {
				if ( $this->rank['bb_location'] == 'top' || $this->rank['bb_location'] == 'both' )
					add_action( 'bp_before_member_header_meta',  array( $this, 'insert_rank_header' ) );

				if ( $this->rank['bb_location'] == 'profile_tab' || $this->rank['bb_location'] == 'both' )
					add_action( 'bp_after_profile_loop_content', array( $this, 'insert_rank_profile' ) );
			}

			// Shortcodes
			add_shortcode( 'mycred_my_rank',            'mycred_render_my_rank' );
			add_shortcode( 'mycred_users_of_rank',      'mycred_render_users_of_rank' );
			add_shortcode( 'mycred_users_of_all_ranks', 'mycred_render_users_of_all_ranks' );
			add_shortcode( 'mycred_list_ranks',         'mycred_render_rank_list' );
			
			add_action( 'mycred_management_prefs', array( $this, 'rank_management' ) );

			add_action( 'wp_ajax_mycred-calc-totals', array( $this, 'calculate_totals' ) );
		}

		/**
		 * Hook into Admin Init
		 * @since 1.1
		 * @version 1.1
		 */
		public function module_admin_init() {
			add_action( 'admin_print_styles-edit-mycred_rank', array( $this, 'ranks_page_header' ) );
			add_filter( 'manage_mycred_rank_posts_columns',       array( $this, 'adjust_column_headers' )        );
			add_action( 'manage_mycred_rank_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );

			add_filter( 'manage_users_columns',       array( $this, 'custom_user_column' )                );
			add_action( 'manage_users_custom_column', array( $this, 'custom_user_column_content' ), 10, 3 );

			add_filter( 'post_row_actions',           array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'post_updated_messages',      array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',           array( $this, 'enter_title_here' )      );

			add_action( 'add_meta_boxes_mycred_rank', array( $this, 'add_meta_boxes' )        );
			add_action( 'save_post',                  array( $this, 'save_rank_settings' )    );
			
			add_action( 'wp_ajax_mycred-action-delete-ranks', array( $this, 'action_delete_ranks' ) );
			add_action( 'wp_ajax_mycred-action-assign-ranks', array( $this, 'action_assign_ranks' ) );
		}
		
		/**
		 * Delete Ranks
		 * @since 1.3.2
		 * @version 1.0
		 */
		public function action_delete_ranks() {
			check_ajax_referer( 'mycred-management-actions-roles', 'token' );

			global $wpdb;

			// First get the ids of all existing ranks
			$rank_ids = $wpdb->get_col( "
				SELECT ID 
				FROM {$wpdb->posts} 
				WHERE post_type = 'mycred_rank';" );

			// Delete all ranks
			$wpdb->query( "
				DELETE FROM {$wpdb->posts} 
				WHERE post_type = 'mycred_rank';" );

			// Delete rank post meta
			if ( $rank_ids ) {
				$ids = implode( ',', $rank_ids );
				$wpdb->query( "
					DELETE FROM {$wpdb->postmeta} 
					WHERE post_id IN ({$ids});" );
			}

			// Confirm that ranks are gone
			$rows = $wpdb->get_var( "
				SELECT COUNT(*) 
				FROM {$wpdb->posts} 
				WHERE post_type = 'mycred_rank';" );

			die( json_encode( array( 'status' => 'OK', 'rows' => $rows ) ) );
		}
		
		/**
		 * Assign Ranks
		 * @since 1.3.2
		 * @version 1.0
		 */
		public function action_assign_ranks() {
			check_ajax_referer( 'mycred-management-actions-roles', 'token' );

			$adjustments = mycred_assign_ranks();
			die( json_encode( array( 'status' => 'OK', 'rows' => $adjustments ) ) );
		}

		/**
		 * Exclude Ranks from Publish Content Hook
		 * @since 1.3
		 * @version 1.0
		 */
		public function exclude_ranks( $excludes ) {
			$excludes[] = 'mycred_rank';
			return $excludes;
		}

		/**
		 * Enqueue Scripts & Styles
		 * @since 1.1
		 * @version 1.1
		 */
		public function enqueue_scripts() {
			$screen = get_current_screen();

			// Ranks List Page
			if ( $screen->id == 'edit-mycred_rank' ) {
				wp_enqueue_style( 'mycred-admin' );
				wp_enqueue_style( 'mycred-admin' ); 
			}

			// Edit Rank Page
			if ( $screen->id == 'mycred_rank' ) {
				wp_enqueue_style( 'mycred-admin' );
				wp_dequeue_script( 'autosave' );
			}

			// Insert management script
			if ( $screen->id == 'mycred_page_myCRED_page_settings' ) {
				wp_register_script(
					'mycred-rank-management',
					plugins_url( 'js/management.js', myCRED_RANKS ),
					array( 'jquery' ),
					myCRED_VERSION . '.1'
				);
				wp_localize_script(
					'mycred-rank-management',
					'myCRED_Ranks',
					array(
						'ajaxurl'        => admin_url( 'admin-ajax.php' ),
						'token'          => wp_create_nonce( 'mycred-management-actions-roles' ),
						'working'        => __( 'Processing...', 'mycred' ),
						'confirm_del'    => __( 'Warning! All ranks will be deleted! This can not be undone!', 'mycred' ),
						'confirm_assign' => __( 'Are you sure you want to re-assign user ranks?', 'mycred' )
					)
				);
				wp_enqueue_script( 'mycred-rank-management' );
			}

			if ( in_array( $screen->id, array( 'edit-mycred_rank', 'mycred_rank' ) ) ) { ?>
<style type="text/css">
#icon-myCRED, .icon32-posts-mycred_email_notice, .icon32-posts-mycred_rank { background-image: url(<?php echo apply_filters( 'mycred_icon', plugins_url( 'assets/images/cred-icon32.png', myCRED_THIS ) ); ?>); }
</style>
<?php
			}
		}

		/**
		 * Register Rank Post Type
		 * @since 1.1
		 * @version 1.1
		 */
		public function register_post_type() {
			$labels = array(
				'name'               => __( 'Ranks', 'mycred' ),
				'singular_name'      => __( 'Rank', 'mycred' ),
				'add_new'            => __( 'Add New', 'mycred' ),
				'add_new_item'       => __( 'Add New Rank', 'mycred' ),
				'edit_item'          => __( 'Edit Rank', 'mycred' ),
				'new_item'           => __( 'New Rank', 'mycred' ),
				'all_items'          => __( 'Ranks', 'mycred' ),
				'view_item'          => __( 'View Rank', 'mycred' ),
				'search_items'       => __( 'Search Ranks', 'mycred' ),
				'not_found'          => __( 'No ranks found', 'mycred' ),
				'not_found_in_trash' => __( 'No ranks found in Trash', 'mycred' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Ranks', 'mycred' )
			);

			// Support
			$supports = array( 'title', 'thumbnail' );
			if ( isset( $this->rank['support']['content'] ) && $this->rank['support']['content'] )
				$supports[] = 'editor';
			if ( isset( $this->rank['support']['excerpt'] ) && $this->rank['support']['excerpt'] )
				$supports[] = 'excerpts';
			if ( isset( $this->rank['support']['comments'] ) && $this->rank['support']['comments'] )
				$supports[] = 'comments';
			if ( isset( $this->rank['support']['page-attributes'] ) && $this->rank['support']['page-attributes'] )
				$supports[] = 'page-attributes';
			if ( isset( $this->rank['support']['custom-fields'] ) && $this->rank['support']['custom-fields'] )
				$supports[] = 'custom-fields';

			$args = array(
				'labels'             => $labels,
				'public'             => (bool) $this->rank['public'],
				'publicly_queryable' => (bool) $this->rank['public'],
				'has_archive'        => (bool) $this->rank['public'],
				'show_ui'            => true, 
				'show_in_menu'       => 'myCRED',
				'capability_type'    => 'page',
				'supports'           => $supports
			);

			// Rewrite
			if ( $this->rank['public'] && !empty( $this->rank['slug'] ) ) {
				$args['rewrite'] = array( 'slug' => $this->rank['slug'] );
			}
			register_post_type( 'mycred_rank', apply_filters( 'mycred_register_ranks', $args ) );
		}

		/**
		 * AJAX: Calculate Totals
		 * @since 1.2
		 * @version 1.0.1
		 */
		public function calculate_totals() {
			// Security
			check_ajax_referer( 'mycred-calc-totals', 'token' );

			global $wpdb;

			$users = $wpdb->get_results( "
				SELECT user_id AS ID, 
				SUM(CASE WHEN creds > 0 THEN creds ELSE 0 END) as positives_sum,
				SUM(CASE WHEN creds < 0 THEN creds ELSE 0 END) as negatives_sum
				FROM {$this->core->log_table} 
				GROUP BY user_id;" );

			$count = 0;
			if ( $users ) {
				foreach ( $users as $user ) {
					update_user_meta( $user->ID, $this->core->get_cred_id() . '_total', $user->positives_sum );
					$count = $count+1;
				}
			}

			if ( $count > 0 )
				die( json_encode( sprintf( __( 'Completed - Total of %d users effected', 'mycred' ), $count ) ) );
			else
				die( json_encode( __( 'Log is Empty', 'mycred' ) ) );
		}

		/**
		 * Registration
		 * Check what rank this user should have
		 * @since 1.1
		 * @version 1.0
		 */
		public function registration( $user_id ) {
			mycred_find_users_rank( $user_id, true );
		}

		/**
		 * Balance Adjustment
		 * Check if users rank should change.
		 * @since 1.1
		 * @version 1.1
		 */
		public function update_balance( $reply, $request, $mycred ) {
			// Ranks are based on current balance
			if ( $this->rank['base'] == 'current' ) {
				mycred_find_users_rank( $request['user_id'], true, $request['amount'] );
			}

			// Ranks are based on total
			else {
				// Update total
				$new_total = mycred_update_users_total( $request, $mycred );
				// Get total before update
				$total_before = $this->core->number( $new_total-$request['amount'] );
				// Make sure update went well and that there has been an actual increase
				if ( $new_total !== false && $new_total != $total_before )
					mycred_find_users_rank( $request['user_id'], true, $request['amount'], $this->core->get_cred_id() . '_total' );
			}

			return $reply;
		}

		/**
		 * Publishing Content
		 * Check if users rank should change.
		 * @since 1.1
		 * @version 1.2.1
		 */
		public function post_status_change( $new_status, $old_status, $post ) {
			global $mycred_ranks;

			// Only ranks please
			if ( $post->post_type != 'mycred_rank' ) return;

			// Publishing rank
			if ( $new_status == 'publish' && $old_status != 'publish' ) {
				mycred_assign_ranks();
			}

			// Trashing of rank
			elseif ( $new_status == 'trash' && $old_status != 'trash' ) {
				mycred_assign_ranks();
			}
		}

		/**
		 * Adjust Rank Sort Order
		 * Adjusts the wp query when viewing ranks to order by the min. point requirement.
		 * @since 1.1.1
		 * @version 1.0
		 */
		public function adjust_wp_query( $query ) {
			if ( isset( $query->query['post_type'] ) && $query->is_main_query() && $query->query['post_type'] == 'mycred_rank' ) {
				$query->set( 'meta_key', 'mycred_rank_min' );
				$query->set( 'orderby',  'meta_value_num' );

				if ( !isset( $this->rank['order'] ) ) $this->rank['order'] = 'ASC';
				$query->set( 'order',    $this->rank['order'] );
			}

			return $query;
		}

		/**
		 * Parse Rank
		 * Parses the %rank% and %rank_logo% template tags.
		 * @since 1.1
		 * @version 1.1
		 */
		public function parse_rank( $content, $user = '', $data = '' ) {
			// No rank no need to run
			if ( !preg_match( '/(%rank[%|_])/', $content ) ) return $content;

			if ( !isset( $user->ID ) ) {
				if ( is_array( $data ) && isset( $data['ID'] ) )
					$user_id = $data['ID'];
				else
					$user_id = get_current_user_id();
			}
			else {
				$user_id = $user->ID;
			}

			$rank_name = mycred_get_users_rank( $user_id );
			$content = str_replace( '%rank%', $rank_name, $content );
			$content = str_replace( '%rank_logo%', mycred_get_rank_logo( $rank_name ), $content );

			return $content;
		}

		/**
		 * Insert Rank In Profile Header
		 * @since 1.1
		 * @version 1.0
		 */
		public function insert_rank_header() {
			if ( bp_is_my_profile() || mycred_is_admin() ) {
				$user_id = bp_displayed_user_id();
				if ( $this->core->exclude_user( $user_id ) ) return;

				$rank_name = mycred_get_users_rank( $user_id );
				echo '<div id="mycred-my-rank">' . __( 'Rank', 'mycred' ) . ': ' . $rank_name . ' ' . mycred_get_rank_logo( $rank_name ) . '</div>';
			}
		}

		/**
		 * Insert Rank In Profile Details
		 * @since 1.1
		 * @version 1.1
		 */
		public function insert_rank_profile() {
			$user_id = bp_displayed_user_id();
			if ( $this->core->exclude_user( $user_id ) ) return;
			$rank_name = mycred_get_users_rank( $user_id ); ?>

<div class="bp-widget mycred-field">
	<table class="profile-fields">
		<tr id="mycred-users-rank">
			<td class="label"><?php _e( 'Rank', 'mycred' ); ?></td>
			<td class="data">
				<?php echo $rank_name . ' ' . mycred_get_rank_logo( $rank_name ); ?>

			</td>
		</tr>
	</table>
</div>
<?php
		}

		/**
		 * Add Default Rank
		 * Adds the default "Newbie" rank and adds all non-exluded user to this rank.
		 * Note! This method is only called when there are zero ranks as this will create the new default rank.
		 * @uses wp_insert_port()
		 * @uses update_post_meta()
		 * @uses get_users()
		 * @uses update_user_meta()
		 * @since 1.1
		 * @version 1.0.1
		 */
		public function add_default_rank() {
			global $mycred_ranks;

			// If there are no ranks at all
			if ( ! mycred_have_ranks() ) {
				// Construct a new post
				$rank = array();
				$rank['post_title'] = __( 'Newbie', 'mycred' );
				$rank['post_type'] = 'mycred_rank';
				$rank['post_status'] = 'publish';

				// Insert new rank post
				$rank_id = wp_insert_post( $rank );

				// Update min and max values
				update_post_meta( $rank_id, 'mycred_rank_min', 0 );
				update_post_meta( $rank_id, 'mycred_rank_max', 9999999 );

				$mycred_ranks = 1;
				mycred_assign_ranks();
			}
		}

		/**
		 * Adjust Post Updated Messages
		 * @since 1.1
		 * @version 1.0
		 */
		public function post_updated_messages( $messages ) {
			global $post;

			$messages['mycred_rank'] = array(
				0 => '',
				1 => sprintf( __( 'Rank Updated. View <a href="%1$s">All Ranks</a>.', 'mycred' ), admin_url( 'edit.php?post_type=mycred_rank' ) ),
				2 => __( 'Custom field updated', 'mycred' ),
				3 => __( 'Custom filed updated', 'mycred' ),
				4 => sprintf( __( 'Rank Updated. View <a href="%1$s">All Ranks</a>.', 'mycred' ), admin_url( 'edit.php?post_type=mycred_rank' ) ),
				5 => false,
				6 => __( 'Rank Activated', 'mycred' ),
				7 => __( 'Rank Saved', 'mycred' ),
				8 => sprintf( __( 'Rank Submitted for approval. View <a href="%1$s">All Ranks</a>.', 'mycred' ), admin_url( 'edit.php?post_type=mycred_rank' ) ),
				9 => sprintf(
					__( 'Rank scheduled for: <strong>%1$s</strong>.', 'mycred' ),
					date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $post->post_date ) )
					),
				10 => ''
			);

			return $messages;
		}

		/**
		 * Adjust Row Actions
		 * @since 1.1
		 * @version 1.0
		 */
		public function adjust_row_actions( $actions, $post ) {
			if ( $post->post_type == 'mycred_rank' ) {
				unset( $actions['inline hide-if-no-js'] );

				if ( !$this->rank['public'] )
					unset( $actions['view'] );
			}

			return $actions;
		}

		/**
		 * Customize Users Column Headers
		 * @since 1.1.1
		 * @version 1.0
		 */
		public function custom_user_column( $columns ) {
			$columns['mycred-rank'] = __( 'Rank', 'mycred' );
			return $columns;
		}

		/**
		 * Customize User Columns Content
		 * @filter 'mycred_user_row_actions'
		 * @since 1.1.1
		 * @version 1.0
		 */
		public function custom_user_column_content( $value, $column_name, $user_id ) {
			if ( 'mycred-rank' != $column_name ) return $value;

			return mycred_get_users_rank( $user_id );
		}

		/**
		 * Adjust Rank Column Header
		 * @since 1.1
		 * @version 1.0
		 */
		public function adjust_column_headers( $defaults ) {
			// Remove
			unset( $defaults['date'] );

			// Add / Adjust
			$defaults['title'] = __( 'Rank Title', 'mycred' );
			$defaults['mycred-rank-logo'] = __( 'Logo', 'mycred' );
			$defaults['mycred-rank-req'] = __( 'Requirement', 'mycred' );
			$defaults['mycred-rank-users'] = __( 'Users', 'mycred' );

			// Return
			return $defaults;
		}

		/**
		 * Adjust Rank Column Content
		 * @since 1.1
		 * @version 1.0
		 */
		public function adjust_column_content( $column_name, $post_id ) {
			// Rank Logo (thumbnail)
			if ( $column_name == 'mycred-rank-logo' ) {
				$logo = mycred_get_rank_logo( $post_id, 'thumbnail' );
				if ( empty( $logo ) )
					echo '<p>' . __( 'No Logo Set', 'mycred' );
				else
					echo '<p>' . $logo . '</p>';
			}
			// Rank Requirement (custom metabox)
			elseif ( $column_name == 'mycred-rank-req' ) {
				$mycred = mycred_get_settings();
				$min = get_post_meta( $post_id, 'mycred_rank_min', true );
				if ( empty( $min ) && (int) $min !== 0 )
					$min = __( 'Any Value', 'mycred' );

				$min = $mycred->template_tags_general( __( 'Minimum %plural%', 'mycred' ) ) . ': ' . $min;
				$max = get_post_meta( $post_id, 'mycred_rank_max', true );
				if ( empty( $max ) )
					$max = __( 'Any Value', 'mycred' );

				$max = $mycred->template_tags_general( __( 'Maximum %plural%', 'mycred' ) ) . ': ' . $max;
				echo '<p>' . $min . '<br />' . $max . '</p>';
			}
			// Rank Users (user list)
			elseif ( $column_name == 'mycred-rank-users' ) {
				$users = count( mycred_get_users_of_rank( $post_id ) );
				//if ( $users > 0 )
				//	$users = '<a href="' . admin_url( 'users.php?rank=' . $post_id ) . '">' . $users . '</a>';
				echo '<p>' . $users . '</p>';
			}
		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.1
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {
			global $post_type;
			if ( $post_type == 'mycred_rank' )
				return __( 'Rank Title', 'mycred' );

			return $title;
		}

		/**
		 * Add Meta Boxes
		 * @since 1.1
		 * @version 1.0
		 */
		public function add_meta_boxes() {
			add_meta_box(
				'mycred_rank_settings',
				__( 'Rank Settings', 'mycred' ),
				array( $this, 'rank_settings' ),
				'mycred_rank',
				'normal',
				'high'
			);
		}

		/**
		 * Rank Settings Metabox
		 * @since 1.1
		 * @version 1.0
		 */
		public function rank_settings( $post ) {
			$mycred = mycred_get_settings();
			$min = get_post_meta( $post->ID, 'mycred_rank_min', true );
			$max = get_post_meta( $post->ID, 'mycred_rank_max', true ); ?>

<input type="hidden" name="mycred_rank[token]" value="<?php echo wp_create_nonce( 'mycred-edit-rank' ); ?>" />
<div style="display:block;float:none;clear:both;">
	<div style="display:block;width:50%;margin:0;padding:0;float:left;">
		<p>
			<?php echo $mycred->template_tags_general( __( 'Minimum %plural% to reach this rank', 'mycred' ) ); ?>:<br />
			<input type="text" name="mycred_rank[min]" id="mycred-rank-min" value="<?php echo $min; ?>" />
		</p>
		<p>
			<?php echo $mycred->template_tags_general( __( 'Maximum %plural% to be included in this rank', 'mycred' ) ); ?>:<br />
			<input type="text" name="mycred_rank[max]" id="mycred-rank-max" value="<?php echo $max; ?>" />
		</p>
	</div>
	<div style="display:block;width:50%;margin:0;padding:0;float:left;">
		<p><?php _e( 'All Published Ranks', 'mycred' ); ?>:</p>
		<?php

			$all = mycred_get_ranks();
			if ( !empty( $all ) ) {
				foreach ( $all as $rank_id => $rank ) {
					$_min = get_post_meta( $rank_id, 'mycred_rank_min', true );
					if ( empty( $_min ) && (int) $_min !== 0 ) $_min = __( 'Not Set', 'mycred' );
					$_max = get_post_meta( $rank_id, 'mycred_rank_max', true );
					if ( empty( $_max ) ) $_max = __( 'Not Set', 'mycred' );
					echo '<p><strong style="display:inline-block;width:20%;">' . $rank->post_title . '</strong> ' . $_min . ' - ' . $_max . '</p>';
				}
			}
			else {
				echo '<p>' . __( 'No Ranks found', 'mycred' ) . '.</p>';
			} ?>
	</div>
	<div class="clear">&nbsp;</div>
</div>
<?php
		}

		/**
		 * Save Email Notice Details
		 * @since 1.1
		 * @version 1.0
		 */
		public function save_rank_settings( $post_id ) {
			// Make sure this is the correct post type
			if ( get_post_type( $post_id ) != 'mycred_rank' ) return;
			// Make sure we can edit
			elseif ( !mycred_is_admin( get_current_user_id() ) ) return;
			// Make sure fields exists
			elseif ( !isset( $_POST['mycred_rank'] ) || !is_array( $_POST['mycred_rank'] ) ) return;
			// Finally check token
			elseif ( !wp_verify_nonce( $_POST['mycred_rank']['token'], 'mycred-edit-rank' ) ) return;

			// Minimum can not be empty
			if ( empty( $_POST['mycred_rank']['min'] ) )
				$min = 0;
			else
				$min = trim( $_POST['mycred_rank']['min'] );

			// Maximum can not be empty
			if ( empty( $_POST['mycred_rank']['max'] ) )
				$max = 999;
			else
				$max = trim( $_POST['mycred_rank']['max'] );

			update_post_meta( $post_id, 'mycred_rank_min', $min );
			update_post_meta( $post_id, 'mycred_rank_max', $max );

			if ( get_post_status( $post_id ) == 'publish' )
				mycred_assign_ranks();
		}

		/**
		 * Add to General Settings
		 * @since 1.1
		 * @version 1.2
		 */
		public function after_general_settings() {
			if ( $this->rank['base'] == 'current' )
				$box = 'display: none;';
			else
				$box = 'display: block;'; ?>

				<h4><div class="icon icon-active"></div><?php _e( 'Ranks', 'mycred' ); ?></h4>
				<div class="body" style="display:none;">
					<label class="subheader" for="<?php echo $this->field_id( 'public' ); ?>"><?php _e( 'Rank Features', 'mycred' ); ?></label>
					<ol id="myCRED-rank-supports">
						<li>
							<input type="checkbox" value="1" checked="checked" disabled="disabled" /> <label for=""><?php _e( 'Title', 'mycred' ); ?></label><br />
							<input type="checkbox" value="1" checked="checked" disabled="disabled" /> <label for=""><?php echo $this->core->template_tags_general( __( '%plural% requirement', 'mycred' ) ); ?></label><br />
							<input type="checkbox" value="1" checked="checked" disabled="disabled" /> <label for=""><?php _e( 'Featured Image (Logo)', 'mycred' ); ?></label><br />
							<input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'content' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'content' ) ); ?>" <?php checked( $this->rank['support']['content'], 1 ); ?> value="1" /> <label for=""><?php _e( 'Content', 'mycred' ); ?></label><br />
							<input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'excerpt' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'excerpt' ) ); ?>" <?php checked( $this->rank['support']['excerpt'], 1 ); ?> value="1" /> <label for=""><?php _e( 'Excerpt', 'mycred' ); ?></label><br />
							<input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'comments' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'comments' ) ); ?>" <?php checked( $this->rank['support']['comments'], 1 ); ?> value="1" /> <label for=""><?php _e( 'Comments', 'mycred' ); ?></label><br />
							<input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'page-attributes' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'page-attributes' ) ); ?>" <?php checked( $this->rank['support']['page-attributes'], 1 ); ?> value="1" /> <label for=""><?php _e( 'Page Attributes', 'mycred' ); ?></label><br />
							<input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'custom-fields' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'custom-fields' ) ); ?>" <?php checked( $this->rank['support']['custom-fields'], 1 ); ?> value="1" /> <label for=""><?php _e( 'Custom Fields', 'mycred' ); ?></label>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'public' ); ?>"><?php _e( 'Public', 'mycred' ); ?></label>
					<ol id="myCRED-rank-public">
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'public' ); ?>" id="<?php echo $this->field_id( 'public' ); ?>" <?php checked( $this->rank['public'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( 'public' ); ?>"><?php _e( 'If you want to create a template archive for each rank, you must select to have ranks public. Defaults to disabled.', 'mycred' ); ?></label>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'count' ); ?>"><?php _e( 'Rank Basis', 'mycred' ); ?></label>
					<ol id="myCRED-rank-basis">
						<li>
							<input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'current' ) ); ?>"<?php checked( $this->rank['base'], 'current' ); ?> value="current" /> <label for="<?php echo $this->field_id( array( 'base' => 'current' ) ); ?>"><?php _e( 'Users are ranked according to their current balance.', 'mycred' ); ?></label>
						</li>
						<li>
							<input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'total' ) ); ?>"<?php checked( $this->rank['base'], 'total' ); ?> value="total" /> <label for="<?php echo $this->field_id( array( 'base' => 'total' ) ); ?>"><?php echo $this->core->template_tags_general( __( 'Users are ranked according to the total amount of %_plural% they have accumulated.', 'mycred' ) ); ?></label>
						</li>
					</ol>
					<div id="calc-total" style="<?php echo $box; ?>">
						<label class="subheader" for=""><?php _e( 'Calculate Totals', 'mycred' ); ?></label>
						<ol id="mycred-rank-calculate">
							<li>
								<p><?php _e( 'Use this button to calculate or re-calcualte your users totals. If not used, the users current balance will be used as a starting point.', 'mycred' ); ?><br /><?php _e( 'Once a users total has been calculated, they will be assigned to their appropriate roles. For this reason, it is highly recommended that you first setup your ranks!', 'mycred' ); ?></p>
								<p><strong><?php _e( 'Depending on your log size and number of users this process may take a while. Please do not leave, click "Update Settings" or re-fresh this page until this is completed!', 'mycred' ); ?></strong></p>
								<input type="button" name="mycred-update-totals" id="mycred-update-totals" value="<?php _e( 'Calculate Totals', 'mycred' ); ?>" class="button button-large button-<?php if ( $this->rank['base'] == 'current' ) echo 'secondary'; else echo 'primary'; ?>"<?php if ( $this->rank['base'] == 'current' ) echo ' disabled="disabled"'; ?> />
							</li>
						</ol>
					</div>
					<label class="subheader" for="<?php echo $this->field_id( 'slug' ); ?>"><?php _e( 'Archive URL', 'mycred' ); ?></label>
					<ol id="">
						<li>
							<div class="h2"><?php bloginfo( 'url' ); ?>/ <input type="text" name="<?php echo $this->field_name( 'slug' ); ?>" id="<?php echo $this->field_id( 'slug' ); ?>" value="<?php echo $this->rank['slug']; ?>" size="20" />/</div>
							<span class="description"><?php _e( 'Ignored if Ranks are not public', 'mycred' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'order' ); ?>"><?php _e( 'Display Order', 'mycred' ); ?></label>
					<ol id="myCRED-rank-order">
						<li>
							<select name="<?php echo $this->field_name( 'order' ); ?>" id="<?php echo $this->field_id( 'order' ); ?>">
								<?php
			// Order added in 1.1.1
			$options = array(
				'ASC'  => __( 'Ascending - Lowest rank to highest', 'mycred' ),
				'DESC' => __( 'Descending - Highest rank to lowest', 'mycred' )
			);
			foreach ( $options as $option_value => $option_label ) {
				echo '<option value="' . $option_value . '"';
				if ( $this->rank['order'] == $option_value ) echo ' selected="selected"';
				echo '>' . $option_label . '</option>';
			} ?>

							</select><br />
							<span class="description"><?php _e( 'Select in what order ranks should be displayed in your admin area and/or front if ranks are "Public"', 'mycred' ); ?></span>
						</li>
					</ol>
<?php
			// If BuddyPress is installed
			if ( function_exists( 'bp_displayed_user_id' ) ) {
				if ( !isset( $this->rank['bb_location'] ) )
					$this->rank['bb_location'] = '';

				$rank_locations = array(
					''            => __( 'Do not show.', 'mycred' ),
					'top'         => __( 'Include in Profile Header.', 'mycred' ),
					'profile_tab' => __( 'Include under the "Profile" tab', 'mycred' ),
					'both'        => __( 'Include under the "Profile" tab and Profile Header.', 'mycred' )
				); ?>

					<label class="subheader" for="<?php echo $this->field_id( 'bb_location' ); ?>"><?php _e( 'Rank in BuddyPress', 'mycred' ); ?></label>
					<ol id="myCRED-rank-bb-location">
						<li>
							<select name="<?php echo $this->field_name( 'bb_location' ); ?>" id="<?php echo $this->field_id( 'bb_location' ); ?>">
								<?php
				// Loop though locations
				foreach ( $rank_locations as $value => $label ) {
					echo '<option value="' . $value . '"';
					if ( $this->rank['bb_location'] == $value ) echo ' selected="selected"';
					echo '>' . $label . '</option>';
				
				} ?>

							</select>
						</li>
					</ol>
<?php		}
			else {
				echo '<input type="hidden" name="' . $this->field_name( 'bb_location' ) . '" value="" />';
			} ?>

<script type="text/javascript">
jQuery(function($){
	$('input[name="<?php echo $this->field_name( 'base' ); ?>"]').change(function(){
		var basis = $(this).val();
		var button = $('#mycred-update-totals');
		// Update
		if ( basis != 'total' ) {
			$("#calc-total").hide();
			button.attr( 'disabled', 'disabled' );
			button.removeClass( 'button-primary' );
			button.addClass( 'button-seconday' );
		}
		else {
			$("#calc-total").show();
			button.removeAttr( 'disabled' );
			button.removeClass( 'button-seconday' );
			button.addClass( 'button-primary' );
		}
	});

	var mycred_calc = function( button ) {
		$.ajax({
			type : "POST",
			data : {
				action    : 'mycred-calc-totals',
				token     : '<?php echo wp_create_nonce( 'mycred-calc-totals' ); ?>'
			},
			dataType : "JSON",
			url : '<?php echo admin_url( 'admin-ajax.php' ); ?>',
			// Before we start
			beforeSend : function() {
				button.attr( 'disabled', 'disabled' );
				button.removeClass( 'button-primary' );
				button.addClass( 'button-seconday' );
				button.val( '<?php echo __( 'Processing...', 'mycred' ); ?>' );
			},
			// On Successful Communication
			success    : function( data ) {
				button.val( data );
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug - uncomment to use
				console.log( jqXHR );
				button.removeAttr( 'disabled' );
				button.removeClass( 'button-seconday' );
				button.addClass( 'button-primary' );
				button.val( '<?php echo __( 'Script Communication Error', 'mycred' ); ?>' );
			}
		});
	};

	$('input#mycred-update-totals').click(function(){
		mycred_calc( $(this) );
	});
});
</script>
				</div>
<?php
		}

		/**
		 * Save Settings
		 * @since 1.1
		 * @version 1.2
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			$new_data['rank']['support']['content'] = ( isset( $data['rank']['support']['content'] ) ) ? true : false;
			$new_data['rank']['support']['excerpt'] = ( isset( $data['rank']['support']['excerpt'] ) ) ? true : false;
			$new_data['rank']['support']['comments'] = ( isset( $data['rank']['support']['comments'] ) ) ? true : false;
			$new_data['rank']['support']['page-attributes'] = ( isset( $data['rank']['support']['page-attributes'] ) ) ? true : false;
			$new_data['rank']['support']['custom-fields'] = ( isset( $data['rank']['support']['custom-fields'] ) ) ? true : false;

			$new_data['rank']['base'] = sanitize_text_field( $data['rank']['base'] );
			$new_data['rank']['public'] = ( isset( $data['rank']['public'] ) ) ? true : false;
			$new_data['rank']['slug'] = sanitize_text_field( $data['rank']['slug'] );
			$new_data['rank']['order'] = sanitize_text_field( $data['rank']['order'] );
			$new_data['rank']['bb_location'] = sanitize_text_field( $data['rank']['bb_location'] );
			return $new_data;
		}

		/**
		 * Management
		 * @since 1.3.2
		 * @version 1.0
		 */
		public function rank_management() {
			$data = mycred_get_published_ranks();
			
			$reset_block = false;
			if ( $data == 0 || $data === false )
				$reset_block = true; ?>

		<label class="subheader"><?php _e( 'Ranks', 'mycred' ); ?></label>
		<ol id="myCRED-rank-actions" class="inline">
			<li>
				<label><?php _e( 'Rank Post Type', 'mycred' ); ?></label>
				<div class="h2"><input type="text" id="mycred-rank-post-type" disabled="disabled" value="mycred_rank" class="readonly" /></div>
			</li>
			<li>
				<label><?php _e( 'No. of ranks', 'mycred' ); ?></label>
				<div class="h2"><input type="text" id="mycred-ranks-no-of-ranks" disabled="disabled" value="<?php echo $data; ?>" class="readonly short" /></div>
			</li>
			<li>
				<label><?php _e( 'Actions', 'mycred' ); ?></label>
				<div class="h2"><input type="button" id="mycred-manage-action-reset-ranks" value="<?php _e( 'Remove All Ranks', 'mycred' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; else echo 'button-primary'; ?>" /> <input type="button" id="mycred-manage-action-assign-ranks" value="<?php _e( 'Assign Ranks to Users', 'mycred' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; ?>" /></div>
			</li>
		</ol>
<?php
		}
	}
	$rank = new myCRED_Ranks();
	$rank->load();
}
?>