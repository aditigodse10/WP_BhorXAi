(function($) {
  'use strict';

  function wplc_activate_timer() {
    setInterval(wplc_check_reload, 5000);
  }

  $(function() {
    $('#show_all_pages').change(function() {
      if (this.checked) {
        $('.wplc_pages_scrollbox').addClass('wplc_box_disabled');
        $('.wplc_pages_scrollbox input').click(function() { return false; });
      } else {
        $('.wplc_pages_scrollbox').removeClass('wplc_box_disabled');
        $('.wplc_pages_scrollbox input').off('click');
      }
    });

    if ($('#show_all_pages').is(':checked')) {
      $('.wplc_pages_scrollbox input').click(function() { return false; });
    }

    if (ajax_object.activated==1) {
      wplc_activate_timer();
    }

    $('.wplc_subscribe_button').click(function() {
      $('.wplc_subscribe_box').hide();
      $('.wplc_activation_box').show();
      ajax_object.activated=1;
      wplc_check_reload(1);
      wplc_activate_timer();
    })

    $('#wplc_reset_config').click(function() {
      return confirm(ajax_object.msg_reset_config);
    });
  });

  function wplc_check_reload(val) {
    $.ajax({
			method : 'POST',
			dataType : 'json',
			url : ajax_object.ajax_url,
			data : {
				action : 'check_update',
        value: val
			}
		})
		.done(
			function( data ){
        if (data=='1'){
          document.location.reload();
        }
			}
		);    
  }

})(jQuery);