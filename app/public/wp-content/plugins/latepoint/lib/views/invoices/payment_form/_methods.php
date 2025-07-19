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
 * Content for order payment - before payment methods step
 *
 * @param {OsTransactionIntentModel} transaction intent for a payment
 *
 * @since 5.1.0
 * @hook latepoint_order_payment__methods_content_before
 *
 */
do_action( 'latepoint_order_payment__methods_content_before', $transaction_intent );
?>
<div class="lp-payment-methods-w">
    <div class="lp-options lp-options-grid lp-options-grid-three">
        <?php

        foreach ( $enabled_payment_methods as $pay_method_code => $pay_method_processors ) {
            $pay_method_info              = reset( $pay_method_processors );
            $pay_method_info['css_class'] = $pay_method_info['css_class'] ?? 'lp-payment-trigger-payment-method-selector';
            $pay_method_info['attrs']     = $pay_method_info['attrs'] ?? ' data-holder="payment_method" data-value="' . esc_attr( $pay_method_code ) . '" ';
            echo OsStepsHelper::output_list_option( $pay_method_info );
        }

        ?>
    </div>
</div>
<?php
/**
 * Content for order payment - after payment methods step
 *
 * @param {OsTransactionIntentModel} transaction intent for a payment
 *
 * @since 5.1.0
 * @hook latepoint_order_payment__methods_content_after
 *
 */
do_action( 'latepoint_order_payment__methods_content_after', $transaction_intent );