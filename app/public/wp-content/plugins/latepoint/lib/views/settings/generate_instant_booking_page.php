<?php
/**
 * @var $agents OsAgentModel[]
 * @var $services OsServiceModel[]
 * @var $locations OsLocationModel[]
 * @var $selected_agent_id string
 * @var $selected_service_id string
 * @var $selected_location_id string
 * @var $patterns array
 * @var $background_pattern string
 * @var $instant_booking_page_url string
 */
?>
<div class="instant-booking-preview-and-settings">
    <div class="instant-booking-preview-wrapper">
        <div class="instant-booking-preview-heading">
            <h2><?php _e( 'Instant Page Preview', 'latepoint' ); ?></h2>
            <div class="instant-booking-preview-settings-buttons">
                <a href="#" data-copy-url="<?php echo $instant_booking_page_url; ?>" class="latepoint-btn latepoint-btn-secondary instant-copy-url"><i class="latepoint-icon latepoint-icon-copy"></i><span><?php _e('Copy URL', 'latepoint'); ?></span></a>
                <a href="<?php echo $instant_booking_page_url; ?>" target="_blank" class="latepoint-btn latepoint-btn-primary instant-visit-url"><span><?php _e('Visit', 'latepoint'); ?></span><i class="latepoint-icon latepoint-icon-external-link"></i></a>
            </div>
        </div>
        <div class="instant-booking-settings-iframe-wrapper">
            <iframe class="instant-preview-iframe" src="<?php echo $instant_booking_page_url; ?>"></iframe>
        </div>
    </div>
    <div class="instant-booking-settings-wrapper">
        <div class="instant-booking-preview-heading">
            <h2><?php _e( 'Settings', 'latepoint' ); ?></h2>
            <a href="#" class="latepoint-instant-preview-close-trigger"><i class="latepoint-icon latepoint-icon-x"></i></a>
        </div>
        <div class="instant-booking-preview-settings-content" data-route-name="<?php echo esc_attr(OsRouterHelper::build_route_name('settings', 'generate_instant_booking_page_url')); ?>">
			<?php

            // Agents
			$agent_options = [ [ 'value' => LATEPOINT_ANY_AGENT, 'label' => __( 'Any Available Agent', 'latepoint' ) ] ];
            foreach ( $agents as $agent ) {
                $agent_options[] = [ 'value' => $agent->id, 'label' => $agent->full_name ];
            }
            $agent_options[] = [ 'value' => '', 'label' => __( 'Customer has to pick', 'latepoint' ) ];
            if ( empty( $selected_agent_id ) ) {
                $selected_agent_id = (count($agents) > 1) ? '' : LATEPOINT_ANY_AGENT;
            }
			echo OsFormHelper::select_field( 'instant_booking[selected_agent]', __( 'Selected Agent', 'latepoint' ), $agent_options, $selected_agent_id );

            // Locations
			$location_options = [ [ 'value' => LATEPOINT_ANY_LOCATION, 'label' => __( 'Any Available Location', 'latepoint' ) ] ];
            foreach ( $locations as $location ) {
                $location_options[] = [ 'value' => $location->id, 'label' => $location->name ];
            }
            $location_options[] = [ 'value' => '', 'label' => __( 'Customer has to pick', 'latepoint' ) ];
            if ( empty( $selected_location_id ) ) {
                $selected_location_id = (count($locations) > 1) ? '' : LATEPOINT_ANY_LOCATION;
            }
			echo OsFormHelper::select_field( 'instant_booking[selected_location]', __( 'Selected Location', 'latepoint' ), $location_options, $selected_location_id );

            // Services
			$service_options = [[ 'value' => '', 'label' => __( 'Customer has to pick', 'latepoint' ) ]];
            foreach ( $services as $service ) {
                $service_options[] = [ 'value' => $service->id, 'label' => $service->name ];
            }
            if ( empty( $selected_service_id ) ) {
                $selected_service_id = (count($services) > 1) ? '' : LATEPOINT_ANY_AGENT;
            }
			echo OsFormHelper::select_field( 'instant_booking[selected_service]', __( 'Selected Service', 'latepoint' ), $service_options, $selected_service_id );

            echo OsFormHelper::toggler_field('instant_booking[hide_side_panel]', __('Hide Left Panel', 'latepoint'), false, '', 'large');
            echo OsFormHelper::toggler_field('instant_booking[hide_summary]', __('Hide Summary Panel', 'latepoint'), false, '', 'large');
			?>
            <div class="preview-settings-label"><?php _e('Background', 'latepoint'); ?></div>
            <div class="preview-background-options">
                <div class="preview-background-option selected" data-pattern-key="default" style="background-image: radial-gradient(#eee 2px, transparent 0); background-size: 15px 15px; background-color: #fff;"></div>
                <?php foreach($patterns as $key => $pattern){
                    echo '<div class="preview-background-option" data-pattern-key="'.esc_attr($key).'" style="'.esc_attr($pattern).'"></div>';
                }
                echo OsFormHelper::hidden_field('instant_booking[background_pattern]', 'default');
                ?>
            </div>
        </div>
    </div>
</div>