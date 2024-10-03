<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package    acc_user_importer
 * @subpackage acc_user_importer/includes
 * @author     Raz Peel <raz.peel@gmail.com>
 */
class acc_user_importer
{
    protected $loader;
    protected $plugin_name;
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     */
    public function __construct()
    {
        if (defined("ACC_USER_IMPORTER_VERSION")) {
            $this->version = ACC_USER_IMPORTER_VERSION;
        } else {
            $this->version = "1.0.0";
        }
        $this->plugin_name = "acc_user_importer";

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @access   private
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-acc_user_importer-loader.php";

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-acc_user_importer-i18n.php";

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) .
            "admin/class-acc_user_importer-admin.php";

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) .
            "public/class-acc_user_importer-public.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-acc_user_importer-activator.php";

        new acc_user_importer_Activator(
            $this->get_plugin_name(),
            $this->get_version()
        );

        $this->loader = new acc_user_importer_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @access   private
     */
    private function set_locale()
    {
        $plugin_i18n = new acc_user_importer_i18n();

        $this->loader->add_action(
            "plugins_loaded",
            $plugin_i18n,
            "load_plugin_textdomain"
        );
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new acc_user_importer_Admin(
            $this->get_plugin_name(),
            $this->get_version()
        );

        $this->loader->add_action(
            "admin_enqueue_scripts",
            $plugin_admin,
            "enqueue_styles"
        );
        $this->loader->add_action(
            "admin_enqueue_scripts",
            $plugin_admin,
            "enqueue_scripts"
        );
        $this->loader->add_action(
            "wp_ajax_accUserAPI",
            $plugin_admin,
            "accUserAPI"
        );
        $this->loader->add_filter(
            "user_registration_email",
            $plugin_admin,
            "__return_false"
        ); // fix variable $plugin_public that isn't defined in this function //karinegaufre

        //automatic update function (for testing)
        //$this->loader->add_action( 'admin_head', $plugin_admin, 'begin_automatic_update' );

        //action hook for automatic updates
        $this->loader->add_action(
            "acc_automatic_import",
            $plugin_admin,
            "begin_automatic_update"
        );
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @access   private
     */
    private function define_public_hooks()
    {
        $plugin_public = new acc_user_importer_Public(
            $this->get_plugin_name(),
            $this->get_version()
        );

        $this->loader->add_action(
            "wp_enqueue_scripts",
            $plugin_public,
            "enqueue_styles"
        );
        $this->loader->add_action(
            "wp_enqueue_scripts",
            $plugin_public,
            "enqueue_scripts"
        );
        $this->loader->add_filter(
            "user_contactmethods",
            $plugin_public,
            "set_custom_profile_fields"
        );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    acc_user_importer_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
