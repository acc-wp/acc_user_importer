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
	function accUM_get_login_name_mapping_default() {return 'Firstname Lastname';}
	function accUM_get_update_user_login_default() {return 'No';}
	function accUM_get_default_role_default() {return 'Subscriber';}

	/*
	 * Register user settings for options page.
	 */
	add_action( 'admin_init', 'accUM_settings_init' );
	function accUM_settings_init () {
	
		//define sections
		add_settings_section( 'accUM_user_section', 'User Settings', '', 'acc_admin_page' );
		
		add_settings_field(
			'accUM_username',				//ID
			'Username', 					//Title
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'text',
				'name' => 'accUM_username',
				'html_tags' => 'required'
			)
		);
		
		add_settings_field(
			'accUM_password',				//ID
			'Password', 					//Title
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'password',
				'name' => 'accUM_password',
				'html_tags' => 'required'
			)
		);
		
		add_settings_field(
			'accUM_token_URI',				//ID
			'API Token Endpoint',			//Title
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'text',
				'name' => 'accUM_tokenURI',
				'html_tags' => 'required',
				'default' => '/Asi.Scheduler_DEV/token'
			)
		);
		
		add_settings_field(
			'accUM_member_URI',				//ID
			'API Data Endpoint',			//Title
			'accUM_text_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'type' => 'text',
				'name' => 'accUM_memberURI',
				'html_tags' => 'required'
			)
		);

		add_settings_field(
			'accUM_login_name_mapping',		//ID
			'When creating a new user, set login name to',	//Title
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_login_name_mapping',
				'values' => ['ContactId' => 'ContactId', 'imis_id' => 'imis_id', 'Firstname Lastname' => 'Firstname Lastname'],
				'default' => accUM_get_login_name_mapping_default(),
			)
		);

		add_settings_field(
			'accUM_update_user_login',		//ID
			'Should we update login names for existing users? (normally no)',  //Title
			'accUM_select_render',			//Callback
			'acc_admin_page',				//Page
			'accUM_user_section',			//Section
			array(
				'name' => 'accUM_update_user_login',
				'values' => ['Yes' => 'Yes', 'No' => 'No'],
				'default' => accUM_get_update_user_login_default(),
			)
		);

		//FIXME Can this be used instead of hardcoding role values a few lines below??
		//$roles = get_editable_roles();

		add_settings_field(
				'accUM_default_role',			//ID
				'When creating a new user, set role to',	//Title
				'accUM_select_render',			//Callback
				'acc_admin_page',				//Page
				'accUM_user_section',			//Section
				array(
					'name' => 'accUM_default_role',
					'values' => ['Editor' => 'Editor',
					             'Author' => 'Author',
								 'Contributor' => 'Contributor',
								 'Subscriber' => 'Subscriber',
								 'Translator' => 'Translator',
								 'Organisateur-trice' => 'Organisateur-trice'],
				'default' => accUM_get_default_role_default(),
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
		$input_value = $options[$input_name];
			
		$html = "<input type=\"$input_type\"";
		$html .= " id=\"$input_name\"";
		$html .= " name=\"accUM_data[$input_name]\"";
		
		//if memory is empty and there is a defauly, use that
		if ( empty($input_value) && $args['default'] ) {
			$input_value = $args['default'];
		}
		
		//add extra html tags if any are given
		if ( $args['html_tags'] ) { $html .= ' ' . $args['html_tags']; }
		
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
	 * WIP: Sanitize and update post data after submit.
	 */
	function accUM_sanitize_data ( $options ) {
		
		foreach ( $options as $key => $val ) {
			$options[$key] = sanitize_text_field($val);
		}
		return $options;
	}
	
?>