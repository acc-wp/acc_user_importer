<div class="wrap">
	<h2>ACC - User Importer</h2>

    <div>
        <!-- General settings form -->
        <form action="options.php" method="post">
            <?php settings_fields("acc_general_group"); ?>
            <?php do_settings_sections("accUM_general_section1"); ?>
            <?php submit_button("Submit Changes for general section"); ?>
        </form>
	</div>

	<?php
 // Define the tabs
 $sections = accUM_get_enabled_sections();
 $tabs = [];
 if (isset($sections)) {
     $num_tabs = count($sections);
     foreach ($sections as $section) {
         $tabs[$section] = $section;
     }
 }

 // Get the current tab
 $current_tab =
     isset($_GET["tab"]) && isset($tabs[$_GET["tab"]])
         ? $_GET["tab"]
         : array_key_first($tabs);
 ?>	

	<nav class="nav-tab-wrapper">
		<?php foreach ($tabs as $tab => $name): ?>
			<a href="<?php echo add_query_arg([
       "page" => "acc_admin_page",
       "tab" => $tab,
   ]); ?>" 
                class="nav-tab <?php echo $current_tab == $tab
                    ? "nav-tab-active"
                    : ""; ?>"><?php echo $name; ?></a>
		<?php endforeach; ?>
	</nav>

	<form id="acc_sections" method="post" action="options.php">
        <?php
        settings_fields("acc_" . $current_tab . "_group");
        do_settings_sections("acc_" . $current_tab . "_section");
        submit_button();
        ?>
	</form>


	<form id="acc_admin_page" method="post" action="options.php">
		<div>
			<h2 id="updateStatusTitle">Update Log</h2>
			<input type="submit" name="update_status_submit" id="update_status_submit" class="button button-primary" value="Manual Membership Update">
			<div type="textarea" name="update_log" id="update_log" contenteditable="false" val="1">
				Output window ready.
			</div>
		</div>
	</form>
</div>
