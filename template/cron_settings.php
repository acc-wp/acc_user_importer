
<div class="wrap">
	<?php 
	$cron_options = get_option("cron");
	$cron_schedules = wp_get_schedules();

	$page_url = get_site_url() . "/wp-admin/options-general.php?page=acc_cron_list";

	$complete_url = wp_nonce_url( $page_url, 'cron-update', '_acc_cron_nonce' );

	//PHP to delete log files
	if(!empty($_POST["_acc_cron_nonce"]) && !empty($_POST["cron"]) 
	  // && check_admin_referer( 'cron-update', '_acc_cron_nonce' ) === 1
		) {

		foreach($cron_options as $key => $cron) {
			if($key !== "version"){

				foreach($_POST["cron"] as $cron_og_name => $cron_new_name){
					$group_key = $_POST["group"][$cron_og_name];
					if($key == $group_key) {


						if(!empty($cron_new_name)){

							//check if function exists, if not: create it
							if(empty($cron[$cron_new_name])) {
								//mismatched functions: it was edited
								foreach($cron as $inner) {
									$cron_options[$key][$cron_new_name] = [];
									$cron_options[$key][$cron_new_name] = $inner;
									break;
								}	//copy one of the crons
							} //ideally I'd remove the old ones tho

							$longkey = key($cron_options[$key][$cron_new_name]);


							if(!empty($cron[$cron_new_name])) {
								foreach($cron[$cron_new_name] as $element) {
									$schedule_chosen = $cron_schedules[$_POST["interval"][$cron_og_name]];

									if($cron_options[$key][$cron_new_name][$longkey]["interval"] !== $schedule_chosen["interval"]) {
										//If the schedule is different

										$cron_options[$key][$cron_new_name][$longkey]["schedule"] = $_POST["interval"][$cron_og_name];
										$cron_options[$key][$cron_new_name][$longkey]["interval"] = $schedule_chosen["interval"];

									    $timestamp = wp_next_scheduled( $cron_og_name );
									    wp_unschedule_event( $timestamp, $cron_og_name );
								        wp_unschedule_hook( $cron_og_name );
								        wp_clear_scheduled_hook( $cron_og_name );

									    $timestamp = wp_next_scheduled( $cron_new_name );
									    wp_unschedule_event( $timestamp, $cron_new_name );
								        wp_unschedule_hook( $cron_new_name );
								        wp_clear_scheduled_hook( $cron_new_name );

									    wp_schedule_event( time()+60, $cron_options[$key][$cron_new_name][$longkey]["schedule"], $cron_new_name );

									}

								}
							}


							if($cron_og_name == "new" && !empty($cron_new_name) ) {
								// $cron_options[$key][$cron_new_name] = array();
								// $cron_options[$key][$cron_new_name][$longkey]["args"] = array();
								
								wp_schedule_event( time()+3600, "hourly", $cron_new_name );

							}

							if($cron_new_name !== $cron_og_name && $cron_og_name !== "new"){
								unset($cron_options[$key][$cron_og_name]);

							    $timestamp = wp_next_scheduled( $cron_og_name );
							    wp_unschedule_event( $timestamp, $cron_og_name );
						        wp_unschedule_hook( $cron_og_name );
						        wp_clear_scheduled_hook( $cron_og_name );

							}

						}

						if(empty($cron_new_name) && $cron_og_name !== "new"){
						
							unset($cron_options[$key][$cron_og_name]);

						    $timestamp = wp_next_scheduled( $cron_og_name );
						    wp_unschedule_event( $timestamp, $cron_og_name );
						    wp_unschedule_hook( $cron_og_name );
						    wp_clear_scheduled_hook( $cron_og_name );

						}

					}
				}

			} // just so it doesnt print the version that's present in the options

		} //foreach

		// update_option( "cron", $cron_options );

	} //update

	$cronjobs = _get_cron_array();

	$last_group;
?>
	<h2>ACC Cron Jobs</h2>
	<form id="cron_jobs_manager" method="post" action="<?php echo $complete_url; ?>">
		<input type="hidden" name="option_page" value="acc_cron_list">
		<input type="hidden" name="action" value="update">
		<input type="hidden" id="_acc_cron_nonce" name="_acc_cron_nonce" value="796c7766b1">
		<input type="hidden" name="_wp_http_referer" value="<?php echo $page_url; ?>">

		<p>Here is the list of ACC functions triggered by a timer. acc_automatic_import
			is a function which contacts the ACC head office, downloads the list
			of members, and updates the local user database accordinly. You may change
			the running interval.
		</p>

			<table class="form-table" role="presentation">
				<tbody>

					<tr>
						<th>Fonction/ Hook (PHP)</th>
						<th>Interval</th>					
						<th>Next planned run</th>					
					</tr>

					<?php foreach($cronjobs as $unknownkey => $jobs) { ?>
					<?php foreach($jobs as $key => $job) { if(strrpos($key, "acc_") === false) { continue; } ?>
					<tr>
						<th scope="row">
							<input 
							type="text" 
							name="cron[<?php echo $key; ?>]" 
							value="<?php echo $key; ?>" 
							style="background-image: url(&quot;data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAASCAYAAABSO15qAAAAAXNSR0IArs4c6QAAAZ9JREFUOBGVU7uKwkAUPXmID5AttNyFYBGwsLGwFBUFF/wOhfyE5jPcxkZt/IHFxg+wsZJtrFwS8NWIohZm545xNkp8XcjMnbnnnJk790YyTfPTcZwm+z7whEmSNGWwaqPR+Ca4/AqZCO5BX+STkcBTJ5/gp9HLkb2BR34kEoGu6xewlwQ0TUOxWPQXCIVCIhAMBsEeS6y9MbHpOirNlUoF6XQanU4Hq9UKhmHAsiy0Wq2L2DWZ1i+l4Ccg1et1hwJ0zd1uxzGUwn6/98OLPZbiL1vUxA3OZEI8IhOGlfKdTU3+BrThZ5lMBoVCAev1Gr1eD7PZDIFAALIs80NIRNzAT4DIw+EQm80G2WyWQ1KpFHK5nICr1NvezhIR5iyXSyQSCUSjUSiKgnK5jGQyCVVVEYvF0O12oeTz+R+GJfk3L5n8yWTC+yEej3OxwWCA4/GI7XaLfr/P0/jvlis2VadUKvH+IFK73YZt2yCxcDiM6ZR+SuDuI45GI4zHY8zncxwOB05YLBZ8Pg83BajOjEilummEuVeFmtssvgJurPYHGEKbZ/T0eqIAAAAASUVORK5CYII=&quot;); background-repeat: no-repeat; background-attachment: scroll; background-size: 16px 18px; background-position: 98% 50%;" 
							autocomplete="off"
							>

							<input 
							type="hidden" 
							name="group[<?php echo $key; ?>]" 
							value="<?php echo $unknownkey; ?>" 
							autocomplete="off"
							>
						</th>
						<td>
							<?php //list all schedules  ?>
							<select name="interval[<?php echo $key; ?>]">
								<?php foreach($cron_schedules as $schedule => $schedule_contents) { ?>
									
								<option value="<?php echo $schedule ?>" <?php if( ($job[key($job)]["interval"]) == $schedule_contents["interval"]) { echo "selected"; } ?>>
									<?php echo $schedule_contents["display"]; ?>
								</option>

								<?php } // schedules ?>
							</select>
						</td>
						<td>
							<p>
								<?php echo wp_date("d M Y - H:i:s", wp_next_scheduled( $key ) ); ?>
							</p>
						</td>
					</tr>

					<?php $last_group = $unknownkey; ?>

					<?php	} //foreach cron jobs ?>
					<?php	} //foreach jobs ?>

				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
			</p>		
		</form>
	</div>