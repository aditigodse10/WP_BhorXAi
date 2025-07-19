<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/* @var $transaction OsTransactionModel */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


?>
<div class="quick-transaction-info-w"
	data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('transactions', 'edit_form')); ?>"
	data-os-params="<?php echo esc_attr(OsUtilHelper::build_os_params(['id' => $transaction->id])); ?>"
	data-os-after-call="latepoint_init_quick_transaction_form"
	data-os-before-after="replace">
    <?php if($transaction->get_refunds()){ ?>
            <?php
            foreach($transaction->get_refunds() as $refund) {
                echo '<div class="quick-transaction-refunds-info">';
                echo sprintf(esc_html__('Refunded %s on %s', 'latepoint'), OsMoneyHelper::format_price($refund->amount, true, false), $refund->readable_created_date());
                echo '</div>';
            }
            ?>
    <?php } ?>
  <div class="quick-transaction-head">
    <div class="quick-transaction-amount"><?php echo esc_html(OsMoneyHelper::format_price($transaction->amount, true, false)); ?></div>
    <div class="lp-processor-logo lp-processor-logo-<?php echo esc_attr($transaction->processor); ?>"><?php echo esc_html($transaction->processor); ?></div>
    <div class="lp-transaction-status lp-transaction-status-<?php echo esc_attr($transaction->status); ?>"><?php echo esc_html($transaction->status); ?></div>
  </div>
  <div class="quick-transaction-sub">
    <div><?php echo esc_html($transaction->readable_created_date()); ?></div>
    <div><?php echo esc_html($transaction->token); ?></div>
  </div>
</div>