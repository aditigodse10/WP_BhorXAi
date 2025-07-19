/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

function latepoint_check_horizontal_calendar_scroll(){
  if(jQuery('.daily-availability-calendar.horizontal-calendar').length){
    if(jQuery('.daily-availability-calendar.horizontal-calendar').width() < 700){
      jQuery('.daily-availability-calendar.horizontal-calendar').scrollLeft(jQuery('.os-day.selected').index() * jQuery('.os-day.selected').width());
    }
  }
}

function latepoint_calendar_custom_period_created(){
    latepoint_reload_calendar_view();
    latepoint_lightbox_close();
}

function latepoint_init_calendar_quick_actions(){
  latepoint_init_input_masks(jQuery('.quick-calendar-action-settings'));

  jQuery('.quick-calendar-action-day-off').on('click', function(){
    jQuery('.quick-calendar-actions-wrapper').addClass('showing-settings');
    jQuery('.quick-calendar-actions').hide();
    jQuery('.quick-calendar-action-settings').removeClass('setting-slot-off').addClass('setting-day-off');
    jQuery('.quick-calendar-action-settings input[name="blocked_period_settings[full_day_off]"]').val('yes');
    jQuery('.quick-calendar-action-toggle.selected').removeClass('selected');
    jQuery('.quick-calendar-action-toggle[data-period-type="full"]').addClass('selected');

    return false;
  });
  jQuery('.quick-calendar-action-slot-off').on('click', function(){
    jQuery('.quick-calendar-actions-wrapper').addClass('showing-settings');
    jQuery('.quick-calendar-actions').hide();
    jQuery('.quick-calendar-action-settings').removeClass('setting-day-off').addClass('setting-slot-off');
    jQuery('.quick-calendar-action-settings input[name="blocked_period_settings[full_day_off]"]').val('no');
    jQuery('.quick-calendar-action-toggle.selected').removeClass('selected');
    jQuery('.quick-calendar-action-toggle[data-period-type="partial"]').addClass('selected');
    return false;
  });

  jQuery('.quick-calendar-action-toggle').on('click', function(){
    if(jQuery(this).data('period-type') === 'full'){
      jQuery('.quick-calendar-action-day-off').trigger('click');
    }else{
      jQuery('.quick-calendar-action-slot-off').trigger('click');
    }
    return false;
  });
}

function latepoint_init_calendars(){
  latepoint_check_horizontal_calendar_scroll();
  jQuery('.os-calendar-settings-extra .latecheckbox').lateCheckbox();


  jQuery('.calendar-settings-toggler').on('click', function(){
    jQuery('.os-calendar-settings-form').toggleClass('show-extra-settings');
    return false;
  });

  jQuery('.os-calendar-settings-form').on('change', 'select[name="calendar_settings[view]"]', function(){
    jQuery(this).closest('.calendar-wrapper').attr('data-view', jQuery(this).val());
  });

  jQuery('.os-calendar-settings-form').on('change', 'select, input, .latecheckbox ', function(){
    latepoint_reload_calendar_view();
  });


  jQuery('.calendar-view-wrapper').on('click', '.weekly-calendar-agent-selector', function(){
    jQuery('.weekly-calendar-agent-selector.selected').removeClass('selected');
    jQuery(this).addClass('selected');
    jQuery('.os-calendar-settings-form input[name="calendar_settings[selected_agent_id]"]').val(jQuery(this).data('agent-id'));
    jQuery('.agent-weekly-calendar.selected').removeClass('selected');
    jQuery('.agent-weekly-calendar[data-agent-id="'+jQuery(this).data('agent-id')+'"]').addClass('selected');
    return false;
  });

  jQuery('.calendar-view-wrapper').on('click', '.daily-calendar-action-navigation-btn', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery(this).data('target-date')).trigger('change');
    return false;
  });

  jQuery('.calendar-view-wrapper').on('click', '.daily-availability-calendar .os-day', function(){
    jQuery('.os-monthly-calendar-days-w .os-day.selected').removeClass('selected');
    jQuery(this).addClass('selected');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery(this).data('date')).trigger('change');
    return false;
  });


  jQuery('.os-calendar-today-btn').on('click', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery(this).data('target-date')).trigger('change');
    return false;
  });

  jQuery('.os-calendar-prev-btn').on('click', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery('input[name="prev_target_date"]').val()).trigger('change');
    return false;
  });

  jQuery('.os-calendar-next-btn').on('click', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery('input[name="next_target_date"]').val()).trigger('change');
    return false;
  });
}

function latepoint_reload_calendar_view(){
  let $calendar_wrapper = jQuery('.calendar-view-wrapper');
  if(!$calendar_wrapper.length) return;
  $calendar_wrapper.addClass('os-loading');

  let calendar_settings = new FormData(jQuery('form.os-calendar-settings-form')[0]);

  let data = new FormData();
  data.append('params', latepoint_formdata_to_url_encoded_string(calendar_settings));
  data.append('action', latepoint_helper.route_action);
  data.append('route_name', $calendar_wrapper.data('route'));
  data.append('return_format', 'json');

  jQuery.ajax({
    type: "post",
    dataType: "json",
    processData: false,
    contentType: false,
    url: latepoint_timestamped_ajaxurl(),
    data: data,
    success: function (response) {
      if (response.status === "success") {
        $calendar_wrapper.html(response.message).removeClass('os-loading');
        jQuery('.os-calendar-today-btn, .os-calendar-prev-btn, .os-calendar-next-btn').removeClass('os-loading');
        jQuery('.os-current-month-label .current-month').text(response.top_date_label);
        jQuery('.os-current-month-label .current-year').text(response.top_date_year);
        latepoint_check_horizontal_calendar_scroll();
      }
    }
  });

}