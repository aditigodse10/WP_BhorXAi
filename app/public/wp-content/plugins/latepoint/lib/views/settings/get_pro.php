<div class="get-pro-wrapper">
	<h4><?php _e('Activate Premium Features', 'latepoint'); ?></h4>
	<div class="pro-sub-heading"><?php esc_html_e('Enter your license key to download the premium features plugin.', 'latepoint'); ?></div>
	<div class="get-pro-wrapper-fields">
		<?php echo OsFormHelper::text_field('license_code', '', '', ['theme' => 'simple', 'placeholder' => __('Enter your license code here...', 'latepoint')]); ?>
		<button class="latepoint-btn"><?php esc_html_e('Download Pro', 'latepoint'); ?></button>
	</div>
</div>
