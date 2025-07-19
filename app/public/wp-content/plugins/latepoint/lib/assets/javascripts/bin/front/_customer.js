/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

async function latepoint_init_transaction_payment_form() {
    let callbacks_list = [];
    let $transaction_payment_form = jQuery('.latepoint-transaction-payment-form');
    let current_step = $transaction_payment_form.find('input[name="current_step"]').val();

    $transaction_payment_form.on('click keydown', '.lp-option', (e) => {
        let $option = jQuery(e.currentTarget);
        if(e.type === 'keydown' && e.key !== ' ' &&  e.key !== 'Enter') return;
        $option.closest('.lp-options').find('.lp-option.selected').removeClass('selected');
        $option.addClass('selected');
        $transaction_payment_form.find('input[name="' + $option.data('holder') + '"]').val($option.data('value'));
        $transaction_payment_form.trigger('submit');
        return false;
    });


    switch (current_step) {
        case 'methods':
            break;
        case 'processors':
            break;
        case 'pay':
            $transaction_payment_form.trigger('latepoint:initOrderPaymentMethod', [{
                callbacks_list: callbacks_list,
                payment_method: $transaction_payment_form.find('input[name="payment_method"]').val(),
                payment_processor: $transaction_payment_form.find('input[name="payment_processor"]').val(),
            }]);
            $transaction_payment_form.addClass('os-loading');

            try {
                for (const callback of callbacks_list) {
                    await callback.action();
                }
                $transaction_payment_form.removeClass('os-loading');
            } catch (error) {
                latepoint_show_error_and_stop_loading_booking_form(error, $transaction_payment_form);
            }
            break;
        case 'confirmation':
            break;
    }

    $transaction_payment_form.on('submit', async function (e) {
        e.preventDefault();
        await latepoint_submit_transaction_payment_form(jQuery(e.target));
    });
}

async function latepoint_submit_transaction_payment_form($transaction_payment_form) {

    if($transaction_payment_form.hasClass('os-loading')) return false;
    let callbacks_list = [];

    $transaction_payment_form.find('.latepoint-message').remove();
    $transaction_payment_form.addClass('os-loading');
    $transaction_payment_form.find('.latepoint-btn').addClass('os-loading');

    $transaction_payment_form.trigger('latepoint:submitTransactionPaymentForm', [{
        callbacks_list: callbacks_list,
        payment_method: $transaction_payment_form.find('input[name="payment_method"]').val(),
        payment_processor: $transaction_payment_form.find('input[name="payment_processor"]').val(),
        current_step: $transaction_payment_form.find('input[name="current_step"]').val(),
    }]);

    try {
        for (const callback of callbacks_list) {
            await callback.action();
        }
    } catch (error) {
        $transaction_payment_form.removeClass('os-loading').find('.os-loading').removeClass('os-loading');
        latepoint_show_message_inside_element(error.message, $transaction_payment_form.find('.lp-payment-method-content'), 'error');
        return false;
    }


    try {
        let response = await jQuery.ajax({
            type: "post",
            dataType: "json",
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: latepoint_create_form_data($transaction_payment_form, latepoint_helper.invoices_payment_form_route)
        });

        $transaction_payment_form.removeClass('os-loading').find('.os-loading').removeClass('os-loading');

        if (response.status === 'success') {
            $transaction_payment_form.html(response.message);
            return await latepoint_init_transaction_payment_form();

        } else {
            latepoint_show_message_inside_element(response.message, $transaction_payment_form.find('.lp-payment-method-content'), 'error');
            return false;
        }
    } catch (e) {

        $transaction_payment_form.removeClass('os-loading').find('.os-loading').removeClass('os-loading');
        console.log(e);
        alert('Error:' + e);
    }

}

function latepoint_hide_reschedule_button() {
    jQuery('.reschedule-confirmation-button-wrapper').hide();
}

function latepoint_show_reschedule_button() {
    jQuery('.reschedule-confirmation-button-wrapper').show();
}

function latepoint_customer_cabinet_reload_booking_tile($booking_tile) {
    $booking_tile.addClass('os-loading');
    let params = {
        booking_id: $booking_tile.data('id'),
    }
    let data = {
        action: latepoint_helper.route_action,
        route_name: $booking_tile.data('route-name'),
        params: params,
        layout: 'none',
        return_format: 'json'
    };
    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: latepoint_timestamped_ajaxurl(),
        data: data,
        success: function (data) {
            $booking_tile.removeClass('os-loading')
            if (data.status === "success") {
                $booking_tile.replaceWith(data.message);
            } else {
                alert(data.message);
            }
        }
    });
}

// show invoices -> summary_before_payment in modal
function show_summary_before_payment($access_key) {
    let data = {
        action: latepoint_helper.route_action,
        route_name: latepoint_helper.invoices_summary_before_payment_route,
        params: {key: $access_key},
        layout: 'none',
        return_format: 'json'
    }

    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: latepoint_timestamped_ajaxurl(),
        data: data,
        success: function (data) {
            latepoint_show_data_in_lightbox(data.message, 'width-500');
        }
    });
}

function latepoint_reload_reschedule_calendar($reschedule_calendar_wrapper) {
    $reschedule_calendar_wrapper.addClass('os-loading');
    let $calendar_element = $reschedule_calendar_wrapper.find('.os-monthly-calendar-days-w.active').last();
    let calendar_year = $calendar_element.data('calendar-year');
    let calendar_month = $calendar_element.data('calendar-month');

    let data = {
        action: latepoint_helper.route_action,
        route_name: $reschedule_calendar_wrapper.data('route'),
        params: {
            timezone_name: $reschedule_calendar_wrapper.find('input[type="hidden"].latepoint_timezone_name').val(),
            key: $reschedule_calendar_wrapper.find('input[type="hidden"].latepoint_manage_booking_key').val(),
            target_date_string: `${calendar_year}-${calendar_month}-1`,
            booking_id: $reschedule_calendar_wrapper.find('input[type="hidden"].latepoint_booking_id').val()
        },
        layout: 'none',
        return_format: 'json'
    }
    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: latepoint_timestamped_ajaxurl(),
        data: data,
        success: function (data) {
            $reschedule_calendar_wrapper.removeClass('os-loading');
            if (data.status === "success") {
                jQuery('.latepoint-lightbox-i').html(data.message);
            } else {
                // console.log(data.message);
            }
        }
    });
    latepoint_calendar_show_or_hide_prev_next_buttons($reschedule_calendar_wrapper);
    return false;
}

function latepoint_init_reschedule() {

    let $reschedule_wrapper = jQuery('.reschedule-calendar-wrapper');

    latepoint_init_timezone_picker($reschedule_wrapper);

    $reschedule_wrapper.on('click', '.latepoint-request-reschedule-trigger', function () {
        let $trigger = jQuery(this)
        let $wrapper = $trigger.closest('.reschedule-calendar-wrapper')
        let booking_id = $wrapper.find('input[type="hidden"].latepoint_booking_id').val()

        $trigger.addClass('os-loading')
        let params = {
            booking_id: booking_id,
            key: $wrapper.find('input[type="hidden"].latepoint_manage_booking_key').val(),
            start_date: $wrapper.find('input[type="hidden"].latepoint_start_date').val(),
            start_time: $wrapper.find('input[type="hidden"].latepoint_start_time').val(),
            timezone_name: $wrapper.find('input[type="hidden"].latepoint_timezone_name').val(),
        }
        let data = {
            action: latepoint_helper.route_action,
            route_name: $trigger.data('route-name'),
            params: params,
            layout: 'none',
            return_format: 'json'
        };
        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: latepoint_timestamped_ajaxurl(),
            data: data,
            success: function (data) {
                $trigger.removeClass('os-loading')
                if (data.status === "success") {
                    jQuery('.latepoint-lightbox-content').html(data.message);
                    jQuery('.latepoint-lightbox-footer, .latepoint-lightbox-heading').remove();
                    if (jQuery('.customer-bookings-tiles').length) {
                        // called from customer cabinet
                        latepoint_customer_cabinet_reload_booking_tile(jQuery('.customer-bookings-tiles .customer-booking[data-id="' + booking_id + '"]'));
                    } else {
                        // called from manage by key
                        latepoint_manage_by_key_reload_booking();
                    }
                } else {
                    latepoint_show_message_inside_element(data.message, jQuery('.latepoint-lightbox-content'), 'error');
                    jQuery('.latepoint-lightbox-content').animate({scrollTop: 0}, 300);
                }
            }
        });
        return false;
    });

    $reschedule_wrapper.on('click keydown', '.dp-timepicker-trigger', function (event) {
        if (event.type === 'keydown' && event.key !== ' ' && event.key !== 'Enter') return;
        var $reschedule_calendar_wrapper = jQuery(this).closest('.reschedule-calendar-wrapper');
        if (jQuery(this).hasClass('is-booked') || jQuery(this).hasClass('is-off')) {
            // Show error message that you cant select a booked period
        } else {
            if (jQuery(this).hasClass('selected')) {
                jQuery(this).removeClass('selected');
                jQuery(this).find('.dp-success-label').remove();
                $reschedule_calendar_wrapper.find('.latepoint_start_time').val('');
                latepoint_hide_reschedule_button();
            } else {
                $reschedule_calendar_wrapper.find('.dp-timepicker-trigger.selected').removeClass('selected').find('.dp-success-label').remove();
                var selected_timeslot_time = jQuery(this).find('.dp-label-time').html();
                jQuery(this).addClass('selected').find('.dp-label').prepend('<span class="dp-success-label">' + latepoint_helper.datepicker_timeslot_selected_label + '</span>');

                var minutes = parseInt(jQuery(this).data('minutes'));
                var start_date = new Date($reschedule_calendar_wrapper.find('.os-day.selected').data('date'));

                $reschedule_calendar_wrapper.find('.latepoint_start_date').val(start_date.toISOString().split('T')[0])
                $reschedule_calendar_wrapper.find('.latepoint_start_time').val(minutes);
                latepoint_show_reschedule_button();
            }
        }
        return false;
    });


    $reschedule_wrapper.on('click', '.os-month-next-btn', function () {
        var $reschedule_calendar_wrapper = jQuery(this).closest('.reschedule-calendar-wrapper');
        var next_month_route_name = jQuery(this).data('route');
        if ($reschedule_calendar_wrapper.find('.os-monthly-calendar-days-w.active + .os-monthly-calendar-days-w').length) {
            $reschedule_calendar_wrapper.find('.os-monthly-calendar-days-w.active').removeClass('active').next('.os-monthly-calendar-days-w').addClass('active');
            latepoint_calendar_set_month_label($reschedule_calendar_wrapper);
        } else {
            // TODO add condition to check maximum number months to call into the future
            if (true) {
                var $btn = jQuery(this);
                $btn.addClass('os-loading');
                var $calendar_element = $reschedule_calendar_wrapper.find('.os-monthly-calendar-days-w').last();
                var calendar_year = $calendar_element.data('calendar-year');
                var calendar_month = $calendar_element.data('calendar-month');
                if (calendar_month == 12) {
                    calendar_year = calendar_year + 1;
                    calendar_month = 1;
                } else {
                    calendar_month = calendar_month + 1;
                }
                var data = {
                    action: latepoint_helper.route_action,
                    route_name: next_month_route_name,
                    params: {
                        timezone_name: $reschedule_calendar_wrapper.find('input[type="hidden"].latepoint_timezone_name').val(),
                        key: $reschedule_calendar_wrapper.find('input[type="hidden"].latepoint_manage_booking_key').val(),
                        target_date_string: `${calendar_year}-${calendar_month}-1`,
                        booking: {
                            id: $reschedule_calendar_wrapper.find('input[type="hidden"].latepoint_booking_id').val()
                        }
                    },
                    layout: 'none',
                    return_format: 'json'
                }
                jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data,
                    success: function (data) {
                        $btn.removeClass('os-loading');
                        if (data.status === "success") {
                            $reschedule_calendar_wrapper.find('.os-months').append(data.message);
                            $reschedule_calendar_wrapper.find('.os-monthly-calendar-days-w.active').removeClass('active').next('.os-monthly-calendar-days-w').addClass('active');
                            latepoint_calendar_set_month_label($reschedule_calendar_wrapper);
                        } else {
                            // console.log(data.message);
                        }
                    }
                });
            }
        }
        latepoint_calendar_show_or_hide_prev_next_buttons($reschedule_calendar_wrapper);
        return false;
    });

    $reschedule_wrapper.on('click', '.os-month-prev-btn', function () {
        var $reschedule_calendar_wrapper = jQuery(this).closest('.reschedule-calendar-wrapper');
        if ($reschedule_calendar_wrapper.find('.os-monthly-calendar-days-w.active').prev('.os-monthly-calendar-days-w').length) {
            $reschedule_calendar_wrapper.find('.os-monthly-calendar-days-w.active').removeClass('active').prev('.os-monthly-calendar-days-w').addClass('active');
            latepoint_calendar_set_month_label($reschedule_calendar_wrapper);
        }
        latepoint_calendar_show_or_hide_prev_next_buttons($reschedule_calendar_wrapper);
        return false;
    });

    $reschedule_wrapper.on('click', '.os-day', function () {
        if (jQuery(this).hasClass('os-day-passed')) return false;
        if (jQuery(this).hasClass('os-not-in-allowed-period')) return false;
        if(jQuery(this).closest('.os-dates-and-times-w').hasClass('calendar-style-modern')){
            if(jQuery(this).hasClass('os-month-prev')) return false;
            if(jQuery(this).hasClass('os-month-next')) return false;
        }
        var $reschedule_calendar_wrapper = jQuery(this).closest('.reschedule-calendar-wrapper');
        if (jQuery(this).closest('.os-monthly-calendar-days-w').hasClass('hide-if-single-slot')) {

            // HIDE TIMESLOT IF ONLY ONE TIMEPOINT
            if (jQuery(this).hasClass('os-not-available')) {
                // clicked on a day that has no available timeslots
                // do nothing
            } else {
                $reschedule_calendar_wrapper.find('.os-day.selected').removeClass('selected');
                jQuery(this).addClass('selected');
                // set date
                $reschedule_calendar_wrapper.find('.latepoint_start_date').val(jQuery(this).data('date'));
                if (jQuery(this).hasClass('os-one-slot-only')) {
                    // clicked on a day that has only one slot available
                    var bookable_minutes = jQuery(this).data('bookable-minutes').toString().split(':')[0];
                    var selected_timeslot_time = latepoint_format_minutes_to_time(Number(bookable_minutes), Number(jQuery(this).data('service-duration')));
                    $reschedule_calendar_wrapper.find('.latepoint_start_time').val(jQuery(this).data('bookable-minutes'));
                    $reschedule_calendar_wrapper.find('.time-selector-w').slideUp(200);
                    latepoint_show_reschedule_button()
                } else {
                    // regular day with more than 1 timeslots available
                    // build timeslots
                    latepoint_generate_day_timeslots(jQuery(this), $reschedule_calendar_wrapper, $reschedule_calendar_wrapper.find('.latepoint-lightbox-content'));
                    // initialize timeslots events
                    // clear time and hide next btn
                    $reschedule_calendar_wrapper.find('.latepoint_start_time').val('');
                }
            }
        } else {

            // SHOW TIMESLOTS EVEN IF ONLY ONE TIMEPOINT
            $reschedule_calendar_wrapper.find('.latepoint_start_date').val(jQuery(this).data('date'));
            $reschedule_calendar_wrapper.find('.os-day.selected').removeClass('selected');
            jQuery(this).addClass('selected');

            // build timeslots
            latepoint_generate_day_timeslots(jQuery(this), $reschedule_calendar_wrapper, $reschedule_calendar_wrapper.find('.latepoint-lightbox-content'));
            // initialize timeslots events
            // clear time and hide next btn
            let $booking_form_element = jQuery(this).closest('.latepoint-booking-form-element');
            if ($booking_form_element.length) latepoint_reload_summary($booking_form_element);
            $reschedule_calendar_wrapper.find('.latepoint_start_time').val('');
            latepoint_hide_next_btn($reschedule_calendar_wrapper);
        }

        return false;
    });
}
