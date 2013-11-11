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
	$name = apply_filters( 'mycred_label', myCRED_NAME );
	$settings_url = esc_url( add_query_arg( array( 'page' => 'myCRED_page_settings' ), admin_url( 'admin.php' ) ) ); ?>

<div class="wrap about-wrap" id="mycred-about-wrap">
	<h1><?php printf( __( 'Welcome to %s %s', 'mycred' ), $name, myCRED_VERSION ); ?></h1>
	<?php mycred_about_header( $name ); ?>

	<div class="changelog">
		<h3><?php _e( 'Ranks Add-on', 'mycred' ); ?></h3>
		<div class="feature-section col two-col">
			<img src="<?php echo plugins_url( 'assets/images/about/ranks-management.png', myCRED_THIS ); ?>" alt="Ranks Management" />
			<div>
				<h4><?php _e( 'Ranks Management', 'mycred' ); ?></h4>
				<p><?php _e( 'You can now select to delete all ranks or if you feel your users have the incorrect rank, re-assign ranks with a click of a button.', 'mycred' ) ?></p>
			</div>
			<div class="last-feature">
				<h4><?php _e( 'Improvements', 'mycred' ); ?></h4>
				<p><?php _e( 'Several rank functions have been re-written to search and assign ranks much faster and at a lower memory cost.', 'mycred' ) ?></p>
			</div>
		</div>
		<h3><?php _e( 'Improved Security', 'mycred' ); ?></h3>
		<div class="feature-section col two-col">
			<div>
				<h4><?php _e( 'Failsafe', 'mycred' ); ?></h4>
				<p><?php _e( 'As of version 1.3.2 you can now set a maximum number that can be given or taken from a user in a single instance. So if someone decides to cheat, this would be the maximum amount they could gain.', 'mycred' ) ?></p>
				<p><?php printf( __( 'You can find this setting on the %s <a href="%s">settings</a> page under "Security" in the "Core" menu.', 'mycred' ), $name, $settings_url ); ?></p>
			</div>
			<div class="last-feature">
				<img src="<?php echo plugins_url( 'assets/images/about/failsafe.png', myCRED_THIS ); ?>" alt="Failsafe" style="width: 100%; height: auto;" />
			</div>
		</div>
		<h3><?php _e( 'Under the hood', 'mycred' ); ?></h3>
		<div class="feature-section col three-col">
			<div>
				<h4><?php _e( 'The myCRED_Query_Log Class', 'mycred' ); ?></h4>
				<p><?php _e( 'Added support for querying multiple references, reference ids or amounts through a comma separated list.', 'mycred' ); ?></p>
			</div>
			<div>
				<h4><?php _e( 'Autofill Transfer Recipient', 'mycred' ); ?></h4>
				<p><?php _e( 'You can now select what user detail users are searched by. By default you can search by username or email but several filters have been added allowing you to customize this further.', 'mycred' ); ?></p>
			</div>
			<div class="last-feature">
				<h4><?php _e( 'Points for clicking on links', 'mycred' ); ?></h4>
				<p><?php _e( 'Fixed a security flaw where users can award themselves any point amount when clicking on a link.', 'mycred' ); ?></p>
			</div>
		</div>
		<div><em><?php _e( 'Oh and as you might have noticed, I have added this new splash page for all future updates!', 'mycred' ); ?></em></div>
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
	$name = apply_filters( 'mycred_label', myCRED_NAME ); ?>

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
					<li><a href="http://mycred.me/members/jaykdoe/">jaykdoe</a></li>
					<li><a href="http://mycred.me/members/enk/">enk</a></li>
					<li><a href="http://mycred.me/members/specopkirbs/">specopkirbs</a></li>
					<li><a href="http://mycred.me/members/Christopher/">Christopher</a></li>
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