<?php


// Move Pages above Media
add_action( 'init', 'acc_adapt_acc_adminpage', 100 );

function acc_adapt_acc_adminpage(){
	add_action('wp_authenticate_user', 'acc_validate_user_login', 10, 2);
}


/**
 * On plugin activation, schedule CRON job.
 * Update will happen in 1 minute, then twice a day afterward.
 */
function acc_cron_activate() {
	if (!wp_next_scheduled('acc_automatic_import')) {
	    wp_schedule_event( time() + 60, "twicedaily", 'acc_automatic_import' );
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
function acc_validate_user_login($user, $password) {
	$userID = $user->ID;

	$wp_caps = get_user_meta( $userID, 'wp_capabilities', 'true' );
	$role = array_keys((array)$wp_caps);

	if($role[0] == "administrator") {
		return $user;
	}

	$expiry= get_user_meta( $userID, 'expiry', 'true' );

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




