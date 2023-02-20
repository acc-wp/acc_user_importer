<?php


$acc_logstr = "";		//handy global to store log string

class acc_user_importer_Admin {

	private $plugin_name;
	private $version;
	private $debug_mode = false;
	private $error_logging = false;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		//load display
		require plugin_dir_path( __FILE__ ) . '/partials/acc_user_importer-admin-settings.php';
	}

	/**
	 * This is the user import loop (when triggered by a timer)
	 */
	public function begin_automatic_update() {

		//force certificate validation - i.e. speed up authentication process
		add_filter( 'https_local_ssl_verify', '__return_true' );

		$options = get_option('accUM_data');
		$acc_user = $options['accUM_username'];
		$this->log_local_output("Automatic member update starting for section $acc_user");
		$timestamp_start = date_i18n("Y-m-d-H-i-s");

		//request token
		$this->log_local_output("Requesting access token from national office.");
		$access_token_request = $this->request_API_token();

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

			case "getAccessToken":
				$api_response = $this->request_API_token();
				break;

			case "getMemberData":
				$api_response = $this->getMemberData( $_POST['token'], $_POST['offset'] );
				break;

			case "processMemberData":
				$postedData = $_POST['dataset'];
				$postedData = str_replace("\\", "", $postedData);
				$cleanData = json_decode($postedData);
				$arrayData = $this->object_to_array( $cleanData );
				//$this->log_local_output( print_r($arrayData, true) );
				$api_response = $this->proccess_user_data( $arrayData );
				break;

			case "processExpiry":
				$api_response = $this->proccess_expiry();
				break;

		}

		//respond to ajax request and terminate
		echo json_encode( $api_response );
		wp_die();
	}

	/**
	 * Extract members from within the dataset.
	 */
	private function parse_user_data( $user_data ) {

		//array($obj, 'myCallbackMethod'))

		//turn data into usable array with valid keys (the imis keys are painful to work with)
		$user_data = $this->object_to_array( $user_data->Items );
		$user_list = $this->extract_members_from_dataset($user_data);
		return $user_list;
	}

	/**
	 * Helper - Map objects to array for easier block iteration.
	 */
	public function object_to_array ( $object ) {

		if(!is_object($object) && !is_array($object))
			return $object;

		return array_map(array($this, 'object_to_array'), (array) $object);
	}

	/**
	 * Extract members from within the dataset.
	 */
	private function extract_members_from_dataset( $members_dataset ) {

		$new_dataset = [];
		if ( array_key_exists('Values', $members_dataset) ) {

			//iterate through members in dataset
			foreach ( $members_dataset["Values"] as $index => $value ) {
				$new_dataset[] = $this->extract_unique_member_properties($value);
			}
		}

		return $new_dataset;
	}

	/**
	 * Extract membership data from each member record.
	 */
	private function extract_unique_member_properties( $member_dataset ) {

		if ( array_key_exists('Properties', $member_dataset) ) {

			$member_list = []; //container

			//iterate through members in data
			foreach ( $member_dataset["Properties"] as $index => $value ) {
				$member_record = [];

				//loop through data for each member
				if ( is_array( $value ) ) {
				foreach ( $value as $key => $member_data) {

					//store data only if there is a name/value pairing
					if (
					 array_key_exists('Name', $member_data) &&
					 array_key_exists('Value', $member_data) &&
					 !is_array( $member_data['Value'] ) &&
					 strlen($member_data['Value']) > 0 //don't include empty elements into dataset
					 ) {

						$value_name = $member_data['Name'];
						$value_record =  $member_data['Value'];
						$new_record = [$value_name => $value_record];
						$member_record = array_merge( array($value_name => $value_record), $member_record);
					}
				}}

				$member_list[] = $member_record;
			}
		}

		return $member_list[0];
	}

	/**
	 * This function will log a string to the log file and also accumulate it
	 * in a variable that is sent in the API response, for displaying on the
	 * plugin Update Status window.
	 */
	private function log_dual( $string ) {
		$this->log_local_output($string);
		$GLOBALS['acc_logstr'] .= "<br/>" . $string;
	}


	/**
	 * Update Wordpress database with member information.
	 * This is where most of the work gets done.
	 */
	private function proccess_user_data ( $users ) {
		$GLOBALS['acc_logstr'] = "";		//Clear the API response log string

		// Get user-configurable option values
		$options = get_option('accUM_data');

		// Get the loginNameMapping setting
		if (!isset($options['accUM_login_name_mapping'])) {
			$loginNameMapping = accUM_get_login_name_mapping_default();
		} else {
			$loginNameMapping = $options['accUM_login_name_mapping'];
		}
		$this->log_dual("Using $loginNameMapping as login name.");

		// Get the default_role setting
		if (!isset($options['accUM_default_role'])) {
			$this->log_dual("accUM_default_role is empty");
			$default_role = accUM_get_default_role_default();
		} else {
			$default_role = $options['accUM_default_role'];
		}
		$this->log_dual("Using $default_role as default role for new users");


		//create response object
		$api_response = [];
		$this->log_dual("Start processing batch of " . count($users) . " users");

		//fail gracefully is dataset is empty
		if (! ( count($users) > 0 ) ) {
			$api_response['message'] = "error";
			$api_response['errorMessage'] = "Dataset provided has returned an error.";
			$this->log_local_output("Error, nothing to process");
			return $api_response;
		}

		//loop through data and create users
		$update_errors = [];
		$new_users = [];
		$new_users_email = [];
		$updated_users = [];
		$updated_users_email = [];

		foreach ( $users as $id => $user ) {
			//Avoid PHP warnings in case some fields are unpopulated
			$userFirstName= $user["FirstName"] ?? '';
			$userLastName= $user["LastName"] ?? '';
			$userContactId = $user['Contact ID'] ?? '';
			$userImisId = $user['imis_id'] ?? '';
			$userEmail = strtolower($user["Email"] ?? '');
			$userHomePhone = $user["HomePhone"] ?? '';
			$userCellPhone = $user["Cell Phone"] ?? '';
			$userMembership = $user["MEMBERSHIP_N"] ?? '';
			$userExpiry = $user["Membership Expiry Date"] ?? '';
			$userCity = $user["City"] ?? '';

			//Log the info we received for this user
			$userInfoString = $userFirstName . " " . $userLastName;
			$userInfoString .= " " . $userEmail;
			$userInfoString .= " ContactID:" . $userContactId;
			$userInfoString .= " imis_id:" . $userImisId;
			$userInfoString .= " membership#:" . $userMembership;
			$userInfoString .= " home:" . $userHomePhone;
			$userInfoString .= " cell:" . $userCellPhone;
			$userInfoString .= " expiry:" . $userExpiry;
			$userInfoString .= " section:" . $user["PRODUCT_CODE"];
			$this->log_dual("Received " . $userInfoString);

			// Skip users if ContactID is missing and needed.
			if (!is_numeric($userContactId) &&
				$loginNameMapping == 'ContactId') {
					$this->log_dual(" > error, no contactID; skip");
					continue;
			}

			// Skip users if userImisId is missing and needed.
			if (!is_numeric($userImisId) &&
				$loginNameMapping == 'imis_id') {
					$this->log_dual(" > error, no imis_id; skip");
					continue;
			}

			switch($loginNameMapping) {
				case 'ContactId':
					$loginName = $userContactId;
					break;
				case 'Firstname Lastname':
					$loginName = "$userFirstName $userLastName";
					break;
				case 'imis_id':
				default:
					$loginName = $userImisId;
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
				'home_phone' => $userHomePhone,
				'cell_phone' => $userCellPhone,
				'membership' => $userMembership,
				'expiry' => $userExpiry,
				'imis_id' => $userImisId,
				'city' => $userCity
			];

			// Check if ID or email already exist. Both should be unique
			$existingUser = get_user_by('login', $loginName);
			if( !is_a( $existingUser, WP_User::class ) ) {
				$this->log_dual(" > not found by login");
				//Not found by login, search by email
				$existingUser = get_user_by('email', $userEmail);
				if( is_a( $existingUser, WP_User::class ) ) {
					//We found a user. If the name is the same, update it.
					//However if the name is different, it must be a family membership
					//sharing the same email, so abort, let the current user be.
					$this->log_dual(" > found by email, name is " . $existingUser->display_name);
					if ($accUserData['display_name'] != $existingUser->display_name) {
						$this->log_dual(" > error, email already used by someone else, skip");
						continue;
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
				if ($userExpiry < $existingUser->expiry) {
					$this->log_dual(" > warn, received expiry is earlier than local one; using existing expiry date");
					$accUserMetaData['expiry'] = $existingUser->expiry;
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
		$this->log_dual("--Errors updating " . count($update_errors) . " accounts:");
		foreach ( $update_errors as $id => $user ) {
			$this->log_dual(" [" . $id . "] " . var_export($user, true));
		}

		$api_response['usersInData'] = count($users);
		$api_response['newUsers'] = count($new_users);
		$api_response['updatedUsers'] = (count($users) - count($update_errors));
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
		$GLOBALS['acc_logstr'] = "";		//Clear the API response log string

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
				if (isset($user->acc_status)) {
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
				if (isset($user->acc_status)) {
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
					// TODO: Figure out why this is not working - spoiler it is because isset returns true for empty string.
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
	 * Request an authentication token from the national office API.
	 */
	private function request_API_token() {

		$options = get_option('accUM_data');
		$acc_user = $options['accUM_username'];
		$acc_pass = $options['accUM_password'];
		$acc_token_uri = 'https://www.alpineclubofcanada.ca/' . $options['accUM_tokenURI'];

		$post_args = array(
			'headers' => array('content-type' => 'application/x-www-form-urlencoded'),
			'body' => array('grant_type' => 'password', 'username' => $acc_user, 'password' => $acc_pass ),
			'timeout' => 10
		);

		//request token
		$auth_request = wp_remote_post( $acc_token_uri , $post_args );

		//create response object for local api
		$api_response = [];
		$api_response['section'] = $acc_user;

		//check response and return data using local api
		if ( is_wp_error( $auth_request ) ) {
			$api_response['message'] = "error";
			$api_response['errorMessage'] = $auth_request->get_error_message();
		}
		else {
			$auth_request_data = wp_remote_retrieve_body ( $auth_request );
			$auth_data = json_decode($auth_request_data);

			if ( array_key_exists('access_token', (array) $auth_data ) ) {
				$api_response['message'] = "success";
				$api_response['accessToken'] = $auth_data->access_token;
			}
			else {
				$api_response['message'] = "error";
				$api_response['errorMessage'] = $auth_data;
			}
		}

		return $api_response;
	}

	/**
	 * Request a dataset from the national office API.
	 */
	private function getMemberData( $access_token, $offset = 0 ) {

		if ( !$offset ) { $offset = 0; }
		$options = get_option('accUM_data');
		$acc_member_uri = 'https://www.alpineclubofcanada.ca/' . $options['accUM_memberURI'];
		if ($offset > 0) {
			$acc_member_uri .= "&offset=" . $offset;
		}

		$get_args = array(
			'timeout' => 10,
			'sslverify' => false,
			'headers' => array(
				'content-type' => 'application/json',
				'Authorization' => "Bearer " . $access_token
			)
		);

		//request data
		$auth_request = wp_remote_get( $acc_member_uri, $get_args );

		/*
		Returned Data Example
		JSON [
			Offset : 0
			Limit : 100
			Count : 100
			TotalCount : nMembers
			HasNext : true
			NextOffset : 100
			Items, $values, [i]
				Properties, $values, [i]
					Name : Name
					Value: Value
		*/

		//create response object
		$api_response = [];

		//if the post request fails
		if ( is_wp_error( $auth_request ) ) {
			$api_response['message'] = "error";
			$api_response['errorMessage'] = $auth_request->get_error_message();
			return $api_response;
		}

		$auth_request_data = wp_remote_retrieve_body ( $auth_request );
		$auth_request_data = str_replace( ["\t", '$values'], ["", 'Values'], $auth_request_data );
		$auth_request_data = preg_replace( '/"\$type"\:".*",/U', "", $auth_request_data );
		$auth_data = json_decode($auth_request_data);

		if ( array_key_exists('Items', (array) $auth_data ) ) {
			$api_response['message'] = "success";
			$api_response['Count'] = $auth_data->Count;
			$api_response['TotalCount'] = $auth_data->TotalCount;
			$api_response['HasNext'] = $auth_data->HasNext;
			$api_response['NextOffset'] = $auth_data->NextOffset;
			$api_response['Offset'] = $auth_data->Offset + 1;
			$api_response['dataset'] = $this->parse_user_data( $auth_data );
			$first_user_index = $auth_data->Offset + 1;
			$last_user_index = $auth_data->Offset + $auth_data->Count;
			$more_to_come = $auth_data->HasNext ? ", more to come" : ", final batch";
			$this->log_local_output("Received users $first_user_index to $last_user_index of $auth_data->TotalCount $more_to_come");

		} else {
			$api_response['message'] = "error";
			$api_response['errorMessage'] = $auth_data->Message;
		}

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
