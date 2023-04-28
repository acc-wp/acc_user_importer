<?php

/* When requesting member data, we are limited by the size of the response.
 * Benoit says that Microsoft Edge has a limit of 64000 bytes.
 * Be conservative and ask for 10 members at a time.
 */
define("MEMBER_API_MAX_USERS", "10");

$acc_logstr = "";		//handy global to store log string

class acc_user_importer_Admin {

	private $plugin_name;
	private $version;
	private $debug_mode = false;
	private $error_logging = false;

	// List of ACC section membership types.
	// Obtained from an Interpodia Excel spreadsheet.
	private $membershipTable = array (
		'1807' => ['section' => 'YUKON', 'type' => 'adult'],
		'1809' => ['section' => 'YUKON', 'type' => 'youth'],
		'1810' => ['section' => 'YUKON', 'type' => 'family1'],
		'1808' => ['section' => 'YUKON', 'type' => 'family2'],
		'1806' => ['section' => 'YUKON', 'type' => 'child'],
		'1918' => ['section' => 'BUGABOOS', 'type' => 'adult'],
		'1920' => ['section' => 'BUGABOOS', 'type' => 'youth'],
		'1921' => ['section' => 'BUGABOOS', 'type' => 'family1'],
		'1919' => ['section' => 'BUGABOOS', 'type' => 'family2'],
		'1917' => ['section' => 'BUGABOOS', 'type' => 'child'],
		'1812' => ['section' => 'COLUMBIA MOUNTAINS', 'type' => 'adult'],
		'1814' => ['section' => 'COLUMBIA MOUNTAINS', 'type' => 'youth'],
		'1815' => ['section' => 'COLUMBIA MOUNTAINS', 'type' => 'family1'],
		'1813' => ['section' => 'COLUMBIA MOUNTAINS', 'type' => 'family2'],
		'1811' => ['section' => 'COLUMBIA MOUNTAINS', 'type' => 'child'],
		'1817' => ['section' => 'OKANAGAN', 'type' => 'adult'],
		'1819' => ['section' => 'OKANAGAN', 'type' => 'youth'],
		'1820' => ['section' => 'OKANAGAN', 'type' => 'family1'],
		'1818' => ['section' => 'OKANAGAN', 'type' => 'family2'],
		'1816' => ['section' => 'OKANAGAN', 'type' => 'child'],
		'1822' => ['section' => 'PRINCE GEORGE', 'type' => 'adult'],
		'1824' => ['section' => 'PRINCE GEORGE', 'type' => 'youth'],
		'1825' => ['section' => 'PRINCE GEORGE', 'type' => 'family1'],
		'1823' => ['section' => 'PRINCE GEORGE', 'type' => 'family2'],
		'1821' => ['section' => 'PRINCE GEORGE', 'type' => 'child'],
		'1573' => ['section' => 'SQUAMISH', 'type' => 'adult'],
		'1575' => ['section' => 'SQUAMISH', 'type' => 'youth'],
		'1576' => ['section' => 'SQUAMISH', 'type' => 'family1'],
		'1579' => ['section' => 'SQUAMISH', 'type' => 'family2'],
		'1577' => ['section' => 'SQUAMISH', 'type' => 'child'],
		'1827' => ['section' => 'VANCOUVER', 'type' => 'adult'],
		'1829' => ['section' => 'VANCOUVER', 'type' => 'youth'],
		'1830' => ['section' => 'VANCOUVER', 'type' => 'family1'],
		'1828' => ['section' => 'VANCOUVER', 'type' => 'family2'],
		'1826' => ['section' => 'VANCOUVER', 'type' => 'child'],
		'1784' => ['section' => 'VANCOUVER ISLAND', 'type' => 'adult'],
		'1783' => ['section' => 'VANCOUVER ISLAND', 'type' => 'youth'],
		'1787' => ['section' => 'VANCOUVER ISLAND', 'type' => 'family1'],
		'1785' => ['section' => 'VANCOUVER ISLAND', 'type' => 'family2'],
		'1786' => ['section' => 'VANCOUVER ISLAND', 'type' => 'child'],
		'1832' => ['section' => 'WHISTLER', 'type' => 'adult'],
		'1834' => ['section' => 'WHISTLER', 'type' => 'youth'],
		'1835' => ['section' => 'WHISTLER', 'type' => 'family1'],
		'1833' => ['section' => 'WHISTLER', 'type' => 'family2'],
		'1831' => ['section' => 'WHISTLER', 'type' => 'child'],
		'1779' => ['section' => 'CALGARY', 'type' => 'adult'],
		'1778' => ['section' => 'CALGARY', 'type' => 'youth'],
		'1782' => ['section' => 'CALGARY', 'type' => 'family1'],
		'1780' => ['section' => 'CALGARY', 'type' => 'family2'],
		'1781' => ['section' => 'CALGARY', 'type' => 'child'],
		'1847' => ['section' => 'CENTRAL ALBERTA ', 'type' => 'adult'],
		'1849' => ['section' => 'CENTRAL ALBERTA ', 'type' => 'youth'],
		'1850' => ['section' => 'CENTRAL ALBERTA ', 'type' => 'family1'],
		'1848' => ['section' => 'CENTRAL ALBERTA ', 'type' => 'family2'],
		'1846' => ['section' => 'CENTRAL ALBERTA ', 'type' => 'child'],
		'1852' => ['section' => 'EDMONTON', 'type' => 'adult'],
		'1854' => ['section' => 'EDMONTON', 'type' => 'youth'],
		'1855' => ['section' => 'EDMONTON', 'type' => 'family1'],
		'1853' => ['section' => 'EDMONTON', 'type' => 'family2'],
		'1851' => ['section' => 'EDMONTON', 'type' => 'child'],
		'1857' => ['section' => 'JASPER / HINTON', 'type' => 'adult'],
		'1859' => ['section' => 'JASPER / HINTON', 'type' => 'youth'],
		'1860' => ['section' => 'JASPER / HINTON', 'type' => 'family1'],
		'1858' => ['section' => 'JASPER / HINTON', 'type' => 'family2'],
		'1856' => ['section' => 'JASPER / HINTON', 'type' => 'child'],
		'1862' => ['section' => 'ROCKY MOUNTAIN', 'type' => 'adult'],
		'1864' => ['section' => 'ROCKY MOUNTAIN', 'type' => 'youth'],
		'1865' => ['section' => 'ROCKY MOUNTAIN', 'type' => 'family1'],
		'1863' => ['section' => 'ROCKY MOUNTAIN', 'type' => 'family2'],
		'1861' => ['section' => 'ROCKY MOUNTAIN', 'type' => 'child'],
		'1867' => ['section' => 'SOUTHERN ALBERTA', 'type' => 'adult'],
		'1869' => ['section' => 'SOUTHERN ALBERTA', 'type' => 'youth'],
		'1870' => ['section' => 'SOUTHERN ALBERTA', 'type' => 'family1'],
		'1868' => ['section' => 'SOUTHERN ALBERTA', 'type' => 'family2'],
		'1866' => ['section' => 'SOUTHERN ALBERTA', 'type' => 'child'],
		'1872' => ['section' => 'GREAT PLAINS', 'type' => 'adult'],
		'1874' => ['section' => 'GREAT PLAINS', 'type' => 'youth'],
		'1875' => ['section' => 'GREAT PLAINS', 'type' => 'family1'],
		'1873' => ['section' => 'GREAT PLAINS', 'type' => 'family2'],
		'1871' => ['section' => 'GREAT PLAINS', 'type' => 'child'],
		'1877' => ['section' => 'SASKATCHEWAN', 'type' => 'adult'],
		'1879' => ['section' => 'SASKATCHEWAN', 'type' => 'youth'],
		'1880' => ['section' => 'SASKATCHEWAN', 'type' => 'family1'],
		'1878' => ['section' => 'SASKATCHEWAN', 'type' => 'family2'],
		'1876' => ['section' => 'SASKATCHEWAN', 'type' => 'child'],
		'1882' => ['section' => 'MANITOBA', 'type' => 'adult'],
		'1884' => ['section' => 'MANITOBA', 'type' => 'youth'],
		'1885' => ['section' => 'MANITOBA', 'type' => 'family1'],
		'1883' => ['section' => 'MANITOBA', 'type' => 'family2'],
		'1881' => ['section' => 'MANITOBA', 'type' => 'child'],
		'1887' => ['section' => 'SAINT BONIFACE', 'type' => 'adult'],
		'1889' => ['section' => 'SAINT BONIFACE', 'type' => 'youth'],
		'1890' => ['section' => 'SAINT BONIFACE', 'type' => 'family1'],
		'1888' => ['section' => 'SAINT BONIFACE', 'type' => 'family2'],
		'1886' => ['section' => 'SAINT BONIFACE', 'type' => 'child'],
		'1892' => ['section' => 'OTTAWA', 'type' => 'adult'],
		'1894' => ['section' => 'OTTAWA', 'type' => 'youth'],
		'1895' => ['section' => 'OTTAWA', 'type' => 'family1'],
		'1893' => ['section' => 'OTTAWA', 'type' => 'family2'],
		'1881' => ['section' => 'OTTAWA', 'type' => 'child'],
		'1897' => ['section' => 'THUNDER BAY', 'type' => 'adult'],
		'1899' => ['section' => 'THUNDER BAY', 'type' => 'youth'],
		'1900' => ['section' => 'THUNDER BAY', 'type' => 'family1'],
		'1898' => ['section' => 'THUNDER BAY', 'type' => 'family2'],
		'1896' => ['section' => 'THUNDER BAY', 'type' => 'child'],
		'1897' => ['section' => 'TORONTO', 'type' => 'adult'],
		'1899' => ['section' => 'TORONTO', 'type' => 'youth'],
		'1900' => ['section' => 'TORONTO', 'type' => 'family1'],
		'1898' => ['section' => 'TORONTO', 'type' => 'family2'],
		'1896' => ['section' => 'TORONTO', 'type' => 'child'],
		'1837' => ['section' => 'MONTRÉAL', 'type' => 'adult'],
		'1839' => ['section' => 'MONTRÉAL', 'type' => 'youth'],
		'1840' => ['section' => 'MONTRÉAL', 'type' => 'family1'],
		'1838' => ['section' => 'MONTRÉAL', 'type' => 'family2'],
		'1836' => ['section' => 'MONTRÉAL', 'type' => 'child'],
		'1842' => ['section' => 'OUTAOUAIS', 'type' => 'adult'],
		'1844' => ['section' => 'OUTAOUAIS', 'type' => 'youth'],
		'1845' => ['section' => 'OUTAOUAIS', 'type' => 'family1'],
		'1843' => ['section' => 'OUTAOUAIS', 'type' => 'family2'],
		'1841' => ['section' => 'OUTAOUAIS', 'type' => 'child'],
		'1907' => ['section' => 'NEWFOUNDLAND & LABRADOR', 'type' => 'adult'],
		'1909' => ['section' => 'NEWFOUNDLAND & LABRADOR', 'type' => 'youth'],
		'1910' => ['section' => 'NEWFOUNDLAND & LABRADOR', 'type' => 'family1'],
		'1908' => ['section' => 'NEWFOUNDLAND & LABRADOR', 'type' => 'family2'],
		'1906' => ['section' => 'NEWFOUNDLAND & LABRADOR', 'type' => 'child'],
	);

	//FIXME only the first 2 APIs have been created, the rest are bogus numbers
	private $sectionApiId = array (
			'SQUAMISH' => '1',
			'CALGARY' => '2',
			'OTTAWA' => '3',
			'MONTRÉAL' => '4',
			'OUTAOUAIS' => '5',
			'VANCOUVER' => '6');

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		//load display
		require plugin_dir_path( __FILE__ ) . '/partials/acc_user_importer-admin-settings.php';
	}

	// Get the section name as per the settings
	private function getSectionName ( ) {
		$options = get_option('accUM_data');
		if (!isset($options['accUM_section_api_id'])) {
			$sectionName = accUM_get_section_default();
		} else {
			$sectionName = $options['accUM_section_api_id'];
		}
		return $sectionName;
	}

	// Get the section API ID
	private function getSectionApiID ( $sectionName ) {
		return($this->sectionApiId[$sectionName]);
	}

	// Get the API token as per the setting
	private function getApiToken ( ) {
		$options = get_option('accUM_data');
		return($options['accUM_token']);
	}



	/**
	 * This is the user import loop (when triggered by a timer)
	 */
	public function begin_automatic_update() {

		//force certificate validation - i.e. speed up authentication process
		add_filter( 'https_local_ssl_verify', '__return_true' );

		$options = get_option('accUM_data');
		$acc_user = $options['accUM_section_api_id'];
		//FIXME
		$this->log_local_output("Automatic member update starting for section $acc_user");
		$timestamp_start = date_i18n("Y-m-d-H-i-s");

		//FIXME this is no longer required
		//request token
		$this->log_local_output("Requesting access token from national office.");
		$access_token_request = ""; 	//FIXME delete line

		//did we get token?
		if ( $access_token_request['message'] == "success") {

			$this->log_local_output('Received token: ' . substr($access_token_request['accessToken'], 0, 10) . ".");

			$has_next = true;
			$get_attempts_remaining = 3;
			$data_offset = 0;

			//get data until no more data exists
			while ( ($has_next === true) && ($get_attempts_remaining > 0) ) {

				//request next dataset with token
				$this->log_local_output("Requesting membership data using token: " . substr($access_token_request['accessToken'], 0, 10) . ".");
				$member_data_request = $this->getMemberData( $access_token_request['accessToken'], $data_offset );

				//did we get data?
				if ( $member_data_request['message'] == "success") {

					$proccess_request = $this->proccess_user_data( $member_data_request['dataset'] );

					//If there is more data, move offset and prepare for one more loop
					if ($member_data_request['HasNext']) {
						$has_next = true;
						$data_offset = $member_data_request['NextOffset'];
						$this->log_local_output("More members to process, next offset=$data_offset");
					} else {
						$has_next = false; //default to not having more data
						$this->log_local_output("No more members, ending data loop.");
					}

				} else {
					//failed to get data - if attempts remain, try again
					$this->log_local_output("Error: " . ($member_data_request['errorMessage'] ? $member_data_request['errorMessage'] : 'Unknown.'));
					$has_next = true;
					$get_attempts_remaining = $get_attempts_remaining - 1;
					$this->log_local_output("Error: " . $get_attempts_remaining . " attempts remaining to get API data.");
				}
			}

			if ($has_next == false) {
				// All members have been successfully updated, now look for expired members
				$expiryResult = $this->proccess_expiry();
			}

		} //end: if token granted

		else {
			$this->log_local_output("Error: Token was not granted.");
		}

		$timestamp_end = date_i18n("Y-m-d-H-i-s");
		$this->log_local_output("This journey has come to an end.");
		$this->log_local_output("Start time: " . $timestamp_start);
		$this->log_local_output("End time: " . $timestamp_end);
	}

	/**
	 * Controller for the WP-API requests.
	 */
	public function accUserAPI() {
		$GLOBALS['acc_logstr'] = "";		//Clear the API response log string

		//create response object
		$api_response = [];

		//kill script if current user lacks permission to edit other users
		if ( current_user_can( "edit_users" ) == false ) {
			$api_response['message'] = "user permission error";
			echo json_encode( $api_response );
			wp_die();
		}

		//kill script if nonce doesn't match up
		if ( check_ajax_referer( 'accUserAPI', 'security', false) == false ) {
			$api_response['message'] = "security error";
			echo json_encode( $api_response );
			wp_die();
		}

		//iterate through requests
		switch ( $_POST['request'] ) {

			case "establish":
				$api_response['message'] = "established";
				break;

			case "getChangedMembers":
				$api_response = $this->getChangedMembers();
				break;

			case "getMemberData":
				$api_response = $this->getMemberData( $_POST['changeList'], $_POST['offset'] );
				break;

			case "processMemberData":
				$memberArray = $_POST['dataset'];
				$api_response = $this->proccess_user_data( $memberArray );
				break;

			case "processExpiry":
				$api_response = $this->proccess_expiry();
				break;

		}

		//Return the log of the operation
		$api_response['log'] = $GLOBALS['acc_logstr'];

		//respond to ajax request and terminate
		echo json_encode( $api_response );
		wp_die();
	}


	/**
	 * Request the list of changed members from the national office API.
	 * It calls the Changed Member API until it has the full list of members
	 * with changed memberships.
	 */
	private function getChangedMembers() {

		$options = get_option('accUM_data');
		$sectionName = $this->getSectionName();
		$sectionApiId = $this->getSectionApiId($sectionName);

		$this->log_dual("Retrieving changed members for section {$sectionName}, API {$sectionApiId}");

		// Read token from user settings. Avoid printing token it is sensitive data
		$access_token = $this->getApiToken();
		//$this->log_dual("Token=" . $access_token);
		if (!isset($access_token) || (strlen($access_token) == 0)) {
			$api_response['errorMessage'] = "Authentication token is not defined";
			return $api_response;
		}

		$sinceDate = $options['accUM_since_date'];
		if (!isset($sinceDate)) {
			// FIXME, need to retrieve last execution date. Just use an arbitrary number for now
			$sinceDate = "2020-01-01T15:05:00";
			$this->log_dual("No sinceDate specified, using last run date (" . $sinceDate . ")");
		}

		// Create response object for local api
		$api_response = [];
		$count = 0;
		$changeList = [];
		$changed_members_uri = 'https://2mev.com/rest/v2/member-apis/' . $sectionApiId .
		                       '/changed_members/?changed_since=' . $sinceDate;

		do {
			$this->log_dual("changed_members_uri=" . $changed_members_uri);
			$acc_response = wp_remote_get($changed_members_uri, array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token
			),));

			if (is_wp_error($acc_response)) {
				$api_response['message'] = "error";
				$api_response['log'] = $GLOBALS['acc_logstr'];
				$api_response['errorMessage'] = $acc_response->get_error_message();
				return $api_response;
			}

			$acc_response_data = wp_remote_retrieve_body ( $acc_response );
			$acc_response_data = json_decode($acc_response_data);
			if ( !array_key_exists('count', (array) $acc_response_data ) ) {
				$api_response['message'] = "error";
				$api_response['log'] = $GLOBALS['acc_logstr'];
				$api_response['errorMessage'] = "No count in Changed Members API response";
				return $api_response;
			}

			$this->log_dual("count=" . $acc_response_data->count);
			$this->log_dual("next=" . $acc_response_data->next);
			$this->log_dual("previous=" . $acc_response_data->previous);
			$this->log_dual("results=" . json_encode($acc_response_data->results));
			$count += $acc_response_data->count;
			$changeList = array_merge($changeList, $acc_response_data->results);
			$this->log_dual("total count=" . $count);
			$this->log_dual("total change list=" . json_encode($changeList));

		} while ($acc_response_data->next != null);

		$api_response['count'] = $count;
		$api_response['results'] = $changeList;
		$api_response['message'] = "success";
		return $api_response;
	}

	/*
	 * Select the next N entries from the list of changed members.
	 */
	private function getChangeListSubset( $changeList, $offset, $numToDo ) {
		$changeSubset = array_slice($changeList, $offset, $numToDo);
		$subsetString = implode(",", $changeSubset);
		return $subsetString;
	}

	/**
	 * Request a dataset from the national office API.
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
	private function getMemberData( $changeList, $offset = 0 ) {

		if ( !$offset ) { $offset = 0; }
		$access_token = $this->getApiToken();

		//Compute how many members we want to process
		$remaining = sizeof($changeList)-$offset;
		$numToDo = min ($remaining, MEMBER_API_MAX_USERS);
		$this->log_dual("remaining={$remaining}, will fetch {$numToDo}");
		$subsetString = $this->getChangeListSubset($changeList, $offset, $numToDo);

		$member_uri = 'https://2mev.com/rest/v2/member-apis/1/fetch/?member_number=' . $subsetString;
		$this->log_dual("member_uri=" . $member_uri);

		$get_args = array(
			'headers' => array(
				'content-type' => 'application/json',
				'Authorization' => "Bearer " . $access_token
			)
		);
		$acc_response = wp_remote_get( $member_uri, $get_args );

		//create response object
		$api_response = [];

		//if the post request fails
		if ( is_wp_error( $acc_response ) ) {
			$api_response['message'] = "error";
			$api_response['errorMessage'] = $acc_response->get_error_message();
			$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
			$this->log_dual("Error, " . $api_response['errorMessage']);
			return $api_response;
		}

		$acc_response_data = wp_remote_retrieve_body ( $acc_response );
		$memberData = json_decode($acc_response_data);
		$count = sizeof ($memberData);

		if ($count != $numToDo) {
			$this->log_dual("Error, member API returned " . $count . " members instead of " . $numToDo);
			$api_response['message'] = "error";
			$api_response['errorMessage'] = "Member API returned " . $count . " members instead of " . $numToDo;
			$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
			return $api_response;
		}

		$lastUser = $offset + $numToDo -1;
		$this->log_dual("Received users $offset to $lastUser");

		$api_response['nextDataOffset'] = $offset + $numToDo;
		$api_response['results'] = $memberData;
		$api_response['message'] = "success";
		$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
		return $api_response;
	}

	// If a member has multiple memberships, which one do we prefer?
	private $membershipPreference = array (
		'family1' => 5,
		'family2' => 4,
		'adult' => 3,
		'child' => 2,
		'youth' => 1);

	/**
	 * Returns true if the type2 membership is preferred over the type1.
	 * If the member has both a family and an adult membership, is it
	 * preferable to chose the family membership because it has more privilege.
	 */
	private function compareMembershipType ( $type1, $type2 ) {
		return ($this->membershipPreference[$type2] > $this->membershipPreference[$type1]);
	}

	/**
	 * Returns true if the 2 membership have equal priority.
	 */
	private function equalMembershipType ( $type1, $type2 ) {
		return ($this->membershipPreference[$type2] == $this->membershipPreference[$type1]);
	}


	/**
	 * Update Wordpress database with member information.
	 * This is where most of the work gets done.
	 */
	private function proccess_user_data ( $users ) {

		//create response object
		$api_response = [];
		$this->log_dual("Start processing batch of " . count($users) . " users");
		$options = get_option('accUM_data');

		//Return gracefully is dataset is empty
		if (! ( count($users) > 0 ) ) {
			$this->log_dual("Nothing to process");
			$api_response['message'] = "success";
			$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
			return $api_response;
		}

		// Get the default_role setting
		if (!isset($options['accUM_default_role'])) {
			$this->log_dual("accUM_default_role is empty");
			$default_role = accUM_get_default_role_default();
		} else {
			$default_role = $options['accUM_default_role'];
		}
		$this->log_dual("Using $default_role as default role for new users");

		// Get the loginNameMapping setting
		if (!isset($options['accUM_login_name_mapping'])) {
			$loginNameMapping = accUM_get_login_name_mapping_default();
		} else {
			$loginNameMapping = $options['accUM_login_name_mapping'];
		}
		$this->log_dual("Using $loginNameMapping as login name.");

		$sectionName = $this->getSectionName();

		//loop through the received data and create users
		$update_errors = [];
		$new_users = [];
		$new_users_email = [];
		$updated_users = [];
		$updated_users_email = [];

		foreach ( $users as $user ) {
			//Avoid PHP warnings in case some fields are unpopulated
			//We are ignoring date of birth for now.
			$userFirstName= $user["first_name"] ?? '';
			$userLastName= $user["last_name"] ?? '';
			//$userContactId = $user['member_number'] ?? '';
			//FIXME what should we do with the new system 'id'?
			//Should we overwrite imis_id with this new field?
			//For now we just ignore it.
			//$userImisId = $user['imis_id'] ?? '';
			$userEmail = strtolower($user["email"] ?? '');
			//FIXME the phone is missing in Interpodia DB
			//$userHomePhone = $user["HomePhone"] ?? '';
			//$userCellPhone = $user["Cell Phone"] ?? '';
			$userMemberNumber = $user["member_number"] ?? '';
			$receivedMemberships = $user['memberships'];
			$this->log_dual(json_encode($user));

			// It is possible for the user to have multiple memberships.
			// We are only interested in memberships for the section the plugin
			// is operating for. Among those memberships, select the one with
			// greater date (most in the future).
			$userMembershipType = "";
			$userMembershipSection = "";
			$userMembershipExpiry = "";
			foreach ( $receivedMemberships as $membership ) {
				$mId = $membership['membership_group']['id'];
				$mSection = $this->membershipTable[$mId]['section'];
				$mType = $this->membershipTable[$mId]['type'];
				$mExpiry = $membership['valid_to'];
				//$this->log_dual("detected membership: {$mId} {$mSection} {$mType} {$mExpiry}");
				if ($mSection == $sectionName) {
					if (empty($userMembershipType)) {
						//Found a first membership
						$userMembershipType = $mType;
						$userMembershipSection = $mSection;
						$userMembershipExpiry = $mExpiry;
					} else if ($mExpiry > $userMembershipExpiry) {
						//Found a membership with a later expiry, take note of it.
						$userMembershipType = $mType;
						$userMembershipSection = $mSection;
						$userMembershipExpiry = $mExpiry;
						$this->log_dual("> Better expiry date");
					} else if ($mExpiry == $userMembershipExpiry &&
					    $this->compareMembershipType($userMembershipType, $mType)) {
						//Same expiry date, but found a better membership type.
						$userMembershipType = $mType;
						$userMembershipSection = $mSection;
						$userMembershipExpiry = $mExpiry;
						$this->log_dual("> Same expiry date but better type");
					}
				}
			}

			//Log the info we received for this user
			$userInfoString = $userFirstName . " " . $userLastName;
			$userInfoString .= " " . $userEmail;
			//$userInfoString .= " ContactID:" . $userContactId;
			//$userInfoString .= " imis_id:" . $userImisId;
			$userInfoString .= " membership#:" . $userMemberNumber;
			//$userInfoString .= " home:" . $userHomePhone;
			//$userInfoString .= " cell:" . $userCellPhone;
			$userInfoString .= " type:" . $userMembershipType;
			$userInfoString .= " section:" . $userMembershipSection;
			$userInfoString .= " expiry:" . $userMembershipExpiry;
			$this->log_dual("Received " . $userInfoString);

			//Validate we have received mandatory fields.
			if (!is_numeric($userMemberNumber)) {
				$this->log_dual(" > error, no member number; skip");
				continue;
			}

			//Safety check in case firstname and lastname are empty
			if (empty($userFirstName) && empty($userLastName)) {
				$this->log_dual(" > error, user has no name; skip");
				continue;
			}

			switch($loginNameMapping) {
				case 'Firstname Lastname':
					$loginName = "$userFirstName $userLastName";
					break;
				case 'member_number':
				default:
					$loginName = $userMemberNumber;
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
				'first_name'	=>	$userFirstName,
				'last_name'		=>	$userLastName,
				'display_name'	=>	$userFirstName . " " . $userLastName,
				'user_email'	=>	$userEmail,
			];

			$accUserMetaData = [
				//'home_phone' => $userHomePhone,
				//'cell_phone' => $userCellPhone,
				'membership' => $userMemberNumber,
				'membership_type' => $userMembershipType,
				'expiry' => $userMembershipExpiry,
				'nickname' => $userFirstName . " " . $userLastName,
				//'imis_id' => $userImisId,
			];

			// Check if ID or email already exist. Both should be unique
			$existingUser = get_user_by('login', $loginName);
			if( !is_a( $existingUser, WP_User::class ) ) {
				$this->log_dual(" > not found by login");
				//Not found by login, search by email
				$existingUser = get_user_by('email', $userEmail);
				if( is_a( $existingUser, WP_User::class ) ) {
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
					if ($accUserData['display_name'] != $existingUser->display_name) {
						$this->log_dual(" > found by email existing userId {$existingUser->ID} named
									   {$existingUser->display_name}. Collision!");
						//Collision, and names are different
						if ($this->compareMembershipType($userMembershipType,
											             $existingUser->membership_type)) {
							//Existing user is better, keep it.
							$this->log_dual(" > email already used by someone else, skip");
							continue;
						} else if ($this->equalMembershipType($userMembershipType,
													          $existingUser->membership_type)) {
							//When both users have equal membership types,
							//prefer the one with the lower member number, it
							//seems to be the one who created the family membership.
							if ($existingUser->membership < $userMemberNumber) {
								$this->log_dual(" > email already used by someone with lower member number, skip");
								continue;
							}
					   }
					} else {
						$this->log_dual(" > found by email, userId is" . $existingUser->ID);
					}
				}
			}

			$updatedFields = [];

			// Existing user, check if any fields were updated
			if( is_a( $existingUser, WP_User::class ) ) {
				//---------USER WAS FOUND IN DATABASE------------
				$this->log_dual(" > checking " . $existingUser->display_name . " (user #" . $existingUser->ID . ")");

				//Introduce a special rule to NOT update a user if the incoming data has
				//a expiry date earlier than the one in the local DB. This is because
				//sometimes a user has 2 memberships, one family and one personal, with
				//different information in each. When the plugin runs, it receives asynchronously
				//the 2 memberships, so one overwrites the other. Which one is the best one
				//is hard to say, but most likely the information in the membership with
				//latest expiry date is the best, because it is the latest one subscribed
				//to by the user.
				//I think we can do a straight string compare, given the YYYY-MM-DD-TIME format.
				if ($userMembershipExpiry < $existingUser->expiry) {
					$this->log_dual(" > warn, received expiry is earlier than local one; skip");
					continue;
				}

				// Check which fields might have changed. On purpose we dont want to check nicename.
				foreach (array_merge($accUserData, $accUserMetaData) as $field => $value) {
					if ($value != $existingUser->$field) {
						$this->log_dual(" > $field changed from " . $existingUser->$field . " to " . $value);
						$existingUser->$field = $value;
						$updatedFields[] = $field;
					}
				}

				// If fields changed, then update the user in the database.
				if (!empty($updatedFields)) {
					// Passing in the $existingUser object with the updated values will persist to the database.
					$updateResp = wp_update_user($existingUser);
					if ( is_wp_error($updateResp) ) {
						$this->log_dual(" > error, failed to update user");
						$this->log_dual(" > WP:" . $updateResp->get_error_message());
						continue;
					}
					$this->log_dual(" > updated user #" . $updateResp);

					//Update meta fields
					foreach ($accUserMetaData as $field => $value) {
						if (in_array($field, $updatedFields)) {
							update_user_meta($existingUser->ID, $field, $value);
						}
					}

					$updated_users[] = $accUserData['display_name'];
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
					$userID = $existingUser->ID;
					$this->log_dual("> user {$userID} username changed from
						{$existingUser->user_login} to {$loginName}, update database");

					global $wpdb;
					$wpdb->update($wpdb->users,
								  ['user_login' => $loginName],
								  ['ID' => $existingUser->ID]);
					$wpdb->update($wpdb->users,
							      ['user_nicename' => $accUserData['display_name']],
								  ['ID' => $existingUser->ID]);
				}

				// Trigger hook if expiry date changed (updated membership)
				if (in_array('expiry', $updatedFields)) {
					do_action('acc_membership_renewal', $existingUser->ID);
				}

				// All done with updating the user
				continue;
			}

			//--------USER NOT FOUND IN DATABASE-----
			// But before creating a new record, make sure email is valid.
			if (!is_email($userEmail)) {
				//User has no email field, skip it
				$this->log_dual(" > error: invalid email, cannot create new user account.");
				$update_errors[] = $user;
				continue;
			}

			//--------CREATE NEW USER-----
			$this->log_dual(" > email not found on any other users");
			$new_users[] = $accUserData['display_name'];
			$new_users_email[] = $userEmail ;
			$accUserData["user_pass"] = wp_generate_password(20);
			$accUserData["role"] = $default_role;
			$accUserData["user_nicename"] = $accUserData['display_name'];  //WP will sanitize
			$accUserData["user_login"] = $loginName;

			// Insert new user
			$userID = wp_insert_user( $accUserData );
			if ( is_wp_error($userID) ) {
				$this->log_dual(" > error, failed to create user");
				$this->log_dual(" > WP:" . $userID->get_error_message());
				continue;
			}

			$this->log_dual(" > Created new user #" . $userID);

			//Insert meta fields.
			//Indicate this user was inactive. Will transition to active in proccess_expiry.
			$accUserMetaData['acc_status'] = 'inactive';
			foreach ($accUserMetaData as $field => $value) {
				update_user_meta($userID, $field, $value);
			}

			// Execute hooks for new membership
			do_action('acc_new_membership', $userID);
		} //end user loop

		//Outcome summary
		$this->log_dual("");
		$this->log_dual("Processing complete for this batch of " . count($users) . " people.");
		$this->log_dual("--Created account for " . count($new_users) . " people:");
		foreach ( $new_users as $id => $user ) {
			$this->log_dual("  " . $user . " (" . $new_users_email[$id] . ")");
		}
		$this->log_dual("--Updated data for " . count($updated_users) . " people:");
		foreach ( $updated_users as $id => $user ) {
			$this->log_dual("  " . $user . " (" . $updated_users_email[$id] . ")");
		}
		if (count($update_errors) != 0) {
			$this->log_dual("--Errors updating " . count($update_errors) . " accounts:");
		}
		foreach ( $update_errors as $id => $user ) {
			$this->log_dual(" [" . $id . "] " . var_export($user, true));
		}

		$api_response['usersInData'] = count($users);
		$api_response['newUsers'] = count($new_users);
		$api_response['updatedUsers'] = (count($updated_users) - count($update_errors));
		$api_response['usersWithErrors'] = count($update_errors);
		$api_response['message'] = "success";
		$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string

		return $api_response;
	}


	/**
	 * Returns True if the user is expired.
	 * If the user has no 'expiry' field, it is considered as active.
	 * Most likely an admin.
	 */
	private function is_user_expired ($user) {
		if (empty($user->expiry)) {
			$this->log_dual("user $user->ID $user->display_name has no expiry, consider active");
			return false;
		}

		if ($user->expiry < date("Y-m-d")) {
			//$this->log_dual("user $user->ID $user->display_name expiry=$user->expiry is expired");
			return true;
		}
		//$this->log_dual("user $user->ID $user->display_name expiry=$user->expiry is valid");
		return false;
	}


	/**
	 * Go over our local user database and see who has an expired membership.
	 */
	private function proccess_expiry () {

		// Get user-configurable option values
		$options = get_option('accUM_data');

		// Get the default_role setting
		if (!isset($options['accUM_default_role'])) {
			$this->log_dual("accUM_default_role is empty");
			$default_role = accUM_get_default_role_default();
		} else {
			$default_role = $options['accUM_default_role'];
		}

		// Get the accUM_do_expire_role setting
		if (!isset($options['accUM_do_expire_role'])) {
			$do_expire_role = accUM_get_do_expire_role_default();
		} else {
			$do_expire_role = $options['accUM_do_expire_role'];
		}

		// Get the accUM_expired_role setting
		if (!isset($options['accUM_expired_role'])) {
			$this->log_dual("accUM_expired_role is empty");
			$expired_role = accUM_get_expired_role_default();
		} else {
			$expired_role = $options['accUM_expired_role'];
		}

		$this->log_dual("Now looking for expired members.");
		if ($do_expire_role == 'on') {
			$this->log_dual("and will update their roles to $expired_role");
		}

		//create response object
		$api_response = [];
		$db_users = get_users(['fields' => 'all_with_meta']);
		$num_active = 0;
		$num_inactive = 0;
		$new_users = [];
		$expired_users = [];
		$expired_role_users = [];
		$restored_role_users = [];

		foreach ( $db_users as $key => $user ) {

			if ($this->is_user_expired($user)) {
				// User is expired
				$num_inactive++;
				if (!empty($user->acc_status)) {
					if ($user->acc_status == 'active') {
						// User was active, now expired.
						update_user_meta($user->ID, 'acc_status', 'inactive');
						$this->log_dual("user $user->ID $user->display_name transitioned to " .
							            "inactive, send goodbye email if enabled");
						acc_send_goodbye_email($user->ID);
						do_action("acc_member_goodbye", $user->ID);		//action hook
						$expired_users[] = "$user->display_name  ($user->user_email)";
					}
				} else {
					// User did not have a acc_status field. Must be the first time this
					// new plugin executes. Set the field but do not send email.
					update_user_meta($user->ID, 'acc_status', 'inactive');
					$this->log_dual("Initial update of user $user->ID $user->display_name to inactive");
				}

				// If needed, change the user role to the expired role.
				// Do not change roles of administrators to prevent lockout.
				$user_roles = $user->roles;
				if ($do_expire_role == 'on' &&
					!in_array('administrator', $user_roles, true) &&
					!in_array($expired_role, $user_roles, true)) {
					$this->log_dual("Changing user $user->ID $user->display_name role to $expired_role");
					$expired_role_users[] = "$user->display_name  ($user->user_email)";
					// Save previous user roles (a user may have many roles)
					update_user_meta($user->ID, 'previous_roles', $user_roles);
					// Set role to expired role
					$user->set_role($expired_role);
				}

			} else {
				// User has a valid membership
				$num_active++;
				if (!empty($user->acc_status)) {
					if ($user->acc_status == 'inactive') {
						// User was inactive, now active.
						update_user_meta($user->ID, 'acc_status', 'active');
						$this->log_dual("user $user->ID $user->display_name transitioned to " .
							            "active, send welcome email if enabled");
						acc_send_welcome_email($user->ID);
						do_action("acc_member_welcome", $user->ID);		//action hook
						$new_users[] = "$user->display_name  ($user->user_email)";
					}
				} else {
					// User did not have a acc_status field. Must be the first time this
					// new plugin executes. Set the field but do not send email.
					update_user_meta($user->ID, 'acc_status', 'active');
					$this->log_dual("Initial update of user $user->ID $user->display_name to active");
				}

				// If needed, restore the previously saved member role. If we dont have a saved
				// previous role, pick the default role for a new member.
				if ($do_expire_role == 'on' &&
					in_array($expired_role, $user->roles, true)) {
					$restored_role_users[] = "$user->display_name  ($user->user_email)";

					if (empty($user->previous_roles)) {
						$previous_roles = [$default_role];
						$this->log_dual("Restoring user $user->ID $user->display_name role from $expired_role to default");
					} else {
						$previous_roles = $user->previous_roles;
						$this->log_dual("Restoring user $user->ID $user->display_name role from $expired_role to previous");
					}

					$first_role = true;
					foreach ($previous_roles as $role) {
						if ($first_role) {
							$user->set_role($role);
							$first_role = false;
							$this->log_dual("Restored role $role");
						} else {
							$user->add_role($role);
							$this->log_dual("Restored role $role");
						}
					}
				}
			}
		}

		$this->log_dual("Active members=$num_active, inactive members=$num_inactive");

		// If option is set and there were membership changes, send email notification
		// There is no checking done to ensure the notification email addresses are valid.
		if (!empty($options['accUM_notification_emails']) &&
			(!empty($new_users) || !empty($expired_users))) {
			$email_addrs = $options['accUM_notification_emails'];

			$title = accUM_get_default_notif_title();
			if (isset($options['accUM_notification_title'])) {
				$title = $options['accUM_notification_title'];
			}
			$content = "The ACC web site has received the following membership changes:\n\n";
			$content .= "---new members---\n";
			foreach ( $new_users as $user ) {
				$content .= $user . "\n";
			}
			$content .= "\n---expired members---\n";
			foreach ( $expired_users as $user ) {
				$content .= $user . "\n";
			}
			$content .= "\n---members which had their roles changed to expired role---\n";
			foreach ( $expired_role_users as $user ) {
				$content .= $user . "\n";
			}
			$content .= "\n---members which had their roles restored because they renewed---\n";
			foreach ( $restored_role_users as $user ) {
				$content .= $user . "\n";
			}
			$this->log_dual("Sending notification email to: $email_addrs");
			$this->log_dual("email title=$title");
			$this->log_dual("email content=$content");
			$rc = wp_mail($email_addrs, $title, $content, 'Content-Type: text/plain; charset=UTF-8' );
			if ($rc) {
				$this->log_dual("Successfully sent notification email to: $email_addrs");
			} else {
				$this->log_dual("Failed to send notification email");
			}
		}

		$api_response['message'] = "success";
		$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string

		return $api_response;
	}


	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/acc_user_importer-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/acc_user_importer-admin.js', array( 'jquery' ), $this->version, false );
		wp_localize_script( $this->plugin_name, 'ajax_object',
			array('url' => admin_url( 'admin-ajax.php' ), 'nonce' =>  wp_create_nonce( "accUserAPI" ))
		);
	}

	/**
	 * This function will log a string to the log file and also accumulate it
	 * in a variable that is sent in the API response, for displaying on the
	 * plugin Update Status window.
	 */
	private function log_dual( $string ) {
		$this->log_local_output($string);
		$GLOBALS['acc_logstr'] .= $string . "<br/>";
	}


	public function log_local_output( $v ) {
		static $new_run = true;
		static $cached_filename = "";

		if ( $this->debug_mode === true ) {
			print_r($v);
			print_r("<br>");
		}

		if ( $this->error_logging === true ) {
			error_log(strval($v));
		}

		/*
		 * Create log file. This is called many times during processing,
		 * so we try to make it efficient. The first time, we scan the directory
		 * and see how old the latest log is. If new, we append to it, but if
		 * old, we create a new one so that things are separated logically.
		 * And the filename is cached for next time around.
		 * Note: the life of a static variable terminates when the script
		 * on the server is done executing the client request.
		 * What I see: after the first batch of 100 members is done, the
		 * script is done and the static variables are reset.
		 */
		//If it's a new run of the script, evaluate which log file to use
		//and cache it for next time around for efficiency.
		if ($new_run) {
			$log_directory = ACC_BASE_DIR . '/logs/';
			$log_date = date_i18n("Y-m-d-H-i-s");
			$log_mode = "wb";
			$log_filename = $log_directory . "log_auto_". $log_date . ".txt";

			//Get list of files, sorted so the latest is on top
			$files2 = scandir($log_directory, SCANDIR_SORT_DESCENDING);

			foreach ($files2 as $filename) {
				if (strpos($filename, "log_auto_") === false) {
					//Not a log file, skip
				} else {
					//Found the latest log file.
					//From filename, extract timestamp and see how long it's been.
					sscanf($filename, "log_auto_%u-%u-%u-%u-%u-%u.txt", $year,$month,$day,$hour,$min,$sec);
					$log_ts = $sec + 60*($min + 60*($hour + 24*($day +31*($month + 12*$year))));
					sscanf($log_date, "%u-%u-%u-%u-%u-%u", $year,$month,$day,$hour,$min,$sec);
					$current_ts = $sec + 60*($min + 60*($hour + 24*($day +31*($month + 12*$year))));
					$elapsed = $current_ts - $log_ts;
					if ($elapsed < 60) {		//less than 60 seconds old
						//The log file is very recent, so append to it.
						$log_mode = "a";
						$log_filename = $log_directory . $filename;
					} else {
						//It's been more than 60s since creation of log file.
						//We must be in a new run of importation. Create a new file.
					}
					break;
				}
			}
			$new_run = false;
			$cached_filename = $log_filename;
		} else {
			//Same run, use the cached filename
			$log_filename = $cached_filename;
			$log_mode = "a";
		}

		$log_content = "\n" . $v;
		$log = fopen($log_filename, $log_mode);
		fwrite( $log, $log_content );
		fclose( $log );

	}

}
