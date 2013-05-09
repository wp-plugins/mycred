=== myCRED ===
Contributors: designbymerovingi
Donate Link: http://mycred.merovingi.com/donate/
Tags:points, tokens, credit, management, reward, charge, community
Requires at least: 3.1
Tested up to: 3.5.1
Stable tag: 1.0.6
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

Add-ons are custom features that can be enabled individually.

* *Transfer* - Allows your users to send points to other members with an option to impose a daily-, weekly- or monthly transfer limit.
* *Import* - Import points from a CSV-file, Cubepoints or points stored under any custom user meta key.
* *Sell Content* - Sell access to entire contents or parts of it with the option to share a percentage of the sale with the content author.
* *Buy Creds* - Let your users buy points via PayPal, Skrill or NETbilling.
* *Gateway* - Allow your users to pay for items in their WooCommerce shopping cart using their point balance.
* *BuddyPress* - Extend **my**CRED to support [BuddyPress](http://wordpress.org/extend/plugins/buddypress/), [bbPress](http://wordpress.org/extend/plugins/bbpress/), [BuddyPress Gifts](http://wordpress.org/extend/plugins/buddypress-gifts/), [BuddyPress Links](http://wordpress.org/extend/plugins/buddypress-links/), [BP Album+](http://wordpress.org/extend/plugins/bp-gallery/) and [BP Gallery](http://buddydev.com/plugins/bp-gallery/).


**Multisites**

**my**CRED supports Multisite installations and offers you the following features:

* *Master Template* - Force your main sites **my**CRED installation upon all other sites. Each site will have it's own log but have no access to any settings, hooks or add-ons.
* *Block List* - Allows you to block specific sites from using **my**CRED.


**Supported Third-party Plugins:**

The following third party plugins are supported by default:

* [Contact Form 7](http://wordpress.org/extend/plugins/contact-form-7/) - Award users points for submitting forms.
* [Invite Anyone Plugin](http://wordpress.org/extend/plugins/invite-anyone/) - Award users for sending invitations and for each time an invited user accepts and signs up.
* [Jetpack](http://wordpress.org/extend/plugins/jetpack/) - Award users for subscribing to comments or your site. Requires users to be logged in or subscribing using the email saved in their profile.


**Further Details**

* [myCRED Features](http://mycred.merovingi.com/about/features/)
* [myCRED Hooks](http://mycred.merovingi.com/about/hooks/)
* [myCRED F.A.Q.](http://mycred.merovingi.com/about/faq/)
* [myCRED Add-ons](http://mycred.merovingi.com/add-ons/)
* [myCRED Tutorials](http://mycred.merovingi.com/support/tutorials/)
* [myCRED Known Issues](http://mycred.merovingi.com/download/known-issues/)
* [myCRED Codex](http://mycred.merovingi.com/support/codex/)

**Contact**

* [General Inquiries](http://mycred.merovingi.com/contact/)
* [Bug Report](http://mycred.merovingi.com/contact/report-bug/)
* [Request Feature](http://mycred.merovingi.com/contact/request-feature/)


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


== Screenshots ==

1. **Multisites** - The myCRED Network Settings Page gives you access to the Master Template feature and the Block List.
2. **Gateway Add-on** - Using **my**CRED as a Payment Gateway in your WooCommerce Shopping Cart plugin.
3. **The Log** - with the option to search or filter results. Each user will also get their own Log page under the "Users" menu.
4. **Editing Users Balance** - You can edit each users point balance directly under "Edit User".
5. **Import Add-on** - The Import Add-on allows you to import points using a CSV file or by importing existing points from your database.


== Changelog ==

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


== Upgrade Notice ==

= 1.0.5 =
Upgrade to add support for Jetpack Site & Comment Subscriptions and to lower MySQL requirement from 5.1 to 5.0. Requires Jetpack 2.2 or higher.