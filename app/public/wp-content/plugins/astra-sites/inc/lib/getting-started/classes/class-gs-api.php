<?php
/**
 * Getting Started API
 *
 * @since 1.0.0
 * @package Getting Started API
 */

namespace GS\Classes;

use GS\Classes\GS_Helper;

/**
 * Class Getting Started API
 *
 * @since 1.0.0
 */
class GS_Api {

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return object initialized object of class.
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
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}



	/**
	 * Get api namespace
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_api_namespace() {
		return 'getting-started/v1';
	}

	/**
	 * Check whether a given request has permission to read notes.
	 *
	 * @param  object $request WP_REST_Request Full details about the request.
	 *
	 * @since 1.0.0
	 * @return object|boolean
	 */
	public function get_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'gt_rest_cannot_access',
				__( 'Sorry, you are not allowed to do that.', 'astra-sites' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Register route
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_route() {
		$namespace = $this->get_api_namespace();

		register_rest_route(
			$namespace,
			'/action-items/',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_action_items_content' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/dismiss-setup-wizard/',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'dismiss_setup_wizard' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => array(
						'dismiss' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/update-action-item-steps/',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_action_item_steps' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => array(
						'action_id' => array(
							'type'     => 'string',
							'required' => true,
						),
						'steps'    => array(
							'type' => 'array',
						),
					),
				),
			)
		);
	}

	/**
	 * Get items content.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function get_action_items_content( $request ) {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		// Verify the nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error(
				array(
					'data'   => __( 'Nonce verification failed.', 'astra-sites' ),
					'status' => false,

				)
			);
		}

		$default_action_items = GS_Helper::get_default_action_items();

		$action_items_status = get_option( 'getting_started_action_items', array() );

		if ( is_array( $action_items_status ) ) {
			$update_needed = false; // Flag to check if any dynamic step is completed.
			foreach ( $default_action_items as $key => $action_item ) {
				// Add step completion status if steps exist.
				if ( isset( $action_item['steps'] ) && is_array( $action_item['steps'] ) ) {
					foreach ( $action_item['steps'] as $step_key => $step ) {
						$db_step_completed = $action_items_status[ $action_item['id'] ]['steps'][ $step['id'] ];

						$step_completed = isset( $step['completed'] ) ? (bool) $step['completed'] : false;
						if ( ! $step_completed ) {
							$step_completed = $db_step_completed ?? false;
						}

						$default_action_items[ $key ]['steps'][ $step_key ]['completed']   = $step_completed;

						if ( $db_step_completed !== $step_completed ) {
							$action_items_status[ $action_item['id'] ]['steps'][ $step['id'] ] = $step_completed;
							$update_needed = true; // Set flag to true if any step completed dynamically.
						}
					}
				}
			}

			// Update the action items status if any step was completed dynamically.
			if ( $update_needed ) {
				update_option( 'getting_started_action_items', $action_items_status );
			}
		}

		if ( empty( $default_action_items ) || empty( $default_action_items[0] ) ) {
			wp_send_json_error(
				array(
					'data'   => array(),
					'status' => false,
					'error'  => __( 'Action items are empty', 'astra-sites' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'items'      => $default_action_items,
				'categories' => GS_Helper::get_action_items_categories(),
				'status'     => true,
			)
		);
	}

	/**
	 * Update items steps status.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function update_action_item_steps( $request ) {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		// Verify the nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nonce verification failed!', 'astra-sites' ),
					'status'  => false,

				)
			);
		}

		$action_id = $request->get_param( 'action_id' );
		$steps     = $request->get_param( 'steps' );

		// Return error if action item ID is not provided.
		if ( empty( $action_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Action item ID is required!', 'astra-sites' ),
					'status'  => false,
				)
			);
		}

		if ( empty( $steps ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Steps data are required!', 'astra-sites' ),
					'status'  => false,
				)
			);
		}

		if ( ! is_array( $steps ) ) {
			wp_send_json_error(
				array(
					'message' => __('Steps data are not in correct format!', 'astra-sites'),
					'status'  => false,
					'data'    => $steps,
				)
			);
		}

		$action_items = get_option( 'getting_started_action_items', array() );

		// If action items are empty, initialize with default action items.
		if ( empty( $action_items ) ) {
			$default_action_items = GS_Helper::get_default_action_items();
			foreach ( $default_action_items as $action_item ) {
				$action_items[ $action_item['id'] ]['status'] = false;
				// Initialize steps status if they exist.
				if ( isset( $action_item['steps'] ) && is_array( $action_item['steps'] ) ) {
					$action_items[ $action_item['id'] ]['steps'] = array();

					$all_steps_completed = true; // Assume all steps are completed initially.
					foreach ( $action_item['steps'] as $step ) {
						$step_completed = isset( $step['completed'] ) ? (bool) $step['completed'] : false;

						$action_items[ $action_item['id'] ]['steps'][ $step['id'] ] = $step_completed;

						if ( $all_steps_completed && ! $step_completed ) {
							$all_steps_completed = false; // If any step is not completed, set to false.
						}
					}

					// Update parent action item status based on steps completion.
					$action_items[ $action_item['id'] ]['status'] = $all_steps_completed;
				}
			}
		}

		if ( is_array( $action_items ) ) {
			// Initialize steps array if it doesn't exist.
			if ( ! isset( $action_items[ $action_id ]['steps'] ) ) {
				$action_items[ $action_id ]['steps'] = array();
			}

			$all_steps_completed = true;
			foreach ( $steps as $step ) {
				$step_id          = $step['id'] ?? '';
				$step_completed  = isset( $step['completed'] ) ? (bool) $step['completed'] : false;

				// If step ID is not provided, skip this step.
				if ( empty( $step_id ) ) {
					continue;
				}

				// Update the step status.
				$action_items[ $action_id ]['steps'][ $step_id ] = $step_completed;

				if ( $all_steps_completed && ! $step_completed ) {
					$all_steps_completed = false; // If any step is not completed, set to false.
				}
			}

			// Update parent action item status based on steps completion.
			$action_items[ $action_id ]['status'] = $all_steps_completed;

			update_option( 'getting_started_action_items', $action_items );

			wp_send_json_success(
				array(
					'status'  => true,
					'message' => __( 'Step status updated.', 'astra-sites' ),
					'data'    => $action_items,
				)
			);
		}

		wp_send_json_error(
			array(
				'status'  => false,
				'message' => __( 'Action items failed to update!', 'astra-sites' ),
			)
		);

	}

	/**
	 * Remove setup wizard.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function dismiss_setup_wizard( $request ) {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		// Verify the nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error(
				array(
					'data'   => __( 'Nonce verification failed.', 'astra-sites' ),
					'status' => false,

				)
			);
		}

		if ( empty( $request['dismiss'] ) || 'no' === $request['dismiss'] ) {
			wp_send_json_error(
				array(
					'status' => false,
					'data'   => __( ' Failed to dismiss and remove the Setup wizard.', 'astra-sites' ),
				)
			);
		}

		delete_option( 'getting_started_is_setup_wizard_showing' );
		wp_send_json_success(
			array(
				'status' => true,
				'data'   => __( 'Successfully dismissed and removed the Setup wizard.', 'astra-sites' ),
			)
		);
	}

}

GS_Api::get_instance();
