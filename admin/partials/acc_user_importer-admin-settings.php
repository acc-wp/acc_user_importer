<?php

/**
 * Provide a admin area view for the plugin
 *
 * @link       https://www.facebook.com/razpeel
 *
 * @package    acc_user_importer
 * @subpackage acc_user_importer/admin/partials
 */

	/*
	 * List menu page in the Wordpress admin.
	 */
	add_action( 'admin_menu', 'accUM_add_menu_page' );
	function accUM_add_menu_page () {
		add_users_page(
			'ACC Administration',			//Title
			'ACC Admin',					//Menu Title
			'edit_users',					//Capability
			'acc_admin_page',				//Slug
			'accUM_render_options_pages'	//Callback
		);
		add_options_page(
			'ACC Email Templates',		//Title
			'ACC Email Templates',		//Menu Title
			'edit_users',				//Capability
			'email_templates',			//Slug
			'acc_email_settings'		//Callback
		);
	}

	/*
	 * Render theme options pages.
	 */
	function accUM_render_options_pages () {
		require plugin_dir_path( __FILE__ ) . '/acc_user_importer-admin-display.php';
		require_once (ACC_BASE_DIR . '/template/cron_settings.php');
		require_once (ACC_BASE_DIR . '/template/acc_logs.php');
	}

	function acc_email_settings() {
		require_once (ACC_BASE_DIR . '/template/email_settings.php');
	}

	// Define functions to get default values from different files.
	function accUM_get_login_name_mapping_default() {return 'member_number';}
	function accUM_get_section_default() {return 'Ottawa';}
	function accUM_get_new_user_role_action_default() {return 'set_role';}
	function accUM_get_new_user_role_value_default() {return 'subscriber';}
	function accUM_get_default_notif_title() {return 'ACC membership change notification';}
	function accUM_get_ex_user_role_action_default() {return 'set_role';}
	function accUM_transition_from_contactID_default() {return 'off';}
	function accUM_readonly_mode_default() {return 'off';}
	function accUM_verify_expiry_default() {return 'off';}
	function accUM_get_ex_user_role_value_default() {return 'subscriber';}
	function accUM_get_default_max_log_files() {return 500;}
	function accUM_get_notification_emails_default() {return '';}

	// Get the section name as per the settings
	function accUM_getSectionName ( ) {
		$options = get_option('accUM_data');
		if (!isset($options['accUM_section_api_id'])) {
			$sectionName = accUM_get_section_default();
		} else {
			$sectionName = $options['accUM_section_api_id'];
		}
		return $sectionName;
	}

	// Returns true if the database is transitioning from FromContactID usernames.
	function accUM_get_transitionFromContactID() {
		$options = get_option('accUM_data');
		if (!isset($options['accUM_transition_from_contactID'])) {
			$transitionFromContactID = accUM_transition_from_contactID_default();
		} else {
			$transitionFromContactID = $options['accUM_transition_from_contactID'];
		}
		return $transitionFromContactID == 'on';
	}

	// Returns true if the plugin operates in read-only mode (for debug)
	function accUM_get_readonly_mode() {
		$options = get_option('accUM_data');
		if (!isset($options['accUM_readonly_mode'])) {
			$readonly_mode = accUM_readonly_mode_default();
		} else {
			$readonly_mode = $options['accUM_readonly_mode'];
		}
		return $readonly_mode == 'on';
	}

	// Returns true if we need to scan the DB looking for expired users
	function accUM_get_verify_expiry() {
		$options = get_option('accUM_data');
		if (!isset($options['accUM_verify_expiry'])) {
			$setting = accUM_verify_expiry_default();
		} else {
			$setting = $options['accUM_verify_expiry'];
		}
		return $setting == 'on';
	}

	/*
	 * Register user settings for options page.
	 */
	add_action( 'admin_init', 'accUM_settings_init' );
	function accUM_settings_init () {

		//define sections
		add_settings_section( 'accUM_user_section', 'User Settings', '', 'acc_admin_page' );

		add_settings_field(
			'accUM_section_api_id',			//ID
			'Section for which to import membership',		//Title
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_section_api_id',
				'values' => ['SQUAMISH' => 'SQUAMISH',
							 'CALGARY' => 'CALGARY',
							 'OTTAWA' => 'OTTAWA',
							 'MONTRÉAL' => 'MONTRÉAL',
							 'OUTAOUAIS' => 'OUTAOUAIS',
							 'VANCOUVER' => 'VANCOUVER',
							 'ROCKY MOUNTAIN' => 'ROCKY MOUNTAIN',
							 'EDMONTON' => 'EDMONTON',
							 'TORONTO' => 'TORONTO',
							 'YUKON' => 'YUKON',
							 'BUGABOOS' => 'BUGABOOS'],
				'default' => accUM_get_section_default(),
			)
		);

		add_settings_field(
			'accUM_token',				//ID
			'One or more section authentication tokens. Section names are in Capitals. ' .
			'Example with bogus token values: ' .
			'OUTAOUAIS:K39FKJ5HJDU2,MONTRÉAL:K49G86J345',
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'password',
				'name' => 'accUM_token',
				'html_tags' => 'required'
			)
		);

		add_settings_field(
			'accUM_since_date',		//ID
			"Sync changes since when? This normally shows the last run time (in UTC), " .
			"but you can force a date in ISO 8601 format such as 2020-11-23T15:05:00.",
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'text',
				'name' => 'accUM_since_date',
			)
		);

		add_settings_field(
			'accUM_login_name_mapping',		//ID
			'Set usernames to (Use with caution, this affects login of users, ' .
			'although they always can login using their email)',
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_login_name_mapping',
				'values' => ['member_number' => 'ACC member number', 'Firstname Lastname' => 'Firstname Lastname'],
				'default' => accUM_get_login_name_mapping_default(),
			)
		);

		add_settings_field(
			'accUM_transition_from_contactID',			//ID
			'Usernames will transition from ContactID to Interpodia member_number? ' .
			'Check this box for a safer transition (verifies that member being synced has the right name)',
			'accUM_chkbox_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_transition_from_contactID',
				'default' => accUM_transition_from_contactID_default(),
			)
		);

		add_settings_field(
			'accUM_readonly_mode',			//ID
			'Test mode: do not update Wordpress database. ' .
			'Check this box to do a normal run but skip the Wordpress users update.',
			'accUM_chkbox_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_readonly_mode',
				'default' => accUM_readonly_mode_default(),
			)
		);

		add_settings_field(
			'accUM_verify_expiry',			//ID
			'Also check user expiry in local DB',
			'accUM_chkbox_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_verify_expiry',
				'default' => accUM_verify_expiry_default(),
			)
		);

		add_settings_field(
			'accUM_new_user_role_action',	//ID
			'When creating a new user, what should I do with role?',
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_new_user_role_action',
				'values' => ['set_role' => 'Set role', 'add_role' => 'Add role', 'nc' => 'Do not change role'],
				'default' => accUM_get_new_user_role_action_default(),
			)
		);

		$roles = wp_roles()->get_names();
		add_settings_field(
			'accUM_new_user_role_value',	//ID
			'role value?',					//Title
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_new_user_role_value',
				'values' => $roles,
				'default' => accUM_get_new_user_role_value_default(),
			)
		);

		add_settings_field(
			'accUM_ex_user_role_action',	//ID
			'When expiring a user, what should I do with role?',
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_ex_user_role_action',
				'values' => ['set_role' => 'Set role', 'remove_role' => 'Remove role', 'nc' => 'Do not change role'],
				'default' => accUM_get_ex_user_role_action_default(),
			)
		);

		add_settings_field(
			'accUM_ex_user_role_value',		//ID
			'role value?',					//Title
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_ex_user_role_value',
				'values' => $roles,
				'default' => accUM_get_ex_user_role_value_default(),
			)
		);

		add_settings_field(
			'accUM_notification_emails',	//ID
			'Admin to notify about membership creation/expiry? List of emails, comma separated. Leave blank for no notifications',
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'text',
				'name' => 'accUM_notification_emails',
				'default' => accUM_get_notification_emails_default(),
			)
		);

		add_settings_field(
			'accUM_notification_title',	//ID
			'Title of admin notification email',
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'text',
				'name' => 'accUM_notification_title',
				'default' => accUM_get_default_notif_title(),
				)
		);

		add_settings_field(
			'accUM_max_log_files',			//ID
			'Maximum number of log files to keep',
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'number',
				'name' => 'accUM_max_log_files',
				'default' => accUM_get_default_max_log_files(),
				)
		);

		//Register the array that will store all plugin data
		register_setting( 'acc_admin_page', 'accUM_data', 'accUM_sanitize_data' );
	}


	/*
	 * Render the textbox fields.
	 */
	function accUM_text_render ( $args ) {

		$options = get_option('accUM_data');
		$input_name = $args['name'];
		$input_type = $args['type'];
		if (empty($options[$input_name])) {
			$input_value = $args['default'];
		} else {
			$input_value = $options[$input_name];
		}

		$html = "<input type=\"$input_type\"";
		$html .= " id=\"$input_name\"";
		$html .= " name=\"accUM_data[$input_name]\"";

		//if memory is empty and there is a defauly, use that
		if ( empty($input_value) && $args['default'] ) {
			$input_value = $args['default'];
		}

		//add extra html tags if any are given
		if ( !empty($args['html_tags'] )) { $html .= ' ' . $args['html_tags']; }

		$html .= " value=\"$input_value\"";
		$html .= "/>";

		echo $html;
	}

	function accUM_select_render ( $args ) {

		$options = get_option('accUM_data');
		$input_name = $args['name'];
		if (empty($options[$input_name])) {
			$select_value = $args['default'];
		} else {
			$select_value = $options[$input_name];
		}

		$html = "<select id=\"$input_name\" name=\"accUM_data[$input_name]\">";

		//Fill columns
		if ($args['values']) {
			foreach ( $args['values'] as $key => $value ) {
				$html .= "<option value=\"$key\"";
				if ($key == $select_value) { $html .= ' selected="selected"'; }
				$html .= ">$value";
				$html .= "</option>";
			}
		}
		echo $html . "</select>";
	}

	/*
	 * Render for a single on/off checkbox.
	 * If checked, the WP database stores 'on'.
	 * If not checked, the WP database has no data for that option.
	 */
	function accUM_chkbox_render($args) {
		$options = get_option('accUM_data');
		$input_name = $args['name'];
		if (empty($options[$input_name])) {
			$select_value = $args['default'];
		} else {
			$select_value = $options[$input_name];
		}

		$html = "<input type=\"checkbox\"";
		$html .= " id=\"$input_name\"";
		$html .= " name=\"accUM_data[$input_name]\"";
		$html .= checked( 'on', $select_value, FALSE ) . ' />';
		echo $html;
	}

	/*
	 * WIP: Sanitize and update post data after submit.
	 */
	function accUM_sanitize_data ( $options ) {

		foreach ( $options as $key => $val ) {
			$options[$key] = sanitize_text_field($val);
		}
		return $options;
	}

?>
