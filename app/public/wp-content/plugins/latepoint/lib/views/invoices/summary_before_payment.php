<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/* @var $in_lightbox bool */
/* @var $order OsOrderModel */
/* @var $invoice OsInvoiceModel */
/* @var $order_item OsOrderItemModel */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<?php if(!$in_lightbox){ ?><form class="clean-layout-content-wrapper invoice-payment-summary-wrapper latepoint-transaction-payment-form"><?php } ?>
    <?php if($in_lightbox) echo '<div class="latepoint-lightbox-heading"><h2>'.esc_html__('Balance Details', 'latepoint').'</h2></div>'; ?>
	<div class=" <?php echo $in_lightbox ? 'latepoint-lightbox-content invoice-payment-summary-wrapper' : 'clean-layout-content-body is-dotted'; ?>">
        <div class="invoice-due-amount-wrapper">
            <div class="invoice-due-amount-inner">
                <div class="id-amount"><?php echo OsMoneyHelper::format_price($invoice->charge_amount, true, false); ?></div>
                <div class="id-sub-info">
                    <?php esc_html_e('Order:', 'latepoint'); ?>
                    <a href="<?php echo $order->manage_by_key_url('customer') ?>" target="_blank"><span><?php echo $order->confirmation_code; ?></span><i class="latepoint-icon latepoint-icon-external-link"></i></a>

                    <?php if ( $invoice->status == LATEPOINT_INVOICE_STATUS_PAID || $invoice->get_successful_payments() ) { ?>
                        <a target="_blank" href="<?php echo OsOrdersHelper::generate_direct_manage_order_url( $invoice->get_order(), 'customer', 'list_payments' ) ?>"><span><?php esc_html_e( 'Payments', 'latepoint' ); ?></span><i class="latepoint-icon latepoint-icon-external-link"></i></a>
                    <?php } ?>
                </div>
            </div>
            <?php if($invoice->status == LATEPOINT_INVOICE_STATUS_OPEN){ ?>
                <a href="#"
                   data-os-params="<?php echo esc_attr( http_build_query( [ 'key' => $invoice->access_key, 'in_lightbox' => ($in_lightbox ? 'yes' : 'no') ] ) ); ?>"
                   data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'invoices', 'payment_form' ) ); ?>"
                   data-os-after-call="latepoint_init_transaction_payment_form"
                   data-os-output-target="<?php echo $in_lightbox ? 'lightbox' : '.clean-layout-content-wrapper'; ?>"
                   data-os-lightbox-no-close-button="yes"
                   data-os-lightbox-inner-tag="form"
                   data-os-lightbox-inner-classes="latepoint-transaction-payment-form"
                   data-os-lightbox-classes="width-500"
                   class="latepoint-btn invoice-make-payment-btn">
                    <span><?php echo sprintf( __( 'Pay Now', 'latepoint' ), OsMoneyHelper::format_price( $order->get_total_balance_due(), true, false ) ); ?></span>
                    <i class="latepoint-icon latepoint-icon-arrow-right1"></i>
                </a>
            <?php }else{
                echo '<span class="invoice-status-label invoice-status-label-'.esc_attr($invoice->status).'">'.esc_html(OsInvoicesHelper::readable_status($invoice->status)).'</span>';
            } ?>
        </div>
        <div class="full-summary-info-w">
            <div class="summary-price-breakdown-wrapper">
                <div class="pb-heading">
                    <div class="pbh-label"><?php esc_html_e( 'Order Breakdown', 'latepoint' ); ?></div>
                    <div class="pbh-line"></div>
                </div>
                <?php
                $price_breakdown_rows = $order->generate_price_breakdown_rows();
                OsPriceBreakdownHelper::output_price_breakdown( $price_breakdown_rows );
                ?>
            </div>
        </div>
	</div>
<?php if(!$in_lightbox){ ?></form><?php } ?>
