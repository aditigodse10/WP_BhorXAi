<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $enabled_payment_methods array */
/* @var $form_prev_button string */
/* @var $form_next_button string */
/* @var $transaction_intent OsTransactionIntentModel */
/* @var $errors array */
?>

<?php
/**
 * Content for order payment - pay step before
 *
 * @param {OsTransactionIntentModel} transaction intent for a payment
 *
 * @since 5.1.0
 * @hook latepoint_order_payment__pay_content_before
 *
 */
do_action( 'latepoint_order_payment__pay_content_before', $transaction_intent );

echo '<div class="lp-payment-charge-amount">'.sprintf(esc_html__('Charge Amount: %s', 'latepoint'), '<strong>'.OsMoneyHelper::format_price($transaction_intent->charge_amount, true, false).'</strong>').'</div>';
echo OsFormHelper::hidden_field('submitting_payment', 'yes');

/**
 * Content for order payment - pay step after
 *
 * @param {OsTransactionIntentModel} transaction intent for a payment
 *
 * @since 5.1.0
 * @hook latepoint_order_payment__pay_content_after
 *
 */
do_action( 'latepoint_order_payment__pay_content_after', $transaction_intent );
?>