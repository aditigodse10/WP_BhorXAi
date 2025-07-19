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
<form action="" data-os-output-target=".invoice-document-wrapper" data-os-action="<?php echo OsRouterHelper::build_route_name('invoices', 'process_data_update'); ?>" class="invoice-data-form latepoint-lightbox-inner-form" data-os-pass-response="yes" data-os-after-call="latepointInvoicesAdminFeature.init_invoice_data_updated">
	<div class="latepoint-lightbox-heading">
		<h2><?php echo esc_html__('Edit Invoice Data', 'latepoint'); ?></h2>
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
            ?>
            <div class="os-row">
                <div class="os-col-lg-4">
                    <?php echo OsFormHelper::select_field( 'invoice[status]', __( 'Status', 'latepoint' ), OsInvoicesHelper::list_of_statuses_for_select(), $invoice->status, ['theme' => 'simple', 'class' => 'size-small'] ); ?>
                </div>
                <div class="os-col-lg-4">
                    <?php echo OsFormHelper::money_field('invoice[charge_amount]', __('Amount Due', 'latepoint'), $invoice->charge_amount, ['theme' => 'simple', 'class' => 'size-small']); ?>
                </div>
                <div class="os-col-lg-4">
                    <div class="os-form-group os-form-select-group os-form-group-simple">
                        <label for=""><?php esc_html_e('Due At', 'latepoint'); ?></label>
                        <?php echo OsFormHelper::date_picker_field( 'invoice[due_at]', OsTimeHelper::get_readable_date_from_string( OsTimeHelper::date_from_db($invoice->due_at, 'Y-m-d') ), OsTimeHelper::date_from_db($invoice->due_at, 'Y-m-d', OsTimeHelper::get_wp_timezone_name()), [ 'class' => 'size-small', 'theme' => 'simple' ]); ?>
                    </div>
                </div>
            </div>
            <?php echo OsFormHelper::hidden_field('invoice_id', $invoice->id); ?>
	</div>
    <?php if(empty($success)){ ?>
	<div class="latepoint-lightbox-footer">
		<button type="submit" class="latepoint-btn latepoint-btn-primary latepoint-btn-position-end "><?php esc_html_e('Update', 'latepoint'); ?></button>
	</div>
    <?php } ?>
</form>