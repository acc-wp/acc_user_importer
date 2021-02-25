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
		
		add_plugins_page(
			'ACC Administration',			//Title
			'ACC Admin',					//Menu Title
			'edit_users',					//Capability
			'acc_admin_page',				//Slug
			'accUM_render_options_pages'	//Callback
		);
	}
	
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
				'html_tags' => 'required',
				'default' => 'Asi.Scheduler_DEV/api/IQA?QueryName=$/ACC/Queries/REST/ACC_VA'
			)
		);
		
		//Register the array that will store all plugin data
		register_setting( 'acc_admin_page', 'accUM_data', 'accUM_sanitize_data' );
	}
	
	/*
	 * Render theme options pages.
	 */
	function accUM_render_options_pages () {
		require plugin_dir_path( __FILE__ ) . '/acc_user_importer-admin-display.php';
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
		$select_value = $options[$input_name];
		
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