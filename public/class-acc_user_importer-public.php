<?php

/**
 * @link              https://www.facebook.com/razpeel
 * @package           acc_user_importer
 * @subpackage        acc_user_importer/public
 *
 * @wordpress-plugin
 * Plugin Name:       ACC User Importer
 * Plugin URI:        http://accvancouver.ca
 * Description:       A plugin for synchronizing users from the <a href="http://alpineclubofcanada.ca">Alpine Club of Canada</a> national office.
 * Version:           1.0.0
 * Author:            Raz Peel
 * Author URI:        https://www.facebook.com/razpeel
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       acc_user_importer
 * Domain Path:       /languages
 */

class acc_user_importer_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param    string    $plugin_name    The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}
	
	/**
	 * Make sure the user profiles have the needed data fields for user-meta.
	 */
	
	public function set_custom_profile_fields( $fields ) {
		
		// add additional fields
		$fields[ 'home_phone' ] = __( 'Home Phone' );
		$fields[ 'cell_phone' ] = __( 'Cell Phone' );
		$fields[ 'membership' ] = __( 'Membership' );
		$fields[ 'expiry' ] = __( 'Expiry' );
		$fields[ 'city' ] = __( 'City' );
		
		//remove useless fields
		unset($fields['aim']);
		unset($fields['yim']);
		unset($fields['jabber']);
		
		return $fields;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/acc_user_importer-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/acc_user_importer-public.js', array( 'jquery' ), $this->version, false );

	}

}
