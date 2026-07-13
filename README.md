# ACC User Importer

Contributors: Francois Bessette, Claude Vessaz, Raz Peel, Karine Frenette-G

Tags:

License: GPLv2 or later

License URI: http://www.gnu.org/licenses/gpl-2.0.html

Repository: https://github.com/acc-wp/acc_user_importer

## Description

Wordpress plugin used to synchronize the membership list obtained from the
Alpine Club of Canada (ACC) to a local section website.

The plugin creates a rest API endpoint. The ACC Hubspot system is configured
to send a notification to this endpoint each time a membership changes.
The plugin also wakes up weekly to do database cleanup (delete expired users)
and also check for user membership inconsistencies.
A button can also manually trigger the local database check process.
Logs are written to a timestamped file.

The plugin can be configured using a settings page:

### ACC User Importer settings

#### General Settings

These settings control the general operation of the plugin. Remember to click on
Submit Changes after changing parameters!

- List of sections to import: Select the sections for which you want the
  memberships to be imported. Note that the ACC national platform (Hubspot)
  needs to be configured to export those section memberships. So this
  setting acts as a filter and enables a section-specific setting tab.
- API authentication token: this is the token that Hubspot needs to use
  for the notifications to be accepted by the Wordpress site.
- Set usernames to: what should the login name be set to? The recommended
  setting is ACC Member Number. Note that normally, users login using
  their email address rather than their ACC Member Number. So the
  login name is mainly used as a key to uniquely search for members.
- Also check local DB sanity? If this option is
  checked, then the plugin periodically (every week by default)
  scan the local user database to verify sanity.
- Delete expired user accounts after a while? If checked, the plugin
  will delete user accounts after they have been inactive a while.
  This is useful to keep the website lean and also prevent private
  member information to be stored forever.
- How many days before deleting an expired user from database?
  In order to protect members personal information, it is good practice
  to eventually delete the data of people who are no longer with the club.
  Enter the number of days the data should be kept in the database
  after a member leaves. 0=delete right away, 1=one day, etc.
- When deleting a user, who will become the new content owner?
  Enter the new owner login name. Suggestion: manually
  create a dummy user (example: 'ex-member') to receive
  ownership of content for users we need to delete,
  and enter its login name here. The plugin will reassign
  posts, pages, articles, events. Leaving this box
  empty will delete the user content along with the user.
  and you might end up with missing pages or broken links.
- Admin to notify: Enter an email or a list of emails (comma separated)
  to be notified whenever the plugin adds or expires members.
  Normally an admin email account. Leave blank for no notifications.
- Title of admin notification email
- Maximum number of log files to keep: how many log files to keep on the disk.
  Note that each new notification from Hubspot creates a distinct logfile.
  Do not use value 0, it does not work.
- Text to display on failed login because user is expired: What error
  to display to a user when he is expired.
- Text to display on failed login because user has not signed the waiver:
  What error to display to a user when he his account does not have
  a valid signed waiver.

#### Per-Section Settings

These settings control the behavior of the plugin for a given section.
Don't forget to click on the Submit Changes button, located below
the settings.

- When creating a new user, how to change role?
  You have the choice to take no action, set role to a value or add a
  role to the member.
- Role value? Value related to the previous choice.
- When expiring a user, how to change role?
  You have the choice to take no action, set role to a value or remove a
  role from the member.
- Role value? Value related to the previous choice.
- Send Welcome email? If enabled, the plugin will send a welcome email.
- Welcome email title
- Welcome email content
- Send Goodbye email? If enabled, the plugin will send a goodbye email.
- Goodbye email title
- Goodbye email content

#### Manual DB Check

The blue button on the right triggers the database check operation. The log
appears in the box below. Once the operation is finished, refreshing
the page will reveal a new log file lower in the web page, under
Recent log files. The log file can be downloaded.

### ACC CRON Job

- Shows how often the DB check is performed.

## Installation

1. install "acc_user_importer_x_y_z.zip".
2. Activate the plugin.
   One hour after activation, the plugin will execute its first automatic
   update, then normally after every 12 hours.

## User Guide

### Flow of operation

- When a POST notification is received from Hubspot, the plugin does
  validation on the received fields and checks the action field.
  Add and Modify are processed the same way: we search for an existing
  member, and if it exists, we update its data with the received information.
  If the member does not exists, we create it. If configured and
  applicable, a welcome or goodbye email is sent to the user.
  Let's take an example where the plugin is configured to track the
  memberships for three sections: A, B and C. Say the user was a member
  of A and B, and we receive a action=Modify indicating the user
  is a member of A and C, then the plugin will send a Welcome email
  for section C and a Goodbye email for section B (if configured
  to send emails).
  For action=remove, the plugin updates the member information
  (set acc_mship_expiry to today), however the
  member is not deleted from the Wordpress user database. This is
  because the user might renew his membership soon (he might have
  missed the deadline) and we dont want to delete his website
  contributions (posts, activities, etc) unnecessarily.

### User Login

The plugin adds a hooks to Wordpress. Each time a user tries to login,
the plugin verifies the following:

#### Normal case

- Allow user if acc_mship_expiry date is good and
  acc_waiver_expiry date is good (indicating waiver has been signed).
- A specific error message is given in the case where the waiver
  needs to be signed.

#### Special cases

- If the user is a Wordpress admin, we allow login
- If the user does not have an acc_member_id, we assume it is a manually
  created account (for admin purpose) and we allow login.
- If the user has an empty acc_mship_expiry date, it is either a lifetime
  member or an auto-renewed membership, so we allow login, as long as
  the acc_waiver_expiry date is good. In terms of security, if an auto-renewed
  membership does not renew, Hubspot will send us a notification to
  terminate membership. And the acc_waiver_expiry field would anyway
  prevent login eventually if the waiver is not renewed.

### Deletion of outdated members

- If you do not want to delete expired members in the database even
  if they are expired for several years, enter a big integer number such as
  9999 in the "How many days before deleting" configuration.
- If you have manually-entered administrative accounts for webmasters,
  content creators, etc and want to protect their account from being deleted,
  just make sure they either do not have an expiry date or have one that
  is far in the future such as 2099-01-01.
- If the "who will become the new content owner" box is empty,
  the content is deleted along with outdated members. If the box contains
  something but that user cannot be found, the plugin generates an error
  in the log but does not proceed with the delete.

### Sending of "Welcome" and "Goodbye" emails

There are checkboxes to control whether a Welcome and Goodbye email are sent to a new or expired member.
Sending of a Welcome email is done whenever a new user account is created, or whenever an expired user renews its membership.
Sending of a Goodbye email is done whenever a member becomes not valid.

## Hooks

- `do_action('acc_new_membership', $userID)`: Called each time a new user account is created in the database.
- `do_action("acc_member_welcome", $user->ID)`: Called whenever a user's membership changes from (non-existent or inactive) to active
- `do_action("acc_member_goodbye", $user->ID)`: Called whenever a user's membership becomes no longer active.

## Caveats

## Ideas for the future

- add a action and filter hook when receiving new users. This way someone could
  decide to filter out some people, or reformat phone numbers, etc.

## Contact

- https://github.com/francoisbessette

## Acknowledgements

## Test Cases

After making changes, here are tests to perform to verify proper operation.
Test on a staging or development site with email transmission disabled.

- Trigger manually the DB check using button
- Verify log in Update window
- Trigger DB check by Cron job: install WP Control plugin, and under that plugin,
  check Cron Events and for the job acc_automatic_db_check, click 'Run Now'.
- Verify log file is created correctly. Download it and inspect it.
- Use Postman to manually send notifications instead of Hubspot
- verify that a goodbye email is sent (if option is enabled) to an expired user
- Test user creation
  - manually delete an existing user in the local database
  - Using Postman, send a Add notification
  - verify user is re-created
  - verify all fields associated to newly created user.
  - verify user_login (username) is set according to the configured setting.
  - verify user has the right default role.
  - verify that a welcome email is sent (if option is enabled).
  - verify the user can login
- Test user with invalid waiver
  - pick an active user in the local DB.
  - Using Postman, send a Modify notification with acc_waiver_expiry in the past.
  - verify the user profile now shows a waiver expiry in the past.
  - verify the user cannot login and is presented with the waiver login error.
- Test user expiry
  - pick an active user in the local DB.
  - in the user profile page, double-check that the acc_mship_expiry date is in
    the future.
  - Using Postman, send a Remove notification with acc_mship_expiry in the past.
  - Check the log, the plugin should declare the user is expired.
  - verify goodbye email is sent (if option is enabled).
  - verify the user can no longer login
