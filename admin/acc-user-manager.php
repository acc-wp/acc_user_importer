<?php

add_filter("wp_authenticate_user", "acc_validate_user_login");

add_filter(
    "um_custom_authenticate_error_codes",
    "acc_um_custom_authenticate_error_codes"
);

function acc_um_custom_authenticate_error_codes($third_party_codes)
{
    $third_party_codes[] = "membership_validation_error";
    return $third_party_codes;
}

/**
 * On plugin activation, schedule CRON job.
 * First occurence will happen in 1 hour, then every week afterward.
 */
function acc_cron_activate()
{
    if (!wp_next_scheduled("acc_automatic_db_check")) {
        wp_schedule_event(time() + 3600, "weekly", "acc_automatic_db_check");
    } else {
        error_log(
            "Error activating plugin, acc_automatic_db_check was already scheduled"
        );
    }
}

/**
 * On plugin deactivation, unschedule CRON.
 */
function acc_cron_deactivate()
{
    $timestamp = wp_next_scheduled("acc_automatic_db_check");
    wp_unschedule_event($timestamp, "acc_automatic_db_check");
    wp_unschedule_hook("acc_automatic_db_check");
    wp_clear_scheduled_hook("acc_automatic_db_check");
}

/**
 * Returns an array of membership types
 */
function acc_get_mship_names()
{
    $mshipNames = [
        "Free",
        "Individual",
        "Family",
        "Youth",
        "Lifetime",
        "Honorary",
        "Student",
        "MEC Staff",
        "ACC Staff",
    ];

    return $mshipNames;
}

/**
 * Returns an array of section names
 */
function acc_get_supported_sections()
{
    $sectNames = [
        "Bugaboos",
        "Calgary",
        "Central Alberta",
        "Columbia Mountains",
        "Edmonton",
        "FQME",
        "Great Plains",
        "Jasper/Hinton",
        "Manitoba",
        "Montréal",
        "Newfoundland and Labrador",
        "Okanagan",
        "Outaouais",
        "Ottawa",
        "Prince George",
        "Rocky Mountain",
        "Saint Boniface",
        "Saskatchewan",
        "Sault Ste. Marie",
        "Southern Alberta",
        "Squamish",
        "Thunder Bay",
        "Toronto",
        "Vancouver",
        "Vancouver Island",
        "Whistler",
        "Yukon",
    ];

    return $sectNames;
}

/**
 * Returns true if the user is expired.
 * As per the API specification, members that have an auto-renewed membership
 * do not have an expiry date. So the acc_mship_expiry field is empty.
 */
function acc_is_user_expired($user)
{
    if (!($user instanceof WP_User)) {
        return true; //error handling
    }

    if (
        !empty($user->acc_mship_expiry) &&
        $user->acc_mship_expiry < date("Y-m-d")
    ) {
        return true;
    }

    //Check if user is member of one of the enabled sections
    //Code is disabled because not sure about old members.
    // if ($user->has_prop("acc_sections")) {
    //     $userSects = $user->acc_sections;
    //     $enabledSects = accUM_get_enabled_sections();
    //     $validSections = array_intersect($userSects, $enabledSects);
    //     if (empty($validSections)) {
    //         return true;
    //     }
    // }

    return false;
}

/**
 * Returns true if the user waiver is valid.
 * If the field does not exists or is null, it means the waiver was
 * not signed.
 */
function acc_is_waiver_valid($user)
{
    if (!$user->has_prop("acc_waiver_expiry") ||
        $user->acc_waiver_expiry < date("Y-m-d")) {
        return false;
    }
    return true;
}

/**
 * Login logic.
 * --- Normal case ---
 * - Allow user if acc_mship_expiry date is good and
 *   acc_waiver_expiry date is good (indicating waiver has been signed).
 * - A specific error message is given in the case where the waiver
 *   needs to be signed.
 * --- Special case ---
 * - If the user is a Wordpress admin, we allow login
 * - If the user does not have an acc_member_id, we assume it is a manually
 *   created account (for admin purpose) and we allow login.
 * - If the user has an empty acc_mship_expiry date, it is either a lifetime
 *   member or an auto-renewed membership, so we allow login, as long as
 *   the acc_waiver_expiry date is good. In terms of security, if an auto-renewed
 *   membership does not renew, Hubspot will send us a notification to
 *   terminate membership. And the acc_waiver_expiry field would anyway
 *   prevent login eventually if the waiver is not renewed.
 */
function acc_validate_user_login($user)
{
    if ($user instanceof WP_User) {
        //Never block an admin
        if (in_array("administrator", $user->roles)) {
            return $user;
        }

        //Allow manually created user entries
        if (
            !$user->has_prop("acc_mship_expiry") ||
            !$user->has_prop("acc_member_id")
        ) {
            return $user;
        }

        // Case where no valid membership
        if (acc_is_user_expired($user)) {
            $error = new WP_Error();
            $msg = accUM_get_oops_expired_text();
            $error->add("membership_validation_error", $msg);
            return $error;
        }

        // Case where waiver has not been signed. We output a specific error.
        if (!acc_is_waiver_valid($user)) {
            $error = new WP_Error();
            $msg = accUM_get_oops_waiver_text();
            $error->add("membership_validation_error", $msg);
            return $error;
        }
    }
    return $user;
}

function acc_send_welcome_email($section, $user_id)
{
    if (accUM_get_welcome_email_enable($section) == "on") {
        $title = accUM_get_welcome_email_title($section);
        $content = accUM_get_welcome_email_content($section);
        if (isset($title) && isset($content)) {
            $user = get_userdata($user_id);
            $user_email = $user->user_email;

            return wp_mail(
                $user_email,
                $title,
                $content,
                "Content-Type: text/html; charset=UTF-8"
            );
        }
    }
}

function acc_send_goodbye_email($section, $user_id)
{
    if (accUM_get_goodbye_email_enable($section) == "on") {
        $title = accUM_get_goodbye_email_title($section);
        $content = accUM_get_goodbye_email_content($section);
        if (isset($title) && isset($content)) {
            $user = get_userdata($user_id);
            $user_email = $user->user_email;

            return wp_mail(
                $user_email,
                $title,
                $content,
                "Content-Type: text/html; charset=UTF-8"
            );
        }
    }
}

static $acc_logfile = "";

/*
 * Generate a new log filename, based on the current day and time. Ex:
 * plugins/acc_user_importer/logs/log_upgrade_2024-02-13-16-35-04.txt
 * This is stored in a global variable for convenience so that
 * each log statement does not have to specifically refer to that file.
 * On top of that, we store it to the plugin DB because the static var
 * is periodically re-init to NULL by Wordpress (in-between http requests,
 * I think).
 */
function acc_pick_new_log_file($suffix)
{
    global $acc_logfile;
    $log_date = date_i18n("Y-m-d-H-i-s");
    $acc_logfile = ACC_LOG_DIR . "log_" . $log_date . $suffix . ".txt";
    //error_log("acc_logfile defined as $acc_logfile");
    acc_write_log_filename_to_db($acc_logfile);

    acc_enforce_max_log_files();

    return $acc_logfile;
}

// Write the current log filename as a plugin DB option.
function acc_write_log_filename_to_db($filename)
{
    $options = get_option(ACCUM_DATA);
    $options["log_filename"] = $filename;
    update_option(ACCUM_DATA, $options);
    //error_log("wrote filename to DB");
}

// Get the log filename stored in the DB.
function acc_read_log_filename_from_db()
{
    $options = get_option(ACCUM_DATA);
    $filename = $options["log_filename"];
    //error_log("read $filename from DB");
    return $filename;
}

/*
 * Delete old log files to ensure it does not grow to infinity
 */
function acc_enforce_max_log_files()
{
    $max_log_files = accUM_get_max_log_files();

    // Get list of files, sorted alphabetically so the latest date is on top
    $files = scandir(ACC_LOG_DIR, SCANDIR_SORT_DESCENDING);
    if (is_array($files)) {
        // Filter to keep only files starting with "log"
        $files2 = [];
        foreach ($files as $file) {
            if (str_starts_with($file, "log")) {
                $files2[] = $file;
            }
        }
        $count = count($files2);
        if ($count > $max_log_files - 1) {
            // How many to delete?  Minus one because we are about to add one more file
            $num_to_delete = $count - $max_log_files - 1;
            $files_to_delete = array_slice($files2, $max_log_files - 1);
            accLog(
                "Loc directory contains {$count} files and max set to " .
                    "{$max_log_files}, deleting " .
                    count($files_to_delete)
            );
            foreach ($files_to_delete as $file) {
                unlink(ACC_LOG_DIR . $file);
            }
        }
    }
}

//Candidate to replace log_dual. Takes less characters on a line, nicer.
function accLog($string)
{
    _acc_log($string);
    //$GLOBALS["acc_logstr"] .= $string . "<br/>";
    $GLOBALS["acc_logstr"] .= $string . "\n";
}

function _acc_log($v)
{
    global $acc_logfile;
    if (empty($acc_logfile)) {
        error_log("Need to create a new logfile");
        $acc_logfile = acc_read_log_filename_from_db();
        error_log("new logfile is $acc_logfile");
    }
    if (!empty($acc_logfile)) {
        $log = fopen($acc_logfile, "a");
        fwrite($log, $v . "\n");
        fclose($log);
    }
}

// Returns the current date plus the specified number of days.
// The format will be a string like "2026-04-28".
function acc_now_plus_N_days($days)
{
    $date = new DateTime(); // Get the current date
    $period = "P" . $days . "D";
    $date->add(new DateInterval($period)); //Add N days
    $return_string = $date->format("Y-m-d");
    return $return_string;
}
