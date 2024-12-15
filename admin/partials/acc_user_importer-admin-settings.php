<?php

/**
 * Provide a admin area view for the plugin
 *
 *
 * @package    acc_user_importer
 * @subpackage acc_user_importer/admin/partials
 */

/**
 * Returns an associative array of section names and API ID.
 * Intentially omitted FQME, which maps to 10.
 */
function acc_get_section_apis()
{
    $acc_section_apis = [
        "SQUAMISH" => "1",
        "CALGARY" => "2",
        "MONTRÉAL" => "3",
        "OUTAOUAIS" => "4",
        "OTTAWA" => "5",
        "VANCOUVER" => "6",
        "ROCKY MOUNTAIN" => "7",
        "EDMONTON" => "8",
        "TORONTO" => "9",
        "YUKON" => "11",
        "BUGABOOS" => "12",
    ];
    return $acc_section_apis;
}

/**
 * Returns an array of section names
 */
function acc_get_supported_sections()
{
    return array_keys(acc_get_section_apis());
}

/**
 * Returns the API ID for the specified section
 */
function acc_get_section_api_id($section)
{
    $section_apis = acc_get_section_apis();
    return $section_apis[$section];
}

/*
 * List menu page in the Wordpress admin.
 */
add_action("admin_menu", "accUM_add_menu_page");
function accUM_add_menu_page()
{
    add_menu_page(
        "ACC Administration", //Title of the page in the browser tab
        "ACC User Importer", //Menu Title
        "edit_users", //Capability
        "acc_admin_page", //Slug
        "accUM_render_options_pages" //Callback
    );
}

/*
 * Render theme options pages.
 */
function accUM_render_options_pages()
{
    require plugin_dir_path(__FILE__) . "/acc_user_importer-admin-display.php";
    require_once ACC_BASE_DIR . "/template/cron_settings.php";
    require_once ACC_BASE_DIR . "/template/acc_logs.php";
}

/*-------------------------Get functions-------------------------------
 * Get functions.
 * Used during automatic import and also by the functions that render
 * settings.  Here is where the default values are specified for
 * when the setting has not been touched by the user.
 *-------------------------------------------------------------------*/

//Return the number of sections we will import
function accUM_get_num_sections()
{
    $options = get_option(ACCUM_DATA);
    if (!isset($options["enabled_sections"])) {
        return 0;
    }
    return count($options["enabled_sections"]);
}

//Return an array of sections selected for import
function accUM_get_enabled_sections()
{
    $options = get_option(ACCUM_DATA);
    if (isset($options) && isset($options["enabled_sections"])) {
        return array_keys($options["enabled_sections"]);
    }
    return null;
}

//Returns "on" if section is imported
function accUM_get_section_imported($section)
{
    $options = get_option(ACCUM_DATA);
    if (
        isset($options) &&
        isset($options["enabled_sections"]) &&
        isset($options["enabled_sections"][$section])
    ) {
        $value = $options["enabled_sections"][$section];
        //error_log("in " . __FUNCTION__ . " returning $value");
        return $value;
    }
    return "off";
}

function accUM_is_section_imported()
{
    return accUM_get_section_imported() === "on";
}

// Sync changes since when?
function accUM_get_since_date()
{
    $options = get_option(ACCUM_DATA);
    $key = "since_date";
    if (!isset($options[$key])) {
        return null;
    }
    $value = $options[$key];
    return $value;
}

function accUM_set_since_date($new_date)
{
    $options = get_option(ACCUM_DATA);
    $key = "since_date";
    $options[$key] = $new_date;
    $rc = update_option(ACCUM_DATA, $options);
    if ($rc != true) {
        error_log("Failed to update since_date to $new_date");
    }
}

// Returns the configured list of users to synchronize
function accUM_get_sync_list()
{
    $options = get_option(ACCUM_DATA);
    $key = "sync_list";
    if (!isset($options[$key])) {
        return null;
    }
    $value = $options[$key];
    return $value;
}

// Returns the configured login name mapping
function accUM_get_login_name_mapping()
{
    $options = get_option(ACCUM_DATA);
    $key = "login_name_mapping";
    if (!isset($options[$key])) {
        return "member_number";
    }
    $value = $options[$key];
    return $value;
}

//Returns "on" if transition from Contact ID is selected
function accUM_get_transition_from_contactID()
{
    $options = get_option(ACCUM_DATA);
    if (isset($options["accUM_transition_from_contactID"])) {
        $value = $options["accUM_transition_from_contactID"];
        //error_log("in " . __FUNCTION__ . " returning $value");
        return $value;
    }
    return "off";
}

// Returns true if the database is transitioning from FromContactID usernames.
function accUM_is_transitionFromContactID()
{
    return accUM_get_transition_from_contactID() === "on";
}

// Returns "on" if the plugin operates in read-only mode (for debug)
function accUM_get_readonly_mode()
{
    $options = get_option(ACCUM_DATA);
    $key = "accUM_readonly_mode";
    if (!isset($options[$key])) {
        return "off";
    }
    $value = $options[$key];
    return $value;
}

// Returns true/false
function accUM_is_section_readonly()
{
    return accUM_get_readonly_mode() === "on";
}

// Returns "on" if the plugin should scan the DB looking for expired members
function accUM_get_verify_expiry()
{
    $options = get_option(ACCUM_DATA);
    $key = "verify_expiry";
    if (!isset($options[$key])) {
        return "off";
    }
    $value = $options[$key];
    return $value;
}

// Returns true/false
function accUM_is_verify_expiry()
{
    return accUM_get_verify_expiry() === "on";
}

// Returns "on" if the plugin should delete obsolete users during
// the DB scan.
function accUM_get_delete_ex_users()
{
    $options = get_option(ACCUM_DATA);
    $key = "delete_ex_users";
    if (!isset($options[$key])) {
        return "off";
    }
    $value = $options[$key];
    return $value;
}

function accUM_is_delete_ex_users()
{
    return accUM_get_delete_ex_users() === "on";
}

// After how many days should an expired user be deleted?
function accUM_get_when_2_delete_ex_user()
{
    $options = get_option(ACCUM_DATA);
    $key = "when_2_delete_ex_user";
    if (!isset($options[$key])) {
        return 365;
    }
    $value = $options[$key];
    return $value;
}

// When deleting a user, who should become the new content owner?
function accUM_get_new_owner()
{
    $options = get_option(ACCUM_DATA);
    $key = "new_owner";
    if (!isset($options[$key])) {
        return "";
    }
    $value = $options[$key];
    return $value;
}

// Who (email addresses) to send notification emails to?
function accUM_get_notification_emails()
{
    $options = get_option(ACCUM_DATA);
    $key = "notification_emails";
    if (!isset($options[$key])) {
        return "";
    }
    $value = $options[$key];
    return $value;
}

// Title of the email?
function accUM_get_notification_title()
{
    $options = get_option(ACCUM_DATA);
    $key = "notification_title";
    if (!isset($options[$key])) {
        return "ACC membership change notification";
    }
    $value = $options[$key];
    return $value;
}

// How many log files should we keep max.
function accUM_get_max_log_files()
{
    $options = get_option(ACCUM_DATA);
    $key = "max_log_files";
    if (!isset($options[$key])) {
        return 500;
    }
    $value = $options[$key];
    return $value;
}

//-----------get functions with a section parameter----------------

function accUM_get_section_disable($section)
{
    if (!in_array($section, acc_get_supported_sections())) {
        //error_log("in " . __FUNCTION__ . " section $section is invalid");
        return true;
    }
    $options = get_option(ACCUM_SEC . $section);
    if (!isset($options["disable"])) {
        //error_log("in " . __FUNCTION__ . " false for $section");
        return "off";
    }
    return $options["disable"];
}

function accUM_is_section_disabled($section)
{
    return accUM_get_section_disable($section) === "on";
}

// Returns the section authentication token
function accUM_get_section_token($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "token";
    if (!isset($options[$key])) {
        return null;
    }
    $value = $options[$key];
    return $value;
}

// Returns what to do with the role of a new user.
function accUM_get_new_user_role_action($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "new_user_role_action";
    if (!isset($options[$key])) {
        return "set_role";
    }
    $value = $options[$key];
    return $value;
}

// Returns what role to use for a new user.
function accUM_get_new_user_role_value($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "new_user_role_value";
    if (!isset($options[$key])) {
        return "subscriber";
    }
    $value = $options[$key];
    return $value;
}

// Returns what to do with the role of a new user.
function accUM_get_ex_user_role_action($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "ex_user_role_action";
    if (!isset($options[$key])) {
        return "set_role";
    }
    $value = $options[$key];
    return $value;
}

// Returns what role to use for a new user.
function accUM_get_ex_user_role_value($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "ex_user_role_value";
    if (!isset($options[$key])) {
        return "subscriber";
    }
    $value = $options[$key];
    return $value;
}

function accUM_get_welcome_email_enable($section)
{
    if (!in_array($section, acc_get_supported_sections())) {
        //error_log("in " . __FUNCTION__ . " section $section is invalid");
        return true;
    }
    $options = get_option(ACCUM_SEC . $section);
    if (!isset($options["welcome_email_enable"])) {
        return "off";
    }
    return $options["welcome_email_enable"];
}

function accUM_get_welcome_email_title($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "welcome_email_title";
    if (!isset($options[$key])) {
        return null;
    }
    $value = stripslashes($options[$key]);
    return $value;
}

function accUM_get_welcome_email_content($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "welcome_email_content";
    if (!isset($options[$key])) {
        return null;
    }
    $value = stripslashes(html_entity_decode($options[$key]));
    return $value;
}

function accUM_get_goodbye_email_enable($section)
{
    if (!in_array($section, acc_get_supported_sections())) {
        //error_log("in " . __FUNCTION__ . " section $section is invalid");
        return true;
    }
    $options = get_option(ACCUM_SEC . $section);
    $key = "goodbye_email_enable";
    if (!isset($options[$key])) {
        return "off";
    }
    $value = $options[$key];
    return $value;
}

function accUM_get_goodbye_email_title($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "goodbye_email_title";
    if (!isset($options[$key])) {
        return null;
    }
    $value = stripslashes($options[$key]);
    return $value;
}

function accUM_get_goodbye_email_content($section)
{
    $options = get_option(ACCUM_SEC . $section);
    $key = "goodbye_email_content";
    if (!isset($options[$key])) {
        return null;
    }
    $value = stripslashes(html_entity_decode($options[$key]));
    return $value;
}

/*****************************************************************************
 * Register plugin settings
 ****************************************************************************/
if (!has_action("admin_init", "accUM_settings_init")) {
    add_action("admin_init", "accUM_settings_init");
}
function accUM_settings_init()
{
    //---------Define general settings---------------
    register_setting("acc_general_group", ACCUM_DATA, "accUM_sanitize_data");

    add_settings_section(
        "accUM_general_section",
        "General Settings",
        "",
        "accUM_general_section1"
    );

    add_settings_field(
        "accUM_enabled_sections", //ID
        "List of sections to import",
        "accUM_chkboxes_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_enabled_sections",
            "name" => ACCUM_DATA . "[enabled_sections]",
            "get" => "accUM_get_section_imported",
            "get_args" => [],
            "help" => "Select the sections to import membership from.",
            "items" => [
                "SQUAMISH" => "SQUAMISH",
                "CALGARY" => "CALGARY",
                "OTTAWA" => "OTTAWA",
                "MONTRÉAL" => "MONTRÉAL",
                "OUTAOUAIS" => "OUTAOUAIS",
                "VANCOUVER" => "VANCOUVER",
                "ROCKY MOUNTAIN" => "ROCKY MOUNTAIN",
                "EDMONTON" => "EDMONTON",
                "TORONTO" => "TORONTO",
                "YUKON" => "YUKON",
                "BUGABOOS" => "BUGABOOS",
            ],
        ]
    );

    add_settings_field(
        "accUM_since_date", //ID
        "Sync changes since when? This normally shows the last run time (in UTC), " .
            "but you can force a date in ISO 8601 format such as 2020-11-23T15:05:00.",
        "accUM_text_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_since_date",
            "get" => "accUM_get_since_date",
            "get_args" => [],
            "name" => ACCUM_DATA . "[since_date]",
            "type" => "text",
            "help" =>
                "The date gets updated when the plugin runs automatically, " .
                "but not when it runs manually with the Update button",
        ]
    );

    add_settings_field(
        "accUM_sync_list", //ID
        "Only sync this comma-separated list of ACC member numbers",
        "accUM_text_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_sync_list",
            "name" => ACCUM_DATA . "[sync_list]",
            "get" => "accUM_get_sync_list",
            "get_args" => [],
            "type" => "text",
            "help" =>
                "Normally blank. Enter member numbers to manually sync those members " .
                "using the Update button. Dont forget to clear the box afterward to " .
                "ensure normal automatic sync.",
        ]
    );

    add_settings_field(
        "accUM_login_name_mapping", //ID
        "Set usernames to (Use with caution, this affects login of users, " .
            "although they always can login using their email)",
        "accUM_select_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_login_name_mapping",
            "name" => ACCUM_DATA . "[login_name_mapping]",
            "get" => "accUM_get_login_name_mapping",
            "get_args" => [],
            "items" => [
                "member_number" => "ACC member number",
                "Firstname Lastname" => "Firstname Lastname",
            ],
        ]
    );

    add_settings_field(
        "accUM_transition_from_contactID", //ID
        "Usernames will transition from ContactID to Interpodia member_number? " .
            "Check this box for a safer transition (verifies that member being synced has the right name)",
        "accUM_chkboxes_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_transition_from_contactID",
            "name" => ACCUM_DATA . "[accUM_transition_from_contactID]",
            "get" => "accUM_get_transition_from_contactID",
            "get_args" => [],
            "help" => "This option should normally be left unchecked.",
        ]
    );

    add_settings_field(
        "accUM_readonly_mode", //ID
        "Test mode: do not update Wordpress user database",
        "accUM_chkboxes_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_readonly_mode",
            "name" => ACCUM_DATA . "[accUM_readonly_mode]",
            "get" => "accUM_get_readonly_mode",
            "get_args" => [],
            "help" =>
                "Check this box to do a normal run but skip the local DB update.",
        ]
    );

    add_settings_field(
        "accUM_verify_expiry", //ID
        "Also check user expiry in local DB",
        "accUM_chkboxes_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_verify_expiry",
            "name" => ACCUM_DATA . "[verify_expiry]",
            "get" => "accUM_get_verify_expiry",
            "get_args" => [],
            "help" =>
                "Recommend to check this box. Once import is done " .
                "the plugin will scan the user database, clean " .
                "obsolete users and potentially raise warnings " .
                "in the logfile",
        ]
    );

    add_settings_field(
        "accUM_delete_ex_users", //ID
        "Delete expired user accounts after a while",
        "accUM_chkboxes_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_delete_ex_users",
            "name" => ACCUM_DATA . "[delete_ex_users]",
            "get" => "accUM_get_delete_ex_users",
            "get_args" => [],
            "help" => "Requires 'Also check user expiry' option.",
        ]
    );

    add_settings_field(
        "accUM_when_2_delete_ex_user", //ID
        "How many days before deleting expired users from database?",
        "accUM_text_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "type" => "number",
            "id" => "accUM_when_2_delete_ex_user",
            "name" => ACCUM_DATA . "[when_2_delete_ex_user]",
            "get" => "accUM_get_when_2_delete_ex_user",
            "get_args" => [],
            "help" =>
                "Enter the number of days after which to delete the user account.",
        ]
    );

    add_settings_field(
        "accUM_new_owner", //ID
        "When deleting a user, who will become the new content owner?",
        "accUM_text_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "type" => "text",
            "id" => "accUM_new_owner",
            "name" => ACCUM_DATA . "[new_owner]",
            "get" => "accUM_get_new_owner",
            "get_args" => [],
            "help" =>
                "Enter the new owner login name. Suggestion: manually " .
                "create a dummy user (example: 'ex-member') to receive " .
                "ownership of content for users we need to delete, " .
                "and enter its login name here. The plugin will reassign " .
                "posts, pages, articles, events. Leaving this box " .
                "empty will delete the user content along with the user, " .
                "and you might end up with missing pages or broken links.",
        ]
    );

    add_settings_field(
        "accUM_notification_emails", //ID
        "Admin to notify about membership creation/expiry? List of emails, comma separated. Leave blank for no notifications",
        "accUM_text_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_notification_emails",
            "name" => ACCUM_DATA . "[notification_emails]",
            "get" => "accUM_get_notification_emails",
            "get_args" => [],
            "type" => "text",
        ]
    );

    add_settings_field(
        "accUM_notification_title", //ID
        "Title of admin notification email",
        "accUM_text_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_notification_title",
            "name" => ACCUM_DATA . "[notification_title]",
            "get" => "accUM_get_notification_title",
            "get_args" => [],
            "type" => "text",
        ]
    );

    add_settings_field(
        "accUM_max_log_files", //ID
        "Maximum number of log files to keep",
        "accUM_text_render", //Callback
        "accUM_general_section1", //Page
        "accUM_general_section", //Section
        [
            "id" => "accUM_max_log_files",
            "name" => ACCUM_DATA . "[max_log_files]",
            "get" => "accUM_get_max_log_files",
            "get_args" => [],
            "type" => "number",
        ]
    );

    //-------------------Define per-section settings--------------------------
    foreach (acc_get_supported_sections() as $section) {
        register_setting(
            "acc_" . $section . "_group",
            ACCUM_SEC . $section, //Used for writing to DB
            //array( 'sanitize_callback' => $section->sanitize_callback )
            "accUM_sanitize_data2"
        );

        add_settings_section(
            ACCUM_SEC . "_$section" . "_section",
            "Per-section settings",
            "",
            "acc_" . $section . "_section"
        );

        add_settings_field(
            "accUM_$section" . "_disable", //ID
            "Temporarily disable import for this section",
            "accUM_chkboxes_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_disable",
                "name" => ACCUM_SEC . $section . "[disable]", //Used for writing to DB
                "get" => "accUM_get_section_disable",
                "get_args" => [$section],
            ]
        );

        add_settings_field(
            "accUM_$section" . "_token", //ID
            "Section authentication token",
            "accUM_text_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_token",
                "name" => ACCUM_SEC . $section . "[token]", //for writing DB
                "get" => "accUM_get_section_token",
                "get_args" => [$section],
                "type" => "password",
            ]
        );

        add_settings_field(
            "accUM_$section" . "_new_user_role_action", //ID
            "When creating a new user, what should I do with role?",
            "accUM_select_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_new_user_role_action",
                "name" => ACCUM_SEC . $section . "[new_user_role_action]", //for writing DB
                "get" => "accUM_get_new_user_role_action",
                "get_args" => [$section],
                "items" => [
                    "set_role" => "Set role",
                    "add_role" => "Add role",
                    "nc" => "Do not change role",
                ],
            ]
        );

        $roles = wp_roles()->get_names();
        add_settings_field(
            "accUM_$section" . "_new_user_role_value", //ID
            "role value?", //Title
            "accUM_select_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_new_user_role_value",
                "name" => ACCUM_SEC . $section . "[new_user_role_value]", //for writing DB
                "get" => "accUM_get_new_user_role_value",
                "get_args" => [$section],
                "items" => $roles,
            ]
        );

        add_settings_field(
            "accUM_$section" . "_ex_user_role_action", //ID
            "When expiring a user, what should I do with role?",
            "accUM_select_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_ex_user_role_action",
                "name" => ACCUM_SEC . $section . "[ex_user_role_action]", //for writing DB
                "get" => "accUM_get_ex_user_role_action",
                "get_args" => [$section],
                "items" => [
                    "set_role" => "Set role",
                    "remove_role" => "Remove role",
                    "nc" => "Do not change role",
                ],
            ]
        );

        $roles = wp_roles()->get_names();
        add_settings_field(
            "accUM_$section" . "_ex_user_role_value", //ID
            "role value?", //Title
            "accUM_select_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_ex_user_role_value",
                "name" => ACCUM_SEC . $section . "[ex_user_role_value]", //for writing DB
                "get" => "accUM_get_ex_user_role_value",
                "get_args" => [$section],
                "items" => $roles,
            ]
        );

        add_settings_field(
            "accUM_$section" . "_welcome_email_enable", //ID
            "Send Welcome email?",
            "accUM_chkboxes_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_welcome_email_enable",
                "name" => ACCUM_SEC . $section . "[welcome_email_enable]", //Used for writing to DB
                "get" => "accUM_get_welcome_email_enable",
                "get_args" => [$section],
                "help" => "Check to send a welcome email to the user",
            ]
        );

        add_settings_field(
            "accUM_$section" . "_welcome_email_title", //ID
            "Welcome email title",
            "accUM_text_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_welcome_email_title",
                "name" => ACCUM_SEC . $section . "[welcome_email_title]", //for writing DB
                "get" => "accUM_get_welcome_email_title",
                "get_args" => [$section],
                "type" => "text",
                "size" => "70",
                "help" => "The subject of the email",
            ]
        );

        add_settings_field(
            "accUM_$section" . "_welcome_email_content", //ID
            "Welcome email content",
            "accUM_wpeditor_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_welcome_email_content",
                "name" => ACCUM_SEC . $section . "[welcome_email_content]", //for writing DB
                "get" => "accUM_get_welcome_email_content",
                "get_args" => [$section],
            ]
        );

        add_settings_field(
            "accUM_$section" . "_goodbye_email_enable", //ID
            "Send Goodbye email?",
            "accUM_chkboxes_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_goodbye_email_enable",
                "name" => ACCUM_SEC . $section . "[goodbye_email_enable]", //Used for writing to DB
                "get" => "accUM_get_goodbye_email_enable",
                "get_args" => [$section],
                "help" => "Check to send a goodbye email to the user",
            ]
        );

        add_settings_field(
            "accUM_$section" . "_goodbye_email_title", //ID
            "Goodbye email title",
            "accUM_text_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_goodbye_email_title",
                "name" => ACCUM_SEC . $section . "[goodbye_email_title]", //for writing DB
                "get" => "accUM_get_goodbye_email_title",
                "get_args" => [$section],
                "type" => "text",
                "size" => "70",
                "help" => "The subject of the email",
            ]
        );

        add_settings_field(
            "accUM_$section" . "_goodbye_email_content", //ID
            "Goodbye email content",
            "accUM_wpeditor_render", //Callback
            "acc_" . $section . "_section", //Page
            ACCUM_SEC . "_$section" . "_section",
            [
                "id" => "accUM_$section" . "_goodbye_email_content",
                "name" => ACCUM_SEC . $section . "[goodbye_email_content]", //for writing DB
                "get" => "accUM_get_goodbye_email_content",
                "get_args" => [$section],
            ]
        );
    }
}

/*
 * Render the textbox fields.
 */
function accUM_text_render($args)
{
    $id = $args["id"];
    $get = $args["get"];
    $get_args = $args["get_args"];
    $name = $args["name"];
    $type = $args["type"];
    //error_log("in " . __FUNCTION__ . " $id $get $name");
    //error_log(print_r($get_args, true));
    //For per-section settings, the get function has a section parameter.
    $value = $get(...$get_args);

    $html = "<input type=\"$type\"";
    $html .= " id=\"$id\"";
    $html .= " name=$name";
    $html .= " value=\"$value\"";

    if (!empty($args["size"])) {
        $size = $args["size"];
        $html .= " size=\"$size\"";
    }

    //add extra html tags if any are given
    if (!empty($args["html_tags"])) {
        $html .= " " . $args["html_tags"];
    }

    //if there is help text to display when hovering
    if (!empty($args["help"])) {
        $help = $args["help"];
        $html .= " title=\"$help\"";
    }

    $html .= "/>";

    echo $html;
    //error_log("in " . __FUNCTION__ . " html=$html");
}

/*
 * Render the textarea (multiline fields).
 */
function accUM_textarea_render($args)
{
    $id = $args["id"];
    $get = $args["get"];
    $get_args = $args["get_args"];
    $name = $args["name"];
    $rows = $args["rows"];
    $cols = $args["cols"];
    //For per-section settings, the get function has a section parameter.
    $value = $get(...$get_args);

    $html = "<textarea";
    $html .= " id=\"$id\"";
    $html .= " name=$name";
    if (isset($rows)) {
        $html .= " rows=$rows";
    }
    if (isset($cols)) {
        $html .= " cols=$cols";
    }
    $html .= " >$value<";
    $html .= "/textarea>";

    echo $html;
}

/*
 * Render a text editor box.
 */
function accUM_wpeditor_render($args)
{
    $id = $args["id"];
    $get = $args["get"];
    $get_args = $args["get_args"];
    $name = $args["name"];
    $rows = $args["rows"];
    $cols = $args["cols"];
    //For per-section settings, the get function has a section parameter.
    $content = $get(...$get_args);

    $settings = [
        "textarea_name" => $name,
        "media_buttons" => true,
        "textarea_rows" => 10,
        "tinymce" => true,
    ];
    wp_editor($content, $id, $settings);
}

function accUM_select_render($args)
{
    $id = $args["id"];
    $name = $args["name"];
    $get = $args["get"];
    $get_args = $args["get_args"];
    $value = $get(...$get_args);

    $html = "<select id=\"$id\" name=\"$name\" >";

    //if there is help text to display when hovering
    if (!empty($args["help"])) {
        $help = $args["help"];
        $html .= " title=\"$help\"";
    }

    //Fill columns
    if ($args["items"]) {
        foreach ($args["items"] as $key => $text) {
            $html .= "<option value=\"$key\"";
            if ($key == $value) {
                $html .= ' selected="selected"';
            }
            $html .= ">$text";
            $html .= "</option>";
        }
    }
    echo $html . "</select>";
}

/*
 * Render for one or more checkboxes.
 * For one checkbox, the "item" parameter should be NULL and then
 * there will be no text printed on the right side and
 * the data will be stored in the DB as a single variable
 * rather than an associative array.
 * If checked, the WP database stores 'on'.
 * If not checked, the WP database has no data for that option.
 */
function accUM_chkboxes_render($args)
{
    $id = $args["id"];
    $name = $args["name"];
    $get = $args["get"];
    $get_args = $args["get_args"];
    $help = $args["help"];

    if (!isset($args["items"])) {
        // This is a single yes/no checkbox
        $value = $get(...$get_args);
        $html = "<input type=\"checkbox\"";
        $html .= " id=\"$id\"";
        $html .= " name=\"$name\"";
        if (isset($help)) {
            $html .= " title=\"$help\"";
        }
        $html .= checked("on", $value, false) . " /> <br />";
        echo $html;
        //error_log("chkboxes html=$html");
    } else {
        foreach ($args["items"] as $item => $text) {
            $args2 = $get_args + [$item]; //Append one more argument
            //error_log(print_r($args2, true));

            $value = $get(...$args2);

            $html = "<input type=\"checkbox\"";
            $html .= " id=\"$item\"";
            $html .= " name=\"$name" . "[$item]\"";
            if (isset($help)) {
                $html .= " title=\"$help\"";
            }
            $html .= checked("on", $value, false) . " /> $text <br />";
            echo $html;
            //error_log("chkboxes html=$html");
        }
    }
}

/*
 * FIXME
 * WIP: Sanitize data after user hits "Save changes".
 * We need a different sanitize function for general and section settings.
 */
function accUM_sanitize_data($options)
{
    //error_log("In sanitize");
    //error_log( print_r( $options, true ) );

    // if (is_array($options)) {
    //     foreach ($options as $key => $val) {
    //         if ($key == "enabled_sections") {
    //             //This is an array of checkbox options
    //             foreach ($val as $key2 => $val2) {
    //                 $options[$key][$key2] = sanitize_text_field($val2);
    //                 $new_value = $options[$key][$key2];
    //             }
    //         } else {
    //             $options[$key] = sanitize_text_field($val);
    //         }
    //     }
    // } else {
    //     $options = sanitize_text_field($options);
    // }
    return $options;
}

function accUM_sanitize_data2($options)
{
    //error_log("In sanitize2");
    //error_log(print_r($options, true));
    return $options;
}

?>
