<?php
/*
 * Copyright (c) 2021 LatePoint LLC. All rights reserved.
 */

namespace LatePoint\Misc;

class Filter {
	public $service_id = 0;
	public $agent_id = 0;
	public $location_id = 0;

	public $connections = [];

	public ?int $week_day = null;
	public ?string $date_from = null;
	public ?string $date_to = null;

	public ?int $start_time = null;
	public ?int $end_time = null;

	public array $statuses = [];
	public array $exclude_booking_ids = [];
	public bool $consider_cart_items = false;
	public bool $exact_match = false;

	function __construct( array $args = [] ) {
		$allowed_args = [
			'service_id',
			'agent_id',
			'location_id',
			'connections',
			'date_from',
			'date_to',
			'start_time',
			'end_time',
			'week_day',
			'statuses',
			'exclude_booking_ids',
			'exact_match'
		];
		foreach ( $args as $key => $arg ) {
			if ( in_array( $key, $allowed_args ) ) {
				$this->$key = $arg;
			}
		}
	}

	public function build_query_args_for_blocked_periods(): array {

		$query_args = [];

		// if connections are passed - query by connection
		if ( $this->connections ) {
			$connection_conditions = [];
			foreach ( $this->connections as $connection ) {
				$connection_conditions[] = [ 'AND' => [ 'agent_id'    => [ 0, $connection->agent_id ],
				                                        'service_id'  => [ 0, $connection->service_id ],
				                                        'location_id' => [ 0, $connection->location_id ]
				]
				];
			}
			$query_args['AND'][] = [ 'OR' => $connection_conditions ];
		} else {
			// Service query
			if ( $this->exact_match ) {
				// search only for schedules that belong to passed service_id
				$query_args['service_id'] = $this->service_id;
			} else {
				$query_args['service_id'] = array_unique( is_array( $this->service_id ) ? array_merge( $this->service_id, [ 0 ] ) : [ $this->service_id, 0 ] );
			}

			// Location query
			if ( $this->exact_match ) {
				// search only for schedules that belong to passed location_id
				$query_args['location_id'] = $this->location_id;
			} else {
				$query_args['location_id'] = array_unique( is_array( $this->location_id ) ? array_merge( $this->location_id, [ 0 ] ) : [ $this->location_id, 0 ] );
			}

			// Agent query
			if ( $this->exact_match ) {
				// search only for schedules that belong to passed agent_id
				$query_args['agent_id'] = $this->agent_id;
			} else {
				$query_args['agent_id'] = array_unique( is_array( $this->agent_id ) ? array_merge( $this->agent_id, [ 0 ] ) : [ $this->agent_id, 0 ] );
			}
		}


		if ( $this->date_from ) {
			$query_args['start_date >='] = $this->date_from;
			$query_args['start_date <='] = $this->date_to;
		}

		return $query_args;
	}

	public static function create_from_booking_request( BookingRequest $booking_request ): Filter {
		return new self( [
			'date_from'   => $booking_request->start_date,
			'start_time'  => $booking_request->start_time,
			'end_time'    => $booking_request->end_time,
			'agent_id'    => $booking_request->agent_id,
			'location_id' => $booking_request->location_id,
			'service_id'  => $booking_request->service_id
		] );
	}

}
