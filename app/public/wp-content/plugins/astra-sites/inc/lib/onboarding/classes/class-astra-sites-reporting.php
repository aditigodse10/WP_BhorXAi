<?php
/**
 * Reporting error
 *
 * @since 3.1.4
 * @package Astra Sites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reporting error
 */
class Astra_Sites_Reporting {

	/**
	 * Instance
	 *
	 * @since 4.0.0
	 * @access private
	 * @var self Class object.
	 */
    private static $instance = null;

    /**
     * Initiator
     *
     * @since 4.0.0
	 * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 3.1.4
     */
    public function __construct() {
        add_action( 'st_before_start_import_process', array( $this, 'schedule_reporting_event' ) );
        add_action( 'generate_analytics_lead', array( $this, 'send_analytics_lead' ) );
    }

    /**
     * Schedule the reporting of Error.
     *
     * @since 3.1.4
     * @return void
     */
    public function schedule_reporting_event() {
        $has_sent_error_report = get_option( 'astra_sites_has_sent_error_report', 'no' );
        if ( 'no' === $has_sent_error_report ) {
            // Schedule and event in next 3mins to send error report.
            wp_schedule_single_event( time() + 180, 'generate_analytics_lead' );
            update_option( 'astra_sites_has_sent_error_report', 'yes' );
        }
    }

    /**
     * Send Error.
     *
     * @since 3.1.4
     * @return void
     */
    public function send_analytics_lead() {
        $cached_errors = get_option( 'astra_sites_cached_import_error', false );

        if ( false === $cached_errors ) {
            return;
        }

        $id = ( isset( $cached_errors['id'] ) ) ? $cached_errors['id'] : 0;

        if ( $id === 0 ) {
            return;
        }

        $data = json_decode( $cached_errors['err'] );
        $report_data = array(
            'id' => $id,
            'import_attempts' => isset( $data->tryAgainCount ) ? absint( $data->tryAgainCount ) : 0,
            'import_status'   => 'false',
            'exit_intend'     => 'true',
            'type'            => isset( $cached_errors['type'] ) ? sanitize_text_field( $cached_errors['type'] ) : 'astra-sites',
            'page_builder'    => isset( $cached_errors['page_builder'] ) ? sanitize_text_field( $cached_errors['page_builder'] ) : '',
            'template_type'   => isset( $cached_errors['template_type'] ) ? sanitize_text_field( $cached_errors['template_type'] ) : '',
            'failure_reason'  => is_object( $data ) && isset( $data->primaryText ) ? sanitize_text_field( $data->primaryText ) : 'unknown',
            'secondary_text'  => is_object( $data ) && isset( $data->secondaryText ) ? sanitize_text_field( $data->secondaryText ) : '',
            'error_text'      => is_object( $data ) && isset( $data->errorText ) ? sanitize_text_field( $data->errorText ) : '',
        );

        $this->report( $report_data );

        update_option( 'astra_sites_has_sent_error_report', 'no' );
        delete_option( 'astra_sites_cached_import_error' );
    }

    /**
     * Report Error.
     * 
     * @param array<string, mixed> $data Error data.
     * @since 3.1.4
     * 
     * @return array<string, mixed>
     */
    public function report( $data ) {
        $id = isset( $data['id'] ) ? $data['id'] : 0;
        $import_attempts   = isset( $data['import_attempts'] ) ? absint( $data['import_attempts'] ) : 0;
        $import_status     = isset( $data['import_status'] ) ? sanitize_text_field( $data['import_status'] ) : 'true';
        $type              = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : 'astra-sites';
        $page_builder      = isset( $data['page_builder'] ) ? sanitize_text_field( $data['page_builder'] ) : 'gutenberg';
        $exit_intend       = isset( $data['exit_intend'] ) ? sanitize_text_field( $data['exit_intend'] ) : 'false';
        $user_agent_string = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $template_type     = isset( $data['template_type'] ) ? sanitize_text_field( $data['template_type'] ) : '';
        $failure_reason    = isset( $data['failure_reason'] ) ? sanitize_text_field( $data['failure_reason'] ) : '';
        $secondary_text    = isset( $data['secondary_text'] ) ? sanitize_text_field( $data['secondary_text'] ) : '';
        $error_text        = isset( $data['error_text'] ) ? sanitize_text_field( $data['error_text'] ) : '';

        // If secondary text is not empty, append it to failure reason.
        if ( ! empty( $secondary_text ) && is_string( $secondary_text ) ) {
            $failure_reason .= ' (' . $secondary_text . ')';
        }

        $api_args = array(
            'timeout'   => 60,
            'blocking'  => true,
            'body'      => array(
                'url'    => esc_url( site_url() ),
                'import_status'   => $import_status,
                'id'    => $id,
                'import_attempts' => $import_attempts,
                'version' => ASTRA_SITES_VER,
                'type' => $type,
                'builder' => $page_builder,
                'user_agent' => $user_agent_string,
                'exit_intend' => $exit_intend,
                'import_event_data' => array(
                    'plugin_type'    => defined( 'ASTRA_PRO_SITES_VER' ) ? 'premium' : 'free',
                    'template_type'  => $template_type,
                    'failure_reason' => $failure_reason,
                    'error_text'     => $error_text,
                ),
            ),
        );

        $request = wp_safe_remote_post( Astra_Sites::get_instance()->import_analytics_url, $api_args );

        if ( is_wp_error( $request ) ) {
            return array(
                'status' => false,
                'data' => $request,
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $request );
        $data = json_decode( wp_remote_retrieve_body( $request ), true );

        if ( 200 === $code ) {
            return array(
                'status' => true,
                'data' => $data,
            );
        }
        return array(
            'status' => false,
            'data' => $data,
        );
    }
}

Astra_Sites_Reporting::get_instance();
