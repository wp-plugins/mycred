<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Settings class
 * @see http://mycred.merovingi.com/classes/mycred_settings/
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'myCRED_Settings' ) ) {
	class myCRED_Settings {

		public $core;

		/**
		 * Construct
		 */
		function __construct() {
			if ( mycred_overwrite() === false )
				$this->core = get_option( 'mycred_pref_core' );
			else
				$this->core = get_blog_option( 1, 'mycred_pref_core' );

			if ( $this->core !== false ) {
				foreach ( (array) $this->core as $key => $value ) {
					$this->$key = $value;
				}
			}
		}

		/**
		 * Singular myCRED name
		 * @since 0.1
		 * @version 1.0
		 */
		public function singular() {
			return $this->name['singular'];
		}

		/**
		 * Plural myCRED name
		 * @since 0.1
		 * @version 1.0
		 */
		public function plural() {
			return $this->name['plural'];
		}

		/**
		 * Number
		 * Returns a given creds formated either as a float with the set number of decimals or as a integer.
		 * This function should be used when you need to make sure the variable is returned in correct format
		 * but without any custom layout you might have given your creds.
		 *
		 * @param $number (int|float) the initial number
		 * @returns the given number formated either as an integer or float
		 * @since 0.1
		 * @version 1.0
		 */
		public function number( $number = '' ) {
			if ( empty( $number ) ) return $number;

			if ( !isset( $this->format['decimals'] ) )
				$decimals = $this->core['format']['decimals'];
			else
				$decimals = $this->format['decimals'];

			if ( (int) $decimals > 0 ) {
				return (float) number_format( (float) $number, (int) $decimals, '.', '' );
			}
			else {
				return (int) $number;
			}
		}

		/**
		 * Format Number
		 * Returns a given creds formated with set decimal and thousands separator and either as a float with
		 * the set number of decimals or as a integer. This function should be used when you want to display creds
		 * formated according to your settings. Do not use this function when adding/removing points!
		 *
		 * @param $number (int|float) the initial number
		 * @returns the given number formated either as an integer or float
		 * @filter 'mycred_format_number'
		 * @since 0.1
		 * @version 1.0
		 */
		public function format_number( $number = '' ) {
			if ( empty( $number ) ) return $number;

			$number = $this->number( $number );
			$decimals = $this->format['decimals'];
			$sep_dec = $this->format['separators']['decimal'];
			$sep_tho = $this->format['separators']['thousand'];

			// Format
			$creds = number_format( $number, (int) $decimals, $sep_dec, $sep_tho );
			$creds = apply_filters( 'mycred_format_number', $creds, $number, $this->core );

			return $creds;
		}
		
		/**
		 * Format Creds
		 * Returns a given number formated with prefix and/or suffix along with any custom presentation set.
		 *
		 * @param $creds (int|float) number of creds
		 * @param $before (string) optional string to insert before the number
		 * @param $after (string) optional string to isnert after the number
		 * @param $force_in (boolean) option to force $before after prefix and $after before suffix
		 * @filter 'mycred_format_creds'
		 * @returns formated string
		 * @since 0.1
		 * @version 1.0
		 */
		public function format_creds( $creds = 0, $before = '', $after = '', $force_in = false ) {
			// Prefix
			$prefix = '';
			if ( !empty( $this->before ) )
				$prefix = $this->before . ' ';

			// Suffix
			$suffix = '';
			if ( !empty( $this->after ) )
				$suffix = ' ' . $this->after;

			// Format creds
			$creds = $this->format_number( $creds );

			// Optional extras to insert before and after
			if ( $force_in )
				$layout = $prefix . $before . $creds . $after . $suffix;
			else
				$layout = $before . $prefix . $creds . $suffix . $after;

			// Let others play
			$formated = apply_filters( 'mycred_format_creds', $layout, $creds, $this->core );

			return $formated;
		}

		/**
		 * Round Value
		 * Will round a given value either up or down with the option to use precision.
		 *
		 * @param $amount (int|float) required amount to round
		 * @param $up_down (string|boolean) choice of rounding up or down. using false bypasses this function
		 * @param $precision (int) the optional number of decimal digits to round to. defaults to 0
		 * @returns rounded int or float
		 * @since 0.1
		 * @version 1.0
		 */
		public function round_value( $amount = 0, $up_down = false, $precision = 0 ) {
			if ( $amount == 0 || !$up_down ) return $amount;

			// Use round() for precision
			if ( $precision !== false ) {
				if ( $up_down == 'up' )
					$amount = round( $amount, (int) $precision, PHP_ROUND_HALF_UP );
				elseif ( $up_down == 'down' )
					$amount = round( $amount, (int) $precision, PHP_ROUND_HALF_DOWN );
			}
			// Use ceil() or floor() for everything else
			else {
				if ( $up_down == 'up' )
					$amount = ceil( $amount );
				elseif ( $up_down == 'down' )
					$amount = floor( $amount );
			}
			return $amount;
		}

		/**
		 * Apply Exchange Rate
		 * Applies a given exchange rate to the given amount.
		 * 
		 * @param $amount (int|float) the initial amount
		 * @param $rate (int|float) the exchange rate to devide by
		 * @param $round (bool) option to round values, defaults to yes.
		 * @since 0.1
		 * @version 1.0
		 */
		public function apply_exchange_rate( $amount, $rate = 1, $round = true ) {
			$amount = $this->number( $amount );
			if ( $rate == 1 ) return $amount;

			$exchange = $amount/(float) $rate;
			if ( $round ) $exchange = round( $exchange );

			return $this->format_number( $exchange );
		}
		
		/**
		 * Parse Template Tags
		 * Parses template tags in a given string by checking for the 'ref_type' array key under $log_entry->data.
		 * @since 0.1
		 * @version 1.0
		 */
		public function parse_template_tags( $content, $log_entry ) {
			// Prep
			$reference = $log_entry->ref;
			$ref_id = $log_entry->ref_id;
			$data = $log_entry->data;

			// Unserialize if serialized
			$check = @unserialize( $data );
			if ( $check !== false && $data !== 'b:0;' )
				$data = unserialize( $data );

			// Run basic template tags first
			$content = $this->template_tags_general( $content );

			// Start by allowing others to play
			$content = apply_filters( 'mycred_parse_log_entry', $content, $log_entry );
			$content = apply_filters( "mycred_parse_log_entry_{$reference}", $content, $log_entry );

			// Get the reference type
			if ( isset( $data['ref_type'] ) ) {
				$type = $data['ref_type'];
				if ( $type == 'post' )
					$content = $this->template_tags_post( $content, $ref_id, $data );
				elseif ( $type == 'user' )
					$content = $this->template_tags_user( $content, $ref_id, $data );
				elseif ( $type == 'comment' )
					$content = $this->template_tags_comment( $content, $ref_id, $data );
				
				$content = apply_filters( "mycred_parse_tags_{$type}", $content, $log_entry );
			}

			return $content;
		}

		/**
		 * General Template Tags
		 * Replaces the general template tags in a given string.
		 * @since 0.1
		 * @version 1.0
		 */
		public function template_tags_general( $content ) {
			$content = apply_filters( 'mycred_parse_tags_general', $content );

			// Singular
			$content = str_replace( '%singular%',        $this->singular(), $content );
			$content = str_replace( '%_singular%',       strtolower( $this->singular() ), $content );

			// Plural
			$content = str_replace( '%plural%',          $this->plural(), $content );
			$content = str_replace( '%_plural%',         strtolower( $this->plural() ), $content );

			// Login URL
			$content = str_replace( '%login_url%',       wp_login_url(), $content );
			$content = str_replace( '%login_url_here%',  wp_login_url( get_permalink() ), $content );

			// Logout URL
			$content = str_replace( '%logout_url%',      wp_logout_url(), $content );
			$content = str_replace( '%logout_url_here%', wp_logout_url( get_permalink() ), $content );

			//$content = str_replace( '', , $content );
			return $content;
		}

		/**
		 * Amount Template Tags
		 * Replaces the amount template tags in a given string.
		 * @since 0.1
		 * @version 1.0
		 */
		public function template_tags_amount( $content, $amount ) {
			$content = str_replace( '%cred_f%', $this->format_creds( $amount ), $content );
			$content = str_replace( '%cred%',   $amount, $content );
			return $content;
		}

		/**
		 * Post Related Template Tags
		 * Replaces the post related template tags in a given string.
		 *
		 * @param $content (string) string containing the template tags
		 * @param $ref_id (int) required post id as reference id
		 * @param $data (object) Log entry data object
		 * @return (string) parsed string
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function template_tags_post( $content, $ref_id = NULL, $data = '' ) {
			if ( $ref_id === NULL ) return $content;

			// Get Post Object
			$post = get_post( $ref_id );

			// Post does not exist
			if ( $post === NULL ) return $content;

			// Let others play first
			$content = apply_filters( 'mycred_parse_tags_post', $content, $data, $post );

			// Replace template tags
			$content = str_replace( '%post_title%',      $post->post_title, $content );
			$content = str_replace( '%post_url%',        get_permalink( $post->ID ), $content );
			$content = str_replace( '%link_with_title%', '<a href="' . get_permalink( $post->ID ) . '">' . $post->post_title . '</a>', $content );

			$post_type = get_post_type_object( $post->post_type );
			$content = str_replace( '%post_type%', $post_type->labels->singular_name, $content );
			unset( $post_type );

			//$content = str_replace( '', $post->, $content );
			unset( $post );

			return $content;
		}

		/**
		 * User Related Template Tags
		 * Replaces the user related template tags in the given string.
		 *
		 * @param $content (string) string containing the template tags
		 * @param $ref_id (int) required user id as reference id
		 * @param $data (object) Log entry data object
		 * @return (string) parsed string
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function template_tags_user( $content, $ref_id = NULL, $data = '' ) {
			if ( $ref_id === NULL ) return $content;

			// Get User Object
			$user = get_userdata( $ref_id );

			// User does not exist
			if ( $user === false ) return $content;

			// Let others play first
			$content = apply_filters( 'mycred_parse_tags_user', $content, $data, $user );

			// Replace template tags
			$content = str_replace( '%user_id%',          $user->ID, $content );
			$content = str_replace( '%user_name%',        $user->user_login, $content );
			$content = str_replace( '%user_name_en%',     urlencode( $user->user_login ), $content );

			// Get Profile URL
			if ( function_exists( 'bp_core_get_user_domain' ) )
				$url = bp_core_get_user_domain( $user->ID );
			else {
				global $wp_rewrite;
				$url = get_bloginfo( 'url' ) . '/' . $wp_rewrite->author_base . '/' . urlencode( $user->user_login ) . '/';
			}

			$content = str_replace( '%display_name%',     $user->display_name, $content );
			$content = str_replace( '%user_profile_url%', $url, $content );
			$content = str_replace( '%user_profile_link%',  '<a href="' . $url . '">' . $user->display_name . '</a>', $content );

			//$content = str_replace( '', $user->, $content );
			unset( $user );

			return $content;
		}

		/**
		 * Comment Related Template Tags
		 * Replaces the comment related template tags in a given string.
		 *
		 * @param $content (string) string containing the template tags
		 * @param $ref_id (int) required comment id as reference id
		 * @param $data (object) Log entry data object
		 * @return (string) parsed string
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function template_tags_comment( $content, $ref_id = NULL, $data = '' ) {
			if ( $ref_id === NULL ) return $content;

			// Get Comment Object
			$comment = get_comment( $ref_id );

			// Comment does not exist
			if ( $comment === NULL ) return $content;

			// Let others play first
			$content = apply_filters( 'mycred_parse_tags_comment', $content, $data, $comment );

			$content = str_replace( '%comment_id%',      $comment->comment_ID, $content );

			$content = str_replace( '%c_post_id%',         $comment->comment_post_ID, $content );
			$content = str_replace( '%c_post_title%',      get_the_title( $comment->comment_post_ID ), $content );

			$content = str_replace( '%c_post_url%',       get_permalink( $comment->comment_post_ID ), $content );
			$content = str_replace( '%c_link_with_title%', '<a href="' . get_permalink( $comment->comment_post_ID ) . '">' . get_the_title( $comment->comment_post_ID ) . '</a>', $content );

			//$content = str_replace( '', $comment->, $content );
			unset( $comment );
			return $content;
		}
		
		/**
		 * Allowed Tags
		 * Strips HTML tags from a given string.
		 *
		 * @param $data (string) to strip tags off
		 * @param $allow (string) allows you to overwrite the default filter with a custom set of tags to strip
		 * @filter 'mycred_allowed_tags'
		 * @returns (string) string stripped of tags
		 * @since 0.1
		 * @version 1.0
		 */
		public function allowed_tags( $data, $allow = '' ) {
			if ( $allow === false )
				return strip_tags( $data );
			elseif ( !empty( $allow ) )
				return strip_tags( $data, $allow );
			else
				return strip_tags( $data, apply_filters( 'mycred_allowed_tags', '<a><br><em><strong><span>' ) );
		}

		/**
		 * Edit Creds Cap
		 * Returns the set edit creds capability.
		 *
		 * @returns capability (string)
		 * @since 0.1
		 * @version 1.0
		 */
		public function edit_creds_cap() {
			if ( !isset( $this->caps['creds'] ) || empty( $this->caps['creds'] ) )
				$this->caps['creds'] = 'edit_users';

			return $this->caps['creds'];
		}

		/**
		 * Can Edit Creds
		 * Check if user can edit other users creds. If no user id is given
		 * we will attempt to get the current users id.
		 *
		 * @param $user_id (int) user id
		 * @returns true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function can_edit_creds( $user_id = '' ) {
			if ( !function_exists( 'get_current_user_id' ) )
				require_once( ABSPATH . WPINC . '/user.php' );

			// Grab current user id
			if ( empty( $user_id ) )
				$user_id = get_current_user_id();

			if ( !function_exists( 'user_can' ) )
				require_once( ABSPATH . WPINC . '/capabilities.php' );

			// Check if user can
			if ( user_can( $user_id, $this->edit_creds_cap() ) ) return true;

			return false;
		}

		/**
		 * Edit Plugin Cap
		 * Returns the set edit plugin capability.
		 *
		 * @returns capability (string)
		 * @since 0.1
		 * @version 1.0
		 */
		public function edit_plugin_cap() {
			if ( !isset( $this->caps['plugin'] ) || empty( $this->caps['plugin'] ) )
				$this->caps['plugin'] = 'manage_options';

			return $this->caps['plugin'];
		}

		/**
		 * Can Edit This Plugin
		 * Checks if a given user can edit this plugin. If no user id is given
		 * we will attempt to get the current users id.
		 *
		 * @param $user_id (int) user id
		 * @returns true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function can_edit_plugin( $user_id = '' ) {
			if ( !function_exists( 'get_current_user_id' ) )
				require_once( ABSPATH . WPINC . '/user.php' );

			// Grab current user id
			if ( empty( $user_id ) )
				$user_id = get_current_user_id();

			if ( !function_exists( 'user_can' ) )
				require_once( ABSPATH . WPINC . '/capabilities.php' );

			// Check if user can
			if ( user_can( $user_id, $this->edit_plugin_cap() ) ) return true;
			
			return false;
		}

		/**
		 * Check if user id is in exclude list
		 * @return true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function in_exclude_list( $user_id = '' ) {

			// Grab current user id
			if ( empty( $user_id ) )
				$user_id = get_current_user_id();

			if ( !isset( $this->exclude['list'] ) )
				$this->exclude['list'] = '';

			$list = explode( ',', $this->exclude['list'] );
			if ( in_array( $user_id, $list ) ) return true;

			return false;
		}

		/**
		 * Exclude Plugin Editors
		 * @return true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function exclude_plugin_editors() {
			return (bool) $this->exclude['plugin_editors'];
		}

		/**
		 * Exclude Cred Editors
		 * @return true or false
		 * @since 0.1
		 * @version 1.0
		 */
		public function exclude_creds_editors() {
			return (bool) $this->exclude['cred_editors'];
		}

		/**
		 * Exclude User
		 * Checks is the given user id should be excluded.
		 *
		 * @param $user_id (int), required user id
		 * @returns boolean true on user should be excluded else false
		 * @since 0.1
		 * @version 1.0
		 */
		public function exclude_user( $user_id ) {
			if ( $this->exclude_plugin_editors() == true && $this->can_edit_plugin( $user_id ) == true ) return true;
			if ( $this->exclude_creds_editors() == true && $this->can_edit_creds( $user_id ) == true ) return true;
			if ( $this->in_exclude_list( $user_id ) ) return true;

			return false;
		}

		/**
		 * Get Cred ID
		 * Returns the default cred id.
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_cred_id() {
			if ( !isset( $this->cred_id ) )
				$this->cred_id = 'mycred_default';

			return $this->cred_id;
		}

		/**
		 * Get users creds
		 * Returns the users creds unformated.
		 *
		 * @param $user_id (int), required user id
		 * @param $type (string), optional cred type to check for
		 * @returns empty if user id is not set or if no creds were found, else returns creds
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_users_cred( $user_id = '', $type = '' ) {
			if ( empty( $user_id ) ) return $user_id;

			if ( empty( $type ) ) $type = $this->get_cred_id();
			$balance = get_user_meta( $user_id, $type, true );
			if ( empty( $balance ) ) $balance = 0;
			
			return $this->number( $balance );
		}

		/**
		 * Update users creds
		 * Returns the updated balance of the given user.
		 *
		 * @param $user_id (int), required user id
		 * @param $amount (int|float), amount to add/deduct from users balance. This value must be pre-formated.
		 * @returns the new balance.
		 * @since 0.1
		 * @version 1.0
		 */
		public function update_users_cred( $user_id = NULL, $amount = NULL ) {
			if ( $user_id === NULL || $amount === NULL ) return $amount;
			if ( empty( $this->cred_id ) ) $this->cred_id = $this->get_cred_id();

			// Adjust creds
			$current_balance = $this->get_users_cred( $user_id );
			$new_balance = $current_balance+$amount;

			// Update creds
			update_user_meta( $user_id, $this->cred_id, $new_balance );

			// Rankings
			if ( $this->frequency['rate'] == 'always' ) delete_transient( $this->cred_id . '_ranking' );

			// Return the new balance
			return $new_balance;
		}

		/**
		 * Add Creds
		 * Adds creds to a given user. A refernece ID, user id and number of creds must be given.
		 * Important! This function will not check if the user should be excluded from gaining points, this must
		 * be done before calling this function!
		 *
		 * @param $ref (string), required reference id
		 * @param $user_id (int), required id of the user who will get these points
		 * @param $cred (int|float), required number of creds to give or deduct from the given user.
		 * @param $ref_id (array), optional array of reference IDs allowing the use of content specific keywords in the log entry
		 * @param $data (object|array|string|int), optional extra data to save in the log. Note that arrays gets serialized!
		 * @param $type (string), optional point name, defaults to 'mycred_default'
		 * @returns boolean true on success or false on fail
		 * @since 0.1
		 * @version 1.0
		 */
		public function add_creds( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' ) {
			// All the reasons we would fail
			if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
			if ( $this->exclude_user( $user_id ) === true ) return false;
			if ( !preg_match( '/mycred_/', $type ) ) return false;

			// Format creds
			$amount = $this->number( $amount );

			// Adjust creds
			$new_balance = $this->update_users_cred( $user_id, $amount );

			// Let others play
			$request = compact( 'ref', 'amount', 'entry', 'ref_id', 'data', 'type', 'user_id', 'current_balance', 'new_balance' );
			do_action( 'mycred_add', $request, $this->core );

			// Add log entry
			$this->add_to_log( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );
			return true;
		}

		/**
		 * Add Log Entry
		 * Adds a new entry into the log. A reference id, user id and number of credits must be set.
		 *
		 * @param $ref (string), required reference id
		 * @param $user_id (int), required id of the user who will get these points
		 * @param $cred (int|float), required number of creds to give or deduct from the given user.
		 * @param $ref_id (array), optional array of reference IDs allowing the use of content specific keywords in the log entry
		 * @param $data (object|array|string|int), optional extra data to save in the log. Note that arrays gets serialized!
		 * @returns boolean true on success or false on fail
		 * @version 1.0
		 */
		public function add_to_log( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = '' ) {
			// All the reasons we would fail
			if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
			if ( !preg_match( '/mycred_/', $type ) ) return false;

			global $wpdb;

			// Strip HTML from log entry
			$entry = $this->allowed_tags( $entry );

			// Type
			if ( empty( $type ) ) $type = $this->get_cred_id();

			// Creds format
			if ( $this->format['decimals'] > 0 )
				$format = '%f';
			elseif ( $this->format['decimals'] == 0 )
				$format = '%d';
			else
				$format = '%s';

			// Insert into DB
			$new_entry = $wpdb->insert(
				$wpdb->prefix . 'myCRED_log',
				array(
					'ref'     => $ref,
					'ref_id'  => $ref_id,
					'user_id' => $user_id,
					'creds'   => $amount,
					'ctype'   => $type,
					'time'    => date_i18n( 'U' ),
					'entry'   => $entry,
					'data'    => ( is_array( $data ) || is_object( $data ) ) ? serialize( $data ) : $data
				),
				array(
					'%s',
					'%d',
					'%d',
					$format,
					'%s',
					'%d',
					'%s',
					( is_numeric( $data ) ) ? '%d' : '%s'
				)
			);

			// $wpdb->insert returns false on fail
			if ( !$new_entry ) return false;
			return true;
		}
	}
}

/**
 * Get Settings
 * Returns myCRED's general settings.
 *
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_settings' ) ) {
	function mycred_get_settings()
	{
		global $mycred;
		if ( !isset( $mycred ) || empty( $mycred ) ) $mycred = new myCRED_Settings();
		return $mycred;
	}
}

/**
 * Get Network Settings
 * Returns myCRED's network settings or false if multisite is not enabled.
 *
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_settings_network' ) ) {
	function mycred_get_settings_network()
	{
		if ( !is_multisite() ) return false;

		global $mycred_network;

		if ( !isset( $mycred_network ) ) {
			$defaults = array(
				'master' => 0,
				'block'  => ''
			);
			$mycred_network = get_site_option( 'mycred_network', $defaults );
		}

		return $mycred_network;
	}
}

/**
 * Overwrite
 * Checks if master template is used.
 * Requires Multisite
 *
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_overwrite' ) ) {
	function mycred_overwrite() {
		// Not a multisite
		if ( !is_multisite() ) return false;

		$mycred_network = mycred_get_settings_network();
		return (bool) $mycred_network['master'];
	}
}

/**
 * Get myCRED Name
 * Returns the name given to creds.
 *
 * @param $signular (boolean) option to return the plural version, returns singular by default
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_name' ) ) {
	function mycred_name( $singular = true )
	{
		$mycred = mycred_get_settings();
		if ( $singular )
			return $mycred->singular();
		else
			return $mycred->plural();
	}
}

/**
 * Strip Tags
 * Strippes HTML tags from a given string.
 *
 * @param $string (string) string to stip
 * @param $overwrite (string), optional HTML tags to allow
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_strip_tags' ) ) {
	function mycred_strip_tags( $string, $overwride = '' )
	{
		$mycred = mycred_get_settings();
		return $mycred->allowed_tags( $string, $overwrite );
	}
}

/**
 * Is Admin
 * Conditional tag that checks if a given user or the current user
 * can either edit the plugin or creds.
 *
 * @param $user_id (int), optional user id to check, defaults to current user
 * @returns true or false
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_is_admin' ) ) {
	function mycred_is_admin( $user_id = NULL )
	{
		$mycred = mycred_get_settings();
		if ( $user_id === NULL ) $user_id = get_current_user_id();

		if ( $mycred->can_edit_creds( $user_id ) || $mycred->can_edit_plugin( $user_id ) ) return true;

		return false;
	}
}

/**
 * Exclude User
 * Checks if a given user is excluded from using myCRED.
 *
 * @see http://mycred.merovingi.com/functions/mycred_exclude_user/
 * @param $user_id (int), optional user to check, defaults to current user
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_exclude_user' ) ) {
	function mycred_exclude_user( $user_id = NULL )
	{
		$mycred = mycred_get_settings();
		if ( $user_id === NULL ) $user_id = get_current_user_id();
		return $mycred->exclude_user( $user_id );
	}
}

/**
 * Get Users Creds
 * Returns the given users current cred balance. If no user id is given this function
 * will default to the current user!
 *
 * @param $user_id (int) user id
 * @return users balance (int|float)
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_users_cred' ) ) {
	function mycred_get_users_cred( $user_id = NULL, $type = '' )
	{
		if ( $user_id === NULL ) $user_id = get_current_user_id();

		$mycred = mycred_get_settings();
		return $mycred->get_users_cred( $user_id, $type );
	}
}

/**
 * Get Users Creds Formated
 * Returns the given users current cred balance formated. If no user id is given
 * this function will return false!
 *
 * @param $user_id (int), required user id
 * @return users balance (string) or false if no user id is given
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_users_fcred' ) ) {
	function mycred_get_users_fcred( $user_id = NULL, $type = '' )
	{
		if ( $user_id === NULL ) return false;

		$mycred = mycred_get_settings();
		$cred = $mycred->get_users_cred( $user_id, $type );
		return $mycred->format_creds( $cred );
	}
}

/**
 * Add Creds
 * Adds creds to a given user. A refernece ID, user id and amount must be given.
 * Important! This function will not check if the user should be excluded from gaining points, this must
 * be done before calling this function!
 *
 * @see http://mycred.merovingi.com/functions/mycred_add/
 * @param $ref (string), required reference id
 * @param $user_id (int), required id of the user who will get these points
 * @param $amount (int|float), required number of creds to give or deduct from the given user.
 * @param $ref_id (array), optional array of reference IDs allowing the use of content specific keywords in the log entry
 * @param $data (object|array|string|int), optional extra data to save in the log. Note that arrays gets serialized!
 * @returns boolean true on success or false on fail
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_add' ) ) {
	function mycred_add( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = '' )
	{
		// $ref, $user_id and $cred is required
		if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;

		$mycred = mycred_get_settings();
		if ( empty( $type ) ) $type = $mycred->get_cred_id();

		// Add creds
		return $mycred->add_creds( $pref, $user_id, $amount, $entry, $ref_id, $data, $type );
	}
}

/**
 * Subtract Creds
 * Subtracts creds from a given user. Works just as mycred_add() but the creds are converted into a negative value.
 * @see http://mycred.merovingi.com/functions/mycred_subtract/
 * @uses mycred_add()
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_subtract' ) ) {
	function mycred_subtract( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = '' )
	{
		if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
		if ( $amount > 0 ) $amount = '-' . $amount;
		return mycred_add( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );
	}
}
?>