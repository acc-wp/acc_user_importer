<div class="wrap">
	<h2>ACC - User Importer</h2>
	<form id="acc_admin_page" method="post" action="options.php">
		<div>
			<?php
				settings_fields ( 'acc_admin_page' );
				do_settings_sections ( 'acc_admin_page' );
				submit_button();
			?>
		</div>
		<div>
			<h2 id="updateStatusTitle">Update Status</h2>
			<input type="submit" name="debug_status_submit" id="debug_status_submit" class="button button-primary" value="Debug" disabled="disabled">
			<input type="submit" name="update_status_submit" id="update_status_submit" class="button button-primary" value="Update">
			<div type="textarea" name="update_log" id="update_log" contenteditable="false" val="1">
				Output window ready.
			</div>
		</div>
	</form>
</div>
