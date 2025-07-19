/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

function latepoint_process_updated() {
    location.reload();
}

function latepoint_process_action_removed($elem) {
    $elem.closest('.os-form-block').remove();
}

function latepoint_replace_process_condition_element($trigger, params, $target, callback = null) {
    $trigger.closest('.sub-section-content').addClass('os-loading');
    let route_name = $trigger.data('route');
    let data = {action: latepoint_helper.route_action, route_name: route_name, params: params, return_format: 'json'}
    jQuery.ajax({
        type: 'post',
        dataType: "json",
        url: latepoint_timestamped_ajaxurl(),
        data: data,
        success: (response) => {
            if (response.status === latepoint_helper.response_status.success) {
                $target.html(response.message);
                latepoint_init_process_conditions_form();
                $trigger.closest('.sub-section-content').removeClass('os-loading');
                if (typeof callback === 'function') {
                    callback();
                }
            } else {
                alert("Error!");
            }
        }
    });
}


function latepoint_init_process_forms() {
    latepoint_init_process_conditions_form();

    jQuery('.os-processes-w').on('latepoint:initProcessActionForm latepoint:initProcessActionTypeSettings', '.process-action-form', async function () {
        if (jQuery(this).find('.latepoint-whatsapp-templates-loader').length) {
            let $loader = jQuery(this).find('.latepoint-whatsapp-templates-loader');
            let $settings = jQuery(this).find('.process-action-settings');
            let $holder = jQuery(this).find('.latepoint-whatsapp-templates-holder');
            $settings.addClass('os-loading');

            let data = {
                action: latepoint_helper.route_action,
                route_name: $loader.data('route'),
                params: {
                    template_id: $loader.data('selected-template-id'),
                    action_id: $loader.data('process-action-id'),
                    process_id: $loader.data('process-id')
                },
                layout: 'none',
                return_format: 'json'
            }
            try {
                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                $settings.removeClass('os-loading');
                if (response.status === 'success') {
                    $loader.remove();
                    $holder.html(response.message);
                } else {
                    throw new Error(response.message);
                }
            } catch (e) {
                $settings.removeClass('os-loading');
                console.log(e);
                alert(e);
            }
        }
    });

    jQuery('.os-processes-w').on('click', '.os-run-process', function () {
        let $btn = jQuery(this);
        $btn.addClass('os-loading');
        let $process_form = $btn.closest('.os-process-form');
        // remove previously assigned class on other forms
        jQuery('.os-process-form.prepared-to-run').removeClass('prepared-to-run');
        // add class so we know which form is about to be processed
        $process_form.addClass('prepared-to-run');


        let form_data = new FormData($process_form[0]);
        form_data.set('process_event_type', $process_form.closest('.os-process-form').find('.process-event-type-selector').val());


        let data = new FormData();
        data.append('params', latepoint_formdata_to_url_encoded_string(form_data));
        data.append('action', latepoint_helper.route_action);
        data.append('route_name', $btn.data('route'));
        data.append('return_format', 'json');

        jQuery.ajax({
            type: "post",
            dataType: "json",
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: data,
            success: function (data) {
                latepoint_show_data_in_side_panel(data.message, 'width-600');
                latepoint_init_process_test_form();
                $btn.removeClass('os-loading');
            }
        });
        return false;
    });

    jQuery('.os-processes-w').find('.process-action-form').each(function (index) {
        latepoint_init_process_action_form(jQuery(this));
    });

    jQuery('.os-processes-w').on('click', '.pe-remove-condition', (event) => {
        if (jQuery(event.currentTarget).closest('.pe-conditions').find('.pe-condition').length > 1) {
            jQuery(event.currentTarget).closest('.pe-condition').remove();
        } else {
            alert('You need to have at least one condition if your custom field is set to be conditional.')
        }
        return false;
    });


    jQuery('.os-processes-w').on('change', 'select.process-condition-operator-selector', (event) => {
        let $select = jQuery(event.currentTarget);
        if ($select.val() == 'changed' || $select.val() == 'not_changed') {
            $select.closest('.pe-condition').find('.process-condition-values-w').hide();
        } else {
            $select.closest('.pe-condition').find('.process-condition-values-w').show();
        }
    });

    jQuery('.os-processes-w').on('change', 'select.process-event-type-selector', (event) => {
        let $select = jQuery(event.currentTarget);
        latepoint_replace_process_condition_element($select, {event_type: $select.val()}, $select.closest('.os-form-block').find('.process-event-condition-wrapper'));
    });

    jQuery('.os-processes-w').on('change', 'select.process-condition-object-selector', (event) => {
        let $select = jQuery(event.currentTarget);
        let $property_selector = $select.closest('.pe-condition').find('.process-condition-properties-w select');
        latepoint_replace_process_condition_element($select, {object_code: $select.val()}, $property_selector, () => {
            $property_selector.trigger('change');
        });
    });

    jQuery('.os-processes-w').on('change', 'select.process-condition-property-selector', (event) => {
        let $select = jQuery(event.currentTarget);
        let $operator_selector = $select.closest('.pe-condition').find('.process-condition-operators-w select');
        latepoint_replace_process_condition_element($select, {property: $select.val()}, $operator_selector, () => {
            $operator_selector.trigger('change');
        });
    });

    jQuery('.os-processes-w').on('change', 'select.process-condition-operator-selector', (event) => {
        let $select = jQuery(event.currentTarget);
        latepoint_replace_process_condition_element($select, {
            property: $select.closest('.pe-condition').find('select.process-condition-property-selector').val(),
            trigger_condition_id: $select.closest('.pe-condition').data('condition-id'),
            operator: $select.val()
        }, $select.closest('.pe-condition').find('.process-condition-values-w'));
    });

}

function latepoint_init_process_conditions_form() {
    jQuery('.os-late-select').lateSelect();
}

function latepoint_add_process_condition($btn, response) {
    $btn.closest('.pe-condition').after(response.message);
    latepoint_init_process_conditions_form();
}

function latepoint_init_added_process_action_form($trigger) {
    let $action_form = $trigger.prev('.process-action-form');
    $action_form.addClass('is-editing').trigger('latepoint:initProcessActionForm');
    latepoint_init_process_action_form($action_form);
}

function latepoint_init_process_test_form() {

    jQuery('.latepoint-run-process-btn').on('click', function () {
        let $btn = jQuery(this);
        if ($btn.hasClass('os-loading')) return false;
        $btn.addClass('os-loading');
        let $test_action_form = jQuery('.latepoint-side-panel-w .action-settings-wrapper');


        let form_data = new FormData(jQuery('.os-process-form.prepared-to-run')[0]);

        // set data sources
        jQuery('.process-test-data-source-selector').each(function () {
            form_data.set(jQuery(this).prop('name'), jQuery(this).val());
        });

        // set selected actions
        jQuery('.process-test-data-source-selector').each(function () {
            form_data.set(jQuery(this).prop('name'), jQuery(this).val());
        });

        let action_ids_to_run = [];
        jQuery('.action-to-run input[type="hidden"]').each(function () {
            if (jQuery(this).val() == 'on') action_ids_to_run.push(jQuery(this).closest('.action-to-run').data('id'));
        });
        form_data.set('action_ids', action_ids_to_run.join(','));


        let data = new FormData();
        data.append('params', latepoint_formdata_to_url_encoded_string(form_data));
        data.append('action', latepoint_helper.route_action);
        data.append('route_name', $btn.data('route'));
        data.append('return_format', 'json');

        jQuery.ajax({
            type: "post",
            dataType: "json",
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: data,
            success: function (data) {
                $btn.removeClass('os-loading');
                if (data.status == 'success') {
                    latepoint_add_notification(data.message);
                } else {
                    latepoint_add_notification(data.message, 'error');
                }
            }
        });
    });

    jQuery('.process-action-test-data-source-selector').on('change', function () {
        // TODO add call to server to check if selected data sources matches conditions of this process
    });
}


function latepoint_init_process_action_test_form() {

    latepoint_init_json_view(jQuery('.action-preview-wrapper.type-trigger_webhook pre'));

    jQuery('.latepoint-run-action-btn').on('click', function () {
        let $btn = jQuery(this);
        if ($btn.hasClass('os-loading')) return false;
        $btn.addClass('os-loading');
        let $test_action_form = jQuery('.latepoint-side-panel-w .action-settings-wrapper');

        let action_data = new FormData();


        action_data.append('params', $test_action_form.find('select, textarea, input').serialize());
        action_data.append('action', latepoint_helper.route_action);
        action_data.append('route_name', $btn.data('route'));
        action_data.append('return_format', 'json');

        jQuery.ajax({
            type: "post",
            dataType: "json",
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: action_data,
            success: function (data) {
                $btn.removeClass('os-loading');
                if (data.status == 'success') {
                    latepoint_add_notification(data.message);
                } else {
                    latepoint_add_notification(data.message, 'error');
                }
            }
        });
    });

    jQuery('.process-action-test-data-source-selector').on('change', function () {
        let $select = jQuery(this);
        jQuery('.action-preview-wrapper').addClass('os-loading');
        let $test_action_form = $select.closest('.action-settings-wrapper');

        let action_data = new FormData();


        action_data.append('params', $test_action_form.find('select, textarea, input').serialize());
        action_data.append('action', latepoint_helper.route_action);
        action_data.append('route_name', $select.data('route'));
        action_data.append('return_format', 'json');

        jQuery.ajax({
            type: "post",
            dataType: "json",
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: action_data,
            success: function (data) {
                jQuery('.action-preview-wrapper').html(data.message).removeClass('os-loading');
                latepoint_init_json_view(jQuery('.action-preview-wrapper.type-trigger_webhook pre'));
            }
        });
    });
}

function latepoint_init_process_action_form($action_form) {
    $action_form.on('click', '.os-run-process-action', function () {
        let $btn = jQuery(this);
        $btn.addClass('os-loading');
        let $action_form = $btn.closest('.process-action-form');

        if (window.tinyMCE !== undefined) window.tinyMCE.triggerSave();

        let action_data = new FormData();
        let params = latepoint_create_form_data_from_non_form_element($action_form);

        params.set('process_event_type', $action_form.closest('.os-process-form').find('.process-event-type-selector').val());

        action_data.append('params', latepoint_formdata_to_url_encoded_string(params));
        action_data.append('action', latepoint_helper.route_action);
        action_data.append('route_name', $btn.data('route'));
        action_data.append('return_format', 'json');

        jQuery.ajax({
            type: "post",
            dataType: "json",
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: action_data,
            success: function (data) {
                latepoint_show_data_in_side_panel(data.message, 'width-800');
                latepoint_init_process_action_test_form();
                $btn.removeClass('os-loading');
            }
        });
        return false;
    });
    $action_form.on('click', '.process-action-heading', function () {
        let $action_form = jQuery(this).closest('.process-action-form');
        if (!$action_form.hasClass('is-editing')) $action_form.trigger('latepoint:initProcessActionForm')
        $action_form.toggleClass('is-editing');
        return false;
    });

    $action_form.find('textarea.os-wp-editor-textarea').each(function (index) {
        latepoint_init_tiny_mce(jQuery(this).attr('id'));
    });
    $action_form.on('click', '.os-remove-process-action', function () {
        if (confirm(jQuery(this).data('os-prompt'))) {
            jQuery(this).closest('.process-action-form').remove();
        }
        return false;
    });

    $action_form.on('change', '.process-action-type-whatsapp-template-selector', async function () {
        let $select = jQuery(this);
        let $preview_holder = $select.closest('.process-action-settings').find('.latepoint-whatsapp-template-preview-holder');
        $preview_holder.addClass('os-loading');

        let data = {
            action: latepoint_helper.route_action,
            route_name: $select.data('route'),
            params: {
                template_id: $select.val(),
                action_id: $select.data('action-id')
            },
            layout: 'none',
            return_format: 'json'
        }
        try {
            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            $preview_holder.removeClass('os-loading');
            if (response.status === 'success') {
                $preview_holder.html(response.message);
            } else {
                throw new Error(response.message);
            }
        } catch (e) {
            $preview_holder.removeClass('os-loading');
            console.log(e);
            alert(e);
        }
        return false;
    });

    $action_form.on('change', '.process-action-type', function () {
        let $select = jQuery(this);
        jQuery(this).closest('.process-action-form').find('.process-action-name').text($select.find('option:selected').text());
        let action_type = $select.val();
        let action_id = $select.data('action-id');
        let route_name = $select.data('route');
        let $action_settings = $select.closest('.process-action-content').find('.process-action-settings');
        $action_settings.addClass('os-loading');
        let data = {
            action: latepoint_helper.route_action,
            route_name: route_name,
            params: {
                action_type: action_type,
                action_id: action_id
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
                $action_settings.html(data.message).removeClass('os-loading');
                let $action_form = $select.closest('.process-action-form');
                latepoint_init_input_masks($action_form);
                $action_form.trigger('latepoint:initProcessActionTypeSettings');
            }
        });
        return false;
    });
}