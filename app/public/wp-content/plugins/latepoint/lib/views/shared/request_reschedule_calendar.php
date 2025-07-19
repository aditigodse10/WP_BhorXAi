<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/** @var $booking OsBookingModel */
/** @var $calendar_start_date OsWpDateTime */
/** @var $timezone_name integer */
/** @var $key string */
?>
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="latepoint-lightbox-heading">
    <h2><?php esc_html_e( 'Select date and time', 'latepoint' ); ?></h2>
</div>
<div class="latepoint-lightbox-content">
    <div class="reschedule-calendar-datepicker" data-route="<?php echo empty($key) ? esc_attr(OsRouterHelper::build_route_name('customer_cabinet', 'request_reschedule_calendar')) : esc_attr(OsRouterHelper::build_route_name('manage_booking_by_key', 'request_reschedule_calendar')); ?>">
		<?php
        echo OsCalendarHelper::generate_dates_and_times_picker( $booking, $calendar_start_date, false, [ 'timezone_name' => $timezone_name, 'exclude_booking_ids' => [ $booking->id ] ] );
		echo OsFormHelper::hidden_field( 'booking_id', $booking->id, [ 'class' => 'latepoint_booking_id', 'skip_id' => true ] );
		if ( ! empty( $key ) ) {
			echo OsFormHelper::hidden_field( 'key', $key, [ 'class' => 'latepoint_manage_booking_key', 'skip_id' => true ] );
		}

		echo OsFormHelper::hidden_field( 'booking[start_date]', $booking->start_date, [ 'class' => 'latepoint_start_date', 'skip_id' => true ] );
		echo OsFormHelper::hidden_field( 'booking[start_time]', $booking->start_time, [ 'class' => 'latepoint_start_time', 'skip_id' => true ] );
		echo OsFormHelper::hidden_field( 'timezone_name', $timezone_name, [ 'class' => 'latepoint_timezone_name', 'skip_id' => true ] );
		?>
    </div>
</div>
<div class="latepoint-lightbox-footer reschedule-confirmation-button-wrapper" style="display: none;">
    <a href="#"
       data-route-name="<?php echo esc_attr( OsRouterHelper::build_route_name( ( empty( $key ) ? 'customer_cabinet' : 'manage_booking_by_key' ), 'process_reschedule_request' ) ); ?>"
       class="latepoint-btn latepoint-btn-primary latepoint-btn-block latepoint-request-reschedule-trigger"><?php esc_html_e( 'Reschedule', 'latepoint' ); ?></a>
</div>