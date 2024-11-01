=== User Login Disable ===
Contributors: chexwarrior
Tags: users, login, security, audit
Requires at least: 5.6
Tested up to: 6.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allows administrators to disable other user logins.

== Description ==

This plugin allows administrators to enable or disable other user account's logins.

When a user is disabled:
* They can not login to the site
* They are immediately logged out of the site
* Any [application passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) created with this user will no longer authenticate with the WP REST API

= WP-CLI Integration =
This plugin integrates with WP-CLI and provides the following two commands:

	# Enables the target users
	wp user enable <List of User IDs, Logins or Emails> [--all]

	# Disables the target users
	wp user disable <List of User IDs, Logins or Emails> [--all]

== Installation ==

= Classic =
1. Upload the `user-login-disable` dir to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

= Composer =
1. Check out the instructions [here](https://composer.rarst.net/)

== Frequently Asked Questions ==

= Why does this plugin exist? =
Very specific audit reasons at a job of mine, and I couldn't find another plugin that did this w/o a ton of other unneeded functionality.

== Screenshots ==

1. The Disable field on a user profile
2. Disabled users on the admin user list page

== Support ==

This plugin is developed on the author's own time so there is no guarantee that reported bugs will be fixed in a timely fashion.

To report a bug please visit the plugin's [GitHub repository](https://github.com/ChexWarrior/wp-user-disable-login).

== Changelog ==

= 0.1 =
* Initial Release
