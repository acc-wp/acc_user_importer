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

    // List of ACC section membership types.
    // Obtained from an Interpodia Excel spreadsheet.
    private $membershipTable = [
        "1807" => ["section" => "YUKON", "type" => "adult"],
        "1809" => ["section" => "YUKON", "type" => "youth"],
        "1810" => ["section" => "YUKON", "type" => "family1"],
        "1808" => ["section" => "YUKON", "type" => "family2"],
        "1806" => ["section" => "YUKON", "type" => "child"],
        "2788" => ["section" => "YUKON", "type" => "life_member"],
        "1918" => ["section" => "BUGABOOS", "type" => "adult"],
        "1920" => ["section" => "BUGABOOS", "type" => "youth"],
        "1921" => ["section" => "BUGABOOS", "type" => "family1"],
        "1919" => ["section" => "BUGABOOS", "type" => "family2"],
        "1917" => ["section" => "BUGABOOS", "type" => "child"],
        "2761" => ["section" => "BUGABOOS", "type" => "life_member"],
        "1812" => ["section" => "COLUMBIA MOUNTAINS", "type" => "adult"],
        "1814" => ["section" => "COLUMBIA MOUNTAINS", "type" => "youth"],
        "1815" => ["section" => "COLUMBIA MOUNTAINS", "type" => "family1"],
        "1813" => ["section" => "COLUMBIA MOUNTAINS", "type" => "family2"],
        "1811" => ["section" => "COLUMBIA MOUNTAINS", "type" => "child"],
        "2764" => ["section" => "COLUMBIA MOUNTAINS", "type" => "life_member"],
        "1817" => ["section" => "OKANAGAN", "type" => "adult"],
        "1819" => ["section" => "OKANAGAN", "type" => "youth"],
        "1820" => ["section" => "OKANAGAN", "type" => "family1"],
        "1818" => ["section" => "OKANAGAN", "type" => "family2"],
        "1816" => ["section" => "OKANAGAN", "type" => "child"],
        "2774" => ["section" => "OKANAGAN", "type" => "life_member"],
        "2348" => ["section" => "OKANAGAN", "type" => "student_club"],
        "2769" => ["section" => "OKANAGAN", "type" => "student_club"],
        "1822" => ["section" => "PRINCE GEORGE", "type" => "adult"],
        "1824" => ["section" => "PRINCE GEORGE", "type" => "youth"],
        "1825" => ["section" => "PRINCE GEORGE", "type" => "family1"],
        "1823" => ["section" => "PRINCE GEORGE", "type" => "family2"],
        "1821" => ["section" => "PRINCE GEORGE", "type" => "child"],
        "2777" => ["section" => "PRINCE GEORGE", "type" => "life_member"],
        "3065" => ["section" => "PRINCE GEORGE", "type" => "student_club"],
        "1573" => ["section" => "SQUAMISH", "type" => "adult"],
        "1575" => ["section" => "SQUAMISH", "type" => "youth"],
        "1576" => ["section" => "SQUAMISH", "type" => "family1"],
        "1579" => ["section" => "SQUAMISH", "type" => "family2"],
        "1577" => ["section" => "SQUAMISH", "type" => "child"],
        "2782" => ["section" => "SQUAMISH", "type" => "life_member"],
        "1827" => ["section" => "VANCOUVER", "type" => "adult"],
        "1829" => ["section" => "VANCOUVER", "type" => "youth"],
        "1830" => ["section" => "VANCOUVER", "type" => "family1"],
        "1828" => ["section" => "VANCOUVER", "type" => "family2"],
        "1826" => ["section" => "VANCOUVER", "type" => "child"],
        "2326" => ["section" => "VANCOUVER", "type" => "student_club"],
        "2593" => ["section" => "VANCOUVER", "type" => "student_club"],
        "2786" => ["section" => "VANCOUVER", "type" => "life_member"],
        "1784" => ["section" => "VANCOUVER ISLAND", "type" => "adult"],
        "1783" => ["section" => "VANCOUVER ISLAND", "type" => "youth"],
        "1787" => ["section" => "VANCOUVER ISLAND", "type" => "family1"],
        "1785" => ["section" => "VANCOUVER ISLAND", "type" => "family2"],
        "1786" => ["section" => "VANCOUVER ISLAND", "type" => "child"],
        "2785" => ["section" => "VANCOUVER ISLAND", "type" => "life_member"],
        "2905" => ["section" => "VANCOUVER ISLAND", "type" => "student_club"],
        "1832" => ["section" => "WHISTLER", "type" => "adult"],
        "1834" => ["section" => "WHISTLER", "type" => "youth"],
        "1835" => ["section" => "WHISTLER", "type" => "family1"],
        "1833" => ["section" => "WHISTLER", "type" => "family2"],
        "1831" => ["section" => "WHISTLER", "type" => "child"],
        "2787" => ["section" => "WHISTLER", "type" => "life_member"],
        "1779" => ["section" => "CALGARY", "type" => "adult"],
        "1778" => ["section" => "CALGARY", "type" => "youth"],
        "1782" => ["section" => "CALGARY", "type" => "family1"],
        "1780" => ["section" => "CALGARY", "type" => "family2"],
        "1781" => ["section" => "CALGARY", "type" => "child"],
        "2762" => ["section" => "CALGARY", "type" => "life_member"],
        "2760" => ["section" => "CALGARY", "type" => "student_club"],
        "2768" => ["section" => "CALGARY", "type" => "student_club"],
        "1847" => ["section" => "CENTRAL ALBERTA ", "type" => "adult"],
        "1849" => ["section" => "CENTRAL ALBERTA ", "type" => "youth"],
        "1850" => ["section" => "CENTRAL ALBERTA ", "type" => "family1"],
        "1848" => ["section" => "CENTRAL ALBERTA ", "type" => "family2"],
        "1846" => ["section" => "CENTRAL ALBERTA ", "type" => "child"],
        "2763" => ["section" => "CENTRAL ALBERTA ", "type" => "life_member"],
        "1852" => ["section" => "EDMONTON", "type" => "adult"],
        "1854" => ["section" => "EDMONTON", "type" => "youth"],
        "1855" => ["section" => "EDMONTON", "type" => "family1"],
        "1853" => ["section" => "EDMONTON", "type" => "family2"],
        "1851" => ["section" => "EDMONTON", "type" => "child"],
        "2765" => ["section" => "EDMONTON", "type" => "life_member"],
        "2411" => ["section" => "EDMONTON", "type" => "student_club"],
        "2770" => ["section" => "EDMONTON", "type" => "student_club"],
        "1857" => ["section" => "JASPER / HINTON", "type" => "adult"],
        "1859" => ["section" => "JASPER / HINTON", "type" => "youth"],
        "1860" => ["section" => "JASPER / HINTON", "type" => "family1"],
        "1858" => ["section" => "JASPER / HINTON", "type" => "family2"],
        "1856" => ["section" => "JASPER / HINTON", "type" => "child"],
        "2767" => ["section" => "JASPER / HINTON", "type" => "life_member"],
        "1862" => ["section" => "ROCKY MOUNTAIN", "type" => "adult"],
        "1864" => ["section" => "ROCKY MOUNTAIN", "type" => "youth"],
        "1865" => ["section" => "ROCKY MOUNTAIN", "type" => "family1"],
        "1863" => ["section" => "ROCKY MOUNTAIN", "type" => "family2"],
        "1861" => ["section" => "ROCKY MOUNTAIN", "type" => "child"],
        "2443" => [
            "section" => "ROCKY MOUNTAIN",
            "type" => "acc_staff_individual",
        ],
        "2444" => ["section" => "ROCKY MOUNTAIN", "type" => "acc_staff_family"],
        "2778" => ["section" => "ROCKY MOUNTAIN", "type" => "life_member"],
        "1867" => ["section" => "SOUTHERN ALBERTA", "type" => "adult"],
        "1869" => ["section" => "SOUTHERN ALBERTA", "type" => "youth"],
        "1870" => ["section" => "SOUTHERN ALBERTA", "type" => "family1"],
        "1868" => ["section" => "SOUTHERN ALBERTA", "type" => "family2"],
        "1866" => ["section" => "SOUTHERN ALBERTA", "type" => "child"],
        "2781" => ["section" => "SOUTHERN ALBERTA", "type" => "life_member"],
        "2816" => ["section" => "SOUTHERN ALBERTA", "type" => "student_club"],
        "1872" => ["section" => "GREAT PLAINS", "type" => "adult"],
        "1874" => ["section" => "GREAT PLAINS", "type" => "youth"],
        "1875" => ["section" => "GREAT PLAINS", "type" => "family1"],
        "1873" => ["section" => "GREAT PLAINS", "type" => "family2"],
        "1871" => ["section" => "GREAT PLAINS", "type" => "child"],
        "2766" => ["section" => "GREAT PLAINS", "type" => "life_member"],
        "1877" => ["section" => "SASKATCHEWAN", "type" => "adult"],
        "1879" => ["section" => "SASKATCHEWAN", "type" => "youth"],
        "1880" => ["section" => "SASKATCHEWAN", "type" => "family1"],
        "1878" => ["section" => "SASKATCHEWAN", "type" => "family2"],
        "1876" => ["section" => "SASKATCHEWAN", "type" => "child"],
        "2780" => ["section" => "SASKATCHEWAN", "type" => "life_member"],
        "1882" => ["section" => "MANITOBA", "type" => "adult"],
        "1884" => ["section" => "MANITOBA", "type" => "youth"],
        "1885" => ["section" => "MANITOBA", "type" => "family1"],
        "1883" => ["section" => "MANITOBA", "type" => "family2"],
        "1881" => ["section" => "MANITOBA", "type" => "child"],
        "2771" => ["section" => "MANITOBA", "type" => "life_member"],
        "2895" => ["section" => "MANITOBA", "type" => "student_club"],
        "1887" => ["section" => "SAINT BONIFACE", "type" => "adult"],
        "1889" => ["section" => "SAINT BONIFACE", "type" => "youth"],
        "1890" => ["section" => "SAINT BONIFACE", "type" => "family1"],
        "1888" => ["section" => "SAINT BONIFACE", "type" => "family2"],
        "1886" => ["section" => "SAINT BONIFACE", "type" => "child"],
        "2779" => ["section" => "SAINT BONIFACE", "type" => "life_member"],
        "1892" => ["section" => "OTTAWA", "type" => "adult"],
        "1894" => ["section" => "OTTAWA", "type" => "youth"],
        "1895" => ["section" => "OTTAWA", "type" => "family1"],
        "1893" => ["section" => "OTTAWA", "type" => "family2"],
        "1891" => ["section" => "OTTAWA", "type" => "child"],
        "2775" => ["section" => "OTTAWA", "type" => "life_member"],
        "2896" => ["section" => "OTTAWA", "type" => "student_club"],
        "1897" => ["section" => "THUNDER BAY", "type" => "adult"],
        "1899" => ["section" => "THUNDER BAY", "type" => "youth"],
        "1900" => ["section" => "THUNDER BAY", "type" => "family1"],
        "1898" => ["section" => "THUNDER BAY", "type" => "family2"],
        "1896" => ["section" => "THUNDER BAY", "type" => "child"],
        "2783" => ["section" => "THUNDER BAY", "type" => "life_member"],
        "1902" => ["section" => "TORONTO", "type" => "adult"],
        "1904" => ["section" => "TORONTO", "type" => "youth"],
        "1905" => ["section" => "TORONTO", "type" => "family1"],
        "1903" => ["section" => "TORONTO", "type" => "family2"],
        "1901" => ["section" => "TORONTO", "type" => "child"],
        "2784" => ["section" => "TORONTO", "type" => "life_member"],
        "1837" => ["section" => "MONTRÉAL", "type" => "adult"],
        "1839" => ["section" => "MONTRÉAL", "type" => "youth"],
        "1840" => ["section" => "MONTRÉAL", "type" => "family1"],
        "1838" => ["section" => "MONTRÉAL", "type" => "family2"],
        "1836" => ["section" => "MONTRÉAL", "type" => "child"],
        "2772" => ["section" => "MONTRÉAL", "type" => "life_member"],
        "1842" => ["section" => "OUTAOUAIS", "type" => "adult"],
        "1844" => ["section" => "OUTAOUAIS", "type" => "youth"],
        "1845" => ["section" => "OUTAOUAIS", "type" => "family1"],
        "1843" => ["section" => "OUTAOUAIS", "type" => "family2"],
        "1841" => ["section" => "OUTAOUAIS", "type" => "child"],
        "2776" => ["section" => "OUTAOUAIS", "type" => "life_member"],
        "1907" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "adult"],
        "1909" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "youth"],
        "1910" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "family1"],
        "1908" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "family2"],
        "1906" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "child"],
        "2773" => [
            "section" => "NEWFOUNDLAND & LABRADOR",
            "type" => "life_member",
        ],
    ];

    // If a member has multiple memberships, which one do we prefer?
    // This is also used to resolve collisions when multiple members have
    // the same email address and we can insert only 1 in the WP database.
    private $membershipPreference = [
        "life_member" => 9,
        "family1" => 8,
        "family2" => 7,
        "adult" => 6,
        "acc_staff_individual" => 5,
        "acc_staff_family" => 4,
        "child" => 3,
        "youth" => 2,
        "student_club" => 1,
        ACC_UNKNOWN_MSHIP => 0,
    ];

    // Preference for membership status
    private $statusPreference = [
        "ISSU" => 3,
        "PROC" => 2,
        "EXP" => 1,
    ];

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        //load display
        require plugin_dir_path(__FILE__) .
            "/partials/acc_user_importer-admin-settings.php";
    }

    public function getMembershipTypeFromId($mId)
    {
        if (array_key_exists($mId, $this->membershipTable)) {
            return $this->membershipTable[$mId]["type"];
        }
        return "Unknown";
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
     * This is the user import loop (when triggered by a timer)
     */
    public function begin_automatic_update()
    {
        $this->begin_update("Automatic");
    }

    public function begin_update($mode)
    {
        $GLOBALS["acc_logstr"] = ""; //Clear the API response log string
        $logfilename = basename(acc_pick_new_log_file("log_auto_")); //Let's store to a new log
        accLog("$mode member update, logging to {$logfilename}");

        //force certificate validation - i.e. speed up authentication process
        add_filter("https_local_ssl_verify", "__return_true");

        //Get the list of sections to process
        $sections = accUM_get_enabled_sections();
        foreach ($sections as $section) {
            if (accUM_is_section_disabled($section)) {
                accLog("Skipping import for disabled $section ");
                continue;
            }

            accLog("Processing section $section");
            $timestamp_start = date_i18n("Y-m-d-H-i-s");

            // Take note of the ISO 8601 time of start
            $iso_timestamp_start = date("Y-m-d\TH:i:s\Z");
            //accLog("iso_timestamp_start={$iso_timestamp_start}");

            // Get the full list of changed members
            $api_response = $this->getChangedMembers($section);
            if ($api_response["message"] != "success") {
                accLog($this->responseErrMsg($api_response));
            } else {
                $done = 0;
                $changeList = $api_response["results"];
                $count = count($changeList);
                accLog("Received {$count} membership changes");

                // Loop for each changed membership
                while ($done < $count) {
                    $api_response = $this->getMemberData(
                        $section,
                        $changeList,
                        $done
                    );
                    if ($api_response["message"] != "success") {
                        accLog($this->responseErrMsg($api_response));
                        break;
                    } else {
                        //We have an array of membership information
                        $nextToDo = $api_response["nextDataOffset"];
                        $done = $nextToDo;
                        $memberArray = $api_response["results"];

                        $api_response = $this->proccess_user_data(
                            $section,
                            $memberArray
                        );
                        if ($api_response["message"] != "success") {
                            accLog($this->responseErrMsg($api_response));
                            break;
                        }

                        // Throttle requests to avoid HTTP errors.
                        if ($done < $count) {
                            sleep(SLEEP_TIME_AFTER_HTTP);
                        }
                    }
                }
            }

            accLog("");
        }

        // If import was a success, store the date/time where we last did it.
        // This will be used as the changed_since parameter in the next plugin run.
        if ($mode == "Automatic" && $api_response["message"] == "success") {
            accUM_set_since_date($iso_timestamp_start);
            accLog("On next run, use since={$iso_timestamp_start}");
        }

        // All sections have been successfully updated, now look for expired members
        $expiryResult = $this->local_db_check();

        $timestamp_end = date_i18n("Y-m-d-H-i-s");
        accLog("This journey has come to an end.");
        accLog("Start time: " . $timestamp_start);
        accLog("End time: " . $timestamp_end);
        accLog("\n\n");
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
                $this->begin_update("Manual");
        }

        //Return the log of the operation
        $api_response["log"] = $GLOBALS["acc_logstr"];

        //respond to ajax request and terminate
        echo json_encode($api_response);
        wp_die();
    }

    /**
     * Request the list of changed members from the national office API.
     * It calls the Changed Member API until it has the full list of members
     * with changed memberships.
     */
    private function getChangedMembers($section)
    {
        accLog("ACC User Importer version {$this->version}");
        $sectionApiId = acc_get_section_api_id($section);

        // There is a plugin setting to specify a list of users to sync.
        // If it contains something, then instead of asking 2M for a list of
        // members with changes, we take the user-provided list.
        $syncList = accUM_get_sync_list();
        if (!empty($syncList)) {
            accLog("Will sync the following list of users (as per settings:");
            accLog("$syncList");
            $changeList = explode(",", $syncList);
            $count = count($changeList);
            if ($count == 0 || in_array(0, $changeList)) {
                // Invalid list
                return $this->returnApiError(
                    "The plugin settings has an invalid list of members to sync"
                );
            }

            accLog("total count=" . $count);
            accLog("total members=" . json_encode($changeList));
            $api_response["count"] = $count;
            $api_response["results"] = $changeList;
            $api_response["message"] = "success";
            $api_response["log"] = $GLOBALS["acc_logstr"];
            return $api_response;
        }

        // Read token from user settings. Avoid printing token it is sensitive data
        $access_token = accUM_get_section_token($section);
        //accLog("Token=" . $access_token);
        if (is_null($access_token)) {
            $api_response["message"] = "error";
            $api_response["log"] = $GLOBALS["acc_logstr"];
            $api_response["errorMessage"] = "No valid token";
            return $api_response;
        }

        $sinceDate = accUM_get_since_date();
        if (!isset($sinceDate) || empty($sinceDate)) {
            // Looks like the plugin is running for the first time.
            // Use 2023-01-01, this should import all memberships.
            $sinceDate = "2023-01-01";
            accLog("No sinceDate specified, using {$sinceDate}");
        }

        accLog(
            "Retrieving changed members since {$sinceDate} " .
                "for section {$section}, API {$sectionApiId}"
        );

        // Create response object for local api
        $api_response = [];
        $count = 0;
        $changeList = [];
        $httpRequest =
            "https://2mev.com/rest/v2/member-apis/" .
            $sectionApiId .
            "/changed_members/?changed_since=" .
            $sinceDate;

        $get_args = [
            "headers" => [
                "content-type" => "application/json",
                "Authorization" => "Bearer " . $access_token,
            ],
        ];

        do {
            $currentTime = date_i18n("Y-m-d-H-i-s");
            accLog("Request sent @{$currentTime}: {$httpRequest}");
            $acc_response = wp_remote_get($httpRequest, $get_args);

            if (is_wp_error($acc_response)) {
                accLog(
                    "wp_remote_get error" . $acc_response->get_error_message()
                );
                $api_response["message"] = "error";
                $api_response["log"] = $GLOBALS["acc_logstr"];
                $api_response[
                    "errorMessage"
                ] = $acc_response->get_error_message();
                return $api_response;
            }

            $acc_response_data = wp_remote_retrieve_body($acc_response);
            //accLog("ACC response=" . $acc_response_data);
            $acc_response_data = json_decode($acc_response_data);

            $responseMsg = wp_remote_retrieve_response_message($acc_response);
            if ($responseMsg != "OK") {
                accLog("HTTP error={$responseMsg}");
                $api_response["message"] = "error";
                $api_response["errorMessage"] = "HTTP error={$responseMsg}";
                $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string
                return $api_response;
            }

            if (!isset($acc_response_data->count)) {
                $api_response["message"] = "error";
                $api_response["log"] = $GLOBALS["acc_logstr"];
                $api_response["errorMessage"] =
                    "No count in Changed Members API response";
                return $api_response;
            }

            // accLog("count=" . $acc_response_data->count);
            // accLog("next=" . $acc_response_data->next);
            // accLog("previous=" . $acc_response_data->previous);
            // accLog("members=" . json_encode($acc_response_data->results));
            $count += count($acc_response_data->results);
            $changeList = array_merge($changeList, $acc_response_data->results);
            //The server gives us a convenient string to access next page of data
            $httpRequest = $acc_response_data->next;

            // If we are going to send another API request, sleep a bit to
            // avoid the server to throttle (reject) our request.
            if ($acc_response_data->next != null) {
                sleep(SLEEP_TIME_AFTER_HTTP);
            }
        } while ($acc_response_data->next != null);

        //Validation step
        if ($acc_response_data->count != $count) {
            accLog("Warning, server said there would be {$acc_response_data->count}
			                entries but we actually received {$count}");
        }

        accLog("total count=" . $count);
        accLog("total members=" . json_encode($changeList));
        $api_response["count"] = $count;
        $api_response["results"] = $changeList;
        $api_response["message"] = "success";
        $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string
        return $api_response;
    }

    /**
     * Request a dataset from the national office API.
     * With retries because if we ask too quicky, the API returns error 429
     * (Too Many Requests). For example, 10 requests in 16 seconds seems too
     * fast for the server.
     *
     * Here is an example of the response from the Interpodia Member API
     *    [
     *        {
     *            "id": 240835,
     *            "first_name": "John",
     *            "last_name": "Doe",
     *            "email": "johndoe@hotmail.com",
     *            "date_of_birth": "1980-08-20",
     *            "member_number": "12345",
     *            "memberships": [
     *                {
     *                    "membership_group": {
     *                        "id": 1573,
     *                        "name": "Squamish Individual Membership (Adult) - Annual",
     *                        "group_group": {
     *                            "id": 546,
     *                            "name": "SQUAMISH SECTION - INDIVIDUAL MEMBERSHIP"
     *                        }
     *                    },
     *                    "valid_from": "2023-04-06",
     *                    "valid_to": "2024-04-04"
     *                }
     *            ],
     *            "identity_attribute_values": []
     *        }
     *    ]
     */
    private function getMemberData($section, $changeList, $offset = 0)
    {
        //create response object
        $api_response = [];

        //Compute how many members we want to process
        if (!$offset) {
            $offset = 0;
        }
        $remaining = sizeof($changeList) - $offset;
        $numToDo = min($remaining, MEMBER_API_MAX_USERS);
        accLog("remaining={$remaining}, will fetch {$numToDo}");

        // Select the next N entries from the list of changed members.
        $changeSubset = array_slice($changeList, $offset, $numToDo);
        $subsetString = implode(",", $changeSubset);

        $sectionApiId = acc_get_section_api_id($section);
        $httpRequest =
            "https://2mev.com/rest/v2/member-apis/{$sectionApiId}/fetch/?member_number=" .
            $subsetString;
        $access_token = accUM_get_section_token($section);

        $get_args = [
            "headers" => [
                "content-type" => "application/json",
                "Authorization" => "Bearer " . $access_token,
            ],
        ];

        $currentTime = date_i18n("Y-m-d-H-i-s");
        accLog("Request sent @{$currentTime}: {$httpRequest}");
        $acc_response = wp_remote_get($httpRequest, $get_args);

        //if the post request fails
        if (is_wp_error($acc_response)) {
            accLog("wp_remote_get error" . $acc_response->get_error_message());
            $api_response["message"] = "error";
            $api_response["errorMessage"] = $acc_response->get_error_message();
            $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string
            return $api_response;
        }

        $acc_response_data = wp_remote_retrieve_body($acc_response);
        $memberData = (array) json_decode($acc_response_data, true);
        $count = sizeof($memberData);
        //accLog("acc_response_data={$acc_response_data}");     //for debug only

        $responseCode = wp_remote_retrieve_response_code($acc_response);
        $responseMsg = wp_remote_retrieve_response_message($acc_response);
        // Here we could test for code 429 (sending data too fast to server who
        // rejects because of throttling). And if it happens, sleep for 60s and retry.
        // But I think we will no longer hit this issue thanks to the preventive
        // sleep after each request.
        if ($responseCode != 200) {
            accLog("HTTP error {$responseCode} ({$responseMsg})");
            $api_response["message"] = "error";
            $api_response[
                "errorMessage"
            ] = "HTTP error={$responseMsg}, code={$responseCode}";
            $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string
            return $api_response;
        }

        // We do not need this check. When a membership becomes expired, it will
        // be flagged as a change and we will receive the member_number, however
        // the Member API will not send any data for that member because he
        // is no longer part of our section and therefore we cannot access
        // his private info.  However we should probably add some handling
        // to terminate his membership. It is possible that on the Wordpress
        // database the user expiry is still in the future.
        // if ($count != $numToDo) {
        // 	accLog("Error, member API returned " . $count . " members instead of " . $numToDo);
        // 	$api_response['message'] = "error";
        // 	$api_response['errorMessage'] = "Member API returned " . $count . " members instead of " . $numToDo;
        // 	$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
        // 	return $api_response;
        // }

        $lastUser = $offset + $numToDo - 1;
        accLog("Received users $offset to $lastUser");

        $api_response["nextDataOffset"] = $offset + $numToDo;
        $api_response["results"] = $memberData;
        $api_response["message"] = "success";
        $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string
        return $api_response;
    }

    /**
     * Returns true if the type2 membership is preferred over the type1.
     * If the member has both a family and an adult membership, is it
     * preferable to chose the family membership because it has more privilege.
     */
    private function compareMembershipType($type1, $type2)
    {
        return $this->membershipPreference[$type2] >
            $this->membershipPreference[$type1];
    }

    /**
     * Return a preference score for the membership status.
     */
    private function statusScore($status)
    {
        if ($this->statusPreference[$status] !== null) {
            return $this->statusPreference[$status];
        }
        return 0;
    }

    /**
     * Returns true if the type2 membership is preferred (or equal) over the type1.
     * If the member has both a family and an adult membership, is it
     * preferable to chose the family membership because it has more privilege.
     * Order of Priority: Status, then expiry, then type
     */
    private function compareMembership(
        $id1,
        $expiry1,
        $status1,
        $id2,
        $expiry2,
        $status2
    ) {
        $type1 = $this->membershipTable[$id1]["type"];
        $type2 = $this->membershipTable[$id2]["type"];

        //accLog("In compareMembership $id1 $expiry1 $status1 $id2 $expiry2 $status2");

        if ($this->statusScore($status2) > $this->statusScore($status1)) {
            //accLog("In compareMembership status $status2 better than $status1");
            return true;
        } elseif ($this->statusScore($status2) < $this->statusScore($status1)) {
            //accLog("In compareMembership status $status2 worse than $status1");
            return false;
        }

        if ($expiry2 > $expiry1) {
            //accLog("In compareMembership expiry $expiry2 better than $expiry1");
            return true;
        } elseif ($expiry2 < $expiry1) {
            //accLog("In compareMembership expiry $expiry2 worse than $expiry1");
            return false;
        }

        if (
            $this->membershipPreference[$type2] >
            $this->membershipPreference[$type1]
        ) {
            // accLog("In compareMembership type " .
            //     $this->membershipPreference[$type2] . " better than ".
            //     $this->membershipPreference[$type1]);
            return true;
        } elseif (
            $this->membershipPreference[$type2] <
            $this->membershipPreference[$type1]
        ) {
            // accLog("In compareMembership type " .
            //     $this->membershipPreference[$type2] . " worse than ".
            //     $this->membershipPreference[$type1]);
            return false;
        }

        return true;
    }

    /**
     * Returns true if membership2 is better or equal.
     * Sometimes a user can have a adult and a family membership.
     * Suggestion: the 2nd parameter should be the existing user in
     * the database because if both 1 and 2 are equivalent,
     * we should avoid replacing unnecessarily the DB.
     */
    private function compareMemberships($memberships1, $memberships2)
    {
        //Step1: find the best membership in membership1
        $bestId = null;
        $bestValue = null;
        foreach ($memberships1 as $mId => $value) {
            if (!isset($bestId)) {
                $bestId = $mId;
                $bestValue = $value;
            } else {
                if (
                    $this->compareMembership(
                        $bestId,
                        $bestValue["expiry"],
                        $bestValue["status"],
                        $mId,
                        $value["expiry"],
                        $value["status"]
                    )
                ) {
                    $bestId = $mId;
                    $bestValue = $value;
                }
            }
        }

        if (!isset($bestId)) {
            accLog("Warning, compareMemberships found nothing");
            return true;
        }

        //Step2: See if membership2 has better
        foreach ($memberships2 as $mId => $value) {
            //accLog("In compareMemberships now comparing with $mId");
            if (
                $this->compareMembership(
                    $bestId,
                    $bestValue["expiry"],
                    $bestValue["status"],
                    $mId,
                    $value["expiry"],
                    $value["status"]
                )
            ) {
                //accLog("In compareMemberships $mId is best, return true");
                return true;
            }
        }

        return false;
    }

    /**
     * Update Wordpress database with member information.
     * This is where most of the work gets done.
     */
    private function proccess_user_data($section, $users)
    {
        //create response object
        $api_response = [];
        accLog("Start processing batch of " . count($users) . " users");

        //Return gracefully is dataset is empty
        if (!(count($users) > 0)) {
            accLog("Nothing to process");
            $api_response["message"] = "success";
            $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string
            return $api_response;
        }

        $new_user_role_action = accUM_get_new_user_role_action($section);
        $new_user_role_value = accUM_get_new_user_role_value($section);
        if ($new_user_role_action == "set_role") {
            accLog("New users will be set with role $new_user_role_value");
        } elseif ($new_user_role_action == "add_role") {
            accLog("New users will be added role $new_user_role_value");
        }

        $ex_user_role_action = accUM_get_ex_user_role_action($section);
        $ex_user_role_value = accUM_get_ex_user_role_value($section);
        if ($ex_user_role_action == "set_role") {
            accLog("Expired users will be set with role $ex_user_role_value");
        } elseif ($ex_user_role_action == "remove_role") {
            accLog("Expired users will be removed role $ex_user_role_value");
        }

        $loginNameMapping = accUM_get_login_name_mapping();
        accLog("Using $loginNameMapping as login name.");

        // Get the transitionFromContactID setting
        $transitionFromContactID = accUM_is_transitionFromContactID();
        accLog(
            "Usernames " .
                ($transitionFromContactID ? "" : "DO NOT ") .
                "transition from ContactID"
        );

        // Get the readonly_mode setting
        $readonly_mode = accUM_is_section_readonly($section);
        if ($readonly_mode) {
            accLog("Read-only test mode, will not update user database");
        }

        //loop through the received data and create users
        $update_errors = [];
        $new_users = [];
        $new_users_email = [];
        $updated_users = [];
        $updated_users_email = [];
        $new_active_users = [];
        $expired_users = [];
        $warnings = [];
        $errors = [];

        foreach ($users as $user) {
            $userFoundByEmail = false;
            //Avoid PHP warnings in case some fields are unpopulated
            //We are ignoring date of birth for now.
            $userFirstName = $user["first_name"] ?? "";
            $userLastName = $user["last_name"] ?? "";
            $userFullName = $userFirstName . " " . $userLastName;
            //$userContactId = $user['member_number'] ?? '';
            //FIXME what should we do with the new system 'id'?
            //Should we overwrite imis_id with this new field?
            //For now we just ignore it.
            //$userImisId = $user['imis_id'] ?? '';
            $userEmail = strtolower($user["email"] ?? "");
            //Note the 2M system only has 1 phone number per user.
            $userCellPhone = $user["phone_number"] ?? "";
            $userMemberNumber = $user["member_number"] ?? "";
            $receivedMemberships = $user["memberships"];
            accLog("");
            accLog(json_encode($user));

            //Log the info we received for this user
            $userInfoString = $userFirstName . " " . $userLastName;
            $userInfoString .= " " . $userEmail;
            //$userInfoString .= " ContactID:" . $userContactId;
            //$userInfoString .= " imis_id:" . $userImisId;
            $userInfoString .= " membership#:" . $userMemberNumber;
            $userInfoString .= " cell:" . $userCellPhone;
            accLog("Received " . $userInfoString);

            // It is possible for the user to have multiple memberships.
            // We are only interested in memberships for the section the plugin
            // is operating for. Here is roughly how the data is stored in the DB:
            // "acc_memberships"
            //     "outaouais" =>
            //         "1842" => ["expiry" => "2024-06-01",
            //                    "status" => "EXP"],
            //         "1845" => ["expiry" => "2025-06-15",
            //                    "status" => "ISSU"],
            //     "Montreal" =>
            //         "1840" => ["expiry" => "2024-06-01",
            //                    "status" => "EXP"],

            $userRxdMemberships = []; //Aggregate of user section memberships
            $userIsValid = false; //default init value

            foreach ($receivedMemberships as $membership) {
                //Sanity check received fields
                if (
                    !isset($membership["membership_group"]) ||
                    !isset($membership["membership_group"]["id"]) ||
                    !isset($membership["valid_to"]) ||
                    !isset($membership["identity_membership_status"])
                ) {
                    accLog("Error, missing fields in rxd data");
                    $errors[] = "Error, missing fields in rxd data";
                    continue;
                }
                $mId = $membership["membership_group"]["id"];
                if (!is_int($mId)) {
                    accLog("Error, rxd membership ID not a number!");
                    $errors[] = "Error, rxd mship ID not a number for $userFullName";
                    continue;
                }
                if (array_key_exists($mId, $this->membershipTable)) {
                    $mSection = $this->membershipTable[$mId]["section"];
                    $mType = $this->membershipTable[$mId]["type"];
                } else {
                    //No such ID in the membershipTable table.
                    //Table is probably outdated. For error handling
                    //assume that the section is the right one and pick
                    //type=unknown and keep going to allow the member.
                    accLog(
                        "Error, unknown rxd membership ID. " .
                            "Maybe the plugin needs updating?"
                    );
                    $errors[] = "Error, unknown mship ID for $userFullName";
                    $mSection = $section;
                    $mType = ACC_UNKNOWN_MSHIP;
                }

                $mStatus = $membership["identity_membership_status"];
                // Keep the YYYY-MM-DD, but truncate the time portion if it was there.
                $mExpiry = substr($membership["valid_to"], 0, 10);
                if ($mSection != $section) {
                    continue; //This could indicate an API error?
                }

                accLog(
                    ">   ID:$mId section:$mSection type:$mType " .
                        "expiry:$mExpiry status:$mStatus"
                );

                // Issue harmless warnings if we see the 2mev API returned discrepancies
                $membershipExpired = $mExpiry < date("Y-m-d");
                $membershipValid = acc_validMembershipStatus($mStatus);
                if ($membershipValid) {
                    $userIsValid = true; //Take note that this user is valid
                }
                if ($membershipExpired && $membershipValid) {
                    //Most of the time this is not a real warning. A user may have
                    //multiple memberships, some having expired dates, but the
                    //status represents the global state of the member and
                    //as long as one membership is OK, 2M sends status=valid
                    //for all memberships the user has.
                    // accLog(
                    //     "> Warning, data discrepancy: " .
                    //         "membership expired but status is good!"
                    // );
                    // $warnings[] =
                    //     "Warning, rxd data discrepancy for $userFullName: " .
                    //     "membership date expired but status is good!";
                } elseif (!$membershipExpired && !$membershipValid) {
                    accLog(
                        "> Warning, data discrepancy: " .
                            "membership date OK but status is not!"
                    );
                    $warnings[] =
                        "Warning, rxd data discrepancy for $userFullName: " .
                        "membership date OK but status is not!";
                }

                //Aggregate rxd info into a structure similar as in DB
                $userRxdMemberships[$mId] = [
                    "expiry" => $mExpiry,
                    "status" => $mStatus,
                ];
            }

            //Safety that we received at least 1 membership for this user
            if (!isset($userRxdMemberships)) {
                $errors[] = "Error, No mship rcvd for $userFullName";
                accLog("> No membership received; skip");
                continue;
            }

            //Validate we have received mandatory fields.
            if (empty($userMemberNumber)) {
                $errors[] = "Error, No member number rcvd for $userFullName";
                accLog(" > error, no member number; skip");
                continue;
            }

            //Safety check in case firstname and lastname are empty
            if (empty($userFirstName) && empty($userLastName)) {
                $errors[] = "Error, user $userMemberNumber has no name";
                accLog(" > error, user has no name; skip");
                continue;
            }

            switch ($loginNameMapping) {
                case "Firstname Lastname":
                    $loginName = "$userFirstName $userLastName";
                    break;
                case "member_number":
                default:
                    $loginName = sanitize_user($userMemberNumber);
                    break;
            }

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
                "cell_phone" => $userCellPhone,
                "membership" => $userMemberNumber,
                "nickname" => $userFirstName . " " . $userLastName,
                "acc_memberships" => [], //To fill later
            ];

            // Check if ID or email already exist. Both should be unique
            $existingUser = get_user_by("login", $loginName);

            // TEMPORARY CODE TO HELP VANCOUVER SECTION TRANSITION TO 2M PLATFORM
            // Vancouver needs to transition usernames from ContactID to 2M member_number.
            // The 'Set username to' setting will be set to member_number. During import,
            // the plugin will try to get users by loginName set to member_number
            // but the username in the DB will initially set to ContactID.
            // Since those 2 number spaces are not distinct, it might happen that
            // a user member_number is the same as someone else ContactID. And so,
            // the first time the plugin operates on the DB, it could match the wrong
            // user.  Add an extra step and make sure that the user name is the right one
            // to ensure we found the right user.  This setting can be left checked
            // for a few days after transition with no harm, except that a user
            // would not be able to change his name and email (at least not at the same time).
            // So once the plugin has done the full import of the membership and all usernames
            // have transitioned to member_number, the accUM_transition_from_contactID
            // setting should be unchecked.  And eventually this piece of code
            // (7 lines)should be removed.
            if (
                is_a($existingUser, WP_User::class) &&
                $transitionFromContactID
            ) {
                // Check if we have a match with either display name or email. If one matches, we assume the user is the same, but the member number changed.
                if (
                    !(
                        $accUserData["display_name"] ==
                            $existingUser->display_name ||
                        $accUserData["user_email"] == $existingUser->user_email
                    )
                ) {
                    accLog(
                        " > error (transition from ContactID): looks like " .
                            "we have a duplicate member number ({$existingUser->display_name}, skipping "
                    );
                    continue;
                }
            }

            if (!is_a($existingUser, WP_User::class)) {
                accLog(" > not found by login");
                //Not found by login, search by email
                $existingUser = get_user_by("email", $userEmail);
                if (is_a($existingUser, WP_User::class)) {
                    //We found a user. If the name is the same, update it.
                    //If the name is different, update the existing account if
                    //the incoming registration is a preferred one.
                    // In a family account, often the childs and partner have the
                    // same email address. We might have received the child record
                    // first and already created the account. If so, overwrite it
                    // with the parent (owner of email) information.
                    // If the name is different but membership type the same,
                    // do not overwrite the existing record.
                    // Note: another approach to avoid this complex code could be
                    // to reverse the processing order of the received user array.
                    // This way we would process older records first, and it
                    // seems we would receive the membership of adult1 first,
                    // then adult2, then childs. So naturally the owner of the
                    // account would be the first to be created.
                    $userFoundByEmail = true;
                    accLog(
                        " > found by email existing userId {$existingUser->ID} named " .
                            "{$existingUser->display_name}. Collision!"
                    );
                    //Collision, and names are different
                    if (
                        !isset($existingUser->acc_memberships) ||
                        $this->compareMemberships(
                            $userRxdMemberships,
                            $existingUser->acc_memberships[$section]
                        )
                    ) {
                        //Existing user is better, keep it.
                        accLog(" > email already used by someone else, skip");
                        continue;
                    }
                }
            }

            $updatedFields = [];

            // Existing user, check if any fields were updated
            if (is_a($existingUser, WP_User::class)) {
                //---------USER WAS FOUND IN DATABASE------------
                $userID = $existingUser->ID;
                accLog(
                    " > checking " .
                        $existingUser->display_name .
                        " (user #" .
                        $userID .
                        ")"
                );

                $userWasExpired = acc_is_user_expired($existingUser);

                //We received memberships for one section. We have to merge
                //this information with the existing user memberships,
                //preserving memberships from other sections.
                if (!isset($existingUser->acc_memberships)) {
                    //This field does not exist yet in the user meta.
                    $accUserMetaData["acc_memberships"] = [
                        $section => $userRxdMemberships,
                    ];
                } else {
                    $accUserMetaData["acc_memberships"] =
                        $existingUser->acc_memberships;
                    $accUserMetaData["acc_memberships"][
                        $section
                    ] = $userRxdMemberships;
                }

                // Check which fields might have changed. On purpose we dont want to check nicename.
                foreach (
                    array_merge($accUserData, $accUserMetaData)
                    as $field => $value
                ) {
                    if ($value != $existingUser->$field) {
                        if (is_array($value)) {
                            //To conveniently print the change in an array,
                            //we serialize and print the string.
                            $old = serialize($existingUser->$field);
                            $new = serialize($value);
                            accLog(" > $field changed from " . "$old to $new");
                        } else {
                            accLog(
                                " > $field changed from " .
                                    $existingUser->$field .
                                    " to $value"
                            );
                        }
                        $existingUser->$field = $value;
                        $updatedFields[] = $field;
                    }
                }

                // If fields changed, then update the user in the database.
                if (!$readonly_mode) {
                    if (!empty($updatedFields)) {
                        // Passing in the $existingUser object with the updated values will persist to the database.
                        $updateResp = wp_update_user($existingUser);
                        if (is_wp_error($updateResp)) {
                            $errors[] = "Error updating user $userFullName in DB";
                            accLog(" > error, failed to update user");
                            accLog(" > WP:" . $updateResp->get_error_message());
                            continue;
                        }
                        accLog(" > updated user #" . $updateResp);

                        //Update meta fields
                        foreach ($accUserMetaData as $field => $value) {
                            if (in_array($field, $updatedFields)) {
                                update_user_meta($userID, $field, $value);
                            }
                        }

                        $updated_users[] = $accUserData["display_name"];
                        $updated_users_email[] = $userEmail;
                    }

                    //Special code is needed to handle a login_name change. wp_update_user()
                    //does not change the login_name or the user_nicename.  This is not
                    //something we want to happen often. But it will happen in the case
                    //where a child record is received before the parent, with the same
                    //email address. Another solution to that would be a pre-processing
                    //step where we re-order the array of incoming registrations,
                    //so that the parent records are received first.
                    if ($loginName != $existingUser->user_login) {
                        global $wpdb;
                        $result = $wpdb->update(
                            $wpdb->users,
                            ["user_login" => $loginName],
                            ["ID" => $userID]
                        );
                        if ($result === false) {
                            $result_str = " failed";
                            $errors[] = "Error changing loginName for $userFullName";
                        } else {
                            $result_str = " success";
                        }
                        accLog(
                            "> user {$userID} username changed from " .
                                "{$existingUser->user_login} to {$loginName}, update database $result_str"
                        );
                        //Erase user cache so that future access gets the right data.
                        clean_user_cache($userID);
                    }

                    //Get updated status of user
                    $userIsExpired = acc_is_user_expired($existingUser);

                    // Check for actions (ex: send welcome or goodbye email)
                    if ($userWasExpired && !$userIsExpired) {
                        // Trigger hook if expiry date changed (updated membership)
                        do_action("acc_membership_renewal", $userID);
                        $this->takeActionOnNewUser($section, $userID);
                        $new_active_users[] = "$existingUser->display_name  ($existingUser->user_email)";
                    } elseif (!$userWasExpired && $userIsExpired) {
                        $this->takeActionOnExpiredUser($section, $userID);
                        $expired_users[] = "$existingUser->display_name  ($existingUser->user_email)";
                    }
                }

                // All done with updating the user
                continue;
            }

            //--------USER NOT FOUND IN DATABASE-----
            // But before creating a new record, make sure email is valid.
            if (!is_email($userEmail)) {
                //User has no email field, skip it
                accLog(" > error: invalid email, cant create new account.");
                $update_errors[] = $user;
                $errors[] = "Error $userFullName has invalid email";
                continue;
            }

            if ($readonly_mode) {
                // Skip the rest if we are in read-only test mode
                continue;
            }

            if (!$userIsValid) {
                // Skip the rest if the user is expired already
                accLog("> Expired membership, dont create account");
                continue;
            }

            //--------CREATE NEW USER-----
            accLog(" > email not found on any other users");
            $new_users[] = $accUserData["display_name"];
            $new_users_email[] = $userEmail;
            $accUserData["user_pass"] = wp_generate_password(20);
            $accUserData["user_nicename"] = $accUserData["display_name"]; //WP will sanitize
            $accUserData["user_login"] = $loginName;

            //Pick the member role (if configured to do so)
            $new_user_role_action = accUM_get_new_user_role_action($section);
            $new_user_role_value = accUM_get_new_user_role_value($section);
            if (
                $new_user_role_action == "set_role" ||
                $new_user_role_action == "add_role"
            ) {
                $accUserData["role"] = $new_user_role_value;
                accLog("> setting role to $new_user_role_value");
            }

            // Insert new user
            $userID = wp_insert_user($accUserData);
            if (is_wp_error($userID)) {
                $errors[] = "Error creating user $userFullName";
                accLog(" > error, failed to create user");
                accLog(" > WP:" . $userID->get_error_message());
                continue;
            }

            accLog(" > Created new user #" . $userID);

            //Insert meta fields.
            $accUserMetaData["acc_memberships"] = [
                $section => $userRxdMemberships,
            ];
            foreach ($accUserMetaData as $field => $value) {
                update_user_meta($userID, $field, $value);
            }

            // Execute hooks for new membership
            do_action("acc_new_membership", $userID);

            $this->takeActionOnNewUser($section, $userID);
            $new_active_users[] = "{$accUserData["display_name"]} ({$accUserData["user_email"]})";
        } //end user loop

        //Outcome summary
        accLog("");
        accLog(
            "Processing complete for this batch of " .
                count($users) .
                " people."
        );
        accLog("--" . count($new_users) . " accounts created:");
        foreach ($new_users as $id => $user) {
            accLog("  " . $user . " (" . $new_users_email[$id] . ")");
        }
        accLog("--" . count($updated_users) . " accounts updated:");
        foreach ($updated_users as $id => $user) {
            accLog("  " . $user . " (" . $updated_users_email[$id] . ")");
        }
        accLog(
            "--" . count($new_active_users) . " members transitioned to Active:"
        );
        foreach ($new_active_users as $user) {
            accLog("  " . $user);
        }
        accLog("--" . count($expired_users) . " members Expired:");
        foreach ($expired_users as $user) {
            accLog("  " . $user);
        }
        if (count($update_errors) != 0) {
            accLog("--Errors updating " . count($update_errors) . " accounts:");
        }
        foreach ($update_errors as $id => $user) {
            accLog(" [" . $id . "] " . var_export($user, true));
        }

        $operation = "The ACC website received the following changes for $section:";
        $this->send_admin_email(
            $operation,
            $new_active_users,
            $expired_users,
            [], //No deleted accounts
            $warnings,
            $errors
        );

        $api_response["usersInData"] = count($users);
        $api_response["newUsers"] = count($new_users);
        $api_response["updatedUsers"] =
            count($updated_users) - count($update_errors);
        $api_response["usersWithErrors"] = count($update_errors);
        $api_response["message"] = "success";
        $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string

        return $api_response;
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
            "> user $user->ID $user->display_name transitioned to " .
            "active, send welcome email if enabled";
        accLog($message);
        acc_send_welcome_email($section, $user->ID);
        do_action("acc_member_welcome", $user->ID); //action hook

        // If needed, change the user role to the new member role.
        $user_roles = $user->roles;
        if (
            $new_user_role_action == "set_role" &&
            (count($user_roles) != 1 ||
                !in_array($new_user_role_value, $user_roles, true))
        ) {
            accLog(
                "> Changing user $user->ID $user->display_name role to $new_user_role_value"
            );
            $user->set_role($new_user_role_value);
        } elseif (
            $new_user_role_action == "add_role" &&
            !in_array($new_user_role_value, $user_roles, true)
        ) {
            accLog(
                "> Adding role $new_user_role_value to user $user->ID $user->display_name"
            );
            $user->add_role($new_user_role_value);
        }
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
            accLog(
                "Error when checking for user state, userid $user_id is invalid"
            );
            return;
        }

        accLog(
            "> user $user->ID $user->display_name transitioned to " .
                "inactive, send goodbye email if enabled"
        );
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
            accLog(
                "> Changing user $user->ID $user->display_name role to $ex_user_role_value"
            );
            $user->set_role($ex_user_role_value);
        } elseif (
            $ex_user_role_action == "remove_role" &&
            !in_array("administrator", $user_roles, true) &&
            in_array($ex_user_role_value, $user_roles, true)
        ) {
            accLog(
                "> Removing role $ex_user_role_value from user $user->ID $user->display_name"
            );
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

            $best_expiry = acc_MembershipLatestDate($user);
            if (!empty($best_expiry)) {
                $expiry = new DateTime($best_expiry);
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
    private function local_db_check()
    {
        $api_response = []; //create response object

        $verify_expiry = accUM_is_verify_expiry();
        if ($verify_expiry) {
            accLog("");
            accLog("=============================================");
            accLog("Checking local DB, as stated in configuration");
            accLog("=============================================");
        } else {
            accLog("Skipping local DB sanity check, as per configuration");
            $api_response["message"] = "success";
            $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string
            return $api_response;
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
            $expiry = acc_MembershipLatestDate($user);
            if (empty($expiry)) {
                $warnings[] = "$user->display_name ($user->user_login, $user->user_email) has no membership expiry!";
            } elseif ($expiry > $more_than_a_year_from_now) {
                $warnings[] = "$user->display_name ($user->user_login, $user->user_email) has expiry=$expiry!";
            }

            //Give warning for users having a 'PROC' membership status
            if (acc_MembershipIsProc($user)) {
                $warnings[] = "$user->display_name ($user->user_login, $user->user_email) has membership in PROC state";
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
        accLog("Number of members in PROC state = $num_processing");
        foreach ($warnings as $warning) {
            accLog("Warning: $warning");
        }
        accLog(
            "<br>List of users email in PROC state = $processing_email_list<br>"
        );

        $operation =
            "The ACC web site local DB check made the following changes:";
        $this->send_admin_email(
            $operation,
            [], //No new users
            [], //No expired users
            $deleted_users,
            $warnings,
            $errors
        );

        $api_response["message"] = "success";
        $api_response["log"] = $GLOBALS["acc_logstr"]; //Return the big log string

        return $api_response;
    }

    /**
     * If the option is configured, send a summary email to the admin.
     * The email is only sent if needed (there were new users, expired users,
     * deleted accounts or warnings).
     * There is no checking done to ensure the notification email addresses are valid.
     */
    private function send_admin_email(
        $operation,
        $new_users,
        $expired_users,
        $deleted_users,
        $warnings,
        $errors
    ) {
        $email_addrs = accUM_get_notification_emails();
        if (
            !empty($email_addrs) &&
            (!empty($new_users) ||
                !empty($expired_users) ||
                !empty($errors) ||
                !empty($deleted_users))
        ) {
            $title = accUM_get_notification_title();
            $content = $operation . "\n\n";
            $content .= "---new members---\n";
            foreach ($new_users as $user) {
                $content .= $user . "\n";
            }
            $content .= "\n---expired members---\n";
            foreach ($expired_users as $user) {
                $content .= $user . "\n";
            }
            $content .= "\n---deleted obsolete members---\n";
            foreach ($deleted_users as $user) {
                $content .= $user . "\n";
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
     * Add acc_membership information to the user profile page
     */
    public function display_acc_memberships($user)
    {
        $acc_memberships = get_user_meta($user->ID, "acc_memberships", true);
        $mshipText = "";
        foreach ($acc_memberships as $section => $memberships) {
            $mshipText .= "$section\n";
            foreach ($memberships as $mId => $fields) {
                $type = $this->getMembershipTypeFromId($mId);
                $mshipText .= "  $type: ";
                foreach ($fields as $key => $value) {
                    $mshipText .= "  $key:$value ";
                }
                $mshipText .= "\n";
            }
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="acc_memberships">Memberships</label></th>
                <td>
                    <textarea id="acc_memberships" name="acc_memberships" rows="5" cols="30"><?php echo esc_textarea(
                        $mshipText
                    ); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }
}
