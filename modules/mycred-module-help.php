<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;
/**
 * myCRED_Help class
 * Adds contextual help for myCRED pages and features.
 * @since 0.1
 * @version 1.0.1
 */
if ( !class_exists( 'myCRED_Help' ) ) {
	class myCRED_Help {

		public $is_admin;
		public $core;

		/**
		 * Construct
		 */
		function __construct() {
			if ( is_admin() )
				$this->is_admin = true;
			else
				$this->is_admin = false;
		}

		/**
		 * Hook Into Contextual Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function load() {
			if ( $this->is_admin ) return;
			add_filter( 'contextual_help', array( $this, 'run' ), 10, 3 );
		}

		/**
		 * Run Appropriate Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function run( $contextual_help, $screen_id, $screen ) {
			$this->core = mycred_get_settings();
			if ( $screen_id == 'toplevel_page_myCRED' )
				$this->log_page( $screen );
			elseif ( $screen_id == 'mycred_page_myCRED_page_hooks' )
				$this->hooks_page( $screen );
			elseif ( $screen_id == 'widgets' )
				$this->widgets( $screen );
			elseif ( $screen_id == 'user-edit' || $screen_id == 'profile' )
				$this->users( $screen );
			elseif ( $screen_id == 'mycred_page_myCRED_page_settings' )
				$this->settings_page( $screen );
			
			do_action( 'mycred_help', $screen_id, $screen );
			
			return $contextual_help;
		}

		/**
		 * Log Page Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function log_page( $screen ) {
			$screen->add_help_tab( array(
				'id'		=> 'mycred-log',
				'title'		=> __( 'The Log', 'mycred' ),
				'content'	=> '
<p>' . $this->core->template_tags_general( __( 'myCRED logs everything giving you a complete overview of %_plural% awarded or deducted from your users. The Log page can be filtered by user, date or reference and we have included a search function for you.', 'mycred' ) ) . '</p>
<p>' . __( 'You can select how many log entries you want to show under "Screen Options". By default you will be shown 10 entires.', 'mycred' ) . '</p>
<p><strong>' . __( 'Filter by Date', 'mycred' ) . '</strong></p>
<p>' . __( 'You can select to show log entries for: Today, Yesterday, This Week or This Month.', 'mycred' ) . '</p>
<p><strong>' . __( 'Filter by Reference', 'mycred' ) . '</strong></p>
<p>' . __( 'Each time a log entry is made a reference is used to identify where or why points were awarded or deducted.', 'mycred' ) . '</p>
<p><strong>' . __( 'Filter by User', 'mycred' ) . '</strong></p>
<p>' . __( 'You can select to show log entries for a specific user. Users with no log entries are not included.', 'mycred' ) . '</p>'
			) );
		}

		/**
		 * Hook Page Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function hooks_page( $screen ) {
			$screen->add_help_tab( array(
				'id'		=> 'mycred-hooks',
				'title'		=> __( 'Hooks', 'mycred' ),
				'content'	=> '
<p>' . $this->core->template_tags_general( __( 'Each instance where users might gain or loose %_plural%, are called hooks. Hooks can relate to WordPress specific actions or any third party plugin action that myCRED supports.', 'mycred' ) ) . '</p>
<p>' . $this->core->template_tags_general( __( 'A hook can relate to a specific instance or several instances. You can disable specific instances in a hook by awarding zero %_plural%.', 'mycred' ) ) . '</p>'
			) );
			$screen->add_help_tab( array(
				'id'		=> 'mycred-hooks-others',
				'title'		=> __( 'Third Party Plugins', 'mycred' ),
				'content'	=> '
<p>' . __( 'myCRED supports several third party plugins by default. These hooks are only available / visible if the plugin has been installed and enabled.', 'mycred' ) . '</p>
<p><strong>' . __( 'Supported Plugins:', 'mycred' ) . '</strong></p>
<ul>
<li><a href="http://wordpress.org/extend/plugins/contact-form-7/" target="_blank">Contact Form 7</a></li>
<li><a href="http://wordpress.org/extend/plugins/invite-anyone/" target="_blank">Invite Anyone Plugin</a></li>
</ul>'
			) );
			$screen->add_help_tab( array(
				'id'		=> 'mycred-hooks-template-tags',
				'title'		=> __( 'Template Tags', 'mycred' ),
				'content'	=> '
<p><strong>' . __( 'General:', 'mycred' ) . '</strong></p>
<p><code>%singular%</code> ' . $this->core->template_tags_general( __( 'Singular %plural% Name.', 'mycred' ) ) . '<br />
<code>%_singular%</code> ' . $this->core->template_tags_general( __( 'Singular %plural% Name in lowercase.', 'mycred' ) ) . '<br />
<code>%plural%</code> ' . $this->core->template_tags_general( __( 'Plural %plural% Name.', 'mycred' ) ) . '<br />
<code>%_plural%</code> ' . $this->core->template_tags_general( __( 'Plural %plural% Name in lowercase.', 'mycred' ) ) . '<br />
<code>%login_url%</code> ' . __( 'The login URL without redirection.', 'mycred' ) . '<br />
<code>%login_url_here%</code> ' . __( 'The login URL with redirection to current page.', 'mycred' ) . '</p>
<p><strong>' . __( 'Post:', 'mycred' ) . '</strong></p>
<p><code>%post_title%</code> ' . __( 'The posts title.', 'mycred' ) . '<br />
<code>%post_url%</code> ' . __( 'The posts URL address.', 'mycred' ) . '<br />
<code>%post_type%</code> ' . __( 'The post type.', 'mycred' ) . '<br />
<code>%link_with_title%</code> ' . __( 'The posts permalink with the post title as title.', 'mycred' ) . '</p>
<p><strong>' . __( 'User:', 'mycred' ) . '</strong></p>
<p><code>%user_id%</code> ' . __( 'The users ID.', 'mycred' ) . '<br />
<code>%user_name%</code> ' . __( 'The users "username".', 'mycred' ) . '<br />
<code>%user_name_en%</code> ' . __( 'The users "username" URL encoded.', 'mycred' ) . '<br />
<code>%display_name%</code> ' . __( 'The users display name.', 'mycred' ) . '<br />
<code>%user_profile_url%</code> ' . __( 'The users profile URL.', 'mycred' ) . '<br />
<code>%user_profile_link%</code> ' . __( 'The users profile link with the display name as title.', 'mycred' ) . '</p>
<p><strong>' . __( 'Comment:', 'mycred' ) . '</strong></p>
<p><code>%comment_id%</code> ' . __( 'The comment ID.', 'mycred' ) . '<br />
<code>%c_post_id%</code> ' . __( 'The post id where the comment was made.', 'mycred' ) . '<br />
<code>%c_post_title%</code> ' . __( 'The post title where the comment was made.', 'mycred' ) . '<br />
<code>%c_post_url%</code> ' . __( 'The post URL address where the comment was made.', 'mycred' ) . '<br />
<code>%c_link_with_title%</code> ' . __( 'Link to the post where the comment was made.', 'mycred' ) . '</p>'
			) );
		}

		/**
		 * Widgets Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function widgets( $screen ) {
			$screen->add_help_tab( array(
				'id'		=> 'mycred-balance',
				'title'		=> __( 'myCRED Balance Template Tags', 'mycred' ),
				'content'	=> '
<h3>' . __( 'Available Template Tags', 'mycred' ) . '</h3>
<p><strong>' . __( 'Layout:', 'mycred' ) . '</strong></p>
<p><code>%cred%</code> ' . __( 'Balance amount in plain format.', 'mycred' ) . '<br />
<code>%cred_f%</code> ' . __( 'Balance amount formatted with prefix and/or suffix.', 'mycred' ) . '</p>
<p><strong>' . __( 'Rank Format:', 'mycred' ) . '</strong></p>
<p><code>%ranking%</code> ' . __( 'The users ranking. Was "%rank%" before version 1.1', 'mycred' ) . '</p>
<p><strong>' . __( 'History Title:', 'mycred' ) . '</strong></p>
<p><code>%singular%</code> ' . __( 'or', 'mycred' ) . ' <code>%_singular%</code> ' . $this->core->template_tags_general( __( 'Singular %plural% Name.', 'mycred' ) ) . '<br />
<code>%plural%</code> ' . __( 'or', 'mycred' ) . ' <code>%_plural%</code> ' . $this->core->template_tags_general( __( 'Plural %plural% Name.', 'mycred' ) ) . '</p>
<p><strong>' . __( 'Row Layout:', 'mycred' ) . '</strong></p>
<p><code>%cred%</code> ' . __( 'Balance amount in plain format.', 'mycred' ) . '<br />
<code>%cred_f%</code> ' . __( 'Balance amount formatted with prefix and/or suffix.', 'mycred' ) . '<br />
<code>%singular%</code> ' . __( 'or', 'mycred' ) . ' <code>%_singular%</code> ' . $this->core->template_tags_general(__( 'Singular %plural% Name.', 'mycred' ) ) . '<br />
<code>%plural%</code> ' . __( 'or', 'mycred' ) . ' <code>%_plural%</code> ' . $this->core->template_tags_general(__( 'Plural %plural% Name.', 'mycred' ) ) . '<br />
<code>%date%</code> ' . __( 'Log entry date.', 'mycred' ) . '<br />
<code>%entry%</code> ' . __( 'The log entry.', 'mycred' ) . '</p>
<p><strong>' . __( 'Message:', 'mycred' ) . '</strong></p>
<p><code>%login_url%</code> ' . __( 'The login URL without redirection.', 'mycred' ) . '<br />
<code>%login_url_here%</code> ' . __( 'The login URL with redirection to current page.', 'mycred' ) . '</p>'
			) );
			$screen->add_help_tab( array(
				'id'		=> 'mycred-list',
				'title'		=> __('myCRED List Template Tags'),
				'content'	=> '
<h3>' . __( 'Available Template Tags', 'mycred' ) . '</h3>
<p><strong>' . __( 'Row Layout:', 'mycred' ) . '</strong></p>
<p><code>%ranking%</code> ' . __( 'The users ranking. Was "%rank%" before version 1.1', 'mycred' ) . '<br />
<code>%cred%</code> ' . __( 'Balance amount in plain format.', 'mycred' ) . '<br />
<code>%cred_f%</code> ' . __( 'Balance amount formatted with prefix and/or suffix.', 'mycred' ) . '<br />
<code>%singular%</code> ' . __( 'or', 'mycred' ) . ' <code>%_singular%</code> ' . $this->core->template_tags_general( __( 'Singular %plural% Name.', 'mycred' ) ) . '<br />
<code>%plural%</code> ' . __( 'or', 'mycred' ) . ' <code>%_plural%</code> ' . $this->core->template_tags_general( __( 'Plural %plural% Name.', 'mycred' ) ) . '<br />
<code>%date%</code> ' . __( 'Log entry date.', 'mycred' ) . '<br />
<code>%entry%</code> ' . __( 'The log entry.', 'mycred' ) . '<br />
<code>%display_name%</code> ' . __( 'Users display name.', 'mycred' ) . '<br />
<code>%user_profile_url%</code> ' . __( 'Users profile URL.', 'mycred' ) . '<br />
<code>%user_name%</code> ' . __( 'Users "username".', 'mycred' ) . '<br />
<code>%user_name_en%</code> ' . __( 'Users "username" URL encoded.', 'mycred' ) . '<br />
<code>%user_profile_link%</code> ' . __( 'Link to users profile with their display name as title.', 'mycred' ) . '</p>'
			) );
		}

		/**
		 * Edit Users / Profile Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function users( $screen ) {
			$screen->add_help_tab( array(
				'id'		=> 'mycred-users',
				'title'		=> $this->core->template_tags_general( __( 'Editing %plural%', 'mycred' ) ),
				'content'	=> '
<p>' . $this->core->template_tags_general( __( 'You can adjust this users %_plural% by giving a positive or negative amount and a log description. Remember that plugin and point editors will always be able to adjust their own balance.', 'mycred' ) ) . '</p>
<p>' . __( 'If the option to edit a users balance is missing the user is set to be excluded from using myCRED.', 'mycred' ) . '</p>'
			) );
		}

		/**
		 * myCRED Settings Page Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function settings_page( $screen ) {
			$screen->add_help_tab( array(
				'id'		=> 'mycred-settings',
				'title'		=> __( 'Core', 'mycred' ),
				'content'	=> '
<p>' . $this->core->template_tags_general( __( 'On this page, you can edit all myCRED settings.', 'mycred' ) ) . '</p>
<p><strong>' . __( 'Core Settings', 'mycred' ) . '</strong></p>
<p>' . __( 'Here you can name your installation along with setting your layout and format. You can use any name as long as you set both the singular and plural format and you can change the name at any time.', 'mycred' ) . '</p>'
			) );
		}
	}
}

?>