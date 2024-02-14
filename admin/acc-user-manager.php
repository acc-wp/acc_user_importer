<?php

add_filter( 'wp_authenticate_user', 'acc_validate_user_login' );


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
 * User is trying to login. Check user expiry date, and if too old,
 * don't allow him to login.
 */
function acc_validate_user_login(WP_User $user) {

	//Never block an admin
	if (in_array("administrator", $user->roles)) {
		return $user;
	}

	$expiry= get_user_meta( $user->ID, 'expiry', 'true' );
	if(empty($expiry) || $expiry < date("Y-m-d")){
		$error = new WP_Error();
		$error->add( 403, 'Oops. Your membership has expired, please renew your membership at <a href="https://www.alpineclubofcanada.ca">www.alpineclubofcanada.ca</a>. Please note that it can take up to three days until the membership data is updated.' );
		return $error;
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
	$log_directory = ACC_BASE_DIR . '/logs/';
	$log_date = date_i18n("Y-m-d-H-i-s");
	$acc_logfile = $log_directory . $prefix . $log_date . ".txt";
	//error_log("acc_logfile defined as $acc_logfile");
	acc_write_log_filename_to_db($acc_logfile);
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


