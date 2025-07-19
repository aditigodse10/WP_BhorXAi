<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


if (!class_exists('OsTransactionsController')) :


	class OsTransactionsController extends OsController {

		function __construct() {
			parent::__construct();

			$this->views_folder = LATEPOINT_VIEWS_ABSPATH . 'transactions/';
			$this->vars['page_header'] = OsMenuHelper::get_menu_items_by_id('payments');
			$this->vars['breadcrumbs'][] = array('label' => __('Transactions', 'latepoint'), 'link' => OsRouterHelper::build_link(OsRouterHelper::build_route_name('transactions', 'index')));

			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'view_receipt_by_key' ] );
		}

		public function edit_form() {
			if (filter_var($this->params['id'], FILTER_VALIDATE_INT)) {
				// existing
				$transaction = new OsTransactionModel($this->params['id']);
			}else{
				// new
				$transaction = new OsTransactionModel();
				if (filter_var($this->params['order_id'], FILTER_VALIDATE_INT)) {
					$transaction->order_id = $this->params['order_id'];
				}
			}
			$this->vars['real_or_rand_id'] = ($transaction->is_new_record()) ? 'new_transaction_' . OsUtilHelper::random_text('alnum', 5) : $transaction->id;
			$this->vars['transaction'] = $transaction;

			$this->format_render(__FUNCTION__);
		}

		public function view_receipt_by_key(){
			$receipt_access_key = sanitize_text_field($this->params['key']);

			if(empty($receipt_access_key)) {
				echo __( 'Invalid Receipt Key', 'latepoint' );
				exit;
			}


			$transaction = new OsTransactionModel();
			$transaction = $transaction->where(['access_key' => $receipt_access_key])->set_limit(1)->get_results_as_models();

			if(empty($transaction)) {
				echo __( 'Receipt not found', 'latepoint' );
				exit;
			}

			if(empty($transaction->receipt_number)) $transaction->update_attributes(['receipt_number' => $transaction->generate_receipt_number()]);

			$invoice = new OsInvoiceModel($transaction->invoice_id);

			$this->vars['invoice'] = $invoice;
			$this->vars['transaction'] = $transaction;

			$this->set_layout( 'clean' );
			$this->format_render( __FUNCTION__ );
		}

		public function destroy() {
			if (filter_var($this->params['id'], FILTER_VALIDATE_INT)) {
				$this->check_nonce('destroy_transaction_'.$this->params['id']);
				$transaction = new OsTransactionModel($this->params['id']);
				if ($transaction->delete()) {
					$status = LATEPOINT_STATUS_SUCCESS;
					$response_html = __('Transaction Removed', 'latepoint');
				} else {
					$status = LATEPOINT_STATUS_ERROR;
					$response_html = __('Error Removing Transaction', 'latepoint');
				}
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = __('Error Removing Transaction', 'latepoint');
			}
			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		/*
			Index of transactions
		*/

		public function index() {

			$per_page = OsSettingsHelper::get_number_of_records_per_page();
			$page_number = isset($this->params['page_number']) ? $this->params['page_number'] : 1;

			$this->vars['page_header'] = false;

			$transactions = new OsTransactionModel();


			// TABLE SEARCH FILTERS
			$filter = $this->params['filter'] ?? false;
			$query_args = [];
			if ($filter) {
				if (!empty($filter['id'])) $query_args['id'] = $filter['id'];
				if (!empty($filter['token'])) $query_args['token'] = $filter['token'];
				if (!empty($filter['booking_id'])) $query_args['booking_id'] = $filter['booking_id'];
				if (!empty($filter['processor'])) $query_args['processor'] = $filter['processor'];
				if (!empty($filter['payment_method'])) $query_args['payment_method'] = $filter['payment_method'];
				if (!empty($filter['amount'])) $query_args['amount'] = $filter['amount'];
				if (!empty($filter['status'])) $query_args['status'] = $filter['status'];
				if (!empty($filter['kind'])) $query_args['kind'] = $filter['kind'];

				if (!empty($filter['customer']['full_name'])) {
					$transactions->select(LATEPOINT_TABLE_TRANSACTIONS . '.*, ' . LATEPOINT_TABLE_CUSTOMERS . '.first_name, ' . LATEPOINT_TABLE_CUSTOMERS . '.last_name');
					$transactions->join(LATEPOINT_TABLE_CUSTOMERS, ['id' => LATEPOINT_TABLE_TRANSACTIONS . '.customer_id']);

					$query_args['concat_ws(" ", ' . LATEPOINT_TABLE_CUSTOMERS . '.first_name,' . LATEPOINT_TABLE_CUSTOMERS . '.last_name) LIKE'] = '%' . $filter['customer']['full_name'] . '%';
					$this->vars['customer_name_query'] = $filter['customer']['full_name'];

				}

				if (!empty($filter['created_at_from']) && !empty($filter['created_at_to'])) {
					$query_args['created_at >='] = $filter['created_at_from'] . ' 00:00:00';
					$query_args['created_at <='] = $filter['created_at_to'] . ' 23:59:59';
				}
			}


			// OUTPUT CSV IF REQUESTED
			if (isset($this->params['download']) && $this->params['download'] == 'csv') {
				$csv_filename = 'payments_' . OsUtilHelper::random_text() . '.csv';

				header("Content-Type: text/csv");
				header("Content-Disposition: attachment; filename={$csv_filename}");

				$labels_row = [__('ID', 'latepoint'),
					__('Token', 'latepoint'),
					__('Order ID', 'latepoint'),
					__('Customer', 'latepoint'),
					__('Processor', 'latepoint'),
					__('Method', 'latepoint'),
					__('Amount', 'latepoint'),
					__('Status', 'latepoint'),
					__('Type', 'latepoint'),
					__('Date', 'latepoint')];


				$transactions_data = [];
				$transactions_data[] = $labels_row;


				$transactions_arr = $transactions->where($query_args)->filter_allowed_records()->get_results_as_models();

				if ($transactions_arr) {
					foreach ($transactions_arr as $transaction) {
						$values_row = [
							$transaction->id,
							$transaction->token,
							$transaction->order_id,
							($transaction->customer_id ? $transaction->customer->full_name : 'n/a'),
							$transaction->processor,
							$transaction->payment_method,
							OsMoneyHelper::format_price($transaction->amount, true, false),
							$transaction->status,
							$transaction->kind,
							$transaction->created_at,
						];
						$values_row = apply_filters('latepoint_transaction_row_for_csv_export', $values_row, $transaction, $this->params);
						$transactions_data[] = $values_row;
					}

				}

				$transactions_data = apply_filters('latepoint_transactions_data_for_csv_export', $transactions_data, $this->params);
				OsCSVHelper::array_to_csv($transactions_data);
				return;
			}

			if ($query_args) $transactions->where($query_args);
			$transactions->filter_allowed_records();


			$count_transactions = clone $transactions;
			$total_transactions = $count_transactions->count();

			$transactions = $transactions->order_by(LATEPOINT_TABLE_TRANSACTIONS . '.created_at desc')->set_limit($per_page);
			if ($page_number > 1) {
				$transactions = $transactions->set_offset(($page_number - 1) * $per_page);
			}

			$this->vars['transactions'] = $transactions->get_results_as_models();

			$this->vars['total_transactions'] = $total_transactions;
			$this->vars['current_page_number'] = $page_number;
			$this->vars['per_page'] = $per_page;
			$total_pages = ceil($total_transactions / $per_page);
			$this->vars['total_pages'] = $total_pages;

			$this->vars['showing_from'] = (($page_number - 1) * $per_page) ? (($page_number - 1) * $per_page) : 1;
			$this->vars['showing_to'] = min($page_number * $per_page, $total_transactions);

			$this->format_render(['json_view_name' => '_table_body', 'html_view_name' => __FUNCTION__], [], ['total_pages' => $total_pages, 'showing_from' => $this->vars['showing_from'], 'showing_to' => $this->vars['showing_to'], 'total_records' => $total_transactions]);
		}


		public function refund_transaction() {
			try {
				if ( ! filter_var( $this->params['transaction_refund']['transaction_id'], FILTER_VALIDATE_INT ) ) {
					throw new Exception(esc_html__( 'Invalid Transaction', 'latepoint' ));
				}

				$transaction = new OsTransactionModel( $this->params['transaction_refund']['transaction_id'] );

				if ( empty( $transaction ) || !$transaction->can_refund() ) {
					throw new Exception(esc_html__( 'Invalid Transaction', 'latepoint' ));
				}

				$refund_amount = ( $this->params['transaction_refund']['portion'] == 'custom' ) ? $this->params['transaction_refund']['custom_amount'] : ( $transaction->amount - $transaction->get_total_refunded_amount() );
				$refund_amount = OsParamsHelper::sanitize_param( $refund_amount, 'money' );

				if ( empty( $refund_amount ) || $refund_amount > $transaction->amount - $transaction->get_total_refunded_amount() ) {
					throw new Exception( __( 'Invalid Refund Amount', 'latepoint' ) );
				}

				/**
				 * Process refund for different payment gateways
				 *
				 * @param {bool | OsTransactionRefundModel} $transaction_refund
				 * @param {OsTransactionModel} $transaction
				 * @param {array} $refund_amount
				 *
				 * @returns {OsTransactionRefundModel}
				 * @since 5.1.8
				 * @hook latepoint_process_refund
				 *
				 */
				$transaction_refund = apply_filters('latepoint_process_refund', false,  $transaction, $refund_amount);

				if ( $transaction_refund ) {
					$this->vars['transaction'] = new OsTransactionModel( $transaction->id ); # reload to get new refund info
					$message                   = $this->render( LATEPOINT_VIEWS_ABSPATH . 'orders/_transaction_box', 'none' );
					$this->send_json( array( 'status' => LATEPOINT_STATUS_SUCCESS, 'message' => $message ) );
				}
			} catch ( Exception $e ) {
				$this->send_json( array( 'status' => LATEPOINT_STATUS_ERROR, 'message' => $e->getMessage() ) );
			}
		}


	}


endif;