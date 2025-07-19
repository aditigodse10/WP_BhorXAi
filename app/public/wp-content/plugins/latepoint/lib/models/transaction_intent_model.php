<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsTransactionIntentModel extends OsModel {
	var $id,
		$intent_key,
		$order_id,
		$customer_id,
		$invoice_id,
		$transaction_id,
		$payment_data = '',
		$payment_data_arr,
		$other_data,
		$charge_amount,
		$specs_charge_amount,
		$status,
		$order_form_page_url,
		$updated_at,
		$created_at;

	function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_TRANSACTION_INTENTS;

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}

	public function calculate_specs_charge_amount() {
		/**
		 * Convert transaction intent charge amount to specs
		 *
		 * @param {string} $charge_amount original charge amount of a transaction intent
		 * @param {OsTransactionIntentModel} $transaction_intent transaction intent model
		 *
		 * @returns {string} The filtered to specs charge amount
		 *
		 * @since 5.1.3
		 * @hook latepoint_transaction_intent_specs_charge_amount
		 *
		 */
		$this->specs_charge_amount = apply_filters( 'latepoint_transaction_intent_specs_charge_amount', $this->charge_amount, $this );
	}

	protected function params_to_sanitize() {
		return [
			'charge_amount'        => 'money',
		];
	}


	public function get_payment_data_value( string $key ): string {
		if ( ! isset( $this->payment_data_arr ) ) {
			$this->payment_data_arr = json_decode( $this->payment_data, true );
		}

		return $this->payment_data_arr[ $key ] ?? '';
	}

	public function set_payment_data_value( string $key, string $value, bool $save = true ) {
		$this->payment_data_arr         = json_decode( $this->payment_data, true );
		$this->payment_data_arr[ $key ] = $value;
		$this->payment_data             = wp_json_encode( $this->payment_data_arr );
		if ( $save ) {
			$this->update_attributes( [ 'payment_data' => $this->payment_data ] );
		}
	}


	public function is_processing(): bool {
		return $this->status == LATEPOINT_TRANSACTION_INTENT_STATUS_PROCESSING;
	}
	public function is_failed(): bool {
		return $this->status == LATEPOINT_TRANSACTION_INTENT_STATUS_FAILED;
	}

	public function wait_for_transaction_completion() : OsTransactionIntentModel {
		$attempts = 0;
		$max_attempts = 6;
		$delay_seconds = 2;

		while ($attempts < $max_attempts) {
			if (!$this->is_processing()) {
				return $this;
			}
			sleep($delay_seconds);
			$attempts++;
			$this->load_by_id($this->id);
		}
		if($this->is_processing()){
			// if it's still processing after waiting - mark as failed
			$this->mark_as_failed();
		}
		return $this;
	}

	public function convert_to_transaction() {
		if($this->is_processing()){
			$this->wait_for_transaction_completion();
			if($this->is_failed()){
				$this->add_error( 'transaction_intent_error', __('Can not convert to transaction, because transaction intent conversion is being processed', 'latepoint') );
				return false;
			}
		}

		if($this->is_converted()){
			return $this->transaction_id;
		}


		$this->mark_as_processing();

		try {

			// process payment if there is amount due
			$transaction = OsPaymentsHelper::process_payment_for_transaction_intent( $this );
			if(!$transaction || $transaction->status != LATEPOINT_TRANSACTION_STATUS_SUCCEEDED){
				if(!$transaction){
					$this->add_error('transaction_intent_error', __('No payment processor available to process this transaction intent', 'latepoint'));
				}else{
					if ( $transaction->get_error() ) {
						$this->add_error('transaction_intent_error', $transaction->get_error_messages());
					}
				}
				$this->mark_as_new();
				return false;
			}

			/**
			 * Filters transaction right before it's about to be saved when converting from a transaction intent
			 *
			 * @param {OsTransactionModel} $transaction Transaction to be filtered
			 * @returns {OsTransactionModel} The filtered transaction
			 *
			 * @since 5.0.0
			 * @hook latepoint_before_transaction_save_from_transaction_intent
			 *
			 */
			$transaction = apply_filters( 'latepoint_before_transaction_save_from_transaction_intent', $transaction );


			if ( $transaction->save() ) {
				$this->mark_as_converted( $transaction );

				/**
				 * Transaction was created
				 *
				 * @param {OsTransactionModel} $transaction instance of transaction model that was created
				 *
				 * @since 5.0.0
				 * @hook latepoint_transaction_created
				 *
				 */
				do_action( 'latepoint_transaction_created', $transaction );

				if($transaction->invoice_id){
					$invoice = new OsInvoiceModel($transaction->invoice_id);
					if($invoice && !$invoice->is_new_record()){
						$invoice->change_status(LATEPOINT_INVOICE_STATUS_PAID);
						OsOrdersHelper::check_if_order_invoices_paid_full_balance($this->order_id);
					}
				}

				return $transaction->id;
			} else {
				$this->add_error( 'transaction_intent_error', $transaction->get_error_messages() );

				$this->mark_as_new();
				return false;
			}
		} catch ( Exception $e ) {
			$this->mark_as_new();
			// translators: %s is the error description
			$this->add_error( 'transaction_intent_error', sprintf(__('Error: %s', 'latepoint'), $e->getMessage() ));
			OsDebugHelper::log( 'Error converting transaction intent to a transaction', 'transaction_intent_error', $e->getMessage() );
			return false;
		}
	}

	public function get_by_intent_key( $intent_key ) {
		return $this->where( [ 'intent_key' => $intent_key ] )->set_limit( 1 )->get_results_as_models();
	}

	public function mark_as_converted( OsTransactionModel $transaction ) : bool {
		if ( empty( $transaction->id ) ) {
			return false;
		}

		$this->transaction_id = $transaction->id;
		$this->status = LATEPOINT_TRANSACTION_INTENT_STATUS_CONVERTED;

		if($this->save()){
			/**
			 * Transaction intent is converted to transaction
			 *
			 * @param {OsTransactionIntentModel} $transaction_intent Instance of transaction intent model that has been converted to transaction
			 * @param {OsTransactionModel} $transaction Instance of transaction model that transaction intent was converted to
			 *
			 * @since 5.0.0
			 * @hook latepoint_transaction_intent_converted
			 *
			 */
			do_action( 'latepoint_transaction_intent_converted', $this, $transaction );
			return true;
		}else{
			return false;
		}
	}

	public function mark_as_failed() {
		$this->update_attributes( [ 'status' => LATEPOINT_TRANSACTION_INTENT_STATUS_FAILED ] );
		/**
		 * Transaction intent is marked as failed
		 *
		 * @param {OsTransactionIntentModel} $transaction_intent Instance of order intent model that has failed
		 *
		 * @since 5.2.0
		 * @hook latepoint_transaction_intent_failed
		 *
		 */
		do_action( 'latepoint_transaction_intent_failed', $this );
	}

	public function mark_as_processing() {
		$this->update_attributes( [ 'status' => LATEPOINT_TRANSACTION_INTENT_STATUS_PROCESSING ] );
		/**
		 * Transaction intent is marked as processing
		 *
		 * @param {OsTransactionIntentModel} $transaction_intent Instance of order intent model that has started processing
		 *
		 * @since 5.0.0
		 * @hook latepoint_transaction_intent_processing
		 *
		 */
		do_action( 'latepoint_transaction_intent_processing', $this );
	}

	public function mark_as_new() {
		$this->update_attributes( [ 'status' => LATEPOINT_TRANSACTION_INTENT_STATUS_NEW ] );
		/**
		 * Order intent is marked as new
		 *
		 * @param {OsTransactionIntentModel} $transaction_intent Instance of order intent model that is being marked as new
		 *
		 * @since 5.0.0
		 * @hook latepoint_transaction_intent_new
		 *
		 */
		do_action( 'latepoint_transaction_intent_new', $this );
	}

	// Determines if order intent has been converted into a order already
	public function is_converted() : bool {
		if ( empty( $this->transaction_id ) ) {
			return false;
		} else {
			return true;
		}
	}

	public function generate_data_vars(): array {
		$vars = [
			'id'                    => $this->id,
			'intent_key'            => $this->intent_key,
			'payment_data'          => !empty($this->payment_data) ? json_decode( $this->payment_data, true ) : [],
			'order_id'              => $this->order_id,
			'transaction_id'              => $this->transaction_id,
			'order_form_page_url' => $this->order_form_page_url,
			'updated_at'            => $this->updated_at,
			'created_at'            => $this->created_at,
		];

		return $vars;
	}

	public function get_page_url_with_intent() {
		$order_form_page_url      = $this->order_form_page_url;
		$existing_var_position = strpos( $order_form_page_url, 'latepoint_transaction_intent_key=' );
		if ( $existing_var_position === false ) {
			// no intent variable in url
			$question_position = strpos( $order_form_page_url, '?' );
			if ( $question_position === false ) {
				// no ?query params
				$hash_position = strpos( $order_form_page_url, '#' );
				if ( $hash_position === false ) {
					// no hashtag in url
					$order_form_page_url = $order_form_page_url . '?latepoint_transaction_intent_key=' . $this->intent_key;
				} else {
					// hashtag in url and no ?query, prepend the hashtag with query
					$order_form_page_url = substr_replace( $order_form_page_url, '?latepoint_transaction_intent_key=' . $this->intent_key . '#', $hash_position, 1 );
				}
			} else {
				// ?query string exists, add intent key to it
				$order_form_page_url = substr_replace( $order_form_page_url, '?latepoint_transaction_intent_key=' . $this->intent_key . '&', $question_position, 1 );
			}
		} else {
			// intent key variable exist in url
			preg_match( '/latepoint_transaction_intent_key=([\d,\w]*)/', $order_form_page_url, $matches );
			if ( isset( $matches[1] ) ) {
				$order_form_page_url = str_replace( 'latepoint_transaction_intent_key=' . $matches[1], 'latepoint_transaction_intent_key=' . $this->intent_key, $order_form_page_url );
			}
		}

		return $order_form_page_url;
	}

	public function generate_intent_key() {
		$this->intent_key = bin2hex( openssl_random_pseudo_bytes( 10 ) );
	}


	public function get_customer() : OsCustomerModel {
		if ( $this->customer_id ) {
			if ( ! isset( $this->customer ) || ( isset( $this->customer ) && ( $this->customer->id != $this->customer_id ) ) ) {
				$this->customer = new OsCustomerModel( $this->customer_id );
			}
		} else {
			$this->customer = new OsCustomerModel();
		}

		return $this->customer;
	}


	protected function before_create() {
		if ( empty( $this->intent_key ) ) {
			$this->intent_key = bin2hex( openssl_random_pseudo_bytes( 10 ) );
		}
		if ( empty( $this->status ) ) {
			$this->status = LATEPOINT_TRANSACTION_INTENT_STATUS_NEW;
		}
	}

	protected function allowed_params( $role = 'admin' ) {
		$allowed_params = array(
			'payment_data',
			'intent_key',
			'order_id',
			'customer_id',
			'invoice_id',
			'transaction_id',
			'order_form_page_url',
			'status',
		);

		return $allowed_params;
	}


	protected function params_to_save( $role = 'admin' ) {
		$params_to_save = array(
			'payment_data',
			'intent_key',
			'charge_amount',
			'specs_charge_amount',
			'order_id',
			'customer_id',
			'invoice_id',
			'transaction_id',
			'status',
		);

		return $params_to_save;
	}


	protected function properties_to_validate() {
		$validations = array(
			'order_id' => array( 'presence' ),
		);

		return $validations;
	}
}