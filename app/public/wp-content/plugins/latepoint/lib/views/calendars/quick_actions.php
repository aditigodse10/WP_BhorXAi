<?php
/**
 * @var $agents_list array
 * @var $services_list array
 * @var $locations_list array
 * @var $blocked_period OsOffPeriodModel
 * @var $readable_start_date string
 * @var $start_date OsWpDateTime
 *
 */
?>
<div class="latepoint-lightbox-heading">
    <h2><?php echo $readable_start_date; ?></h2>
</div>
<div class="latepoint-lightbox-content">
    <div class="quick-calendar-actions-wrapper <?php echo $blocked_period->is_new_record() ? '' : 'showing-settings'; ?>">
    <div class="quick-calendar-actions">
        <div class="quick-calendar-action-new-booking quick-calendar-action" <?php echo OsOrdersHelper::quick_order_btn_html( false, [ 'start_time'  => $blocked_period->start_time,
		                                                                                                                               'agent_id'    => $blocked_period->agent_id,
		                                                                                                                               'start_date'  => $blocked_period->start_date,
		                                                                                                                               'location_id' => $blocked_period->location_id,
		                                                                                                                               'service_id'  => $blocked_period->service_id
		] ); ?>><i class="latepoint-icon latepoint-icon-plus1"></i><span><?php esc_html_e( 'Create a Booking', 'latepoint' ); ?></span></div>
        <div class="quick-calendar-action-slot-off quick-calendar-action"><i class="latepoint-icon latepoint-icon-clock"></i><span><?php esc_html_e( 'Block a Time Slot', 'latepoint' ); ?></span></div>
        <div class="quick-calendar-action-day-off quick-calendar-action"><i class="latepoint-icon latepoint-icon-cancel"></i><span><?php esc_html_e( 'Set as Day Off', 'latepoint' ); ?></span></div>
    </div>
    <div class="quick-calendar-action-settings <?php echo $blocked_period->is_new_record() ? 'setting-day-off' : 'setting-slot-off'; ?>">
        <div class="quick-calendar-locked-feature">
            <h3><?php esc_html_e('Premium Feature', 'latepoint'); ?></h3>
            <div><?php esc_html_e('This feature is only available in a Premium version.', 'latepoint'); ?></div>
            <a href="#" class="latepoint-btn latepoint-btn-primary" <?php echo OsSettingsHelper::get_link_attributes_for_premium_features(); ?> target="blank"><span><?php esc_html_e('Unlock All Features', 'latepoint'); ?></span><i class="latepoint-icon latepoint-icon-arrow-right"></i></a>
        </div>
        <?php do_action( 'latepoint_quick_calendar_actions_settings', $blocked_period, $start_date, $readable_start_date, $agents_list, $services_list, $locations_list ); ?>
    </div>
    </div>
</div>