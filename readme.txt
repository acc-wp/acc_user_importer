=== ACC User Importer ===
Contributors: Raz Peel, Karine Frenette-G, Francois Bessette, Claude Vessaz
Tags: 
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Repository: https://github.com/acc-wp/acc_user_importer


== Description ==
Wordpress plugin used to synchronize the membership list obtained from the
Alpine Club of Canada (ACC) to a local section website. 

A button allows to manually trigger the update process.
Logs of the operation are written to a timestamped file.

The plugin provides the following 3 web pages for configuration:
**ACC Admin**
    -how to access to the National website
    -What role to assign new members
    -What role to assign to members right after their membership is expired
    -What role to assign to members 1 month after their membership is expired
    -A button to maually trigger the Membership update
    -logs of the tasks
**ACC Email Templates**
    -templates for email to send to new users or expired users
**ACC Cron Jobs**
    -timers intervals for the two taks to periodically run.


== Installation ==
1. Make sure the ACC User Importer plugin is installed and activated.
1. install "acc_user_importer.zip".
1. Activate the plugin.

== User Guide ==

== Road Map ==
Here are some ideas that could be implemented, sorted by likelyhood.
-setting: email addresses to notify about new and expired members
-setting: email addresses to notify about plugin operation
-setting: disable access for expired members
-setting: email addresses to never expire (ex: webmaster)
-Send summary email to an admin email. Sent only when triggered by cron,
 this way we can aggregate all information into 1 email.
 What info would admin want to know in summary email?
	-number of received records
	-list of new members
	-list of expired members
	-errors encountered by plugin (by type)
	-number of active/inactive members in local db
-there is already a Wordpress setting (see General) for default role when creating
 a user. Use this setting and get rid of default_role ACC setting.
-keep only N most recent log files
-add a action and filter hook when receiving new users. This way someone could 
 decide to filter out some people, or reformat phone numbers, etc.
-give warning about weird cases. Example: a member that is no longer part of 
 	the imported list, but still have an expiry date in the future.
-option to automatically delete inactive members
-option to provide a grace period (keep member access for N days 
 after its membership is expired)


== Contact ==
https://github.com/francoisbessette
https://github.com/cloetzi

== Acknowledgements ==

== Test Cases ==
After making changes, here are tests to perform to verify proper operation.
Test on a staging or development site with email transmission disabled.
-Trigger plugin manually using Update button
-Verify log in Update window
-Trigger plugin by Cron job: install WP Control plugin, and under that plugin,
 check Cron Events and for the job acc_automatic_import, click 'Run Now'.
-Verify log file is created correctly. Download it and inspect it.
-In the log file, the following errors are normal. They are caused by
 family members having the same email address, or not having an email address.
    > error: no valid email given, cannot create new user account.
    > error, email already used by someone else, skip
-verify that a goodbye email is sent (if option is enabled) to an expired user
-Test user creation
    -delete an existing user in the local database
    -trigger the plugin to import membership
    -verify user is re-created
    -verify all fields associated to newly created user. 
    -verify user_login (username) is set according to the configured setting.
    -verify user has the right default role.
    -verify that a welcome email is sent (if option is enabled).
-Test user expiry
    -pick an inactive user in the local DB.
    -in the user profile page, double-check that the expiry date is in
     the past. Force the acc_status to active and save changes.
    -run the plugin import
    -the plugin should notice that the expiry date is in the past
     and change acc_status to inactive.
    -verify goodbye email is sent (if option is enabled).


== Changelog ==
1.2.3 Francois Bessette
    -Fix minor review comments

1.2.2 Francois Bessette
    -add a new processing loop to review membership expiry date and send
     welcome and goodbye emails. This is done after the import phase.
    -add one more user meta variable called acc_status, with value
     either active or inactive. This is needed to detect transition
     and only send 1 email.
    -clean a few logs
    -translate email settings to english. French can come later.

1.2.1 Francois Bessette
    -Fix bug when default role was set to organisateur-trice.

1.2.0 Francois Bessette and Claude Vessaz, 2020-12-23
    -Fix major bug where only the first 100 users would be imported when triggered by timer
    -added setting to control mapping of user_login (username).
    -added setting to control whether the user_login is updated for users already in DB.
    -simplified the settings page, move CRON settings on same page
    -fixed bug where the log 'Delete' button was not working
    -removed obsolete settings related to changing the role during update
