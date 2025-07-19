<?php

/**
 * @property OsCustomerModel $customer
 * @property OsAgentModel $agent
 * @property OsServiceModel $service
 * @property OsLocationModel $location
 */
class OsBookingModel extends OsModel {
	public $id,
		$booking_code,
		$service_id,
		$customer_id,
		$agent_id,
		$location_id,
		$recurrence_id,
		$buffer_before = 0,
		$buffer_after = 0,
		$status,
		$start_date,
		$end_date,
		$start_time,
		$end_time,
		$start_datetime_utc,
		$end_datetime_utc,
		$duration,
		$total_attendees = 1,
		$total_attendees_sum = 1,
		$total_customers = 1,
		$cart_item_id = null,
		$order_item_id,
		$server_timezone,
		$customer_timezone,
		$meta_class = 'OsBookingMetaModel',
		$keys_to_manage = [],
		$generate_recurrent_sequence = [],
		$updated_at,
		$created_at;

	function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_BOOKINGS;
		$this->nice_names = array(
			'service_id' => __( 'Service', 'latepoint' ),
			'agent_id'   => __( 'Agent', 'latepoint' )
		);

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}


	/**
	 * @return mixed|void
	 *
	 * Returns full amount to charge in database format 1999.0000
	 *
	 */
	public function full_amount_to_charge() {
		return OsBookingHelper::calculate_full_amount_for_booking( $this );
	}

	/**
	 * @return mixed|void
	 *
	 * Returns deposit amount to charge in database format 1999.0000
	 *
	 */
	public function deposit_amount_to_charge() {
		return OsBookingHelper::calculate_deposit_amount_to_charge( $this );
	}


	public function get_key_to_manage_for(string $for): string {
		if($this->is_new_record()) return '';
		if(!empty($this->keys_to_manage[$for])) return $this->keys_to_manage[$for];
		$key = OsMetaHelper::get_booking_meta_by_key( 'key_to_manage_for_' . $for, $this->id );
		if ( empty( $key ) ) {
			$key = OsUtilHelper::generate_key_to_manage();
			OsMetaHelper::save_booking_meta_by_key( 'key_to_manage_for_' . $for, $key, $this->id );
		}
		$this->keys_to_manage[$for] = $key;
		return $key;
	}

	public function manage_by_key_url(string $for = 'customer'): string{
		return OsBookingHelper::generate_direct_manage_booking_url($this, $for);
	}

	public function get_service_name_for_summary() {
		$service_name = $this->service_id ? $this->service->name : '';

		/**
		 * Get service name to be displayed on a booking summary
		 *
		 * @param {string} $service_name Service name to be filtered
		 * @param {OsBookingModel} $booking Booking model which service name is requested
		 *
		 * @returns {string} Filtered service name
		 * @since 5.0.0
		 * @hook latepoint_booking_get_service_name_for_summary
		 *
		 */
		return apply_filters( 'latepoint_booking_get_service_name_for_summary', $service_name, $this );
	}

	public function get_order() {
		if ( $this->order_item_id ) {
			if ( ! isset( $this->order_item ) || ( $this->order_item->id != $this->order_item_id ) ) {
				$this->order_item = new OsOrderItemModel( $this->order_item_id );
				if ( ! isset( $this->order ) || ( $this->order->id != $this->order_item->order_id ) ) {
					$this->order = new OsOrderModel( $this->order_item->order_id );
				}
			}
		} else {
			$this->order = new OsOrderModel();
		}

		return $this->order;
	}

	public function get_order_item(  ) {
		if (!isset( $this->order_item )) {
			$this->order_item = new OsOrderItemModel( $this->order_item_id );
		}
		return $this->order_item;
	}

	public function filter_allowed_records(): OsModel {
		if ( ! OsRolesHelper::are_all_records_allowed() ) {
			if ( ! OsRolesHelper::are_all_records_allowed( 'agent' ) ) {
				$this->filter_where_conditions( [ 'agent_id' => OsRolesHelper::get_allowed_records( 'agent' ) ] );
			}
			if ( ! OsRolesHelper::are_all_records_allowed( 'location' ) ) {
				$this->filter_where_conditions( [ 'location_id' => OsRolesHelper::get_allowed_records( 'location' ) ] );
			}
			if ( ! OsRolesHelper::are_all_records_allowed( 'service' ) ) {
				$this->filter_where_conditions( [ 'service_id' => OsRolesHelper::get_allowed_records( 'service' ) ] );
			}
		}

		return $this;
	}

	public function properties_to_query(): array {
		return [
			'service_id'         => __( 'Service', 'latepoint' ),
			'agent_id'           => __( 'Agent', 'latepoint' ),
			'status'             => __( 'Status', 'latepoint' ),
			'start_datetime_utc' => __( 'Start Time', 'latepoint' ),
		];
	}

	public function generate_item_data() {
		return wp_json_encode( $this->generate_params_for_booking_form() );
	}


	public function generate_params_for_booking_form() {
		$params = [
			"id"            => $this->id,
			"customer_id"   => $this->customer_id,
			"agent_id"      => $this->agent_id,
			"location_id"   => $this->location_id,
			"service_id"    => $this->service_id,
			"recurrence_id"    => $this->recurrence_id,
			"start_date"    => $this->start_date,
			"start_time"    => $this->start_time,
			"end_date"      => $this->end_date,
			"end_time"      => $this->end_time,
			"status"        => $this->status,
			"buffer_before" => $this->buffer_before,
			"buffer_after"  => $this->buffer_after,
			"duration"      => $this->duration,
			"generate_recurrent_sequence" => $this->generate_recurrent_sequence,
		];

		/**
		 * Returns an array of params generated from OsBookingModel to be used in a booking form
		 *
		 * @param {array} $params Array of booking params
		 * @param {OsBookingModel} $booking Instance of <code>OsBookingModel</code> that params are being generated for
		 *
		 * @returns {array} Filtered array of booking params
		 * @since 5.0.0
		 * @hook latepoint_generated_params_for_booking_form
		 *
		 */
		return apply_filters( 'latepoint_generated_params_for_booking_form', $params, $this );
	}

	public function get_formatted_price(){
      $order_item     = new OsOrderItemModel( $this->order_item_id );
	  return OsMoneyHelper::format_price($order_item->get_total());
	}

	public function generate_first_level_data_vars() : array{
		$vars         = [
			'id'               => $this->id,
			'booking_code'     => $this->booking_code,
			'start_datetime'   => $this->format_start_date_and_time_rfc3339(),
			'end_datetime'     => $this->format_end_date_and_time_rfc3339(),
			'service_name'     => $this->service->name,
			'duration'         => $this->duration,
			'customer_comment' => $this->order->customer_comment,
			'status'           => $this->status,
			'start_date'       => $this->format_start_date(),
			'start_time'       => OsTimeHelper::minutes_to_hours_and_minutes( $this->start_time ),
			'timezone'         => OsTimeHelper::get_wp_timezone_name(),
			'agent'            => $this->agent->get_data_vars(),
			'created_datetime' => $this->format_created_datetime_rfc3339(),
			'manage_booking_for_agent' => OsBookingHelper::generate_direct_manage_booking_url( $this, 'agent' ),
			'manage_booking_for_customer' => OsBookingHelper::generate_direct_manage_booking_url( $this, 'customer' ),
		];
		return $vars;
	}

	public function generate_data_vars(): array {
		$vars = $this->get_first_level_data_vars();

		$vars['customer'] = $this->customer->get_data_vars();
		$vars['transactions'] = [];
		$vars['order'] = $this->order->get_first_level_data_vars();

		$transactions = $this->order->get_transactions();
		if ( $transactions ) {
			foreach ( $transactions as $transaction ) {
				$vars['transactions'][] = $transaction->get_data_vars();
			}
		}

		return $vars;
	}


	public function is_ready_for_summary() {
		return ( $this->agent_id && $this->agent_id != LATEPOINT_ANY_AGENT && OsAgentHelper::count_agents() > 1 ) || $this->service_id;
	}

	public function is_part_of_bundle(): bool {
		if ( $this->order_item_id ) {
			$order_item = new OsOrderItemModel( $this->order_item_id );

			return $order_item->is_bundle();
		}

		return false;
	}

	public function is_upcoming(): bool {
		if ( empty( $this->start_datetime_utc ) ) {
			return false;
		}
		$start_time_utc = new OsWpDateTime( $this->start_datetime_utc, new DateTimeZone( 'UTC' ) );
		$now_time_utc   = new OsWpDateTime( 'now', new DateTimeZone( 'UTC' ) );

		return ( $start_time_utc > $now_time_utc );
	}

	public function set_utc_datetimes(bool $save = false) {
		if ( empty( $this->start_date ) || empty( $this->end_date ) || empty( $this->start_time ) || empty( $this->end_time ) ) {
			return;
		}
		$this->start_datetime_utc = $this->get_start_datetime('UTC')->format(LATEPOINT_DATETIME_DB_FORMAT);
		$this->end_datetime_utc   = $this->get_end_datetime('UTC')->format(LATEPOINT_DATETIME_DB_FORMAT);
		if ( $save ) {
			$this->update_attributes(['start_datetime_utc' => $this->start_datetime_utc, 'end_datetime_utc' => $this->end_datetime_utc]);
		}
	}


	public function delete( $id = false ) {
		if ( ! $id && isset( $this->id ) ) {
			$id = $this->id;
		}

		$booking_metas = new OsBookingMetaModel();
		$booking_metas->delete_where( [ 'object_id' => $id ] );
		$process_jobs = new OsProcessJobModel();
		$process_jobs->delete_where( [ 'object_id' => $id, 'object_model_type' => 'booking' ] );


		return parent::delete( $id );
	}

	public function delete_meta_by_key( $meta_key ) {
		if ( $this->is_new_record() ) {
			return true;
		}

		$meta = new OsBookingMetaModel();

		return $meta->delete_by_key( $meta_key, $this->id );
	}

	public function get_url_for_add_to_calendar_button( string $calendar_type ): string {
		switch ( $calendar_type ) {
			case 'google':
				$url    = 'https://calendar.google.com/calendar/render';
				$params = [
					'action' => 'TEMPLATE',
					'text'   => $this->service->name,
					'dates'  => $this->get_start_datetime_object( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ) . '/' . $this->get_end_datetime_object( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' )
				];
				if ( ! empty( $this->location->full_address ) ) {
					$params['location'] = $this->location->full_address;
				}
				break;
			case 'outlook':
				$url    = 'https://outlook.office.com/calendar/0/deeplink/compose';
				$params = [
					'path'    => '/calendar/action/compose',
					'rru'     => 'addevent',
					'startdt' => $this->get_start_datetime_object( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ),
					'enddt'   => $this->get_end_datetime_object( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ),
					'subject' => $this->service->name,
				];
				break;
		}
		/**
		 * Generate params for the add to calendar link
		 *
		 * @param {array} $params Array of parameters that will be converted into a param query
		 * @param {string} $calendar_type Type of calendar the link is requested for
		 * @param {OsBookingModel} $booking A booking object
		 * @returns {array} The filtered array of appointment attributes
		 *
		 * @since 4.8.1
		 * @hook latepoint_build_add_to_calendar_link_params
		 *
		 */
		$params = apply_filters( 'latepoint_build_add_to_calendar_link_params', $params, $calendar_type, $this );

		$url = $url . '?' . http_build_query( $params );

		/**
		 * URL for the link for a button to add appointment to calendar
		 *
		 * @param {array} $params Array of parameters that will be converted into a param query
		 * @param {string} $calendar_type Type of calendar the link is requested for
		 * @param {OsBookingModel} $booking A booking object
		 * @returns {string} The filtered url of adding appointment to calendar
		 *
		 * @since 4.8.1
		 * @hook latepoint_build_add_to_calendar_link_url
		 *
		 */
		return apply_filters( 'latepoint_build_add_to_calendar_link_url', $url, $calendar_type, $this );
	}

	public function get_ical_download_link( $key = false ) {
		return ( $key ) ? OsRouterHelper::build_admin_post_link( [
			'manage_booking_by_key',
			'ical_download'
		], [ 'key' => $key ] ) : OsRouterHelper::build_admin_post_link( [
			'customer_cabinet',
			'ical_download'
		], [ 'latepoint_booking_id' => $this->id ] );
	}

	public function get_print_link( $key = false ) {
		return ( $key ) ? OsRouterHelper::build_admin_post_link( [ 'manage_booking_by_key', 'print'], [ 'key' => $key ] ) : OsRouterHelper::build_admin_post_link( [ 'customer_cabinet', 'print_booking_info' ], [ 'latepoint_booking_id' => $this->id ] );
	}

	public function get_meta_by_key( $meta_key, $default = false ) {
		if ( $this->is_new_record() ) {
			return $default;
		}

		$meta = new OsBookingMetaModel();

		return $meta->get_by_key( $meta_key, $this->id, $default );
	}

	public function get_coupon_code(  ) {
		$order = $this->get_order();
		return $order->coupon_code;
	}

	public function get_coupon_discount(  ): string {
		$order_item = $this->get_order_item();
		$coupon_discount = $order_item->get_coupon_discount();
		return $coupon_discount > 0 ? OsMoneyHelper::format_price($order_item->get_coupon_discount()) : '';
	}

	public function save_meta_by_key( $meta_key, $meta_value ) {
		if ( $this->is_new_record() ) {
			return false;
		}

		$meta = new OsBookingMetaModel();

		return $meta->save_by_key( $meta_key, $meta_value, $this->id );
	}

	public function calculate_end_date() {
		if ( empty( $this->start_time ) || empty( $this->start_date ) ) {
			return $this->start_date;
		}
		if ( ( $this->start_time + $this->get_total_duration() ) >= ( 24 * 60 ) ) {
			$date_obj = new OsWpDateTime( $this->start_date );
			$end_date = $date_obj->modify( '+1 day' )->format( 'Y-m-d' );
		} else {
			$end_date = $this->start_date;
		}

		return $end_date;
	}


	public function calculate_end_time() {
		$end_time = (int) $this->start_time + (int) $this->get_total_duration();
		// continues to next day?
		if ( $end_time > ( 24 * 60 ) ) {
			$end_time = $end_time - ( 24 * 60 );
		}

		return $end_time;
	}

	public function calculate_end_date_and_time() {
		$this->end_time = $this->calculate_end_time();
		$this->end_date = $this->calculate_end_date();
	}

	public function after_data_was_set( $data ) {
		if ( empty( $this->end_time ) ) {
			$this->calculate_end_date_and_time();
		}
		if ( empty( $this->end_date ) ) {
			$this->calculate_end_date();
		}
	}

	public function set_buffers() {
		if ( $this->service_id ) {
			$service = new OsServiceModel( $this->service_id );
			if ( $service ) {
				$this->buffer_before = $service->buffer_before;
				$this->buffer_after  = $service->buffer_after;
			}
		}
	}

	public function get_total_duration( $calculate_from_start_and_end = false ) {
		if ( $calculate_from_start_and_end ) {
			if ( $this->start_date == $this->end_date ) {
				// same day
				$total_duration = $this->end_time - $this->start_time;
			} else {
				// TODO calculate how many days difference there is, if difference is more than 1 day - account for that
				$total_duration = 60 * 24 - $this->start_time + $this->end_time;
			}
		} else {
			if ( $this->duration ) {
				$total_duration = $this->duration;
			} else {
				$total_duration = ( $this->service_id ) ? $this->service->duration : 60;
			}
			$total_duration = apply_filters( 'latepoint_calculated_total_duration', $total_duration, $this );
		}

		return (int) $total_duration;
	}


	public function get_nice_created_at( $include_time = true ) {
		$format = $include_time ? OsSettingsHelper::get_readable_date_format() . ' ' . OsSettingsHelper::get_readable_time_format() : OsSettingsHelper::get_readable_date_format();
		$utc_date = date_create_from_format( LATEPOINT_DATETIME_DB_FORMAT, $this->created_at );
		$wp_timezone_date = $utc_date->setTimezone(OsTimeHelper::get_wp_timezone());

		return date_format( $wp_timezone_date, $format );
	}

	public function is_bookable( array $settings = [] ): bool {

		$defaults = [
			'skip_customer_check' => false,
			'log_errors' => true
		];

		$settings = OsUtilHelper::merge_default_atts( $defaults, $settings );

		$customer = $this->customer_id ? new OsCustomerModel( $this->customer_id ) : false;
		// check if customer has to be assigned to a booking, or a guest booking is fine at this point
		if($settings['skip_customer_check']){
			$customer_requirement_satisfied = true;
		}else{
			$customer_requirement_satisfied = ($this->customer_id && $customer && $customer->id && ( $this->customer_id == $customer->id ));
		}

		// agent, service and customer should be set
		if ( $this->service_id && $this->agent_id &&  $customer_requirement_satisfied) {

			if ( $this->agent_id == LATEPOINT_ANY_AGENT && $this->location_id == LATEPOINT_ANY_LOCATION ) {
				// both location and agent are set to any
				$connections       = new OsConnectorModel();
				$connection_groups = $connections->select( LATEPOINT_TABLE_AGENTS_SERVICES . '.agent_id, ' . LATEPOINT_TABLE_AGENTS_SERVICES . '.location_id' )
				                                 ->where( [
					                                 'service_id'                          => $this->service_id,
					                                 LATEPOINT_TABLE_AGENTS . '.status'    => LATEPOINT_AGENT_STATUS_ACTIVE,
					                                 LATEPOINT_TABLE_LOCATIONS . '.status' => LATEPOINT_LOCATION_STATUS_ACTIVE
				                                 ] )
				                                 ->join( LATEPOINT_TABLE_AGENTS, [ 'id' => LATEPOINT_TABLE_AGENTS_SERVICES . '.agent_id' ] )
				                                 ->join( LATEPOINT_TABLE_LOCATIONS, [ 'id' => LATEPOINT_TABLE_AGENTS_SERVICES . '.location_id' ] )
				                                 ->get_results( ARRAY_A );
				if ( empty( $connection_groups ) ) {
					// no active locations and agents are connected to this service
					$this->add_error( 'send_to_step', __( 'Unfortunately there are no active resources that can offer selected service, please select another service.', 'latepoint' ), 'booking__service' );

					return false;
				} else {
					foreach ( $connection_groups as $connection ) {
						$this->location_id = $connection['location_id'];
						$this->agent_id    = OsBookingHelper::get_any_agent_for_booking_by_rule( $this );
						// available agent found in this location - break the loop
						if ( $this->agent_id ) {
							break;
						}
					}
					if ( ! $this->agent_id ) {
						$this->add_error( 'send_to_step', __( 'Unfortunately the selected time slot is not available anymore, please select another timeslot.', 'latepoint' ), 'booking__datepicker' );

						return false;
					}
				}


			} elseif ( $this->agent_id == LATEPOINT_ANY_AGENT ) {
				$this->agent_id = OsBookingHelper::get_any_agent_for_booking_by_rule( $this );
				if ( ! $this->agent_id ) {
					$this->add_error( 'send_to_step', __( 'Unfortunately the selected time slot is not available anymore, please select another timeslot.', 'latepoint' ), 'booking__datepicker' );

					return false;
				}
			} elseif ( $this->location_id == LATEPOINT_ANY_LOCATION ) {
				$this->location_id = OsBookingHelper::get_any_location_for_booking_by_rule( $this );
				if ( ! $this->location_id ) {
					$this->add_error( 'send_to_step', __( 'Unfortunately the selected time slot is not available anymore, please select another timeslot.', 'latepoint' ), 'booking__datepicker' );

					return false;
				}
			} else {
				// check if booking time is still available
				if ( ! OsBookingHelper::is_booking_request_available( \LatePoint\Misc\BookingRequest::create_from_booking_model( $this ) ) ) {
					// translators: %1$s is the timeslot date and time
					// translators: %2$s is the service name
					$error_message = sprintf( __( 'Unfortunately the selected time slot "%1$s" for "%2$s" is not available anymore, please select another timeslot.', 'latepoint' ), $this->get_nice_start_datetime_for_customer(), $this->service->name );
					$this->add_error( 'send_to_step', $error_message, 'booking__datepicker' );

					return false;
				}
			}

			if(!$this->validate(false, ['order_item_id', 'status', 'customer_id'])){
				return false;
			}

			return true;
		} else {
			if ( ! $this->service_id ) {
				$this->add_error( 'missing_service', __( 'You have to select a service', 'latepoint' ) );
			}
			if ( ! $this->agent_id ) {
				$this->add_error( 'missing_agent', __( 'You have to select an agent', 'latepoint' ) );
			}
			if ( ! $this->customer_id && !$settings['skip_customer_check'] ) {
				$this->add_error( 'missing_customer', __( 'Customer Not Found', 'latepoint' ) );
				if($settings['log_errors']){
					OsDebugHelper::log( 'Customer not found', 'customer_error', print_r( $customer, true ) );
				}
			}
			if ( ! $customer && !$settings['skip_customer_check'] ) {
				$this->add_error( 'missing_customer', __( 'You have to be logged in', 'latepoint' ) );
				if($settings['log_errors']){
					OsDebugHelper::log( 'Customer not logged in', 'customer_error', print_r( $customer, true ) );
				}
			}
			if($settings['log_errors']){
				OsDebugHelper::log( 'Error saving booking', 'booking_error', 'Agent: ' . $this->agent_id . ', Service: ' . $this->service_id . ', Booking Customer: ' . $this->customer_id );
			}

			return false;
		}
	}


	public function get_nice_status() {
		return OsBookingHelper::get_nice_status_name( $this->status );
	}

	public function get_latest_bookings_sorted_by_status( $args = array() ) {
		$args = array_merge( array(
			'service_id'  => false,
			'customer_id' => false,
			'agent_id'    => false,
			'location_id' => false,
			'limit'       => false,
			'offset'      => false
		), $args );

		$bookings   = new OsBookingModel();
		$query_args = array();
		if ( $args['service_id'] ) {
			$query_args['service_id'] = $args['service_id'];
		}
		if ( $args['customer_id'] ) {
			$query_args['customer_id'] = $args['customer_id'];
		}
		if ( $args['agent_id'] ) {
			$query_args['agent_id'] = $args['agent_id'];
		}
		if ( $args['location_id'] ) {
			$query_args['location_id'] = $args['location_id'];
		}
		if ( $args['limit'] ) {
			$bookings->set_limit( $args['limit'] );
		}
		if ( $args['offset'] ) {
			$bookings->set_offset( $args['offset'] );
		}

		return $bookings->where( $query_args )->should_not_be_cancelled()->order_by( "status != '" . LATEPOINT_BOOKING_STATUS_PENDING . "' asc, start_date asc, start_time asc" )->get_results_as_models();

	}


	public function should_not_be_cancelled() {
		return $this->where( [ $this->table_name . '.status !=' => LATEPOINT_BOOKING_STATUS_CANCELLED ] );
	}

	public function should_be_cancelled() {
		return $this->where( [ $this->table_name . '.status' => LATEPOINT_BOOKING_STATUS_CANCELLED ] );
	}

	public function should_be_approved() {
		return $this->where( [ $this->table_name . '.status' => LATEPOINT_BOOKING_STATUS_APPROVED ] );
	}

	public function should_be_in_future() {
		return $this->where( [
			'OR' => [
				'start_date >' => OsTimeHelper::today_date( 'Y-m-d' ),
				'AND'          => [
					'start_date'   => OsTimeHelper::today_date( 'Y-m-d' ),
					'start_time >' => OsTimeHelper::get_current_minutes()
				]
			]
		] );
	}


	public function get_upcoming_bookings( $agent_id = false, $customer_id = false, $service_id = false, $location_id = false, int $limit = 3 ) {
		$bookings = new OsBookingModel();
		$args     = array(
			'OR' => array(
				'start_date >' => OsTimeHelper::today_date( 'Y-m-d' ),
				'AND'          => array(
					'start_date'   => OsTimeHelper::today_date( 'Y-m-d' ),
					'start_time >' => OsTimeHelper::get_current_minutes()
				)
			)
		);
		if ( $service_id ) {
			$args['service_id'] = $service_id;
		}
		if ( $customer_id ) {
			$args['customer_id'] = $customer_id;
		}
		if ( $agent_id ) {
			$args['agent_id'] = $agent_id;
		}
		if ( $location_id ) {
			$args['location_id'] = $location_id;
		}

		$args = OsAuthHelper::get_current_user()->clean_query_args( $args );
		$allowed_statuses = OsCalendarHelper::get_booking_statuses_to_display_on_calendar();
		if (empty($allowed_statuses)) {
			return [];
		}

		return $bookings->select( '*, count(id) as total_customers, sum(total_attendees) as total_attendees_sum' )
		                ->where_in('status', $allowed_statuses )
		                ->group_by( 'start_datetime_utc, agent_id, service_id, location_id' )
		                ->where( $args )
		                ->set_limit( $limit )
		                ->order_by( 'start_datetime_utc asc' )
		                ->get_results_as_models();

	}

	public function get_nice_start_time_for_customer() {
		return $this->format_start_date_and_time( OsTimeHelper::get_time_format(), false, $this->get_customer_timezone() );
	}

	public function get_nice_end_time_for_customer() {
		return $this->format_end_date_and_time( OsTimeHelper::get_time_format(), false, $this->get_customer_timezone() );
	}

	public function get_nice_start_date_for_customer($customer_timezone = false, $hide_year = false) {
		if(!$customer_timezone) $customer_timezone = $this->get_customer_timezone();
		return OsUtilHelper::translate_months($this->format_start_date_and_time( OsSettingsHelper::get_readable_date_format($hide_year), false, $customer_timezone ));
	}

	public function get_nice_start_datetime_for_customer($customer_timezone = false) {
		if(!$customer_timezone) $customer_timezone = $this->get_customer_timezone();
		return OsUtilHelper::translate_months($this->format_start_date_and_time( OsSettingsHelper::get_readable_datetime_format(), false, $customer_timezone ));
	}

	public function get_start_datetime_for_customer() : OsWpDateTime{
		return $this->get_start_datetime($this->get_customer_timezone_name());
	}
	public function get_end_datetime_for_customer() : OsWpDateTime{
		return $this->get_end_datetime($this->get_customer_timezone_name());
	}

	public function get_customer_timezone() : DateTimeZone{
		if(OsSettingsHelper::is_on('steps_show_timezone_selector')){
			return ($this->customer_id) ? $this->customer->get_selected_timezone_obj() : OsTimeHelper::get_timezone_from_session();
		}else{
			return OsTimeHelper::get_wp_timezone();
		}
	}
	public function get_customer_timezone_name() : string{
		if(OsSettingsHelper::is_on('steps_show_timezone_selector')) {
			return ( $this->customer_id ) ? $this->customer->get_selected_timezone_name() : OsTimeHelper::get_timezone_name_from_session();
		}else{
			return OsTimeHelper::get_wp_timezone_name();
		}
	}

	/**
	 *
	 * Returns time in WP timezone, because start_time is stored in WP timezone, do not use it for customer facing outputs
	 *
	 * @return string|null
	 */
	public function get_nice_start_time() {
		return OsTimeHelper::minutes_to_hours_and_minutes( $this->start_time );
	}

	/**
	 *
	 * Returns time in WP timezone, because end_time is stored in WP timezone, do not use it for customer facing outputs
	 *
	 * @return string|null
	 */
	public function get_nice_end_time() {
		return OsTimeHelper::minutes_to_hours_and_minutes( $this->end_time );
	}

	/**
	 *
	 * Returns time in WP timezone, because start_date is stored in WP timezone, do not use it for customer facing outputs
	 *
	 * @return string|null
	 */
	public function get_nice_end_date( $hide_year_if_current = false ) {
		$datetime = OsWpDateTime::os_createFromFormat( "Y-m-d", $this->end_date );
		OsTimeHelper::format_to_nice_date($datetime, $hide_year_if_current);
	}

	/**
	 *
	 * Returns time in WP timezone, because end_date is stored in WP timezone, do not use it for customer facing outputs
	 *
	 * @return string|null
	 */
	public function get_nice_start_date( $hide_year_if_current = false ) : string {
		$datetime = OsWpDateTime::os_createFromFormat( "Y-m-d", $this->start_date );
		return OsTimeHelper::format_to_nice_date($datetime, $hide_year_if_current);
	}



	/**
	 *
	 * Returns time in WP timezone, because start_date is stored in WP timezone, do not use it for customer facing outputs
	 *
	 * @param $hide_if_today bool
	 * @param $hide_year_if_current bool
	 *
	 * @return string
	 */
	public function get_nice_start_datetime( bool $hide_if_today = true, bool $hide_year_if_current = true ): string {
		if ( $hide_if_today && $this->start_date == OsTimeHelper::today_date( 'Y-m-d' ) ) {
			$date = __( 'Today', 'latepoint' );
		} else {
			$date = $this->get_nice_start_date( $hide_year_if_current );
		}

		return implode( ', ', array_filter( [ $date, $this->get_nice_start_time() ] ) );
	}


	public function is_bundle_scheduling() : bool {
		return ! empty( $this->order_item_id );
	}

	public function get_connected_recurring_bookings() : array{
		if(empty($this->recurrence_id) || $this->is_new_record()) return [];
		$bookings = new OsBookingModel();
		return $bookings->where(['recurrence_id' => $this->recurrence_id, 'id !=' => $this->id])->order_by('start_datetime_utc asc')->get_results_as_models();
	}

	public function get_nice_datetime_for_summary(string $viewer = 'customer'){
		$nice_datetime = '';
		if($this->start_date){
			$nice_datetime = $this->get_nice_start_datetime(false);
			if(OsSettingsHelper::is_on( 'show_booking_end_time') && !empty($this->end_time) && !empty($this->start_time)){
				$nice_datetime = $nice_datetime.' - '.$this->get_nice_end_time();
			}
		}
		/**
		 * Get a formatted start and end time (if needed)
		 *
		 * @since 5.1.0
		 * @hook latepoint_get_nice_datetime_for_summary
		 *
		 * @param {string} $statuses Nice datetime
		 * @param {OsBookingModel} $booking An object of booking model
		 *
		 * @returns {string} Filtered nice datetime
		 */
		$nice_datetime = apply_filters('latepoint_get_nice_datetime_for_summary', $nice_datetime, $this, $viewer);
		return $nice_datetime;
	}


	public function format_end_date_and_time( $format = LATEPOINT_DATETIME_DB_FORMAT, $input_timezone = false, $output_timezone = false ) {
		if ( ! $input_timezone ) {
			$input_timezone = OsTimeHelper::get_wp_timezone();
		}
		if ( ! $output_timezone ) {
			$output_timezone = OsTimeHelper::get_wp_timezone();
		}

		$date = OsWpDateTime::os_createFromFormat( LATEPOINT_DATETIME_DB_FORMAT, $this->end_date . ' ' . OsTimeHelper::minutes_to_army_hours_and_minutes( $this->end_time ) . ':00', $input_timezone );
		$date->setTimeZone( $output_timezone );

		return OsUtilHelper::translate_months( $date->format( $format ) );
	}

	public function format_start_date() {
		if ( empty( $this->start_date ) ) {
			$date             = new OsWpDateTime();
			$this->start_date = $date->format( 'Y-m-d' );
		} else {
			$date = OsWpDateTime::os_createFromFormat( "Y-m-d", $this->start_date );
		}

		return $date->format( OsSettingsHelper::get_date_format() );
	}

	public function format_start_date_and_time( $format = LATEPOINT_DATETIME_DB_FORMAT, $input_timezone = false, $output_timezone = false ) {
		if ( ! $input_timezone ) {
			$input_timezone = OsTimeHelper::get_wp_timezone();
		}
		if ( ! $output_timezone ) {
			$output_timezone = OsTimeHelper::get_wp_timezone();
		}

		if ( is_null( $this->start_time ) || $this->start_time === '' ) {
			// no time set yet (could be because summary is reloaded when date is picked, before the time is picked)
			$date = OsWpDateTime::os_createFromFormat( "Y-m-d", $this->start_date );
			if ( $date ) {
				return OsUtilHelper::translate_months( $date->format( OsSettingsHelper::get_readable_date_format() ) );
			} else {
				return __( 'Invalid Date/Time', 'latepoint' );
			}
		} else {
			// both date & time are set, update timezone and translate
			$date = OsWpDateTime::os_createFromFormat( LATEPOINT_DATETIME_DB_FORMAT, $this->start_date . ' ' . OsTimeHelper::minutes_to_army_hours_and_minutes( $this->start_time ) . ':00', $input_timezone );
			if ( $date ) {
				$date->setTimeZone( $output_timezone );

				return OsUtilHelper::translate_months( $date->format( $format ) );
			} else {
				return __( 'Invalid Date/Time', 'latepoint' );
			}
		}
	}

	public function format_start_date_and_time_rfc3339() {
		return $this->format_start_date_and_time( \DateTime::RFC3339 );
	}

	public function format_end_date_and_time_rfc3339() {
		return $this->format_end_date_and_time( \DateTime::RFC3339 );
	}

	public function format_start_date_and_time_for_google() {
		return $this->format_start_date_and_time( \DateTime::RFC3339 );
	}

	public function format_end_date_and_time_for_google() {
		return $this->format_end_date_and_time( \DateTime::RFC3339 );
	}

	/*
	 * Checks if the booking has passed
	 */
	public function time_status() {
		try {
			$now_datetime  = OsTimeHelper::now_datetime_utc();
			if(empty($this->start_datetime_utc) || empty($this->end_datetime_utc)){
				$this->set_utc_datetimes(true);
			}
			$booking_start = new OsWpDateTime( $this->start_datetime_utc, new DateTimeZone( 'UTC' ) );
			$booking_end   = new OsWpDateTime( $this->end_datetime_utc, new DateTimeZone( 'UTC' ) );
			if ( ( $now_datetime <= $booking_end ) && ( $now_datetime >= $booking_start ) ) {
				return 'now';
			} elseif ( $now_datetime <= $booking_start ) {
				return 'upcoming';
			} else {
				return 'past';
			}
		} catch ( Exception $e ) {
			return 'past';
		}

	}

	public function start_datetime_in_format( string $format, string $output_in_timezone_name ) : string {
		if(empty($this->start_datetime_utc)) return '';
		$booking_start_datetime = OsTimeHelper::date_from_db( $this->start_datetime_utc );
		$booking_start_datetime->setTimezone( new DateTimeZone($output_in_timezone_name) );
		return $booking_start_datetime->format( $format );
	}

	public function is_start_date_and_time_set() : bool {
		return ($this->start_date != '' && $this->start_time != '');
	}

	protected function get_time_left() {
		$now_datetime     = new OsWpDateTime( 'now' );
		$booking_datetime = OsWpDateTime::os_createFromFormat( LATEPOINT_DATETIME_DB_FORMAT, $this->format_start_date_and_time() );
		$css_class        = 'left-days';

		if ( $booking_datetime ) {
			$diff = $now_datetime->diff( $booking_datetime );
			if ( $diff->d > 0 || $diff->m > 0 || $diff->y > 0 ) {
				$left = $diff->format( '%a ' . __( 'days', 'latepoint' ) );
			} else {
				if ( $diff->h > 0 ) {
					$css_class = 'left-hours';
					$left      = $diff->format( '%h ' . __( 'hours', 'latepoint' ) );
				} else {
					$css_class = 'left-minutes';
					$left      = $diff->format( '%i ' . __( 'minutes', 'latepoint' ) );
				}
			}
		} else {
			$left = 'n/a';
		}

		return '<span class="time-left ' . esc_attr($css_class) . '">' . esc_html($left) . '</span>';
	}


	protected function get_agent() {
		if ( $this->agent_id ) {
			if ( ! isset( $this->agent ) || ( isset( $this->agent ) && ( $this->agent->id != $this->agent_id ) ) ) {
				$this->agent = new OsAgentModel( $this->agent_id );
			}
		} else {
			$this->agent = new OsAgentModel();
		}

		return $this->agent;
	}

	public function get_agent_full_name() {
		if ( $this->agent_id == LATEPOINT_ANY_AGENT ) {
			return __( 'Any Available Agent', 'latepoint' );
		} else {
			return $this->agent->full_name;
		}
	}


	public function get_location() {
		if ( $this->location_id ) {
			// if location has not been initialized yet, or location_id is different from the one initialized - init again
			if ( empty( $this->location ) || ( $this->location->id != $this->location_id ) ) {
				$this->location = new OsLocationModel( $this->location_id );
			}
		} else {
			$this->location = new OsLocationModel();
		}

		return $this->location;
	}

	protected function get_customer() {
		if ( $this->customer_id ) {
			if ( ! isset( $this->customer ) || ( isset( $this->customer ) && ( $this->customer->id != $this->customer_id ) ) ) {
				$this->customer = new OsCustomerModel( $this->customer_id );
			}
		} else {
			$this->customer = new OsCustomerModel();
		}

		return $this->customer;
	}


	protected function get_service() {
		if ( $this->service_id ) {
			if ( ! isset( $this->service ) || ( isset( $this->service ) && ( $this->service->id != $this->service_id ) ) ) {
				$this->service = new OsServiceModel( $this->service_id );
			}
		} else {
			$this->service = new OsServiceModel();
		}

		return $this->service;
	}

	public function get_nice_start_date_in_timezone(string $timezone_name = '', $hide_year_if_current = false) : string{
		$datetime = $this->get_start_datetime($timezone_name);
		return OsTimeHelper::format_to_nice_date($datetime, $hide_year_if_current);
	}

	public function get_nice_end_date_in_timezone(string $timezone_name = '', $hide_year_if_current = false) : string{
		$datetime = $this->get_end_datetime($timezone_name);
		return OsTimeHelper::format_to_nice_date($datetime, $hide_year_if_current);
	}

	public function get_nice_start_time_in_timezone(string $timezone_name = '') : string{
		$datetime = $this->get_start_datetime($timezone_name);
		return OsTimeHelper::format_to_nice_time($datetime);
	}

	public function get_nice_end_time_in_timezone(string $timezone_name = '') : string{
		$datetime = $this->get_end_datetime($timezone_name);
		return OsTimeHelper::format_to_nice_time($datetime);
	}

	public function get_start_datetime_object( ?DateTimeZone $timezone = null ) {
		if ( empty( $timezone ) ) {
			$timezone = OsTimeHelper::get_wp_timezone();
		}
		if ( empty( $this->start_datetime_utc ) ) {
			// fix data, probably an older booking from the time when we didn't store UTC date
			$this->start_datetime_utc = $this->generate_start_datetime_in_db_format();
		}
		$booking_start_datetime = OsTimeHelper::date_from_db( $this->start_datetime_utc );
		if ( $booking_start_datetime ) {
			$booking_start_datetime->setTimezone( $timezone );
		} else {
			OsDebugHelper::log( 'Error generating start date and time for booking ID: ' . $this->id, 'corrupt_booking_data' );
		}

		return $booking_start_datetime;
	}

	public function get_end_datetime_object( ?DateTimeZone $timezone = null ) {
		if ( empty( $timezone ) ) {
			$timezone = OsTimeHelper::get_wp_timezone();
		}
		if ( empty( $this->end_datetime_utc ) ) {
			// fix data, probably an older booking from the time when we didn't store UTC date
			$this->end_datetime_utc = $this->generate_end_datetime_in_db_format();
		}
		$booking_end_datetime = OsTimeHelper::date_from_db( $this->end_datetime_utc );
		if ( $booking_end_datetime ) {
			$booking_end_datetime->setTimezone( $timezone );
		} else {
			OsDebugHelper::log( 'Error generating end date and time for booking ID: ' . $this->id, 'corrupt_booking_data' );
		}

		return $booking_end_datetime;
	}

	public function get_start_datetime( string $set_timezone = 'UTC') : OsWpDateTime{
		try{
			// start_time and start_date is legacy stored in wordpress timezone
			$dateTime = new OsWpDateTime( $this->start_date . ' 00:00:00', OsTimeHelper::get_wp_timezone() );
			if($this->start_time > 0){
				$dateTime->modify( '+' . $this->start_time . ' minutes' );
			}
			if($set_timezone) $dateTime->setTimezone( new DateTimeZone( $set_timezone ) );
			return $dateTime;
		}catch(Exception $e){
			return new OsWpDateTime('now');
		}
	}

	public function get_end_datetime( string $set_timezone = 'UTC') : OsWpDateTime{
		try{
			// start_time and start_date is legacy stored in wordpress timezone
			$dateTime = new OsWpDateTime( $this->end_date . ' 00:00:00', OsTimeHelper::get_wp_timezone() );
			if($this->end_time > 0){
				$dateTime->modify( '+' . $this->end_time . ' minutes' );
			}
			if($set_timezone) $dateTime->setTimezone( new DateTimeZone( $set_timezone ) );
			return $dateTime;
		}catch(Exception $e){
			return new OsWpDateTime('now');
		}
	}

	public function generate_start_datetime_in_db_format( string $timezone = 'UTC' ): string {
		$dateTime = $this->get_start_datetime($timezone);

		return $dateTime->format( LATEPOINT_DATETIME_DB_FORMAT );
	}


	public function generate_end_datetime_in_db_format( string $timezone = 'UTC' ): string {
		$dateTime = $this->get_end_datetime($timezone);

		return $dateTime->format( LATEPOINT_DATETIME_DB_FORMAT );
	}


	protected function before_save() {
		// TODO check for uniqueness
		if ( empty( $this->booking_code ) ) {
			$this->booking_code = strtoupper( OsUtilHelper::random_text( 'distinct', 7 ) );
		}
		if ( empty( $this->end_date ) ) {
			$this->end_date = $this->calculate_end_date();
		}
		if ( empty( $this->status ) ) {
			$this->status = $this->get_default_booking_status();
		}
		if ( empty( $this->total_attendees ) ) {
			$this->total_attendees = 1;
		}
		if ( empty( $this->duration ) && $this->service_id ) {
			$service        = new OsServiceModel( $this->service_id );
			$this->duration = $service->duration;
		}
	}

	public function get_default_booking_status() {
		return OsBookingHelper::get_default_booking_status( $this->service_id );
	}

	public function update_status( $new_status ) {
		if ( $new_status == $this->status ) {
			return true;
		} else {
			if ( ! in_array( $new_status, array_keys( OsBookingHelper::get_statuses_list() ) ) ) {
				$this->add_error( 'invalid_booking_status', 'Invalid booking status' );

				return false;
			}
			$old_booking  = clone $this;
			$this->status = $new_status;
			$result       = $this->update_attributes( [ 'status' => $new_status ] );
			if ( $result ) {
				do_action( 'latepoint_booking_updated', $this, $old_booking );

				return true;
			} else {
				return false;
			}
		}
	}

	public function convert_start_datetime_into_server_timezone(string $input_timezone, bool $set_as_customer_timezone = true){
		$this->server_timezone   = OsTimeHelper::get_wp_timezone_name();
		if($set_as_customer_timezone) $this->customer_timezone = $input_timezone;
		if ( $this->is_start_date_and_time_set() && $this->server_timezone != $input_timezone ) {

			try {
				// convert from submitted customer timezone into WP timezone
				$start_datetime = new OsWpDateTime( $this->start_date . ' 00:00:00', new DateTimeZone( $input_timezone ) );
				if ( $this->start_time > 0 ) {
					$start_datetime->modify( '+' . $this->start_time . ' minutes' );
				}
				$start_datetime->setTimezone( OsTimeHelper::get_wp_timezone() );
				$this->start_date = $start_datetime->format( 'Y-m-d' );
				$this->start_time = OsTimeHelper::convert_datetime_to_minutes( $start_datetime );

			} catch ( Exception $e ) {
			}

		}
	}

	public function save_avatar( $image_id = false ) {
		if ( ( false === $image_id ) && $this->image_id ) {
			$image_id = $this->image_id;
		}
		if ( $image_id && $this->post_id ) {
			set_post_thumbnail( $this->post_id, $image_id );
			$this->image_id = $image_id;
		}

		return $this->image_id;
	}


	protected function allowed_params( $role = 'admin' ) {
		$allowed_params = array(
			'service_id',
			'booking_code',
			'agent_id',
			'customer_id',
			'location_id',
			'start_date',
			'end_date',
			'start_time',
			'end_time',
			'start_datetime_utc',
			'end_datetime_utc',
			'buffer_before',
			'duration',
			'buffer_after',
			'total_attendees',
			'total_attendees_sum',
			'total_customers',
			'cart_item_id',
			'order_item_id',
			'status',
			'form_id',
			'server_timezone',
			'customer_timezone',
			'generate_recurrent_sequence',
			'recurrence_id'
		);

		return $allowed_params;
	}


	protected function params_to_save( $role = 'admin' ) {
		$params_to_save = array(
			'service_id',
			'booking_code',
			'agent_id',
			'customer_id',
			'location_id',
			'start_date',
			'end_date',
			'start_time',
			'end_time',
			'start_datetime_utc',
			'end_datetime_utc',
			'duration',
			'buffer_before',
			'buffer_after',
			'total_attendees',
			'status',
			'order_item_id',
			'server_timezone',
			'customer_timezone',
			'recurrence_id'
		);

		return $params_to_save;
	}


	protected function properties_to_validate() {
		$validations = array(
			'order_item_id' => array( 'presence' ),
			'service_id'    => array( 'presence' ),
			'agent_id'      => array( 'presence' ),
			'location_id'   => array( 'presence' ),
			'customer_id'   => array( 'presence' ),
			'start_date'    => array( 'presence' ),
			'end_date'      => array( 'presence' ),
			'status'        => array( 'presence' ),
		);

		return $validations;
	}
}