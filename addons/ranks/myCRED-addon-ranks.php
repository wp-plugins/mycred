<?php
/**
 * Addon: Ranks
 * Addon URI: http://mycred.me/add-ons/email-notices/
 * Version: 1.0
 * Description: Create ranks for users reaching a certain number of %_plural% with the option to add logos for each rank. 
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
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
 * @version 1.0
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
					'slug'        => 'mycred_rank',
					'bb_location' => 'top'
				),
				'register'    => false,
				'add_to_core' => true
			) );

			add_action( 'mycred_help',            array( $this, 'help' ), 10, 2 );
			add_action( 'mycred_parse_tags_user', array( $this, 'parse_rank' ), 10, 3 );
		}
		
		/**
		 * Hook into Init
		 * @since 1.1
		 * @version 1.0
		 */
		public function module_init() {
			$this->register_post_type();
			if ( !mycred_have_ranks() )
				$this->add_default_rank();

			add_action( 'mycred_admin_enqueue',   array( $this, 'enqueue_scripts' )           );
			add_action( 'transition_post_status', array( $this, 'publishing_content' ), 10, 3 );
			add_filter( 'mycred_add',             array( $this, 'check_for_rank' ), 10, 3     );
			
			// BuddyPress
			if ( function_exists( 'bp_displayed_user_id' ) && isset( $this->rank['bb_location'] ) && !empty( $this->rank['bb_location'] ) ) {
				if ( $this->rank['bb_location'] == 'top' || $this->rank['bb_location'] == 'both' )
					add_action( 'bp_before_member_header_meta', array( $this, 'insert_rank_header' ) );
				
				if ( $this->rank['bb_location'] == 'profile_tab' || $this->rank['bb_location'] == 'both' )
					add_action( 'bp_profile_field_item',        array( $this, 'insert_rank_profile' ) );
			}
			
			// Shortcodes
			add_shortcode( 'mycred_my_rank',            'mycred_render_my_rank' );
			add_shortcode( 'mycred_users_of_rank',      'mycred_render_users_of_rank' );
			add_shortcode( 'mycred_users_of_all_ranks', 'mycred_render_users_of_all_ranks' );
		}
		
		/**
		 * Hook into Admin Init
		 * @since 1.1
		 * @version 1.0
		 */
		public function module_admin_init() {
			add_filter( 'manage_mycred_rank_posts_columns',       array( $this, 'adjust_column_headers' )        );
			add_action( 'manage_mycred_rank_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );

			add_filter( 'post_row_actions',      array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',      array( $this, 'enter_title_here' )      );

			add_action( 'add_meta_boxes',        array( $this, 'add_meta_boxes' )        );
			add_action( 'save_post',             array( $this, 'save_rank_settings' )    );
		}
		
		/**
		 * Enqueue Scripts & Styles
		 * @since 1.1
		 * @version 1.0
		 */
		public function enqueue_scripts() {
			$screen = get_current_screen();
			// Commonly used
			if ( $screen->id == 'edit-mycred_rank' ) {
				wp_enqueue_style( 'mycred-admin' );
			}
			elseif ( $screen->id == 'mycred_rank' ) {
				wp_enqueue_style( 'mycred-admin' );
				wp_dequeue_script( 'autosave' );
			}
		}
		
		/**
		 * Register Rank Post Type
		 * @since 1.1
		 * @version 1.0
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
			$args = array(
				'labels'             => $labels,
				'public'             => (bool) $this->rank['public'],
				'publicly_queryable' => (bool) $this->rank['public'],
				'show_ui'            => true, 
				'show_in_menu'       => 'myCRED',
				'capability_type'    => 'page',
				'supports'           => array( 'title', 'thumbnail' )
			);
			
			if ( $this->rank['public'] ) {
				$args['rewrite'] = array( 'slug' => $this->rank['slug'] );
			}
			register_post_type( 'mycred_rank', $args );
		}
		
		/**
		 * Find Users Ranks
		 * When a rank is published we run though all users to allowcate them to
		 * the appropriate rank.
		 * @since 1.1
		 * @version 1.0
		 */
		public function publishing_content( $new_status, $old_status, $post ) {
			// Only ranks please
			if ( $post->post_type != 'mycred_rank' ) return;
			
			// Check for ranks that are getting published
			$status = apply_filters( 'mycred_publish_hook_old', array( 'new', 'auto-draft', 'draft', 'private', 'pending', 'scheduled' ) );
			if ( in_array( $old_status, $status ) && $new_status == 'publish' ) {
				// Run though all users and find their rank
				$mycred = mycred_get_settings();
				$args = array();
		
				// In case we have an exclude list
				if ( isset( $mycred->exclude['list'] ) && !empty( $mycred->exclude['list'] ) )
					$args['exclude'] = explode( ',', $mycred->exclude['list'] );
		
				$users = get_users( $args );
				$rank_users = array();
				if ( $users ) {
					foreach ( $users as $user ) {
						// The above exclude list will not take into account
						// if admins are excluded. For this reason we need to run
						// this check again to avoid including them in this list.
						if ( $mycred->exclude_user( $user->ID ) ) continue;
						// Find users rank
						mycred_find_users_rank( $user->ID, true );
					}
				}
			}
		}
		
		/**
		 * Check For Rank
		 * Each time a users balance changes we check if this effects their ranking.
		 * @since 1.1
		 * @version 1.0
		 */
		public function check_for_rank( $reply, $request, $mycred ) {
			mycred_find_users_rank( $request['user_id'], true );
			return $reply;
		}
		
		/**
		 * Parse Rank
		 * Parses the %rank% and %rank_logo% template tags.
		 * @since 1.1
		 * @version 1.0
		 */
		public function parse_rank( $content, $user = '', $data = '' ) {
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
		 * @version 1.0
		 */
		public function insert_rank_profile() {
			$user_id = bp_displayed_user_id();
			if ( $this->core->exclude_user( $user_id ) ) return;
			$rank_name = mycred_get_users_rank( $user_id ); ?>

	<tr id="mycred-users-rank">
		<td class="label"><?php _e( 'Rank', 'mycred' ); ?></td>
		<td class="data">
			<?php echo $rank_name . ' ' . mycred_get_rank_logo( $rank_name ); ?>

		</td>
	</tr>
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
		 * @version 1.0
		 */
		public function add_default_rank() {
			$rank = array();
			$rank['post_title'] = __( 'Newbie', 'mycred' );
			$rank['post_type'] = 'mycred_rank';
			$rank['post_status'] = 'publish';
			
			$rank_id = wp_insert_post( $rank );
			
			update_post_meta( $rank_id, 'mycred_rank_min', 0 );
			update_post_meta( $rank_id, 'mycred_rank_max', 9999999 );
			
			$args = array();
			if ( isset( $this->core->exclude['list'] ) && !empty( $this->core->exclude['list'] ) )
				$args['exclude'] = explode( ',', $this->core->exclude['list'] );
			
			$users = get_users( $args );
			if ( $users ) {
				foreach ( $users as $user ) {
					update_user_meta( $user->ID, 'mycred_rank', $rank_id );
				}
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
				10 => __( '', 'mycred' )
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
		 * Adjust Column Header
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
		 * Adjust Column Content
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
				foreach ( $all as $rank_id => $rank_title ) {
					$_min = get_post_meta( $rank_id, 'mycred_rank_min', true );
					if ( empty( $_min ) && (int) $_min !== 0 ) $_min = __( 'Not Set', 'mycred' );
					$_max = get_post_meta( $rank_id, 'mycred_rank_max', true );
					if ( empty( $_max ) ) $_max = __( 'Not Set', 'mycred' );
					echo '<p><strong style="display:inline-block;width:20%;">' . $rank_title . '</strong> ' . $_min . ' - ' . $_max . '</p>';
				}
			}
			else {
				echo '<p>' . __( 'No Ranks found', 'mycred' ) . '.</p>';
			}
		?>
	
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
				$max = 9999999;
			else
				$max = trim( $_POST['mycred_rank']['max'] );
			
			update_post_meta( $post_id, 'mycred_rank_min', $min );
			update_post_meta( $post_id, 'mycred_rank_max', $max );
		}
		
		/**
		 * Add to General Settings
		 * @since 1.1
		 * @version 1.0
		 */
		public function after_general_settings() { ?>

				<h4 style="color:#BBD865;"><?php _e( 'Ranks', 'mycred' ); ?></h4>
				<div class="body" style="display:none;">
					<label class="subheader" for="<?php echo $this->field_id( 'public' ); ?>"><?php _e( 'Public', 'mycred' ); ?></label>
					<ol id="myCRED-email-notice-allow-filters">
						<li>
							<input type="checkbox" name="<?php echo $this->field_name( 'public' ); ?>" id="<?php echo $this->field_id( 'public' ); ?>" <?php checked( $this->rank['public'], 1 ); ?> value="1" />
							<label for="<?php echo $this->field_id( 'public' ); ?>"><?php _e( 'If you want to create a template archive for each rank, you must select to have ranks public. Defaults to disabled.', 'mycred' ); ?></label>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<label for="<?php echo $this->field_id( 'slug' ); ?>"><?php _e( 'Rank Post Type URL Slug', 'mycred' ); ?></label>
							<div class="h2"><?php bloginfo( 'url' ); ?>/ <input type="text" name="<?php echo $this->field_name( 'slug' ); ?>" id="<?php echo $this->field_id( 'slug' ); ?>" value="<?php echo $this->rank['slug']; ?>" size="20" />/</div>
							<span class="description"><?php _e( 'If you are using a custom permalink structure and you make ranks public or change the slug, you will need to visit your permalink settings page and click "Save Changes" to flush your re-write rules! Otherwise you will get a 404 error message when trying to view a rank archive page.', 'mycred' ); ?></span>
						</li>
						<li class="empty">&nbsp;</li>
						<li>
							<p><?php echo sprintf( __( 'For more information on Templates for Custom Post Types visit the <a href="%s">WordPress Codex</a>.', 'mycred' ), 'http://codex.wordpress.org/Post_Types#Custom_Post_Types' ); ?></p>
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
					<ol id="myCRED-email-notice-buddypress-location">
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

				</div>
<?php
		}
		
		/**
		 * Save Settings
		 * @since 1.1
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			$new_data['rank']['public'] = ( isset( $data['rank']['public'] ) ) ? true : false;
			$new_data['rank']['slug'] = sanitize_text_field( $data['rank']['slug'] );
			$new_data['rank']['bb_location'] = sanitize_text_field( $data['rank']['bb_location'] );
			return $new_data;
		}
		
		/**
		 * Help
		 * @since 1.1
		 * @version 1.0
		 */
		public function help( $screen_id, $screen ) {
			if ( $screen_id == 'mycred_page_myCRED_page_settings' ) {
				$screen->add_help_tab( array(
					'id'		=> 'mycred-rank',
					'title'		=> __( 'Ranks', 'mycred' ),
					'content'	=> '
<p>' . __( 'You can create ranks according to the amount of points a user has. By default, ranks are only visible in widgets and shortcodes however it is possible for you to also create archive pages in your theme for all ranks or specific ones.', 'mycred' ) . '</p>
<p><strong>' . __( 'Templates', 'mycred' ) . '</strong></p>
<p>' . __( 'Ranks are just another custom post type which means that you can, if you select to make Ranks Public, create custom template files for ranks in your theme folder.', 'mycred' ) . '</p>
<p>' . sprintf( __( 'For more information on Templates for Custom Post Types visit the <a href="%s">WordPress Codex</a>.', 'mycred' ), 'http://codex.wordpress.org/Post_Types#Custom_Post_Types' ) . '</p>
<p><strong>' . __( 'Changing URL Slug', 'mycred' ) . '</strong></p>
<p>' . __( 'You can change the URL slug used for ranks to any URL friendly value.', 'mycred' ) . '</p>
<p>' . __( 'If you are using a custom permalink structure and you make ranks public or change the slug, you will need to visit your permalink settings page and click "Save Changes" to flush your re-write rules! Otherwise you will get a 404 error message when trying to view a rank archive page.', 'mycred' ) . '</span></p>'
				) );
			}
			
		}
	}
	$rank = new myCRED_Ranks();
	$rank->load();
}
?>