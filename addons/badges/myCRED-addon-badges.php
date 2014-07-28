<?php
/**
 * Addon: Badges
 * Addon URI: http://mycred.me/add-ons/badges/
 * Version: 1.0
 * Description: Give your users badges based on their interaction with your website.
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_BADGE',         __FILE__ );
define( 'myCRED_BADGE_DIR',     myCRED_ADDONS_DIR . 'badges/' );
define( 'myCRED_BADGE_VERSION', myCRED_VERSION . '.1' );

include_once( myCRED_BADGE_DIR . 'includes/mycred-badge-functions.php' );
include_once( myCRED_BADGE_DIR . 'includes/mycred-badge-shortcodes.php' );

/**
 * myCRED_buyCRED_Module class
 * @since 1.5
 * @version 1.0
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
					'buddypress' => '',
					'bbpress'    => ''
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
			add_filter( 'mycred_add', array( $this, 'mycred_add' ), 99999, 3 );
		}

		/**
		 * Module Init
		 * @version 1.0
		 */
		public function module_init() {
			$this->register_post_type();
			add_shortcode( 'mycred_my_badges', 'mycred_render_my_badges' );
			add_shortcode( 'mycred_badges',    'mycred_render_badges' );

			// Insert into bbPress
			if ( class_exists( 'bbPress' ) ) {
				if ( $this->badges['bbpress'] == 'profile' || $this->badges['bbpress'] == 'both' )
					add_action( 'bbp_template_after_user_profile', array( $this, 'insert_into_bbpress_profile' ) );
				elseif ( $this->badges['bbpress'] == 'reply' || $this->badges['bbpress'] == 'both' )
					add_action( 'bbp_theme_after_reply_author_details', array( $this, 'insert_into_bbpress_reply' ) );
			}

			// Insert into BuddyPress
			if ( class_exists( 'BuddyPress' ) ) {
				// Insert into header
				if ( $this->badges['buddypress'] == 'header' || $this->badges['buddypress'] == 'both' )
					add_action( 'bp_before_member_header_meta', array( $this, 'insert_into_buddypress' ) );
				// Insert into profile
				elseif ( $this->badges['buddypress'] == 'profile' || $this->badges['buddypress'] == 'both' )
					add_action( 'bp_after_profile_loop_content', array( $this, 'insert_into_buddypress' ) );
			}

		}

		/**
		 * Module Admin Init
		 * @version 1.0
		 */
		public function module_admin_init() {
			add_action( 'mycred_admin_enqueue',   array( $this, 'enqueue_scripts' ) );

			add_filter( 'manage_mycred_badge_posts_columns',       array( $this, 'adjust_column_headers' ) );
			add_action( 'manage_mycred_badge_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );

			add_filter( 'post_row_actions',           array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'post_updated_messages',      array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',           array( $this, 'enter_title_here' ) );

			add_action( 'add_meta_boxes_mycred_badge', array( $this, 'add_meta_boxes' ) );
			add_action( 'post_submitbox_start',        array( $this, 'publishing_actions' ) );
			add_action( 'save_post',                   array( $this, 'save_badge_post' ) );

			add_action( 'wp_ajax_mycred-assign-badge',       array( $this, 'action_assign_badge' ) );
			add_action( 'wp_ajax_mycred-remove-connections', array( $this, 'action_remove_connections' ) );
		}

		/**
		 * AJAX: Assign Badge
		 * @version 1.0
		 */
		public function action_assign_badge() {
			check_ajax_referer( 'mycred-assign-badge', 'token' );

			$badge_id = absint( $_POST['badge_id'] );
			$requirements = mycred_get_badge_requirements( $badge_id );
			if ( empty( $requirements ) )
				wp_send_json_error( 'This badge has no requirements set!' );

			$needs = $requirements[0];
			$mycred = mycred( $needs['type'] );
			$mycred_log = $mycred->log_table;

			global $wpdb;

			$sql = "
			SELECT user_id 
			FROM {$mycred_log} 
			WHERE " . $wpdb->prepare( "ctype = %s AND ref = %s ", $needs['type'], $needs['reference'] );

			$sql .= " GROUP by user_id ";

			$amount = $needs['amount'];
			if ( $needs['by'] == 'count' )
				$sql .= "HAVING COUNT( id ) >= {$amount}";
			else
				$sql .= "HAVING SUM( creds ) >= {$amount}";

			// Let others play
			$users = $wpdb->get_col( apply_filters( 'mycred_assign_badge_sql', $sql, $badge_id ) );

			// Empty results = no one has earned this badge yet
			if ( empty( $users ) )
				wp_send_json_error( __( 'No users has yet earned this badge.', 'mycred' ) );

			// Assign badge
			foreach ( $users as $user_id )
				mycred_update_user_meta( $user_id, 'mycred_badge' . $badge_id, '', apply_filters( 'mycred_badge_user_value', 1, $user_id, $badge_id ) );

			wp_send_json_success( sprintf( __( '%d Users earned this badge.', 'mycred' ), count( $users ) ) );
		}

		/**
		 * AJAX: Remove Badge Connections
		 * @version 1.0
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

			wp_send_json_success( sprintf( __( '%s connections where removed.', 'mycred' ), $count ) );
		}

		public function insert_into_bbpress_profile() {
			$user_id = bbp_get_displayed_user_id();
			mycred_display_users_badges( $user_id );
		}

		public function insert_into_bbpress_reply() {
			$user_id = bbp_reply_author_id();
			mycred_display_users_badges( $user_id );
		}

		public function insert_into_buddypress() {
			$user_id = bp_displayed_user_id();
			mycred_display_users_badges( $user_id );
		}

		/**
		 * myCRED Add
		 * @version 1.0
		 */
		public function mycred_add( $reply, $request, $mycred ) {
			// Declined
			if ( $reply === false ) return $reply;

			extract( $request );

			// Check if this reference has badges
			$badge_ids = mycred_ref_has_badge( $ref );
			if ( $badge_ids === false ) return $reply;

			// Indicate the mycred_check_if_user_gets_badge function that
			// the log entry for this already exists.
			if ( $reply == 'done' ) {
				$request['done'] = true;
			}

			// Assign Badges
			mycred_check_if_user_gets_badge( $user_id, $request, $badge_ids );

			return $reply;
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
			
			if ( $screen->id == 'mycred_badge' ) {
				 wp_enqueue_media();
			}
			elseif ( $screen->id == 'edit-mycred_badge' ) {
				wp_enqueue_style( 'mycred-badge-admin', plugins_url( 'assets/css/admin.css', myCRED_BADGE ) );
			}
		}

		/**
		 * Adjust Badge Column Header
		 * @version 1.0
		 */
		public function adjust_column_headers( $defaults ) {
			// Remove
			unset( $defaults['date'] );

			// Add / Adjust
			$defaults['title']               = __( 'Badge Name', 'mycred' );
			$defaults['badge-default-image'] = __( 'Badge Images', 'mycred' );
			$defaults['badge-reqs']          = __( 'Requirements', 'mycred' );
			$defaults['badge-users']         = __( 'Users', 'mycred' );

			// Return
			return $defaults;
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
					echo '<img src="' . $default_image . '" style="max-width: 100px;height: auto;" alt="" />';

				$main_image = get_post_meta( $post_id, 'main_image', true );
				if ( $main_image != '' )
					echo '<img src="' . $main_image . '" style="max-width: 100px;height: auto;" alt="" />';

				if ( $default_image == '' && $main_image == '' )
					echo '-';
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
				0 => '',
				1 => __( 'Badge Updated.', 'mycred' ),
				2 => '',
				3 => '',
				4 => __( 'Badge Updated.', 'mycred' ),
				5 => false,
				6 => __( 'Badge Enabled', 'mycred' ),
				7 => __( 'Badge Saved', 'mycred' ),
				8 => __( 'Badge Updated.', 'mycred' ),
				9 => __( 'Badge Updated.', 'mycred' ),
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
				__( 'Requirements', 'mycred' ),
				array( $this, 'metabox_badge_requirements' ),
				'mycred_badge',
				'normal',
				'high'
			);

			add_meta_box(
				'mycred_badge_images',
				__( 'Badge Images', 'mycred' ),
				array( $this, 'metabox_badge_images' ),
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
				button.attr( 'value', '<?php _e( 'Processing...', 'mycred' ); ?>' );
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
		 * @version 1.0
		 */
		public function metabox_badge_requirements( $post ) {
			$requirements = mycred_get_badge_requirements( $post->ID, true );

			$types = mycred_get_types();
			$references = mycred_get_all_references();
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
table#setup-badge-reqs td.type { width: 25%; }
table#setup-badge-reqs td.for { width: 5%; }
table#setup-badge-reqs td.reference { width: 50%; }
table#setup-badge-reqs td.amount { width: 10%; }
table#setup-badge-reqs td.sum { width: 10%; }
p.actions { text-align: right; }
</style>
<script type="text/javascript">
jQuery(function($) {
	$( '#postimagediv h3.hndle span' ).empty().text( '<?php _e( 'Badge Image', 'mycred' ); ?>' );
	$( '#postimagediv div.inside p a' ).attr( 'title', '<?php _e( 'Set badge image', 'mycred' ); ?>' ).empty().text( '<?php _e( 'Set badge image', 'mycred' ); ?>' );
});
</script>
<p>To earn this badge, a user must have received:</p>
<table class="table" style="width: 100%;" id="setup-badge-reqs">

	<?php do_action( 'mycred_edit_badge_before_req', $post ); ?>

<?php

			foreach ( $requirements as $row => $needs ) {

				if ( ! isset( $needs['by'] ) )
					$needs = array(
						'type'      => '',
						'reference' => '',
						'amount'    => '',
						'by'        => ''
					);

?>
	<tr class="badge-requires" id="badge-requirement-<?php echo $row; ?>">
		<td class="type">
			<?php mycred_types_select_from_dropdown( 'mycred_badge[req][' . $row . '][type]', '', $needs['type'] ); ?>
		</td>
		<td class="for">for</td>
		<td class="reference">
			<select name="mycred_badge[req][<?php echo $row; ?>][reference]" id=""><?php

	
				foreach ( $references as $ref => $label ) {
					echo '<option value="' . $ref . '"';
					if ( $needs['reference'] == $ref ) echo ' selected="selected"';
					echo '>' . $label . '</option>';
				}

?></select>
		</td>
		<td class="amount">
			<input type="text" size="8" name="mycred_badge[req][<?php echo $row; ?>][amount]" id="" value="<?php echo $needs['amount']; ?>" />
		</td>
		<td class="sum">
			<select name="mycred_badge[req][<?php echo $row; ?>][by]" id=""><?php

				foreach ( $sums as $sum => $label ) {
					echo '<option value="' . $sum . '"';
					if ( $needs['by'] == $sum ) echo ' selected="selected"';
					echo '>' . $label . '</option>';
				}

?></select>
		</td>
	</tr>
<?php

			}

?>

	<?php do_action( 'mycred_edit_badge_after_req', $post ); ?>

</table>
<?php
		}

		/**
		 * Badge Variations Metabox
		 * @version 1.0
		 */
		public function metabox_badge_images( $post ) {
			$default_image = get_post_meta( $post->ID, 'default_image', true ); 
			$main_image = get_post_meta( $post->ID, 'main_image', true ); ?>

<style type="text/css">
#mycred_badge_images .inside, #mycred_badge_images .inside p { margin: 0; padding: 0; }
#image-wrapper { float: none; clear: both; min-height: 100px; }
#image-wrapper #main-image { border-bottom: none; }
#image-wrapper > div.inner-box { float: none; clear: both; min-height: 50px; margin: 0; padding: 0; border-bottom: 1px solid #eee; }
#image-wrapper > div.inner-box .thumb { display: block; width: 100px; height: 100px; float: left; border-right: 1px solid #eee; padding: 12px; }
#image-wrapper > div.inner-box .thumb img { max-width: 100px; height: auto; margin: 0 auto; }
#image-wrapper > div.inner-box .thumb p { line-height: 100px; text-align: center; color: #ccc; }
#image-wrapper > div.inner-box .details { margin-left: 125px; padding: 12px; }
#image-wrapper > div.inner-box .details p strong { display: block; }
#image-wrapper > div.inner-box .details p.desc { margin-bottom: 12px; }
</style>
<div id="image-wrapper">

	<?php do_action( 'mycred_edit_badge_before_images', $post ); ?>

	<div id="default-image" class="inner-box">
		<div class="thumb">
			<?php if ( $default_image == '' ) : ?>
			<p><?php _e( 'no image', 'mycred' ); ?></p>
			<?php else : ?>
			<img src="<?php echo $default_image; ?>" alt="" />
			<?php endif; ?>
		</div>
		<div class="details">
			<p class="desc">
				<strong><?php _e( 'Default Image', 'mycred' ); ?></strong>
				<span class="description"><?php _e( 'Option to show a default image if the user has not yet earned this badge. Leave empty if not used.', 'mycred' ); ?></span>
			</p>
			<p>
				<input type="text" name="mycred_badge[default_image]" placeholder="<?php _e( 'image url', 'mycred' ); ?>" id="mycred-default-image-url" class="regular-text" size="30" value="<?php echo $default_image; ?>" /> 
				<input type="button" data-target="default-image" id="mycred-add-default-image" class="button button-primary mycred-badge-load-image" value="Add Image" />
			</p>
		</div>
		<div class="clear clearfix"></div>
	</div>
	<div id="main-image" class="inner-box">
		<div class="thumb">
			<?php if ( $main_image == '' ) : ?>
			<p><?php _e( 'no image', 'mycred' ); ?></p>
			<?php else : ?>
			<img src="<?php echo $main_image; ?>" alt="" />
			<?php endif; ?>
		</div>
		<div class="details">
			<p class="desc">
				<strong><?php _e( 'Main Image', 'mycred' ); ?></strong>
				<span class="description"><?php _e( 'Image to show when the user has earned this badge.', 'mycred' ); ?></span>
			</p>
			<p>
				<input type="text" name="mycred_badge[main_image]" placeholder="<?php _e( 'image url', 'mycred' ); ?>" id="mycred-main-image-url" class="regular-text" size="30" value="<?php echo $main_image; ?>" /> 
				<input type="button" data-target="main-image" id="mycred-add-main-image" class="button button-primary mycred-badge-load-image" value="Add Image" />
			</p>
		</div>
		<div class="clear clearfix"></div>
	</div>

	<?php do_action( 'mycred_edit_badge_after_images', $post ); ?>

	<div class="clear clearfix"></div>
</div>
<script type="text/javascript">
jQuery(function($) {

	var custom_uploader;

	$( 'input.mycred-badge-load-image' ).unbind('click').click(function( e ){
		e.preventDefault();

		var formfield = $(this).attr( 'data-target' );

		//Extend the wp.media object
        custom_uploader = wp.media.frames.file_frame = wp.media({
            title: '<?php _e( 'Badge Image', 'mycred' ); ?>',
            button: {
                text: '<?php _e( 'Use as Badge', 'mycred' ); ?>'
            },
            multiple: false
        });
 
        //When a file is selected, grab the URL and set it as the text field's value
        custom_uploader.on('select', function() {
            attachment = custom_uploader.state().get('selection').first().toJSON();
            $( '#mycred-' + formfield + '-url' ).val( attachment.url );
            $( '#' + formfield + ' .thumb' ).empty().append( "<img class='show-selected-image' src='" + attachment.url + "' alt='' />" );
        });
 
        //Open the uploader dialog
        custom_uploader.open();

	});

});
</script>
<?php
		}

		/**
		 * Save Badge Details
		 * @version 1.0
		 */
		public function save_badge_post( $post_id ) {

			// Make sure this is for badges
			if ( ! isset( $_POST['mycred_badge'] ) || ! isset( $_POST['mycred-badge-edit'] ) || ! wp_verify_nonce( $_POST['mycred-badge-edit'], 'edit-mycred-badge' ) ) return;

			// Requirements
			update_post_meta( $post_id, 'badge_requirements', $_POST['mycred_badge']['req'] );

			// Default Image
			$default_image = sanitize_text_field( $_POST['mycred_badge']['default_image'] );
			update_post_meta( $post_id, 'default_image', $default_image );

			// Main Image
			$main_image = sanitize_text_field( $_POST['mycred_badge']['main_image'] );
			update_post_meta( $post_id, 'main_image', $main_image );

			// Let others play
			do_action( 'mycred_save_badge', $post_id );
		}

		/**
		 * Add to General Settings
		 * @version 1.0
		 */
		public function after_general_settings() {
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
			$new_data['badges']['buddypress'] = sanitize_text_field( $data['badges']['buddypress'] );
			$new_data['badges']['bbpress'] = sanitize_text_field( $data['badges']['bbpress'] );

			return $new_data;
		}

	}

	$badge = new myCRED_Badge_Module();
	$badge->load();
}
?>