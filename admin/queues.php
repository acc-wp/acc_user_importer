<?php
/*
 *
 *	ENQUEUES AND DEQUEUE
 *
 *
 */
if (!defined("ABSPATH")) {
    exit();
} // Exit if accessed directly

/************************
 *	    JS ET CSS     	*
 ************************/

add_action("admin_enqueue_scripts", "acc_admin_styles_scripts");
function acc_admin_styles_scripts($hook)
{
    wp_register_style(
        "acc-adminstyle",
        ACC_PLUGIN_DIR . "/assets/styles/admin.css"
    );
    wp_enqueue_style("acc-adminstyle");
}
