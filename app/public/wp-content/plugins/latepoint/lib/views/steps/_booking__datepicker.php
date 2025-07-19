<?php
/**
 * @var $current_step_code string
 * @var $cart OsCartModel
 * @var $booking OsBookingModel
 * @var $restrictions array
 * @var $presets array
 * @var $calendar_start_date string
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="step-datepicker-w latepoint-step-content" data-step-code="<?php echo esc_attr( $current_step_code ); ?>" data-clear-action="clear_step_datepicker">
	<?php
	do_action( 'latepoint_before_step_content', $current_step_code );
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'before' );
    try{
        $target_date = new OsWpDateTime( $calendar_start_date );
    }catch( Exception $e ){
        $target_date = new OsWpDateTime('now');
    }
    echo OsCalendarHelper::generate_dates_and_times_picker($booking, $target_date, !OsStepsHelper::disable_searching_first_available_slot(), [ 'timezone_name' => OsTimeHelper::get_timezone_name_from_session(), 'consider_cart_items' => true]);

	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'after' );
	do_action( 'latepoint_after_step_content', $current_step_code );

	echo OsFormHelper::hidden_field( 'is_recurring', LATEPOINT_VALUE_OFF, [ 'class' => 'latepoint_is_recurring', 'skip_id' => true ] );
	echo OsFormHelper::hidden_field( 'booking[start_date]', $booking->start_date, [ 'class' => 'latepoint_start_date', 'skip_id' => true ] );
	echo OsFormHelper::hidden_field( 'booking[start_time]', $booking->start_time, [ 'class' => 'latepoint_start_time', 'skip_id' => true ] );
	?>
</div>