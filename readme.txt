=== myCRED ===
Contributors: designbymerovingi
Donate Link: http://mycred.me/donate/
Tags:points, tokens, credit, management, reward, charge, community, contest, BuddyPress, Jetpack
Requires at least: 3.1
Tested up to: 3.5.1
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

myCRED is an adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website.

== Description ==

We feel that todays WordPress community lacks a flexible points management system. Existing system often feel restrictive, stale or lack support for popular plugins.

So we built an adaptive plugin which gives it’s users full control on how points are awarded, used, traded, managed, logged and presented.

**my**CRED is an adaptive points management system for WordPress powered websites, giving you full control on how points are gained, used, traded, managed, logged or presented.

**Core Features:**

* Logging of all events
* Log entry templates and template tags
* Easy User Points editing
* Easy to manage Hooks for each instance where users gain/loose points
* Supports any point format
* Ranking
* Custom My Balance Widget
* Custom Leader board Widget
* Minimum CSS Styling


**Add-ons:**

Your myCRED installation comes packed with optional add-ons, adding further features and third-party plugin support.

* *Email Notices* - Setup email notices for your users and/or admins when a users points balance changes or on specific events, for example when they purchase content set for sale.
* *Transfer* - Allows your users to send points to other members with an option to impose a daily-, weekly- or monthly transfer limit.
* *Import* - Import points from a CSV-file, Cubepoints or points stored under any custom user meta key.
* *Sell Content* - Sell access to entire contents or parts of it with the option to share a percentage of the sale with the content author.
* *Buy Creds* - Let your users buy points via PayPal, Skrill, Zombaio or NETbilling.
* *Ranks* - Allows you to setup ranks based on your users points balance.
* *Gateway* - Allow your users to pay for items in their WooCommerce or MarketPress shopping cart using their point balance.
* *BuddyPress* - Extend **my**CRED to support [BuddyPress](http://wordpress.org/extend/plugins/buddypress/), [bbPress](http://wordpress.org/extend/plugins/bbpress/), [BuddyPress Gifts](http://wordpress.org/extend/plugins/buddypress-gifts/), [BuddyPress Links](http://wordpress.org/extend/plugins/buddypress-links/), [BP Album+](http://wordpress.org/extend/plugins/bp-gallery/) and [BP Gallery](http://buddydev.com/plugins/bp-gallery/).


**Multisites**

**my**CRED supports Multisite installations and offers you the following features:

* *Master Template* - Force your main sites **my**CRED installation upon all other sites. Each site will have it's own log but have no access to any settings, hooks or add-ons.
* *Block List* - Allows you to block specific sites from using **my**CRED.


**Supported Third-party Plugins:**

The following third party plugins are supported by default:

* [Contact Form 7](http://wordpress.org/extend/plugins/contact-form-7/) - Award users points for submitting forms.
* [Invite Anyone Plugin](http://wordpress.org/extend/plugins/invite-anyone/) - Award users for sending invitations and for each time an invited user accepts and signs up.
* [Jetpack](http://wordpress.org/extend/plugins/jetpack/) - Award users for subscribing to comments or your site. Requires users to be logged in or subscribe using the email saved in their profile.
* [BadgeOS](http://wordpress.org/extend/plugins/badgeos/) - Award points for any BadgeOS achievement type.
* [WP-Polls](http://wordpress.org/plugins/wp-polls/) - Award points for users voting in polls.
* [WP Favorite Posts](http://wordpress.org/plugins/wp-favorite-posts/) - Award points for users adding posts to their favorites or deduct points if they remove posts.
* [Events Manager](http://wordpress.org/plugins/events-manager/) - Award points for users attending events with the option to deduct points if attendance is cancelled.



**Further Details**

* [Features](http://mycred.me/about/features/)
* [Hooks](http://mycred.me/about/hooks/)
* [F.A.Q.](http://mycred.me/about/faq/)
* [Add-ons](http://mycred.me/add-ons/)
* [Tutorials](http://mycred.me/support/tutorials/)
* [Known Issues](http://mycred.me/download/known-issues/)
* [Codex](http://mycred.me/support/codex/)

**Contact**

* [General Inquiries](http://mycred.me/contact/)
* [Bug Report](http://mycred.me/contact/report-bug/)
* [Request Feature](http://mycred.me/contact/request-feature/)


== Installation ==

1. Upload `mycred` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Run the myCRED Setup which will allow you to configure your **my**CRED installation
4. Activate the Add-ons you wish to use under the 'myCRED' menu in WordPress
5. Configure and Enable the hooks you wish to use though the 'Hooks' sub menu in WordPress
6. Configure any other Add-on settings you might be using i.e. BuddyPress though the 'Settings' sub menu in WordPress


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


== Changelog ==

**Updating to 1.1.1** Note that in this version the bbPress hook has been moved from the BuddyPress add-on to the core plugin support. This means that you you no longer require BuddyPress to access the bbPress Hook!

Once you have updated to 1.1.1, visit the Hooks sub-menu page in the myCRED menu and update your bbPress settings!

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

* Fixed Bug #31 - Language files are not loaded.
* Fixed Bug #32 - Incorrect spelling of the myCRED_Hook class for Events Manager causes white screen of death.
* Fixed Bug #33 - Hooks run() method fires to early causing custom hooks to fail to run.
* Fixed Bug #34 - Import Add-on' CubePoints import does not log import.

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