<?php

class OsTransactionRefundModel extends OsModel {
	public $id,
		$token,
		$transaction_id,
		$amount,
		$updated_at,
		$created_at;

	function __construct($id = false) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_TRANSACTION_REFUNDS;
		$this->nice_names = ['token' => __('Confirmation Number', 'latepoint')];

		if ($id) {
			$this->load_by_id($id);
		}
	}

	public function properties_to_query(): array{
		return [
			'payment_method' => __('Payment Method', 'latepoint'),
			'payment_portion' => __('Payment Portion', 'latepoint'),
			'kind' => __('Type', 'latepoint'),
		];
	}

	public function generate_data_vars(): array {
		return [
			'id' => $this->id,
			'token' => $this->token,
			'transaction_id' => $this->transaction_id,
			'amount' => OsMoneyHelper::format_price($this->amount),
		];
	}

	protected function params_to_sanitize() {
		return ['amount' => 'money'];
	}


	protected function params_to_save($role = 'admin'): array {
		$params_to_save = array('id',
			'token',
			'transaction_id',
			'amount');
		return $params_to_save;
	}


	protected function allowed_params($role = 'admin'): array {
		$allowed_params = array('id',
			'token',
			'transaction_id',
			'amount');
		return $allowed_params;
	}


	protected function properties_to_validate() :array {
		$validations = array(
			'transaction_id' => array('presence'),
			'token' => array('presence'),
		);
		return $validations;
	}
}