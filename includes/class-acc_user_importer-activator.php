<?php

/**
 * Fired during plugin activation
 *
 * @package    acc_user_importer
 * @subpackage acc_user_importer/includes
 * @author     Raz Peel <raz.peel@gmail.com>
 * @link       https://www.facebook.com/razpeel
 */

class acc_user_importer_Activator
{
    private $plugin_name;
    private $version;
    private $previous_plugin_version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        register_activation_hook(ACC_MAIN_PLUGIN_FILE_URL, [$this, "activate"]);
    }

    /**
     * Activation scripts.
     */
    public function activate()
    {
        $this->read_previous_plugin_version_from_db();

        if ($this->version != $this->previous_plugin_version) {
            $this->process_upgrade();
            $this->write_new_plugin_version_to_db();
        }

        acc_cron_activate();
    }

    // Check version number of plugin which ran last.
    // Starting in v2.1.0, we store the version of the plugin in the database,
    // and this info is available when we activate to see if any upgrade
    // or downgrade work is needed.
    private function read_previous_plugin_version_from_db()
    {
        $options = get_option(ACCUM_DATA);
        if (!empty($options["accUM_plugin_version"])) {
            $this->previous_plugin_version = $options["accUM_plugin_version"];
        } else {
            $this->previous_plugin_version = "unknown";
        }
    }

    public function get_previous_plugin_version()
    {
        return $this->previous_plugin_version;
    }

    // Write the current plugin version in the DB.
    private function write_new_plugin_version_to_db()
    {
        $options = get_option(ACCUM_DATA);
        $options["accUM_plugin_version"] = $this->version;
        update_option(ACCUM_DATA, $options);
        //error_log("Updated version# from $this->previous_plugin_version " .
        //	 	  "to $this->version in DB\n");		//Normally disabled
    }

    /**
     * Upon activation, there might be some upgrade/downgrade cleanup work.
     */
    private function process_upgrade()
    {
        $previous_version = $this->get_previous_plugin_version();
        if (
            $previous_version === "unknown" ||
            ($previous_version > "1.3.0" && $previous_version < "2.3.0")
        ) {
            // Some upgrade work has to be done
            $log_file = $this->pick_new_log_filename("log_upgrade_");
            error_log(
                "Upgrade from $previous_version to $this->version needed\n",
                3,
                $log_file
            );
            $this->convert_settings($log_file);
            error_log("Upgrade done\n", 3, $log_file);
        }
    }

    /**
     * Read accUM_data and make adjusments as required.
     */
    private function convert_settings($log_file)
    {
        $options = get_option(ACCUM_DATA);
        // $options_gen = get_option("accUM_gen");
        // $options_sec = get_option("accUM_sec_OUTAOUAIS");
        // error_log(print_r($options_gen, true));
        // error_log(print_r($options_sec, true));
        error_log("Here's a dump of the legacy settings\n", 3, $log_file);
        error_log(serialize($options) . "\n", 3, $log_file);

        // Read API ID and detect which section the existing settings are for
        $section = $options["accUM_section_api_id"];
        $section_option_name = "accUM_sec_" . $section;
        $section_options = [];
        if (isset($section)) {
            unset($options["accUM_section_api_id"]);
            $options["enabled_sections"] = [$section => "on"];
            error_log("Detected section $section\n", 3, $log_file);

            // The token config is a comma-separated list of sections and tokens.
            // Example: MONTRÉAL:a1b234x,OUTAOUAIS:abcdef1234
            if (isset($options["accUM_token"])) {
                $token_cfg = $options["accUM_token"];
                unset($options["accUM_token"]);
                $tokens = explode(",", $token_cfg);
                foreach ($tokens as $tok_string) {
                    $tok_parts = explode(":", $tok_string);
                    if (count($tok_parts) == 2) {
                        if ($tok_parts[0] == $section) {
                            //Found token for our section
                            $section_options["token"] = $tok_parts[1];
                            error_log("Converted accUM_token\n", 3, $log_file);
                        }
                    }
                }
            }
            //Convert accUM_new_user_role_action
            if (isset($options["accUM_new_user_role_action"])) {
                $value = $options["accUM_new_user_role_action"];
                unset($options["accUM_new_user_role_action"]);
                $section_options["new_user_role_action"] = $value;
                error_log(
                    "Converted accUM_new_user_role_action\n",
                    3,
                    $log_file
                );
            }

            //Convert accUM_new_user_role_value
            if (isset($options["accUM_new_user_role_value"])) {
                $value = $options["accUM_new_user_role_value"];
                unset($options["accUM_new_user_role_value"]);
                $section_options["new_user_role_value"] = $value;
                error_log(
                    "Converted accUM_new_user_role_value\n",
                    3,
                    $log_file
                );
            }

            //Convert accUM_ex_user_role_action
            if (isset($options["accUM_ex_user_role_action"])) {
                $value = $options["accUM_ex_user_role_action"];
                unset($options["accUM_ex_user_role_action"]);
                $section_options["ex_user_role_action"] = $value;
                error_log(
                    "Converted accUM_ex_user_role_action\n",
                    3,
                    $log_file
                );
            }

            //Convert accUM_ex_user_role_value
            if (isset($options["accUM_ex_user_role_value"])) {
                $value = $options["accUM_ex_user_role_value"];
                unset($options["accUM_ex_user_role_value"]);
                $section_options["ex_user_role_value"] = $value;
                error_log("Converted accUM_ex_user_role_value\n", 3, $log_file);
            }

            //Convert acc_email_activation
            $opt = get_option("acc_email_activation");
            if (isset($opt)) {
                if (isset($opt[0])) {
                    $value = $opt[0];
                    $section_options["welcome_email_enable"] = $value;
                    error_log(
                        "Converted acc_email_activation[0]\n",
                        3,
                        $log_file
                    );
                }
                if (isset($opt[1])) {
                    $value = $opt[1];
                    $section_options["goodbye_email_enable"] = $value;
                    error_log(
                        "Converted acc_email_activation[1]\n",
                        3,
                        $log_file
                    );
                }
                delete_option("acc_email_activation"); //delete old setting
            }

            //Convert acc_email_titles
            $opt = get_option("acc_email_titles");
            if (isset($opt)) {
                if (isset($opt[0])) {
                    $value = $opt[0];
                    $section_options["welcome_email_title"] = $value;
                    error_log("Converted acc_email_titles[0]\n", 3, $log_file);
                }
                if (isset($opt[1])) {
                    $value = $opt[1];
                    $section_options["goodbye_email_title"] = $value;
                    error_log("Converted acc_email_titles[1]\n", 3, $log_file);
                }
                delete_option("acc_email_titles"); //delete old setting
            }

            //Convert acc_email_contents
            $opt = get_option("acc_email_contents");
            if (isset($opt)) {
                if (isset($opt[0])) {
                    $value = $opt[0];
                    $section_options["welcome_email_content"] = $value;
                    error_log(
                        "Converted acc_email_contents[0]\n",
                        3,
                        $log_file
                    );
                }
                if (isset($opt[1])) {
                    $value = $opt[1];
                    $section_options["goodbye_email_content"] = $value;
                    error_log(
                        "Converted acc_email_contents[1]\n",
                        3,
                        $log_file
                    );
                }
                delete_option("acc_email_contents"); //delete old setting
            }

            //Rewrite the updated section options
            update_option("accUM_sec_$section", $section_options);
        }

        //Convert accUM_since_date
        if (isset($options["accUM_since_date"])) {
            $value = $options["accUM_since_date"];
            unset($options["accUM_since_date"]);
            $options["since_date"] = $value;
            error_log("Converted accUM_since_date\n", 3, $log_file);
        }

        //Convert accUM_sync_list
        if (isset($options["accUM_sync_list"])) {
            $value = $options["accUM_sync_list"];
            unset($options["accUM_sync_list"]);
            $options["sync_list"] = $value;
            error_log("Converted accUM_sync_list\n", 3, $log_file);
        }

        //Convert accUM_login_name_mapping
        if (isset($options["accUM_login_name_mapping"])) {
            $value = $options["accUM_login_name_mapping"];
            unset($options["accUM_login_name_mapping"]);
            $options["login_name_mapping"] = $value;
            error_log("Converted accUM_login_name_mapping\n", 3, $log_file);
        }

        //Convert accUM_verify_expiry
        if (isset($options["accUM_verify_expiry"])) {
            $value = $options["accUM_verify_expiry"];
            unset($options["accUM_verify_expiry"]);
            $options["verify_expiry"] = $value;
            error_log("Converted accUM_verify_expiry\n", 3, $log_file);
        }

        //Convert accUM_ex_user_role_value
        if (isset($options["accUM_delete_ex_users"])) {
            $value = $options["accUM_delete_ex_users"];
            unset($options["accUM_delete_ex_users"]);
            $options["delete_ex_users"] = $value;
            error_log("Converted accUM_delete_ex_users\n", 3, $log_file);
        }

        //Convert accUM_when_2_delete_ex_user
        if (isset($options["accUM_when_2_delete_ex_user"])) {
            $value = $options["accUM_when_2_delete_ex_user"];
            unset($options["accUM_when_2_delete_ex_user"]);
            $options["when_2_delete_ex_user"] = $value;
            error_log("Converted accUM_when_2_delete_ex_user\n", 3, $log_file);
        }

        //Convert accUM_new_owner
        if (isset($options["accUM_new_owner"])) {
            $value = $options["accUM_new_owner"];
            unset($options["accUM_new_owner"]);
            $options["new_owner"] = $value;
            error_log("Converted accUM_new_owner\n", 3, $log_file);
        }

        //Convert accUM_notification_emails
        if (isset($options["accUM_notification_emails"])) {
            $value = $options["accUM_notification_emails"];
            unset($options["accUM_notification_emails"]);
            $options["notification_emails"] = $value;
            error_log("Converted accUM_notification_emails\n", 3, $log_file);
        }

        //Convert accUM_notification_title
        if (isset($options["accUM_notification_title"])) {
            $value = $options["accUM_notification_title"];
            unset($options["accUM_notification_title"]);
            $options["notification_title"] = $value;
            error_log("Converted accUM_notification_title\n", 3, $log_file);
        }

        //Convert accUM_max_log_files
        if (isset($options["accUM_max_log_files"])) {
            $value = $options["accUM_max_log_files"];
            unset($options["accUM_max_log_files"]);
            $options["max_log_files"] = $value;
            error_log("Converted accUM_max_log_files\n", 3, $log_file);
        }

        //Rewrite the updated options
        update_option(ACCUM_DATA, $options);
    }

    /**
     * Remove user meta "previous_roles" which was used from version 1.3.0 to 2.1.0
     * and that we no longer use.  This is not really critical, but it is best
     * to not pollute the DB with obsolete elements.
     */
    // private function scan_user_db_and_remove_previous_roles($log_file)
    // {
    //     $user_ids = get_users(["fields" => "ID"]);
    //     foreach ($user_ids as $user_id) {
    //         $user = get_userdata($user_id);
    //         if (isset($user->previous_roles)) {
    //             $rc = delete_user_meta($user_id, "previous_roles");
    //             error_log(
    //                 "Removed obsolete 'previous_role' for user {$user_id}, result={$rc}\n",
    //                 3,
    //                 $log_file
    //             );
    //         }
    //     }
    // }

    /*
     * Generate a new log file, based on the current day and time. Ex:
     * plugins/acc_user_importer/logs/log_upgrade_2024-02-13-16-35-04.txt
     */
    private function pick_new_log_filename($prefix)
    {
        $log_date = date_i18n("Y-m-d-H-i-s");
        $log_filename = ACC_LOG_DIR . $prefix . $log_date . ".txt";
        return $log_filename;
    }
}
