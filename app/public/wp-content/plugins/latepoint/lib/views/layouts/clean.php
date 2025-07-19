<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
add_filter( 'show_admin_bar', '__return_false' );
remove_action( 'wp_head', '_admin_bar_bump_cb' );
remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );

// remove these to prevent deprecating warnings
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_admin_bar_header' );
remove_action( 'wp_admin_head', 'wp_admin_bar_header' );


do_action('wp_enqueue_scripts');
// load custom css and js files to enque because we are not using wp_head and wp_footer

$patterns = OsSettingsHelper::instant_page_background_patterns();
if(!empty($background_pattern) && !empty($patterns[$background_pattern])) {
    $background_pattern_css = $patterns[$background_pattern];
}


?>
<!DOCTYPE html>
<html lang="en" class="latepoint-clean">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title ?? get_bloginfo('name'); ?></title>
	<?php echo OsUtilHelper::generate_css_for_clean_layout(); ?>
</head>
<body class="latepoint-clean-body with-pattern latepoint" style="<?php echo esc_attr($background_pattern_css); ?>">
    <div class="latepoint-w">
        <?php include( $view ); ?>
    </div>
    <?php echo OsUtilHelper::generate_js_for_clean_layout(); ?>
</body>
</html>
<?php exit(200);