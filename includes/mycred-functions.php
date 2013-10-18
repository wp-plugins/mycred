<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Settings class
 * @see http://mycred.me/classes/mycred_settings/
 * @since 0.1
 * @version 1.3
 */
if ( !class_exists( 'myCRED_Settings' ) ) {
	class myCRED_Settings {

		public $core;
		public $log_table;

		/**
		 * Construct
		 */
		function __construct() {
			if ( is_multisite() ) {
				if ( mycred_override_settings() )
					$this->core = get_blog_option( 1, 'mycred_pref_core', $this->defaults() );
				else
					$this->core = get_blog_option( $GLOBALS['blog_id'], 'mycred_pref_core', $this->defaults() );
			}
			else {
				$this->core = get_option( 'mycred_pref_core', $this->defaults() );
			}
			
			if ( $this->core !== false ) {
				foreach ( (array) $this->core as $key => $value ) {
					$this->$key = $value;
				}
			}
			
			if ( defined( 'MYCRED_LOG_TABLE' ) )
				$this->log_table = MYCRED_LOG_TABLE;
			else {
				global $wpdb;
				
				if ( mycred_centralize_log() )
					$this->log_table = $wpdb->base_prefix . 'myCRED_log';
				else
					$this->log_table = $wpdb->prefix . 'myCRED_log';
			}
		}
		
		/**
		 * Default Settings
		 * @since 1.3
		 * @version 1.0
		 */
		public function defaults() {
			return array(
				'cred_id'   => 'mycred_default',
				'format'    => array(
					'type'       => '',
					'decimals'   => 0,
					'separators' => array(
						'decimal'   => '.',
						'thousand'  => ','
					)
				),
				'name'      => array(
					'singular' => __( 'Point', 'mycred' ),
					'plural'   => __( 'Points', 'mycred' )
				),
				'before'    => '',
				'after'     => '',
				'caps'      => array(
					'plugin'   => 'manage_options',
					'creds'    => 'export'
				),
				'exclude'   => array(
					'plugin_editors' => 0,
					'cred_editors'   => 0,
					'list'           => ''
				),
				'frequency' => array(
					'rate'     => 'always',
					'date'     => ''
				)
			);
		}

		/**
		 * Singular myCRED name
		 * @since 0.1
		 * @version 1.0
		 */
		public function singular() {
			if ( ! isset( $this->core['name']['singular'] ) )
				return $this->name['singular'];
			else
				return $this->core['name']['singular'];
		}

		/**
		 * Plural myCRED name
		 * @since 0.1
		 * @version 1.0
		 */
		public function plural() {
			if ( ! isset( $this->core['name']['plural'] ) )
				return $this->name['plural'];
			else
				return $this->core['name']['plural'];
		}
		
		/**
		 * Zero
		 * Returns zero formated with or without decimals.
		 * @since 1.3
		 * @version 1.0
		 */
		public function zero() {
			if ( !isset( $this->format['decimals'] ) )
				$decimals = $this->core['format']['decimals'];
			else
				$decimals = $this->format['decimals'];
			
			return number_format( 0, $decimals );
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
		 * @version 1.1.1
		 */
		public function number( $number = '' ) {
			if ( $number === '' ) return $number;

			if ( !isset( $this->format['decimals'] ) )
				$decimals = (int) $this->core['format']['decimals'];
			else
				$decimals = (int) $this->format['decimals'];

			if ( $decimals > 0 ) {
				return number_format( (float) $number, $decimals, '.', '' );
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
		 * @version 1.1
		 */
		public function format_number( $number = '' ) {
			if ( $number === '' ) return $number;

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
		 * @version 1.2
		 */
		public function apply_exchange_rate( $amount = 0, $rate = 1, $round = true ) {
			$amount = $this->number( $amount );
			if ( !is_numeric( $rate ) || $rate == 1 ) return $amount;

			$exchange = $amount/(float) $rate;
			if ( $round ) $exchange = round( $exchange );

			return $this->number( $exchange );
		}
		
		/**
		 * Parse Template Tags
		 * Parses template tags in a given string by checking for the 'ref_type' array key under $log_entry->data.
		 * @since 0.1
		 * @version 1.0
		 */
		public function parse_template_tags( $content = '', $log_entry ) {
			// Prep
			$reference = $log_entry->ref;
			$ref_id = $log_entry->ref_id;
			$data = $log_entry->data;

			// Unserialize if serialized
			$data = maybe_unserialize( $data );

			// Run basic template tags first
			$content = $this->template_tags_general( $content );

			// Start by allowing others to play
			$content = apply_filters( 'mycred_parse_log_entry', $content, $log_entry );
			$content = apply_filters( "mycred_parse_log_entry_{$reference}", $content, $log_entry );

			// Get the reference type
			if ( isset( $data['ref_type'] ) || isset( $data['post_type'] ) ) {
				if ( isset( $data['ref_type'] ) )
					$type = $data['ref_type'];
				elseif ( isset( $data['post_type'] ) )
					$type = $data['post_type'];

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
		 * @version 1.2
		 */
		public function template_tags_general( $content = '' ) {
			$content = apply_filters( 'mycred_parse_tags_general', $content );

			// Singular
			$content = str_replace( array( '%singular%', '%Singular%' ), $this->singular(), $content );
			$content = str_replace( '%_singular%',       strtolower( $this->singular() ), $content );

			// Plural
			$content = str_replace(  array( '%plural%', '%Plural%' ), $this->plural(), $content );
			$content = str_replace( '%_plural%',         strtolower( $this->plural() ), $content );

			// Login URL
			$content = str_replace( '%login_url%',       wp_login_url(), $content );
			$content = str_replace( '%login_url_here%',  wp_login_url( get_permalink() ), $content );

			// Logout URL
			$content = str_replace( '%logout_url%',      wp_logout_url(), $content );
			$content = str_replace( '%logout_url_here%', wp_logout_url( get_permalink() ), $content );
			
			// Blog Related
			if ( preg_match( '%(num_members|blog_name|blog_url|blog_info|admin_email)%', $content, $matches ) ) {
				$content = str_replace( '%num_members%',     $this->count_members(), $content );
				$content = str_replace( '%blog_name%',       get_bloginfo( 'name' ), $content );
				$content = str_replace( '%blog_url%',        get_bloginfo( 'url' ), $content );
				$content = str_replace( '%blog_info%',       get_bloginfo( 'description' ), $content );
				$content = str_replace( '%admin_email%',     get_bloginfo( 'admin_email' ), $content );
			}

			//$content = str_replace( '', , $content );
			return $content;
		}

		/**
		 * Amount Template Tags
		 * Replaces the amount template tags in a given string.
		 * @since 0.1
		 * @version 1.0.3
		 */
		public function template_tags_amount( $content = '', $amount = 0 ) {
			$content = $this->template_tags_general( $content );
			if ( !$this->has_tags( 'amount', 'cred|cred_f', $content ) ) return $content;
			$content = apply_filters( 'mycred_parse_tags_amount', $content, $amount );
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
		 * @version 1.0.4
		 */
		public function template_tags_post( $content = '', $ref_id = NULL, $data = '' ) {
			if ( $ref_id === NULL ) return $content;
			$content = $this->template_tags_general( $content );
			if ( !$this->has_tags( 'post', 'post_title|post_url|link_with_title|post_type', $content ) ) return $content;

			// Get Post Object
			$post = get_post( $ref_id );

			// Post does not exist
			if ( $post === NULL ) {
				if ( !is_array( $data ) || !array_key_exists( 'ID', $data ) ) return $content;
				$post = new StdClass();
				foreach ( $data as $key => $value ) {
					if ( $key == 'post_title' ) $value .= ' (' . __( 'Deleted', 'mycred' ) . ')';
					$post->$key = $value;
				}
				$url = get_permalink( $post->ID );
				if ( empty( $url ) ) $url = '#item-has-been-deleted';
			}
			else {
				$url = get_permalink( $post->ID );
			}

			// Let others play first
			$content = apply_filters( 'mycred_parse_tags_post', $content, $post, $data );

			// Replace template tags
			$content = str_replace( '%post_title%',      $post->post_title, $content );
			$content = str_replace( '%post_url%',        $url, $content );
			$content = str_replace( '%link_with_title%', '<a href="' . $url . '">' . $post->post_title . '</a>', $content );

			$post_type = get_post_type_object( $post->post_type );
			if ( $post_type !== NULL ) {
				$content = str_replace( '%post_type%', $post_type->labels->singular_name, $content );
				unset( $post_type );
			}

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
		 * @version 1.2.1
		 */
		public function template_tags_user( $content = '', $ref_id = NULL, $data = '' ) {
			if ( $ref_id === NULL ) return $content;
			$content = $this->template_tags_general( $content );
			if ( !$this->has_tags( 'user', 'user_id|user_name|user_name_en|display_name|user_profile_url|user_profile_link|user_nicename|user_email|user_url|balance|balance_f', $content ) ) return $content;

			// Get User Object
			if ( $ref_id !== false )
				$user = get_userdata( $ref_id );
			// User object is passed on though $data
			elseif ( $ref_id === false && is_object( $data ) && isset( $data->ID ) )
				$user = $data;
			// User array is passed on though $data
			elseif ( $ref_id === false && is_array( $data ) || array_key_exists( 'ID', $data ) ) {
				$user = new StdClass();
				foreach ( $data as $key => $value ) {
					if ( $key == 'login' )
						$user->user_login = $value;
					else
						$user->$key = $value;
				}
			}
			else return $content;

			// Let others play first
			$content = apply_filters( 'mycred_parse_tags_user', $content, $user, $data );

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

			$content = str_replace( '%display_name%',       $user->display_name, $content );
			$content = str_replace( '%user_profile_url%',   $url, $content );
			$content = str_replace( '%user_profile_link%',  '<a href="' . $url . '">' . $user->display_name . '</a>', $content );

			$content = str_replace( '%user_nicename%',      ( isset( $user->user_nicename ) ) ? $user->user_nicename : '', $content );
			$content = str_replace( '%user_email%',         ( isset( $user->user_email ) ) ? $user->user_email : '', $content );
			$content = str_replace( '%user_url%',           ( isset( $user->user_url ) ) ? $user->user_url : '', $content );

			// Account Related
			$balance = $this->get_users_cred( $user->ID );
			$content = str_replace( '%balance%',            $balance, $content );
			$content = str_replace( '%balance_f%',          $this->format_creds( $balance ), $content );
			
			// Ranking
			if ( !function_exists( 'mycred_get_users_rank' ) )
				$content = str_replace( array( '%rank%', '%ranking%' ), mycred_rankings_position( $user->ID ), $content );
			else
				$content = str_replace( '%ranking%', mycred_rankings_position( $user->ID ), $content );
			
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
		 * @version 1.0.4
		 */
		public function template_tags_comment( $content = '', $ref_id = NULL, $data = '' ) {
			if ( $ref_id === NULL ) return $content;
			$content = $this->template_tags_general( $content );
			if ( !$this->has_tags( 'comment', 'comment_id|c_post_id|c_post_title|c_post_url|c_link_with_title', $content ) ) return $content;

			// Get Comment Object
			$comment = get_comment( $ref_id );

			// Comment does not exist
			if ( $comment === NULL ) {
				if ( !is_array( $data ) || !array_key_exists( 'comment_ID', $data ) ) return $content;
				$comment = new StdClass();
				foreach ( $data as $key => $value ) {
					$comment->$key = $value;
				}
				$url = get_permalink( $comment->comment_post_ID );
				if ( empty( $url ) ) $url = '#item-has-been-deleted';

				$title = get_the_title( $comment->comment_post_ID );
				if ( empty( $title ) ) $title = __( 'Deleted Item', 'mycred' );
			}
			else {
				$url = get_permalink( $comment->comment_post_ID );
				$title = get_the_title( $comment->comment_post_ID );
			}

			// Let others play first
			$content = apply_filters( 'mycred_parse_tags_comment', $content, $comment, $data );

			$content = str_replace( '%comment_id%',        $comment->comment_ID, $content );

			$content = str_replace( '%c_post_id%',         $comment->comment_post_ID, $content );
			$content = str_replace( '%c_post_title%',      $title, $content );

			$content = str_replace( '%c_post_url%',        $url, $content );
			$content = str_replace( '%c_link_with_title%', '<a href="' . $url . '">' . $title . '</a>', $content );

			//$content = str_replace( '', $comment->, $content );
			unset( $comment );
			return $content;
		}
		
		/**
		 * Has Tags
		 * Checks if a string has any of the defined template tags.
		 *
		 * @param $type (string) template tag type
		 * @param $tags (string) tags to search for, list with |
		 * @param $content (string) content to search
		 * @filter 'mycred_has_tags'
		 * @filter 'mycred_has_tags_{$type}'
		 * @returns (boolean) true or false
		 * @since 1.2.2
		 * @version 1.0
		 */
		public function has_tags( $type = '', $tags = '', $content = '' ) {
			$tags = apply_filters( 'mycred_has_tags', $tags, $content );
			$tags = apply_filters( 'mycred_has_tags_' . $type, $tags, $content );
			if ( !preg_match( '%(' . trim( $tags ) . ')%', $content, $matches ) ) return false;
			return true;
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
		public function allowed_tags( $data = '', $allow = '' ) {
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
				$this->caps['creds'] = 'delete_users';

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
		public function exclude_user( $user_id = 0 ) {
			if ( $this->exclude_plugin_editors() == true && $this->can_edit_plugin( $user_id ) == true ) return true;
			if ( $this->exclude_creds_editors() == true && $this->can_edit_creds( $user_id ) == true ) return true;
			if ( $this->in_exclude_list( $user_id ) ) return true;

			return false;
		}

		/**
		 * Count Blog Members
		 * @since 1.1
		 * @version 1.0
		 */
		public function count_members() {
			global $wpdb;
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users};" );
		}

		/**
		 * Get Cred ID
		 * Returns the default cred id.
		 * @since 0.1
		 * @version 1.0
		 */
		public function get_cred_id() {
			if ( !isset( $this->cred_id ) || empty( $this->cred_id ) )
				$this->cred_id = 'mycred_default';

			return $this->cred_id;
		}

		/**
		 * Get users creds
		 * Returns the users creds unformated.
		 *
		 * @param $user_id (int), required user id
		 * @param $type (string), optional cred type to check for
		 * @returns zero if user id is not set or if no creds were found, else returns amount
		 * @since 0.1
		 * @version 1.2
		 */
		public function get_users_cred( $user_id = '', $type = '' ) {
			if ( empty( $user_id ) ) return $this->zero();

			if ( empty( $type ) ) $type = $this->get_cred_id();
			$balance = get_user_meta( $user_id, $type, true );
			if ( empty( $balance ) ) $balance = $this->zero();

			// Let others play
			$balance = apply_filters( 'mycred_get_users_cred', $balance, $this, $user_id, $type );

			return $this->number( $balance );
		}
		public function get_users_balance( $user_id = '', $type = '' ) {
			return $this->get_users_cred( $user_id, $type );
		}

		/**
		 * Update users balance
		 * Returns the updated balance of the given user.
		 *
		 * @param $user_id (int), required user id
		 * @param $amount (int|float), amount to add/deduct from users balance. This value must be pre-formated.
		 * @returns the new balance.
		 * @since 0.1
		 * @version 1.1
		 */
		public function update_users_balance( $user_id = NULL, $amount = NULL ) {
			if ( $user_id === NULL || $amount === NULL ) return $amount;
			if ( empty( $this->cred_id ) ) $this->cred_id = $this->get_cred_id();

			// Adjust creds
			$current_balance = $this->get_users_cred( $user_id );
			$new_balance = $current_balance+$amount;

			// Update creds
			update_user_meta( $user_id, $this->cred_id, $new_balance );

			// Rankings
			if ( $this->frequency['rate'] == 'always' ) delete_transient( $this->cred_id . '_ranking' );

			// Let others play
			do_action( 'mycred_update_user_balance', $user_id, $current_balance, $amount, $this->cred_id );

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
		 * @param $ref_id (int), optional array of reference IDs allowing the use of content specific keywords in the log entry
		 * @param $data (object|array|string|int), optional extra data to save in the log. Note that arrays gets serialized!
		 * @param $type (string), optional point name, defaults to 'mycred_default'
		 * @returns boolean true on success or false on fail
		 * @since 0.1
		 * @version 1.2
		 */
		public function add_creds( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' ) {
			// All the reasons we would fail
			if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
			if ( $this->exclude_user( $user_id ) === true ) return false;
			if ( !preg_match( '/mycred_/', $type ) ) return false;

			// Format creds
			$amount = $this->number( $amount );

			// Execution Override
			// Let others play before awarding points.
			// Your functions should return the answer to the question: "Should myCRED adjust the users point balance?"
			$execute = apply_filters( 'mycred_add', true, compact( 'ref', 'user_id', 'amount', 'entry', 'ref_id', 'data', 'type' ), $this );

			// Acceptable answers:
			// true (boolean)  - "Yes" let myCRED add points and log the event
			if ( $execute === true ) {
				$this->update_users_balance( $user_id, $amount );
				
				if ( !empty( $entry ) )
					$this->add_to_log( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );

				// Update rankings
				if ( $this->frequency['rate'] == 'always' )
					$this->update_rankings();

				return true;
			}
			// done (string)   - "Already done"
			elseif ( $execute === 'done' ) {
				// Update rankings
				if ( $this->frequency['rate'] == 'always' )
					$this->update_rankings();

				return true;
			}
			// false (boolean) - "No"
			else {
				return false;
			}
		}
		
		/**
		 * Update Rankings
		 * Updates the rankings for a given points type.
		 *
		 * @param $force (bool), if rankings are updated on a set interval, this option can override
		 * and force a new setting to be saved.
		 * @param $type (string), optional points type
		 * @since 1.1.2
		 * @version 1.0
		 */
		public function update_rankings( $force = false, $type = 'mycred_default' ) {
			$ranking = new myCRED_Query_Rankings( array( 'type' => $type ) );
			$ranking->get_rankings();
			$ranking->save( $force );
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
		 * @version 1.0.2
		 */
		public function add_to_log( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' ) {
			// All the reasons we would fail
			if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
			if ( !preg_match( '/mycred_/', $type ) ) return false;
			if ( $this->number( $amount ) == $this->zero() ) return false;

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
				$this->log_table,
				array(
					'ref'     => $ref,
					'ref_id'  => $ref_id,
					'user_id' => (int) $user_id,
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
			
			delete_transient( 'mycred_log_entries' );
			return true;
		}

		/**
		 * Has Entry
		 * Checks to see if a given action with reference ID and user ID exists in the log database.
		 * @param $reference (string) required reference ID
		 * @param $ref_id (int) optional reference id
		 * @param $user_id (int) optional user id
		 * @param $data (array|string) option data to search
		 * @since 0.1
		 * @version 1.1
		 */
		function has_entry( $reference = '', $ref_id = '', $user_id = '', $data = '' ) {
			global $wpdb;

			$where = $prep = array();
			if ( !empty( $reference ) ) {
				$where[] = 'ref = %s';
				$prep[] = $reference;
			}

			if ( !empty( $ref_id ) ) {
				$where[] = 'ref_id = %d';
				$prep[] = $ref_id;
			}

			if ( !empty( $user_id ) ) {
				$where[] = 'user_id = %d';
				$prep[] = abs( $user_id );
			}

			if ( !empty( $data ) ) {
				$where[] = 'data = %s';
				$prep[] = maybe_serialize( $data );
			}

			$where = implode( ' AND ', $where );

			if ( !empty( $where ) ) {
				$sql = "SELECT * FROM {$this->log_table} WHERE {$where};";
				$wpdb->get_results( $wpdb->prepare( $sql, $prep ) );
				if ( $wpdb->num_rows > 0 ) return true;
			}

			return false;
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

		$defaults = array(
			'master'  => 0,
			'central' => 0,
			'block'   => ''
		);
		$settings = get_blog_option( 1, 'mycred_network', $defaults );

		return $settings;
	}
}

/**
 * Override Settings
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_override_settings' ) ) {
	function mycred_override_settings() {
		// Not a multisite
		if ( ! is_multisite() ) return false;

		$mycred_network = mycred_get_settings_network();
		if ( $mycred_network['master'] ) return true;
		
		return false;
	}
}

/**
 * Centralize Log
 * @since 1.3
 * @version 1.0
 */
if ( !function_exists( 'mycred_centralize_log' ) ) {
	function mycred_centralize_log() {
		// Not a multisite
		if ( ! is_multisite() ) return true;

		$mycred_network = mycred_get_settings_network();
		if ( $mycred_network['central'] ) return true;
		
		return false;
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
	function mycred_strip_tags( $string = '', $overwride = '' )
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
 * @see http://mycred.me/functions/mycred_exclude_user/
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
 * Flush Widget Cache
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_flush_widget_cache' ) ) {
	function mycred_flush_widget_cache( $id = NULL )
	{
		if ( $id === NULL ) return;
		wp_cache_delete( $id, 'widget' );
	}
}

/**
 * Add Creds
 * Adds creds to a given user. A refernece ID, user id and amount must be given.
 * Important! This function will not check if the user should be excluded from gaining points, this must
 * be done before calling this function!
 *
 * @see http://mycred.me/functions/mycred_add/
 * @param $ref (string), required reference id
 * @param $user_id (int), required id of the user who will get these points
 * @param $amount (int|float), required number of creds to give or deduct from the given user.
 * @param $ref_id (array), optional array of reference IDs allowing the use of content specific keywords in the log entry
 * @param $data (object|array|string|int), optional extra data to save in the log. Note that arrays gets serialized!
 * @returns boolean true on success or false on fail
 * @since 0.1
 * @version 1.1
 */
if ( !function_exists( 'mycred_add' ) ) {
	function mycred_add( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' )
	{
		// $ref, $user_id and $cred is required
		if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;

		$mycred = mycred_get_settings();
		if ( empty( $type ) ) $type = $mycred->get_cred_id();

		// Add creds
		return $mycred->add_creds( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );
	}
}

/**
 * Subtract Creds
 * Subtracts creds from a given user. Works just as mycred_add() but the creds are converted into a negative value.
 * @see http://mycred.me/functions/mycred_subtract/
 * @uses mycred_add()
 * @since 0.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_subtract' ) ) {
	function mycred_subtract( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' )
	{
		if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
		if ( (int) $amount > 0 ) $amount = 0-$amount;
		return mycred_add( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );
	}
}

/**
 * Count Reference Instances
 * Counts the total number of occurrences of a specific reference for a user.
 * @see http://mycred.me/functions/mycred_count_ref_instances/
 * @param $reference (string) required reference to check
 * @param $user_id (int) option to check references for a specific user
 * @uses get_var()
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_count_ref_instances' ) ) {
	function mycred_count_ref_instances( $reference = '', $user_id = NULL )
	{
		if ( empty( $reference ) ) return 999999999;

		$mycred = mycred_get_settings();

		global $wpdb;

		if ( $user_id !== NULL ) {
			return $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$mycred->log_table} WHERE ref = %s AND user_id = %d;",
				$reference,
				$user_id
			) );
		}

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$mycred->log_table} WHERE ref = %s;", $reference ) );
	}
}

/**
 * Get Total Points by Time
 * Counts the total amount of points that has been entered into the log between
 * two given UNIX timestamps. Optionally you can restrict counting to a specific user
 * or specific reference (or both).
 *
 * Will return false if the time stamps are incorrectly formated same for user id (must be int).
 * If you do not want to filter by reference pass NULL and not an empty string or this function will
 * return false. Same goes for the user id!
 *
 * @param $from (int|string) UNIX timestamp from when to start counting. The string 'today' can also
 * be used to start counting from the start of today.
 * @param $to (int|string) UNIX timestamp for when to stop counting. The string 'now' can also be used
 * to count up until now.
 * @param $ref (string) reference to filter by.
 * @param $user_id (int|NULL) user id to filter by.
 * @param $type (string) point type to filer by.
 * @returns total points (int|float) or error message (string)
 * @since 1.1.1
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_total_by_time' ) ) {
	function mycred_get_total_by_time( $from = 'today', $to = 'now', $ref = NULL, $user_id = NULL, $type = '' )
	{
		// Get myCRED
		$mycred = mycred_get_settings();

		// Prep
		$wheres = array();
		$prep = array();

		// Reference
		if ( $ref !== NULL ) {
			if ( empty( $ref ) ) return __( 'ref empty', 'mycred' );

			$wheres[] = 'ref = %s';
			$prep[] = $ref;
		}

		// User
		if ( $user_id !== NULL ) {
			if ( !is_int( $user_id ) ) return __( 'incorrect user id format', 'mycred' );

			$wheres[] = 'user_id = %d';
			$prep[] = $user_id;
		}

		// Default from start of today
		if ( $from == 'today' ) {
			$today = date_i18n( 'Y/m/d 00:00:00' );
			$from = strtotime( $today );
		}

		// From
		if ( !is_numeric( $from ) ) return __( 'incorrect unix timestamp (from):', 'mycred' ) . ' ' . $from;
		$wheres[] = 'time >= %d';
		$prep[] = $from;

		// Default to is now
		if ( $to == 'now' )
			$to = date_i18n( 'U' );

		// To
		if ( !is_numeric( $to ) ) return __( 'incorrect unix timestamp (to):', 'mycred' ) . ' ' . $to;
		$wheres[] = 'time <= %d';
		$prep[] = $to;

		// Type
		if ( empty( $type ) )
			$type = $mycred->get_cred_id();

		$wheres[] = 'ctype = %s';
		$prep[] = $type;

		global $wpdb;

		// Construct
		$where = implode( ' AND ', $wheres );
		$sql = "SELECT creds FROM {$mycred->log_table} WHERE {$where} ORDER BY time;";

		// Query
		$query = $wpdb->get_results( $wpdb->prepare( $sql, $prep ) );

		$count = 0;
		// if we have results we add creds up
		if ( !empty( $query ) ) {
			foreach ( $query as $entry ) {
				$count = $count+$entry->creds;
			}
		}

		return $mycred->format_number( $count );
	}
}

/**
 * Get users total creds
 * Returns the users total creds unformated. If no total is fuond,
 * the users current balance is returned instead.
 *
 * @param $user_id (int), required user id
 * @param $type (string), optional cred type to check for
 * @returns zero if user id is not set or if no total were found, else returns creds
 * @since 1.2
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_users_total' ) ) {
	function mycred_get_users_total( $user_id = '', $type = '' ) {
		if ( empty( $user_id ) ) return 0;

		$mycred = mycred_get_settings();
		if ( empty( $type ) ) $type = $mycred->get_cred_id();
		$total = get_user_meta( $user_id, $type . '_total', true );
		if ( empty( $total ) ) $total = $mycred->get_users_cred( $user_id, $type );

		return $mycred->number( $total );
	}
}

/**
 * Update users total creds
 * Updates a given users total creds balance.
 *
 * @param $user_id (int), required user id
 * @param $request (array), required request array with information on users id (user_id) and amount
 * @param $mycred (myCRED_Settings object), required myCRED settings object
 * @returns zero if user id is not set or if no total were found, else returns total
 * @since 1.2
 * @version 1.0
 */
if ( !function_exists( 'mycred_update_users_total' ) ) {
	function mycred_update_users_total( $type = '', $request = NULL, $mycred = NULL ) {
		if ( $request === NULL || !is_object( $mycred ) || !isset( $request['user_id'] ) || !isset( $request['amount'] ) ) return false;
		if ( $request['amount'])
		if ( empty( $type ) ) $type = $mycred->get_cred_id();
		
		do_action( 'mycred_update_users_total', $request, $type, $mycred );
		
		$amount = $mycred->number( $request['amount'] );
		if ( $amount < 0 || $amount == 0 ) return;
		
		$user_id = $request['user_id'];
		$users_total = mycred_get_users_total( $user_id, $type );
		
		$new_total = $mycred->number( $users_total+$amount );
		update_user_meta( $user_id, $type . '_total', $new_total );
		
		return $new_total;
	}
}

/**
 * Apply Defaults
 * Based on the shortcode_atts() function with support for
 * multidimentional arrays.
 * @since 1.1.2
 * @version 1.0
 */
if ( !function_exists( 'mycred_apply_defaults' ) ) {
	function mycred_apply_defaults( &$pref, $set ) {
		$set = (array) $set;
		$return = array();
		foreach ( $pref as $key => $value ) {
			if ( array_key_exists( $key, $set ) ) {
				if ( is_array( $value ) && !empty( $value ) )
					$return[$key] = mycred_apply_defaults( $value, $set[$key] );
				else
					$return[$key] = $set[$key];
			}
			else $return[$key] = $value;
		}
		return $return;
	}
}

/**
 * Get Remote API Settings
 * @since 1.3
 * @version 1.0
 */
if ( !function_exists( 'mycred_get_remote' ) ) {
	function mycred_get_remote() {
		$defaults = apply_filters( 'mycred_remote_defaults', array(
			'enabled' => 0,
			'key'     => '',
			'uri'     => 'api-dev',
			'debug'   => 0
		) );
		return mycred_apply_defaults( $defaults, get_option( 'mycred_pref_remote', array() ) );
	}
}

/**
 * Is myCRED Ready
 * @since 1.3
 * @version 1.0
 */
function is_mycred_ready()
{
	global $mycred;
	$mycred = new myCRED_Settings();

	// By default we start with the main sites setup. If it is not a multisite installation
	// get_blog_option() will default to get_option() for us.
	if ( is_multisite() )
		$setup = get_blog_option( 1, 'mycred_setup_completed' );
	else
		$setup = get_option( 'mycred_setup_completed' );

	// If it is a multisite and the master template is not used, check if this site has
	// been installed
	if ( is_multisite() && $GLOBALS['blog_id'] > 1 && ! mycred_override_settings() )
		$setup = get_blog_option( $GLOBALS['blog_id'], 'mycred_setup_completed' );

	// Make sure that if we switch from central log to seperate logs, we install this
	// log if it does not exists.
	if ( is_multisite() && $GLOBALS['blog_id'] > 1 && ! mycred_centralize_log() ) {
		mycred_install_log( $mycred->core['format']['decimals'], $mycred->log_table );
	}

	// If setup is set, we are ready
	if ( $setup !== false ) return true;

	// If we have come this far we need to load the setup
	require_once( myCRED_INCLUDES_DIR . 'mycred-install.php' );
	$setup = new myCRED_Setup();
	return $setup->status();
}

/**
 * Install Log
 * Installs the log for a site.
 * Requires Multisite
 * @since 1.3
 * @version 1.1
 */
function mycred_install_log( $decimals = 0, $table = NULL )
{
	if ( is_multisite() && get_blog_option( $GLOBALS['blog_id'], 'mycred_version_db', false ) !== false ) return true;
	elseif ( ! is_multisite() && get_option( 'mycred_version_db', false ) !== false ) return true;

	global $wpdb;

	if ( $table === NULL ) {
		$mycred = mycred_get_settings();
		$table = $mycred->log_table;
	}

	if ( $decimals > 0 ) {
		if ( $decimals > 4 )
			$cred_format = "decimal(32,$decimals)";
		else
			$cred_format = "decimal(22,$decimals)";
	}
	else {
		$cred_format = 'bigint(22)';
	}

	// Log structure
	$sql = "id int(11) NOT NULL AUTO_INCREMENT, 
		ref VARCHAR(256) NOT NULL, 
		ref_id int(11) DEFAULT NULL, 
		user_id int(11) DEFAULT NULL, 
		creds $cred_format DEFAULT NULL, 
		ctype VARCHAR(64) DEFAULT 'mycred_default', 
		time bigint(20) DEFAULT NULL, 
		entry LONGTEXT DEFAULT NULL, 
		data LONGTEXT DEFAULT NULL, 
		PRIMARY KEY  (id), 
		UNIQUE KEY id (id)"; 

	// Insert table
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( "CREATE TABLE IF NOT EXISTS {$table} ( " . $sql . " );" );
	if ( is_multisite() )
		add_blog_option( $GLOBALS['blog_id'], 'mycred_version_db', '1.0' );
	else
		add_option( 'mycred_version_db', '1.0' );

	return true;
}
?>