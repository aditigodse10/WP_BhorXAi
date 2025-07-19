<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $order OsOrderModel */
/* @var $invoice OsInvoiceModel */
/* @var $selected_payment_method string */
/* @var $selected_payment_processor string */
/* @var $current_step string */
/* @var $transaction_intent OsTransactionIntentModel */
/* @var $errors array */
/* @var $payment_token string */
/* @var $form_heading string */
/* @var $form_prev_button string */
/* @var $form_prev_button string */
/* @var $invoice_link string */
/* @var $receipt_link string */
/* @var $in_lightbox string */
/* @var $invoice_access_key string */
?>
<?php if(!empty($form_heading)) { ?>
<div class="<?php echo ($in_lightbox == 'yes') ? 'latepoint-lightbox-heading' : 'clean-layout-content-header'; ?>">
    <h2><?php echo esc_html($form_heading); ?></h2>
    <a href="#" class="latepoint-lightbox-close" tabindex="0"><i class="latepoint-icon latepoint-icon-x"></i></a>
</div>
<?php } ?>
<div class="<?php echo ($in_lightbox == 'yes') ? 'latepoint-lightbox-content' : 'clean-layout-content-body'; ?> latepoint-payment-step-<?php echo esc_attr($current_step); ?>">
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
    <?php
    include('payment_form/_'.$current_step.'.php');

    echo OsFormHelper::hidden_field('invoice_id', $transaction_intent->invoice_id);
    echo OsFormHelper::hidden_field('payment_method', $selected_payment_method);
    echo OsFormHelper::hidden_field('payment_processor', $selected_payment_processor);
    echo OsFormHelper::hidden_field('payment_portion', $invoice->payment_portion);
    echo OsFormHelper::hidden_field('payment_token', $payment_token);
    echo OsFormHelper::hidden_field('current_step', $current_step);
    echo OsFormHelper::hidden_field('in_lightbox', $in_lightbox);
    echo OsFormHelper::hidden_field('key', $invoice_access_key);
    ?>
</div>
<?php if(!empty($form_prev_button) || !empty($form_next_button) || !empty($invoice_link) || !empty($receipt_link)){ ?>
<div class="<?php echo ($in_lightbox == 'yes') ? 'latepoint-lightbox-footer' : 'clean-layout-content-footer'; ?>">
	<?php
    if(false && !empty($form_prev_button)) echo '<button type="button" class="latepoint-btn latepoint-btn-secondary latepoint-btn-position-start">'.$form_prev_button.'</a>';
	if(!empty($form_next_button)) echo '<button type="submit" class="latepoint-btn latepoint-btn-primary latepoint-btn-position-end ">'.$form_next_button.'</a>';
	if(!empty($invoice_link)) echo '<a href="'.esc_url($invoice_link).'" target="_blank" class="latepoint-btn latepoint-btn-primary latepoint-btn-block"><span>'.__('View Invoice', 'latepoint').'</span><i class="latepoint-icon latepoint-icon-external-link"></i></a>';
	if(!empty($receipt_link)) echo '<a href="'.esc_url($receipt_link).'" target="_blank" class="latepoint-btn latepoint-btn-primary latepoint-btn-block"><span>'.__('View Receipt', 'latepoint').'</span><i class="latepoint-icon latepoint-icon-external-link"></i></a>';
    ?>
</div>
<?php } ?>