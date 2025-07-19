<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsInvoicesController' ) ) :


	class OsInvoicesController extends OsController {

		function __construct() {
			parent::__construct();

			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'view_by_key', 'payment_form', 'summary_before_payment' ] );

			$this->views_folder = LATEPOINT_VIEWS_ABSPATH . 'invoices/';
		}


		public function new_form() {
			$order_id = absint( sanitize_text_field( $this->params['order_id'] ) );

			if ( ! is_numeric( $order_id ) ) {
				echo __( 'Invalid Order ID', 'latepoint' );

				return;
			}

			$order = new OsOrderModel( $order_id );
			if ( empty( $order ) || $order->is_new_record() ) {
				echo __( 'Invalid Order ID', 'latepoint' );

				return;
			}

			$invoice                  = new OsInvoiceModel();
			$invoice->order_id        = $order->id;
			$invoice->payment_portion = LATEPOINT_PAYMENT_PORTION_CUSTOM;

			$this->vars['invoice'] = $invoice;

			$this->format_render( __FUNCTION__ );
		}

		private function get_invoice_params() {
			$invoice_params                    = $this->params['invoice'];

			// input date is in WP format (or in viewer's format), we need to make it "end of the day" and also convert to UTC timezone
			$due_at_wp_time = sanitize_text_field( $invoice_params['due_at'] ).' 23:59:59';
			$invoice_params['due_at'] = OsWpDateTime::os_createFromFormat(LATEPOINT_DATETIME_DB_FORMAT, $due_at_wp_time)->setTimezone(new DateTimeZone('UTC'))->format(LATEPOINT_DATETIME_DB_FORMAT);

			$invoice_params['order_id']        = absint( sanitize_text_field( $this->params['invoice']['order_id'] ) );
			$invoice_params['payment_portion'] = sanitize_text_field( $this->params['invoice']['payment_portion'] );
			$invoice_params['charge_amount']   = OsParamsHelper::sanitize_param( sanitize_text_field( $this->params['invoice']['charge_amount'] ), 'money' );

			$errors = [];
			if ( ! in_array( $invoice_params['payment_portion'], array_keys( OsPaymentsHelper::get_payment_portions_list() ) ) ) {
				$errors[] = __( 'Invalid payment portion', 'latepoint' );
			}
			if ( ! is_numeric( $invoice_params['order_id'] ) ) {
				$errors[] = __( 'Invalid Order ID', 'latepoint' );
			}
			if ( ! empty( $errors ) ) {
				return new WP_Error( 'invalid_params', implode( ', ', $errors ) );
			}

			return $invoice_params;
		}

		public function process_data_update() {

			if ( ! filter_var( $this->params['invoice_id'], FILTER_VALIDATE_INT ) ) {
				echo 'Invalid invoice';

				return;
			}
			$invoice = new OsInvoiceModel( $this->params['invoice_id'] );
			$old_invoice = clone $invoice;

			if ( empty( $invoice ) || $invoice->is_new_record() ) {
				echo 'Invalid invoice';

				return;
			}

			$invoice->charge_amount = OsParamsHelper::sanitize_param( sanitize_text_field( $this->params['invoice']['charge_amount'] ), 'money' );
			$due_at_wp_time = sanitize_text_field( $this->params['invoice']['due_at'] ).' 23:59:59';
			$due_at_utc_time = OsWpDateTime::os_createFromFormat(LATEPOINT_DATETIME_DB_FORMAT, $due_at_wp_time)->setTimezone(new DateTimeZone('UTC'))->format(LATEPOINT_DATETIME_DB_FORMAT);
			$invoice->due_at        = $due_at_utc_time;
			$invoice->status        = sanitize_text_field( $this->params['invoice']['status'] );

			if ( $invoice->save() ) {

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
				do_action( 'latepoint_invoice_updated', $invoice, $old_invoice );
				$status = LATEPOINT_STATUS_SUCCESS;
				ob_start();
				OsInvoicesHelper::invoice_document_html( $invoice, true );
				$message = ob_get_clean();

			} else {
				$status  = LATEPOINT_STATUS_ERROR;
				$message = $invoice->get_error_messages();
			}

			$this->send_json( [ 'status' => $status, 'message' => $message ] );
		}

		public function edit_data() {
			if ( ! filter_var( $this->params['invoice_id'], FILTER_VALIDATE_INT ) ) {
				echo __( 'Invalid Invoice ID', 'latepoint' );

				return;
			}
			$invoice = new OsInvoiceModel( $this->params['invoice_id'] );
			if ( empty( $invoice ) || $invoice->is_new_record() ) {
				echo __( 'Invoice not found', 'latepoint' );

				return;
			}

			$this->vars['invoice'] = $invoice;

			$this->format_render( __FUNCTION__ );
		}

		public function reload_invoice_tile() {
			if ( ! filter_var( $this->params['invoice_id'], FILTER_VALIDATE_INT ) ) {
				echo 'Invalid invoice';

				return;
			}
			$invoice = new OsInvoiceModel( $this->params['invoice_id'] );
			if ( empty( $invoice ) || $invoice->is_new_record() ) {
				echo 'Invalid invoice';

				return;
			}

			$this->send_json( [ 'status' => LATEPOINT_STATUS_SUCCESS, 'message' => OsInvoicesHelper::generate_invoice_tile_on_order_edit_form( $invoice ) ] );
		}

		public function create() {
			$invoice_params = $this->get_invoice_params();
			if ( is_wp_error( $invoice_params ) ) {
				$this->send_json( [ 'status' => LATEPOINT_STATUS_ERROR, 'message' => $invoice_params->get_error_message() ] );

				return;
			}

			$order = new OsOrderModel( $invoice_params['order_id'] );
			if ( empty( $order ) || $order->is_new_record() ) {
				echo __( 'Invalid Order ID', 'latepoint' );

				return;
			}

			$invoice = new OsInvoiceModel();
			$invoice->set_data( $invoice_params );

			$invoice->data = json_encode( OsInvoicesHelper::generate_invoice_data_from_order( $order ) );
			if ( $invoice->save() ) {
				/**
				 * Invoice was created
				 *
				 * @param {OsInvoiceModel} $invoice instance of invoice model that was created
				 *
				 * @since 5.1.0
				 * @hook latepoint_invoice_created
				 *
				 */
				do_action( 'latepoint_invoice_created', $invoice );
				$response_html = OsInvoicesHelper::generate_invoice_tile_on_order_edit_form( $invoice );
				$this->send_json( [ 'status' => LATEPOINT_STATUS_SUCCESS, 'message' => $response_html ] );
			} else {
				$this->send_json( [ 'status' => LATEPOINT_STATUS_ERROR, 'message' => __( 'Error: ', 'latepoint' ) . $invoice->get_error_messages() ] );
			}

		}

		public function change_status() {
			if ( ! filter_var( $this->params['invoice_id'], FILTER_VALIDATE_INT ) ) {
				echo 'Invalid Invoice';

				return;
			}

			if ( ! in_array( $this->params['status'], array_keys( OsInvoicesHelper::list_of_statuses_for_select() ) ) ) {
				echo 'Invalid Status';

				return;
			}

			$invoice = new OsInvoiceModel( $this->params['invoice_id'] );
			$invoice->change_status( $this->params['status'] );

			$status = LATEPOINT_STATUS_SUCCESS;

			ob_start();
			OsInvoicesHelper::invoice_document_html( $invoice, true );
			$response_html = ob_get_clean();

			if ( $this->get_return_format() == 'json' ) {

				$this->send_json( [ 'status' => $status, 'message' => $response_html ] );
			}
		}

		public function email_form() {
			if ( ! filter_var( $this->params['invoice_id'], FILTER_VALIDATE_INT ) ) {
				echo __( 'Invalid Invoice ID', 'latepoint' );

				return;
			}
			$invoice = new OsInvoiceModel( $this->params['invoice_id'] );
			if ( empty( $invoice ) || $invoice->is_new_record() ) {
				echo __( 'Invoice not found', 'latepoint' );

				return;
			}
			$errors = [];

			$to      = __( '{{customer_email}}', 'latepoint' );
			$subject = OsInvoicesHelper::get_subject_for_invoice_email();
			$content = OsInvoicesHelper::get_content_for_invoice_email();

			if ( ! empty( $this->params['invoice_email'] ) ) {
				// send email
				$to       = $this->params['invoice_email[to]'] ?? $to;
				$order    = new OsOrderModel( $invoice->order_id );
				$customer = new OsCustomerModel( $order->customer_id );

				$original_to = $to;
				$to          = OsReplacerHelper::replace_all_vars( $to, [ 'order' => $order, 'customer' => $customer, 'invoice' => $invoice ] );
				$subject     = OsReplacerHelper::replace_all_vars( $subject, [ 'order' => $order, 'customer' => $customer, 'invoice' => $invoice ] );
				$content     = OsReplacerHelper::replace_all_vars( $content, [ 'order' => $order, 'customer' => $customer, 'invoice' => $invoice ] );
				if ( OsUtilHelper::is_valid_email( $to ) ) {
					$mailer = new OsMailer();
					wp_mail( $to, $subject, $content, $mailer->get_headers() );
					// set back so it appears correctly on the front
					$to                    = $original_to;
					$this->vars['success'] = __( 'Invoice email sent', 'latepoint' );
				} else {
					$errors[] = __( 'Please enter a valid email address.', 'latepoint' );
				}

			}

			$this->vars['errors']  = $errors;
			$this->vars['to']      = $to;
			$this->vars['subject'] = $subject;
			$this->vars['content'] = $content;
			$this->vars['invoice'] = $invoice;

			$this->format_render( __FUNCTION__ );
		}


		public function payment_form() {
			$invoice_access_key = sanitize_text_field( $this->params['key'] );
			if ( empty( $invoice_access_key ) ) {
				echo __( 'Invalid Invoice Key', 'latepoint' );
				exit;
			}

			$invoice = OsInvoicesHelper::get_invoice_by_key( $invoice_access_key );
			if ( $invoice->is_new_record() ) {
				echo __( 'Invoice not found', 'latepoint' );
				exit;
			}

			$errors = [];
			$order  = $invoice->get_order();

			// find an existing transaction intent for this invoice

			$transaction_intent = new OsTransactionIntentModel();
			$transaction_intent = $transaction_intent->where( [ 'status' => [
					LATEPOINT_TRANSACTION_INTENT_STATUS_NEW,
					LATEPOINT_TRANSACTION_INTENT_STATUS_PROCESSING,
					LATEPOINT_TRANSACTION_INTENT_STATUS_CONVERTED
				], 'invoice_id' => $invoice->id ] )->set_limit( 1 )->get_results_as_models();
			if ( empty( $transaction_intent ) ) {
				$transaction_intent = new OsTransactionIntentModel();
			}

			$transaction_intent->charge_amount = $invoice->charge_amount;
			$transaction_intent->invoice_id    = $invoice->id;
			$transaction_intent->order_id      = $order->id;
			$transaction_intent->customer_id   = $order->customer_id;
			$transaction_intent->set_payment_data_value( 'time', LATEPOINT_PAYMENT_TIME_NOW, false );
			$transaction_intent->set_payment_data_value( 'portion', $invoice->payment_portion, false );

			$form_prev_button = esc_html__( 'Back', 'latepoint' );
			$form_next_button = esc_html__( 'Next', 'latepoint' );
			$invoice_link     = '';
			$receipt_link     = '';

			$selected_payment_method    = $this->params['payment_method'] ?? '';
			$selected_payment_processor = $this->params['payment_processor'] ?? '';
			$payment_token              = $this->params['payment_token'] ?? '';

			$enabled_payment_methods = OsPaymentsHelper::get_enabled_payment_methods_for_payment_time( LATEPOINT_PAYMENT_TIME_NOW );
			// if only one available, force select it
			if ( count( $enabled_payment_methods ) == 1 ) {
				$selected_payment_method = array_key_first( $enabled_payment_methods );
			}

			if ( $selected_payment_method ) {
				$enabled_payment_processors = OsPaymentsHelper::get_enabled_payment_processors_for_payment_time_and_method( LATEPOINT_PAYMENT_TIME_NOW, $selected_payment_method );
				if ( count( $enabled_payment_processors ) == 1 ) {
					$selected_payment_processor = array_key_first( $enabled_payment_processors );
				}
			}

			if ( ! $selected_payment_method ) {
				$current_step     = 'methods';
				$form_heading     = __( 'Payment Methods', 'latepoint' );
				$form_prev_button = false;
				$form_next_button = false;
			} else {
				$transaction_intent->set_payment_data_value( 'method', $selected_payment_method, false );
				if ( ! $selected_payment_processor ) {
					$current_step = 'processors';
					$form_heading = __( 'Payment Processors', 'latepoint' );

					// hide prev button if we don't need to pick a payment methods
					if ( count( $enabled_payment_methods ) <= 1 ) {
						$form_prev_button = false;
					}
					$form_next_button = false;
				} else {
					$transaction_intent->set_payment_data_value( 'processor', $selected_payment_processor, false );
					$form_next_button = sprintf( esc_html__( 'Pay %s', 'latepoint' ), OsMoneyHelper::format_price( $transaction_intent->charge_amount, true, false ) );
					$form_heading     = __( 'Payment Form', 'latepoint' );
					// hide prev button if we don't need to pick a payment method or processor
					if ( count( $enabled_payment_methods ) <= 1 && count( $enabled_payment_processors ) <= 1 ) {
						$form_prev_button = false;
					}
					if ( $payment_token ) {
						$transaction_intent->set_payment_data_value( 'token', $payment_token, false );
					}
					if ( ! $payment_token || empty( $this->params['submitting_payment'] ) ) {
						$current_step = 'pay';
						$transaction_intent->calculate_specs_charge_amount();
						$transaction_intent->save();
					} else {
						$transaction_id = $transaction_intent->convert_to_transaction();
						if ( $transaction_id ) {
							$transaction               = new OsTransactionModel( $transaction_id );
							$form_next_button          = false;
							$form_prev_button          = false;
							$invoice_link              = apply_filters( 'latepoint_transaction_invoice_link', $invoice_link, $invoice );
							$receipt_link              = apply_filters( 'latepoint_transaction_receipt_link', $receipt_link, $invoice, $transaction );
							$current_step              = 'confirmation';
							$this->vars['transaction'] = $transaction;
							$form_heading              = __( 'Confirmation', 'latepoint' );
						} else {
							$current_step = 'pay';
							$errors[]     = implode( ', ', $transaction_intent->get_error_messages() );
						}
					}
				}
			}


			$this->vars['invoice']                    = $invoice;
			$this->vars['invoice_link']               = $invoice_link;
			$this->vars['receipt_link']               = $receipt_link;
			$this->vars['form_heading']               = $form_heading;
			$this->vars['payment_token']              = $payment_token;
			$this->vars['errors']                     = $errors;
			$this->vars['in_lightbox']                = $this->params['in_lightbox'] ?? 'yes';
			$this->vars['transaction_intent']         = $transaction_intent;
			$this->vars['current_step']               = $current_step;
			$this->vars['selected_payment_method']    = $selected_payment_method;
			$this->vars['selected_payment_processor'] = $selected_payment_processor;
			$this->vars['enabled_payment_methods']    = $enabled_payment_methods;

			$this->vars['form_next_button']   = $form_next_button;
			$this->vars['form_prev_button']   = $form_prev_button;
			$this->vars['invoice_access_key'] = $invoice_access_key;


			$this->vars['order'] = $order;

			$this->format_render( __FUNCTION__ );
		}

		public function summary_before_payment() {
			$invoice_access_key = sanitize_text_field( $this->params['key'] );
			if ( empty( $invoice_access_key ) ) {
				echo __( 'Invalid Invoice Key', 'latepoint' );
				exit;
			}

			$invoice = OsInvoicesHelper::get_invoice_by_key( $invoice_access_key );
			if ( $invoice->is_new_record() ) {
				echo __( 'Invoice not found', 'latepoint' );
				exit;
			}

			$this->vars['invoice'] = $invoice;
			$this->vars['order']   = $invoice->get_order();

			if ( $this->get_return_format() == 'json' ) {
				$this->vars['in_lightbox'] = true;
				$this->set_layout( 'none' );
				$response_html = $this->format_render_return( __FUNCTION__ );
				$this->send_json( [ 'status' => LATEPOINT_STATUS_SUCCESS, 'message' => $response_html ] );
			} else {
				$this->vars['in_lightbox'] = false;
				$this->set_layout( 'clean' );
				$this->format_render( __FUNCTION__ );
			}
		}


		function view_by_key() {
			$invoice_access_key    = sanitize_text_field( $this->params['key'] );
			$invoice               = new OsInvoiceModel();
			$invoice               = $invoice->where( [ 'access_key' => $invoice_access_key ] )->set_limit( 1 )->get_results_as_models();
			$this->vars['invoice'] = $invoice;

			$this->set_layout( 'clean' );
			$this->format_render( __FUNCTION__ );
		}

		function view() {
			if ( ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				return;
			}

			$invoice = new OsInvoiceModel( $this->params['id'] );

			$this->vars['invoice'] = $invoice;

			$this->set_layout( 'none' );
			$response_html = $this->format_render_return( __FUNCTION__ );

			$status = LATEPOINT_STATUS_SUCCESS;

			if ( $this->get_return_format() == 'json' ) {

				$this->send_json( [ 'status' => $status, 'message' => $response_html ] );
			}
		}
	}

endif;
