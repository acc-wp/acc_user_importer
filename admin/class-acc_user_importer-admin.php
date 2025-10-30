<?php

/*
 * When requesting member data, we are limited by the size of the response.
 * Benoit says that Microsoft Edge has a limit of 64000 bytes.
 * 100 works
 * 150 fails with HTTP error 502 Bad Gateway.
 */
define("MEMBER_API_MAX_USERS", "50");

/*
 * Slow down HTTP request rate by sleeping deliberately after each one.
 * The 2M server throttles API at 20 requests per minute max.
 * We get HTTP error 429 if send faster than that.
 * Parameter is in seconds.
 */
define("SLEEP_TIME_AFTER_HTTP", 4);

/*
 * If we receive an unknown membership ID, assume this type.
 * Not too critical, the type is only used to prioritize
 * in certain corner cases which membership to store in DB.
 */
define("ACC_UNKNOWN_MSHIP", "unknown");

$acc_logstr = ""; //handy global to store log string

class acc_user_importer_Admin
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        //load display
        require plugin_dir_path(__FILE__) .
            "/partials/acc_user_importer-admin-settings.php";
    }

    public function responseErrMsg($api_response)
    {
        return "Error: " .
            ($api_response["errorMessage"]
                ? $api_response["errorMessage"]
                : "Unknown.");
    }

    private function returnApiError($errorString)
    {
        $api_response["message"] = "error";
        $api_response["log"] = $GLOBALS["acc_logstr"];
        $api_response["errorMessage"] = $errorString;
        return $api_response;
    }

    /**
     * Controller for the WP-API requests.
     */
    public function accUserAPI()
    {
        $GLOBALS["acc_logstr"] = ""; //Clear the API response log string

        //create response object
        $api_response = [];

        //kill script if current user lacks permission to edit other users
        if (current_user_can("edit_users") == false) {
            $api_response["message"] = "user permission error";
            echo json_encode($api_response);
            wp_die();
        }

        //kill script if nonce doesn't match up
        if (check_ajax_referer("accUserAPI", "security", false) == false) {
            $api_response["message"] = "security error";
            echo json_encode($api_response);
            wp_die();
        }

        //iterate through requests
        switch ($_POST["request"]) {
            case "import":
                $this->local_db_check("Manual");
        }

        //Return the log of the operation
        $api_response["log"] = $GLOBALS["acc_logstr"];

        //respond to ajax request and terminate
        echo json_encode($api_response);
        wp_die();
    }

    /**
     * Register the ACC REST API notifying us of membership changes
     */
    public function register_acc_rest_api()
    {
        register_rest_route("api/v1", "/members", [
            "methods" => "POST",
            "callback" => [$this, "handle_acc_membership_notification"],
            "permission_callback" => "__return_true", // Allow public access for now
        ]);
    }

    /**
     * Handle membership notification from national ACC IT platform.
     */
    public function handle_acc_membership_notification($request)
    {
        $params = $request->get_json_params();

        // Log the received data
        //error_log('Webhook received: ' . print_r($params, true));

        $GLOBALS["acc_logstr"] = ""; //Clear the API response log string
        $rc = $this->proccess_user_data($params);
        if (!$rc) {
            // An error happened, warn webmaster
            $this->send_error_email();
        }
        error_log($GLOBALS["acc_logstr"]); //FIXME

        return new WP_REST_Response(
            [
                "status" => "success",
                "message" => "Member data received",
            ],
            200
        );
    }

    /**
     * Returns the list of sections the user has joined
     */
    private function getSectionsAdded($rxdMships, $user)
    {
        $sectionsAdded = [];
        foreach ($rxdMships as $section) {
            if (!in_array($section, $user->acc_sections)) {
                $sectionsAdded[] = $section;
            }
        }
        if (!empty($sectionsAdded)) {
            accLog(" > user joined " . implode(",", $sectionsAdded));
        }
        return $sectionsAdded;
    }

    /**
     * Returns the list of sections the user has left
     */
    private function getSectionsDeleted($rxdMships, $user)
    {
        $sectionsDeleted = [];
        foreach ($user->acc_sections as $section) {
            if (!in_array($section, $rxdMships)) {
                $sectionsDeleted[] = $section;
            }
        }
        if (!empty($sectionsDeleted)) {
            accLog(" > user left " . implode(",", $sectionsDeleted));
        }
        return $sectionsDeleted;
    }

    /**
     * Update Wordpress database with member information.
     * This is where most of the work gets done.
     * Here is an example of the JSON format received from ACC national.
     *     {
     *         "action": (add, remove, update)
     *         "acc_notif_timestamp": "2025-08-29T11:15:04Z"
     *         "acc_member_id": "55148",
     *         "first_name": "François",
     *         "last_name": "Bessette",
     *         "user_email": "francois.bessette@gmail.com",
     *         "cell_phone": "555-555-3689",
     *         "acc_sections": ["Outaouais", "Ottawa"],
     *         "acc_mship_type": "Individual",
     *         "acc_mship_expiry": "2025-10-30",
     *         "acc_waiver_signed": "true",
     *         "acc_contact_fname": "Sonia",
     *         "acc_contact_lname": "Pouliot",
     *         "acc_contact_phone": "555-555-1234",
     *     }
     */
    private function proccess_user_data($params)
    {
        //Let's pick a new log file
        $userFirstName = $params["first_name"] ?? "";
        $userLastName = $params["last_name"] ?? "";
        $userNameInLog = "_" . $userFirstName . "_" . $userLastName;
        preg_replace("/[^A-Za-z0-9.\-_]/", "_", $userNameInLog);
        $logfilename = basename(acc_pick_new_log_file($userNameInLog));
        accLog("Received the following membership notification: ");
        accLog(var_export($params, true));

        $sectionsAdded = [];
        $sectionsDeleted = [];
        $warnings = [];
        $errors = [];

        //Sanity checks for mandatory fields
        $needed = [
            "action",
            "acc_notif_timestamp",
            "acc_member_id",
            "user_email",
        ];
        foreach ($needed as $index => $field) {
            if (!isset($params[$field])) {
                accLog("Error, missing $field in notification");
                return false;
            }
        }

        if ($params["action"] == "add" || $params["action"] == "update") {
            //Sanity checks for additional mandatory fields
            $needed = [
                "first_name",
                "last_name",
                "acc_sections",
                "acc_mship_type",
                "acc_mship_expiry",
                "acc_waiver_signed",
            ];
            foreach ($needed as $index => $field) {
                if (!isset($params[$field])) {
                    accLog("Error, missing $field in notification");
                    return false;
                }
            }
        }
        if (!is_string($params["acc_waiver_signed"])) {
            accLog("Error, field 'acc_waiver_signed' is not a string");
            return false;
        }

        // Make sure email is valid.
        if (!is_email($params["user_email"])) {
            accLog("Error: rxd invalid email " . $params["user_email"]);
            return false;
        }

        $action = $params["action"];
        $acc_notif_timestamp = $params["acc_notif_timestamp"];
        $userMemberNumber = $params["acc_member_id"];
        $userFullName = $userFirstName . " " . $userLastName;
        $userEmail = strtolower($params["user_email"] ?? "");
        $userCellPhone = $params["cell_phone"] ?? "";
        $receivedSections = $params["acc_sections"] ?? [];
        $mshipType = $params["acc_mship_type"];
        $mshipExpiry = $params["acc_mship_expiry"];
        $waiverSigned = $params["acc_waiver_signed"];
        $contactFname = $params["acc_contact_fname"] ?? "";
        $contactLname = $params["acc_contact_lname"] ?? "";
        $contactPhone = $params["acc_contact_phone"] ?? "";

        $sectionsOfInterest = accUM_get_enabled_sections();

        //Validate received membership type
        $validMships = acc_get_mship_names();
        if (!in_array($mshipType, $validMships)) {
            accLog(" > Error, $mshipType is an invalid membership name");
            return false;
        }

        // Sanity check: keep only sections we are interested in. Log
        // warning if ACC notifies us about sections we dont care about.
        $validSections = acc_get_supported_sections();
        $rxdMships = [];
        foreach ($receivedSections as $section) {
            if (!in_array($section, $validSections)) {
                accLog(" > Error, $section is an invalid section");
                return false;
            }

            if (in_array($section, $sectionsOfInterest)) {
                // Rxd membership is in our interest list, keep it.
                $rxdMships[] = $section;
                //accLog("Rxd membership for section $section is part of our interest list");
            } else {
                accLog(
                    "Warning: ACC notified us about $section not in our interest list "
                );
            }
        }

        //Log the info we received for this user
        // $userInfoString = "Received [" . $acc_notif_timestamp . "] ";
        // $userInfoString .= "$action $userMemberNumber ";
        // $userInfoString .= $userFirstName . " " . $userLastName;
        // $userInfoString .= " " . $userEmail;
        // $userInfoString .= " with waiver " . ($waiverSigned ? "signed" : "NOT signed");
        // accLog($userInfoString);

        // Does received user have a membership?
        if ($action == "remove" || empty($rxdMships)) {
            $rxdMships = [];
            $userIsValid = false;
        } else {
            $userIsValid = true;
        }

        $loginNameMapping = accUM_get_login_name_mapping();
        switch ($loginNameMapping) {
            case "Firstname Lastname":
                $loginName = "$userFirstName $userLastName";
                break;
            case "member_number":
            default:
                $loginName = sanitize_user($userMemberNumber);
                break;
        }
        accLog("User login name is $loginName");

        // Create an array for the core wordpress user information.
        // accUserData lists all fields that will be checked for existing users.
        // Note: once a user is created, its nicename should not be changed otherwise the
        // author and post Permalinks would be affected. This is why user_nicename
        // is not part of the next array. Similarly the user_login is not part
        // of the arrray because for existing users, wordpress does not allow
        // to change it, and also because if we try to change it, there is a bug
        // where WP will post-fix the existing user_nicename with "-2".
        $accUserData = [
            "first_name" => $userFirstName,
            "last_name" => $userLastName,
            "display_name" => $userFirstName . " " . $userLastName,
            "user_email" => $userEmail,
        ];

        $accUserMetaData = [
            "acc_notif_timestamp" => $acc_notif_timestamp,
            "acc_mship_type" => $mshipType,
            "acc_waiver_signed" => $waiverSigned,
            "acc_mship_expiry" => $mshipExpiry,
            "acc_contact_fname" => $contactFname,
            "acc_contact_lname" => $contactLname,
            "acc_contact_phone" => $contactPhone,
            "cell_phone" => $userCellPhone,
            "acc_member_id" => $userMemberNumber,
            "nickname" => $userFirstName . " " . $userLastName,
            "acc_sections" => $rxdMships,
        ];

        do {
            // ---Find user by login---
            $user = get_user_by("login", $loginName);

            // ---If not found by login, find by email---
            if (is_a($user, WP_User::class)) {
                accLog(" > found $user->display_name " . "(user id $user->ID)");
            } else {
                accLog(" > not found by login");
                $user = get_user_by("email", $userEmail);
                if (is_a($user, WP_User::class)) {
                    if ($user->display_name == $userFullName) {
                        accLog(
                            " > found by email existing userId $user->ID " .
                                "named $user->display_name"
                        );
                    } else {
                        // Collision: the user we found has a different name
                        accLog(
                            " > Collision! Found by email existing userId " .
                                $user->ID .
                                " named $user->display_name"
                        );
                    }
                }
            }

            // Existing user, check if any fields were updated
            if (is_a($user, WP_User::class)) {
                //---------USER WAS FOUND IN DATABASE------------
                $userID = $user->ID;

                // Reject if rxd notification seems to be backward in time
                if (
                    isset($user->acc_notif_timestamp) &&
                    $user->acc_notif_timestamp > $acc_notif_timestamp
                ) {
                    $err =
                        "Error, notification seems outdated. " .
                        "Last rxd one was $user->acc_notif_timestamp";
                    accLog(" > $err");
                    return false;
                }

                $sectionsAdded = $this->getSectionsAdded($rxdMships, $user);
                $sectionsDeleted = $this->getSectionsDeleted($rxdMships, $user);

                // Check which fields might have changed. On purpose we dont want to check nicename.
                $updatedFields = [];
                foreach (
                    array_merge($accUserData, $accUserMetaData)
                    as $field => $value
                ) {
                    if ($value !== $user->$field) {
                        if (is_array($value)) {
                            //To conveniently print the change in an array,
                            //we serialize and print the string.
                            // $old = serialize($user->$field);
                            // $new = serialize($value);
                            $old = implode(",", $user->$field);
                            $new = implode(",", $value);
                            accLog(" > $field changed from " . "$old to $new");
                        } else {
                            accLog(
                                " > $field changed from " .
                                    $user->$field .
                                    " to " .
                                    $value
                            );
                        }
                        $user->$field = $value;
                        $updatedFields[] = $field;
                    }
                }

                // If fields changed, then update the user in the database.
                if (!empty($updatedFields)) {
                    // Passing in the $user object with the updated values will persist to the database.
                    $updateResp = wp_update_user($user);
                    if (is_wp_error($updateResp)) {
                        accLog(" > error, failed to update user");
                        accLog(" > WP:" . $updateResp->get_error_message());
                        return false;
                    }
                    accLog(" > updated userid " . $updateResp);

                    //Update meta fields
                    foreach ($accUserMetaData as $field => $value) {
                        if (in_array($field, $updatedFields)) {
                            update_user_meta($userID, $field, $value);
                        }
                    }
                }

                //Special code is needed to handle a login_name change. wp_update_user()
                //does not change the login_name or the user_nicename.  This is not
                //something we want to happen often. But it will happen in the case
                //where a child record is received before the parent, with the same
                //email address. Another solution to that would be a pre-processing
                //step where we re-order the array of incoming registrations,
                //so that the parent records are received first.
                if ($loginName != $user->user_login) {
                    global $wpdb;
                    $result = $wpdb->update(
                        $wpdb->users,
                        ["user_login" => $loginName],
                        ["ID" => $userID]
                    );
                    if ($result === false) {
                        $result_str = " failed";
                        accLog("Error changing loginName for $userFullName");
                    } else {
                        $result_str = " success";
                    }
                    accLog(
                        "> user {$userID} username changed from " .
                            "{$user->user_login} to {$loginName}, update database $result_str"
                    );
                    //Erase user cache so that future access gets the right data.
                    clean_user_cache($userID);
                }

                // For each section added to the user membership, send welcome email, etc.
                foreach ($sectionsAdded as $section) {
                    // Trigger hook if expiry date changed (updated membership)
                    do_action("acc_membership_renewal", $userID); //FIXME this is outdated
                    $this->takeActionOnNewUser($section, $userID);
                }

                // For each section deleted from the user membership, send goodbye email, etc.
                foreach ($sectionsDeleted as $section) {
                    $this->takeActionOnExpiredUser($section, $userID);
                }

                // Done updating the existing user
                continue;
            }

            //--------USER NOT FOUND IN DATABASE-----

            if (!$userIsValid) {
                // Skip the rest if the user is expired already
                accLog("> Expired membership, dont create account");
                continue;
            }

            //--------CREATE NEW USER-----
            accLog(" > email not found on any other users");
            $accUserData["user_pass"] = wp_generate_password(20);
            $accUserData["user_nicename"] = $accUserData["display_name"]; //WP will sanitize
            $accUserData["user_login"] = $loginName;

            // Insert new user
            $userID = wp_insert_user($accUserData);
            if (is_wp_error($userID)) {
                accLog(" > error, failed to create user");
                accLog(" > WP:" . $userID->get_error_message());
                return false;
            }

            accLog(" > Created new user " . $userID);
            $new_active_users[] = $accUserData["display_name"];

            //Insert meta fields.
            $accUserMetaData["acc_sections"] = $rxdMships;
            foreach ($accUserMetaData as $field => $value) {
                update_user_meta($userID, $field, $value);
            }

            // Execute hooks for new membership
            do_action("acc_new_membership", $userID);

            $sectionsAdded = $rxdMships;
            foreach ($sectionsAdded as $section) {
                $this->takeActionOnNewUser($section, $userID);
            }
        } while (false); //end dummy user processing loop

        $operation =
            "The ACC website received the following changes " .
            "for $userFullName <$userEmail>:";
        $this->send_admin_email(
            $operation,
            $sectionsAdded,
            $sectionsDeleted,
            [], //No deleted accounts
            $warnings,
            $errors
        );

        return true;
    }

    /**
     * A user became valid, take the necessary actions (ex: change role, send email)
     */
    private function takeActionOnNewUser($section, $user_id)
    {
        $new_user_role_action = accUM_get_new_user_role_action($section);
        $new_user_role_value = accUM_get_new_user_role_value($section);

        $user = get_userdata($user_id);
        if (!is_a($user, WP_User::class)) {
            accLog(
                "Error when checking for user state, userid $user_id is invalid"
            );
            return;
        }

        $message =
            " > user $user->ID $user->display_name joined " .
            "$section, send welcome email if enabled";
        accLog($message);
        acc_send_welcome_email($section, $user->ID);
        do_action("acc_member_welcome", $user->ID); //action hook

        // If needed, change the user role to the new member role.
        // Protection: never overwrite the 'admin' user role a user might have.
        $user_roles = $user->roles;
        if (
            $new_user_role_action == "set_role" &&
            in_array("administrator", $user_roles, true)
        ) {
            $new_user_role_action = "add_role"; //Change set_role to add_role
        }
        if ($new_user_role_action == "set_role") {
            if (!in_array($new_user_role_value, $user_roles, true)) {
                accLog("> Changing user role to $new_user_role_value");
                $user->set_role($new_user_role_value);
            } else {
                accLog(" > User already has role $new_user_role_value");
            }
        } elseif (
            $new_user_role_action == "add_role" &&
            !in_array($new_user_role_value, $user_roles, true)
        ) {
            accLog(" > Adding user role $new_user_role_value");
            $user->add_role($new_user_role_value);
        }
        accLog(" > user now has roles=" . implode(",", $user->roles));
    }

    /**
     * A user became invalid, take the necessary actions (ex: change role, send email)
     */
    private function takeActionOnExpiredUser($section, $user_id)
    {
        $ex_user_role_action = accUM_get_ex_user_role_action($section);
        $ex_user_role_value = accUM_get_ex_user_role_value($section);

        $user = get_userdata($user_id);
        if (!is_a($user, WP_User::class)) {
            accLog("Error checking for user state,$user_id is invalid");
            return;
        }

        $message =
            " > user $user->ID $user->display_name left " .
            "$section, send goodbye email if enabled";
        accLog($message);
        acc_send_goodbye_email($section, $user->ID);
        do_action("acc_member_goodbye", $user->ID); //action hook

        // If needed, change the user role to the expired role.
        // Do not change roles of administrators to prevent lockout.
        $user_roles = $user->roles;
        if (
            $ex_user_role_action == "set_role" &&
            !in_array("administrator", $user_roles, true) &&
            (count($user_roles) != 1 ||
                !in_array($ex_user_role_value, $user_roles, true))
        ) {
            accLog(" > Changing user role to $ex_user_role_value");
            $user->set_role($ex_user_role_value);
        } elseif (
            $ex_user_role_action == "remove_role" &&
            !in_array("administrator", $user_roles, true) &&
            in_array($ex_user_role_value, $user_roles, true)
        ) {
            accLog(" > Removing role $ex_user_role_value from user");
            $user->remove_role($ex_user_role_value);
        }
    }

    /**
     * Returns true if the user membership expired a while back and it is time
     * to delete it from the database.  This comparison is just based on the
     * user expiry date. We dont take into account the membership status.
     * Returns false if user does not have an expiry.
     */
    private function acc_is_time_to_delete_user($user, $days_before_delete)
    {
        if ($user instanceof WP_User) {
            //Never delete an admin
            if (in_array("administrator", $user->roles)) {
                return false;
            }

            if (!empty($user->acc_mship_expiry)) {
                $expiry = new DateTime($user->acc_mship_expiry);
                $expiry_ts = $expiry->getTimestamp();

                $now = new DateTime("now");
                $now_ts = $now->getTimestamp();

                $days_since_expiry = intval(
                    ($now_ts - $expiry_ts) / (60 * 60 * 24)
                );
                if ($days_since_expiry >= $days_before_delete) {
                    accLog(
                        "Need to delete $user->display_name ($user->ID) " .
                            "expired $days_since_expiry days ago"
                    );
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * List content owned by a user we might be deleting.
     */
    private function acc_list_content($old_user_id)
    {
        $old_user_posts = get_posts([
            "author" => $old_user_id,
            "post_type" => "any",
            "post_status" => "any",
            "posts_per_page" => -1, //All posts
        ]);

        $count = count($old_user_posts);
        if ($count != 0) {
            accLog("User $old_user_id owns $count posts");
        }

        foreach ($old_user_posts as $post) {
            if ($post->post_type == "attachment") {
                accLog(
                    " post $post->ID type=$post->post_type date=$post->post_date guid=$post->guid"
                );
            } else {
                accLog(
                    " post $post->ID type=$post->post_type date=$post->post_date title=$post->post_title"
                );
            }
        }
    }

    /**
     * This is the user import loop (when triggered by a timer)
     */
    public function automatic_db_check()
    {
        $this->local_db_check("Automatic");
    }

    /**
     * Go over our local user database and do some sanity checks.
     * This is mainly to delete old accounts that are no longer needed
     * on the website, and also to raise warnings if inconsistencies are detected.
     * Possible future improvement: we could double-check if the role is correct
     * according to the membership status and the plugin config.
     *
     * What we do:
     * -We check for old accounts and delete them if configured to do so.
     * -We check for suspicious user expiry dates (no expiry date, or
     *  an expiry date more than 1 year 1 month from now) and log warnings.
     */
    private function local_db_check($mode)
    {
        $GLOBALS["acc_logstr"] = ""; //Clear the API response log string
        //Pick a new logfile
        $logfilename = basename(acc_pick_new_log_file("_sanity_check"));
        accLog("$mode DB sanity check, logging to {$logfilename}");
        $timestamp_start = date_i18n("Y-m-d-H-i-s");

        $verify_expiry = accUM_is_verify_expiry();
        if ($verify_expiry) {
            accLog("");
            accLog("=============================================");
            accLog("Checking local DB, as stated in configuration");
            accLog("=============================================");
        } else {
            accLog("Skipping local DB sanity check, as per configuration");
        }

        $warnings = [];
        $errors = [];

        $delete_ex_users = accUM_is_delete_ex_users();
        if ($delete_ex_users) {
            //When should we delete expired users? Who will now own the content?
            $days_before_delete = accUM_get_when_2_delete_ex_user();
            accLog(
                "Will delete users expired for more than $days_before_delete days"
            );

            $new_owner = "";
            $new_owner_error = false;
            $new_owner_login = accUM_get_new_owner();
            if (empty($new_owner_login)) {
                //User did not specify a new owner, so delete content
                accLog("and will delete their published content");
            } else {
                $new_owner = get_user_by("login", $new_owner_login);
                if ($new_owner instanceof WP_User) {
                    accLog(
                        "and transfer content to $new_owner->user_nicename ($new_owner->ID)"
                    );
                } else {
                    $new_owner_error = true;
                    accLog(
                        "Error in config, invalid new content owner " .
                            "($new_owner_login). Skipping delete of expired users."
                    );
                    $errors[] = "Error in config, invalid new content owner ($new_owner_login)";
                }
            }
        }

        $deleted_users = [];
        $num_active = 0;
        $num_inactive = 0;
        $num_processing = 0;
        $processing_email_list = "";
        $more_than_a_year_from_now = acc_now_plus_N_days(400);
        $user_ids = get_users(["fields" => "ID"]);

        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);

            //Delete users which have been expired long enough
            if (
                $delete_ex_users &&
                $this->acc_is_time_to_delete_user($user, $days_before_delete)
            ) {
                if (!$new_owner_error) {
                    $this->acc_list_content($user->ID);
                    if ($new_owner instanceof WP_User) {
                        //User specified a new content owner
                        $rc = wp_delete_user($user->ID, $new_owner->ID);
                    } else {
                        $rc = wp_delete_user($user->ID);
                    }
                    if ($rc) {
                        accLog(
                            "Successfully deleted $user->display_name ($user->ID)"
                        );
                        $deleted_users[] = "$user->display_name  ($user->user_email)";
                        continue; //User no longer exists, skip rest of loop
                    } else {
                        accLog(
                            "Failed to delete $user->display_name ($user->ID)"
                        );
                        $errors[] = "Failed to delete $user->display_name ($user->ID)";
                    }
                }
            }

            //Sanity check: raise a warning if user has no or weird expiry date.
            $expiry = $user->acc_mship_expiry;
            if (empty($expiry)) {
                $warnings[] = "$user->display_name ($user->user_login, $user->user_email) has no membership expiry!";
            } elseif ($expiry > $more_than_a_year_from_now) {
                $warnings[] = "$user->display_name ($user->user_login, $user->user_email) has expiry=$expiry!";
            }

            //Give warning for users that have not signed a waiver
            if (
                !empty($user->acc_sections) &&
                $user->acc_waiver_signed != "true"
            ) {
                $warnings[] = "$user->display_name ($user->user_login, $user->user_email) has not signed waiver";
                $processing_email_list .=
                    $user->display_name . " &lt" . $user->user_email . "&gt, ";
                $num_processing++;
            }

            //count active and inactive
            if (acc_is_user_expired($user)) {
                $num_inactive++;
            } else {
                $num_active++;
            }
        }

        //Give a summary
        $deleted_cnt = count($deleted_users);
        accLog("Local DB check deleted $deleted_cnt obsolete users");
        accLog("Number of valid members = $num_active");
        accLog("Number of expired members = $num_inactive");
        accLog("Number of members without signed waiver = $num_processing");
        foreach ($warnings as $warning) {
            accLog("Warning: $warning");
        }
        accLog(
            "<br>List of users email with no waivers = $processing_email_list<br>"
        );

        //FIXME reevaluate the admin email function
        $operation =
            "The ACC web site local DB check made the following changes:";
        $this->send_admin_email(
            $operation,
            [], //No sections added
            [], //No sections deleted
            $deleted_users,
            $warnings,
            $errors
        );

        $timestamp_end = date_i18n("Y-m-d-H-i-s");
        accLog("DB Sanity check is now over");
        accLog("Start time: " . $timestamp_start);
        accLog("End time: " . $timestamp_end);
        accLog("\n\n");
    }

    /**
     * Email a processing error to the webmaster and include the log buffer.
     */
    private function send_error_email()
    {
        $email_addrs = accUM_get_notification_emails();

        if (!empty($email_addrs)) {
            $title = accUM_get_notification_title() . " (error)";
            $content = "---log---\n";
            $content .= $GLOBALS["acc_logstr"];

            accLog("Sending error email to: $email_addrs");
            accLog("email title=$title");
            //accLog("email content=$content");
            $rc = wp_mail(
                $email_addrs,
                $title,
                $content,
                "Content-Type: text/plain; charset=UTF-8"
            );
            if ($rc) {
                accLog("Sent notification email to: $email_addrs");
            } else {
                accLog("Failed to send notification email");
            }
        }
    }

    /**
     * If the option is configured, send a summary email to the admin.
     * The email is only sent if needed (there were new users, expired users,
     * deleted accounts or warnings).
     * There is no checking done to ensure the notification email addresses are valid.
     */
    private function send_admin_email(
        $operation,
        $sectionsAdded,
        $sectionsDeleted,
        $deleted_users,
        $warnings,
        $errors
    ) {
        $email_addrs = accUM_get_notification_emails();
        if (
            !empty($email_addrs) &&
            (!empty($sectionsAdded) ||
                !empty($sectionsDeleted) ||
                !empty($errors) ||
                !empty($deleted_users))
        ) {
            $title = accUM_get_notification_title();
            $content = $operation . "\n\n";
            if (!empty($sectionsAdded)) {
                $content .=
                    "sections added: " . implode(",", $sectionsAdded) . "\n";
            }
            if (!empty($sectionsDeleted)) {
                $content .=
                    "sections deleted: " .
                    implode(",", $sectionsDeleted) .
                    "\n";
            }
            if (!empty($deleted_users)) {
                $content .= "\n---deleted obsolete members---\n";
                foreach ($deleted_users as $user) {
                    $content .= $user . "\n";
                }
            }
            $content .= "\n---errors---\n";
            foreach ($errors as $error) {
                $content .= $error . "\n";
            }
            $content .= "\n---warnings---\n";
            foreach ($warnings as $warning) {
                $content .= $warning . "\n";
            }

            accLog("Sending notification email to: $email_addrs");
            accLog("email title=$title");
            accLog("email content=$content");
            $rc = wp_mail(
                $email_addrs,
                $title,
                $content,
                "Content-Type: text/plain; charset=UTF-8"
            );
            if ($rc) {
                accLog("Sent notification email to: $email_addrs");
            } else {
                accLog("Failed to send notification email");
            }
        }
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . "css/acc_user_importer-admin.css",
            [],
            $this->version,
            "all"
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . "js/acc_user_importer-admin.js",
            ["jquery"],
            $this->version,
            false
        );
        wp_localize_script($this->plugin_name, "ajax_object", [
            "url" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("accUserAPI"),
        ]);
    }

    /**
     * Show list of sections membership on the user profile page
     * One section per line. Can be edited and saved back.
     */
    public function display_acc_sections_field($user)
    {
        $acc_sections = get_user_meta($user->ID, "acc_sections", true);
        if (!is_array($acc_sections)) {
            $acc_sections = [];
        }
        ?>
        <h3>Access Sections</h3>
        <table class="form-table">
            <tr>
                <th><label for="acc_sections">Sections</label></th>
                <td>
                    <textarea name="acc_sections" id="acc_sections" rows="5" cols="50"><?php echo esc_textarea(
                        implode("\n", $acc_sections)
                    ); ?></textarea>
                    <p class="description">Enter one section per line.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_acc_sections_field($user_id)
    {
        if (!current_user_can("edit_user", $user_id)) {
            return false;
        }

        if (isset($_POST["acc_sections"])) {
            $raw_input = sanitize_textarea_field($_POST["acc_sections"]);
            $sections_array = array_filter(
                array_map("trim", explode("\n", $raw_input))
            );
            // Validate against supported sections
            $valid_sections = acc_get_supported_sections();
            $filtered_sections = array_intersect(
                $sections_array,
                $valid_sections
            );
            update_user_meta($user_id, "acc_sections", $filtered_sections);
        }
    }
}
