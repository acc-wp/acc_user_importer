=== ACC User Importer ===
Contributors: Raz Peel, Karine Frenette-G, Francois Bessette, Claude Vessaz
Tags: 
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Repository: https://github.com/acc-wp/acc_user_importer


== Description ==
Wordpress plugin used to synchronize the membership list obtained from the
Alpine Club of Canada (ACC) to a local section website. 

The plugin wakes up periodically (default 2x per day) to do the membership
import.  A button can also manually trigger the update process.
Logs are written to a timestamped file.

The plugin provides the following 2 web pages for configuration:
**ACC Admin**
    -how to access to the National website
    -what username to assign new members
    -whether to update username for an existing user (normally set to NO)
    -What role to assign new members
    -periodic Cron timer interval
    -A button to maually trigger the Membership update
    -logs of the tasks
**ACC Email Templates**
    -templates for email to send to new users or expired users


== Installation ==
1. install "acc_user_importer_x_y_z.zip".
2. Activate the plugin.

== User Guide ==

--Sending of "Welcome" and "Goodbye" emails--
There are checkboxes to control whether a Welcome and Goodbye email
are sent to a new or expired member.
Sending of a Welcome email is done whenever a new user account is created,
or whenever an expired user renews its membership.
Sending of a Goodbye email is done whenever a member 'expiry' date
is in the past.
To help with expiry detection and avoid sending an email on every run 
of the plugin, in the database each user has a meta variable called 
acc_status. The acc_status is set according to the user 'expiry' date:
    user expiry                           user acc_status
    --------------------------            -----------------------------------
    in the future                         active
    in the past (or field not set):       inactive

An email is sent whenever the acc_status state changes.
When upgrading an existing installation, we dont want to flood all users
with Welcome/Goodbye emails.  So when the plugin runs, it will avoid
sending emails for existing users that do not have such variable yet
in the database. But it will create the acc_status variable, and from then
on will send emails on state changes. Assuming the email checkbox is set,
of course.



== Hooks ==
do_action('acc_new_membership', $userID);
Called each time a new user account is created during import.

do_action('acc_membership_renewal', $existingUser->ID);
Called each time an existing user 'expiry' date changes. 
Yes, this is not perfect, there is an assumption here that the expiry 
will only change forward because of a renewal. Could be improved. 

do_action("acc_member_welcome", $user->ID);
Called whenever a Welcome email would be sent (assuming checkbox is enabled).

do_action("acc_member_goodbye", $user->ID);
Called whenever a Goodbye email would be sent (assuming checkbox is enabled).



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
1.3.1 Francois Bessette
    Fixes for Mtl section:
    -Make imis_id optional
    -users with no expiry date are now considered active

1.3.0 Francois Bessette
    -Add options to change role when member becomes expired, and 
     restore previous role when member renews.

1.2.6 Francois Bessette
    -Add an option to specify the title for the notification email.

1.2.5 Francois Besssette
    -Ignore error if data received from ACC server is missing Membership
     field. Temp fix for Mtl, never pushed to Github.

1.2.4 Francois Bessette
    -The user can now configure a list of emails who will be notified whenever
     the web site membership changes (user created/renewed, or becames expired).
    -Leave the field blank if you want no email notifications.

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
