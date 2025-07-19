<?php
/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

class OsPaymentRequestModel extends OsModel {
	var $id,
		$invoice_id,
		$order_id,
		$portion,
		$charge_amount,
		$due_at,
		$updated_at,
		$created_at;

	function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_PAYMENT_REQUESTS;

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}


	public function get_invoice(): OsInvoiceModel {
		if ( $this->invoice_id ) {
			if ( ! isset( $this->invoice ) || ( isset( $this->invoice ) && ( $this->invoice->id != $this->invoice_id ) ) ) {
				$this->invoice = new OsInvoiceModel( $this->invoice_id );
			}
		} else {
			$this->invoice = new OsInvoiceModel();
		}

		return $this->invoice;
	}


	public function properties_to_query(): array {
		return [
			'portion' => __( 'Payment Portion', 'latepoint' ),
		];
	}

	public function get_readable_due_at(): string {
		try {
			return OsTimeHelper::get_readable_date( new OsWpDateTime( $this->due_at, new DateTimeZone( 'UTC' ) ) );
		} catch ( Exception $e ) {
			return 'n/a';
		}
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

	public function get_customer(): OsCustomerModel {
		return $this->get_order()->get_customer();
	}

	public function generate_data_vars(): array {

		$vars['id'] = $this->id;
		$vars['portion'] = $this->portion;
		$vars['charge_amount'] = $this->charge_amount;
		$vars['due_at'] = $this->due_at;
		$vars['invoice_id'] = $this->invoice_id;
		$vars['order_id'] = $this->order_id;
		$vars['customer'] = $this->get_customer()->get_data_vars();
		$vars['order']    = $this->get_order()->get_data_vars();

		return $vars;
	}


	protected function params_to_sanitize() {
		return [
			'charge_amount' => 'money',
		];
	}

	protected function params_to_save( $role = 'admin' ) {
		$params_to_save = [
			'id',
			'portion',
			'charge_amount',
			'due_at',
			'invoice_id',
			'order_id'
		];

		return $params_to_save;
	}

	protected function allowed_params( $role = 'admin' ) {
		$allowed_params = [
			'id',
			'portion',
			'charge_amount',
			'due_at',
			'invoice_id',
			'order_id'
		];

		return $allowed_params;
	}


	protected function properties_to_validate() {
		$validations = [];

		return $validations;
	}
}