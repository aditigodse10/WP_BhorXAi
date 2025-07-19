<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $invoice OsInvoiceModel */
?>
<div class="invoice-settings-wrapper" data-route="<?php echo esc_attr(OsRouterHelper::build_route_name('invoices', 'create')); ?>">
    <?php echo OsFormHelper::hidden_field('invoice[order_id]', $invoice->order_id); ?>
    <?php echo OsFormHelper::hidden_field('invoice[payment_portion]', $invoice->payment_portion); ?>
    <div class="invoice-settings-heading">
        <div><?php esc_html_e( 'Invoice Settings', 'latepoint' ); ?></div>
        <a href="#" class="invoice-settings-close"><i class="latepoint-icon latepoint-icon-x"></i></a>
    </div>
    <div class="invoice-settings-body">
        <div class="invoice-setting-column">
			<?php
			echo '<div class="label-for-select">' . esc_html__( 'Amount', 'latepoint' ) . '</div>';
			echo '<div class="custom-charge-amount-wrapper">';
			echo OsFormHelper::money_field( 'invoice[charge_amount]', false, 0, [ 'class' => 'size-small', 'theme' => 'simple' ] );
			echo '</div>';
			?>
        </div>
        <div class="invoice-setting-column">
			<?php
			echo '<div class="label-for-select">' . esc_html__( 'Due On', 'latepoint' ) . '</div>';
			echo OsFormHelper::date_picker_field( 'invoice[due_at]', OsTimeHelper::get_readable_date_from_string( OsTimeHelper::today_date( 'Y-m-d' ) ), OsTimeHelper::today_date( 'Y-m-d' ), [
				'class' => 'size-small',
				'theme' => 'simple'
			] );
			?>
        </div>
        <div class="invoice-setting-column">
			<?php
			echo '<div class="label-for-select">' . esc_html__( 'Status', 'latepoint' ) . '</div>';
			echo '<div class="custom-charge-amount-wrapper">';
			echo OsFormHelper::select_field( 'invoice[status]', false, OsInvoicesHelper::list_of_statuses_for_select(), LATEPOINT_INVOICE_STATUS_DRAFT, [ 'class' => 'size-small', 'theme' => 'simple' ] );
			echo '</div>';
			?>
        </div>
    </div>
    <div class="invoice-settings-buttons">
        <a href="#" class="latepoint-btn latepoint-btn-sm latepoint-btn-primary create-invoice-button"><?php esc_html_e( 'Create Invoice', 'latepoint' ); ?></a>
    </div>
</div>