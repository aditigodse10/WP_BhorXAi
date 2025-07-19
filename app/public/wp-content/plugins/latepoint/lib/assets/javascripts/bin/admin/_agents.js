
function latepoint_init_quick_agent_form(){
  let $agent_form_wrapper = jQuery('.quick-agent-form-w');
  latepoint_init_input_masks($agent_form_wrapper);


  $agent_form_wrapper.find('.agent-quick-edit-form').on('submit', function(e){
    if(jQuery(this).find('button[type="submit"]').hasClass('os-loading')) return false;
    e.preventDefault();
    latepoint_submit_quick_agent_form();
  });


  $agent_form_wrapper.find('.quick-agent-form-view-log-btn').on('click', function(){
    let $trigger_elem = jQuery(this);
    $trigger_elem.addClass('os-loading');
    let route = $trigger_elem.data('route');
    let data = { action: 'latepoint_route_call', route_name: route, params: {agent_id: $trigger_elem.data('agent-id')}, return_format: 'json' }
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        $trigger_elem.removeClass('os-loading');
        if(response.status === "success"){
          latepoint_display_in_side_sub_panel(response.message);
          jQuery('body').addClass('has-side-sub-panel');
        }else{
          alert(response.message, 'error');
        }
      }
    });
    return false;
  });
}