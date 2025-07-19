<?php
/**
 * @var $steps array
 */
?>
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<form class="latepoint-lightbox-wrapper-form" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('settings', 'start_import')); ?>">
	<?php wp_nonce_field('import_json_data'); ?>
<div class="latepoint-lightbox-heading">
	<h2><?php esc_html_e('Import LatePoint Data', 'latepoint'); ?></h2>
</div>
<div class="latepoint-lightbox-content">
	<?php echo OsFormHelper::file_upload_field('latepoint_json_data', __('Select JSON file to upload', 'latepoint')); ?>
	<?php echo OsFormHelper::checkbox_field('latepoint_data_erase_acknowledgement', __('I understand that this import will replace all of my existing LatePoint data.', 'latepoint'), 'on'); ?>
</div>
<div class="latepoint-lightbox-footer">
	<button type="submit" class="latepoint-btn"><?php esc_html_e('Start Import', 'latepoint'); ?></button>
</div>
</form>