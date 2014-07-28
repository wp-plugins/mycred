=== myCRED ===
Contributors: designbymerovingi
Tags:points, tokens, credit, management, reward, charge, community, contest, BuddyPress, Jetpack, bbPress, woocommerce, marketpress, wp e-commerce, gravity forms
Requires at least: 3.8
Tested up to: 4.0
Stable tag: 1.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

myCRED is an adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website.

== Description ==

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

** myCRED Guides **

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

= 1.5.1 =
Important bug fixes.


== Other Notes ==

= Requirements =
* WordPress 3.8 or greater
* PHP version 5.3 or greater
* PHP mcrypt library enabled
* MySQL version 5.0 or greater

= Language Contributors =
* Swedish - Gabriel S Merovingi
* French - Chouf1 [Dan - BuddyPress France](http://bp-fr.net/)
* Persian - Mani Akhtar [Dan - BuddyPress France](http://mycred.me/members/maniv-a/)
* Spanish - Rob Row [Website](http://robertrowshan.com)
* Russian - Skladchik
* Chinese - Changmeng Hu
* Portuguese (Brazil) - Guilherme


== Changelog ==

= 1.5.1 =
* FIX - Bad logic for management locks out the option to "Empty log".
* FIX - Log export to CSV for search results are not working.
* FIX - Misspelled "Registration" fix. (thanks Jesus Cardenas)
* FIX - Calculate totals uses incorrect meta_key search. (thanks Jim)
* FIX - Ranks on Multisites saves the users total under an incorrect meta key.
* FIX - "No Rank" is shown on multisites when the Master Template feature is enabled.
* FIX - Points history in toolbar not showing for those who do not use BuddyPress.
* FIX - rtMedia Hook is not awarding / deducting points for media deletions.
* FIX - mycred_total_balance is not counting multiple point types correctly.
* FIX - Transfer form showing all point types instead of the selected ones.
* FIX - buyCRED Update script should not run if buyCRED is not enabled.
* TWEAK - Adjusted the transfer script to add support for custom error messages.
* NEW - Hook for content views.
* FIX - Email Notifications are not sent for general balance changes.
* FIX - Email Notifications are not sent on servers where wp_mail is restricted.

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