<?php

/**
 * @link              https://github.com/acc-wp/acc_user_importer
 * @package           acc_user_importer
 *
 * @wordpress-plugin
 * Plugin Name:       ACC User Importer
 * Plugin URI:        https://github.com/acc-wp/acc_user_importer
 * Description:       A plugin for synchronizing users from the <a href="http://alpineclubofcanada.ca">Alpine Club of Canada</a> national office.
 * Version:           2.2.3
 * Author:            Francois Bessette, Claude Vessaz, Raz Peel, Karine Frenette Gaufre, Keith Dunwoody
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       acc_user_importer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define('ACC_BASE_DIR', WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)));
define('ACC_PLUGIN_DIR', plugins_url() . "/acc_user_importer/");
define('ACC_MAIN_PLUGIN_FILE_URL', __FILE__);
define('ACC_LOG_DIR', ACC_BASE_DIR . '/logs/');

/**
 * Current plugin version.
 */
define( 'ACC_USER_IMPORTER_VERSION', '2.2.3' );


/**
 * Plugin deactivation.
 */
function deactivate_acc_user_importer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-acc_user_importer-deactivator.php';
	acc_cron_deactivate();
	acc_user_importer_Deactivator::deactivate();
}

register_deactivation_hook( __FILE__, 'deactivate_acc_user_importer' );

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
include_once( ACC_BASE_DIR . '/admin/queues.php' );
include_once( ACC_BASE_DIR . '/admin/acc-user-manager.php' );

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
