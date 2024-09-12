<?php

add_filter( 'wp_authenticate_user', 'acc_validate_user_login' );

add_filter( 'um_custom_authenticate_error_codes', 'acc_um_custom_authenticate_error_codes' );

function acc_um_custom_authenticate_error_codes( $third_party_codes ) {
	$third_party_codes[] = "membership_validation_error";
	return $third_party_codes;
}


/**
 * On plugin activation, schedule CRON job.
 * Update will happen in 1 hour, then twice a day afterward.
 */
function acc_cron_activate() {
	if (!wp_next_scheduled('acc_automatic_import')) {
	    wp_schedule_event( time() + 3600, "twicedaily", 'acc_automatic_import' );
	} else {
		error_log("Error activating plugin, acc_automatic_import was already scheduled");
	}
}


/**
 * On plugin deactivation, unschedule CRON.
 */
function acc_cron_deactivate() {
    $timestamp = wp_next_scheduled( 'acc_automatic_import' );
    wp_unschedule_event( $timestamp, 'acc_automatic_import' );
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
function acc_validMembershipStatus ( $membershipStatus ) {
	return ($membershipStatus == "ISSU" || $membershipStatus == "PROC");
}


/**
 * Returns true if the membership status is in PROCessing state.
 */
function acc_MembershipStatusIsProc ( $membershipStatus ) {
	return ($membershipStatus == "PROC");
}


/**
 * User is trying to login.
 * Prevent user login if membership is PROC, EXP or expiry date is passed.
 */
function acc_validate_user_login($user) {

	if ($user instanceof WP_User) {
	//Never block an admin
	if (in_array("administrator", $user->roles)) {
		return $user;
	}

	// Case where membership is in PROC state. We output a specific error.
	$status= get_user_meta( $user->ID, 'membership_status', 'true' );
	if (!empty($status) && acc_MembershipStatusIsProc($status)){
		$error = new WP_Error();
		$msg = 'Oops. Your membership is in Processing state, which means ' .
		'a requirement is still missing. Maybe your membership renewed automatically but you did not sign the new waiver yet? ' .
		'Please check your membership at <a href="https://2mev.com/#!/login">https://2mev.com/#!/login</a> ' .
		'and make the corrections needed. This will allow you to login and register to activities. ' .
		'Note: it may take 24 hours for the update to be propagated to our local website. <br><br>' .
		'Il semble que votre abonnement ne soit pas complet. Peut-être que votre abonnement s\'est renouvelé ' .
		'automatiquement mais que vous n\'avez pas encore signé le nouveau ' .
		'formulaire d\'acceptation des risques (à signer chaque année)? Vérifiez l\'état de votre abonnement au ' .
		'<a href="https://2mev.com/#!/login">https://2mev.com/#!/login</a> afin de pouvoir vous connecter ' .
		'et participer aux activités. Allouez 24h pour que les changements se propagent au site web local.';
		$error->add( "membership_validation_error", $msg);
		return $error;
	}

	// Case where membership is not ISSU, or expiry date is passed. In theory, just
	// checking for not ISSU should be enough. But I have seen weird cases where 2M forgot to
	// notify us of a user expiry, and checking the expiry date here acts as a safeguard.
	$expiry= get_user_meta( $user->ID, 'expiry', 'true' );
	if ((!empty($status) && !acc_validMembershipStatus($status)) ||
		empty($expiry) || $expiry < date("Y-m-d")) {
		$error = new WP_Error();
		$msg = 'Oops. Looks like your membership has expired. Please renew your membership at ' .
		'<a href="https://www.alpineclubofcanada.ca">www.alpineclubofcanada.ca</a>. ' .
		'Allow 24 hours for the change to propagate to the local web site.<br><br>' .
		'Il semble que votre abonnement soit échu. Renouvelez votre abonnement au ' .
		'<a href="https://www.alpineclubofcanada.ca">www.alpineclubofcanada.ca</a>. ' .
		'et allouez 24 heures pour que le changement se propage au site web local.';
		$error->add( "membership_validation_error", $msg);
		return $error;
	}
}
	return $user;
}


function acc_send_email($user_email, $email_ID) {
	// Picks up from the ACC Email contents/titles options
	// 0 = Welcome Email
	// 1 = Expired Email
	// 2 = ...

	$email_contents = get_option("acc_email_contents");

	if(!empty($email_contents)){
		add_option("acc_email_contents", array());

		$email_contents = get_option("acc_email_contents");
		$chosen_email = stripslashes(html_entity_decode( $email_contents[$email_ID] ) );

		$email_titles = get_option("acc_email_titles");
		$chosen_title = stripslashes( $email_titles[$email_ID] );

		$email_active = get_option("acc_email_activation");
		$chosen_active = stripslashes( $email_active[$email_ID] );

		if(empty($chosen_active)){
			return false;
		}

		//Send email
		return wp_mail( $user_email, $chosen_title, $chosen_email, 'Content-Type: text/html; charset=UTF-8' );
	}
}

function acc_send_welcome_email($user_id) {
	$user = get_userdata($user_id);
	$user_email = $user->user_email;
	$test = acc_send_email( $user_email, 0 );
}

function acc_send_goodbye_email($user_id) {
	$user = get_userdata($user_id);
	$user_email = $user->user_email;
	$test = acc_send_email( $user_email, 1 );
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
function acc_pick_new_log_file($prefix) {
	global $acc_logfile;
	$log_date = date_i18n("Y-m-d-H-i-s");
	$acc_logfile = ACC_LOG_DIR . $prefix . $log_date . ".txt";
	//error_log("acc_logfile defined as $acc_logfile");
	acc_write_log_filename_to_db($acc_logfile);

	acc_enforce_max_log_files();

	return $acc_logfile;
}

// Write the current log filename as a plugin DB option.
function acc_write_log_filename_to_db ($filename) {
	$options = get_option('accUM_data');
	$options['log_filename'] = $filename;
	update_option( 'accUM_data',  $options);
	//error_log("wrote filename to DB");
}

// Get the log filename stored in the DB.
function acc_read_log_filename_from_db () {
	$options = get_option('accUM_data');
	$filename = $options['log_filename'];
	//error_log("read $filename from DB");
	return $filename;
}

/*
 * Delete old log files to ensure it does not grow to infinity
 */
function acc_enforce_max_log_files() {
	$options = get_option('accUM_data');
	if (!isset($options['accUM_max_log_files'])) {
		$max_log_files = accUM_get_default_max_log_files();
	} else {
		$max_log_files = $options['accUM_max_log_files'];
	}

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
		if ($count > ($max_log_files - 1)) {
			// How many to delete?  Minus one because we are about to add one more file
			$num_to_delete = $count - $max_log_files - 1;
			$files_to_delete = array_slice($files2, $max_log_files-1);
			acc_log("Loc directory contains {$count} files and max set to " .
					"{$max_log_files}, deleting " . count($files_to_delete));
			foreach ( $files_to_delete as $file) {
				unlink(ACC_LOG_DIR . $file);
			}
		}
	}
}

function acc_log( $v ) {
	global $acc_logfile;
	if (empty($acc_logfile)) {
		$acc_logfile = acc_read_log_filename_from_db();
	}
	if (!empty($acc_logfile)) {
		$log = fopen($acc_logfile, "a");
		fwrite( $log, $v . "\n");
		fclose( $log );
	}
}

// Returns the current date plus the specified number of days.
// The format will be a string like "2026-04-28".
function acc_now_plus_N_days ($days) {
		$date = new DateTime(); 					// Get the current date
		$period = "P" . $days . "D";
		$date->add(new DateInterval($period));    	//Add N days
		$return_string = $date->format('Y-m-d');
		return($return_string);
	}
