<?php

/**
 * @link              https://www.facebook.com/razpeel
 * @package           acc_user_importer
 * @subpackage        acc_user_importer/admin
 *
 * @wordpress-plugin
 * Plugin Name:       ACC User Importer
 * Plugin URI:        http://accvancouver.ca
 * Description:       A plugin for synchronizing users from the <a href="http://alpineclubofcanada.ca">Alpine Club of Canada</a> national office.
 * Version:           1.0.2
 * Author:            Raz Peel (edits by KFG and Francois Bessette)
 * Author URI:        https://www.facebook.com/razpeel
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       acc_user_importer
 * Domain Path:       /languages
 */

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
	 * The journey of 1000 miles begins with a single footstep.
	 */
	public function begin_automatic_update () {
		
		//force certificate validation - i.e. speed up authentication process
		add_filter( 'https_local_ssl_verify', '__return_true' );
		
		$this->log_local_output("Automatic member update starting");
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
		    
				$this->log_local_output("A new door has opened.");
				$this->log_local_output('There are ' . $get_attempts_remaining . " API attempts remaining to get membership data.");
				$this->log_local_output('Starting at position: ' . $data_offset);
				
				$has_next = false; //default to not having more data
				
				//request next dataset with token
				$this->log_local_output("Requesting membership data using token: " . substr($access_token_request['accessToken'], 0, 10) . ".");
				$member_data_request = $this->getMemberData( $access_token_request['accessToken'], $data_offset );
				
				//did we get data?
				if ( $member_data_request['message'] == "success") {

					$this->log_local_output("Membership data received.");

					//FLAG: éditions par KFG en juin 2020 => ajouter les conditions pour rawdata
					if(!empty($member_data_request['rawData'])){
						$this->log_local_output("--" . $member_data_request['rawData']->Count . " records expected.");
						$this->log_local_output("--" . count($member_data_request['rawData']->Items->Values) . " valid records provided.");
						$this->log_local_output("--" . $member_data_request['rawData']->TotalCount . " total records available.");
					} else {
						$this->log_local_output("Somehow the data requested was empty. Contact an administrator for further information. (Check added by Karine F.G.)");
						
					}
					
					//parse data
					$this->log_local_output("Trying to update user database with new dataset.");
					$proccess_request = $this->proccess_user_data( $member_data_request['dataset'] );
					$this->log_local_output("Membership data processed.");
					
					//move offset ahead if there is more data
					//FLAG: éditions par KFG en juin 2020 => ajouter les conditions pour rawdata
					if(!empty($member_data_request['rawData'])){
						if ($member_data_request['rawData']->HasNext == 1) {
							$this->log_local_output("More membership data found, restarting data loop.");
							$has_next = true;
							$data_offset = $member_data_request['rawData']->NextOffset;
							$this->log_local_output("Next offset: " . $member_data_request['rawData']->NextOffset . ".");
						}
						else {
							$this->log_local_output("No more membership data indicated, ending data loop.");
						}
					} 
					
				}
				//failed to get data - if attempts remain, try again
				else {
					$this->log_local_output("Error: " . ($member_data_request['errorMessage'] ? $member_data_request['errorMessage'] : 'Unknown.'));
					$this->log_local_output("Error: " . $get_attempts_remaining . " attempts remaining to get API data.");
					$has_next = true;
					$get_attempts_remaining = $get_attempts_remaining - 1;
				}
				
				$this->log_local_output("The door on this data loop has closed.");
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
		$api_response = Array();
		
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
	 * Update Wordpress database with member information.
	 */
	private function proccess_user_data ( $users ) {
		
		//create response object
		$api_response = Array();
		$api_response['log'] = "Processing has begun.<br>";
		$this->log_local_output("Processing has begun.");
		
		
		//fail gracefully is dataset is empty
		if (! ( count($users) > 0 ) ) {
			$api_response['message'] = "error";
			$api_response['errorMessage'] = "Dataset provided has returned an error.";
			return $api_response;
		}
		
		//remove record entirely if membership number is missing
		$malformed_users = [];
		foreach ( $users as $key => $user ) {
			$member_id = $user['MEMBERSHIP_N'];
			if (! (is_numeric($member_id) )) {
				$malformed_users[] = $user;
				unset( $users[$key] );
				continue;
			}
		}
		
		//log outcome of removing users without a membership number
		if (count($malformed_users) > 0) {
			$api_response['log'] .= "Removed " . count($malformed_users) . " record(s) without membership numbers.<br/>";
			$this->log_local_output("Removed " . count($malformed_users) . " record(s) without membership numbers.");
			foreach ( $malformed_users as $id => $user ) {
				$api_response['log'] .= "&nbsp;&nbsp;[" . ($id + 1) . "] " . $user['FirstName'] . " " . $user['LastName'] . "<br/>";
				$this->log_local_output("  [" . ($id + 1) . "] " . $user['FirstName'] . " " . $user['LastName']);
			}
		}
		
		//sanitize - i.e. remove keys/values that aren't in this list
		foreach ( $users as $key => $user ) {
			
			$allowed_columns = array(	//'PRODUCT_CODE',
										//'MemberType',
										'Membership Expiry Date',
										'Email',
										'Cell Phone',
										'HomePhone',
										//'Address1',
										//'Address2',
										'City',
										'LastName',
										'FirstName',
										//'Contact ID',
										'MEMBERSHIP_N'
									);
									
			foreach ( $user as $value_key => $value ) {
				
				if (! in_array( $value_key, $allowed_columns ) ) {
					unset( $user[$value_key] );
				}
				$users[$key] = $user; //save into correct scope
			}
		}
		
		//loop through data and create users
		$update_errors = [];
		$new_users = [];
		$role_refreshed = [];

		// Get the configured default role for new users
		$default_role = get_option( "acc_role_editor", "subscriber" );
		$this->log_local_output("For new users, default role=" . $default_role);

		// Existing users which are expired needs to have their role refreshed
		$role_expiry1 = get_option("acc_expiry_lvl_1");
		$role_expiry2 = get_option("acc_expiry_lvl_2");
		$this->log_local_output("For existing users, refresh role if:" . $role_expiry1 . " or " . $role_expiry2);


		foreach ( $users as $id => $user ) {
	
			//everyone should have an acc-membership number
			//$user_contact_id = $user['MEMBERSHIP_N'];
			$user_contact_id = $user['FirstName'] . " " . $user['LastName'];
			
			//using first-names to help make a unique identifier (id)
			//$user_contact_id .= strval( ord($user['FirstName']) );			
			$api_response['log'] .= "<br/>[" . ($id + 1) . "] Processing: <u>" . $user['FirstName'] . " " . $user['LastName'] . "</u> (" . $user_contact_id . ")<br/>";
			$this->log_local_output("Searching for: " . $user['FirstName'] . " " . $user['LastName'] . " (" . $user_contact_id . ")");
	
			//populate a defined user object that wordpress can use
			$update_this_user = true;

			//FLAG: editions par kfg en juin 2020 => Ajouté les conditions
			if(!empty($user["Email"]))
				$user_email = strtolower($user['Email']);


			$user_data = array(
				'first_name'	=>	$user['FirstName'],
				'last_name'		=>	$user['LastName'],
				'display_name'	=>	$user['FirstName'] . " " . $user['LastName'],
				'user_nicename'	=>	strtolower( $user['FirstName'] ) . "-" . strtolower( $user['LastName'] ),
				'user_login'	=>	$user_contact_id,
				'user_email'	=>	$user_email,
				'user_pass'		=>	null,
			);
			
			//check if ID already exist
			$user_id = username_exists($user_contact_id);
			
			
			//if user was found
			if( is_numeric( $user_id ) ) {
				
				//append ID to data object - wordpress will update the user instead of create a new one
				$user_data = array_merge( array('ID' => $user_id), $user_data);
				$api_response['log'] .= "&nbsp;&gt; found <em>(unique #" . $user_contact_id . ", user #" . email_exists($user_email) . ")</em><br/>";
				$this->log_local_output(" > found (unique #" . $user_contact_id . ", user #" . email_exists($user_email) . ")");

				// Get the user object.
				$user_meta = get_userdata($user_id);
				// Get all the user roles as an array.
				$user_roles = $user_meta->roles;
				// Check if user has expired or ex-member role. If so, update role to default
				if ( in_array( $role_expiry1, $user_roles, true ) ) {
					$this->log_local_output("User is " . $role_expiry1 . ", refreshing role to " . $default_role);
					$user_data["role"] = $default_role;
					$role_refreshed[] = $user_contact_id;
				} elseif (in_array( $role_expiry2, $user_roles, true ) ) {
					$this->log_local_output("User is " . $role_expiry2 . ", refreshing role to " . $default_role);
					$user_data["role"] = $default_role;
					$role_refreshed[] = $user_contact_id;
				}
			}
			
			//if no ID was matched, try find email
			elseif ( strlen($user_email) > 1 ) {
				$api_response['log'] .= "&nbsp;&gt; unique ID not found<br/>";
				$this->log_local_output(" > unique ID not found");
				$user_id = email_exists($user_email);
				
				
				//if email exists in different contact ID, skip updating user
				if ( is_numeric($user_id) ) {	
					$api_response['log'] .= "&nbsp;&gt; duplicate email found on another user <em>(user #" . $user_id . ")</em><br/>";
					$api_response['log'] .= "&nbsp;&gt; user update skipped<br/>";
					$this->log_local_output(" > duplicate email found on another user (user #" . $user_id . ")");
					$this->log_local_output(" > user update skipped");
					$user_data = array_merge( array('ID' => $user_id), $user_data); //point to correct ID (future proofing)
					$update_this_user = false;
					$update_errors[] = $user;
				}
				
				//no duplicate email exists
				else {
					$api_response['log'] .= "&nbsp;&gt; email not found on any other users<br/>";
					$api_response['log'] .= "&nbsp;&gt; creating new user account<br/>";
					$this->log_local_output(" > email not found on any other users");
					$this->log_local_output(" > creating new user account");
					$new_users[] = $user_contact_id;
					$user_data["role"] = $default_role;
				}
			}
			
			//didn't find a user by contact ID, and don't have an email to search for either
			else {
				$api_response['log'] .= "&nbsp;&gt; <b>error</b>: no email given, cannot create new user account.<br/>";
				$this->log_local_output(" > error: no email given, cannot create new user account.");
				$update_this_user = false;
				$update_errors[] = $user['FirstName'] . " " . $user['LastName'] . " (" . $user_contact_id . ")";
			}
			

			//only run if indicated
			if ($update_this_user) {

				$wp_user = wp_insert_user( $user_data ) ;
				
				//attempt to add user
				if ( is_wp_error($wp_user) ) {
					
					$api_response['log'] .= "&nbsp;&gt; failed to update user<br/>";
					$api_response['log'] .= "&nbsp;&gt; WP:" . $wp_user->get_error_message() . "<br/>";
					$this->log_local_output(" > failed to update user");
					$this->log_local_output(" > WP:" . $wp_user->get_error_message());
				}
			
				//add user meta if update succeded
				else {
					
					//FLAG: editions par kfg en juin 2020 => Ajouté les conditions
					if(!empty($user["HomePhone"]))
						update_user_meta( $user_id, 'home_phone', $user['HomePhone'] );
					if(!empty($user["Cell Phone"]))
						update_user_meta( $user_id, 'cell_phone', $user['Cell Phone'] );
					if(!empty($user["MEMBERSHIP_N"]))
						update_user_meta( $user_id, 'membership', $user['MEMBERSHIP_N'] );
					if(!empty($user["Membership Expiry Date"]))
						update_user_meta( $user_id, 'expiry', $user['Membership Expiry Date'] );
					if(!empty($user["City"]))
						update_user_meta( $user_id, 'city', $user['City'] );

				}
			}
			
		} //end user loop
		
		//log outcomes
		$api_response['log'] .= "<br/>Processing complete.";
		$api_response['log'] .= "<br/>--Parsed data for " . count($users) . " people.";
		$api_response['log'] .= "<br/>--Refreshed roles for " . count($role_refreshed) . " people.";
		$api_response['log'] .= "<br/>--Created accounts for " . count($new_users) . " people.";
		$api_response['log'] .= "<br/>--Updated data for " . (count($users) - count($update_errors)) . " people.";
		$api_response['log'] .= "<br/>--Errors updating " . count($update_errors) . " accounts.";
		
		foreach ( $update_errors as $id => $user ) {
			$api_response['log'] .= 
			"<br/>--[" 
				. $id 
				. "] " 
				. var_export($user, true); //FLAG: edition par kfg en juin 2020, fixed so it would print as a string and not an array
		}
		
		//error log
		$this->log_local_output("Processing complete.");
		$this->log_local_output("--Parsed data for " . count($users) . " people.");
		$this->log_local_output("--Refreshed roles for " . count($role_refreshed) . " people.");
		$this->log_local_output("--Created accounts for " . count($new_users) . " people.");
		$this->log_local_output("--Updated data for " . (count($users) - count($update_errors)) . " people.");
		$this->log_local_output("--Errors updating " . count($update_errors) . " accounts.");
		
		foreach ( $update_errors as $id => $user ) {
			$this->log_local_output(
				" [" 
				. $id 
				. "] " 
				. var_export($user, true) //FLAG: edition par kfg en juin 2020, fixed so it would print as a string and not an array
				. "."
			);
		}
		
		$api_response['usersInData'] = count($users);
		$api_response['roleRefreshed'] = count($role_refreshed);
		$api_response['newUsers'] = count($new_users);
		$api_response['updatedUsers'] = (count($users) - count($update_errors));
		$api_response['usersWithErrors'] = count($update_errors);
		$api_response['message'] = "success";
		
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
		$api_response = Array();
		
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
		$api_response = Array();
			
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
		}
		else {
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
		
		if ( $this->debug_mode === true ) {
			print_r($v);
			print_r("<br>");
		}
		
		if ( $this->error_logging === true ) {
			error_log(strval($v));
		}

		/*
		 * Create log files. (code from KFG)
		 * Very inefficient. Goes through the directory and try to find an existing log file
		 * with a date/timestamp within the same hour. If so, reuse the file (append).
		 * If not, create a new one.
		 */
		if( is_plugin_active( 'acc-periodic-sync/index.php' ) ) {
			$log_directory  = KFG_BASE_DIR . '/logs/acc/';
			$today_date = date_i18n("Y-m-d-H");
			$log_date = date_i18n("Y-m-d-H-i-s");
			$log_content = "\n" . $v;

        	$log_mode = "wb";
        	$log_filename = $log_directory . "log_auto_". $log_date . ".txt";

			if ($handle = opendir( $log_directory )) {
			    while (false !== ($filename = readdir($handle)))
			    {
			        if (
			        	$filename != "." 
			        &&  $filename != ".." 
			        &&  ( strpos($filename, $today_date) !== false ) ) { //try to find a log file dated today
						
						//If the file exists: append in the existing file for today;
	        			$log_mode = "a";
	        			$log_filename = $log_directory . $filename;

	        			break;
					}

			    } //loop in all log files
			    closedir($handle);
			}


			$log = fopen($log_filename, $log_mode);
			fwrite( $log, $log_content );
			fclose( $log );
			
		}  

	}
	
}
