<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED About Page Header
 * @since 1.3.2
 * @version 1.0
 */
function mycred_about_header( $name ) {
	$new = $credit = '';
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'mycred-credit' )
		$credit = ' nav-tab-active';
	else
		$new = ' nav-tab-active';
	
	$index_php = admin_url( 'index.php' );
	$about_page = esc_url( add_query_arg( array( 'page' => 'mycred' ), $index_php ) );
	$credit_page = esc_url( add_query_arg( array( 'page' => 'mycred-credit' ), $index_php ) );
	
	$admin_php = admin_url( 'admin.php' );
	$log_url = esc_url( add_query_arg( array( 'page' => 'myCRED' ), $admin_php ) );
	$hook_url = esc_url( add_query_arg( array( 'page' => 'myCRED_page_hooks' ), $admin_php ) );
	$addons_url = esc_url( add_query_arg( array( 'page' => 'myCRED_page_addons' ), $admin_php ) );
	$settings_url = esc_url( add_query_arg( array( 'page' => 'myCRED_page_settings' ), $admin_php ) ); ?>

	<div class="about-text"><?php printf( __( 'Thank you for choosing %s as your points management tool!<br />I hope you have as much fun using it as I had developing it.', 'mycred' ), $name ); ?></div>
	<p class="mycred-actions">
		<a href="<?php echo $log_url; ?>" class="button button-large">Log</a>
		<a href="<?php echo $hook_url; ?>" class="button button-large">Hooks</a>
		<a href="<?php echo $addons_url; ?>" class="button button-large">Add-ons</a>
		<a href="<?php echo $settings_url; ?>" class="button button-large button-primary">Settings</a>
	</p>
	<div class="mycred-badge">&nbsp;</div>
	
	<h2 class="nav-tab-wrapper">
		<a class="nav-tab<?php echo $new; ?>" href="<?php echo $about_page; ?>">
			<?php _e( 'What&#8217;s New', 'mycred' ); ?>
		</a><a class="nav-tab<?php echo $credit; ?>" href="<?php echo $credit_page; ?>">
			<?php _e( 'Credits', 'mycred' ); ?>
		</a>
	</h2>
<?php
}

/**
 * myCRED About Page Footer
 * @since 1.3.2
 * @version 1.0
 */
function mycred_about_footer() { ?>

	<p>&nbsp;</p>
	<div id="social-media">
		<a href="//plus.google.com/102981932999764129220?prsrc=3" rel="publisher" style="text-decoration:none;float: left; margin-right: 12px;">
<img src="//ssl.gstatic.com/images/icons/gplus-32.png" alt="Google+" style="border:0;width:24px;height:24px;"/></a><div class="fb-like" data-href="https://www.facebook.com/myCRED" data-height="32" data-colorscheme="light" data-layout="standard" data-action="like" data-show-faces="false" data-send="false" style="display:inline;"></div>
	</div>
	<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=283161791819752";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
<?php
}

/**
 * About myCRED Page
 * @since 1.3.2
 * @version 1.0
 */
function mycred_about_page() {
	$name = mycred_label();
	$mycred = mycred_get_settings();
	$settings_url = esc_url( add_query_arg( array( 'page' => 'myCRED_page_settings' ), admin_url( 'admin.php' ) ) ); ?>

<div class="wrap about-wrap" id="mycred-about-wrap">
	<h1><?php printf( __( 'Welcome to %s %s', 'mycred' ), $name, myCRED_VERSION ); ?></h1>
	<?php mycred_about_header( $name ); ?>

	<div class="changelog">
		<h3><?php _e( 'New Features', 'mycred' ); ?></h3>
		<div class="feature-section col two-col">
			<div>
				<h4><?php printf( __( '%s Right Now', 'mycred' ), $name ); ?></h4>
				<p><?php echo $mycred->template_tags_general( __( 'This new Dashboard widget gives you an overview of %_plural% gained or lost by your users along with a few other summaries based on your logs content.', 'mycred' ) ); ?></p>
			</div>
			<div class="last-feature">
				<h4><?php _e( 'YouTube Iframe API', 'mycred' ); ?></h4>
				<p><?php echo $mycred->template_tags_general( __( 'The "%plural% for watching videos" hook has been updated to use the YouTube Iframe API which allows you to embed videos that can also be viewed on mobile devices.', 'mycred' ) ); ?></p>
			</div>
		</div>
		<h3><?php _e( 'Added Support', 'mycred' ); ?></h3>
		<div class="feature-section col two-col">
			<div>
				<h4><a href="http://simple-press.com/" target="_blank"><?php _e( 'SimplePress', 'mycred' ); ?></a></h4>
				<p><?php printf( __( 'As of 1.3.3, %s has a built in support for SimplePress!', 'mycred' ), $name ); ?> <?php echo $mycred->template_tags_general( __( 'Once you have installed SimplePress, you will find the "SimplePress" hook on your Hooks page. You can award or deduct %_plural% for new topics and topic posts.', 'mycred' ) ); ?></p>
			</div>
			<div class="last-feature">
				<h4><a href="http://www.timersys.com/plugins-wordpress/wordpress-social-invitations/" target="_blank">WP Social Invitations</a></h4>
				<p><?php _e( 'With WordPress Social Invitations aka WSI you can enhance your site by letting your users to invite their social network friends. This plugin works perfectly with Buddypress and also hooks into Invite Anyone Plugin.', 'mycred' ); ?></p>
				<p><?php _e( 'Please consult the plugins website for information on how to install and setup this plugin.', 'mycred' ); ?></p>
			</div>
		</div>
		<h3><?php _e( 'Improvements', 'mycred' ); ?></h3>
		<div class="feature-section col three-col">
			<div>
				<h4><?php _e( 'Transfer Add-on', 'mycred' ); ?></h4>
				<p><?php _e( 'The transfer add-on has received several improvements which gives you must better control of customizing your setup.', 'mycred' ); ?></p>
			</div>
			<div>
				<h4><?php _e( 'Ranks Add-on', 'mycred' ); ?></h4>
				<p><?php _e( 'The ranks add-on has received several bug fixes, especially if you are assigning ranks according to your users total accumilated points and not their current balance.', 'mycred' ); ?></p>
				<p><em><?php _e( 'If you have been experiencing issues with users not getting the correct rank, please make sure you "Calculate Totals" to fix the issue!', 'mycred' ); ?></em></p>
			</div>
			<div class="last-feature">
				<h4><?php _e( 'Events Management', 'mycred' ); ?></h4>
				<p><?php _e( 'Fixed the issue with users not being able to pay for events in the free version, if attendance is pre-approved.', 'mycred' ); ?></p>
			</div>
		</div>
	</div>
	<?php mycred_about_footer(); ?>

</div>
<?php
}

/**
 * myCRED Credit Page
 * @since 1.3.2
 * @version 1.0
 */
function mycred_about_credit_page() {
	$name = mycred_label(); ?>

<div class="wrap about-wrap" id="mycred-credit-wrap">
	<h1><?php _e( 'Awesome People', 'mycred' ); ?></h1>
	<?php mycred_about_header( $name ); ?>

	<div class="changelog">
		<h3><?php printf( __( '%s Users', 'mycred' ), $name ); ?></h3>
		<div class="feature-section col two-col">
			<div>
				<h4><?php _e( 'Bug Finders', 'mycred' ); ?></h4>
				<p><?php _e( 'Users who have taken the time to report bugs helping me improve this plugin.', 'mycred' ); ?></p>
				<ul>
					<li><a href="http://mycred.me/members/douglas-dupuis-9_o8jr2b/">Douglas</a></li>
					<li><a href="http://mycred.me/members/joebethepro-com/">joe</a></li>
				</ul>
			</div>
			<div class="last-feature">
				<h4><?php _e( 'Plugin Translators', 'mycred' ); ?></h4>
				<p><?php _e( 'Users who have helped with translating this plugin.', 'mycred' ); ?></p>
				<ul>
					<li><a href="http://bp-fr.net/">Dan</a> <em>( French )</em></li>
				</ul>
			</div>
		</div>
		<h3><?php _e( 'Find out more', 'mycred' ); ?></h3>
		<p><?php printf( __( 'You can always find more information about this plugin on the %s <a href="%s">website</a>.', 'mycred' ), $name, 'http://mycred.me' ); ?></p>
	</div>
	<?php mycred_about_footer(); ?>

</div>
<?php
}
?>