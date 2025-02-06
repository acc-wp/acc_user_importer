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
 * Update will happen in 1 hour, then twice a day afterward.
 */
function acc_cron_activate()
{
    if (!wp_next_scheduled("acc_automatic_import")) {
        wp_schedule_event(time() + 3600, "twicedaily", "acc_automatic_import");
    } else {
        error_log(
            "Error activating plugin, acc_automatic_import was already scheduled"
        );
    }
}

/**
 * On plugin deactivation, unschedule CRON.
 */
function acc_cron_deactivate()
{
    $timestamp = wp_next_scheduled("acc_automatic_import");
    wp_unschedule_event($timestamp, "acc_automatic_import");
    wp_unschedule_hook("acc_automatic_import");
    wp_clear_scheduled_hook("acc_automatic_import");
}

/**
 * Returns true if the membership status is valid.
 * ISSU means ISSUED
 * PROC means Processing. The member has a paid membership, however he probably
 * has not signed the waiver yet, so should not be allowed to participate to activities.
 * In this function, we consider this a valid membership because we do not want
 * to send goodbye emails for such cases.
 */
function acc_validMembershipStatus($membershipStatus)
{
    return $membershipStatus == "ISSU" || $membershipStatus == "PROC";
}

/**
 * Returns true if the membership status is in PROCessing state.
 */
function acc_MembershipStatusIsProc($membershipStatus)
{
    return $membershipStatus == "PROC";
}

/**
 * Returns true if the membership status is in ISSUed state.
 */
function acc_MembershipStatusIsIssu($membershipStatus)
{
    return $membershipStatus == "ISSU";
}

/**
 * Returns the latest date among all user memberships.
 * If the user has no membership, return NULL.
 */
function acc_MembershipLatestDate($user)
{
    if (!($user instanceof WP_User)) {
        return null; //error handling
    }

    $latestDate = null;
    if (!empty($user->acc_memberships)) {
        foreach ($user->acc_memberships as $section => $sect_memberships) {
            foreach ($sect_memberships as $mId => $mship) {
                $expiry = $mship["expiry"];
                $status = $mship["status"];
                if (empty($latestDate) || $expiry > $latestDate) {
                    $latestDate = $expiry;
                }
            }
        }
    } elseif (!empty($user->expiry)) {
        //This part is for backward compatibility
        $latestDate = $user->expiry;
    }

    return $latestDate;
}

/**
 * Returns true if the specified user has a membership in PROC state.
 * According to Interpodia, all memberships will share the same status,
 * derived from the main national ACC base membership. So we could
 * probably just look at the first membership instead of looping.
 * If the user has no membership, return false.
 */
function acc_MembershipIsProc($user)
{
    if (!($user instanceof WP_User)) {
        return false; //error handling
    }

    if (!empty($user->acc_memberships)) {
        foreach ($user->acc_memberships as $section => $sect_memberships) {
            foreach ($sect_memberships as $mId => $mship) {
                $status = $mship["status"];
                if (acc_MembershipStatusIsProc($status)) {
                    return true;
                }
            }
        }
    } elseif (!empty($user->membership_status)) {
        //This part is for backward compatibility
        $status = $user->membership_status;
        if (acc_MembershipStatusIsProc($status)) {
            return true;
        }
    }

    return false;
}

/**
 * Returns true if the user is expired.
 * The user is consider valid if one membership_status is PROC or ISSU.
 * We first check the newest acc_memberships array.
 * For backward compatibility we also check the user membership_status.
 * For backward compatibility, if user has no membership_status, we
 * the check the 'expiry' date.  If there is no expiry, the user is
 * considered as valid.	 The user is most likely an admin, and his account was
 * created manually.
 */
function acc_is_user_expired($user)
{
    if (!($user instanceof WP_User)) {
        return true; //error handling
    }

    if (!empty($user->acc_memberships)) {
        $found_valid = false;
        foreach ($user->acc_memberships as $section => $sect_memberships) {
            foreach ($sect_memberships as $mId => $mship) {
                $status = $mship["status"];
                if (acc_validMembershipStatus($status)) {
                    return false;
                }
            }
        }

        //We scanned and found no valid memberships. User is invalid.
        return true;
    } elseif (!empty($user->membership_status)) {
        //This part is for backward compatibility
        $status = $user->membership_status;
        if (!acc_validMembershipStatus($status)) {
            return true;
        } else {
            return false;
        }
    } elseif (!empty($user->expiry)) {
        if ($user->expiry < date("Y-m-d")) {
            return true;
        } else {
            return false;
        }
    }

    //Must be a manually created entry (ex: admin account). Consider active.
    return false;
}

/**
 * User is trying to login.
 * Allow login there is at least 1 section membership in ISSU state.
 * NOTE: there is no check that the user membership is part of the
 * sections configured for import. If members of a section should
 * no longer be allowed to login, then a manual DB cleanup is needed.
 * Prevent user login if membership is PROC, EXP or expiry date is passed.
 * The logic allows login of manually created accounts that have no
 * acc_memberships and no membership_status fields, as long as they have
 * an expiry date in the future.
 */
function acc_validate_user_login($user)
{
    if ($user instanceof WP_User) {
        //Never block an admin
        if (in_array("administrator", $user->roles)) {
            return $user;
        }

        $proc_error = false;
        $expiry_error = false;

        // Plugin version 3.x.x introduces acc_memberships array
        $memberships = get_user_meta($user->ID, "acc_memberships", true);
        if (!empty($memberships)) {
            foreach ($memberships as $section => $sect_memberships) {
                foreach ($sect_memberships as $mId => $mship) {
                    $expiry = $mship["expiry"];
                    $status = $mship["status"];
                    //error_log("Login: found $section with id=$mId $expiry $status");

                    if (acc_MembershipStatusIsIssu($status)) {
                        //User has at least one valid membership, let him login
                        return $user;
                    }
                    if (acc_MembershipStatusIsProc($status)) {
                        $proc_error = true;
                    }
                }
            }

            //If we reach here and it's not a proc error, then it must be that
            //the membership is expired.
            if (!$proc_error) {
                $expiry_error = true;
            }
        } else {
            //This is for backward compatibility during upgrade, it will allow
            //users to login in the period during which the plugin has not
            //reimported users and created the new acc_memberships array yet.
            //Plugin version 2.2.x has a single membership_status field.
            $status = get_user_meta($user->ID, "membership_status", "true");
            if (!empty($status) && acc_MembershipStatusIsProc($status)) {
                $proc_error = true;
            } else {
                $expiry = get_user_meta($user->ID, "expiry", "true");
                if (
                    (!empty($status) && !acc_validMembershipStatus($status)) ||
                    empty($expiry) ||
                    $expiry < date("Y-m-d")
                ) {
                    $expiry_error = true;
                }
            }
        }

        // Case where membership is in PROC state. We output a specific error.
        if ($proc_error) {
            $error = new WP_Error();
            $msg =
                "Oops. Your membership is in Processing state, which means " .
                "a requirement is still missing. Maybe your membership renewed automatically but you did not sign the new waiver yet? " .
                'Please check your membership at <a href="https://2mev.com/#!/login">https://2mev.com/#!/login</a> ' .
                "and make the corrections needed. This will allow you to login and register to activities. " .
                "Note: it may take 24 hours for the update to be propagated to our local website. <br><br>" .
                'Il semble que votre abonnement ne soit pas complet. Peut-être que votre abonnement s\'est renouvelé ' .
                'automatiquement mais que vous n\'avez pas encore signé le nouveau ' .
                'formulaire d\'acceptation des risques (à signer chaque année)? Vérifiez l\'état de votre abonnement au ' .
                '<a href="https://2mev.com/#!/login">https://2mev.com/#!/login</a> afin de pouvoir vous connecter ' .
                "et participer aux activités. Allouez 24h pour que les changements se propagent au site web local.";
            $error->add("membership_validation_error", $msg);
            return $error;
        }

        // Case where membership is not ISSU, or expiry date is passed. In theory, just
        // checking for not ISSU should be enough. But I have seen weird cases where 2M forgot to
        // notify us of a user expiry, and checking the expiry date here acts as a safeguard.
        if ($expiry_error) {
            $error = new WP_Error();
            $msg =
                "Oops. Looks like your membership has expired. Please renew your membership at " .
                '<a href="https://www.alpineclubofcanada.ca">www.alpineclubofcanada.ca</a>. ' .
                "Allow 24 hours for the change to propagate to the local web site.<br><br>" .
                "Il semble que votre abonnement soit échu. Renouvelez votre abonnement au " .
                '<a href="https://www.alpineclubofcanada.ca">www.alpineclubofcanada.ca</a>. ' .
                "et allouez 24 heures pour que le changement se propage au site web local.";
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
function acc_pick_new_log_file($prefix)
{
    global $acc_logfile;
    $log_date = date_i18n("Y-m-d-H-i-s");
    $acc_logfile = ACC_LOG_DIR . $prefix . $log_date . ".txt";
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
            acc_log(
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
function accLog2($string)
{
    acc_log($string);
    $GLOBALS["acc_logstr"] .= $string . "<br/>";
}

function acc_log($v)
{
    global $acc_logfile;
    if (empty($acc_logfile)) {
        $acc_logfile = acc_read_log_filename_from_db();
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
