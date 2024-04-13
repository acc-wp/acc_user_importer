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

$acc_logstr = "";		//handy global to store log string

class acc_user_importer_Admin {

	private $plugin_name;
	private $version;

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
		'2326' => ['section' => 'VANCOUVER', 'type' => 'student_club'],
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

	//FIXME only the first 5 APIs have been created, the rest are bogus numbers
	private $sectionApiId = array (
			'SQUAMISH' => '1',
			'CALGARY' => '2',
			'MONTRÉAL' => '3',
			'OUTAOUAIS' => '4',
			'OTTAWA' => '5',
			'VANCOUVER' => '6');

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		//load display
		require plugin_dir_path( __FILE__ ) . '/partials/acc_user_importer-admin-settings.php';
	}

	// Get the section API ID
	private function getSectionApiID () {
		return($this->sectionApiId[accUM_getSectionName()]);
	}

	/*
	 * Returns the token for the section the plugin is operating on.
	 * Null is returned if no token has been defined for the section.
	 * Token format: the section name must be as in the accUM_section_api_id list.
	 * Do not put spaces before or after the comma. Here are valid examples
	 * (with bogus token values):
	 * 		SQUAMISH:fpZloKQj8L
	 * 		COLUMBIA MOUNTAINS:123,MONTRÉAL:666,OUTAOUAIS:HJD634
	 */
	private function getSectionToken() {
		$options = get_option('accUM_data');
		$sectionName = accUM_getSectionName();
		$tokenStrings = explode(',', $options['accUM_token']);

		foreach ($tokenStrings as $tokenString) {
			$sectionEntry = [];
			$sectionTokenStrings = explode(':', $tokenString);
			if (count($sectionTokenStrings) != 2) {
				$this->log_dual("Error, each section token should have 2 params");
				return null;
			}
			if ($sectionTokenStrings[0] == $sectionName) {
				// Found the token for the current section
				//$this->log_dual("Token for section {$sectionName} is {$sectionTokenStrings[1]}");
				return $sectionTokenStrings[1];
			}
		}

		$this->log_dual("Error, no token provided for section {$sectionName}");
		return null;
	}


	public function responseErrMsg($api_response) {
		return("Error: " . ($api_response['errorMessage'] ? $api_response['errorMessage'] : 'Unknown.'));
	}


	/**
	 * This is the user import loop (when triggered by a timer)
	 */
	public function begin_automatic_update() {

		$GLOBALS['acc_logstr'] = "";		//Clear the API response log string
		$logfilename = basename(acc_pick_new_log_file("log_auto_")); //Let's store to a new log 
		$this->log_dual("Logging to {$logfilename}");

		//force certificate validation - i.e. speed up authentication process
		add_filter( 'https_local_ssl_verify', '__return_true' );

		$sectionName = accUM_getSectionName();
		$this->log_dual("Automatic member update starting for section $sectionName");
		$timestamp_start = date_i18n("Y-m-d-H-i-s");

		// Take note of the ISO 8601 time of start
		$iso_timestamp_start = date('Y-m-d\TH:i:s\Z');
		//$this->log_dual("iso_timestamp_start={$iso_timestamp_start}");

		// Get the full list of changed members
		$api_response = $this->getChangedMembers();
		if ( $api_response['message'] != "success") {
			$this->log_dual($this->responseErrMsg($api_response));
		} else {
			$done = 0;
			$changeList = $api_response['results'];
			$count = count($changeList);
			$this->log_dual("Received {$count} membership changes");

			// Loop for each changed membership
			while ($done < $count) {

				$api_response = $this->getMemberData($changeList, $done);
				if ( $api_response['message'] != "success") {
					$this->log_dual($this->responseErrMsg($api_response));
					break;
				} else {
					//We have an array of membership information
					$nextToDo = $api_response['nextDataOffset'];
					$done = $nextToDo;
					$memberArray = $api_response['results'];

					$api_response = $this->proccess_user_data($memberArray);
					if ( $api_response['message'] != "success") {
						$this->log_dual($this->responseErrMsg($api_response));
						break;
					}

					// Throttle requests to avoid HTTP errors.
					if ($done < $count) {
						sleep(SLEEP_TIME_AFTER_HTTP);
					}
				}
			}
		}

		// If import was a success, store the date/time where we last did it.
		// This will be used as the changed_since parameter in the next plugin run.
		if ( $api_response['message'] == "success") {
			$options = get_option('accUM_data');
			if (is_array($options)) {
				$options['accUM_since_date'] = $iso_timestamp_start;
				update_option( 'accUM_data',  $options);
				$this->log_dual("On next run, use changed_since={$iso_timestamp_start}");
			} else {
				$this->log_dual("Error getting plugin options");
			}

		}

		$timestamp_end = date_i18n("Y-m-d-H-i-s");
		$this->log_dual("This journey has come to an end.");
		$this->log_dual("Start time: " . $timestamp_start);
		$this->log_dual("End time: " . $timestamp_end);
		$this->log_dual("\n\n");
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
				$logfilename = basename(acc_pick_new_log_file("log_auto_")); //Let's store to a new log 
				$this->log_dual("Logging to {$logfilename}");
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

			// This step is only done when the script is triggered manually.
			// We could eventually execute it periodically (once after each end 
			// of month) if we see that it catches some errors.
			case "processExpiry":
				$api_response = $this->local_db_check();
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

		$this->log_dual("ACC User Importer version {$this->version}");

		$options = get_option('accUM_data');
		$sectionName = accUM_getSectionName();
		$sectionApiId = $this->getSectionApiID();

		// Read token from user settings. Avoid printing token it is sensitive data
		$access_token=$this->getSectionToken();
		//$this->log_dual("Token=" . $access_token);
		if (is_null($access_token)) {
			$api_response['message'] = "error";
			$api_response['log'] = $GLOBALS['acc_logstr'];
			$api_response['errorMessage'] = "No valid token";
			return $api_response;
		}

		if (array_key_exists('accUM_since_date', $options)) {
			$sinceDate = $options['accUM_since_date'];
		}
		if (!isset($sinceDate) || empty($sinceDate)) {
			// Looks like the plugin is running for the first time.
			// Use 2023-01-01, this should import all memberships.
			$sinceDate = "2023-01-01";
			$this->log_dual("No sinceDate specified, using {$sinceDate}");
		}

		$this->log_dual("Retrieving changed members since {$sinceDate} " .
				        "for section {$sectionName}, API {$sectionApiId}");

		// Create response object for local api
		$api_response = [];
		$count = 0;
		$changeList = [];
		$httpRequest = 'https://2mev.com/rest/v2/member-apis/' . $sectionApiId .
		               '/changed_members/?changed_since=' . $sinceDate;

		$get_args = array('headers' => array('content-type' => 'application/json',
										     'Authorization' => "Bearer " . $access_token));

		do {
			$currentTime = date_i18n("Y-m-d-H-i-s");
			$this->log_dual("Request sent @{$currentTime}: {$httpRequest}");
			$acc_response = wp_remote_get($httpRequest, $get_args);

			if (is_wp_error($acc_response)) {
				$this->log_dual("wp_remote_get error" . $acc_response->get_error_message());
				$api_response['message'] = "error";
				$api_response['log'] = $GLOBALS['acc_logstr'];
				$api_response['errorMessage'] = $acc_response->get_error_message();
				return $api_response;
			}

			$acc_response_data = wp_remote_retrieve_body ( $acc_response );
			//$this->log_dual("ACC response=" . $acc_response_data);
			$acc_response_data = json_decode($acc_response_data);

			$responseMsg = wp_remote_retrieve_response_message($acc_response);
			if ($responseMsg != 'OK') {
				$this->log_dual("HTTP error={$responseMsg}");
				$api_response['message'] = "error";
				$api_response['errorMessage'] = "HTTP error={$responseMsg}";
				$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
				return $api_response;
			}

			if ( !isset($acc_response_data->count )) {
				$api_response['message'] = "error";
				$api_response['log'] = $GLOBALS['acc_logstr'];
				$api_response['errorMessage'] = "No count in Changed Members API response";
				return $api_response;
			}

			// $this->log_dual("count=" . $acc_response_data->count);
			// $this->log_dual("next=" . $acc_response_data->next);
			// $this->log_dual("previous=" . $acc_response_data->previous);
			// $this->log_dual("members=" . json_encode($acc_response_data->results));
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
			$this->log_dual("Warning, server said there would be {$acc_response_data->count}
			                entries but we actually received {$count}");
		}

		$this->log_dual("total count=" . $count);
		$this->log_dual("total members=" . json_encode($changeList));
		$api_response['count'] = $count;
		$api_response['results'] = $changeList;
		$api_response['message'] = "success";
		$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
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
	private function getMemberData( $changeList, $offset = 0 ) {

		//create response object
		$api_response = [];

		//Compute how many members we want to process
		if ( !$offset ) { $offset = 0; }
		$remaining = sizeof($changeList)-$offset;
		$numToDo = min ($remaining, MEMBER_API_MAX_USERS);
		$this->log_dual("remaining={$remaining}, will fetch {$numToDo}");

		// Select the next N entries from the list of changed members.
		$changeSubset = array_slice($changeList, $offset, $numToDo);
		$subsetString = implode(",", $changeSubset);

		$sectionApiId = $this->getSectionApiID();
		$httpRequest = "https://2mev.com/rest/v2/member-apis/{$sectionApiId}/fetch/?member_number=" . $subsetString;
		$access_token = $this->getSectionToken();

		$get_args = array('headers' => array('content-type' => 'application/json',
										     'Authorization' => "Bearer " . $access_token));

		$currentTime = date_i18n("Y-m-d-H-i-s");
		$this->log_dual("Request sent @{$currentTime}: {$httpRequest}");
		$acc_response = wp_remote_get( $httpRequest, $get_args );

		//if the post request fails
		if ( is_wp_error( $acc_response ) ) {
			$this->log_dual("wp_remote_get error" . $acc_response->get_error_message());
			$api_response['message'] = "error";
			$api_response['errorMessage'] = $acc_response->get_error_message();
			$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
			return $api_response;
		}

		$acc_response_data = wp_remote_retrieve_body ( $acc_response );
		$memberData = (array) json_decode($acc_response_data, true);
		$count = sizeof ($memberData);
		//$this->log_dual("acc_response_data={$acc_response_data}");     //for debug only

		$responseCode = wp_remote_retrieve_response_code($acc_response);
		$responseMsg = wp_remote_retrieve_response_message($acc_response);
		// Here we could test for code 429 (sending data too fast to server who
		// rejects because of throttling). And if it happens, sleep for 60s and retry.
		// But I think we will no longer hit this issue thanks to the preventive
		// sleep after each request.
		if ($responseCode != 200) {
			$this->log_dual("HTTP error {$responseCode} ({$responseMsg})");
			$api_response['message'] = "error";
			$api_response['errorMessage'] = "HTTP error={$responseMsg}, code={$responseCode}";
			$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
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
		// 	$this->log_dual("Error, member API returned " . $count . " members instead of " . $numToDo);
		// 	$api_response['message'] = "error";
		// 	$api_response['errorMessage'] = "Member API returned " . $count . " members instead of " . $numToDo;
		// 	$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
		// 	return $api_response;
		// }

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
		'youth' => 1,
		'student_club' => 0,
	);

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
	 * Returns true if the membership status is valid.
	 */
	private function validMembershipStatus ( $membershipStatus ) {
		return ($membershipStatus == "ISSU" || $membershipStatus == "PROC");
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

		// Get the accUM_new_user_role_action setting
		if (!isset($options['accUM_new_user_role_action'])) {
			$new_user_role_action = accUM_get_new_user_role_action_default();
		} else {
			$new_user_role_action = $options['accUM_new_user_role_action'];
		}

		// Get the new_user_role_value setting
		if (!isset($options['accUM_new_user_role_value'])) {
			$this->log_dual("accUM_new_user_role_value is empty");
			$new_user_role_value = accUM_get_new_user_role_value_default();
		} else {
			$new_user_role_value = $options['accUM_new_user_role_value'];
		}

		if ($new_user_role_action == 'set_role') {
			$this->log_dual("New users will be set with role $new_user_role_value");
		} else if ($new_user_role_action == 'add_role') {
			$this->log_dual("New users will be added role $new_user_role_value");
		}

		// Get the accUM_ex_user_role_action setting
		if (!isset($options['accUM_ex_user_role_action'])) {
			$ex_user_role_action = accUM_get_ex_user_role_action_default();
		} else {
			$ex_user_role_action = $options['accUM_ex_user_role_action'];
		}

		// Get the ex_user_role_value setting
		if (!isset($options['accUM_ex_user_role_value'])) {
			$ex_user_role_value = accUM_get_ex_user_role_value_default();
		} else {
			$ex_user_role_value = $options['accUM_ex_user_role_value'];
		}

		if ($ex_user_role_action == 'set_role') {
			$this->log_dual("Expired users will be set with role $ex_user_role_value");
		} else if ($ex_user_role_action == 'remove_role') {
			$this->log_dual("Expired users will be removed role $ex_user_role_value");
		}

		// Get the loginNameMapping setting
		if (!isset($options['accUM_login_name_mapping'])) {
			$loginNameMapping = accUM_get_login_name_mapping_default();
		} else {
			$loginNameMapping = $options['accUM_login_name_mapping'];
		}
		$this->log_dual("Using $loginNameMapping as login name.");

		// Get the readonly_mode setting
		$readonly_mode = accUM_get_readonly_mode();
		if ($readonly_mode) {
			$this->log_dual("Read-only test mode, will not update user database");
		}

		$sectionName = accUM_getSectionName();

		//loop through the received data and create users
		$update_errors = [];
		$new_users = [];
		$new_users_email = [];
		$updated_users = [];
		$updated_users_email = [];
		$new_active_users = [];
		$expired_users = [];
		$warnings = [];

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
			//Note the 2M system only has 1 phone number per user.
			$userCellPhone = $user["phone_number"] ?? '';
			$userMemberNumber = $user["member_number"] ?? '';
			$receivedMemberships = $user['memberships'];
			$this->log_dual(json_encode($user));

			// It is possible for the user to have multiple memberships.
			// We are only interested in memberships for the section the plugin
			// is operating for. Among those memberships, select the one with
			// greater date (most in the future).
			// The membership status is read and printed in the log, however
			// we only look at the valid_to date in order to decide if a user is
			// valid or not, which is equivalent according to the API spec.
			$userMembershipType = "";
			$userMembershipSection = "";
			$userMembershipExpiry = "1900-01-01";
			$userMembershipStatus = "UNKNOWN_STATUS";
			foreach ( $receivedMemberships as $membership ) {
				$mId = $membership['membership_group']['id'];
				$mSection = $this->membershipTable[$mId]['section'];
				$mType = $this->membershipTable[$mId]['type'];
				// Keep the YYYY-MM-DD, but truncate the time portion if it was there.
				$mExpiry = substr($membership['valid_to'], 0, 10);
				//$this->log_dual("detected membership: {$mId} {$mSection} {$mType} {$mExpiry}");
				if ($mSection == $sectionName) {
					if (empty($userMembershipType)) {
						//Found a first membership
						$userMembershipType = $mType;
						$userMembershipSection = $mSection;
						$userMembershipExpiry = $mExpiry;
						$userMembershipStatus = $membership['identity_membership_status'] ?? '';
					} else if ($mExpiry > $userMembershipExpiry) {
						//Found a membership with a later expiry, take note of it.
						$userMembershipType = $mType;
						$userMembershipSection = $mSection;
						$userMembershipExpiry = $mExpiry;
						$userMembershipStatus = $membership['identity_membership_status'] ?? '';
						$this->log_dual("> Better expiry date");
					} else if ($mExpiry == $userMembershipExpiry &&
					    $this->compareMembershipType($userMembershipType, $mType)) {
						//Same expiry date, but found a better membership type.
						$userMembershipType = $mType;
						$userMembershipSection = $mSection;
						$userMembershipExpiry = $mExpiry;
						$userMembershipStatus = $membership['identity_membership_status'] ?? '';
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
			$userInfoString .= " cell:" . $userCellPhone;
			$userInfoString .= " type:" . $userMembershipType;
			$userInfoString .= " section:" . $userMembershipSection;
			$userInfoString .= " expiry:" . $userMembershipExpiry;
			$userInfoString .= " status:" . $userMembershipStatus;
			$this->log_dual("Received " . $userInfoString);

			//Validate we have received mandatory fields.
			if (empty($userMemberNumber)) {
				$this->log_dual(" > error, no member number; skip");
				continue;
			}

			//Safety check in case firstname and lastname are empty
			if (empty($userFirstName) && empty($userLastName)) {
				$this->log_dual(" > error, user has no name; skip");
				continue;
			}

			//Safety check in case member is not a member of this section
			if (empty($userMembershipSection))
			{
				$this->log_dual("> error, user is not a member of this section; skip");
				continue;
			}

			// Issue a harmless warning in the log if we see the 2mev API returned discrepancies
			$membershipExpired = ($userMembershipExpiry < date("Y-m-d"));
			$membershipStatus = $this->validMembershipStatus($userMembershipStatus);
			if ($membershipExpired && $membershipStatus) {
				$this->log_dual("Warning, data discrepancy: membership date expired but status is good!");
			} else if (!$membershipExpired && !$membershipStatus) {
				$this->log_dual("Warning, data discrepancy: membership date good but status is bad!");
			}

			switch($loginNameMapping) {
				case 'Firstname Lastname':
					$loginName = "$userFirstName $userLastName";
					break;
				case 'member_number':
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
				'first_name'	=>	$userFirstName,
				'last_name'		=>	$userLastName,
				'display_name'	=>	$userFirstName . " " . $userLastName,
				'user_email'	=>	$userEmail,
			];

			$accUserMetaData = [
				'cell_phone' => $userCellPhone,
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
				//to by the user. On 2024-01-10 I saw a similar case where a member in 2M
				//moved to a new member_number. And the API returned 2 records, one with
				//valid membership and another with expired membership. In such case the
				//special rule prevents the expired record to overwrite the valid one.
				//I think we can do a straight string compare, given the YYYY-MM-DD-TIME format.
				//But truncate strings to remove the time portion, it is not needed.
				//The old ACC API used to give a time portion we no longer want.
				$existingUserExpiryDate = substr($existingUser->expiry, 0, 10);
				//$this->log_dual(" > userMembershipExpiry={$userMembershipExpiry}, existingUserExpiryDate={$existingUserExpiryDate}");
				if ($userMembershipExpiry < $existingUserExpiryDate) {
					$this->log_dual(" > warning, received expiry is earlier than local $existingUserExpiryDate; skip");
					$warnings[] = "warning, rxd expiry for user {$accUserData['display_name']} is earlier than in local DB\n";
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
				if (!$readonly_mode) {
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
					}

					// Trigger hook if expiry date changed (updated membership)
					if (in_array('expiry', $updatedFields)) {
						do_action('acc_membership_renewal', $existingUser->ID);
					}

					// Check for actions (ex: send welcome or goodbye email)
					$outcome = $this->checkForUserStateChange($existingUser->ID);
					if ($outcome == "active") {
						$new_active_users[] = "$existingUser->display_name  ($existingUser->user_email)";
					} else if ($outcome == "inactive") {
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
				$this->log_dual(" > error: invalid email, cannot create new user account.");
				$update_errors[] = $user;
				continue;
			}

			if (!$readonly_mode) {	// Skip the rest if we are in read-only test mode

				// Skip the rest if the user does not have a valid membership (ex: expired already)
				if (!$this->validMembershipStatus($userMembershipStatus)) continue;

				//--------CREATE NEW USER-----
				$this->log_dual(" > email not found on any other users");
				$new_users[] = $accUserData['display_name'];
				$new_users_email[] = $userEmail ;
				$accUserData["user_pass"] = wp_generate_password(20);
				$accUserData["role"] = $new_user_role_value;
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
				//Indicate this user was inactive. Will transition to active in checkForUserStateChange.
				$accUserMetaData['acc_status'] = 'inactive';
				foreach ($accUserMetaData as $field => $value) {
					update_user_meta($userID, $field, $value);
				}

				// Execute hooks for new membership
				do_action('acc_new_membership', $userID);

				// Check for actions (ex: send welcome or goodbye email)
				$outcome = $this->checkForUserStateChange($userID);
				if ($outcome == "active") {
					$new_active_users[] = "{$accUserData['display_name']} ({$accUserData['user_email']})";
				} else if ($outcome == "inactive") {
					$expired_users[] = "{$accUserData['display_name']} ({$accUserData['user_email']})";
				}

			}

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
		$this->log_dual("--" . count($new_active_users) . " members transitioned to Active:");
		foreach ( $new_active_users as $user ) {
			$this->log_dual("  " . $user);
		}
		$this->log_dual("--" . count($expired_users) . " members Expired:");
		foreach ( $expired_users as $user ) {
			$this->log_dual("  " . $user);
		}
		if (count($update_errors) != 0) {
			$this->log_dual("--Errors updating " . count($update_errors) . " accounts:");
		}
		foreach ( $update_errors as $id => $user ) {
			$this->log_dual(" [" . $id . "] " . var_export($user, true));
		}

		$this->send_admin_email($new_active_users, $expired_users, $warnings);

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
	 * See if it's a new valid user or a user that expired. If so, we might have actions
	 * to take, such as sending emails.
	 */
	private function checkForUserStateChange ($user_id) {

		$outcome = "na";

		// Get user-configurable option values
		$options = get_option('accUM_data');

		// Get the accUM_new_user_role_action setting
		if (!isset($options['accUM_new_user_role_action'])) {
			$new_user_role_action = accUM_get_new_user_role_action_default();
		} else {
			$new_user_role_action = $options['accUM_new_user_role_action'];
		}

		// Get the new_user_role_value setting
		if (!isset($options['accUM_new_user_role_value'])) {
			$new_user_role_value = accUM_get_new_user_role_value_default();
		} else {
			$new_user_role_value = $options['accUM_new_user_role_value'];
		}

		// What should we do to ex-users role?
		if (!isset($options['accUM_ex_user_role_action'])) {
			$ex_user_role_action = accUM_get_ex_user_role_action_default();
		} else {
			$ex_user_role_action = $options['accUM_ex_user_role_action'];
		}

		// Get the accUM_ex_user_role_value setting
		if (!isset($options['accUM_ex_user_role_value'])) {
			$ex_user_role_value = accUM_get_ex_user_role_value_default();
		} else {
			$ex_user_role_value = $options['accUM_ex_user_role_value'];
		}

		$user = get_userdata($user_id);
		if( !is_a( $user, WP_User::class ) ) {
			$this->log_dual("Error when checking for user state, userid $user_id is invalid");
			return $outcome;
		}


		if ($this->is_user_expired($user)) {
			// User is expired
			if (!empty($user->acc_status)) {
				if ($user->acc_status == 'active') {
					// User was active, now expired.
					update_user_meta($user->ID, 'acc_status', 'inactive');
					$this->log_dual("user $user->ID $user->display_name transitioned to " .
									"inactive, send goodbye email if enabled");
					acc_send_goodbye_email($user->ID);
					do_action("acc_member_goodbye", $user->ID);		//action hook
					$outcome = "inactive";

					// If needed, change the user role to the expired role.
					// Do not change roles of administrators to prevent lockout.
					$user_roles = $user->roles;
					if ($ex_user_role_action == 'set_role' &&
						!in_array('administrator', $user_roles, true) && 
						(count($user_roles) != 1 ||
						!in_array($ex_user_role_value, $user_roles, true))) {
						$this->log_dual("Changing user $user->ID $user->display_name role to $ex_user_role_value");
						$user->set_role($ex_user_role_value);
					} elseif ($ex_user_role_action == 'remove_role' &&
						!in_array('administrator', $user_roles, true) &&
						in_array($ex_user_role_value, $user_roles, true)) {
						$this->log_dual("Removing role $ex_user_role_value from user $user->ID $user->display_name");
						$user->remove_role($ex_user_role_value);
					}
				}
			} else {
				// User did not have a acc_status field. Must be the first time this
				// new plugin executes. Set the field but do not send email.
				update_user_meta($user->ID, 'acc_status', 'inactive');
				$this->log_dual("Initial update of user $user->ID $user->display_name to inactive");
			}

		} else {
			// User has a valid membership
			if (!empty($user->acc_status)) {
				if ($user->acc_status == 'inactive') {
					// User was inactive, now active.
					update_user_meta($user->ID, 'acc_status', 'active');
					$this->log_dual("user $user->ID $user->display_name transitioned to " .
									"active, send welcome email if enabled");
					acc_send_welcome_email($user->ID);
					do_action("acc_member_welcome", $user->ID);		//action hook
					$outcome = "active";

					// If needed, change the user role to the new member role.
					$user_roles = $user->roles;
					if ($new_user_role_action == 'set_role' &&
						(count($user_roles) != 1 ||
						!in_array($new_user_role_value, $user_roles, true))) {
						$this->log_dual("Changing user $user->ID $user->display_name role to $new_user_role_value");
						$user->set_role($new_user_role_value);
					} elseif ($new_user_role_action == 'add_role' &&
						!in_array($new_user_role_value, $user_roles, true)) {
						$this->log_dual("Adding role $new_user_role_value to user $user->ID $user->display_name");
						$user->add_role($new_user_role_value);
					}
				}
			} else {
				// User did not have a acc_status field. Must be the first time this
				// new plugin executes. Set the field but do not send email.
				update_user_meta($user->ID, 'acc_status', 'active');
				$this->log_dual("Initial update of user $user->ID $user->display_name to active");
			}
		}

		return $outcome;
	}


	/**
	 * Go over our local user database and do some sanity checks. 
	 * This is mainly to cover the case where the 2mev API would fail
	 * to notify us of a change.  This would cause expired users to go
	 * unnoticed and potentially with the wrong role.  If the periodic
	 * membership sync works fine, this function will find nothing to do.
	 * 
	 * What we do: we basically check for expired users and if any are
	 * found, we potentially send the goodbye email and change the role
	 * (as per config).
	 */
	private function local_db_check () {

		$readonly_mode = accUM_get_readonly_mode();
		if ($readonly_mode) {
			$this->log_dual("Read-only test mode, skipping local DB check");
			$api_response['message'] = "success";
			$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string
			return $api_response;
		}

		$api_response = [];					//create response object
		$new_users = [];
		$expired_users = [];
		$warnings = [];
		$user_ids = get_users(['fields' => 'ID']);

		foreach ( $user_ids as $user_id ) {

			$outcome = $this->checkForUserStateChange($user_id);
			if ($outcome == "active" || $outcome == "inactive") {
				$user = get_userdata($user_id);

				if ($outcome == "active") {
					$new_users[] = "$user->display_name  ($user->user_email)";
				} else if ($outcome == "inactive") {
					$expired_users[] = "$user->display_name  ($user->user_email)";
				}
			}
		}

		$this->send_admin_email($new_users, $expired_users, $warnings);

		$api_response['message'] = "success";
		$api_response['log'] = $GLOBALS['acc_logstr'];	//Return the big log string

		return $api_response;
	}


	/**
	 * If the option is configured, send a summary email to the admin.
	 * The email is only sent if needed (there were new users, expired users or warnings).
	 * There is no checking done to ensure the notification email addresses are valid.
	 */
	private function send_admin_email ($new_users, $expired_users, $warnings) {

		$options = get_option('accUM_data');
		if (!empty($options['accUM_notification_emails']) &&
			(!empty($new_users) || !empty($expired_users) || !empty($warnings))) {

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
			$content .= "\n---warnings---\n";
			foreach ( $warnings as $warning ) {
				$content .= $warning . "\n";
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
		acc_log($string);
		$GLOBALS['acc_logstr'] .= $string . "<br/>";
	}

}