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
        $oldVersion = $this->get_old_plugin_version_from_db();
        if (
            $oldVersion === "unknown" ||
            ($oldVersion >= "3.0.0" && $oldVersion < "4.0.0")
        ) {
            $this->process_upgrade($oldVersion);
        }

        acc_cron_activate();
    }

    // Check version number of plugin which ran last.
    // Starting in v2.1.0, we store the version of the plugin in the database,
    // and this info is available when we activate to see if any upgrade
    // or downgrade work is needed.
    private function get_old_plugin_version_from_db()
    {
        $options = get_option(ACCUM_DATA);
        if (!empty($options["accUM_plugin_version"])) {
            $oldVersion = $options["accUM_plugin_version"];
        } else {
            $oldVersion = "unknown";
        }
        return $oldVersion;
    }

    // Write the current plugin version in the DB.
    private function write_new_plugin_version_to_db($log)
    {
        $options = get_option(ACCUM_DATA);
        $options["accUM_plugin_version"] = $this->version;
        update_option(ACCUM_DATA, $options);
        error_log("Updated version# to $this->version in DB\n", 3, $log);
    }

    /**
     * Upon activation, there might be some upgrade/downgrade cleanup work.
     */
    private function process_upgrade($oldVersion)
    {
        // Some upgrade work has to be done
        $log = acc_pick_new_log_file("_upgrade");
        error_log(
            "Upgrade from $oldVersion to $this->version needed\n",
            3,
            $log
        );
        $this->convert_settings($log);
        $this->scan_user_db_and_upgrade($log);
        $this->write_new_plugin_version_to_db($log);
        error_log("Upgrade done\n", 3, $log);
    }

    /**
     * Read accUM_data and make adjusments as required.
     * No changes required to the per-section settings.
     */
    private function convert_settings($log)
    {
        error_log("Converting Settings\n", 3, $log);

        // Delete some historical options no longer used
        delete_option("acc_expiry_lvl_1");
        delete_option("acc_expiry_lvl_2");
        delete_option("acc_role_editor");
        delete_option("accUM_gen");

        $options = get_option(ACCUM_DATA);

        // For enabled sections, translate sect name to new format.
        if (isset($options) && isset($options["enabled_sections"])) {
            $enabledSect = $options["enabled_sections"];
            foreach ($enabledSect as $sect => $on) {
                $sectNewName = $this->convertSectionName($sect);
                unset($options["enabled_sections"][$sect]);
                $options["enabled_sections"][$sectNewName] = $on;
            }
        }

        //Delete since_date
        if (isset($options["since_date"])) {
            unset($options["since_date"]);
            error_log("Deleted since_date\n", 3, $log);
        }

        //Delete sync_list
        if (isset($options["sync_list"])) {
            unset($options["sync_list"]);
            error_log("Deleted sync_list\n", 3, $log);
        }

        update_option(ACCUM_DATA, $options);

        // Seek for section specific options and rewrite with new section name
        $oldSections = $this->oldSectionNames();
        foreach ($oldSections as $oldName) {
            $newSectionName = $this->convertSectionName($oldName);
            if ($newSectionName == $oldName) {
                continue;
            }

            $candidateOptionName = ACCUM_SEC . $oldName;
            $newOptionName = ACCUM_SEC . $newSectionName;
            $sectionOption = get_option($candidateOptionName);
            if (false !== $sectionOption) {
                // There are some settings for this section
                delete_option($candidateOptionName);
                update_option($newOptionName, $sectionOption);
            }
        }
    }

    // If a member has multiple memberships, which one do we prefer?
    // This is also used to resolve collisions when multiple members have
    // the same email address and we can insert only 1 in the WP database.
    private $membershipPref = [
        "Honorary" => 7,
        "Lifetime" => 6,
        "Family" => 5,
        "ACC Staff" => 4,
        "Individual" => 3,
        "Youth" => 2,
        "Student" => 1,
        ACC_UNKNOWN_MSHIP => 0,
    ];

    // List of ACC section membership types.
    // Obtained from an Interpodia Excel spreadsheet.
    private $membershipTable = [
        "1807" => ["section" => "YUKON", "type" => "Individual"],
        "1809" => ["section" => "YUKON", "type" => "Youth"],
        "1810" => ["section" => "YUKON", "type" => "Family"],
        "1808" => ["section" => "YUKON", "type" => "Family"],
        "1806" => ["section" => "YUKON", "type" => "Family"],
        "2788" => ["section" => "YUKON", "type" => "Lifetime"],
        "1918" => ["section" => "BUGABOOS", "type" => "Individual"],
        "1920" => ["section" => "BUGABOOS", "type" => "Youth"],
        "1921" => ["section" => "BUGABOOS", "type" => "Family"],
        "1919" => ["section" => "BUGABOOS", "type" => "Family"],
        "1917" => ["section" => "BUGABOOS", "type" => "Family"],
        "2761" => ["section" => "BUGABOOS", "type" => "Lifetime"],
        "1812" => ["section" => "COLUMBIA MOUNTAINS", "type" => "Individual"],
        "1814" => ["section" => "COLUMBIA MOUNTAINS", "type" => "Youth"],
        "1815" => ["section" => "COLUMBIA MOUNTAINS", "type" => "Family"],
        "1813" => ["section" => "COLUMBIA MOUNTAINS", "type" => "Family"],
        "1811" => ["section" => "COLUMBIA MOUNTAINS", "type" => "Family"],
        "2764" => ["section" => "COLUMBIA MOUNTAINS", "type" => "Lifetime"],
        "1817" => ["section" => "OKANAGAN", "type" => "Individual"],
        "1819" => ["section" => "OKANAGAN", "type" => "Youth"],
        "1820" => ["section" => "OKANAGAN", "type" => "Family"],
        "1818" => ["section" => "OKANAGAN", "type" => "Family"],
        "1816" => ["section" => "OKANAGAN", "type" => "Family"],
        "2774" => ["section" => "OKANAGAN", "type" => "Lifetime"],
        "2348" => ["section" => "OKANAGAN", "type" => "Student"],
        "2769" => ["section" => "OKANAGAN", "type" => "Student"],
        "1822" => ["section" => "PRINCE GEORGE", "type" => "Individual"],
        "1824" => ["section" => "PRINCE GEORGE", "type" => "Youth"],
        "1825" => ["section" => "PRINCE GEORGE", "type" => "Family"],
        "1823" => ["section" => "PRINCE GEORGE", "type" => "Family"],
        "1821" => ["section" => "PRINCE GEORGE", "type" => "Family"],
        "2777" => ["section" => "PRINCE GEORGE", "type" => "Lifetime"],
        "3065" => ["section" => "PRINCE GEORGE", "type" => "Student"],
        "1573" => ["section" => "SQUAMISH", "type" => "Individual"],
        "1575" => ["section" => "SQUAMISH", "type" => "Youth"],
        "1576" => ["section" => "SQUAMISH", "type" => "Family"],
        "1579" => ["section" => "SQUAMISH", "type" => "Family"],
        "1577" => ["section" => "SQUAMISH", "type" => "Family"],
        "2782" => ["section" => "SQUAMISH", "type" => "Lifetime"],
        "1827" => ["section" => "VANCOUVER", "type" => "Individual"],
        "1829" => ["section" => "VANCOUVER", "type" => "Youth"],
        "1830" => ["section" => "VANCOUVER", "type" => "Family"],
        "1828" => ["section" => "VANCOUVER", "type" => "Family"],
        "1826" => ["section" => "VANCOUVER", "type" => "Family"],
        "2326" => ["section" => "VANCOUVER", "type" => "Student"],
        "2593" => ["section" => "VANCOUVER", "type" => "Student"],
        "2786" => ["section" => "VANCOUVER", "type" => "Lifetime"],
        "1784" => ["section" => "VANCOUVER ISLAND", "type" => "Individual"],
        "1783" => ["section" => "VANCOUVER ISLAND", "type" => "Youth"],
        "1787" => ["section" => "VANCOUVER ISLAND", "type" => "Family"],
        "1785" => ["section" => "VANCOUVER ISLAND", "type" => "Family"],
        "1786" => ["section" => "VANCOUVER ISLAND", "type" => "Family"],
        "2785" => ["section" => "VANCOUVER ISLAND", "type" => "Lifetime"],
        "2905" => ["section" => "VANCOUVER ISLAND", "type" => "Student"],
        "1832" => ["section" => "WHISTLER", "type" => "Individual"],
        "1834" => ["section" => "WHISTLER", "type" => "Youth"],
        "1835" => ["section" => "WHISTLER", "type" => "Family"],
        "1833" => ["section" => "WHISTLER", "type" => "Family"],
        "1831" => ["section" => "WHISTLER", "type" => "Family"],
        "2787" => ["section" => "WHISTLER", "type" => "Lifetime"],
        "1779" => ["section" => "CALGARY", "type" => "Individual"],
        "1778" => ["section" => "CALGARY", "type" => "Youth"],
        "1782" => ["section" => "CALGARY", "type" => "Family"],
        "1780" => ["section" => "CALGARY", "type" => "Family"],
        "1781" => ["section" => "CALGARY", "type" => "Family"],
        "2762" => ["section" => "CALGARY", "type" => "Lifetime"],
        "2760" => ["section" => "CALGARY", "type" => "Student"],
        "2768" => ["section" => "CALGARY", "type" => "Student"],
        "1847" => ["section" => "CENTRAL ALBERTA ", "type" => "Individual"],
        "1849" => ["section" => "CENTRAL ALBERTA ", "type" => "Youth"],
        "1850" => ["section" => "CENTRAL ALBERTA ", "type" => "Family"],
        "1848" => ["section" => "CENTRAL ALBERTA ", "type" => "Family"],
        "1846" => ["section" => "CENTRAL ALBERTA ", "type" => "Family"],
        "2763" => ["section" => "CENTRAL ALBERTA ", "type" => "Lifetime"],
        "1852" => ["section" => "EDMONTON", "type" => "Individual"],
        "1854" => ["section" => "EDMONTON", "type" => "Youth"],
        "1855" => ["section" => "EDMONTON", "type" => "Family"],
        "1853" => ["section" => "EDMONTON", "type" => "Family"],
        "1851" => ["section" => "EDMONTON", "type" => "Family"],
        "2765" => ["section" => "EDMONTON", "type" => "Lifetime"],
        "2411" => ["section" => "EDMONTON", "type" => "Student"],
        "2770" => ["section" => "EDMONTON", "type" => "Student"],
        "1857" => ["section" => "JASPER / HINTON", "type" => "Individual"],
        "1859" => ["section" => "JASPER / HINTON", "type" => "Youth"],
        "1860" => ["section" => "JASPER / HINTON", "type" => "Family"],
        "1858" => ["section" => "JASPER / HINTON", "type" => "Family"],
        "1856" => ["section" => "JASPER / HINTON", "type" => "Family"],
        "2767" => ["section" => "JASPER / HINTON", "type" => "Lifetime"],
        "1862" => ["section" => "ROCKY MOUNTAIN", "type" => "Individual"],
        "1864" => ["section" => "ROCKY MOUNTAIN", "type" => "Youth"],
        "1865" => ["section" => "ROCKY MOUNTAIN", "type" => "Family"],
        "1863" => ["section" => "ROCKY MOUNTAIN", "type" => "Family"],
        "1861" => ["section" => "ROCKY MOUNTAIN", "type" => "Family"],
        "2443" => [
            "section" => "ROCKY MOUNTAIN",
            "type" => "ACC Staff",
        ],
        "2444" => ["section" => "ROCKY MOUNTAIN", "type" => "ACC Staff"],
        "2778" => ["section" => "ROCKY MOUNTAIN", "type" => "Lifetime"],
        "1867" => ["section" => "SOUTHERN ALBERTA", "type" => "Individual"],
        "1869" => ["section" => "SOUTHERN ALBERTA", "type" => "Youth"],
        "1870" => ["section" => "SOUTHERN ALBERTA", "type" => "Family"],
        "1868" => ["section" => "SOUTHERN ALBERTA", "type" => "Family"],
        "1866" => ["section" => "SOUTHERN ALBERTA", "type" => "Family"],
        "2781" => ["section" => "SOUTHERN ALBERTA", "type" => "Lifetime"],
        "2816" => ["section" => "SOUTHERN ALBERTA", "type" => "Student"],
        "1872" => ["section" => "GREAT PLAINS", "type" => "Individual"],
        "1874" => ["section" => "GREAT PLAINS", "type" => "Youth"],
        "1875" => ["section" => "GREAT PLAINS", "type" => "Family"],
        "1873" => ["section" => "GREAT PLAINS", "type" => "Family"],
        "1871" => ["section" => "GREAT PLAINS", "type" => "Family"],
        "2766" => ["section" => "GREAT PLAINS", "type" => "Lifetime"],
        "1877" => ["section" => "SASKATCHEWAN", "type" => "Individual"],
        "1879" => ["section" => "SASKATCHEWAN", "type" => "Youth"],
        "1880" => ["section" => "SASKATCHEWAN", "type" => "Family"],
        "1878" => ["section" => "SASKATCHEWAN", "type" => "Family"],
        "1876" => ["section" => "SASKATCHEWAN", "type" => "Family"],
        "2780" => ["section" => "SASKATCHEWAN", "type" => "Lifetime"],
        "1882" => ["section" => "MANITOBA", "type" => "Individual"],
        "1884" => ["section" => "MANITOBA", "type" => "Youth"],
        "1885" => ["section" => "MANITOBA", "type" => "Family"],
        "1883" => ["section" => "MANITOBA", "type" => "Family"],
        "1881" => ["section" => "MANITOBA", "type" => "Family"],
        "2771" => ["section" => "MANITOBA", "type" => "Lifetime"],
        "2895" => ["section" => "MANITOBA", "type" => "Student"],
        "1887" => ["section" => "SAINT BONIFACE", "type" => "Individual"],
        "1889" => ["section" => "SAINT BONIFACE", "type" => "Youth"],
        "1890" => ["section" => "SAINT BONIFACE", "type" => "Family"],
        "1888" => ["section" => "SAINT BONIFACE", "type" => "Family"],
        "1886" => ["section" => "SAINT BONIFACE", "type" => "Family"],
        "2779" => ["section" => "SAINT BONIFACE", "type" => "Lifetime"],
        "1892" => ["section" => "OTTAWA", "type" => "Individual"],
        "1894" => ["section" => "OTTAWA", "type" => "Youth"],
        "1895" => ["section" => "OTTAWA", "type" => "Family"],
        "1893" => ["section" => "OTTAWA", "type" => "Family"],
        "1891" => ["section" => "OTTAWA", "type" => "Family"],
        "2775" => ["section" => "OTTAWA", "type" => "Lifetime"],
        "2896" => ["section" => "OTTAWA", "type" => "Student"],
        "1897" => ["section" => "THUNDER BAY", "type" => "Individual"],
        "1899" => ["section" => "THUNDER BAY", "type" => "Youth"],
        "1900" => ["section" => "THUNDER BAY", "type" => "Family"],
        "1898" => ["section" => "THUNDER BAY", "type" => "Family"],
        "1896" => ["section" => "THUNDER BAY", "type" => "Family"],
        "2783" => ["section" => "THUNDER BAY", "type" => "Lifetime"],
        "1902" => ["section" => "TORONTO", "type" => "Individual"],
        "1904" => ["section" => "TORONTO", "type" => "Youth"],
        "1905" => ["section" => "TORONTO", "type" => "Family"],
        "1903" => ["section" => "TORONTO", "type" => "Family"],
        "1901" => ["section" => "TORONTO", "type" => "Family"],
        "2784" => ["section" => "TORONTO", "type" => "Lifetime"],
        "1837" => ["section" => "MONTRÉAL", "type" => "Individual"],
        "1839" => ["section" => "MONTRÉAL", "type" => "Youth"],
        "1840" => ["section" => "MONTRÉAL", "type" => "Family"],
        "1838" => ["section" => "MONTRÉAL", "type" => "Family"],
        "1836" => ["section" => "MONTRÉAL", "type" => "Family"],
        "2772" => ["section" => "MONTRÉAL", "type" => "Lifetime"],
        "1842" => ["section" => "OUTAOUAIS", "type" => "Individual"],
        "1844" => ["section" => "OUTAOUAIS", "type" => "Youth"],
        "1845" => ["section" => "OUTAOUAIS", "type" => "Family"],
        "1843" => ["section" => "OUTAOUAIS", "type" => "Family"],
        "1841" => ["section" => "OUTAOUAIS", "type" => "Family"],
        "2776" => ["section" => "OUTAOUAIS", "type" => "Lifetime"],
        "1907" => [
            "section" => "NEWFOUNDLAND & LABRADOR",
            "type" => "Individual",
        ],
        "1909" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "Youth"],
        "1910" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "Family"],
        "1908" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "Family"],
        "1906" => ["section" => "NEWFOUNDLAND & LABRADOR", "type" => "Family"],
        "2773" => [
            "section" => "NEWFOUNDLAND & LABRADOR",
            "type" => "Lifetime",
        ],
    ];

    private $sectionNameConversion = [
        "BUGABOOS" => "Bugaboos",
        "CALGARY" => "Calgary",
        "CENTRAL ALBERTA" => "Central Alberta",
        "COLUMBIA MOUNTAINS" => "Columbia Mountains",
        "EDMONTON" => "Edmonton",
        "GREAT PLAINS" => "Great Plains",
        "JASPER / HINTON" => "Jasper/Hinton",
        "MANITOBA" => "Manitoba",
        "MONTRÉAL" => "Montréal",
        "NEWFOUNDLAND & LABRADOR" => "Newfoundland and Labrador",
        "OKANAGAN" => "Okanagan",
        "OUTAOUAIS" => "Outaouais",
        "OTTAWA" => "Ottawa",
        "PRINCE GEORGE" => "Prince George",
        "ROCKY MOUNTAIN" => "Rocky Mountain",
        "SAINT BONIFACE" => "Saint Boniface",
        "SASKATCHEWAN" => "Saskatchewan",
        "SAULT STE. MARIE" => "Sault Ste. Marie",
        "SOUTHERN ALBERTA" => "Southern Alberta",
        "SQUAMISH" => "Squamish",
        "THUNDER BAY" => "Thunder Bay",
        "TORONTO" => "Toronto",
        "VANCOUVER" => "Vancouver",
        "VANCOUVER ISLAND" => "Vancouver Island",
        "WHISTLER" => "Whistler",
        "YUKON" => "Yukon",
    ];

    private function oldSectionNames()
    {
        return array_keys($this->sectionNameConversion);
    }

    private function convertSectionName($section)
    {
        if (array_key_exists($section, $this->sectionNameConversion)) {
            return $this->sectionNameConversion[$section];
        }

        return null;
    }

    private function getMembershipTypeFromId($mId)
    {
        if (array_key_exists($mId, $this->membershipTable)) {
            return $this->membershipTable[$mId]["type"];
        }
        return "Unknown";
    }

    /**
     * Delete obsolete "acc_status"
     * Delete obsolete "membership_status"
     * Rename "membership" to "acc_member_id"
     * Rename "membership_type" to "acc_mship_type"
     * Rename "expiry" to "acc_mship_expiry"
     *
     * Scans all user memberships and
     *   -set "acc_mship_expiry" to the latest expiry date found
     *   -set "acc_mship_type" according to the latest valid membership.
     *    If more than one membership has the same expiry date, give
     *    preference to family type.
     *   -set "acc_sections" = array of section names with valid memberships.
     *    The array can be empty if the user has no valid membership.
     */
    private function scan_user_db_and_upgrade($log)
    {
        error_log("Converting user DB to 4.0.0 format\n", 3, $log);
        error_log(
            "Will remove obsolete acc_status and membership_status\n",
            3,
            $log
        );

        $user_ids = get_users(["fields" => "ID"]);
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            error_log("Processing $user->display_name\n", 3, $log);

            //Safety. acc_member_id was introduced in v4.0.0
            if (isset($user->acc_member_id)) {
                error_log(
                    "User entry was already upgraded, skip...\n",
                    3,
                    $log
                );
                continue;
            }

            // Rename membership->acc_member_id
            if (!empty($user->membership)) {
                update_user_meta($user_id, "acc_member_id", $user->membership);
                delete_user_meta($user_id, "membership");
            }

            $isWaiverSigned = false;
            $latestExpiry = null;
            $mType = null;
            $mStatus = null;
            $newMemberships = [];
            $userHadMemberships = false;

            if (!empty($user->acc_memberships)) {
                $userHadMemberships = true;

                foreach (
                    $user->acc_memberships
                    as $section => $sect_memberships
                ) {
                    $sectionNewName = $this->convertSectionName($section);
                    if ($sectionNewName == null) {
                        continue;
                    }

                    foreach ($sect_memberships as $mId => $mship) {
                        $expiry = $mship["expiry"];
                        $status = $mship["status"];
                        if (!$isWaiverSigned && $status == "ISSU") {
                            //error_log("Waiver has been signed\n",3,$log);
                            $isWaiverSigned = true;
                        }

                        // Build a list of all sections with valid memberships
                        // i.e. date is good
                        if ($expiry > date("Y-m-d")) {
                            if (!in_array($sectionNewName, $newMemberships)) {
                                $newMemberships[] = $sectionNewName;
                            }
                        }

                        // Find the latest membership and note its type. If multiple memberships
                        // have the same expiry date, prefer the family types over adult.
                        if (empty($latestExpiry) || $expiry >= $latestExpiry) {
                            $latestExpiry = $expiry;
                            $candidateType = $this->getMembershipTypeFromId(
                                $mId
                            );
                            if (empty($mType)) {
                                $mType = $candidateType;
                            } else {
                                if (
                                    $this->membershipPref[$candidateType] >
                                    $this->membershipPref[$mType]
                                ) {
                                    $mType = $candidateType;
                                }
                            }
                        }
                    }
                }
            }

            $mshipTxt = serialize($user->acc_memberships);
            error_log(
                "Before: user $user->ID memberships $mshipTxt\n",
                3,
                $log
            );
            $mshipTxt = implode(",", $newMemberships);
            error_log(
                "After: user $user->ID $mType exp $latestExpiry sect: $mshipTxt\n",
                3,
                $log
            );

            // For manually created admin entries, do not create useless fields.
            if ($userHadMemberships) {
                if ($isWaiverSigned) {
                    update_user_meta(
                        $user_id,
                        "acc_waiver_expiry",
                        $latestExpiry
                    );
                }
                update_user_meta($user_id, "acc_mship_type", $mType);
                update_user_meta($user_id, "acc_mship_expiry", $latestExpiry);
                update_user_meta($user_id, "acc_sections", $newMemberships);
            }

            delete_user_meta($user_id, "expiry");
            delete_user_meta($user_id, "membership_type");
            delete_user_meta($user_id, "acc_status");
            delete_user_meta($user_id, "membership_status");
            delete_user_meta($user_id, "acc_memberships");
            delete_user_meta($user_id, "imis_id");
        }
    }

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
