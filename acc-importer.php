<?php

/**
 * @link              https://www.facebook.com/razpeel
 * @package           acc_user_importer
 *
 * @wordpress-plugin
 * Plugin Name:       ACC User Importer
 * Plugin URI:        http://accvancouver.ca
 * Description:       A plugin for synchronizing users from the <a href="http://alpineclubofcanada.ca">Alpine Club of Canada</a> national office.
 * Version:           1.0.4
 * Author:            Raz Peel (edits by KFG and Francois Bessette)
 * Author URI:        https://www.facebook.com/razpeel
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       acc_user_importer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'ACC_USER_IMPORTER_VERSION', '1.0.4' );

/**
 * Plugin activation.
 */
function activate_acc_user_importer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-acc_user_importer-activator.php';
	acc_user_importer_Activator::activate();
}

/**
 * Plugin deactivation.
 */
function deactivate_acc_user_importer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-acc_user_importer-deactivator.php';
	acc_user_importer_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_acc_user_importer' );
register_deactivation_hook( __FILE__, 'deactivate_acc_user_importer' );

/**
 * Core plugin class.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-acc_user_importer.php';

/**
 * Begins execution of the plugin.
 */
function run_acc_user_importer() {

	$plugin = new acc_user_importer();
	$plugin->run();

}

run_acc_user_importer();
