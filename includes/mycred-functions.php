<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Settings class
 * @see http://codex.mycred.me/classes/mycred_settings/
 * @since 0.1
 * @version 1.4.1
 */
if ( ! class_exists( 'myCRED_Settings' ) ) :
	class myCRED_Settings {

		public $core;
		public $log_table;
		public $cred_id;

		public $is_multisite = false;
		public $use_master_template = false;
		public $use_central_logging = false;

		/**
		 * Construct
		 */
		function __construct( $type = 'mycred_default' ) {
			// Prep
			$this->is_multisite = is_multisite();
			$this->use_master_template = mycred_override_settings();
			$this->use_central_logging = mycred_centralize_log();

			// Load Settings
			$option_id = 'mycred_pref_core';
			if ( $type != 'mycred_default' && $type != '' )
				$option_id .= '_' . $type;

			$this->core = mycred_get_option( $option_id, $this->defaults() );
			
			if ( $this->core !== false ) {
				foreach ( (array) $this->core as $key => $value ) {
					$this->$key = $value;
				}
			}

			$this->cred_id = $type;

			if ( defined( 'MYCRED_LOG_TABLE' ) )
				$this->log_table = MYCRED_LOG_TABLE;
			else {
				global $wpdb;
				
				if ( $this->is_multisite && $this->use_central_logging )
					$this->log_table = $wpdb->base_prefix . 'myCRED_log';
				else
					$this->log_table = $wpdb->prefix . 'myCRED_log';
			}

			do_action_ref_array( 'mycred_settings', array( &$this ) );
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
					'type'       => 'bigint',
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
				'max'       => 0,
				'exclude'   => array(
					'plugin_editors' => 0,
					'cred_editors'   => 0,
					'list'           => ''
				),
				'frequency' => array(
					'rate'     => 'always',
					'date'     => ''
				),
				'delete_user' => 0
			);
		}

		/**
		 * Singular myCRED name
		 * @since 0.1
		 * @version 1.1
		 */
		public function singular() {
			if ( ! isset( $this->core['name']['singular'] ) )
				return $this->name['singular'];

			return $this->core['name']['singular'];
		}

		/**
		 * Plural myCRED name
		 * @since 0.1
		 * @version 1.1
		 */
		public function plural() {
			if ( ! isset( $this->core['name']['plural'] ) )
				return $this->name['plural'];

			return $this->core['name']['plural'];
		}

		/**
		 * Zero
		 * Returns zero formated with or without decimals.
		 * @since 1.3
		 * @version 1.0
		 */
		public function zero() {
			if ( ! isset( $this->format['decimals'] ) )
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
		 * @version 1.2
		 */
		public function number( $number = '' ) {
			if ( $number === '' ) return $number;

			if ( ! isset( $this->format['decimals'] ) )
				$decimals = (int) $this->core['format']['decimals'];
			else
				$decimals = (int) $this->format['decimals'];

			if ( $decimals > 0 )
				return number_format( (float) $number, $decimals, '.', '' );

			return (int) $number;
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

			return apply_filters( 'mycred_format_number', $creds, $number, $this->core );
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
			if ( ! empty( $this->before ) )
				$prefix = $this->before . ' ';

			// Suffix
			$suffix = '';
			if ( ! empty( $this->after ) )
				$suffix = ' ' . $this->after;

			// Format creds
			$creds = $this->format_number( $creds );

			// Optional extras to insert before and after
			if ( $force_in )
				$layout = $prefix . $before . $creds . $after . $suffix;
			else
				$layout = $before . $prefix . $creds . $suffix . $after;

			return apply_filters( 'mycred_format_creds', $layout, $creds, $this->core );
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
		 * @version 1.1
		 */
		public function round_value( $amount = 0, $up_down = false, $precision = 0 ) {
			if ( $amount == 0 || ! $up_down ) return $amount;

			// Use round() for precision
			if ( $precision !== false ) {
				if ( $up_down == 'up' )
					$_amount = round( $amount, (int) $precision, PHP_ROUND_HALF_UP );
				elseif ( $up_down == 'down' )
					$_amount = round( $amount, (int) $precision, PHP_ROUND_HALF_DOWN );
			}
			// Use ceil() or floor() for everything else
			else {
				if ( $up_down == 'up' )
					$_amount = ceil( $amount );
				elseif ( $up_down == 'down' )
					$_amount = floor( $amount );
			}

			return apply_filters( 'mycred_round_value', $_amount, $amount, $up_down, $precision );
		}

		/**
		 * Apply Exchange Rate
		 * Applies a given exchange rate to the given amount.
		 * 
		 * @param $amount (int|float) the initial amount
		 * @param $rate (int|float) the exchange rate to devide by
		 * @param $round (bool) option to round values, defaults to yes.
		 * @since 0.1
		 * @version 1.3
		 */
		public function apply_exchange_rate( $amount = 0, $rate = 1, $round = true ) {
			if ( ! is_numeric( $rate ) || $rate == 1 ) return $amount;

			$exchange = $amount/(float) $rate;
			if ( $round ) $exchange = round( $exchange );

			return apply_filters( 'mycred_apply_exchange_rate', $exchange, $amount, $rate, $round );
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
			if ( ! $this->has_tags( 'amount', 'cred|cred_f', $content ) ) return $content;
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
			if ( ! $this->has_tags( 'post', 'post_title|post_url|link_with_title|post_type', $content ) ) return $content;

			// Get Post Object
			$post = get_post( $ref_id );

			// Post does not exist
			if ( $post === NULL ) {
				if ( ! is_array( $data ) || ! array_key_exists( 'ID', $data ) ) return $content;
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
			if ( ! $this->has_tags( 'user', 'user_id|user_name|user_name_en|display_name|user_profile_url|user_profile_link|user_nicename|user_email|user_url|balance|balance_f', $content ) ) return $content;

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

			if ( ! isset( $user->ID ) ) return $content;

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
			if ( ! function_exists( 'mycred_get_users_rank' ) )
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
			if ( ! $this->has_tags( 'comment', 'comment_id|c_post_id|c_post_title|c_post_url|c_link_with_title', $content ) ) return $content;

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
			if ( ! preg_match( '%(' . trim( $tags ) . ')%', $content, $matches ) ) return false;
			return true;
		}

		/**
		 * Available Template Tags
		 * Based on an array of template tag types, a list of codex links
		 * are generted for each tag type.
		 * @since 1.4
		 * @version 1.0
		 */
		public function available_template_tags( $available = array(), $custom = '' ) {
			// Prep
			$links = $template_tags = array();

			// General
			if ( in_array( 'general', $available ) )
				$template_tags[] = array(
					'title' => __( 'General', 'mycred' ),
					'url'   => 'http://codex.mycred.me/category/template-tags/temp-general/'
				);

			// User
			if ( in_array( 'user', $available ) )
				$template_tags[] = array(
					'title' => __( 'User Related', 'mycred' ),
					'url'   => 'http://codex.mycred.me/category/template-tags/temp-user/'
				);

			// Post
			if ( in_array( 'post', $available ) )
				$template_tags[] = array(
					'title' => __( 'Post Related', 'mycred' ),
					'url'   => 'http://codex.mycred.me/category/template-tags/temp-post/'
				);

			// Comment
			if ( in_array( 'comment', $available ) )
				$template_tags[] = array(
					'title' => __( 'Comment Related', 'mycred' ),
					'url'   => 'http://codex.mycred.me/category/template-tags/temp-comment/'
				);

			// Widget
			if ( in_array( 'widget', $available ) )
				$template_tags[] = array(
					'title' => __( 'Widget Related', 'mycred' ),
					'url'   => 'http://codex.mycred.me/category/template-tags/temp-widget/'
				);

			// Amount
			if ( in_array( 'amount', $available ) )
				$template_tags[] = array(
					'title' => __( 'Amount Related', 'mycred' ),
					'url'   => 'http://codex.mycred.me/category/template-tags/temp-amount/'
				);

			// Video
			if ( in_array( 'video', $available ) )
				$template_tags[] = array(
					'title' => __( 'Video Related', 'mycred' ),
					'url'   => 'http://codex.mycred.me/category/template-tags/temp-amount/'
				);

			if ( ! empty( $template_tags ) ) {
				foreach ( $template_tags as $tag ) {
					$links[] = '<a href="' . $tag['url'] . '" target="_blank">' . $tag['title'] . '</a>';
				}
			}

			if ( ! empty( $custom ) )
				$custom = ' ' . __( 'and', 'mycred' ) . ' ' . $custom;

			return __( 'Available Template Tags:', 'mycred' ) . ' ' . implode( ', ', $links ) . $custom . '.';
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
		 * @version 1.1
		 */
		public function allowed_tags( $data = '', $allow = '' ) {
			if ( $allow === false )
				return strip_tags( $data );
			elseif ( ! empty( $allow ) )
				return strip_tags( $data, $allow );

			return strip_tags( $data, apply_filters( 'mycred_allowed_tags', '<a><br><em><strong><span>' ) );
		}

		/**
		 * Edit Creds Cap
		 * Returns the set edit creds capability.
		 *
		 * @returns capability (string)
		 * @since 0.1
		 * @version 1.1
		 */
		public function edit_creds_cap() {
			if ( ! isset( $this->caps['creds'] ) || empty( $this->caps['creds'] ) )
				$this->caps['creds'] = 'delete_users';

			return apply_filters( 'mycred_edit_creds_cap', $this->caps['creds'] );
		}

		/**
		 * Can Edit Creds
		 * Check if user can edit other users creds. If no user id is given
		 * we will attempt to get the current users id.
		 *
		 * @param $user_id (int) user id
		 * @returns true or false
		 * @since 0.1
		 * @version 1.1
		 */
		public function can_edit_creds( $user_id = '' ) {
			$result = false;

			if ( ! function_exists( 'get_current_user_id' ) )
				require_once( ABSPATH . WPINC . '/user.php' );

			// Grab current user id
			if ( empty( $user_id ) )
				$user_id = get_current_user_id();

			if ( ! function_exists( 'user_can' ) )
				require_once( ABSPATH . WPINC . '/capabilities.php' );

			// Check if user can
			if ( user_can( $user_id, $this->edit_creds_cap() ) )
				$result = true;

			return apply_filters( 'mycred_can_edit_creds', $result, $user_id );
		}

		/**
		 * Edit Plugin Cap
		 * Returns the set edit plugin capability.
		 *
		 * @returns capability (string)
		 * @since 0.1
		 * @version 1.1
		 */
		public function edit_plugin_cap() {
			if ( ! isset( $this->caps['plugin'] ) || empty( $this->caps['plugin'] ) )
				$this->caps['plugin'] = 'manage_options';

			return apply_filters( 'mycred_edit_plugin_cap', $this->caps['plugin'] );
		}

		/**
		 * Can Edit This Plugin
		 * Checks if a given user can edit this plugin. If no user id is given
		 * we will attempt to get the current users id.
		 *
		 * @param $user_id (int) user id
		 * @returns true or false
		 * @since 0.1
		 * @version 1.1
		 */
		public function can_edit_plugin( $user_id = '' ) {
			$result = false;

			if ( ! function_exists( 'get_current_user_id' ) )
				require_once( ABSPATH . WPINC . '/user.php' );

			// Grab current user id
			if ( empty( $user_id ) )
				$user_id = get_current_user_id();

			if ( ! function_exists( 'user_can' ) )
				require_once( ABSPATH . WPINC . '/capabilities.php' );

			// Check if user can
			if ( user_can( $user_id, $this->edit_plugin_cap() ) )
				$result = true;
			
			return apply_filters( 'mycred_can_edit_plugin', $result, $user_id );
		}

		/**
		 * Check if user id is in exclude list
		 * @return true or false
		 * @since 0.1
		 * @version 1.1
		 */
		public function in_exclude_list( $user_id = '' ) {
			$result = false;

			// Grab current user id
			if ( empty( $user_id ) )
				$user_id = get_current_user_id();

			if ( ! isset( $this->exclude['list'] ) )
				$this->exclude['list'] = '';

			$list = wp_parse_id_list( $this->exclude['list'] );
			if ( in_array( $user_id, $list ) )
				$result = true;

			return apply_filters( 'mycred_is_excluded_list', $result, $user_id );
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
		 * @version 1.0.1
		 */
		public function exclude_user( $user_id = 0 ) {
			if ( apply_filters( 'mycred_exclude_user', false, $user_id ) === true ) return true;
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
			if ( ! isset( $this->cred_id ) || empty( $this->cred_id ) )
				$this->cred_id = 'mycred_default';

			return $this->cred_id;
		}

		/**
		 * Get Max
		 * @since 1.3
		 * @version 1.0
		 */
		public function max() {
			if ( ! isset( $this->max ) )
				$this->max = 0;

			return $this->max;
		}

		/**
		 * Get users creds
		 * Returns the users creds unformated.
		 *
		 * @param $user_id (int), required user id
		 * @param $type (string), optional cred type to check for
		 * @returns zero if user id is not set or if no creds were found, else returns amount
		 * @since 0.1
		 * @version 1.3
		 */
		public function get_users_cred( $user_id = '', $type = '' ) {
			if ( empty( $user_id ) ) return $this->zero();

			if ( empty( $type ) ) $type = $this->get_cred_id();

			// Handle multisites without centralized log
			if ( $this->is_multisite && $GLOBALS['blog_id'] > 1 && ! $this->use_central_logging )
				$type .= '_' . $GLOBALS['blog_id'];

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
		 * @version 1.3
		 */
		public function update_users_balance( $user_id = NULL, $amount = NULL, $type = 'mycred_default' ) {
			if ( $user_id === NULL || $amount === NULL ) return $amount;
			if ( empty( $type ) ) $type = $this->get_cred_id();

			// Enforce max
			if ( $this->max() > $this->zero() && $amount > $this->max() ) {
				$amount = $this->number( $this->max() );

				do_action( 'mycred_max_enforced', $user_id, $_amount, $this->max() );
			}

			// Adjust creds
			$current_balance = $this->get_users_cred( $user_id, $type );
			$new_balance = $current_balance+$amount;

			// Handle multisites without centralized log
			if ( $this->is_multisite && $GLOBALS['blog_id'] > 1 && ! $this->use_central_logging )
				$type .= '_' . $GLOBALS['blog_id'];

			// Update creds
			update_user_meta( $user_id, $type, $new_balance );

			// Let others play
			do_action( 'mycred_update_user_balance', $user_id, $current_balance, $amount, $type );

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
		 * @version 1.4
		 */
		public function add_creds( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' ) {
			// All the reasons we would fail
			if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
			if ( $this->exclude_user( $user_id ) === true ) return false;

			// Format creds
			$amount = $this->number( $amount );
			if ( $amount == $this->zero() || $amount == 0 ) return false;

			// Enforce max
			if ( $this->max() > $this->zero() && $amount > $this->max() ) {
				$amount = $this->number( $this->max() );

				do_action( 'mycred_max_enforced', $user_id, $_amount, $this->max() );
			}

			// Execution Override
			// Let others play before awarding points.
			// Your functions should return the answer to the question: "Should myCRED adjust the users point balance?"
			$execute = apply_filters( 'mycred_add', true, compact( 'ref', 'user_id', 'amount', 'entry', 'ref_id', 'data', 'type' ), $this );

			// Acceptable answers:
			// true (boolean)  - "Yes" let myCRED add points and log the event
			if ( $execute === true ) {
				$this->update_users_balance( $user_id, $amount, $type );
				
				if ( ! empty( $entry ) )
					$this->add_to_log( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );

				// Update rankings
				//if ( $this->frequency['rate'] == 'always' )
				//	$this->update_rankings();

				return true;
			}
			// done (string)   - "Already done"
			elseif ( $execute === 'done' ) {
				// Update rankings
				//if ( $this->frequency['rate'] == 'always' )
				//	$this->update_rankings();

				return true;
			}

			return false;
		}

		/**
		 * Update Rankings (no longer used)
		 * Updates the rankings for a given points type.
		 *
		 * @param $force (bool), if rankings are updated on a set interval, this option can override
		 * and force a new setting to be saved.
		 * @param $type (string), optional points type
		 * @since 1.1.2
		 * @version 1.0
		 */
		public function update_rankings( $force = false, $type = 'mycred_default' ) { }

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
			if ( $amount == $this->zero() || $amount == 0 ) return false;

			global $wpdb;

			// Strip HTML from log entry
			$entry = $this->allowed_tags( $entry );

			// Enforce max
			if ( $this->max() > $this->zero() && $amount > $this->max() ) {
				$amount = $this->number( $this->max() );
			}

			// Type
			if ( empty( $type ) ) $type = $this->get_cred_id();

			// Creds format
			if ( $this->format['decimals'] > 0 )
				$format = '%f';
			elseif ( $this->format['decimals'] == 0 )
				$format = '%d';
			else
				$format = '%s';

			$time = apply_filters( 'mycred_log_time', date_i18n( 'U' ), $ref, $user_id, $amount, $entry, $ref_id, $data, $type );

			// Insert into DB
			$new_entry = $wpdb->insert(
				$this->log_table,
				array(
					'ref'     => $ref,
					'ref_id'  => $ref_id,
					'user_id' => (int) $user_id,
					'creds'   => $amount,
					'ctype'   => $type,
					'time'    => $time,
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
			if ( ! $new_entry ) return false;
			
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
		 * @version 1.3
		 */
		function has_entry( $reference = '', $ref_id = '', $user_id = '', $data = '', $type = '' ) {
			global $wpdb;

			$where = $prep = array();
			if ( ! empty( $reference ) ) {
				$where[] = 'ref = %s';
				$prep[] = $reference;
			}

			if ( ! empty( $ref_id ) ) {
				$where[] = 'ref_id = %d';
				$prep[] = $ref_id;
			}

			if ( ! empty( $user_id ) ) {
				$where[] = 'user_id = %d';
				$prep[] = abs( $user_id );
			}

			if ( ! empty( $data ) ) {
				$where[] = 'data = %s';
				$prep[] = maybe_serialize( $data );
			}

			if ( empty( $type ) )
				$type = $this->cred_id;

			$where[] = 'ctype = %s';
			$prep[] = sanitize_text_field( $type );

			$where = implode( ' AND ', $where );

			$has = false;
			if ( ! empty( $where ) ) {
				$sql = "SELECT * FROM {$this->log_table} WHERE {$where};";
				$wpdb->get_results( $wpdb->prepare( $sql, $prep ) );
				if ( $wpdb->num_rows > 0 )
					$has = true;
			}

			return apply_filters( 'mycred_has_entry', $has, $reference, $ref_id, $user_id, $data );
		}
	}
endif;

/**
 * myCRED Label
 * Returns the myCRED Label
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_label' ) ) :
	function mycred_label( $trim = false )
	{
		global $mycred_label;
		if ( ! isset( $mycred_label ) || empty( $mycred_label ) )
			$name = apply_filters( 'mycred_label', myCRED_NAME );

		if ( $trim )
			$name = strip_tags( $name );

		return $name;
	}
endif;

/**
 * Get myCRED
 * Returns myCRED's general settings and core functions.
 * Replaces mycred_get_settings()
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred' ) ) :
	function mycred( $type = 'mycred_default' )
	{
		if ( $type != 'mycred_default' )
			return new myCRED_Settings( $type );

		global $mycred;

		if ( ! isset( $mycred ) || ! is_object( $mycred ) )
			$mycred = new myCRED_Settings();

		return $mycred;
	}
endif;

/**
 * Get Cred Types
 * Returns an associative array of registered point types.
 * @since 1.4
 * @version 1.1
 */
if ( ! function_exists( 'mycred_get_types' ) ) :
	function mycred_get_types() {
		$types = array();

		$available_types = mycred_get_option( 'mycred_types', array( 'mycred_default' => mycred_label() ) );
		if ( count( $available_types ) > 1 ) {
			foreach ( $available_types as $type => $label ) {
				if ( $type == 'mycred_default' ) {
					$_mycred = mycred( $type );
					$label = $_mycred->plural();
				}

				$types[ $type ] = $label;
			}
		}
		else {
			$types = $available_types;
		}

		return apply_filters( 'mycred_types', $types );
	}
endif;

/**
 * Select Point Type from Select Dropdown
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_types_select_from_dropdown' ) ) :
	function mycred_types_select_from_dropdown( $name = '', $id = '', $selected = '', $return = false, $extra = '' ) {
		$types = mycred_get_types();

		$output = '';
		if ( count( $types ) == 1 ) {
			$output .= '<input type="hidden"' . $extra . ' name="' . $name . '" id="' . $id . '" value="mycred_default" />';
		}
		else {
			$output .= '
<select' . $extra . ' name="' . $name . '" id="' . $id . '">';
			foreach ( $types as $type => $label ) {
				if ( $type == 'mycred_default' ) {
					$_mycred = mycred( $type );
					$label = $_mycred->plural();
				}
				$output .= '<option value="' . $type . '"';
				if ( $selected == $type ) $output .= ' selected="selected"';
				$output .= '>' . $label . '</option>';
			}
			$output .= '
</select>';
		}

		if ( $return )
			return $output;

		echo $output;
	}
endif;

/**
 * Select Point Type from Checkboxes
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_types_select_from_checkboxes' ) ) :
	function mycred_types_select_from_checkboxes( $name = '', $id = '', $selected_values = array(), $return = false ) {
		$types = mycred_get_types();

		$output = '';
		if ( count( $types ) > 0 ) {
			foreach ( $types as $type => $label ) {
				$selected = '';
				if ( in_array( $type, (array) $selected_values ) )
					$selected = ' checked="checked"';

				$id .= '-' . $type;

				$output .= '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="' . $type . '"' . $selected . ' /><label for="' . $id . '">' . $label . '</label><br />';
			}
		}

		if ( $return )
			return $output;

		echo $output;
	}
endif;

/**
 * Get Network Settings
 * Returns myCRED's network settings or false if multisite is not enabled.
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_settings_network' ) ) :
	function mycred_get_settings_network()
	{
		if ( ! is_multisite() ) return false;

		$defaults = array(
			'master'  => 0,
			'central' => 0,
			'block'   => ''
		);
		$settings = get_blog_option( 1, 'mycred_network', $defaults );

		return $settings;
	}
endif;

/**
 * Override Settings
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_override_settings' ) ) :
	function mycred_override_settings()
	{
		// Not a multisite
		if ( ! is_multisite() ) return false;

		$mycred_network = mycred_get_settings_network();
		if ( $mycred_network['master'] ) return true;

		return false;
	}
endif;

/**
 * Get Option
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_option' ) ) :
	function mycred_get_option( $option_id, $default = array() ) {
		if ( is_multisite() ) {
			if ( mycred_override_settings() )
				$settings = get_blog_option( 1, $option_id, $default );
			else
				$settings = get_blog_option( $GLOBALS['blog_id'], $option_id, $default );
		}
		else {
			$settings = get_option( $option_id, $default );
		}

		return $settings;
	}
endif;

/**
 * Update Option
 * Used to make sure settings that are encompased by
 * the MU Master Template feature are saved correctly.
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_update_option' ) ) :
	function mycred_update_option( $option_id, $value = '' ) {
		if ( is_multisite() ) {
			if ( mycred_override_settings() )
				update_blog_option( 1, $option_id, $value );
			else
				update_blog_option( $GLOBALS['blog_id'], $option_id, $value );
		}
		else {
			update_option( $option_id, $value );
		}
	}
endif;

/**
 * Centralize Log
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_centralize_log' ) ) :
	function mycred_centralize_log()
	{
		// Not a multisite
		if ( ! is_multisite() ) return true;

		$mycred_network = mycred_get_settings_network();
		if ( $mycred_network['central'] ) return true;

		return false;
	}
endif;

/**
 * Get myCRED Name
 * Returns the name given to creds.
 * @param $signular (boolean) option to return the plural version, returns singular by default
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_name' ) ) :
	function mycred_name( $singular = true )
	{
		$mycred = mycred();
		if ( $singular )
			return $mycred->singular();
		else
			return $mycred->plural();
	}
endif;

/**
 * Strip Tags
 * Strippes HTML tags from a given string.
 * @param $string (string) string to stip
 * @param $overwrite (string), optional HTML tags to allow
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_strip_tags' ) ) :
	function mycred_strip_tags( $string = '', $overwride = '' )
	{
		$mycred = mycred();
		return $mycred->allowed_tags( $string, $overwrite );
	}
endif;

/**
 * Is Admin
 * Conditional tag that checks if a given user or the current user
 * can either edit the plugin or creds.
 * @param $user_id (int), optional user id to check, defaults to current user
 * @returns true or false
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_is_admin' ) ) :
	function mycred_is_admin( $user_id = NULL )
	{
		$mycred = mycred();
		if ( $user_id === NULL ) $user_id = get_current_user_id();

		if ( $mycred->can_edit_creds( $user_id ) || $mycred->can_edit_plugin( $user_id ) ) return true;

		return false;
	}
endif;

/**
 * Exclude User
 * Checks if a given user is excluded from using myCRED.
 * @see http://codex.mycred.me/functions/mycred_exclude_user/
 * @param $user_id (int), optional user to check, defaults to current user
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_exclude_user' ) ) :
	function mycred_exclude_user( $user_id = NULL )
	{
		$mycred = mycred();
		if ( $user_id === NULL ) $user_id = get_current_user_id();
		return $mycred->exclude_user( $user_id );
	}
endif;

/**
 * Get Users Creds
 * Returns the given users current cred balance. If no user id is given this function
 * will default to the current user!
 * @param $user_id (int) user id
 * @return users balance (int|float)
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_users_cred' ) ) :
	function mycred_get_users_cred( $user_id = NULL, $type = '' )
	{
		if ( $user_id === NULL ) $user_id = get_current_user_id();

		$mycred = mycred();
		return $mycred->get_users_cred( $user_id, $type );
	}
endif;

/**
 * Get Users Creds Formated
 * Returns the given users current cred balance formated. If no user id is given
 * this function will return false!
 * @param $user_id (int), required user id
 * @return users balance (string) or false if no user id is given
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_users_fcred' ) ) :
	function mycred_get_users_fcred( $user_id = NULL, $type = '' )
	{
		if ( $user_id === NULL ) return false;

		$mycred = mycred();
		$cred = $mycred->get_users_cred( $user_id, $type );
		return $mycred->format_creds( $cred );
	}
endif;

/**
 * Flush Widget Cache
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_flush_widget_cache' ) ) :
	function mycred_flush_widget_cache( $id = NULL )
	{
		if ( $id === NULL ) return;
		wp_cache_delete( $id, 'widget' );
	}
endif;

/**
 * Format Number
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_format_number' ) ) :
	function mycred_format_number( $value = NULL )
	{
		$mycred = mycred();
		if ( $value === NULL )
			return $mycred->zero();

		return $mycred->format_number( $value );
	}
endif;

/**
 * Format Creds
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_format_creds' ) ) :
	function mycred_format_creds( $value = NULL )
	{
		$mycred = mycred();
		if ( $value === NULL ) $mycred->zero();

		return $mycred->format_creds( $value );
	}
endif;

/**
 * Add Creds
 * Adds creds to a given user. A refernece ID, user id and amount must be given.
 * Important! This function will not check if the user should be excluded from gaining points, this must
 * be done before calling this function!
 *
 * @see http://codex.mycred.me/functions/mycred_add/
 * @param $ref (string), required reference id
 * @param $user_id (int), required id of the user who will get these points
 * @param $amount (int|float), required number of creds to give or deduct from the given user.
 * @param $ref_id (array), optional array of reference IDs allowing the use of content specific keywords in the log entry
 * @param $data (object|array|string|int), optional extra data to save in the log. Note that arrays gets serialized!
 * @returns boolean true on success or false on fail
 * @since 0.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_add' ) ) :
	function mycred_add( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' )
	{
		// $ref, $user_id and $cred is required
		if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;

		$mycred = mycred();
		if ( empty( $type ) ) $type = $mycred->get_cred_id();

		// Add creds
		return $mycred->add_creds( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );
	}
endif;

/**
 * Subtract Creds
 * Subtracts creds from a given user. Works just as mycred_add() but the creds are converted into a negative value.
 * @see http://codex.mycred.me/functions/mycred_subtract/
 * @uses mycred_add()
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'mycred_subtract' ) ) :
	function mycred_subtract( $ref = '', $user_id = '', $amount = '', $entry = '', $ref_id = '', $data = '', $type = 'mycred_default' )
	{
		if ( empty( $ref ) || empty( $user_id ) || empty( $amount ) ) return false;
		if ( (int) $amount > 0 ) $amount = 0-$amount;
		return mycred_add( $ref, $user_id, $amount, $entry, $ref_id, $data, $type );
	}
endif;

/**
 * Get Log Exports
 * Returns an associative array of log export options.
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_log_exports' ) ) :
	function mycred_get_log_exports()
	{
		return apply_filters( 'mycred_log_exports', array(
			'all' => array(
				'label' => __( 'Entire Log', 'mycred' ),
				'my_label' => '',
				'class' => 'btn btn-primary button button-secondary'
			),
			'display'  => array(
				'label' => __( 'Displayed Rows', 'mycred' ),
				'my_label' => __( 'Displayed Rows', 'mycred' ),
				'class' => 'btn btn-default button button-secondary'
			),
			'search'     => array(
				'label' => __( 'Search Results', 'mycred' ),
				'my_label' => __( 'My Entire Log', 'mycred' ),
				'class' => 'btn btn-default button button-secondary'
			)
		) );
	}
endif;

/**
 * Count Reference Instances
 * Counts the total number of occurrences of a specific reference for a user.
 * @see http://codex.mycred.me/functions/mycred_count_ref_instances/
 * @param $reference (string) required reference to check
 * @param $user_id (int) option to check references for a specific user
 * @uses get_var()
 * @since 1.1
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_count_ref_instances' ) ) :
	function mycred_count_ref_instances( $reference = '', $user_id = NULL, $type = 'mycred_default' )
	{
		if ( empty( $reference ) ) return '';

		$mycred = mycred( $type );

		global $wpdb;

		if ( $user_id !== NULL ) {
			return $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$mycred->log_table} WHERE ref = %s AND user_id = %d AND ctype = %s;",
				$reference,
				$user_id,
				$type
			) );
		}

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$mycred->log_table} WHERE ref = %s;", $reference ) );
	}
endif;

/**
 * Count All Reference Instances
 * Counts all the reference instances in the log returning the result
 * in an assosiative array.
 * @see http://codex.mycred.me/functions/mycred_count_all_ref_instances/
 * @param $number (int) number of references to return. Defaults to 5. Use '-1' for all.
 * @param $order (string) order to return ASC or DESC
 * @filter mycred_count_all_refs
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_count_all_ref_instances' ) ) :
	function mycred_count_all_ref_instances( $number = 5, $order = 'DESC', $type = 'mycred_default' )
	{
		global $wpdb;
		$mycred = mycred();

		if ( $number == '-1' )
			$limit = '';
		else
			$limit = ' LIMIT 0,' . abs( $number );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		if ( $type != 'all' )
			$type = " WHERE ctype = '{$type}'";
		else
			$type = '';

		$query = $wpdb->get_results( "SELECT ref, COUNT(*) AS count FROM {$mycred->log_table} {$type} GROUP BY ref ORDER BY count {$order} {$limit};" );

		$results = array();
		if ( $wpdb->num_rows > 0 ) {
			foreach ( $query as $num => $reference ) {
				$occurrence = $reference->count;
				if ( $reference->ref == 'transfer' )
					$occurrence = $occurrence/2;

				$results[ $reference->ref ] = $occurrence;
			}
			arsort( $results );
		}

		return apply_filters( 'mycred_count_all_refs', $results );
	}
endif;

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
 * @version 1.2
 */
if ( ! function_exists( 'mycred_get_total_by_time' ) ) :
	function mycred_get_total_by_time( $from = 'today', $to = 'now', $ref = NULL, $user_id = NULL, $type = 'mycred_default' )
	{
		// Get myCRED
		$mycred = mycred( $type );

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
			if ( ! is_int( $user_id ) ) return __( 'incorrect user id format', 'mycred' );

			$wheres[] = 'user_id = %d';
			$prep[] = $user_id;
		}

		// Default from start of today
		if ( $from == 'today' ) {
			$today = date_i18n( 'Y/m/d 00:00:00' );
			$from = strtotime( $today );
		}

		// From
		if ( ! is_numeric( $from ) ) return __( 'incorrect unix timestamp (from):', 'mycred' ) . ' ' . $from;
		$wheres[] = 'time >= %d';
		$prep[] = $from;

		// Default to is now
		if ( $to == 'now' )
			$to = date_i18n( 'U' );

		// To
		if ( ! is_numeric( $to ) ) return __( 'incorrect unix timestamp (to):', 'mycred' ) . ' ' . $to;
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

		// Query
		$query = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM( creds ) 
			FROM {$mycred->log_table} 
			WHERE {$where} 
			ORDER BY time;", $prep ) );

		if ( $query === NULL || $query == 0 )
			return $mycred->zero();

		return $mycred->number( $query );
	}
endif;

/**
 * Get users total creds
 * Returns the users total creds unformated. If no total is fuond,
 * the users current balance is returned instead.
 *
 * @param $user_id (int), required user id
 * @param $type (string), optional cred type to check for
 * @returns zero if user id is not set or if no total were found, else returns creds
 * @since 1.2
 * @version 1.3
 */
if ( ! function_exists( 'mycred_get_users_total' ) ) :
	function mycred_get_users_total( $user_id = '', $type = 'mycred_default' )
	{
		if ( $user_id == '' ) return 0;

		if ( $type == '' ) $type = 'mycred_default';
		$mycred = mycred( $type );

		$key = $type;
		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_central_logging )
			$key .= '_' . $GLOBALS['blog_id'];

		$key .= '_total';

		$total = get_user_meta( $user_id, $key, true );
		if ( $total == '' ) {
			$total = mycred_query_users_total( $user_id, $type );
			update_user_meta( $user_id, $key, $total );
		}

		$total = apply_filters( 'mycred_get_users_total', $total, $user_id, $type, $key );
		return $mycred->number( $total );
	}
endif;

/**
 * Query Users Total
 * Queries the database for the users total acculimated points.
 *
 * @param $user_id (int), required user id
 * @param $type (string), required point type
 * @since 1.4.7
 * @version 1.0
 */
if ( ! function_exists( 'mycred_query_users_total' ) ) :
	function mycred_query_users_total( $user_id, $type = 'mycred_default' )
	{
		global $wpdb;

		$mycred = mycred( $type );

		$total = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM( creds ) 
			FROM {$mycred->log_table} 
			WHERE user_id = %d
				AND ( ( creds > 0 ) OR ( creds < 0 AND ref = 'manual' ) )
				AND ctype = %s;", $user_id, $type ) );

		if ( $total === NULL )
			$total = 0;

		return apply_filters( 'mycred_query_users_total', $total, $user_id, $type, $mycred );
	}
endif;

/**
 * Update users total creds
 * Updates a given users total creds balance.
 *
 * @param $user_id (int), required user id
 * @param $request (array), required request array with information on users id (user_id) and amount
 * @param $mycred (myCRED_Settings object), required myCRED settings object
 * @returns zero if user id is not set or if no total were found, else returns total
 * @since 1.2
 * @version 1.3
 */
if ( ! function_exists( 'mycred_update_users_total' ) ) :
	function mycred_update_users_total( $type = 'mycred_default', $request = NULL, $mycred = NULL )
	{
		if ( $request === NULL || ! is_object( $mycred ) || ! isset( $request['user_id'] ) || ! isset( $request['amount'] ) ) return false;

		if ( $type == '' )
			$type = $mycred->get_cred_id();

		$key = $type;
		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_central_logging )
			$key .= '_' . $GLOBALS['blog_id'];

		$key .= '_total';

		$amount = $mycred->number( $request['amount'] );
		$user_id = absint( $request['user_id'] );

		$users_total = get_user_meta( $user_id, $key, true );
		if ( $users_total == '' )
			$users_total = mycred_query_users_total( $user_id, $type );

		$new_total = $mycred->number( $users_total+$amount );
		update_user_meta( $user_id, $key, $new_total );

		return apply_filters( 'mycred_update_users_total', $new_total, $type, $request, $mycred );
	}
endif;

/**
 * Apply Defaults
 * Based on the shortcode_atts() function with support for
 * multidimentional arrays.
 * @since 1.1.2
 * @version 1.0
 */
if ( ! function_exists( 'mycred_apply_defaults' ) ) :
	function mycred_apply_defaults( &$pref, $set )
	{
		$set = (array) $set;
		$return = array();
		foreach ( $pref as $key => $value ) {
			if ( array_key_exists( $key, $set ) ) {
				if ( is_array( $value ) && ! empty( $value ) )
					$return[ $key ] = mycred_apply_defaults( $value, $set[ $key ] );
				else
					$return[ $key ] = $set[ $key ];
			}
			else $return[ $key ] = $value;
		}
		return $return;
	}
endif;

/**
 * Get Remote API Settings
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'mycred_get_remote' ) ) :
	function mycred_get_remote()
	{
		$defaults = apply_filters( 'mycred_remote_defaults', array(
			'enabled' => 0,
			'key'     => '',
			'uri'     => 'api-dev',
			'debug'   => 0
		) );
		return mycred_apply_defaults( $defaults, get_option( 'mycred_pref_remote', array() ) );
	}
endif;

/**
 * Is myCRED Ready
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'is_mycred_ready' ) ) :
	function is_mycred_ready()
	{
		global $mycred;
		$mycred = new myCRED_Settings();

		// By default we start with the main sites setup. If it is not a multisite installation
		// get_blog_option() will default to get_option() for us.
		if ( $mycred->is_multisite )
			$setup = get_blog_option( 1, 'mycred_setup_completed', false );
		else
			$setup = get_option( 'mycred_setup_completed', false );

		// If it is a multisite and the master template is not used, check if this site has
		// been installed
		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_master_template )
			$setup = get_blog_option( $GLOBALS['blog_id'], 'mycred_setup_completed' );

		// Make sure that if we switch from central log to seperate logs, we install this
		// log if it does not exists.
		if ( $mycred->is_multisite && $GLOBALS['blog_id'] > 1 && ! $mycred->use_master_template )
			mycred_install_log( $mycred->core['format']['decimals'], $mycred->log_table );

		// If setup is set, we are ready
		if ( $setup !== false ) return true;

		// If we have come this far we need to load the setup
		require_once( myCRED_INCLUDES_DIR . 'mycred-install.php' );
		$setup = new myCRED_Setup();
		return $setup->status();
	}
endif;

/**
 * Install Log
 * Installs the log for a site.
 * Requires Multisite
 * @since 1.3
 * @version 1.2
 */
if ( ! function_exists( 'mycred_install_log' ) ) :
	function mycred_install_log( $decimals = 0, $table = NULL )
	{
		if ( is_multisite() && get_blog_option( $GLOBALS['blog_id'], 'mycred_version_db', false ) !== false ) return true;
		elseif ( ! is_multisite() && get_option( 'mycred_version_db', false ) !== false ) return true;

		global $wpdb;

		if ( $table === NULL ) {
			$mycred = mycred();
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

		$wpdb->hide_errors();

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			if ( ! empty( $wpdb->charset ) )
				$collate .= "DEFAULT CHARACTER SET {$wpdb->charset}";
			if ( ! empty( $wpdb->collate ) )
				$collate .= " COLLATE {$wpdb->collate}";
		}

		// Log structure
		$sql = "
			id int(11) NOT NULL AUTO_INCREMENT, 
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
		dbDelta( "CREATE TABLE IF NOT EXISTS {$table} ( " . $sql . " ) $collate;" );
		if ( is_multisite() )
			add_blog_option( $GLOBALS['blog_id'], 'mycred_version_db', '1.0' );
		else
			add_option( 'mycred_version_db', '1.0' );

		return true;
	}
endif;
?>