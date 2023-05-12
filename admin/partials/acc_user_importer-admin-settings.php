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
	function accUM_get_default_role_default() {return 'subscriber';}
	function accUM_get_default_notif_title() {return 'ACC membership change notification';}
	function accUM_get_do_expire_role_default() {return 'off';}
	function accUM_get_expired_role_default() {return 'subscriber';}

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
							 'VANCOUVER' => 'VANCOUVER'],
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

		$roles = wp_roles()->get_names();
		add_settings_field(
			'accUM_default_role',			//ID
			'When creating a new user, set role to',	//Title
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_default_role',
				'values' => $roles,
				'default' => accUM_get_default_role_default(),
			)
		);

		add_settings_field(
			'accUM_do_expire_role',			//ID
			'Should plugin modify the role when a member becomes expired?',	//Title
			'accUM_chkbox_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_do_expire_role',
				'default' => accUM_get_do_expire_role_default(),
			)
		);

		add_settings_field(
			'accUM_expired_role',			//ID
			'Set role of expired members to',	//Title
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_expired_role',
				'values' => $roles,
				'default' => accUM_get_expired_role_default(),
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