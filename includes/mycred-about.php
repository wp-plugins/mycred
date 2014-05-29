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

	<div class="about-text"><?php printf( 'Thank you for choosing %s as your points management tool!<br />I hope you have as much fun using it as I had developing it.', $name ); ?></div>
	<p class="mycred-actions">
		<a href="<?php echo $log_url; ?>" class="button button-large">Log</a>
		<a href="<?php echo $hook_url; ?>" class="button button-large">Hooks</a>
		<a href="<?php echo $addons_url; ?>" class="button button-large">Add-ons</a>
		<a href="<?php echo $settings_url; ?>" class="button button-large button-primary">Settings</a>
	</p>
	<div class="mycred-badge">&nbsp;</div>
	
	<h2 class="nav-tab-wrapper">
		<a class="nav-tab<?php echo $new; ?>" href="<?php echo $about_page; ?>">What&#8217;s New</a><a class="nav-tab<?php echo $credit; ?>" href="<?php echo $credit_page; ?>">Credits</a>
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
	$mycred = mycred();
	$settings_url = esc_url( add_query_arg( array( 'page' => 'myCRED_page_settings' ), admin_url( 'admin.php' ) ) ); ?>

<div class="wrap about-wrap" id="mycred-about-wrap">
	<h1><?php printf( __( 'Welcome to %s %s', 'mycred' ), $name, myCRED_VERSION ); ?></h1>
	<?php mycred_about_header( $name ); ?>

	<h4><strong>Important!</strong> Make sure you re-save your myCRED Settings and Hook settings by visiting both pages and clicking "Save Changes" even if you make no changes in your current settings. Version 1.4 also requires you to re-save all myCRED Widget settings in order to add support for multiple point types.</h4>
	<div class="changelog">
		<h3>New Features</h3>
		<div class="feature-section col two-col">
			<div>
				<h4>Multiple Point Types</h4>
				<p>No longer are you bound to use only one point type! New in 1.4 is the built in multiple point types system. Visit the Settings page to add any number of point types.</p>
			</div>
			<div class="last-feature">
				<h4><?php echo $mycred->template_tags_general( '%plural% for Referrals Hook!' ); ?></h4>
				<p><?php echo $mycred->template_tags_general( 'Based on the most popular myCRED Tutorial, you will find a new Referral hook allowing you to give %_plural% for visit or signup referrals!' ); ?></p>
			</div>
		</div>
		<h3>Add-on News</h3>
		<div class="feature-section col two-col">
			<div>
				<h4>Coupons</h4>
				<p>The new Coupons add-on allows you to setup your own coupons that users can redeem via the new [mycred_get_coupon_by_code] shortcode. Coupons can have a global and/or a user limit with an optional minimum or maximum balance requirement in order to use!</p>
			</div>
			<div class="last-feature">
				<h4>Removed Add-ons</h4>
				<p>In 1.4 the BuddyPress and Import add-ons have been removed and instead been integrated into myCRED. If you have BuddyPress enabled, the BuddyPress features are automatically enabled while you can find the Import add-on now under Tools > Import in the admin menu to the left.</p>
			</div>
		</div>
		<h3>Added Support</h3>
		<div class="feature-section col two-col">
			<div>
				<h4><a href="http://disqus.com/" target="_blank">Disqus</a></h4>
				<p>myCRED now has built-in support for Disqus comments! Please remember that in order for points to be awarded, comments must be synced and comment authors must use the email they have registered on your website!</p>
			</div>
			<div class="last-feature">
				<h4><a href="http://www.gravityforms.com/" target="_blank">Gravity Forms</a></h4>
				<p>Just like with Contact Form 7, you can now award / deduct points from your users for submitting forms managed by Gavity Forms!</p>
			</div>
		</div>
		<h3>Improvements</h3>
		<div class="feature-section col three-col">
			<div>
				<h4>The Log</h4>
				<p>The myCRED log now supports paginations, inline editing and/or removal of individual log entries. Gone are the days where you are stuck with your current log entries!</p>
			</div>
			<div>
				<h4>Leaderboard</h4>
				<p>The myCRED Leaderboard feature has been updated and improved to handle larger user bases. Note that in 1.4 the Leaderboard will be updated automatically and you can no longer select specific update intervals.</p>
			</div>
			<div class="last-feature">
				<h4>New Wallet Widget</h4>
				<p>If you have more then one point type setup, you gain access to the new myCRED Wallet Widget which you can use to show your users point balances in a single widget.</p>
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
 * @version 1.0
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
				<p>Users who have taken the time to report bugs helping me improve this plugin.</p>
				<ul>
					<li><a href="http://mycred.me/members/seamtv/">seamtv</a></li>
					<li><a href="http://mycred.me/members/joebethepro-com/">joe</a></li>
					<li><a href="http://mycred.me/members/gogott/">gogott</a></li>
					<li><a href="http://mycred.me/members/geegee/">Christian S</a></li>
					<li><a href="http://mycred.me/members/keisermedia/">Lucas Keiser</a></li>
					<li><a href="http://mycred.me/members/ebf/">Boab</a></li>
					<li><a href="http://mycred.me/members/threadsgeneration/">Gabriel Galvão</a></li>
					<li><a href="http://mycred.me/members/dvdbrazil/">Dvdbrazil</a></li>
					<li><a href="http://mycred.me/members/bobblefruit/">Dean</a></li>
					<li><a href="http://mycred.me/members/sl21/">sl21</a></li>
				</ul>
			</div>
			<div class="last-feature">
				<h4>Plugin Translators</h4>
				<p>Users who have helped with translating this plugin.</p>
				<ul>
					<li><a href="http://bp-fr.net/">Dan</a> <em>( French )</em></li>
					<li><a href="http://mycred.me/members/maniv-a/">Mani Akhtar</a> <em>( Persian )</em></li>
					<li><a href="http://www.merovingi.com/">Gabriel S Merovingi</a> <em>( Swedish )</em></li>
					<li><a href="http://robertrowshan.com/">Rob Row</a> <em>( Spanish )</em></li>
					<li>Skladchik <em>( Russian )</em></li>
				</ul>
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