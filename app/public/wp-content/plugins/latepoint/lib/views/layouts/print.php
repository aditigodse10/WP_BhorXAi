<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
// remove these to prevent deprecating warnings
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_admin_bar_header');
remove_action('wp_admin_head', 'wp_admin_bar_header');

do_action('wp_enqueue_scripts');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title></title>
	<?php echo OsUtilHelper::generate_css_for_clean_layout(); ?>
</head>
<body>
<div class="latepoint-w">
	<?php include($view); ?>
</div>
    <?php echo OsUtilHelper::generate_js_for_clean_layout(); ?>
<script id="latepoint-auto-print" defer="defer">window.onload = function() { window.print(); }</script>
</body>
</html>