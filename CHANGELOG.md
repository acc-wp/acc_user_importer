# Changelog

## 2.0.3 Claude Vessaz

- Remove update of user_nicename field during ID migration. The nicename is used for static URLs and changing it breaks all kind of stuff.

## 2.0.2 Francois Bessette

- Sleep only 4s since 2M reduced their server throttling to 20 requests per minute

## 2.0.1 Francois Bessette

- Optimized sleeps used to avoid HTTP too many requests errors

## 2.0.0 Francois Bessette

- Adapted code to the new Interpedia-based ACC IT platform

## 1.4.2 Francois Bessette

- Fix bug where a lapsed user would still be able to login.

## 1.4.1 Francois Bessette

- Revert 2 small changes made by Claude in 1.4.0: Keep Firstname, Lastname as being the default login when creating a new account, and skip received user record if expiry seems wrong.

## 1.4.0 Claude Vessaz

- Remove option to change WP login ID.
- Change default ID to ContactId.
- Improve expired membership error message.
- Use local expiry date if it is later than ACC national provided date.
- Generate secure password for new users.
- Fix possible issue with setting initial acc_status
- Delay initial cron job run by 1h.
- Delete old email templates.

## 1.3.1 Francois Bessette

- Make imis_id optional
- users with no expiry date are now considered active

## 1.3.0 Francois Bessette

- Add options to change role when member becomes expired, and restore previous role when member renews.

## 1.2.6 Francois Bessette

- Add an option to specify the title for the notification email.

## 1.2.5 Francois Besssette

- Ignore error if data received from ACC server is missing Membership field. Temp fix for Mtl, never pushed to Github.

## 1.2.4 Francois Bessette

- The user can now configure a list of emails who will be notified whenever the web site membership changes (user created/renewed, or becames expired).
- Leave the field blank if you want no email notifications.

## 1.2.3 Francois Bessette

- Fix minor review comments

## 1.2.2 Francois Bessette

- add a new processing loop to review membership expiry date and send welcome and goodbye emails. This is done after the import phase.
- add one more user meta variable called acc_status, with value either active or inactive. This is needed to detect transition and only send 1 email.
- clean a few logs
- translate email settings to english. French can come later.

## 1.2.1 Francois Bessette

- Fix bug when default role was set to organisateur-trice.

## 1.2.0 Francois Bessette and Claude Vessaz, 2020-12-23

- Fix major bug where only the first 100 users would be imported when triggered by timer
- added setting to control mapping of user_login (username).
- added setting to control whether the user_login is updated for users already in DB.
- simplified the settings page, move CRON settings on same page
- fixed bug where the log 'Delete' button was not working
- removed obsolete settings related to changing the role during update
