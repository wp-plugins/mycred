=== myCRED ===
Contributors: designbymerovingi
Tags:points, tokens, credit, management, reward, charge, community, contest, buddypress, jetpack, bbpress, simple press, woocommerce, marketpress, wp e-commerce, gravity forms, share-this
Requires at least: 3.8
Tested up to: 4.1
Stable tag: 1.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

myCRED is an adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website.

== Description ==

> #### Read before updating to 1.6
> Version 1.6 brings some major core changes for your point type settings and hooks. Please read [this guide](http://codex.mycred.me/updating-to-mycred-1-6/) before updating! 


> #### Plugin Support
> Free support is offered Monday - Friday 9 - 5 (UTC+1). Please note that myCRED has it's own [support forum](http://mycred.me/support/forums/) which is prioritised over the wordpress.org support forum!

I felt that todays WordPress community lacks a flexible points management system. Existing system often feel restrictive, stale or lack support for popular plugins.

So I built an adaptive plugin which gives it’s users full control on how points are awarded, used, traded, managed, logged and presented. Built on the "opt-in" principle, it is up to you what features you want to use and how. If your WordPress installation does not support a feature it is hidden from you to keep things clean and simple.

**my**CRED comes packed with features along with built-in support for some of the most popular [WordPress plugins](http://mycred.me/about/supported-plugins/) out there. But of course **my**CRED does not support everything out of the box so I have documented as much as possible in the **my**CRED [codex](http://codex.mycred.me) and you can find several [tutorials](http://mycred.me/support/tutorials/) that can help you better acquaint yourself with **my**CRED.

I am here to help where ever I can but please remember that right now this is a one man show and I do need an occasional coffee break.

You are welcome to post your issues or questions under the "Support" tab but remember that  **my**CRED has it's own [online forum](http://mycred.me/support/forums/) along with [F.A.Q.](http://mycred.me/about/faq/) page and an online [support page](http://mycred.me/support/).

**About Hooks**

**my**CRED hooks are instances where you award or deduct points from a user. By default you can award point for: registrations, logins, content publishing, commenting, clicking on links and viewing YouTube videos. You can find more information on built-in hooks [here](http://mycred.me/about/hooks/).


**About Add-ons**

**my**CRED add-ons allows you to enable more complex features that is not just about awarding / deducting points. Features include: [Sell Content](http://mycred.me/add-ons/sell-content/) with points, [Buy points](http://mycred.me/add-ons/buycred/) for real money, [Transfer](http://mycred.me/add-ons/transfer/) points between users, award [ranks](http://mycred.me/add-ons/ranks/) according to points balances. You can find a complete list of [built-in](http://mycred.me/add-on-types/built-in/) and [premium](http://mycred.me/add-on-types/premium/) add-ons [here](http://mycred.me/add-ons/).


**The Codex**

If you are comfortable with PHP or have some experience with customising your WordPress installation, I have documented as much as possible of **my**CRED in the [Codex](http://codex.mycred.me/).


**Contact**

* [General Inquiries](http://mycred.me/contact/)


== Installation ==

**myCRED Guides**

[myCRED Codex - Setup Guides](http://codex.mycred.me/get-started/)

[myCRED Codex - Install](http://codex.mycred.me/get-started/install/)

[myCRED Codex - Setup Hooks](http://codex.mycred.me/get-started/setup-hooks/)

[myCRED Codex - Setup Addons](http://codex.mycred.me/get-started/setup-addons/)

[myCRED Codex - Multiple Point Types](http://codex.mycred.me/get-started/multiple-point-types/)

[myCRED Codex - Multisites](http://codex.mycred.me/get-started/multisites/)


== Frequently Asked Questions ==

= Does myCRED support Multisite Installations? =

Yep! myCRED also offers you the option to centralize your log or enforce your main sites installation on all sub sites via the "Master Template" feature.

= What point types does myCRED support? =

myCRED points can be whole numbers or use up to 20 decimals.

= Does myCRED support Multiple Point Types? =

Yes! myCRED as of version 1.4 officially supports multiple point types. You can setup an unlimited number of point types with it's own settings, available hooks and log page for each administration. Note that add-ons have limited support. Please consult the myCRED website for more information.

= Can users use points to pay for items in my store? =

Yes, myCRED supports WooCommerce, MarketPress and WP E-Commerce straight out of the box. If you want users to pay for event tickets myCRED also supports Events Manger and Event Espresso.

= Can myCRED award points for users sharing posts on social media sites? =

No. myCRED can only detect and award / deduct points for actions done on your website. You can always create an app on the social media site in question that calls back to your website and informs myCRED of actions taken by your users but this is not supported out of the box.

= Can I award points for watching videos? =

Yes. myCRED supports YouTube out of the box and you can purchase the video add-on to add support for Vimeo as well. myCRED uses iframes for videos making video watching possible on portable devices as well.

= Can I import / export log entries? =

myCRED supports importing, exporting, inline editing and manual deletion of log entires as of version 1.4.


== Screenshots ==

1. **The Log** - myCRED Logs everything for you. You can browse, search, export, edit or delete log entries.
2. **Add-ons** - Enable only the features you want to use.
3. **Hooks** - Instances where you might want to award or deduct points from users are referred to as a "hook".
4. **Settings** - As of version 1.4 you can create multiple point types!
5. **Edit Balances** - While browsing your users in the admin area you always adjust their point balances.


== Upgrade Notice ==

= 1.6 =
New Features, Big Improvements and Bug Fixes.


== Other Notes ==

= Requirements =
* WordPress 3.8 or greater
* PHP version 5.3 or greater
* PHP mcrypt library enabled
* MySQL version 5.0 or greater

= Language Contributors =
* Swedish - Gabriel S Merovingi
* French - Chouf1 [Dan - BuddyPress France](http://bp-fr.net/)
* Persian - Mani Akhtar
* Spanish - Robert Rowshan [Website](http://robertrowshan.com)
* Russian - Skladchik
* Chinese - Changmeng Hu
* Portuguese (Brazil) - Guilherme


== Changelog ==

= 1.6 =
* NEW - Added option to change number of decimal places after setup.
* NEW - Statistics Add-on
* NEW - Badges can now have levels.
* NEW - Added manual badge management when editing a user.
* NEW - Built-in hooks have been added optional limits.
* NEW - Added Rewards system for MarketPress allowing you to reward purchases with points.
* NEW - Added new hook for WP Postratings plugin.
* NEW - Added new shortcode: mycred_hook_table to show the amount of points users can earn or lose based on setup.
* NEW - Ranks now support multiple point types.

* NEW - Added new filter: mycred_run_this for adjusting points before being executed.
* NEW - Added new filter: mycred_add_finished for customizations after points have been awarded / deducted.
* NEW - Added new filters: mycred_wpecom_profit_share, mycred_marketpress_profit_share and mycred_woo_profit_share to allow customizations of the percentage to pay out to store vendors.
* NEW - Added new constants MYCRED_BADGE_WIDTH and MYCRED_BADGE_HEIGHT to set the width and height of badge images in pixels. Defaults to 100x100.
* NEW - Added support for sending email notifications for new badges.
* NEW - Added support for setting which point type an email is to be sent for.
* NEW - Added option to set what ranks for each point type is based on.
* NEW - Added new shortcode [mycred_users_of_ranks] to show all users ranks.
* NEW - Added support for Affiliate WP.
* NEW - Added option to award points to post authors when their post is added to favorites.
* NEW - Added warning when creating custom point types to ensure keys are properly formatted.
* NEW - Added hook for Profile Update removals.
* NEW - Added new constant SHOW_MYCRED_IN_WOOCOMMERCE to always show the myCRED payment gateway in WooCommerce.

* TWEAK - Moved Email Notifications from mycred_add to mycred_add_finished.
* TWEAK - Moved Ranks check from mycred_add to mycred_add_finished.
* TWEAK - Added option to for BuddyPress and bbPress to select if we should show only earned or all badges in profiles / replies.
* TWEAK - Updated admin styling and available template tags for email notifications.
* TWEAK - When selecting "Set all balances to zero", all ranks are reset as well.
* TWEAK - All rank shortcodes have been updated to support multiple point types.
* TWEAK - Re-organized certain add-on folders by moving js and css items into assets folder.
* TWEAK - Updated HTML code structure on buyCRED Payment Gateways page.
* TWEAK - Added support for user_id and post_id usage for the mycred_affiliate_link shortcode.
* TWEAK - Notifications add-on's "Duration" setting has been changed from using milliseconds to using seconds.
* TWEAK - Moved all custom post type updates from save_post to save_post_{post_type}.
* TWEAK - Added new show_nav="" attribute for the mycred_history shortcode, to set if the navigation should be shown (1) or not (0). Set to show (1) by default.
* TWEAK - Added function check to prevent fatal error when WooCommerce has been used but disabled, while we are viewing log entries for purchases made.
* TWEAK - Added warning to the log page if the Mcrypt library has been disabled after activation.
* TWEAK - Moved Transfer functions, shortcode and widget code to their own files. Re-organized the transfer add-on folder.

* FIX - Badge requirements show 1 instead of actual value.
* FIX - When points without decimals are purchased, NETbilling required them to have two decimals.
* FIX - Incorrect sorting variable is passed when sorting your points history in BuddyPress.
* FIX - When awarding points for BP Group members on x number of members, points are not awarded.
* FIX - mycred_give limit only works for the default point type.
* FIX - When ranks are not based on total point balance the awarding of a new rank is "one step" behind.
* FIX - Adjusted admin.css stylesheet to force the myCRED layout on myCRED settings page.

= 1.5.4 =
http://mycred.me/support/changelog/

= 1.5.3 =
http://mycred.me/support/changelog/

= 1.5.2 =
http://mycred.me/support/changelog/

= 1.5.1 =
http://mycred.me/support/changelog/

= 1.5 =
http://mycred.me/support/changelog/

= 1.4.7 =
http://mycred.me/support/changelog/2/

= 1.4.6 =
http://mycred.me/support/changelog/2/

= 1.4.5 =
http://mycred.me/support/changelog/2/

= 1.4.4 =
http://mycred.me/support/changelog/2/

= 1.4.3 =
http://mycred.me/support/changelog/2/

= 1.4.2 =
http://mycred.me/support/changelog/2/

= 1.4.1 =
http://mycred.me/support/changelog/2/

= 1.4 =
http://mycred.me/support/changelog/2/

= 1.3.3.2 =
http://mycred.me/support/changelog/3/

= 1.3.3.1 =
http://mycred.me/support/changelog/3/

= 1.3.3 =
http://mycred.me/support/changelog/3/

= 1.3.2 =
http://mycred.me/support/changelog/3/

= 1.3.1 =
http://mycred.me/support/changelog/3/

= 1.3 =
http://mycred.me/support/changelog/3/

= 1.2.3 =
http://mycred.me/support/changelog/4/

= 1.2.2 =
http://mycred.me/support/changelog/4/

= 1.2.1 =
http://mycred.me/support/changelog/4/

= 1.2 =
http://mycred.me/support/changelog/4/

= 1.1.2 =
http://mycred.me/support/changelog/5/

= 1.1.1 =
http://mycred.me/support/changelog/5/

= 1.1 =
http://mycred.me/support/changelog/5/

= 1.0.9.3 =
http://mycred.me/support/changelog/6/

= 1.0.9.2 =
http://mycred.me/support/changelog/6/

= 1.0.9.1 =
http://mycred.me/support/changelog/6/

= 1.0.9 =
http://mycred.me/support/changelog/6/

= 1.0.8 =
http://mycred.me/support/changelog/6/

= 1.0.7 =
http://mycred.me/support/changelog/6/

= 1.0.6 =
http://mycred.me/support/changelog/6/

= 1.0.5 =
http://mycred.me/support/changelog/6/

= 1.0.4 =
http://mycred.me/support/changelog/6/

= 1.0.3 =
http://mycred.me/support/changelog/6/

= 1.0.2 =
http://mycred.me/support/changelog/6/

= 1.0.1 =
http://mycred.me/support/changelog/6/

= 1.0 =
http://mycred.me/support/changelog/6/