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
     * This creates an endpoint such as https://mywebsite/wp-json/api/v1/members
     */
    public function register_acc_rest_api()
    {
        register_rest_route("api/v1", "/members", [
            "methods" => "POST",
            "callback" => [$this, "handle_acc_membership_notification"],
            "permission_callback" => [$this, "verify_bearer_token"],
            //"permission_callback" => "__return_true", // for no authentication
        ]);
    }

    /**
     * Authentication of the request
     */
    public function verify_bearer_token()
    {
        $headers = getallheaders();

        if (!isset($headers["Authorization"])) {
            return new WP_Error(
                "no_auth_header",
                "Authorization header missing",
                ["status" => 401]
            );
        }

        $auth_header = $headers["Authorization"];
        if (strpos($auth_header, "Bearer ") !== 0) {
            return new WP_Error(
                "invalid_auth_format",
                "Invalid Authorization format",
                ["status" => 401]
            );
        }

        $apiToken = accUM_get_api_token();
        $token = substr($auth_header, 7); // Remove "Bearer " prefix
        if ($token !== $apiToken) {
            return new WP_Error("invalid_token", "Token is invalid", [
                "status" => 403,
            ]);
        }

        return true;
    }

    /**
     * Handle membership notification from national ACC IT platform.
     */
    public function handle_acc_membership_notification($request)
    {
        $params = $request->get_json_params();
        $status = "success";
        $message = "Member data received";
        // Log the received data
        //error_log('Webhook received: ' . print_r($params, true));

        $GLOBALS["acc_logstr"] = ""; //Clear the API response log string
        $rc = $this->proccess_user_data($params);
        if ($rc !== true) {
            // An error happened, warn webmaster
            $status = "error";
            $message = strval($rc);
            accLog(strval($rc));
            $this->send_error_email();
        }
        //error_log($GLOBALS["acc_logstr"]); //print content of log in the debug file

        return new WP_REST_Response(
            [
                "status" => $status,
                "message" => $message,
            ],
            200
        );
    }

    /**
     * Returns the list of sections the user has joined
     */
    private function getSectionsAdded($rxdSections, $user)
    {
        $userSections = is_array($user->acc_sections)
            ? $user->acc_sections
            : [];
        $sectionsAdded = [];
        foreach ($rxdSections as $section) {
            if (!in_array($section, $userSections)) {
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
    private function getSectionsDeleted($rxdSections, $user)
    {
        $userSections = is_array($user->acc_sections)
            ? $user->acc_sections
            : [];
        $sectionsDeleted = [];
        foreach ($userSections as $section) {
            if (!in_array($section, $rxdSections)) {
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
     * Timestamps are numbers, UNIX Epoch (since 1970) in milliseconds.
     *     {
     *         "action": (add, update, remove),
     *         "acc_notif_timestamp": 1762456698480,
     *         "acc_member_id": 55148,           (text or number)
     *         "first_name": "François",
     *         "last_name": "Bessette",
     *         "user_email": "francois.bessette@gmail.com",
     *         "cell_phone": "555-555-3689",     (text or number)
     *         "acc_sections": "Outaouais;Ottawa",  (a string)
     *         "acc_mship_type": "Individual",
     *         "acc_mship_expiry": 1767139200000,   (but stored as Y-M-D in DB)
     *         "acc_waiver_expiry": 1792612236000   (but stored as Y-M-D in DB)
     *         "acc_contact_name": "Sonia Pouliot",
     *         "acc_contact_email": "spouliot@gmail.com",
     *         "acc_contact_phone": "555-555-1234",    (text or number)
     *     }
     *
     * acc_mship_expiry will be null for auto-renewal memberships
     * acc_waiver_expiry will be null if the person never signed the waiver
     */
    private function proccess_user_data($params)
    {
        //Let's pick a new log file
        $userFirstName = $params["first_name"] ?? "";
        $userLastName = $params["last_name"] ?? "";
        $userNameInLog = "_" . $userFirstName . "_" . $userLastName;
        $userNameInLog = preg_replace("/[^A-Za-z0-9\-_]/", "_", $userNameInLog);
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
            // The 'empty' test will trigger for numbers 0 but that is fine,
            // we should never have an acc_member_id being 0.
            if (empty($params[$field])) {
                if ($field == "user_email") {
                    // Children memberships often do not have an email address.
                    // Not an error, just ignore notification.
                    accLog("No email, skip this notification");
                    return true;
                } else {
                    $msg = "Error, missing $field in notification";
                    return $msg;
                }
            }
        }

        if ($params["action"] == "add" || $params["action"] == "update") {
            //Sanity checks for additional mandatory fields
            $needed = [
                "acc_sections",
                "acc_mship_type",
            ];
            foreach ($needed as $index => $field) {
                if (!isset($params[$field])) {
                    $msg = "Error, missing $field in notification";
                    return $msg;
                }
            }
        }

        // Make sure email is valid.
        if (!is_email($params["user_email"])) {
            $msg = "Error: rxd invalid email " . $params["user_email"];
            return $msg;
        }

        $action = $params["action"];
        $notifTimestamp = $params["acc_notif_timestamp"];
        $userMemberId = strval($params["acc_member_id"]);
        $userEmail = strtolower($params["user_email"] ?? "");
        $userCellPhone = strval($params["cell_phone"]) ?? "";
        $receivedSections = $params["acc_sections"] ?? [];
        $mshipType = $params["acc_mship_type"] ?? "";
        $mshipExpiry = $params["acc_mship_expiry"] ?? null;
        $waiverExpiry = $params["acc_waiver_expiry"] ?? null;
        $contactFname = $params["acc_contact_name"] ?? "";
        $contactLname = $params["acc_contact_email"] ?? "";
        $contactPhone = strval($params["acc_contact_phone"]) ?? "";

        if (empty($userFirstName) && empty($userLastName)) {
            // This case has been seen and can be a valid ACC account.
            // For display purpose, use the email address.
            // Wordpress uses nicename as a unique user identifier in URL
            // and author archives. So it should be unique and
            // hopefully never change. Best to use the ACC memberID.
            accLog(" > Empty name, will use email and memberID");
            $userDisplayname = strstr($userEmail, '@', true);
            $userNicename = $userMemberId;
        } else {
            $userDisplayname = $userFirstName . " " . $userLastName;
            $userNicename = $userDisplayname;
        }

        // Sanity check received timestamps and convert some to Y-M-D
        // Keep the notification timestamp in UNIX format because
        // we want high precision and it is not meant to be read by humans.
        if (!is_int($notifTimestamp)) {
            $msg = "Error, rxd 'acc_notif_timestamp' is not integer";
            return $msg;
        }
        if (!empty($waiverExpiry) && !is_int($waiverExpiry)) {
            return "Error, rxd 'acc_waiver_expiry' is not integer";
        }
        if (!empty($mshipExpiry) && !is_int($mshipExpiry)) {
            return "Error, rxd 'acc_mship_expiry' is not integer";
        }
        if (!empty($waiverExpiry)) {
            $waiverExpiry = date("Y-m-d", intval($waiverExpiry / 1000));
        }
        if (!empty($mshipExpiry)) {
            $mshipExpiry = date("Y-m-d", intval($mshipExpiry / 1000));
        }

        $sectionsOfInterest = accUM_get_enabled_sections();

        //Validate received membership type (but not for action=remove)
        if ($action != "remove") {
            $validMships = acc_get_mship_names();
            if (!in_array($mshipType, $validMships)) {
                $msg = " > warning, $mshipType is an invalid membership type";
                $warnings[] = $msg;
                accLog($msg);
            }
        }

        // Sanity check: keep only sections we are interested in. Log
        // warning if ACC notifies us about sections we dont care about.
        $validSections = acc_get_supported_sections();
        $rxdSections = [];
        $sectionsArray = explode(";", $receivedSections); //Split the string

        foreach ($sectionsArray as $section) {
            $section = trim($section); // Trim whitespace

            if (!in_array($section, $validSections)) {
                $msg = " > warning, $section is an invalid section";
                $warnings[] = $msg;
                accLog($msg);
            }

            if (in_array($section, $sectionsOfInterest)) {
                // Rxd membership is in our interest list, keep it.
                $rxdSections[] = $section;
                //accLog("Rxd membership for section $section is part of our interest list");
            } else {
                // Nothing to do. It is not an error. The server will notify
                // about members belonging to the section of interest, but a
                // member can also be part of other sections and Hubspot will
                // not filter the information, it will give a transparent view
                // of what the member is part of. Just ignore the sections we
                // do not care about.
            }
        }

        // Does received user have a membership?
        if ($action == "remove" || empty($rxdSections)) {
            $rxdSections = [];
            $userIsValid = false;
        } else {
            $userIsValid = true;
        }

        $loginNameMapping = accUM_get_login_name_mapping();
        switch ($loginNameMapping) {
            case "Firstname Lastname":
                $loginName = $userDisplayname;
                break;
            case "member_number":
            default:
                $loginName = sanitize_user($userMemberId);
                break;
        }
        accLog(" > User login name is $loginName");

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
            "display_name" => $userDisplayname,
            "user_email" => $userEmail,
        ];

        $accUserMetaData = [
            "acc_notif_timestamp" => strval($notifTimestamp),
            "acc_mship_type" => $mshipType,
            "acc_waiver_expiry" => $waiverExpiry,
            "acc_mship_expiry" => $mshipExpiry,
            "acc_contact_name" => $contactFname,
            "acc_contact_email" => $contactLname,
            "acc_contact_phone" => $contactPhone,
            "cell_phone" => $userCellPhone,
            "acc_member_id" => $userMemberId,
            "nickname" => $userDisplayname,
            "acc_sections" => $rxdSections,
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
                    if ($user->display_name == $userDisplayname) {
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
                    intval($user->acc_notif_timestamp) > $notifTimestamp
                ) {
                    $err =
                        "Error, notification seems outdated. " .
                        "Last rxd one was $user->acc_notif_timestamp";
                    return $err;
                }

                $sectionsAdded = $this->getSectionsAdded($rxdSections, $user);
                $sectionsDeleted = $this->getSectionsDeleted(
                    $rxdSections,
                    $user
                );

                // Special case for safety: if the action is remove, Hubspot
                // does not set the expiry field. But we want to keep the one
                // already in user DB. Unless it needs to be shortened.
                // Also do not erase the DB acc_mship_type.
                if (!$userIsValid) {
                    $today = date("Y-m-d");
                    if (empty($accUserMetaData["acc_mship_expiry"])) {
                        $accUserMetaData["acc_mship_expiry"] = $user->acc_mship_expiry;
                        accLog(" > No expiry in notification, use ".
                               "$user->acc_mship_expiry from DB");
                    }

                    if ($accUserMetaData["acc_mship_expiry"] > $today) {
                        $accUserMetaData["acc_mship_expiry"] = $today;
                        accLog(" > Forced user expiry to today");
                    }

                    if (empty($accUserMetaData["acc_mship_type"])) {
                        $accUserMetaData["acc_mship_type"] = $user->acc_mship_type;
                        accLog(" > No membership type in notification, use ".
                               "$user->acc_mship_type from DB");
                    }

                }

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
                            $oldVal = is_array($user->$field)
                                ? $user->$field
                                : [];
                            $old = implode(",", $oldVal);
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
                        accLog(" > WP:" . $updateResp->get_error_message());
                        return " > error, failed to update user";
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
                        accLog("Error changing loginName for $userDisplayname");
                    } else {
                        $result_str = " success";
                    }
                    accLog(
                        " > user {$userID} username changed from " .
                            "{$user->user_login} to {$loginName}, update database $result_str"
                    );
                    //Erase user cache so that future access gets the right data.
                    clean_user_cache($userID);
                }

                // For each section added to the user membership, send welcome email, etc.
                foreach ($sectionsAdded as $section) {
                    $this->takeActionOnNewUser($section, $userID, false);
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
            $accUserData["user_nicename"] = $userNicename;  //WP will sanitize
            $accUserData["user_login"] = $loginName;

            // Insert new user
            $userID = wp_insert_user($accUserData);
            if (is_wp_error($userID)) {
                accLog(" > WP:" . $userID->get_error_message());
                return " > error, failed to create user";
            }

            accLog(" > Created new user " . $userID);
            $new_active_users[] = $accUserData["display_name"];

            //Insert meta fields.
            $accUserMetaData["acc_sections"] = $rxdSections;
            foreach ($accUserMetaData as $field => $value) {
                update_user_meta($userID, $field, $value);
            }

            // Execute hooks for new membership
            do_action("acc_new_membership", $userID);

            $sectionsAdded = $rxdSections;
            foreach ($sectionsAdded as $section) {
                $this->takeActionOnNewUser($section, $userID, true);
            }
        } while (false); //end dummy user processing loop

        $operation =
            "The ACC website received the following changes " .
            "for $userDisplayname <$userEmail>:";
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
    private function takeActionOnNewUser($section, $user_id, $newUser)
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
        } elseif ($newUser && $new_user_role_action == "add_role") {
            // User has just been created, and Wordpress assigned an annoying
            // default role we want to get rid of. Change to set_role
            // in order to overwrite the Wordpress default role.
            accLog(" > use set_role to overwrite default Wordpress role");
            $new_user_role_action = "set_role";
        }

        if ($new_user_role_action == "set_role") {
            if (!in_array($new_user_role_value, $user_roles, true)) {
                accLog(" > Changing user role to $new_user_role_value");
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
            return;
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
        $num_wo_waiver = 0;
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

            // Some WP accounts are manually created for admin purposes. Those
            // typically have no ACC member ID. Not much validation done on those.
            if (!$user->has_prop("acc_member_id")) {
                $warnings[] =
                    "$user->display_name ($user->user_email) has no ACC " .
                    "member ID, it's probably a manually created admin account";
                continue;
            }

            if (!$user->has_prop("acc_sections")) {
                $warnings[] = "$user->display_name ($user->user_email) has no " .
                              "acc_sections entry in DB, weird";
            } else if (!acc_is_user_expired($user)) {
                $sections = $user->acc_sections;
                if (!is_array($sections) || empty($sections)) {
                    $warnings[] = "$user->display_name ($user->user_email) is not " .
                                "expired but not part of any section";
                }
            }

            //Sanity check: raise a warning if user has no or weird expiry date.
            $expiry = $user->acc_mship_expiry ?? null;
            if ($expiry == null) {
                //Must be an auto-renewal account
            } elseif ($expiry > $more_than_a_year_from_now) {
                $warnings[] = "$user->display_name ($user->user_login, $user->user_email) ".
                              "has suspicious expiry=$expiry!";
            }

            //Give warning for users with a membership but no signed waiver
            if (!acc_is_user_expired($user) &&
                !acc_is_waiver_valid($user)) {
                $num_wo_waiver++;
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
        accLog("Number of members without signed waiver = $num_wo_waiver");
        foreach ($warnings as $warning) {
            accLog("Warning: $warning");
        }

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
        // Get the raw UNIX timestamp from user meta
        $notif_timestamp = get_user_meta(
            $user->ID,
            "acc_notif_timestamp",
            true
        );

        // Convert to human-readable format if valid
        if (!empty($notif_timestamp) && is_numeric($notif_timestamp)) {
            $formatted_date = date(
                "Y-m-d H:i:s",
                intval($notif_timestamp / 1000)
            );
        } else {
            $formatted_date = "Not set or invalid";
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="acc_notif_timestamp">Last API Notification</label></th>
                <td>
                    <input type="text" name="acc_notif_timestamp" id="acc_notif_timestamp"
                        value="<?php echo esc_attr(
                            $formatted_date
                        ); ?>" class="regular-text" disabled />
                    <p class="description">This is when we received the last notification (read-only).</p>
                </td>
            </tr>
        </table>
        <?php
        $acc_sections = get_user_meta($user->ID, "acc_sections", true);
        if (!is_array($acc_sections)) {
            $acc_sections = [];
        }
        ?>
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
