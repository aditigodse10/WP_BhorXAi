<?php

class OsInvoiceModel extends OsModel {
	public $id,
		$order_id,
		$invoice_number,
		$data,
		$charge_amount,
		$payment_portion,
		$status,
		$access_key,
		$due_at,
		$updated_at,
		$created_at;

	function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_ORDER_INVOICES;

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}

	public function get_receipt_url(): string {
		$transaction = new OsTransactionModel();
		$transaction = $transaction->where( [ 'invoice_id' => $this->id, 'status' => LATEPOINT_TRANSACTION_STATUS_SUCCEEDED ] )->set_limit( 1 )->get_results_as_models();
		if ( $transaction && ! $transaction->is_new_record() ) {
			return $transaction->get_receipt_url();
		} else {
			return $this->get_access_url();
		}
	}

	public function properties_to_query(): array {
		return [
			'status'          => __( 'Status', 'latepoint' ),
			'payment_portion' => __( 'Payment Portion', 'latepoint' ),
		];
	}

	public function get_customer(): OsCustomerModel {
		return $this->get_order()->get_customer();
	}


	public function generate_data_vars(): array {

		$vars['id'] = $this->id;
		$vars['order_id'] = $this->order_id;
		$vars['invoice_number'] = $this->get_invoice_number();
		$vars['payment_portion'] = $this->payment_portion;
		$vars['status'] = $this->status;
		$vars['data'] = $this->data;
		$vars['charge_amount'] = $this->charge_amount;
		$vars['access_key'] = $this->access_key;
		$vars['due_at'] = $this->due_at;

		$vars['customer']     = $this->get_customer()->get_data_vars();
		$vars['order']        = $this->get_order()->get_first_level_data_vars();
		$vars['transactions'] = [];

		$transactions = $this->get_successful_payments();
		if ( $transactions ) {
			foreach ( $transactions as $transaction ) {
				$vars['transactions'][] = $transaction->get_data_vars();
			}
		}

		return $vars;
	}

	public function get_order(): OsOrderModel {
		if ( $this->order_id ) {
			if ( ! isset( $this->order ) || ( isset( $this->order ) && ( $this->order->id != $this->order_id ) ) ) {
				$this->order = new OsOrderModel( $this->order_id );
			}
		} else {
			$this->order = new OsOrderModel();
		}

		return $this->order;
	}

	public function get_successful_payments(): array {
		$transactions = new OsTransactionModel();
		$transactions = $transactions->where( [ 'status' => LATEPOINT_TRANSACTION_STATUS_SUCCEEDED, 'invoice_id' => $this->id ] )->get_results_as_models();

		return $transactions;
	}


	protected function params_to_sanitize() {
		return [
			'charge_amount' => 'money',
		];
	}

	/**
	 * @param string $new_status
	 *
	 * @return bool
	 */
	public function change_status( string $new_status ): bool {
		$old_status = $this->status;
		$old_invoice = clone $this;
		if ( $old_status == $new_status ) {
			return true;
		}
		if ( $this->update_attributes( [ 'status' => $new_status ] ) ) {
			/**
			 * Invoice status has been changed
			 *
			 * @param {OsInvoiceModel} $invoice invoice model
			 * @param {string} $old_status previous status of the invoice
			 * @param {string} $new_status new status of the invoice
			 *
			 * @since 5.1.0
			 * @hook latepoint_invoice_status_changed
			 *
			 */
			do_action( 'latepoint_invoice_status_changed', $this, $old_status, $new_status );
			/**
			 * Invoice was updated
			 *
			 * @param {OsInvoiceModel} $invoice instance of invoice model after it was updated
			 * @param {OsInvoiceModel} $old_invoice instance of invoice model before it was updated
			 *
			 * @since 5.1.0
			 * @hook latepoint_invoice_updated
			 *
			 */
			do_action( 'latepoint_invoice_updated', $this, $old_invoice );

			return true;
		} else {
			return false;
		}

	}

	public function get_invoice_number(): string {
		if(!empty($this->invoice_number)) return $this->invoice_number;
		if(empty($this->id)) return '';
		$this->invoice_number = OsSettingsHelper::get_settings_value( 'invoices_number_prefix', 'INV-' ).sprintf('1%06d', $this->id);
		$this->update_attributes(['invoice_number' => $this->invoice_number]);
		return $this->invoice_number;
	}


	protected function before_save() {
		if ( empty( $this->status ) ) {
			$this->status = LATEPOINT_INVOICE_STATUS_OPEN;
		}
		if ( empty( $this->due_at ) ) {
			$this->due_at = OsTimeHelper::now_datetime_in_format( LATEPOINT_DATETIME_DB_FORMAT );
		}
		if ( empty( $this->access_key ) ) {
			$this->access_key = OsUtilHelper::generate_uuid();
		}
	}

	public function get_readable_due_at(): string {
		try {
			return OsTimeHelper::get_readable_date( new OsWpDateTime( $this->due_at, new DateTimeZone( 'UTC' ) ) );
		} catch ( Exception $e ) {
			return 'n/a';
		}
	}

	public function get_access_url(): string {
		return OsRouterHelper::build_admin_post_link( [ 'invoices', 'view_by_key' ], [ 'key' => $this->access_key ] );
	}

	public function get_pay_url(): string {
		return OsRouterHelper::build_admin_post_link( [ 'invoices', 'summary_before_payment' ], [ 'key' => $this->access_key ] );
	}


	protected function params_to_save( $role = 'admin' ): array {
		$params_to_save = [
			'id',
			'order_id',
			'invoice_number',
			'payment_portion',
			'status',
			'data',
			'charge_amount',
			'access_key',
			'due_at',
		];

		return $params_to_save;
	}


	protected function allowed_params( $role = 'admin' ): array {
		$allowed_params = [
			'id',
			'order_id',
			'invoice_number',
			'payment_portion',
			'status',
			'data',
			'charge_amount',
			'access_key',
			'due_at',
		];

		return $allowed_params;
	}


	protected function properties_to_validate(): array {
		$validations = [
			'order_id' => [ 'presence' ],
			'status'   => [ 'presence' ],
		];

		return $validations;
	}
}