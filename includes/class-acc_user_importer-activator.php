<?php

/**
 * Fired during plugin activation
 *
 * @package    acc_user_importer
 * @subpackage acc_user_importer/includes
 * @author     Raz Peel <raz.peel@gmail.com>
 * @link       https://www.facebook.com/razpeel
 */

class acc_user_importer_Activator {

	private $plugin_name;
	private $version;
	private $previous_plugin_version;


	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		register_activation_hook( ACC_MAIN_PLUGIN_FILE_URL, array( $this, 'activate'));
	}


	/**
	 * Activation scripts.
	 */
	public function activate() {
		$this->read_previous_plugin_version_from_db();
		$this->process_upgrade();
		$this->write_new_plugin_version_to_db();
		acc_cron_activate();
	}


	// Check version number of plugin which ran last.
	// Starting in v2.1.0, we store the version of the plugin in the database, 
	// and this info is available when we activate to see if any upgrade
	// or downgrade work is needed.
	private function read_previous_plugin_version_from_db() {
		$options = get_option('accUM_data');
		if (!empty($options['accUM_plugin_version'])) {
		   $this->previous_plugin_version = $options['accUM_plugin_version'];
		} else {
		   $this->previous_plugin_version = 'unknown';
		}
   }

   public function get_previous_plugin_version() {
	   return $this->previous_plugin_version;
   }

	// Write the current plugin version in the DB.
	private function write_new_plugin_version_to_db () {
		$options = get_option('accUM_data');
		$options['accUM_plugin_version'] = $this->version;
		update_option( 'accUM_data',  $options);
	}

   /**
	 * Upon activation, there might be some upgrade/downgrade cleanup work.
	 */
	private function process_upgrade() {

		$previous_version = $this->get_previous_plugin_version();
		if ($previous_version === 'unknown' ||
		    ($previous_version > '1.3.0' && $previous_version < '2.1.0')) {

			// Some upgrade work has to be done
			$log_file = $this->pick_new_log_filename("log_upgrade_");
			error_log("Upgrade from $previous_version to $this->version needed\n", 3, $log_file);
			$this->scan_user_db_and_remove_previous_roles($log_file);
			error_log("Upgrade done\n", 3, $log_file);
		}
	}

   /**
	 * Remove user meta "previous_roles" which was used from version 1.3.0 to 2.1.0
	 * and that we no longer use.  This is not really critical, but it is best
	 * to not pollute the DB with obsolete elements.
	 */
	private function scan_user_db_and_remove_previous_roles ($log_file) {

		$user_ids = get_users(['fields' => 'ID']);
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata($user_id);
			if (isset($user->previous_roles)) {
				$rc = delete_user_meta($user_id, 'previous_roles');
				error_log("Removed obsolete 'previous_role' for user {$user_id}, result={$rc}\n",
						  3, $log_file);
			}
		}
	}

	/*
     * Generate a new log file, based on the current day and time. Ex:
	 * plugins/acc_user_importer/logs/log_upgrade_2024-02-13-16-35-04.txt
	 */
	private function pick_new_log_filename($prefix) {
		$log_date = date_i18n("Y-m-d-H-i-s");
		$log_filename = ACC_LOG_DIR . $prefix . $log_date . ".txt";
		return $log_filename;
	}

}
