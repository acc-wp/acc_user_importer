# ACC User Importer

Contributors: Francois Bessette, Claude Vessaz, Raz Peel, Karine Frenette-G

Tags:

Stable tag: 2.0.3

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
#### User Settings
These settings control the operation of the plugin. Remember to click on
Save Changes after changing parameters!
- Section for which to import membership: Select the name of the section for which
you want to import the membership. This selects the API number to be used when
requesting membership information.
- One or more section authentication tokens: enter in this box the API token to be
used for authentication. More than one token can be entered. Although the
plugin only operates on one section when it runs (the one selected in the
above setting), some admin people have to sequentially import members
from more than one section, and it is a convenience to not have to
change the token string everytime a different section is selected.
The format is like this:  SECTION1:Token1,SECTION2:Token2,...
No space before or after the comma.  The plugin only support these
section names right now, and make sure the spelling and case is exact:
| Valid Section Names   |
| ----------- |
| SQUAMISH      |
| CALGARY      |
| MONTRÉAL      |
| OUTAOUAIS      |
| OTTAWA      |
| VANCOUVER   |

- Sync changes since when? On the next run, the plugin will request for
all membership changes since that date. The field normally shows the
last run time (in UTC), but you can force a date in ISO 8601 format
such as 2020-11-23T15:05:00. Note that when after an automatic
(timer triggered) run, the plugin updates that value, but not when
the run was manually triggered.
- Set usernames to: what username to assign new members. Most section
have decided to standardize on ACC Member Number.
- Usernames will transition from ContactID? Only to be used by the
Vancouver section, where members currently have ContactID as their usernames.
Selecting this option adds a safety check in case the incoming ACC
member number matches the ContactID of an unrelated member.
- Test mode (do not actually update the local Wordpress database).
If you enable this option, the plugin will run and will display the
received data, but will not update the local Wordpress database.
- What role to assign new members
- Should plugin modify the role when a member becomes expired?
This option allows to change the permissions given to a user
that is no longer member.
- Admin to notify: Enter an email or a list of emails (comma separated)
to be notified whenever the plugin adds or expires members.
Normally an admin email account. Leave blank for no notifications.
- Title of admin notification email

#### Manual Membership Update
The blue button on the right triggers the plugin operation. The log
appears in the box below.  Once the operation is finished, refreshing
the page will reveal a new log file lower in the web page, under
Recent log files.  The log file can be downloaded.


### ACC Email Templates
- templates for email to send to new users or expired users


## Installation
1. install "acc_user_importer_x_y_z.zip".
2. Activate the plugin.
One hour after activation, the plugin will execute its first automatic
update, then normally after every 12 hours.

## User Guide

### Flow of operation
- Using the token the plugin contacts the 2M Changed Member API and requests
for all memberships change since the last run (or the date entered
in the settings).
- The 2M server replies with a list of members which changed. 100 maximum
at a time.
- If needed, the plugin loops and keep requesting until it has the full list
- Using the token the plugin contacts the 2M Member API and requests for
detailed information about a list of members.  Up to 50 at a time.
- The 2M server replies. For each member, it gives information such as
firsnane, lastname, email, phone, etc. plus a list of memberships relevant
for the section.
- the plugin receives the data, and searches for a corresponding member in
the local Wordpress database. If none is found, a new user is created.
If one is found, the information is verified and updated if needed.
- Case of a member that did not renew: his number will show up in the
response from the Changed Member API, but he will not have an entry
in the Member API. The reason is, since this user is no longer part
of the section club, the section should not really access his personal
information and does not need to.  The absence of the user
means he is no longer a member.  CUrrently the plugin is not super wise.
It does not know that the user was in the Changed Member and is missing
in the Member API. The plugin simply processes what it receives, and
since that user is missing, nothing is done. Normally, this is fine
since the user should be missing when the membership expiry is
reached. Doing nothing simply means that the user account will still
live in the Wordpress site, however as soon as his membership expiry date
is reached, he will not be able to connect.
The only caveat is, if a user membership was prematurely terminated,
this would go unseen by the plugin and the user would still be
able to connect to the Wordpress site until his normal expiry
is reached.  We could improve on this eventually.
- Once all received memberships have been processed, the plugin
does a special operation called expiry processing. It scans
the whole database of Wordpress users. If a user was "inactive"
and his expiry date is in the future, a Welcome email is sent
and the user is set to "active".  If a user was "active"
and his expiry date is in the past, a Goodbye email is sent
and the user is set to "inactive".

### What decides if a user can connect or not to the site?
The "expiry" field associated with each user is what decides
if the user can connect. The plugin adds a hooks to Wordpress
and for each connection, verifies the "expiry" date. If
it is in the past, the connection is rejected.

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
- The 2M server is throttling API requests at 20 per minute max.
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
