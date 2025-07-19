<?php
/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 *
 * This class is used to create booking requests, they are useful for getting availability information on the calendar
 * and checking if a timeslot is available.
 */

namespace LatePoint\Misc;

class BookingRequest{
	public ?string $start_date;
	public ?string $end_date;
	public ?int $start_time = 0;
	public ?int $end_time = 0;
	public ?int $duration = 0;
	public ?int $buffer_before = 0;
	public ?int $buffer_after = 0;
	public ?int $total_attendees = 1;
	public $service_id = 0;
	public $agent_id = 0;
	public $location_id = 0;

	function __construct($args = []){
		$allowed_props = self::allowed_props();
		foreach($args as $key => $arg){
			if(in_array($key, $allowed_props)) $this->$key = $arg;
		}
	}


	public static function create_from_booking_model(\OsBookingModel $booking): BookingRequest{
		$booking_request = new BookingRequest([ 'start_date'      => $booking->start_date,
																'end_date'        => $booking->end_date ? $booking->end_date : $booking->start_date,
																'start_time'      => (int) $booking->start_time,
		                            'end_time'        => (int) $booking->end_time,
		                            'duration'        => (int) $booking->get_total_duration(),
		                            'buffer_before'   => (int) $booking->buffer_before,
		                            'buffer_after'    => (int) $booking->buffer_after,
		                            'total_attendees' => (int) $booking->total_attendees,
		                            'agent_id'        => ($booking->agent_id == LATEPOINT_ANY_AGENT) ? 0 : $booking->agent_id,
		                            'service_id'      => $booking->service_id,
																'location_id'     => ($booking->location_id == LATEPOINT_ANY_LOCATION) ? 0 : $booking->location_id]);
		return apply_filters('latepoint_create_booking_request_from_booking_model', $booking_request, $booking);
	}

	public function get_start_time_with_buffer(): int{
		return $this->start_time - $this->buffer_before;
	}

	public function get_end_time_with_buffer(): int{
		return $this->end_time + $this->buffer_after;
	}


	public function get_start_datetime( string $set_timezone = '') : \OsWpDateTime{
		try{
			// start_time and start_date is legacy stored in wordpress timezone
			$dateTime = new \OsWpDateTime( $this->start_date . ' 00:00:00', \OsTimeHelper::get_wp_timezone() );
			if($this->start_time > 0){
				$dateTime->modify( '+' . $this->start_time . ' minutes' );
			}
			if(!empty($set_timezone)) $dateTime->setTimezone( new \DateTimeZone( $set_timezone ) );
			return $dateTime;
		}catch(\Exception $e){
			return new \OsWpDateTime('now');
		}
	}

	public function get_end_datetime( string $set_timezone = '') : \OsWpDateTime{
		try{
			// start_time and start_date is legacy stored in wordpress timezone
			$dateTime = new \OsWpDateTime( $this->end_date . ' 00:00:00', \OsTimeHelper::get_wp_timezone() );
			if($this->end_time > 0){
				$dateTime->modify( '+' . $this->end_time . ' minutes' );
			}
			if(!empty($set_timezone)) $dateTime->setTimezone( new \DateTimeZone( $set_timezone ) );
			return $dateTime;
		}catch(\Exception $e){
			return new \OsWpDateTime('now');
		}
	}


	public static function allowed_props(): array{
		return ['start_date',
						'end_date',
						'start_time',
						'end_time',
						'duration',
						'buffer_before',
						'buffer_after',
						'total_attendees',
						'agent_id',
						'service_id',
						'location_id'];
	}
}