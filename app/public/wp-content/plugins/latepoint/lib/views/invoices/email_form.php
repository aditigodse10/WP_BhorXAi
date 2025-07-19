<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $invoice OsInvoiceModel */
/* @var $errors array */
/* @var $success string */
/* @var $to string */
/* @var $subject string */
/* @var $content string */


?>
<form action="" data-os-action="<?php echo OsRouterHelper::build_route_name('invoices', 'email_form'); ?>" class="invoice-email-form latepoint-lightbox-inner-form" data-os-after-call="latepointInvoicesAdminFeature.init_email_invoice_form" data-os-output-target=".invoice-email-form">
	<div class="latepoint-lightbox-heading">
		<h2><?php echo esc_html__('Email Invoice', 'latepoint'); ?></h2>
		<a href="#" class="latepoint-lightbox-close" tabindex="0"><i class="latepoint-icon latepoint-icon-x"></i></a>
	</div>
	<div class="latepoint-lightbox-content">
		<?php

		// Output errors if any
		if (!empty($errors)){
			echo '<div class="latepoint-message latepoint-message-error">';
			foreach($errors as $error){
				echo '<div>'.$error.'</div>';
			}
			echo '</div>';
		}
		if (!empty($success)){
			echo '<div class="latepoint-message latepoint-message-success">'.$success.'</div>';
		}else{
            ?>
            <?php
            echo '<div class="latepoint-message latepoint-message-subtle">' . __( 'You can customize subject and content of the email in general settings', 'latepoint' ). '</div>';
            echo OsFormHelper::text_field('invoice_email[to]', __('Email to:', 'latepoint'), $to, ['theme' => 'simple']);
            echo OsFormHelper::hidden_field('invoice_id', $invoice->id);
        }
        ?>
	</div>
    <?php if(empty($success)){ ?>
	<div class="latepoint-lightbox-footer">
		<button type="submit" class="latepoint-btn latepoint-btn-primary latepoint-btn-position-end "><?php esc_html_e('Send', 'latepoint'); ?></button>
	</div>
    <?php } ?>
</form>