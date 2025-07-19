<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="pro-feature-banner">
	<h4>&#128274; <?php esc_html_e('Pro Feature', 'latepoint');?></h4>
	<div class="pro-desc">
		<div><?php esc_html_e('This feature is available with a paid version, along with over 30 other premium features.', 'latepoint');?></div>
	</div>
	<a href="<?php echo esc_url(LATEPOINT_UPGRADE_URL); ?>" class="latepoint-pro-link"><?php esc_html_e('Upgrade to paid version', 'latepoint'); ?></a>
	<a href="#" class="latepoint-pro-link-subtle" <?php echo OsSettingsHelper::get_link_attributes_for_premium_features(); ?>><?php esc_html_e('Show All premium features', 'latepoint'); ?></a>
</div>