<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsInvoicesHelper {

	public static function get_invoices_for_select() : array {
		$invoices         = new OsInvoiceModel();
		$invoices         = $invoices->order_by( 'id desc' )->set_limit( 100 )->get_results_as_models();
		$invoice_options = [];
		foreach ( $invoices as $invoice ) {
			$name            = sprintf(esc_html__('%s To %s', 'latepoint'), OsMoneyHelper::format_price($invoice->charge_amount, true, false), $invoice->get_customer()->full_name) . ' [#' . $invoice->get_invoice_number() . ' : ID: ' . $invoice->id . ']';
			$invoice_options[] = [ 'value' => $invoice->id, 'label' => esc_html( $name ) ];
		}

		return $invoice_options;
	}

    public static function replace_invoice_vars_in_template(string $text, array $vars, string $original_text) : string{
	    if ( isset( $vars['invoice'] ) ) {
            $invoice = $vars['invoice'];
            $needles      = [
                '{{invoice_status}}',
                '{{invoice_due_date}}',
                '{{invoice_amount}}',
                '{{invoice_number}}',
                '{{invoice_access_url}}',
                '{{invoice_pay_url}}',
                '{{invoice_receipt_url}}',
            ];
            $replacements = [
                OsInvoicesHelper::readable_status($invoice->status),
                $invoice->get_readable_due_at(),
                OsMoneyHelper::format_price($invoice->charge_amount, true, false),
                $invoice->get_invoice_number(),
                $invoice->get_access_url(),
                $invoice->get_pay_url(),
                $invoice->get_receipt_url()
            ];
            $text         = str_replace( $needles, $replacements, $text );

	    }
        return $text;
    }

	public static function handle_invoice_created(OsInvoiceModel $invoice){
		$objects = [];
		$objects[] = ['model' => 'invoice', 'id' => $invoice->id, 'model_ready' => $invoice];
		OsProcessJobsHelper::create_jobs_for_event('invoice_created', $objects);
	}


	public static function handle_invoice_updated(OsInvoiceModel $new_invoice, OsInvoiceModel $old_invoice){
		// remove previously scheduled jobs for this invoice because it's changed and might not need them anymore
		// remove only those that are in "scheduled" status, those that were already sent or errored should stay
		$jobs = new OsProcessJobModel();
		$jobs->delete_where(['status' => LATEPOINT_JOB_STATUS_SCHEDULED, 'object_id' => $new_invoice->id, 'object_model_type' => 'invoice']);

		$objects = [];
		$objects[] = ['model' => 'invoice', 'id' => $new_invoice->id, 'model_ready' => $new_invoice];
		$objects[] = ['model' => 'old_invoice', 'id' => $old_invoice->id, 'model_ready' => $old_invoice];
		OsProcessJobsHelper::create_jobs_for_event('invoice_updated', $objects);
	}


	public static function log_invoice_created(OsInvoiceModel $invoice) {
		$data = [];
		$data['invoice_id'] = $invoice->id;
		$data['code'] = 'invoice_created';
		$data['description'] = wp_json_encode(['invoice_data_vars' => $invoice->get_data_vars()]);
		OsActivitiesHelper::create_activity($data);
	}

	public static function log_invoice_updated(OsInvoiceModel $invoice, OsInvoiceModel $old_invoice) {
		$data = [];
		$data['invoice_id'] = $invoice->id;
		$data['code'] = 'invoice_updated';
		$data['description'] = wp_json_encode(['invoice_data_vars' => ['new' => $invoice->get_data_vars(), 'old' => $old_invoice->get_data_vars()]]);
		OsActivitiesHelper::create_activity($data);
	}

	public static function readable_status( string $status ): string {
		$statuses = self::list_of_statuses_for_select();

		return $statuses[ $status ] ?? __( 'n/a', 'latepoint' );
	}

	public static function get_invoice_by_key( string $key ): OsInvoiceModel {
		if ( empty( $key ) ) {
			return new OsInvoiceModel();
		}
		$invoice = new OsInvoiceModel();

		return $invoice->where( [ 'access_key' => $key ] )->set_limit( 1 )->get_results_as_models();
	}

    public static function get_invoice_logo_url(): string {
        $default_logo_url = LATEPOINT_IMAGES_URL . 'logo.svg';
        return OsImageHelper::get_image_url_by_id(OsSettingsHelper::get_settings_value('invoices_company_logo'), 'thumbnail', $default_logo_url);
    }

	public static function invoice_document_html( OsInvoiceModel $invoice, bool $show_controls, ?OsTransactionModel $transaction = null ) {
		$invoice_data = json_decode( $invoice->data, true );
		?>
        <div class="invoice-document status-<?php echo esc_attr( $invoice->status ); ?>" data-invoice-id="<?php echo esc_attr($invoice->id); ?>" data-route="<?php echo OsRouterHelper::build_route_name('invoices', 'change_status'); ?>">
			<?php if ( $show_controls ) { ?>
                <div class="invoice-controls">
                    <div class="ic-block">
						<?php echo OsFormHelper::select_field( 'invoice[status]', __( 'Status', 'latepoint' ), OsInvoicesHelper::list_of_statuses_for_select(), $invoice->status, ['class' => 'invoice-change-status-selector'] ); ?>
                    </div>
                    <div class="ic-block">
                        <a target="_blank" href="<?php echo $invoice->get_access_url(); ?>" class="ic-external-link">
                            <span><?php esc_html_e( 'Open', 'latepoint' ); ?></span>
                            <i class="latepoint-icon latepoint-icon-external-link"></i>
                        </a>
                    </div>
                    <div class="ic-block make-last">
                        <button type="button" class="latepoint-btn latepoint-btn-sm latepoint-btn-outline"
                                data-os-params="<?php echo esc_attr(http_build_query( [ 'invoice_id' => $invoice->id ] )); ?>"
                              data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name( 'invoices', 'edit_data' )); ?>"
                              data-os-output-target="lightbox"
                                 data-os-after-call="latepointInvoicesAdminFeature.init_invoice_data_form"
                              data-os-lightbox-classes="width-500">
                            <i class="latepoint-icon latepoint-icon-edit-2"></i>
                            <span><?php esc_html_e( 'Edit Data', 'latepoint' ); ?></span>
                        </button>
                        <button type="button" class="latepoint-btn latepoint-btn-sm latepoint-btn-outline"
                                data-os-params="<?php echo esc_attr(http_build_query( [ 'invoice_id' => $invoice->id ] )); ?>"
                              data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name( 'invoices', 'email_form' )); ?>"
                              data-os-output-target="lightbox"
                                 data-os-after-call="latepointInvoicesAdminFeature.init_email_invoice_form"
                              data-os-lightbox-classes="width-500">
                            <i class="latepoint-icon latepoint-icon-mail"></i>
                            <span><?php esc_html_e( 'Email Invoice', 'latepoint' ); ?></span>
                        </button>
                    </div>
                </div>
			<?php } ?>
            <div class="invoice-document-i">
				<?php
				if ( empty( $transaction ) ) {
					switch ( $invoice->status ) {
						case LATEPOINT_INVOICE_STATUS_PAID:
							echo '<div class="invoice-status-paid-label">' . esc_html( self::readable_status( $invoice->status ) ) . '</div>';
							break;
						case LATEPOINT_INVOICE_STATUS_DRAFT:
							echo '<div class="invoice-status-draft-label">' . esc_html( self::readable_status( $invoice->status ) ) . '</div>';
                            break;
						case LATEPOINT_INVOICE_STATUS_VOID:
							echo '<div class="invoice-status-voided-label">' . esc_html( self::readable_status( $invoice->status ) ) . '</div>';
							break;
					}
				}
				?>
                <div class="invoice-heading">
                    <div class="invoice-info">
                        <div class="invoice-title"><?php echo $transaction ? esc_html__( 'Receipt', 'latepoint' ) : esc_html__( 'Invoice', 'latepoint' ); ?></div>
                        <div class="invoice-data">
                            <div class="invoice-row">
                                <div class="id-label"><?php esc_html_e( 'Invoice number', 'latepoint' ); ?></div>
                                <div class="id-value"><?php echo esc_html( $invoice->get_invoice_number() ); ?></div>
                            </div>
							<?php if ( $transaction ) { ?>
                                <div class="invoice-row">
                                    <div class="id-label"><?php esc_html_e( 'Receipt number', 'latepoint' ); ?></div>
                                    <div class="id-value"><?php echo esc_html( $transaction->receipt_number ); ?></div>
                                </div>
                                <div class="invoice-row">
                                    <div class="id-label"><?php esc_html_e( 'Date paid', 'latepoint' ); ?></div>
                                    <div class="id-value"><?php echo esc_html( OsTimeHelper::get_readable_date( new OsWpDateTime( $transaction->created_at, new DateTimeZone('UTC') ) ) ); ?></div>
                                </div>
							<?php } else { ?>
                                <div class="invoice-row">
                                    <div class="id-label"><?php esc_html_e( 'Date of issue', 'latepoint' ); ?></div>
                                    <div class="id-value"><?php echo esc_html( OsTimeHelper::get_readable_date( new OsWpDateTime( $invoice->created_at, new DateTimeZone('UTC') ) ) ); ?></div>
                                </div>
                                <div class="invoice-row">
                                    <div class="id-label"><?php esc_html_e( 'Date due', 'latepoint' ); ?></div>
                                    <div class="id-value"><?php echo esc_html( OsTimeHelper::get_readable_date( new OsWpDateTime( $invoice->due_at, new DateTimeZone('UTC') ) ) ); ?></div>
                                </div>
							<?php } ?>
                            <?php if(!empty($invoice_data['tax_id'])){ ?>
                            <div class="invoice-row">
                                <div class="id-label"><?php esc_html_e( 'VAT Number', 'latepoint' ); ?></div>
                                <div class="id-value"><?php echo esc_html( $invoice_data['tax_id'] ); ?></div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="invoice-logo">
                        <img src="<?php echo esc_attr( self::get_invoice_logo_url() ); ?>" width="50" height="50" alt="<?php esc_attr_e('LatePoint Dashboard', 'latepoint'); ?>">
                    </div>
                </div>
                <div class="invoice-to-from">
                    <div class="invoice-from">
                        <?php if(!empty($invoice_data['company'])){ ?>
                        <div class="if-heading"><?php echo esc_html( $invoice_data['company'] ); ?></div>
                        <?php } ?>
                        <div class="if-data-block">
                            <?php echo wp_kses($invoice_data['from'], ['br' => []]); ?>
                        </div>
                    </div>
                    <div class="invoice-from">
                        <div class="if-heading"><?php echo esc_html( __('Bill to', 'latepoint') ); ?></div>
                        <div class="if-data-block">
							<?php echo wp_kses($invoice_data['to'], ['br' => []]); ?>
                        </div>
                    </div>
                </div>
				<?php if ( empty( $transaction ) ) { ?>
                    <div class="invoice-due-info">
                        <div class="invoice-due-amount">
							<?php echo esc_html( sprintf( __( '%s due %s', 'latepoint' ), OsMoneyHelper::format_price( $invoice->charge_amount, true, false ), OsTimeHelper::get_readable_date_from_string( $invoice->due_at ) ) ); ?>
                        </div>
						<?php if ( $invoice->status == LATEPOINT_INVOICE_STATUS_OPEN ) { ?>
                            <div class="invoice-due-pay-link-w">
                                <a href="<?php echo $invoice->get_pay_url(); ?>" target="_blank"><?php esc_html_e( 'Pay Online', 'latepoint' ); ?></a>
                            </div>
						<?php } ?>
						<?php if ( $invoice->status == LATEPOINT_INVOICE_STATUS_PAID || $invoice->get_successful_payments() ) { ?>
                            <div class="invoice-due-pay-link-w">
                                <a target="_blank"
                                   href="<?php echo OsOrdersHelper::generate_direct_manage_order_url( $invoice->get_order(), 'customer', 'list_payments' ) ?>"><?php esc_html_e( 'View Payments', 'latepoint' ); ?></a>
                            </div>
						<?php } ?>
                    </div>
				<?php } else { ?>
                    <div class="invoice-due-info">
                        <div class="invoice-due-amount">
							<?php echo esc_html( sprintf( __( '%s paid on %s', 'latepoint' ), OsMoneyHelper::format_price( $transaction->amount, true, false ), OsTimeHelper::get_readable_date_from_string( $transaction->created_at ) ) ); ?>
                        </div>
                    </div>

				<?php } ?>
                <div class="invoice-items">
                    <div class="invoice-items-table-heading">
                        <div class="it-column"><?php esc_html_e( 'Description', 'latepoint' ); ?></div>
                        <div class="it-column"><?php esc_html_e( 'Amount', 'latepoint' ); ?></div>
                    </div>
					<?php OsPriceBreakdownHelper::output_price_breakdown( $invoice_data['price_breakdown'] ); ?>
                </div>
                <div class="invoice-totals">
                    <div class="it-row">
                        <div class="it-column"><?php esc_html_e( 'Subtotal', 'latepoint' ); ?></div>
                        <div class="it-column"><?php echo esc_html( OsMoneyHelper::format_price( $invoice_data['totals']['subtotal'], true, false ) ); ?></div>
                    </div>
                    <div class="it-row">
                        <div class="it-column"><?php esc_html_e( 'Total', 'latepoint' ); ?></div>
                        <div class="it-column"><?php echo esc_html( OsMoneyHelper::format_price( $invoice_data['totals']['total'], true, false ) ); ?></div>
                    </div>
					<?php if ( empty( $transaction ) ) { ?>
						<?php if ( ! empty( $invoice_data['totals']['payments'] ) ) { ?>
                            <div class="it-row it-row-positive">
                                <div class="it-column"><?php esc_html_e( 'Payments & Credits', 'latepoint' ); ?></div>
                                <div class="it-column"><?php echo '-' . esc_html( OsMoneyHelper::format_price( $invoice_data['totals']['payments'], true, false ) ); ?></div>
                            </div>
						<?php } ?>
                        <div class="it-row it-row-bold">
                            <div class="it-column"><?php esc_html_e( 'Amount Due', 'latepoint' ); ?></div>
                            <div class="it-column"><?php echo esc_html( OsMoneyHelper::format_price( $invoice->charge_amount, true, false ) ); ?></div>
                        </div>
					<?php } else { ?>
                        <div class="it-row it-row-bold">
                            <div class="it-column"><?php esc_html_e( 'Amount Paid', 'latepoint' ); ?></div>
                            <div class="it-column"><?php echo esc_html( OsMoneyHelper::format_price( $transaction->amount, true, false ) ); ?></div>
                        </div>
					<?php } ?>
                </div>
				<?php if ( OsSettingsHelper::get_settings_value( 'invoice_terms', '' ) ) { ?>
                    <div class="invoice-terms">
                        <div class="terms-heading"><?php esc_html_e( 'Terms & Conditions', 'latepoint' ); ?></div>
                        <div class="terms-content"><?php echo esc_html( OsSettingsHelper::get_settings_value( 'invoice_terms', '' ) ); ?></div>
                    </div>
				<?php } ?>
            </div>
        </div>
		<?php
	}

	public static function list_of_statuses_for_select(): array {
		$statuses = [
			LATEPOINT_INVOICE_STATUS_OPEN       => __( 'Open', 'latepoint' ),
			LATEPOINT_INVOICE_STATUS_PAID           => __( 'Paid', 'latepoint' ),
			LATEPOINT_INVOICE_STATUS_PARTIALLY_PAID => __( 'Partially Paid', 'latepoint' ),
			LATEPOINT_INVOICE_STATUS_DRAFT          => __( 'Draft', 'latepoint' ),
			LATEPOINT_INVOICE_STATUS_VOID         => __( 'Void', 'latepoint' ),
			LATEPOINT_INVOICE_STATUS_UNCOLLECTIBLE  => __( 'Uncollectible', 'latepoint' ),
		];

		/**
		 * Get the list of invoice statuses
		 *
		 * @param {array} $statuses Array of invoice statuses
		 *
		 * @returns {array} Filtered array of invoice statuses
		 * @since 5.1.0
		 * @hook latepoint_invoices_statuses_for_select
		 *
		 */
		return apply_filters( 'latepoint_invoices_statuses_for_select', $statuses );
	}

	public static function list_of_payment_portions_for_select(): array {
		$payment_portions = [
                LATEPOINT_PAYMENT_PORTION_FULL => __('Full', 'latepoint'),
                LATEPOINT_PAYMENT_PORTION_REMAINING => __('Remaining', 'latepoint'),
                LATEPOINT_PAYMENT_PORTION_DEPOSIT => __('Deposit', 'latepoint'),
                LATEPOINT_PAYMENT_PORTION_CUSTOM => __('Custom', 'latepoint'),
		];

		/**
		 * Get the list of invoice payment_portions
		 *
		 * @param {array} $payment_portions Array of invoice payment_portions
		 *
		 * @returns {array} Filtered array of invoice payment_portions
		 * @since 5.1.0
		 * @hook latepoint_invoices_payment_portions_for_select
		 *
		 */
		return apply_filters( 'latepoint_invoices_payment_portions_for_select', $payment_portions );
	}

	/**
	 * @param OsOrderModel $order
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function list_invoices_for_order( OsOrderModel $order ) {
		if ( $order->is_new_record() ) {
			return;
		}
		$invoices = new OsInvoiceModel();
		$invoices = $invoices->where( [ 'order_id' => $order->id ] )->get_results_as_models();
		if ( OsRolesHelper::can_user( 'invoice__view' ) ) { ?>
            <div class="invoices-info-w">
                <div class="os-form-sub-header">
                    <h3><?php esc_html_e( 'Invoices', 'latepoint' ); ?></h3>
                </div>
                <div class="list-of-invoices">
				<?php if ( $invoices ) {
					foreach ( $invoices as $invoice ) {
                        echo OsInvoicesHelper::generate_invoice_tile_on_order_edit_form($invoice);
					}
				}
				?>
                </div>

				<?php if ( OsRolesHelper::can_user( 'invoice__create' ) ) { ?>
                    <div class="quick-add-item-button"
                         data-os-params="<?php echo OsUtilHelper::build_os_params(['order_id' => $order->id]); ?>"
                         data-os-after-call="latepointInvoicesAdminFeature.init_quick_invoice_settings_form"
                         data-os-output-target=".quick-invoice-settings-form-wrapper"
                         data-os-before-after="before"
                         data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'invoices', 'new_form' ) ); ?>">
                        <i class="latepoint-icon latepoint-icon-plus2"></i>
                        <span><?php esc_html_e( 'New Invoice', 'latepoint' ); ?></span>
                    </div>
                    <div class="quick-invoice-settings-form-wrapper"></div>
				<?php } ?>
            </div>
			<?php
		}
	}

	/**
	 * @param OsOrderModel $order
	 *
	 * @return array
	 */
	public static function generate_invoice_data_from_order( OsOrderModel $order ): array {
		$data = [
			'company'         => OsReplacerHelper::replace_business_vars(OsSettingsHelper::get_settings_value( 'invoices_company_name', '' )),
			'from'            => OsReplacerHelper::replace_all_vars(OsInvoicesHelper::get_invoice_data_bill_from(), ['order' => $order, 'customer' => $order->get_customer()]),
            'to' => OsReplacerHelper::replace_all_vars(OsInvoicesHelper::get_invoice_data_bill_to(), ['order' => $order, 'customer' => $order->get_customer()]),
            'price_breakdown' => json_decode( $order->price_breakdown ),
            'tax_id' => OsSettingsHelper::get_settings_value('invoices_tax_id', ''),
            'totals'          => [
                'subtotal' => $order->get_subtotal(),
                'total'    => $order->get_total(),
            ]
		];


        /**
         * Invoice data generated from an order
         *
         * @since 5.1.0
         * @hook latepoint_invoices_data_from_order
         *
         * @param {array} $data array of values for invoice
         * @param {OsOrderModel} $order order model
         * @returns {array} The filtered array of invoice data
         */
        $data = apply_filters('latepoint_invoices_data_from_order', $data, $order);

		return $data;
	}

	/**
	 * @param OsOrderModel $order
	 * @param OsPaymentRequestModel|null $payment_request
	 *
	 * @return void
	 */
	public static function create_invoices_for_new_order( OsOrderModel $order, ?OsPaymentRequestModel $payment_request = null) {
        $existing_invoices = new OsInvoiceModel();
        $existing_invoices = $existing_invoices->where(['order_id' => $order->id])->get_results_as_models();
        $total_invoiced_amount = 0;
        if($existing_invoices){
            foreach($existing_invoices as $invoice) {
                $total_invoiced_amount+= $invoice->charge_amount;
            }
        }
        $order_total = $order->get_total();
        $total_invoiced_amount = OsMoneyHelper::pad_to_db_format( $total_invoiced_amount );

        if($total_invoiced_amount > 0 && $total_invoiced_amount == $order_total){
            return;
        }
        $invoice           = new OsInvoiceModel();
        $invoice->order_id = $order->id;
        $invoice->data     = json_encode( self::generate_invoice_data_from_order( $order ) );

        if ( $order->get_initial_payment_data_value( 'time' ) == LATEPOINT_PAYMENT_TIME_NOW ) {
            // need to pay now, portion will depend on what customer/booker selected
            $invoice->payment_portion = $order->get_initial_payment_data_value( 'portion' );
            $invoice->charge_amount   = $order->get_initial_payment_data_value( 'charge_amount' );
            if ( $order->get_initial_payment_data_value( 'portion' ) != LATEPOINT_PAYMENT_PORTION_FULL ) {
                // since we are not paying full balance, create invoice for the remaining amount
                $invoice_for_remaining_balance                  = clone $invoice;
                $invoice_for_remaining_balance->charge_amount   = $order->get_total() - $invoice->charge_amount;
                $invoice_for_remaining_balance->payment_portion = LATEPOINT_PAYMENT_PORTION_REMAINING;
            }
            if ( ! empty( $payment_request ) ) {
                $invoice->due_at = $payment_request->due_at;
            }
        } else {
            // will pay later, need to generate full price invoice
            $invoice->charge_amount   = $order->get_total();
            $invoice->payment_portion = LATEPOINT_PAYMENT_PORTION_FULL;
        }


        if ( $order->get_total_amount_paid_from_transactions() == $invoice->charge_amount ) {
            // since the order has just been created - any transactions that were made - are part of the time of creation, so should be on the invoice
            $invoice->status = LATEPOINT_INVOICE_STATUS_PAID;
        }


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
            if ( ! empty( $payment_request ) ) {
                // if we have payment request, update it with created invoice
                $payment_request->invoice_id = $invoice->id;
                $payment_request->order_id = $order->id;
                if($payment_request->save()){
                    /**
                     * Invoice was created
                     *
                     * @param {OsInvoiceModel} $payment_request instance of payment request model that was created
                     *
                     * @since 5.1.0
                     * @hook latepoint_payment_request_created
                     *
                     */
                    do_action( 'latepoint_payment_request_created', $payment_request );
                }
            }
            if ( isset( $invoice_for_remaining_balance ) && $invoice_for_remaining_balance->charge_amount > 0 ) {
                $order_items = $order->get_items();
                foreach ( $order_items as $item ) {
                    if ( $item->is_booking() ) {
                        $booking                               = $item->build_original_object_from_item_data();
                        $invoice_for_remaining_balance->due_at = $booking->start_datetime_utc;
                        break;
                    }
                }
                $invoice_for_remaining_balance->status = LATEPOINT_INVOICE_STATUS_DRAFT;
                $invoice_for_remaining_balance->save();
                /**
                 * Invoice was created
                 *
                 * @param {OsInvoiceModel} $invoice instance of invoice model that was created
                 *
                 * @since 5.1.0
                 * @hook latepoint_invoice_created
                 *
                 */
                do_action( 'latepoint_invoice_created', $invoice_for_remaining_balance );
            }
        }
	}

	/**
	 *
	 * Tries to get a matching invoice for a transaction, this is only useful when a new order is created and a transaction needs to find an invoice to attach to
	 *
	 * @param OsTransactionModel $transaction
	 *
	 * @return OsInvoiceModel
	 */
	public static function get_matching_invoice_for_transaction( OsTransactionModel $transaction ): OsInvoiceModel {
		$invoice = new OsInvoiceModel();
		$invoice->where( [ 'order_id' => $transaction->order_id ] );
		$invoice->where( [ 'payment_portion' => $transaction->payment_portion ] );
		$invoice->where( [ 'charge_amount' => $transaction->amount ] );
		$invoice->where( [ 'status' => LATEPOINT_INVOICE_STATUS_OPEN ] );
		$invoice = $invoice->set_limit( 1 )->get_results_as_models();
		if ( empty( $invoice ) ) {
			$invoice = new OsInvoiceModel();
		}

		/**
		 * Try to get a matching invoice for a transaction
		 *
		 * @param {OsTransactionModel} $transaction transaction to match invoice to
		 *
		 * @returns {OsInvoiceModel} Filtered invoice model
		 * @since 5.1.0
		 * @hook latepoint_invoice_get_matching_invoice_for_transaction
		 *
		 */
		return apply_filters( 'latepoint_invoice_get_matching_invoice_for_transaction', $invoice, $transaction );
	}

	public static function create_invoice_from_transaction( OsTransactionModel $transaction_to_create_invoice ): bool {
		$order = new OsOrderModel( $transaction_to_create_invoice->order_id );
		if ( $order->is_new_record() ) {
			return false;
		}
		$invoice                  = new OsInvoiceModel();
		$invoice->charge_amount   = $transaction_to_create_invoice->amount;
		$invoice->data            = self::generate_invoice_data_from_order( $order );
		$invoice->order_id        = $order->id;
		$invoice->status          = LATEPOINT_INVOICE_STATUS_PAID;
		$invoice->payment_portion = $transaction_to_create_invoice->payment_portion;
		$invoice->due_at          = $transaction_to_create_invoice->created_at;
		if ( $invoice->save() ) {
			return $transaction_to_create_invoice->update_attributes( [ 'invoice_id' => $invoice->id ] );
		}

		return false;
	}

	public static function generate_invoice_tile_on_order_edit_form( $invoice ) : string {
        $html = '';
        $html.= '<div class="os-invoice-wrapper" data-reload-tile-route="'.esc_attr(OsRouterHelper::build_route_name('invoices', 'reload_invoice_tile')).'" data-route="' . esc_attr( OsRouterHelper::build_route_name( 'invoices', 'view' ) ) . '" data-invoice-id="' . esc_attr( $invoice->id ) . '">';
        $html.= '<div class="quick-invoice-head">
                <div class="quick-invoice-icon"><i class="latepoint-icon latepoint-icon-file-text"></i></div>
                <div class="quick-invoice-amount">' . OsMoneyHelper::format_price( $invoice->charge_amount, true, false ) . '</div>
                <div class="lp-invoice-status lp-invoice-status-' . $invoice->status . '">' . self::readable_status( $invoice->status ) . '</div>
              </div>
              <div class="quick-invoice-sub">
                <div class="lp-invoice-number"><span>' . esc_html__( 'Invoice Number:', 'latepoint' ) . '</span> <strong>' . esc_html( $invoice->get_invoice_number() ) . '</strong></div>
                <div class="lp-invoice-date">' . sprintf( esc_html__( 'Due: %s', 'latepoint' ), OsTimeHelper::get_readable_date( new OsWpDateTime( $invoice->due_at, new DateTimeZone('UTC') ) ) ) . '</div>
              </div>';
        $html.= '</div>';
        return $html;
	}


	public static function update_activity_after_invoice_job_run( OsActivityModel $activity, OsProcessJobModel $process_job, string $event_type ): OsActivityModel {
		switch ( $event_type ) {
			case 'invoice_created':
			case 'invoice_updated':
				$invoice               = new OsInvoiceModel( $process_job->object_id );
				$activity->customer_id = $invoice->get_customer()->id;
				$activity->order_id    = $invoice->order_id;
				break;
		}

		return $activity;
	}

	public static function add_activity_view_vars_for_invoice( array $vars, OsActivityModel $activity ): array {
        $data     = json_decode( $activity->description, true );
		switch ( $activity->code ) {
			case 'invoice_created':
				$link_to_invoice = '<a href="#" ' . OsOrdersHelper::quick_order_btn_html( $activity->order_id ) . '>' . __( 'View Order', 'latepoint' ) . '</a>';
                $vars['name'] = __('Invoice Created', 'latepoint');
				$vars['meta_html']     = '<div class="activity-preview-to"><span class="os-value">' . $link_to_invoice . '</span><span class="os-label">' . __( 'Created On:', 'latepoint' ) . '</span><span class="os-value">' . $activity->nice_created_at . '</div>';
				$vars['content_html']  = '<pre class="format-json">' . wp_json_encode( $data['invoice_data_vars'], JSON_PRETTY_PRINT ) . '</pre>';
				break;
			case 'invoice_updated':
				$link_to_invoice = '<a href="#" ' . OsOrdersHelper::quick_order_btn_html( $activity->order_id ) . '>' . __( 'View Order', 'latepoint' ) . '</a>';
                $vars['name'] = __('Invoice Updated', 'latepoint');
				$vars['meta_html']     = '<div class="activity-preview-to"><span class="os-value">' . $link_to_invoice . '</span><span class="os-label">' . __( 'Updated On:', 'latepoint' ) . '</span><span class="os-value">' . $activity->nice_created_at . '</div>';
				$changes       = OsUtilHelper::compare_model_data_vars( $data['invoice_data_vars']['new'], $data['invoice_data_vars']['old'] );
				$vars['content_html']  = '<pre class="format-json">' . wp_json_encode( $changes, JSON_PRETTY_PRINT ) . '</pre>';
				break;
		}

		return $vars;
	}

	public static function add_invoice_activity_code( array $codes ): array {
		$codes['invoice_created'] = __( 'Invoice Created', 'latepoint' );
		$codes['invoice_updated'] = __( 'Invoice Updated', 'latepoint' );

		return $codes;
	}

	public static function add_invoice_templates_for_event_actions( array $templates, string $action_type, $wp_filesystem ) {

		switch ( $action_type ) {
			case 'send_email':
				$templates[] = [
					'id'           => 'invoice__created__to_customer',
					'to_user_type' => 'customer',
					'name'         => "New Invoice",
					'to_email'     => '{{customer_full_name}} <{{customer_email}}>',
					'subject'      => "New Invoice {{invoice_number}}",
					'content'      => OsEmailHelper::get_email_layout( $wp_filesystem->get_contents( LATEPOINT_ADDON_PRO_VIEWS_ABSPATH . 'mailers/customer/invoice_created.html' ) )
				];
				$templates[] = [
					'id'           => 'invoice__paid__to_customer',
					'to_user_type' => 'customer',
					'name'         => "Invoice Paid",
					'to_email'     => '{{customer_full_name}} <{{customer_email}}>',
					'subject'      => "Invoice #{{invoice_number}} Paid",
					'content'      => OsEmailHelper::get_email_layout( $wp_filesystem->get_contents( LATEPOINT_ADDON_PRO_VIEWS_ABSPATH . 'mailers/customer/invoice_paid.html' ) )
				];
		}

		return $templates;
	}

	public static function get_invoice_object_for_process( array $object_data, string $source, string $value, bool $include_model ): array {
		switch ( $source ) {
			case 'invoice_id':
				$object_data = [ 'model' => 'invoice', 'id' => $value ];
				if ( $include_model ) {
					$model                      = new OsInvoiceModel( $value );
					$object_data['model_ready'] = $model;
				}
				break;
		}

		return $object_data;
	}

	public static function prepare_replacement_vars_for_invoice( array $vars, array $data_objects, array $other_vars ): array {

		foreach ( $data_objects as $data_object ) {
			switch ( $data_object['model'] ) {
				case 'old_invoice':
					$old_invoice = $data_object['model_ready'] ?? new OsInvoiceModel( $data_object['id'] );
					$temp_vars   = self::generate_replacement_vars_from_invoice( $old_invoice );
					foreach ( $temp_vars as $key => $data ) {
						$vars[ 'old_' . $key ] = $data;
					}
					break;
				case 'invoice':
					$invoice = $data_object['model_ready'] ?? new OsInvoiceModel( $data_object['id'] );
					$vars    = array_merge( $vars, self::generate_replacement_vars_from_invoice( $invoice ) );
					break;
			}
		}

		return $vars;
	}

	/**
	 *
	 * Prepares an array of variables to be used in replacer method from a order object
	 *
	 * @param OsInvoiceModel $invoice
	 * @param array $other_vars
	 *
	 * @return array
	 */
	public static function generate_replacement_vars_from_invoice( OsInvoiceModel $invoice, array $other_vars = [] ): array {
		$vars             = [];
		$vars['invoice']  = $invoice;
		$vars['customer'] = $invoice->get_customer();
		$vars['order']    = $invoice->get_order();
		if ( ! empty( $other_vars ) ) {
			$vars = array_merge( $vars, $other_vars );
		}

		/**
		 * Returns an array of replacement variables, based on supplied <code>OsInvoiceModel</code> instance
		 *
		 * @param {array} $vars Current array of replacement variables
		 * @param {OsInvoiceModel} $invoice Instance of <code>OsInvoiceModel</code> to generate replacement variables for
		 * @param {array} $other_vars Array of additional (pre-prepared) replacement variables
		 *
		 * @returns {array} Filtered array of replacement variables
		 * @since 1.1.0
		 * @hook latepoint_prepare_replacement_vars_from_invoice
		 *
		 */
		return apply_filters( 'latepoint_prepare_replacement_vars_from_invoice', $vars, $invoice, $other_vars );
	}

	public static function add_data_source_for_invoice_events( array $data_sources, \LatePoint\Misc\ProcessEvent $event ): array {
		switch ( $event->type ) {
			case 'invoice_created':
				$invoices_for_select = \OsInvoicesHelper::get_invoices_for_select();
				$data_sources[]      = [
					'name'   => 'invoice_id',
					'values' => $invoices_for_select,
					'label'  => __( 'Choose an invoice for this test run:', 'latepoint' ),
					'model'  => 'invoice'
				];
				break;
			case 'invoice_updated':
				$invoices_for_select = \OsInvoicesHelper::get_invoices_for_select();
				$data_sources[]      = [
					'name'   => 'new_invoice_id',
					'values' => $invoices_for_select,
					'label'  => __( 'Choose old invoice to be used for this test run:', 'latepoint' ),
					'model'  => 'invoice'
				];
				$data_sources[]      = [
					'name'   => 'old_invoice_id',
					'values' => $invoices_for_select,
					'label'  => __( 'Choose new invoice to be used for this test run:', 'latepoint' ),
					'model'  => 'invoice'
				];
				break;
		}

		return $data_sources;
	}

	public static function prepare_data_for_run( \LatePoint\Misc\ProcessAction $action ): \LatePoint\Misc\ProcessAction {

		foreach ( $action->selected_data_objects as $data_object ) {
			switch ( $data_object['model'] ) {
				case 'invoice':
					$action->prepared_data_for_run['activity_data']['invoice_id'] = $data_object['id'];
					if ( ! empty( $data_object['model_ready'] ) ) {
						$action->prepared_data_for_run['activity_data']['order_id'] = $data_object['model_ready']->order_id;
					}
					break;
			}
		}

		return $action;
	}

	public static function set_object_model_type_for_invoice_processes( ?string $object_model_type, OsProcessModel $process, array $objects ): ?string {
		if ( in_array( $process->event_type, [ 'invoice_created', 'invoice_updated' ] ) ) {
			$object_model_type = 'invoice';
		}

		return $object_model_type;
	}


	public static function set_event_time_utc_for_invoice_processes( ?OsWpDateTime $event_time_utc, OsProcessModel $process, array $objects ): ?OsWpDateTime {

		if ( in_array( $process->event_type, [ 'invoice_created', 'invoice_updated' ] ) ) {
			try {
				switch ( $process->event_type ) {
					case 'invoice_created':
						$event_time_utc = new OsWpDateTime( $objects[0]['model_ready']->created_at, new DateTimeZone( 'UTC' ) );
						break;
					case 'invoice_updated':
						$event_time_utc = new OsWpDateTime( $objects[0]['model_ready']->updated_at, new DateTimeZone( 'UTC' ) );
						break;
				}
			} catch ( Exception $e ) {
				OsDebugHelper::log( 'Error creating jobs for workflow', 'process_jobs_error', print_r( $process->id, true ) . ' ' . print_r( $objects, true ) . ' ' . $e->getMessage() );

				return $event_time_utc;
			}
		}

		return $event_time_utc;
	}

	public static function add_invoice_to_process_event_models( OsModel $model, string $property_object_name ): OsModel {
		switch ( $property_object_name ) {
			case 'old_invoice':
			case 'invoice':
				$model = new OsInvoiceModel();
				break;
		}

		return $model;
	}

	public static function add_values_for_process_event_condition( array $values, string $property, string $property_object, string $property_attribute ): array {
		switch ( $property_object ) {
			case 'invoice':
			case 'old_invoice':
				switch ( $property_attribute ) {
					case 'status':
						$statuses = OsInvoicesHelper::list_of_statuses_for_select();
						foreach ( $statuses as $status_key => $status ) {
							$values[] = [ 'value' => $status_key, 'label' => $status ];
						}
						break;
					case 'payment_portion':
						$statuses = OsInvoicesHelper::list_of_payment_portions_for_select();
						foreach ( $statuses as $status_key => $status ) {
							$values[] = [ 'value' => $status_key, 'label' => $status ];
						}
						break;
				}
				break;

		}

		return $values;
	}

	public static function add_operators_to_conditions_for_invoice_events( array $operators, string $property, string $object_code, string $object_property ): array {
		switch ( $object_code ) {
			case 'old_invoice':
				// TODO time range operators instead of removing these opearators completely
				if ( $object_property != 'due_at' ) {
					$operators['equal']     = __( 'was equal to', 'latepoint' );
					$operators['not_equal'] = __( 'was not equal to', 'latepoint' );
				}
				$operators['changed']     = __( 'has changed', 'latepoint' );
				$operators['not_changed'] = __( 'has not changed', 'latepoint' );
				break;
			case 'invoice':
				// TODO time range operators instead of removing these opearators completely
				if ( $object_property != 'due_at' ) {
					$operators['equal']     = __( 'is equal to', 'latepoint' );
					$operators['not_equal'] = __( 'is not equal to', 'latepoint' );
				}
				break;
		}

		return $operators;
	}

	public static function add_conditions_for_invoice_events( array $objects, string $event_type ): array {
		switch ( $event_type ) {
			case 'invoice_created':
				$objects[] = [ 'code' => 'invoice', 'model' => 'OsInvoiceModel', 'label' => __( 'Invoice', 'latepoint' ), 'properties' => [] ];
				break;
			case 'invoice_updated':
				$objects[] = [ 'code' => 'old_invoice', 'model' => 'OsInvoiceModel', 'label' => __( 'Old Invoice', 'latepoint' ), 'properties' => [] ];
				$objects[] = [ 'code' => 'invoice', 'model' => 'OsInvoiceModel', 'label' => __( 'New Invoice', 'latepoint' ), 'properties' => [] ];
				break;
		}

		return $objects;
	}

	public static function add_process_events_for_invoices( array $process_events ): array {
		$process_events[] = 'invoice_created';
		$process_events[] = 'invoice_updated';

		return $process_events;
	}

	public static function add_names_for_process_events_for_invoices( array $process_event_names ): array {
		$process_event_names['invoice_created'] = __( 'Invoice Created', 'latepoint' );
		$process_event_names['invoice_updated'] = __( 'Invoice Updated', 'latepoint' );

		return $process_event_names;
	}


	public static function get_subject_for_invoice_email(): string {
		$default_subject = 'Your Invoice for Order #{{order_confirmation_code}}';

		return OsSettingsHelper::get_settings_value( 'invoices_email_subject', $default_subject );
	}

	public static function get_invoice_data_bill_from(): string {
		$default = '{{business_address}}<br>{{business_phone}}';

		return OsSettingsHelper::get_settings_value( 'invoices_data_from', $default );
	}

	public static function get_invoice_data_bill_to(): string {
		$default = '{{customer_full_name}}<br>{{customer_email}}<br>{{customer_phone}}';

		return OsSettingsHelper::get_settings_value( 'invoices_data_to', $default );
	}

	public static function get_content_for_invoice_email(): string {
		$default_content = 'Hi {{customer_full_name}},<br><br>Thank you for choosing {{business_name}}.<br>You can view your invoice using <a href="{{invoice_access_url}}">this link</a><br><br>If you have any questions, feel free to contact us.';

		return OsSettingsHelper::get_settings_value( 'invoices_email_content', $default_content );
	}

	public static function output_invoice_vars() {
		?>
        <div class="available-vars-block">
            <h4><?php _e( 'Invoices', 'latepoint' ); ?></h4>
            <ul>
                <li><span class="var-label"><?php esc_html_e( 'Invoice Status', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{invoice_status}}</span></li>
                <li><span class="var-label"><?php esc_html_e( 'Invoice Due Date', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{invoice_due_date}}</span></li>
                <li><span class="var-label"><?php esc_html_e( 'Invoice Amount', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{invoice_amount}}</span></li>
                <li><span class="var-label"><?php esc_html_e( 'Invoice Number', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{invoice_number}}</span></li>
                <li><span class="var-label"><?php esc_html_e( 'Invoice Access URL', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{invoice_access_url}}</span></li>
                <li><span class="var-label"><?php esc_html_e( 'Invoice Pay URL', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{invoice_pay_url}}</span></li>
                <li><span class="var-label"><?php esc_html_e( 'Invoice Receipt URL', 'latepoint' ); ?></span> <span class="var-code os-click-to-copy">{{invoice_receipt_url}}</span></li>

            </ul>
        </div>
		<?php
	}

	public static function output_invoice_settings() {
		?>
        <div class="white-box section-anchor" id="stickySectionOther">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Invoice Settings', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">
				<?php
				echo '<div class="sub-section-row">
                          <div class="sub-section-label">
                            <h3>' . __( 'Invoice Data', 'latepoint' ) . '</h3>
                          </div>
                          <div class="sub-section-content">';

                echo '<div class="os-row">
                            <div class="os-col-lg-12">'.OsFormHelper::media_uploader_field( 'settings[invoices_company_logo]', 0, __( 'Company Logo', 'latepoint' ), __( 'Remove Image', 'latepoint' ), OsSettingsHelper::get_settings_value( 'invoices_company_logo' ) ).'</div>
                        </div>';
				echo '<div class="os-row os-mb-2">';
				echo '<div class="os-col-4">';
				echo OsFormHelper::text_field( 'settings[invoices_company_name]', __( 'Company Name', 'latepoint' ), OsSettingsHelper::get_settings_value( 'invoices_company_name', '' ), [ 'theme' => 'simple' ] );
				echo '</div>';
				echo '<div class="os-col-4">';
				echo OsFormHelper::text_field( 'settings[invoices_tax_id]', __( 'VAT Number/Tax ID', 'latepoint' ), OsSettingsHelper::get_settings_value( 'invoices_tax_id', '' ), [ 'theme' => 'simple' ] );
				echo '</div>';
				echo '<div class="os-col-4">';
				echo OsFormHelper::text_field( 'settings[invoices_number_prefix]', __( 'Number Prefix', 'latepoint' ), OsSettingsHelper::get_settings_value( 'invoices_number_prefix', 'INV-' ), [ 'theme' => 'simple' ] );
				echo '</div>';
				echo '</div>';
				echo '<div class="os-mb-2">';
				echo OsFormHelper::textarea_field( 'settings[invoices_data_from]', __( 'Bill From', 'latepoint' ), self::get_invoice_data_bill_from(), [ 'theme' => 'simple' ] );
				echo '</div>';
				echo '<div>';
				echo OsFormHelper::textarea_field( 'settings[invoices_data_to]', __( 'Bill To', 'latepoint' ), self::get_invoice_data_bill_to(), [ 'theme' => 'simple' ] );
				echo '</div>';
				echo '</div>
					</div>
					<div class="sub-section-row">
                          <div class="sub-section-label">
                            <h3>' . __( 'Email Invoice', 'latepoint' ) . '</h3>
                          </div>
                          <div class="sub-section-content">
                            <div class="latepoint-message latepoint-message-subtle">' . __( 'This subject and content will be used when invoice is being emailed. ', 'latepoint' ) . OsUtilHelper::template_variables_link_html() . '</div>';
				echo OsFormHelper::text_field( 'settings[invoices_email_subject]', __( 'Subject', 'latepoint' ), self::get_subject_for_invoice_email(), [ 'theme' => 'simple' ] );
				OsFormHelper::wp_editor_field( 'settings[invoices_email_content]', 'settingsInvoiceEmailContent', __( 'Email Content', 'latepoint' ), self::get_content_for_invoice_email() );
				echo '</div>
					</div>';
				?>
            </div>
        </div>
		<?php
	}

	/**
	 * Get Invoice by transaction intent key
	 * @param string $intent_key
	 * @return OsInvoiceModel | false
	 */
	public static function get_invoice_by_transaction_intent_key( string $intent_key ) {
		$transaction_intent = OsTransactionIntentHelper::get_transaction_intent_by_intent_key($intent_key);
		if (!$transaction_intent->is_new_record()) {
			return new OsInvoiceModel($transaction_intent->invoice_id);
		}
		return false;
	}
}