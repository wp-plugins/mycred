=== myCRED ===
Contributors: designbymerovingi
Tags:points, tokens, credit, management, reward, charge, community, contest, BuddyPress, Jetpack, bbPress
Requires at least: 3.1
Tested up to: 3.6.1
Stable tag: 1.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

myCRED is an adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website.

== Description ==
 
I felt that todays WordPress community lacks a flexible points management system. Existing system often feel restrictive, stale or lack support for popular plugins.

So I built an adaptive plugin which gives it’s users full control on how points are awarded, used, traded, managed, logged and presented. Built on the "opt-in" principle, it is up to you what features you want to use and how. If your WordPress installation does not support a feature it is hidden from you to keep things clean and simple.

**my**CRED comes packed with features along with built-in support for some of the most popular WordPress plugins out there. But of course **my**CRED does not support everything out of the box so I have documented as much as possible in the **my**CRED codex and you can find several tutorials that can help you better acquaint yourself with **my**CRED.

I am here to help where ever I can but please remember that right now this is a one man show and I do need an occasional coffee break.

You are welcome to post your issues or questions under the "Support" tab but remember that  **my**CRED has it's own online forum along with [F.A.Q.](http://mycred.me/about/faq/) page and [Known Issues](http://mycred.me/download/known-issues/).

**Hooks**

**my**CRED hooks are instances where you award or deduct points from a user. By default you can award point for: registrations, logins, content publishing, commenting, clicking on links and viewing YouTube videos. You can find more information on built-in hooks [here](http://mycred.me/about/hooks/).


**Add-ons**

**my**CRED add-ons allows you to enable more complex features that is not just about awarding / deducting points. Features include: Sell Content with points, Buy points for real money, transfer points between users, award ranks according to points balances and expand **my**CRED to work with BuddyPress. You can find a complete list of built-in and premium add-ons [here](http://mycred.me/add-ons/).


**The Codex**

If you are comfortable with PHP or have some experience with customising your WordPress installation, I have documented as much as possible of **my**CRED in the [Codex](http://codex.mycred.me/).


**Further Details**

* [Features](http://mycred.me/about/features/)
* [Hooks](http://mycred.me/about/hooks/)
* [F.A.Q.](http://mycred.me/about/faq/)
* [Add-ons](http://mycred.me/add-ons/)
* [Tutorials](http://mycred.me/support/tutorials/)
* [Known Issues](http://mycred.me/download/known-issues/)
* [Codex](http://codex.mycred.me/support/)


**Contact**

* [General Inquiries](http://mycred.me/contact/)
* [Bug Report](http://mycred.me/contact/report-bug/)
* [Request Feature](http://mycred.me/contact/request-feature/)


== Installation ==

For a comprehensive guide on how to install **my**CRED or how this plugin works, consider visiting our [Online Tutorial](http://mycred.me/support/tutorials/how-to-install-and-setup-mycred/).

**Single Site**

1. Upload `mycred` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Run the **my**CRED Setup which will allow you to configure your **my**CRED installation
4. Activate the Add-ons you wish to use under the 'myCRED' menu in WordPress
5. Configure and Enable the hooks you wish to use though the 'Hooks' sub menu in WordPress
6. Configure any other Add-on settings you might be using i.e. BuddyPress though the 'Settings' sub menu in WordPress

**Multisite with one myCRED installation on all sites**

1. Upload `mycred` to the `/wp-content/plugins/` directory
2. Enable **my**CRED Network Wide though your WordPress Network page
3. While in your Network area, visit the new myCRED menu and select your setup and save.
4. Visit your main sites admin area
5. Run the **my**CRED Setup which will allow you to configure your **my**CRED installation
6. Activate the Add-ons you wish to use under the 'myCRED' menu in WordPress
7. Configure and Enable the hooks you wish to use though the 'Hooks' sub menu in WordPress
8. Configure any other Add-on settings you might be using i.e. BuddyPress though the 'Settings' sub menu in WordPress

**Multisite with individual myCRED installation for each site**

1. Upload `mycred` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Run the **my**CRED Setup which will allow you to configure your **my**CRED installation
4. Activate the Add-ons you wish to use under the 'myCRED' menu in WordPress
5. Configure and Enable the hooks you wish to use though the 'Hooks' sub menu in WordPress
6. Configure any other Add-on settings you might be using i.e. BuddyPress though the 'Settings' sub menu in WordPress
7. Repeat the process on each site you want to run **my**CRED

== Frequently Asked Questions ==

= Does myCRED support Multisite Installations? =

Yes, **my**CRED supports Multisite installations.

= Can my "Points" use decimals? =

Yes. When you run the **my**CRED Setup, you will be asked if you want to use whole numbers or decimals. However it should be noted that once the setup is completed, this can not be changed without first deleting **my**CRED though the 'Delete Plugin' function in WordPress (in other words, DO NOT delete the files using FTP).

= I want to charge a user for creating a group, can I disable group creation for users who does not have enough points? =

Yes. You can set a negative value for either "Creating Group" or "Joining Group" which will restrict a user from creating or joining any group unless they have enough points without going minus on their account.

= Some Hooks contain several instances where points might be given to users. Can I disable parts of them or do I have to use every instance? =

You can always disable parts of a hook by awarding zero points. Hooks that have zero points are ignored.

= Can I use the mycred_sell_this shortcode multiple times in a post?

Yes but if one of them is bought, all is shown. The mycred_sell_this shortcode was created so you can show "teaser" content before someone purchases the post / page / custom post type. It was not built to sell multiple items on a single page.


== Screenshots ==

1. **Multisites** - The myCRED Network Settings Page gives you access to the Master Template feature and the Block List.
2. **Gateway Add-on** - Using **my**CRED as a Payment Gateway in your WooCommerce Shopping Cart plugin.
3. **The Log** - with the option to search or filter results. Each user will also get their own Log page under the "Users" menu.
4. **Editing Users Balance** - You can edit each users point balance directly under "Edit User".
5. **Import Add-on** - The Import Add-on allows you to import points using a CSV file or by importing existing points from your database.


== Upgrade Notice ==

= 1.3.1 =
Important bug fixes for 1.3 users


== Other Notes ==

= Requirements =
* WordPress 3.1 or greater
* PHP version 5.2.4 or greater
* MySQL version 5.0 or greater

= Language Contributors =
* French - Chouf1 [Dan - BuddyPress France](http://bp-fr.net/)


== Changelog ==

= 1.3.1 =
* Fixed Bug #58 - Some hooks fire to late due to hooks being loaded to late.
* Fixed Bug #59 - Shopping cart settings are inaccessible due to to late hook registration.
* Fixed Bug #60 - Notifications lack support for ' or " signs causing an error which prevents notices to load.
* Fixed Bug #61 - myCRED_Query_Log can not return a single result, requires minimum 2 to be returned or it will default to all.
* Fixed Bug #62 - Commenter is awarded points instead of post author due to incorrect user id being passed on.
* Fixed Bug #63 - The removal of myCRED_Core causes customisations depending on this class to fail causing a site wide error.
* Fixed Bug #64 - Points over 999 with a non-empty thousands separator will cause hooks to award to low points.
* Fixed Bug #65 - The use of update_blog_option() on non multi sites will cause a php notice.
* Fixed Bug #66 - Points History page link still visible in tool bar even if history page is disabled.
* Fixed Bug #67 - Duplicate Gateways when using Events Manager (free version).
* Fixed Bug #68 - Users are not refunded when cancelling their event attendance in Events Manager (free & pro)
* Added logout redirect to BP when users logout from the points history page to prevent 404 errors.
* Added new mycred_label_my_balance filter for adjustments of the tool bar "My Balance" item.
* Re-wrote the Points for commenting.


= 1.3 =
* Improved myCRED's module management lowering memory usage.
* Re-designed hooks, add-ons and settings accordion.
* Moved Log Query to mycred-log.php in the includes/ folder.
* Removed mycred_modules hook.
* Improved Hooks management and re-structured hooks in new plugins/ folder.
* Adjusted styling for MP6 users.
* Added %title% as new template tag for mycred_link.
* Added class attribute to mycred_buy shortcode.
* Added support for profit sharing to Supported Shopping Carts and Event Booking plugins.
* Added support for WP E-Commerce Shopping cart.
* Added new constant MYCRED_LOG_TABLE to allow custom table names for the log.
* Added new Management to settings page allowing to empty the log, reset all user points to zero or export all user balances to a CSV file.
* Added myCRED Remote API to allow remote actions for sites.
* Rewritten myCRED Network for Multisite installations.
* Updated the mycred_my_balance shortcode to allow stripping off html wrappers.
* Added new mycred_log_date filter allowing to customise the log dates.
* Added daily limit for points for profile updates (Activities) in Buddypress.
* Added option to award points to content authors for comments made by others.
* Fixed Bug #52 - Notifications add-on does not parse post related template tags.
* Fixed Bug #53 - General template tags are not parsed in sell content templates.
* Fixed Bug #54 - mycred_link shortcode does not support target attribute.
* Fixed Bug #55 - Users can transfer points to themselves.
* Fixed Bug #56 - Incorrect use of ob_start() in myCRED widgets.
* Fixed Bug #57 - Incorrect capability check on Multisites as edit_users is not available.


= 1.2.3 =
* Moved .POT file to correct location in /lang
* Cart support for Email notices has incorrect function name.
* Removed stray debug options on plugin activation.
* Added support for Email Notices when users gets promoted or demoted.
* Improved default ranking check.
* Improved Banking services by moving exclusion checks from the payout process to the get user IDs process. Also added set_time_limit( 0 ); for larger sites to avoid time out issues.
* Minimized CSS assets.
* Added new Notifications add-on.

= 1.2.2 =
* Added User Batches for Banking Add-on to support websites with more then 2k users.
* Added Cache option to myCRED_Query_Log class.
* Adjusted template tags handling to improve performance.
* Added missing options and schedules to the uninstaller.
* Fixed Bug #46 - Missing text domains for translations and incorrect html syntaxes. (thanks Dan).
* Fixed Bug #47 - Rank shown multiple times in BP Profile.
* Fixed Bug #48 - Disabling specific comment hook instances with zero does not work.
* Fixed Bug #49 - PHP Notice when user accepts invite though the Invite Anyone Plugin.
* Fixed Bug #50 - Users balance is not updated when viewing videos.
* Fixed Bug #51 - Ranking Loop function missing.
* Added check to remove balances for users who have been selected to be excluded.
* Added support for shopping cart related template tags in the Email Notice add-on. 

= 1.2.1 =
* Fixed Bug #43 - Users are not sorted according to balance in WP admin.
* Fixed Bug #44 - Incorrect settings are saved by Banking add-on causing Hooks to reset.
* Fixed Bug #45 - Jetpack subscription causes error when jetpack is updated as there is no check if JETPACK__PLUGIN_DIR is defined.

= 1.2 =
* Moved the has_entry() method from myCRED_Hook to myCRED Settings to allow use by other features then just hooks.
* Adjusted Hooks for clicking on links to enforce limits once per user and not once per use.
* Adjusted links.js to take into account slow server connections.
* Adjusted bad logic in BuddyPress navigation setup for points history.
* Added option to re-name the points history slug from the default "mycred-history".
* Added option to set the number of log entries to retrieve on users points history page.
* Fixed Bug #39 - If user object is not passed on when wp_login fires, the loggin_in() method fails.
* Fixed Bug #40 – Adjusted Hooks for clicking on links to enforce limits once per user and not once per use.
* Fixed Bug #41 - Only the initiator is awarded points when accepting new friendships in BuddyPress.
* Fixed Bug #42 - Excluded users still see "My Points History" page in the Users menu.
* Updated bbPress Support by adding the option to enforce a daily limit for topic replies and fav replies. Also added option to deduct points for forums, topics and replies getting deleted.
* Added new shortcode mycred_video for awarding / deducting points for viewing YouTube videos.
* Added Inline Editing of users myCRED points.
* Added option to sort users in the admin area according to their point balance.
* Added option to sort the myCRED Log Ascending or Descending.
* Renamed Transfer CREDs to Transfer %plural% on the myCRED > Settings page.
* Added Support for Event Espresso 3 though the Gateway Add-on.
* Added Support for Events Manager though the Gateway Add-on.
* Added "Rank Basis" option for the Rank add-on. Ranks can not be assigned based on current points balance or a users total accumulated points.
* Added Banking Add-on.
* Added support for GD Star Rating.
* Added new templates for MarketPress Gateway Add-on. Now you can customise the message shown when users do not have enough points to pay or are not logged in. Also inserted a table on the payment gateway selection page to show users current balance, cost and balance after payment.

= 1.1.2 =
* Fixed Bug #35 - Rankings is not displaying in BuddyPress profile header.
* Fixed Bug #36 - Use of incorrect reference when calling hooks.
* Fixed Bug #37 - Transferring negative amounts deducts points from recipient.
* Fixed Bug #38 - Error message if the Transfer widget is used before the Add-on is setup.
* Adjusted Transfer Autofill to require minimum 2 characters instead of just 1.
* Improved Rankings with the new myCRED_Query_Rankings class.
* Removed support for %nickname% in user related template tags.
* Added Support Forum link to Settings page.
* Added Donate Link to Plugin Links.
* Added Ranking update when plugin is re-activated.
* Added support for custom templates for the mycred_leaderboard shortcode.
* Improved how the abstract class myCRED_Hook and myCRED_Module handles settings.
* Added new mycred_apply_defaults function.
* Improved points for comments allowing points to be awarded when comments get marked as SPAM or Trashed by mistake.

= 1.1.1 =
* Moved the bbPress Hook from BuddyPress add-on to default plugin hooks.
* Added points for users adding an authors topic to favourites. By [Fee](http://wordpress.org/support/profile/wdfee).
* Added option to include authors point balance under author details and profile.
* Added [mycred_list_ranks] to Ranks Add-on.
* Added option to set if ranks should be displayed Ascending or Descending.
* Added support for 'content', 'excerpt', 'custom-fields' and 'page-attributes' for Ranks.
* Added Rank column in User list.
* Adjusted Ranks add-on to update all users ranks when an already published rank gets updated.
* Adjusted [mycred_users_of_rank] to support table outputs.
* Added new function mycred_get_total_by_time.
* Added Daily and / or Per post limits for comments. By default these are disabled.
* Added option to allow points to be awarded for comment authors reply to their own comment. By default disabled.
* Fixed Bug #31 - Language files are not loaded.
* Fixed Bug #32 - Incorrect spelling of the myCRED_Hook class for Events Manager causes white screen of death.
* Fixed Bug #33 - Hooks run() method fires to early causing custom hooks to fail to run.
* Fixed Bug #34 - Import Add-on's CubePoints import does not log import.

= 1.1 =
* Added new Email Notices Add-on.
* Added new Ranks Add-on.

* Added support for WP-Polls plugin.
* Added support for WP Favorite Posts.
* Added support for Events Manager plugin.

* Added support for MarketPress (Gateway Add-on).
* Added Zombaio as Payment Gateway for the buyCRED Add-on.
* Added filter mycred_label to allow white-labeling of myCRED.

* Added new template tags to: General and User related.
* Added new shortcode [mycred_link] to award points for users clicking on web links.
* Added new shortcode [mycred_give] to award x number of points to the current user.
* Added new shortcode [mycred_send] to send a given user x number of points if the current user can afford it.
* Added new shortcode [mycred_render_my_rank] to show either a given users rank or the current users rank. Requires Ranks Add-on.
* Added new shortcode [mycred_users_of_rank] to show all users of a given rank. Requires Ranks Add-on.
* Added new shortcode [mycred_users_of_all_ranks] to show all users of every published rank in order. Requires Ranks Add-on.

* Added the option to let purchases made with the Sell Content add-on to expire after an x number of hours.
* Added new shortcode [mycred_sell_this_ajax] to allow sale of content using AJAX. Note this shortcode can only be used once per content.
* Adjusted the myCRED List Widget to offer the same features as the [mycred_leaderboard] shortcode, adding the option to offset or change order of list.
* Adjusted the buyCRED Forms submit button location. (Suggested by dambacher)
* Adjusted the Transfer form with new CSS styling.
* Adjusted add_points() method to allow admins to change users points balances without making a log entry.

* Renamed the default %rank% template tag to %ranking% to give space for the Ranks Add-on.

* Fixed Bug #27 - Premium Content Author can not see their own content without paying.
* Fixed Bug #28 - make_purchase() method referencing arguments that does not exist (renamed).
* Fixed Bug #29 - ABSPATH issue with WP Stage plugin. (Fixed by clariner)
* Fixed Bug #30 - WooCommerce division by zero error. (Thanks hamzahali)

= 1.0.9.3 =
* Added new template tag %num_members% to show the total number of members on blog.
* Added support for user related template tags for myCRED Balance widget.
* Fixed Bug #23 - Misspelled $ref in mycred_add() function.
* Fixed Bug #24 - Exchange rate returns incorrect value.
* Fixed Bug #25 - Misspelled the new_reply method name for bbPress Hook.
* Fixed Bug #26 - Add-on address are incorrect on windows servers.

= 1.0.9.2 =
* Fixed Bug #22 - BadgeOS Badge ID issue. Critical for BadgeOS users!
* Adjusted plugin to handle custom features when adjusting a users points balance.
* Added function to handle Post/User/Comment deletions for the log.
* Renamed the `update_users_creds` method to a more logical choice of `update_users_balance`.

= 1.0.9.1 =
* Fixed Bug #17 - Shortcodes inside mycred_sell_this does not render.
* Fixed Bug #18 - Pending posts that gets published by admin are not awarded points.
* Fixed Bug #19 - mycred_subtract function not working properly.
* Fixed Bug #20 - If user id deleted the log returns empty string.
* Fixed Bug #21 - If user is removed the ranking is not updated leaving to missing users.

= 1.0.9 =
* Adjusted plugin URLs in files that were missed previously.
* Fixed Bug #16 - PHP Notice when using the `mycred_history` shortcode.

* Request #9 - Remove paragraph element for not logged in users if the login message is set to blank.
* Request #10 - Added new `mycred_my_balance` shortcode to display current users balance.
* Request #11 - Added new `mycred_sales_history` shortcode to the Sell Content Add-on to show all content purchased by the current user.
* Request #12 - Add reference search to the `mycred_history` shortcode.

= 1.0.8 =
* Added BuddyPress tag to description
* Adjusted plugin for new website mycred.me

* Fixed Bug #14 - BuddyPress Add-on causes crash if activated before BuddyPress or BuddyPress gets de-activated.
* Fixed Bug #15 - PayPal does not work with exchange rates lower then 0.01.

* Request #4 - Allow users to go minus when transferring points.
* Request #5 - For the Login Hook, impose a default 1 min limit to prevent users from logging in and out for points.
* Request #6 - Added DIV wrapper around content that is set for sale using the mycred_sell_this shortcode. Only visible to administrators.
* Request #7 - Added %gateway% template tag for buyCRED add-on showing which payment gateway was used for purchase.
* Request #8 - Added support for BadgeOS allowing users to award myCRED points for achievements.

= 1.0.7 =
* Adjusted Social Media CSS Styling.
* Fixed Bug #12 - Leaderboard Widget Title is not shown.
* Fixed Bug #13 - PayPal Payment Standard uses a reference that does not exist causing verified IPN calls to fail.

= 1.0.6 =
* Fixed Bug #10 - Incorrect call of Ranking class.
* Added Social Media Links to Settings Page.
* Fixed Bug #11 - Skrill Payment Gateway is missing supported currencies.
* Request #3 - Move the points for login hook from authenticate to wp_login.

= 1.0.5 =
* Fixed Bug #9 - Hooks are "run" too late causing some filters/actions to never fire.
* Request #1 - Adjust plugin to lower requirements for MySQL from 5.1 to 5.0.
* Request #2 - Add support for Jetpack Site & Comment Subscriptions.

= 1.0.4 =
* Fixed Bug #6 - Transfer add-on returns "low balance" for everyone.
* Fixed Bug #7 - Removed stray function in Sell Content Add-on causing error notices.
* Fixed Bug #8 - Sell Content add-on not parsing post template tags.

= 1.0.3 =
* Fixed Bug #5 - Missing general template tag parser in Sell Content form.

= 1.0.2 =
* Fixed Bug #3 - Field name collision in myCRED_Module() class.
* Fixed Bug #4 - Missing text domain for core hook titles.

= 1.0.1 =
* Fixed Bug #1 - Incorrect handling of $data variable causing a PHP Notice.
* Fixed Bug #2 - Incorrect reference to myCRED_Settings object in installer.

= 1.0 =
* Official release.