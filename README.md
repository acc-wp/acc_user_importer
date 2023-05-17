# ACC User Importer

Contributors: Francois Bessette, Claude Vessaz, Raz Peel, Karine Frenette-G

Tags:

Stable tag: 1.4.2

License: GPLv2 or later

License URI: http://www.gnu.org/licenses/gpl-2.0.html

Repository: https://github.com/acc-wp/acc_user_importer


## Description
Wordpress plugin used to synchronize the membership list obtained from the
Alpine Club of Canada (ACC) to a local section website.

The plugin wakes up periodically (default 2x per day) to do the membership
import.  A button can also manually trigger the update process.
Logs are written to a timestamped file.

The plugin provides the following 2 web pages for configuration:

### ACC Admin
- Which section API the plugin should connect to
- Authentication token or token list
- Sync changes since when?
- what username to assign new members
- Test mode (do not actually update the local Wordpress database)
- What role to assign new members
- periodic Cron timer interval
- A button to manually trigger the Membership update
- logs of the tasks

### ACC Email Templates
- templates for email to send to new users or expired users


## Installation
1. install "acc_user_importer_x_y_z.zip".
2. Activate the plugin.
One hour after activation, the plugin will execute its first automatic
update, then normally after every 12 hours.

## User Guide

### Sending of "Welcome" and "Goodbye" emails

There are checkboxes to control whether a Welcome and Goodbye email are sent to a new or expired member.
Sending of a Welcome email is done whenever a new user account is created, or whenever an expired user renews its membership.
Sending of a Goodbye email is done whenever a member 'expiry' date is in the past. To help with expiry detection and avoid sending an email on every run
of the plugin, in the database each user has a meta variable called `acc_status`. The `acc_status` is set according to the user `expiry` date:

| user expiry                    | user acc_status |
| ------------------------------ | --------------- |
| in the future                  | active          |
| in the past (or field not set) | inactive        |

An email is sent whenever the acc_status state changes. When upgrading an existing installation, we don't want to flood all users with Welcome/Goodbye emails.  So when the plugin runs, it will avoid sending emails for existing users that do not have such variable yet in the database. But it will create the acc_status variable, and from the non will send emails on state changes. Assuming the email checkbox is set, of course.



## Hooks
- `do_action('acc_new_membership', $userID)`: Called each time a new user account is created during import.
- `do_action('acc_membership_renewal', $existingUser->ID)`: Called each time an existing user 'expiry' date changes. Yes, this is not perfect, there is an assumption here that the expiry will only change forward because of a renewal. Could be improved.
- `do_action("acc_member_welcome", $user->ID)`: Called whenever a user's membership is switched from inactive to active.
- `do_action("acc_member_goodbye", $user->ID)`: Called whenever a user's membership lapsed.


## Caveats
- The 2M server is throttling API requests at 10 per minute max.
  To avoid HTTP errors, we sleep when processing large amount of data.
  This may cause the web site to become unresponsive for a minute or so.
- The log files accumulate forever, so take more and more memory over time.
  For the moment it is good to manually delete old files once in a while.
- It is possible for the 2M Changed Member API to return a number to
  indicate a member change, and then for the Member API to return nothing
  for that particular member. This may happen if the member is no longer
  member of ACC.  Right now if this happens, the plugin is not wise
  enough to understand that something is missing in the response.
  It processes all received responses, and therefore ignores the
  missing member. The result: nothing is updated in the DB for that user.
  This is fine assuming the membership expired at the planned date.
  However if the ACC decides to expire prematurely this member
  (expell, cancel, reimburse, etc), then on the local section web site
  the membership would still be valid until the last received expiry date.


## Road Map
Here are some ideas that could be implemented, sorted by likelihood.
- setting: enable/disable automatic operation (Cron job)
- setting: email addresses to notify about new and expired members
- setting: email addresses to notify about plugin operation
- setting: disable access for expired members
- setting: email addresses to never expire (ex: webmaster)
- Send summary email to an admin email. Sent only when triggered by cron,
 this way we can aggregate all information into 1 email.
 What info would admin want to know in summary email?
	- number of received records
	- list of new members
	- list of expired members
	- errors encountered by plugin (by type)
	- number of active/inactive members in local db
- there is already a Wordpress setting (see General) for default role when creating
 a user. Use this setting and get rid of default_role ACC setting.
- keep only N most recent log files
- add a action and filter hook when receiving new users. This way someone could
 decide to filter out some people, or reformat phone numbers, etc.
- give warning about weird cases. Example: a member that is no longer part of
 	the imported list, but still have an expiry date in the future.
- option to automatically delete inactive members
- option to provide a grace period (keep member access for N days after its membership is expired)


## Contact
* https://github.com/francoisbessette
* https://github.com/cloetzi

## Acknowledgements

## Test Cases
After making changes, here are tests to perform to verify proper operation.
Test on a staging or development site with email transmission disabled.
- Trigger plugin manually using Update button
- Verify log in Update window
- Trigger plugin by Cron job: install WP Control plugin, and under that plugin,
 check Cron Events and for the job acc_automatic_import, click 'Run Now'.
- Verify log file is created correctly. Download it and inspect it.
- In the log file, the following errors are normal. They are caused by
 family members having the same email address, or not having an email address.
    > error: no valid email, cannot create new user account.

    > error, email already used by someone else, skip
- verify that a goodbye email is sent (if option is enabled) to an expired user
- Test user creation
    - delete an existing user in the local database
    - trigger the plugin to import membership
    - verify user is re-created
    - verify all fields associated to newly created user.
    - verify user_login (username) is set according to the configured setting.
    - verify user has the right default role.
    - verify that a welcome email is sent (if option is enabled).
- Test user expiry
    - pick an inactive user in the local DB.
    - in the user profile page, double-check that the expiry date is in
     the past. Force the acc_status to active and save changes.
    - run the plugin import
    - the plugin should notice that the expiry date is in the past
     and change acc_status to inactive.
    - verify goodbye email is sent (if option is enabled).
