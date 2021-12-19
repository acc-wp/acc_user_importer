<div class="wrap">
<?php 

	//Update the email contents
	if(!empty($_POST["_acc_email_nonce"])) {

		update_option("acc_email_contents", array(
			htmlentities(wpautop($_POST["welcome_email"])),
			htmlentities(wpautop($_POST["expired_email"])),
		));

		update_option("acc_email_titles", array(
			$_POST["welcome_email_title"],
			$_POST["expired_email_title"],
		));

		update_option("acc_email_activation", array(
			$_POST["welcome_email_activation"],
			$_POST["expired_email_activation"],
		));
	}
	

	$email_contents = get_option("acc_email_contents");
	if(empty($email_contents)){
		add_option("acc_email_contents", array());
		$email_contents = get_option("acc_email_contents");
	}


	$email_titles = get_option("acc_email_titles");
	if(empty($email_contents)){
		add_option("acc_email_contents", array());
		$email_contents = get_option("acc_email_contents");
	}

	$email_active = get_option("acc_email_activation");
	if(empty($email_active)){
		add_option("acc_email_activation", array());
		$email_active = get_option("acc_email_activation");
	}

	$welcome_email = stripslashes(html_entity_decode($email_contents[0]));
	$expired_email = stripslashes(html_entity_decode($email_contents[1]));

	$welcome_title = stripslashes($email_titles[0]);
	$expired_title = stripslashes($email_titles[1]);

	$welcome_active = $email_active[0];
	$expired_active = $email_active[1];

	$page_url = get_site_url() . "/wp-admin/options-general.php?page=email_templates";
	$complete_url = wp_nonce_url( $page_url, 'email-update', '_acc_email_nonce' );

?>
	<h2>Email Templates</h2>
	<form id="acc_email_contents" method="post" action="<?php echo $complete_url; ?>">
		<input type="hidden" name="option_page" value="acc_email_contents">
		<input type="hidden" name="action" value="update">
		<input type="hidden" id="_acc_email_nonce" name="_acc_email_nonce" value="796c7766b1">
		<input type="hidden" name="_wp_http_referer" value="<?php echo $page_url; ?>">

		<p> </p>

			<table class="form-table" role="presentation">
				<tbody>

					<tr>
						<th>Activé?</th>
						<th>Nom du courriel</th>
						<th>Contenu</th>					
						<th></th>					
						<th></th>					
						<th></th>					
						<th></th>					
						<th></th>					
					</tr>

					<tr>
						<th scope="row">
							<div  style="background-color:#fff; padding:20px;display:inline-block">
								
							<input name="welcome_email_activation" type="checkbox" <?php if(!empty($welcome_active)) { echo "checked"; } ?>>
							</div>
						</th>
						<th colspan="1" scope="row">
							Courriel de bienvenue
						</th>
						<td colspan="6">
							Sujet: <input name="welcome_email_title" id="welcome_email_title" type="text" style="width:100%;max-width:400px;" value="<?php echo $welcome_title ?>"> <br> <br>
							<?php wp_editor($welcome_email, "welcome_email"); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<div  style="background-color:#fff; padding:20px;display:inline-block">
								
							<input name="expired_email_activation" type="checkbox" <?php if(!empty($expired_active)) { echo "checked"; } ?>>
							</div>
						</th>
						<th colspan="1" scope="row">
							Courriel pour membres Ex-membres<br> (après 1 mois d'expiration)
						</th>
						<td colspan="6">
							Sujet: <input name="expired_email_title" id="expired_email_title" type="text" style="width:100%;max-width:400px;" value="<?php echo $expired_title ?>"> <br> <br>
							<?php wp_editor($expired_email, "expired_email"); ?>
						</td>
					</tr>

				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
			</p>		
		</form>
	</div>