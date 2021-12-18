<?php 
	$log_directory  = ACC_BASE_DIR . '/logs/';
	$log_folder 	= ACC_PLUGIN_DIR . '/logs/';
	$log_list = "";


	//PHP to delete log files
	if(!empty($_GET["acc_nonce"]) && check_admin_referer( 'trash-log', 'acc_nonce' ) === 1) {
		$file_to_delete = $log_directory . $_GET["log"];
		if(file_exists($file_to_delete))
			unlink($file_to_delete);
	}


	//PHP to delete log files
	if(!empty($_POST["_acc_role_change"]) && !empty($_POST["acc_roles"]) 
	  // && check_admin_referer( 'cron-update', '_acc_role_change' ) === 1
		) { 

		update_option("acc_role_editor", $_POST["acc_roles"]);
		update_option("acc_expiry_lvl_1", $_POST["acc_roles_expiry_lvl1"]);
		update_option("acc_expiry_lvl_2", $_POST["acc_roles_expiry_lvl2"]);
	} 

	$current_role = get_option( "acc_role_editor", "Subscriber" );
	$expiry_lvl_1 = get_option( "acc_expiry_lvl_1", "Expiré" );
	$expiry_lvl_2 = get_option( "acc_expiry_lvl_2", "ex-membre" );

	$roles = get_editable_roles();
 ?>

<div class="wrap">

	<form id="acc_role_change" method="post" action="<?php echo get_site_url(); ?>/wp-admin/users.php?page=acc_admin_page">
		<input type="hidden" name="option_page" value="acc_role_change">
		<input type="hidden" name="action" value="update">
		<input type="hidden" id="_acc_role_change" name="_acc_role_change" value="796c7766b1">

			<table class="form-table" role="presentation" style="max-width:650px">
				<tbody>

					<tr>
						<th style="width:550px">Rôle par défaut des utilisateurs importés:</th>
						<td style="width:50px">
							<select name="acc_roles" id="acc_roles">
								<?php 
									
									foreach($roles as $key => $role) { if($key == "administrator") { continue; } ?>
								<option value="<?php echo $key; ?>" <?php if($key == $current_role) { echo "selected"; } ?>>
									<?php echo $role["name"]; ?>
								</option>
								<?php } //foreach ?>
							</select>
						</td>
						<td style="width:50px">
							<input type="submit" name="submit" id="submit" class="button button-primary" value="Enregistrer">
						</td>
					</tr>
					<tr>
						<th style="width:550px">Rôle pour les membres qui ont leur date d'expiration plus vieille que la date du jour:</th>
						<td style="width:50px">
							<select name="acc_roles_expiry_lvl1" id="acc_roles_expiry_lvl1">
								<?php 
									
									foreach($roles as $key => $role) { if($key == "administrator") { continue; } ?>
								<option value="<?php echo $key; ?>" <?php if($key == $expiry_lvl_1) { echo "selected"; } ?>>
									<?php echo $role["name"]; ?>
								</option>
								<?php } //foreach ?>
							</select>
						</td>
						<td style="width:50px">
							<input type="submit" name="submit" id="submit" class="button button-primary" value="Enregistrer">
						</td>
					</tr>
					<tr>
						<th style="width:550px">Rôle pour les membres qui ont leur date d'expiration à plus d'un mois:</th>
						<td style="width:50px">
							<select name="acc_roles_expiry_lvl2" id="acc_roles_expiry_lvl2">
								<?php 
									
									foreach($roles as $key => $role) { if($key == "administrator") { continue; } ?>
								<option value="<?php echo $key; ?>" <?php if($key == $expiry_lvl_2) { echo "selected"; } ?>>
									<?php echo $role["name"]; ?>
								</option>
								<?php } //foreach ?>
							</select>
						</td>
						<td style="width:50px">
							<input type="submit" name="submit" id="submit" class="button button-primary" value="Enregistrer">
						</td>
					</tr>

				</tbody>
			</table>
	</form>

	<ul class="log_files">

<?php 
	chdir($log_directory);
	array_multisort(array_map('basename', ($files = glob("*.txt"))), SORT_DESC, $files);
	foreach($files as $filename)
	{
		$split_filename;
		$page_url = get_site_url() . "/wp-admin/users.php?page=acc_admin_page&log=".$filename;
		$complete_url = wp_nonce_url( $page_url, 'trash-log', 'acc_nonce' );
		preg_match('/log_(.*)_(\d+-\d+-\d+)-(\d+-\d+-\d+).txt/mis', $filename, $split_filename);

		$log_list .= '
		<li>
			<strong>
				Log '. $split_filename[1] .'
			</strong> &mdash;
			<em>
				'. $split_filename[2] .' '. str_replace("-", ":", $split_filename[3]) .'
			</em> &mdash;
			<a href="'. $log_folder . $filename .'" download>Télécharger</a> | 
			<a href="' . $complete_url .'">Supprimer</a>
		</li> ';
}

	echo $log_list;

?>

	</ul>
</div>