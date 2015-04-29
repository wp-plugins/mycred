<?php
if ( !defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED About Page Header
 * @since 1.3.2
 * @version 1.1
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

	<div class="about-text"><?php printf( 'Thank you for choosing %s as your points management tool!<br />I hope you have as much fun using it as I had developing it.', $name ); ?></div>
	<p class="mycred-actions">
		<a href="<?php echo $log_url; ?>" class="button">Log</a>
		<a href="<?php echo $hook_url; ?>" class="button">Hooks</a>
		<a href="<?php echo $addons_url; ?>" class="button">Add-ons</a>
		<a href="<?php echo $settings_url; ?>" class="button button-primary">Settings</a>
	</p>
	<div class="mycred-badge">&nbsp;</div>
	
	<h2 class="nav-tab-wrapper">
		<a class="nav-tab<?php echo $new; ?>" href="<?php echo $about_page; ?>">What&#8217;s New</a><a class="nav-tab<?php echo $credit; ?>" href="<?php echo $credit_page; ?>">Credits</a><a class="nav-tab" href="http://codex.mycred.me" target="_blank">Documentation</a><a class="nav-tab" href="http://mycred.me/support/forums/" target="_blank">Support Forum</a><a class="nav-tab" href="http://mycred.me/store/" target="_blank">Store</a>
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
<script>
(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "http://connect.facebook.net/en_US/all.js#xfbml=1&appId=283161791819752";
  fjs.parentNode.insertBefore(js, fjs);
  }(document, 'script', 'facebook-jssdk'));
</script>
<?php
}

/**
 * About myCRED Page
 * @since 1.3.2
 * @version 1.1
 */
function mycred_about_page() {
	$name = mycred_label();
	$mycred = mycred();
	$settings_url = esc_url( add_query_arg( array( 'page' => 'myCRED_page_settings' ), admin_url( 'admin.php' ) ) ); ?>

<div class="wrap about-wrap" id="mycred-about-wrap">
	<h1><?php printf( __( 'Welcome to %s %s', 'mycred' ), $name, myCRED_VERSION ); ?></h1>
	<?php mycred_about_header( $name ); ?>

	<div class="changelog">
		<h3>Version Changes</h3>
		<div class="feature-section col two-col">
			<div>
				<h4>New Statistics Add-on</h4>
				<p>Gain a quick overview of how points a re earned and spent on your website via this new Statistics add-on.</p>
			</div>
			<div class="last-feature">
				<h4>Hook Limits</h4>
				<p>All built-in hooks now support limits! You can select to have no limits, total limits, daily, weekly or monthly limit.</p>
			</div>
			<div>
				<h4>Ranks for all types</h4>
				<p>myCRED 1.6 now supports ranks for multiple point types! You are no longer limited to the main point type for ranks.</p>
			</div>
			<div class="last-feature">
				<h4>Badge Levels</h4>
				<p>The badges add-on now supports Badge levels and manually assigning badges to users via the admin area!</p>
			</div>
		</div>
		<h3>Added Support</h3>
		<div class="feature-section col two-col">
			<div>
				<h4>AffiliateWP</h4>
				<p>Award points for affiliates referring visitors or store sales. You can also select to use points as your affiliate currency!</p>
			</div>
			<div class="last-feature">
				<h4>BuddyPress</h4>
				<p>In 1.6 I have added in support for deducting points from users when they delete their profile updates.</p>
			</div>
		</div>
		<p style="text-align:right;">Want to help further development of <strong>my</strong>CRED? <a href="http://mycred.me/about/support-mycred/" target="_blank">Here is a list</a> of things you can do to help!</p>
	</div>
	<?php mycred_about_footer(); ?>

</div>
<?php
}

/**
 * myCRED Credit Page
 * @since 1.3.2
 * @version 1.6.3
 */
function mycred_about_credit_page() {
	$name = mycred_label(); ?>

<div class="wrap about-wrap" id="mycred-credit-wrap">
	<h1>Awesome People</h1>
	<?php mycred_about_header( $name ); ?>

	<div class="changelog">
		<h3>myCRED Users</h3>
		<div class="feature-section col two-col">
			<div>
				<h4>Bug Finders</h4>
				<p>Users who have taken the time to report bugs. A big thank you to all.</p>
				<ul>
					<li><a href="http://mycred.me/community/innergy4every1/">innergy4every1</a></li>
					<li><a href="http://mycred.me/community/kristoff/">Kristoff</a></li>
					<li><a href="http://mycred.me/community/colson/">colson</a></li>
					<li><a href="http://mycred.me/community/Martin/">Martin</a></li>
					<li><a href="http://mycred.me/community/orousal/">Orousal</a></li>
					<li><a href="http://mycred.me/community/joseph/">Joseph</a></li>
					<li>Maria Campbell</li>
				</ul>
			</div>
			<div class="last-feature">
				<h4>Plugin Translators</h4>
				<p>Users who have helped with translating this plugin.</p>
				<ul>
					<li><a href="http://bp-fr.net/">Dan</a> <em>( French )</em></li>
					<li><a href="http://mycred.me/members/maniv-a/">Mani Akhtar</a> <em>( Persian )</em></li>
					<li><a href="http://www.merovingi.com/">Gabriel S Merovingi</a> <em>( Swedish )</em></li>
					<li><a href="http://robertrowshan.com/">Robert Rowshan</a> <em>( Spanish )</em></li>
					<li>Skladchik <em>( Russian )</em></li>
					<li>Guilherme <em>( Portuguese - Brazil )</em></li>
					<li><a href="http://coolwp.com">suifengtec</a> <em>( Chinese )</em></li>
				</ul>
				<p>Remember that translators are rewarded with <strong>my</strong>CRED tokens for their help. Tokens can be used in the myCRED store to pay for premium add-ons.</p>
			</div>
		</div>
		<h3>Find out more</h3>
		<p>You can always find more information about this plugin on the <strong>my</strong>CRED <a href="http://mycred.me/">website</a>.</p>
	</div>
	<?php mycred_about_footer(); ?>

</div>
<?php
}
?>