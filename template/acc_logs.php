<?php
$log_folder = ACC_PLUGIN_DIR . "/logs/";
$log_list = "";

//PHP to delete log files
if (
    !empty($_GET["acc_nonce"]) &&
    check_admin_referer("trash-log", "acc_nonce") === 1
) {
    $file_to_delete = ACC_LOG_DIR . $_GET["log"];
    if (file_exists($file_to_delete)) {
        unlink($file_to_delete);
    }
}
?>


<div class="wrap">
	<h2>Recent log files</h2>
	<ul class="log_files">

<?php
chdir(ACC_LOG_DIR);
array_multisort(
    array_map("basename", $files = glob("*.txt")),
    SORT_DESC,
    $files
);
foreach ($files as $filename) {
    $split_filename;
    $page_url =
        get_site_url() .
        "/wp-admin/users.php?page=acc_admin_page&log=" .
        $filename;
    $complete_url = wp_nonce_url($page_url, "trash-log", "acc_nonce");
    preg_match(
        "/log_(.*)_(\d+-\d+-\d+)-(\d+-\d+-\d+).txt/mis",
        $filename,
        $split_filename
    );

    $log_list .=
        '
		<li>
			<strong>
				Log ' .
        $split_filename[1] .
        '
			</strong> &mdash;
			<em>
				' .
        $split_filename[2] .
        " " .
        str_replace("-", ":", $split_filename[3]) .
        '
			</em> &mdash;
			<a href="' .
        $log_folder .
        $filename .
        '" download>Download</a> |
			<a href="' .
        $complete_url .
        '">Delete</a>
		</li> ';
}

echo $log_list;
?>

	</ul>
</div>