<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $enabled_payment_processors array */
/* @var $form_prev_button string */
/* @var $form_next_button string */
/* @var $transaction_intent OsTransactionIntentModel */
/* @var $errors array */
?>
<?php
/**
 * Content for order payment - before payment processors step
 *
 * @param {OsTransactionIntentModel} transaction intent for a payment
 *
 * @since 5.1.0
 * @hook latepoint_order_payment__processors_content_before
 *
 */
do_action( 'latepoint_order_payment__processors_content_before', $transaction_intent );
?>
    <div class="lp-payment-methods-w">
        <div class="lp-options lp-options-grid lp-options-grid-three">
			<?php
			foreach ( $enabled_payment_processors as $pay_processor_code => $pay_processor ) {
				$pay_processor['label']     = $pay_processor['front_name'] ?? $pay_processor['name'];
				$pay_processor['css_class'] = $pay_processor['css_class'] ?? 'lp-payment-trigger-payment-processor-selector';
				$pay_processor['attrs']     = $pay_processor['attrs'] ?? ' data-holder="payment_processor" data-value="' . esc_attr( $pay_processor_code ) . '" ';
				$form_content               = OsStepsHelper::output_list_option( $pay_processor );
			}
			?>
        </div>
    </div>

<?php
/**
 * Content for order payment - after payment processors step
 *
 * @param {OsTransactionIntentModel} transaction intent for a payment
 *
 * @since 5.1.0
 * @hook latepoint_order_payment__processors_content_after
 *
 */
do_action( 'latepoint_order_payment__processors_content_after', $transaction_intent );
