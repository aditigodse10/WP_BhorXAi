<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsTransactionHelper {


	/**
	 * @param OsTransactionModel $transaction
	 *
	 * @return void
	 */
	public static function output_refund_button( OsTransactionModel $transaction ) {
		if (!$transaction->can_refund()) return;

		echo '<div class="transaction-refund-settings">';
		echo '<div class="refund-settings-heading"><div>' . esc_html__( 'Refund Amount', 'latepoint' ) . '</div><div class="refund-settings-close"><i class="latepoint-icon latepoint-icon-x"></i></div></div>';
		echo '<div class="refund-settings-fields">';
		$full_amount_label = sprintf( __( 'Full [%s]' ), OsMoneyHelper::format_price( ( $transaction->amount - $transaction->get_total_refunded_amount() ), true, false ) );
		echo OsFormHelper::select_field( 'transaction_refund[portion]', false, [
			'full'   => $full_amount_label,
			'custom' => __( 'Custom', 'latepoint' )
		], 'full', [ 'class' => 'size-small refund-portion-selector', 'theme' => 'simple' ] );
		echo '<div class="custom-charge-amount-wrapper" style="display: none;">';
		echo OsFormHelper::money_field( 'transaction_refund[custom_amount]', false, ( $transaction->amount - $transaction->get_total_refunded_amount() ), [
			'class' => 'size-small',
			'theme' => 'simple'
		] );
		echo OsFormHelper::hidden_field( 'transaction_refund[transaction_id]', $transaction->id );
		echo '</div>';
		echo '<a href="#" class="latepoint-btn latepoint-btn-primary latepoint-btn-sm transaction-refund-submit-button" data-os-prompt="' . esc_attr__( 'Are you sure you want to refund this transaction?', 'latepoint' ) . '" data-route="' . esc_attr( OsRouterHelper::build_route_name( 'transactions', 'refund_transaction' ) ) . '">' . esc_html__( 'Submit', 'latepoint' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="transaction-refund-button-w">';
		echo '<a href="#" class="latepoint-btn latepoint-btn-sm latepoint-btn-danger transaction-refund-settings-button" title="' . esc_attr__( 'Refund Transaction', 'latepoint' ) . '" >' . esc_html__( 'Issue a Refund', 'latepoint' ) . '</a>';
		echo '</div>';
	}
}