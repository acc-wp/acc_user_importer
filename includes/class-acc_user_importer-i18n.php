<?php

/**
 * @link              https://www.facebook.com/razpeel
 * @package           acc_user_importer
 * @subpackage.       acc_user_importer/includes
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

class acc_user_importer_i18n {


	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'acc_user_importer',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
