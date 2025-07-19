<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="latepoint-print-summary-w">
    <div class="booking-status-info-wrapper status-<?php echo esc_attr($order->status); ?>">
        <div class="booking-status-icon"></div>
        <div class="booking-status-label"><?php echo $order->get_nice_status_name(); ?></div>
        <div class="booking-confirmation"><?php echo sprintf(esc_html__('Order #%s', 'latepoint'), '<strong>'.$order->confirmation_code.'</strong>'); ?></div>
    </div>
	<?php include(LATEPOINT_VIEWS_ABSPATH.'orders/_full_summary.php'); ?>
</div>