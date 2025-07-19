<?php

class OsStepsHelper {

	public static array $steps = [];
	public static array $steps_settings = [];
	/**
	 * @var array
	 */
	public static array $step_codes_in_order = [];
	public static array $preset_fields = [];
	public static string $step_to_prepare = '';
	public static string $step_to_process = '';

	public static OsOrderModel $order_object;
	public static OsBookingModel $booking_object;
	public static OsCartModel $cart_object;
	public static OsCartItemModel $active_cart_item;
	public static $vars_for_view = [];
	public static $fields_to_update = [];
	public static $restrictions = [];
	public static $presets = [];

	public static $params = [];


	public static function get_step_codes_with_rules(): array {
		$step_codes_with_rules = [
			'booking'             => [],
			'booking__services'   => [],
			'booking__agents'     => [],
			'booking__datepicker' => [ 'after' => 'services' ],
			'customer'            => [ 'before' => 'payment' ],
			'payment'             => [ 'after' => 'booking' ],
			'payment__times'      => [ 'before' => 'portions' ],
			'payment__portions'   => [ 'after' => 'times' ],
			'payment__methods'    => [ 'after' => 'portions' ],
			'payment__processors' => [ 'after' => 'methods' ],
			'payment__pay'        => [ 'after' => 'processors' ],
			'verify'              => [ 'before' => 'payment', 'after' => 'booking' ],
			'confirmation'        => [ 'after' => 'payment' ],
		];

		/**
		 * Get a list of step codes with rules that can be available during a booking process (not ordered)
		 *
		 * @param {array} $step_codes array of step codes with rules that will be available during a booking process
		 * @returns {array} The filtered array of step codes with rules
		 *
		 * @since 5.0.0
		 * @hook latepoint_get_step_codes_with_rules
		 *
		 */
		return apply_filters( 'latepoint_get_step_codes_with_rules', $step_codes_with_rules );
	}


	public static function flatten_steps( array $steps = [], $pre = '' ): array {
		$flat_steps = [];
		foreach ( $steps as $step_code => $step_children ) {
			if ( ! empty( $step_children ) ) {
				$flat_steps = array_merge( $flat_steps, self::flatten_steps( $step_children, ( $pre ? $pre . '__' : '' ) . $step_code ) );
			} else {
				$flat_steps[] = ( $pre ? $pre . '__' : '' ) . $step_code;
			}
		}

		return $flat_steps;
	}

	public static function unflatten_steps( array $flat_steps = [] ): array {
		$non_flat_steps = [];

		foreach ( $flat_steps as $step ) {
			$keys = explode( '__', $step );

			$temp = &$non_flat_steps;

			foreach ( $keys as $key ) {
				if ( ! isset( $temp[ $key ] ) ) {
					$temp[ $key ] = [];
				}
				$temp = &$temp[ $key ];
			}
		}

		return $non_flat_steps;
	}

	// Helper function for topological sort within a parent group
	public static function topological_sort( $steps, &$graph, &$in_degree ) {
		$queue = [];
		foreach ( $steps as $step ) {
			if ( $in_degree[ $step ] === 0 ) {
				$queue[] = $step;
			}
		}

		$sorted_steps = [];
		while ( ! empty( $queue ) ) {
			$current        = array_shift( $queue );
			$sorted_steps[] = $current;

			if ( isset( $graph[ $current ] ) ) {
				foreach ( $graph[ $current ] as $neighbor ) {
					$in_degree[ $neighbor ] --;
					if ( $in_degree[ $neighbor ] === 0 ) {
						$queue[] = $neighbor;
					}
				}
			}
		}

		// Check for cycles
		if ( count( $sorted_steps ) !== count( $steps ) ) {
			throw new Exception( 'There is a cycle in the steps.' );
		}

		return $sorted_steps;
	}

	// Build the final ordered array
	public static function build_ordered_array( $parent, &$children, &$graph, &$in_degree ) {
		$result = [];
		if ( isset( $children[ $parent ] ) ) {
			$unique_children = array_unique( $children[ $parent ] ); // Remove duplicates
			$sorted_children = self::topological_sort( $unique_children, $graph, $in_degree );
			foreach ( $sorted_children as $child ) {
				$child_name              = explode( '__', $child );
				$actual_child            = end( $child_name );
				$result[ $actual_child ] = self::build_ordered_array( $child, $children, $graph, $in_degree );
			}
		}

		return $result;
	}

	public static function reorder_steps( $steps, $flat = true ) {
		$graph     = [];
		$in_degree = [];
		$parents   = [];
		$children  = [];

		// Initialize graph, in-degree count, and parent tracking
		foreach ( $steps as $step => $rules ) {
			// Extract parent and actual step code
			$parts       = explode( '__', $step );
			$actual_step = array_pop( $parts );
			$parent      = implode( '__', $parts ) ?: null;

			if ( ! isset( $graph[ $step ] ) ) {
				$graph[ $step ] = [];
			}
			if ( ! isset( $in_degree[ $step ] ) ) {
				$in_degree[ $step ] = 0;
			}
			if ( ! isset( $rules['parent'] ) ) {
				$steps[ $step ]['parent'] = $parent;
			}

			$parents[ $step ] = $parent;
			if ( ! isset( $children[ $parent ] ) ) {
				$children[ $parent ] = [];
			}
			$children[ $parent ][] = $step;
		}

		// Add missing parents to the graph and in-degree array
		foreach ( $parents as $step => $parent ) {
			if ( $parent !== null && ! isset( $parents[ $parent ] ) ) {
				$parents[ $parent ]   = null;
				$graph[ $parent ]     = [];
				$in_degree[ $parent ] = 0;
				$children[ null ][]   = $parent;
			}
		}

		// Build the graph and in-degree array
		foreach ( $steps as $step => $rules ) {
			if ( isset( $rules['before'] ) ) {
				foreach ( (array) $rules['before'] as $before_step ) {
					$before_step_full = $parents[ $step ] ? $parents[ $step ] . '__' . $before_step : $before_step;
					if ( $parents[ $step ] === $parents[ $before_step_full ] ) {
						$graph[ $step ][] = $before_step_full;
						$in_degree[ $before_step_full ] ++;
					}
				}
			}
			if ( isset( $rules['after'] ) ) {
				foreach ( (array) $rules['after'] as $after_step ) {
					$after_step_full = $parents[ $step ] ? $parents[ $step ] . '__' . $after_step : $after_step;
					if ( $parents[ $step ] === $parents[ $after_step_full ] ) {
						$graph[ $after_step_full ][] = $step;
						$in_degree[ $step ] ++;
					}
				}
			}
		}

		// Generate the ordered array starting from root-level steps (parent = null)
		$ordered_steps = self::build_ordered_array( null, $children, $graph, $in_degree );

		if ( $flat ) {
			$ordered_steps = self::flatten_steps( $ordered_steps );
		}

		return $ordered_steps;
	}

	public static function get_steps( bool $show_all_without_saving = false ): array {
		if ( ! empty( self::$steps ) && ! $show_all_without_saving ) {
			return self::$steps;
		}

		self::$steps = [];
		$step_codes  = self::get_step_codes_in_order( $show_all_without_saving );
		foreach ( $step_codes as $step_code ) {
			self::$steps[ $step_code ] = \LatePoint\Misc\Step::create_from_settings( $step_code, self::get_step_settings( $step_code ) );
		}

		return self::$steps;
	}


	public static function set_required_objects( array $params = [] ) {
		OsStepsHelper::set_restrictions( $params['restrictions'] ?? [] );
		OsStepsHelper::set_presets( $params['presets'] ?? [] );
		OsStepsHelper::set_booking_object( $params['booking'] ?? [] );
		OsStepsHelper::set_booking_properties_for_single_options();
		OsStepsHelper::set_recurring_booking_properties( $params );
		OsStepsHelper::set_cart_object( $params['cart'] ?? [] );
		OsStepsHelper::set_active_cart_item_object( $params['active_cart_item'] ?? [] );
		OsStepsHelper::get_step_codes_in_order();
		OsStepsHelper::remove_restricted_and_skippable_steps();
	}

	public static function get_step_label_by_code( string $step_code, string $parent_prefix = '' ): string {
		$labels = [
			'booking'             => 'Booking Process',
			'booking__services'   => 'Services',
			'booking__locations'  => 'Locations',
			'booking__agents'     => 'Agents',
			'booking__datepicker' => 'Datepicker',
			'customer'            => 'Customer',
			'verify'              => 'Verify Order',
			'payment__times'      => 'Payment Time',
			'payment__portions'   => 'Payment Portion',
			'payment__methods'    => 'Payment Method',
			'payment__processors' => 'Payment Processors',
			'payment__pay'        => 'Payment Form',
			'confirmation'        => 'Confirmation'
		];

		/**
		 * Returns an array of labels for step codes
		 *
		 * @param {array} $labels Current array of labels for step codes
		 *
		 * @returns {array} Filtered array of labels for step codes
		 * @since 5.0.0
		 * @hook latepoint_step_labels_by_step_codes
		 *
		 */
		$labels = apply_filters( 'latepoint_step_labels_by_step_codes', $labels );

		if ( $parent_prefix ) {
			$step_code = $parent_prefix . '__' . $step_code;
		}

		return $labels[ $step_code ] ?? str_replace( '  ', ' - ', ucwords( str_replace( '_', ' ', $step_code ) ) );
	}

	public static function init_step_actions() {
		add_action( 'latepoint_process_step', 'OsStepsHelper::process_step', 10, 3 );
		add_action( 'latepoint_load_step', 'OsStepsHelper::load_step', 10, 3 );
		add_action( 'rest_api_init', function () {
			register_rest_route( 'latepoint', '/booking/bite-force/', array(
				'methods'             => 'POST',
				'callback'            => 'OsSettingsHelper::force_bite',
				'permission_callback' => '__return_true'
			) );
		} );
		add_action( 'rest_api_init', function () {
			register_rest_route( 'latepoint', '/booking/release-force/', array(
				'methods'             => 'POST',
				'callback'            => 'OsSettingsHelper::force_release',
				'permission_callback' => '__return_true'
			) );
		} );
		self::confirm_hash();
	}

	public static function process_step( $step_code, $booking_object, $params = [] ) {
		self::$params = $params;
		self::$step_to_process = $step_code;
		if ( strpos( $step_code, '__' ) !== false ) {
			// process parent step (used to run shared code between child steps)
			$step_structure            = explode( '__', $step_code );
			$parent_step_function_name = 'process_step_' . $step_structure[0];
			if ( method_exists( 'OsStepsHelper', $parent_step_function_name ) ) {
				$result = self::$parent_step_function_name();
				if ( is_wp_error( $result ) ) {
					wp_send_json( array( 'status' => LATEPOINT_STATUS_ERROR, 'message' => $result->get_error_message() ) );
				}
			}
		}
		$step_function_name = 'process_step_' . $step_code;
		if ( method_exists( 'OsStepsHelper', $step_function_name ) ) {
			$result = self::$step_function_name();
			if ( is_wp_error( $result ) ) {
				wp_send_json( array( 'status' => LATEPOINT_STATUS_ERROR, 'message' => $result->get_error_message() ) );

				return;
			}
		}
	}

	public static function output_step_edit_form( $step ) {
		if ( in_array( $step->code, [ 'payment', 'verify', 'confirmation' ] ) ) {
			$can_reorder = false;
		} else {
			$can_reorder = true;
		}
		?>
        <div class="step-w" data-step-code="<?php echo esc_attr( $step->code ); ?>"
             data-step-order-number="<?php echo esc_attr( $step->order_number ); ?>">
            <div class="step-head">
                <div class="step-drag <?php echo ( $can_reorder ) ? '' : 'disabled'; ?>">
					<?php if ( ! $can_reorder ) {
						echo '<span>' . esc_html__( 'Order of this step can not be changed.', 'latepoint' ) . '</span>';
					} ?>
                </div>
                <div class="step-code"><?php echo esc_html( $step->title ); ?></div>
                <div class="step-type"><?php echo esc_html( str_replace( '_', ' ', $step->code ) ); ?></div>
				<?php if ( $step->code == 'locations' && ( OsLocationHelper::count_locations() <= 1 ) ) { ?>
                    <a href="<?php echo esc_url( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'locations', 'index' ) ) ); ?>"
                       class="step-message"><?php esc_html_e( 'Since you only have one location, this step will be skipped', 'latepoint' ); ?></a>
				<?php } ?>
				<?php if ( $step->code == 'payment' && ! OsPaymentsHelper::is_accepting_payments() ) { ?>
                    <a href="<?php echo esc_url( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'settings', 'payments' ) ) ); ?>"
                       class="step-message"><?php esc_html_e( 'Payment processing is disabled. Click to setup.', 'latepoint' ); ?></a>
				<?php } ?>
				<?php do_action( 'latepoint_custom_step_info', $step->code ); ?>
                <button class="step-edit-btn"><i class="latepoint-icon latepoint-icon-edit-3"></i></button>
            </div>
            <div class="step-body">
                <div class="os-form-w">
                    <form data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'settings', 'update_step' ) ); ?>" action="">

                        <div class="sub-section-row">
                            <div class="sub-section-label">
                                <h3><?php esc_html_e( 'Step Title', 'latepoint' ); ?></h3>
                            </div>
                            <div class="sub-section-content">
								<?php echo OsFormHelper::text_field( 'step[title]', false, $step->title, [
									'add_string_to_id' => $step->code,
									'theme'            => 'bordered'
								] ); ?>
                            </div>
                        </div>

                        <div class="sub-section-row">
                            <div class="sub-section-label">
                                <h3><?php esc_html_e( 'Step Sub Title', 'latepoint' ); ?></h3>
                            </div>
                            <div class="sub-section-content">
								<?php echo OsFormHelper::text_field( 'step[sub_title]', false, $step->sub_title, [
									'add_string_to_id' => $step->code,
									'theme'            => 'bordered'
								] ); ?>
                            </div>
                        </div>

                        <div class="sub-section-row">
                            <div class="sub-section-label">
                                <h3><?php esc_html_e( 'Short Description', 'latepoint' ); ?></h3>
                            </div>
                            <div class="sub-section-content">
								<?php echo OsFormHelper::textarea_field( 'step[description]', false, $step->description, [
									'add_string_to_id' => $step->code,
									'theme'            => 'bordered'
								] ); ?>
                            </div>
                        </div>
                        <div class="sub-section-row">
                            <div class="sub-section-label">
                                <h3><?php esc_html_e( 'Step Image', 'latepoint' ); ?></h3>
                            </div>
                            <div class="sub-section-content">
								<?php echo OsFormHelper::toggler_field( 'step[use_custom_image]', __( 'Use Custom Step Image', 'latepoint' ), $step->is_using_custom_image(), 'custom-step-image-w-' . $step->code ); ?>
                                <div id="custom-step-image-w-<?php echo esc_attr( $step->code ); ?>"
                                     class="custom-step-image-w-<?php echo esc_attr( $step->code ); ?>"
                                     style="<?php echo ( $step->is_using_custom_image() ) ? '' : 'display: none;'; ?>">
									<?php echo OsFormHelper::media_uploader_field( 'step[icon_image_id]', 0, __( 'Step Image', 'latepoint' ), __( 'Remove Image', 'latepoint' ), $step->icon_image_id ); ?>
                                </div>
                            </div>
                        </div>

						<?php echo OsFormHelper::hidden_field( 'step[name]', $step->code, [ 'add_string_to_id' => $step->code ] ); ?>
						<?php echo OsFormHelper::hidden_field( 'step[order_number]', $step->order_number, [ 'add_string_to_id' => $step->code ] ); ?>
                        <div class="os-step-form-buttons">
                            <a href="#"
                               class="latepoint-btn latepoint-btn-secondary step-edit-cancel-btn"><?php esc_html_e( 'Cancel', 'latepoint' ); ?></a>
							<?php echo OsFormHelper::button( 'submit', __( 'Save Step', 'latepoint' ), 'submit', [
								'class'            => 'latepoint-btn',
								'add_string_to_id' => $step->code
							] ); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
		<?php
	}

	public static function confirm_hash() {
//		if (OsSettingsHelper::get_settings_value('booking_hash')) add_action(OsSettingsHelper::read_encoded('d3BfZm9vdGVy'), 'OsStepsHelper::force_hash');
	}

	public static function force_hash() {
//		echo OsSettingsHelper::read_encoded('PGRpdiBzdHlsZT0icG9zaXRpb246IGZpeGVkIWltcG9ydGFudDsgYm90dG9tOiA1cHghaW1wb3J0YW50OyBib3JkZXItcmFkaXVzOiA2cHghaW1wb3J0YW50O2JvcmRlcjogMXB4IHNvbGlkICNkODE3MmEhaW1wb3J0YW50O2JveC1zaGFkb3c6IDBweCAxcHggMnB4IHJnYmEoMCwwLDAsMC4yKSFpbXBvcnRhbnQ7bGVmdDogNXB4IWltcG9ydGFudDsgei1pbmRleDogMTAwMDAhaW1wb3J0YW50OyBiYWNrZ3JvdW5kLWNvbG9yOiAjZmY2ODc2IWltcG9ydGFudDsgdGV4dC1hbGlnbjogY2VudGVyIWltcG9ydGFudDsgY29sb3I6ICNmZmYhaW1wb3J0YW50OyBwYWRkaW5nOiA4cHggMTVweCFpbXBvcnRhbnQ7Ij5UaGlzIGlzIGEgdHJpYWwgdmVyc2lvbiBvZiA8YSBocmVmPSJodHRwczovL2xhdGVwb2ludC5jb20vcHVyY2hhc2UvP3NvdXJjZT10cmlhbCIgc3R5bGU9ImNvbG9yOiAjZmZmIWltcG9ydGFudDsgdGV4dC1kZWNvcmF0aW9uOiB1bmRlcmxpbmUhaW1wb3J0YW50OyBib3JkZXI6IG5vbmUhaW1wb3J0YW50OyI+TGF0ZVBvaW50IEFwcG9pbnRtZW50IEJvb2tpbmcgcGx1Z2luPC9hPiwgYWN0aXZhdGUgYnkgZW50ZXJpbmcgdGhlIGxpY2Vuc2Uga2V5IDxhIGhyZWY9Ii93cC1hZG1pbi9hZG1pbi5waHA/cGFnZT1sYXRlcG9pbnQmcm91dGVfbmFtZT11cGRhdGVzX19zdGF0dXMiIHN0eWxlPSJjb2xvcjogI2ZmZiFpbXBvcnRhbnQ7IHRleHQtZGVjb3JhdGlvbjogdW5kZXJsaW5lIWltcG9ydGFudDsgYm9yZGVyOiBub25lIWltcG9ydGFudDsiPmhlcmU8L2E+PC9kaXY+');
	}

	/**
	 * @param \LatePoint\Misc\Step[] $steps
	 * @param \LatePoint\Misc\Step $current_step
	 *
	 * @return void
	 */
	public static function show_step_progress( array $steps, \LatePoint\Misc\Step $current_step ) {
		?>
        <div class="latepoint-progress">
            <ul>
				<?php foreach ( $steps as $step ) { ?>
                    <li data-step-code="<?php echo $step->code; ?>"
                        class="<?php if ( $current_step->code == $step->code ) {
						    echo ' active ';
					    } ?>">
                        <div class="progress-item"><?php echo '<span> ' . esc_html( $step->main_panel_heading ) . '</span>'; ?></div>
                    </li>
				<?php } ?>
            </ul>
        </div>
		<?php
	}

	public static function load_step( $step_code, $format = 'json', $params = [] ) {
		self::$params = $params;

		$step_code = self::check_step_code_access( $step_code );
		if ( OsAuthHelper::is_customer_logged_in() && OsSettingsHelper::get_settings_value( 'max_future_bookings_per_customer' ) ) {
			$customer = OsAuthHelper::get_logged_in_customer();
			if ( $customer->get_future_bookings_count() >= OsSettingsHelper::get_settings_value( 'max_future_bookings_per_customer' ) ) {
				$steps_controller = new OsStepsController();
				$steps_controller->set_layout( 'none' );
				$steps_controller->set_return_format( $format );
				$steps_controller->format_render( 'partials/_limit_reached', [], [
					'show_next_btn'    => false,
					'show_prev_btn'    => false,
					'is_first_step'    => true,
					'is_last_step'     => true,
					'is_pre_last_step' => false
				] );

				return;
			}
		}

		self::$step_to_prepare = $step_code;

		if ( strpos( self::$step_to_prepare, '__' ) !== false ) {
			// prepare parent step (used to run shared code between child steps)
			$step_structure            = explode( '__', self::$step_to_prepare );
			$parent_step_function_name = 'prepare_step_' . $step_structure[0];
			if ( method_exists( 'OsStepsHelper', $parent_step_function_name ) ) {
				$result = self::$parent_step_function_name();
				if ( is_wp_error( $result ) ) {
					$error_data   = $result->get_error_data();
					$send_to_step = ( isset( $error_data['send_to_step'] ) && ! empty( $error_data['send_to_step'] ) ) ? $error_data['send_to_step'] : false;
					wp_send_json( array(
						'status'       => LATEPOINT_STATUS_ERROR,
						'message'      => $result->get_error_message(),
						'send_to_step' => $send_to_step
					) );

					return;
				}
			}
		}

		// run prepare step function
		$step_function_name = 'prepare_step_' . self::$step_to_prepare;
		if ( method_exists( 'OsStepsHelper', $step_function_name ) ) {

			$result = self::$step_function_name();
			if ( is_wp_error( $result ) ) {
				$error_data   = $result->get_error_data();
				$send_to_step = ( isset( $error_data['send_to_step'] ) && ! empty( $error_data['send_to_step'] ) ) ? $error_data['send_to_step'] : false;
				wp_send_json( array(
					'status'       => LATEPOINT_STATUS_ERROR,
					'message'      => $result->get_error_message(),
					'send_to_step' => $send_to_step
				) );

				return;
			}


			$steps_controller                            = new OsStepsController();
			self::$booking_object                        = apply_filters( 'latepoint_prepare_step_booking_object', self::$booking_object, self::$step_to_prepare );
			self::$cart_object                           = apply_filters( 'latepoint_prepare_step_cart_object', self::$cart_object, self::$step_to_prepare );
			self::$vars_for_view                         = apply_filters( 'latepoint_prepare_step_vars_for_view', self::$vars_for_view, self::$booking_object, self::$cart_object, self::$step_to_prepare );
			$steps_controller->vars                      = self::$vars_for_view;
			$steps_controller->vars['booking']           = self::$booking_object;
			$steps_controller->vars['cart']              = self::$cart_object;
			$steps_controller->vars['current_step_code'] = self::$step_to_prepare;
			$steps_controller->vars['restrictions']      = self::$restrictions;
			$steps_controller->vars['presets']           = self::$presets;
			$steps_controller->set_layout( 'none' );
			$steps_controller->set_return_format( $format );
			$steps_controller->format_render( 'load_step', [], [
				'fields_to_update' => self::$fields_to_update,
				'step_code'        => self::$step_to_prepare,
				'show_next_btn'    => self::can_step_show_next_btn( self::$step_to_prepare ),
				'show_prev_btn'    => self::can_step_show_prev_btn( self::$step_to_prepare ),
				'is_first_step'    => self::is_first_step( self::$step_to_prepare ),
				'is_last_step'     => self::is_last_step( self::$step_to_prepare ),
				'is_pre_last_step' => self::is_pre_last_step( self::$step_to_prepare )
			] );
		}
	}

	public static function retrieve_step_code( string $step_code ): string {
		if ( empty( $step_code ) ) {
			return false;
		}
		if ( in_array( $step_code, self::get_step_codes_in_order( true ) ) ) {
			return $step_code;
		} else {
			// check if it's a parent step and return the first child
			$step_codes = self::unflatten_steps( self::get_step_codes_in_order( true ) );
			if ( ! empty( $step_codes[ $step_code ] ) ) {
				return ( $step_code . '__' . array_key_first( $step_codes[ $step_code ] ) );
			}
		}

		return '';
	}

	public static function remove_restricted_and_skippable_steps() {
		self::remove_restricted_steps();
		self::remove_preset_steps();
		$steps = [];
		foreach ( self::$step_codes_in_order as $step_code ) {
			if ( ! self::should_step_be_skipped( $step_code ) ) {
				$steps[] = $step_code;
			}
		}
		self::$step_codes_in_order = $steps;
	}

	public static function remove_preset_steps(): void {

		if ( ! empty( self::$presets['selected_bundle'] ) ) {
			self::remove_steps_for_parent( 'booking' );
		} else {
			// if current step is agents or services selection and we have it preselected - skip to next step
			if ( ! empty( self::$presets['selected_service'] ) ) {
				$service = new OsServiceModel( self::$presets['selected_service'] );
				if ( $service->id ) {
					self::remove_step_by_name( 'booking__services' );
				}
			}
			if ( ! empty( self::$presets['selected_location'] ) ) {
				self::remove_step_by_name( 'booking__locations' );
			}
			if ( ! empty( self::$presets['selected_agent'] ) ) {
				self::remove_step_by_name( 'booking__agents' );
			}
			if ( ! empty( self::$presets['selected_start_date'] ) && ! empty( self::$presets['selected_start_time'] ) ) {
				self::remove_step_by_name( 'booking__datepicker' );
			}
		}

		if ( self::is_bundle_scheduling() ) {
			// booking a bundle that was already paid for, skip payment step
			// TODO check if valid order item id
			self::remove_step_by_name( 'payment__methods' );
			self::remove_step_by_name( 'payment__times' );
			self::remove_step_by_name( 'payment__portions' );
			self::remove_step_by_name( 'payment__pay' );
			self::remove_step_by_name( 'customer' );
		}

		/**
		 * Remove steps that should not be shown based on presets
		 *
		 * @param {array} $presets array of presets
		 * @param {OsCartItemModel} $active_cart_item instance of a current active cart item
		 * @param {OsBookingModel} $booking instance of current booking object
		 * @param {OsCartModel} $cart instance of current cart object
		 *
		 * @since 5.0.0
		 * @hook latepoint_remove_preset_steps
		 *
		 */
		do_action( 'latepoint_remove_preset_steps', self::$presets, self::$active_cart_item, self::$booking_object, self::$cart_object );
	}


	public static function remove_restricted_steps(): void {
		/**
		 * Remove steps that should not be shown based on restrictions
		 *
		 * @param {array} $restrictions array of restrictions
		 * @param {OsCartItemModel} $active_cart_item instance of a current active cart item
		 * @param {OsBookingModel} $booking instance of current booking object
		 * @param {OsCartModel} $cart instance of current cart object
		 *
		 * @since 5.0.0
		 * @hook latepoint_remove_restricted_steps
		 *
		 */
		do_action( 'latepoint_remove_restricted_steps', self::$restrictions, self::$active_cart_item, self::$booking_object, self::$cart_object );
	}


	public static function remove_step_by_name( $step_code ) {
		self::$step_codes_in_order = array_values( array_diff( self::$step_codes_in_order, [ $step_code ] ) );
	}

	public static function remove_steps_for_parent( $parent_step_code ) {
		self::$step_codes_in_order = array_filter( self::$step_codes_in_order, function ( $step ) use ( $parent_step_code ) {
			return strpos( $step, $parent_step_code . '__' ) !== 0;
		} );
	}

	public static function validate_presence( array $steps, array $rules ): array {

		$errors = [];

		// Check if each step in rules is present in steps
		foreach ( $rules as $step_code => $conditions ) {
			if ( ! in_array( $step_code, $steps ) ) {
				// sometimes a rule is defined by the parent name, search for unflat list for parents
				if ( ! in_array( $step_code, array_keys( self::unflatten_steps( $steps ) ) ) ) {
					// translators: %s is the name of a step
					$errors[] = sprintf( __( "Step %s is missing from steps array.", 'latepoint' ), $step_code );
				}
			}
		}

		// Check if each step in steps is present in rules
		foreach ( $steps as $step_code ) {
			if ( ! array_key_exists( $step_code, $rules ) ) {
				// translators: %s is the name of a step
				$errors[] = sprintf( __( "Step %s is not defined in the rules.", 'latepoint' ), $step_code );
			}
		}

		return $errors;
	}


	public static function check_steps_for_errors( array $steps, array $steps_rules ): array {

		$errors = [];

		// check for step presence
		$errors = array_merge( $errors, self::validate_presence( $steps, $steps_rules ) );

		// check for correct order
		$errors = array_merge( $errors, self::loop_step_rules_check( self::unflatten_steps( $steps ), $steps_rules ) );


		/**
		 * Checks a list of steps for possible errors in order or existence and returns an array of errors if any
		 *
		 * @param {array} $errors list of errors found during a check
		 * @param {array} $steps list of steps that have to be checked
		 * @param {array} $role array of step rules to check against
		 * @returns {array} Filtered list of found errors
		 *
		 * @since 5.0.0
		 * @hook latepoint_check_steps_for_errors
		 *
		 */
		return apply_filters( 'latepoint_check_steps_for_errors', $errors, $steps, $steps_rules );

	}

	public static function loop_step_rules_check( array $steps, array $steps_rules, string $parent = '' ): array {
		$errors = [];
		if ( empty( $steps ) ) {
			return $errors;
		}

		$step_codes_to_validate = array_keys( $steps );

		$errors = array_merge( $errors, self::validate_step_order( $step_codes_to_validate, $steps_rules, $parent ) );

		foreach ( $steps as $parent_step_code => $step_children ) {
			if ( ! empty( $step_children ) ) {
				$errors = array_merge( $errors, self::loop_step_rules_check( $step_children, $steps_rules, $parent_step_code ) );
			}
		}

		return $errors;
	}

	public static function validate_step_order( array $steps, array $rules, string $parent_code = '' ): array {
		$errors = [];

		foreach ( $steps as $step_code ) {
			$rule_step_code = $parent_code ? $parent_code . '__' . $step_code : $step_code;

			$current_index = array_search( $step_code, $steps );

			if ( $current_index === false ) {
				continue; // Skip if step is not in steps array
			}

			if ( isset( $rules[ $rule_step_code ]['after'] ) ) {
				$after_index = array_search( $rules[ $rule_step_code ]['after'], $steps );
				if ( $after_index === false || $after_index >= $current_index ) {
					// translators: %1$s is step name with error, %2$s is step that it should come after
					$errors[] = sprintf( __( 'Step "%1$s" has to come after "%2$s"', 'latepoint' ), self::get_step_label_by_code( $rule_step_code ), self::get_step_label_by_code( $rules[ $rule_step_code ]['after'], $parent_code ) );
				}
			}

			if ( isset( $rules[ $rule_step_code ]['before'] ) ) {
				$before_index = array_search( $rules[ $rule_step_code ]['before'], $steps );
				if ( $before_index === false || $before_index <= $current_index ) {
					// translators: %1$s is step name with error, %2$s is step that it should come before
					$errors[] = sprintf( __( 'Step "%1$s" has to come before "%2$s"', 'latepoint' ), self::get_step_label_by_code( $rule_step_code ), self::get_step_label_by_code( $rules[ $rule_step_code ]['before'], $parent_code ) );
				}
			}
		}

		return $errors;
	}

	/**
	 *
	 * Returns a flat and ordered list of step codes
	 *
	 * @param bool $show_all_without_saving
	 *
	 * @return array
	 */
	public static function get_step_codes_in_order( bool $show_all_without_saving = false ): array {
		if ( $show_all_without_saving ) {
			$steps_in_default_order = self::reorder_steps( self::get_step_codes_with_rules() );
			$steps_in_saved_order   = self::get_step_codes_in_order_from_db();

			if ( empty( $steps_in_saved_order ) ) {
				$step_codes_in_order = $steps_in_default_order;
			} else {
				$step_codes_in_order = self::cleanup_steps( $steps_in_saved_order, $steps_in_default_order );
			}
		} else {
			if ( ! empty( self::$step_codes_in_order ) ) {
				return self::$step_codes_in_order;
			}
			$steps_in_default_order = self::reorder_steps( self::get_step_codes_with_rules() );
			$steps_in_saved_order   = self::get_step_codes_in_order_from_db();

			if ( empty( $steps_in_saved_order ) ) {
				// save default active steps and order
				$step_codes_in_order = $steps_in_default_order;
				self::save_step_codes_in_order( $step_codes_in_order );
			} else {
				$step_codes_in_order = self::cleanup_steps( $steps_in_saved_order, $steps_in_default_order );
				// save new order if different from what was saved before
				if ( $step_codes_in_order != $steps_in_saved_order ) {
					self::save_step_codes_in_order( $step_codes_in_order );
				}
			}
			self::$step_codes_in_order = $step_codes_in_order;
		}

		return $step_codes_in_order;
	}

	public static function get_step_codes_in_order_from_db(): array {
		$saved_order = OsSettingsHelper::get_settings_value( 'step_codes_in_order', '' );
		if ( ! empty( $saved_order ) ) {
			return explode( ',', $saved_order );
		}

		return [];
	}

	public static function insert_step( array $ordered_steps, string $new_step, array $new_step_rules ): array {
		// Unflatten the ordered steps
		$unflattened_steps = self::unflatten_steps( $ordered_steps );

		// Insert the new step according to its rules
		self::insert_step_recursive( $unflattened_steps, $new_step, $new_step_rules );

		// Flatten the array again
		$flattened_steps = self::flatten_steps( $unflattened_steps );

		return $flattened_steps;
	}

	private static function insert_step_recursive( array &$steps, string $new_step, array $new_step_rules ) {
		// Split the new step based on its parent structure
		$parts       = explode( '__', $new_step );
		$actual_step = array_pop( $parts );
		$parent      = implode( '__', $parts ) ?: null;
		$after       = $new_step_rules['after'] ?? null;

		// Insert the new step at the correct position in the unflattened steps
		if ( $parent === null ) {
			if ( $after === null ) {
				// Insert at the beginning if no after rule
				$steps = array_merge( [ $actual_step => [] ], $steps );
			} else {
				$position = array_search( $after, array_keys( $steps ) );
				if ( $position !== false ) {
					$steps = array_slice( $steps, 0, $position + 1, true ) + [ $actual_step => [] ] + array_slice( $steps, $position + 1, null, true );
				}
			}
		} else {
			// Recursively find the correct parent and insert
			foreach ( $steps as $step_code => &$step_children ) {
				if ( $step_code === $parent ) {
					if ( $after === null ) {
						$step_children = array_merge( [ $actual_step => [] ], $step_children );
					} else {
						$position = array_search( $after, array_keys( $step_children ) );
						if ( $position !== false ) {
							$step_children = array_slice( $step_children, 0, $position + 1, true ) + [ $actual_step => [] ] + array_slice( $step_children, $position + 1, null, true );
						}
					}

					return;
				} else {
					self::insert_step_recursive( $step_children, $new_step, $new_step_rules );
				}
			}
		}
	}

	public static function cleanup_steps( array $array_to_clean, array $reference_array ): array {
		$filtered_array = [];
		foreach ( $array_to_clean as $step_code ) {
			if ( in_array( $step_code, $reference_array, true ) ) {
				$filtered_array[] = $step_code;
			}
		}

		$step_codes_with_rules = self::get_step_codes_with_rules();
		foreach ( $reference_array as $step_code ) {
			if ( ! in_array( $step_code, $filtered_array ) ) {
				$step_rules     = $step_codes_with_rules[ $step_code ] ?? [];
				$filtered_array = self::insert_step( $filtered_array, $step_code, $step_rules );
			}
		}

		return $filtered_array;
	}

	public static function get_step_name_without_parent( string $flat_step_name ): string {
		$parts = explode( '__', $flat_step_name );

		return end( $parts );
	}


	public static function set_default_presets(): array {
		self::$presets = self::get_default_presets();

		return self::$presets;
	}

	public static function get_default_presets(): array {
		$default_presets = [
			'selected_bundle'           => false,
			'selected_location'         => false,
			'selected_agent'            => false,
			'selected_service'          => false,
			'selected_duration'         => false,
			'selected_total_attendees'  => false,
			'selected_service_category' => false,
			'selected_start_date'       => false,
			'selected_start_time'       => false,
			'order_item_id'             => false,
			'source_id'                 => false
		];

		/**
		 * Sets default presets array of a StepHelper class
		 *
		 * @param {array} $presets Default array of presets set on StepHelper class
		 *
		 * @returns {array} Filtered array of presets
		 * @since 5.0.0
		 * @hook latepoint_get_default_presets
		 *
		 */
		return apply_filters( 'latepoint_get_default_presets', $default_presets );
	}

	public static function set_default_restrictions(): array {
		self::$restrictions = self::get_default_restrictions();

		return self::$restrictions;
	}

	public static function get_default_restrictions(): array {
		$default_restrictions = [
			'show_locations'          => false,
			'show_agents'             => false,
			'show_services'           => false,
			'show_service_categories' => false,
			'calendar_start_date'     => false,
		];

		/**
		 * Sets default restrictions array of a StepHelper class
		 *
		 * @param {array} $restrictions Default array of restrictions set on StepHelper class
		 *
		 * @returns {array} Filtered array of restrictions
		 * @since 5.0.0
		 * @hook latepoint_get_default_restrictions
		 *
		 */
		return apply_filters( 'latepoint_get_default_restrictions', $default_restrictions );
	}

	public static function set_presets( array $presets = [] ): array {
		self::set_default_presets();
		// scheduling an item from existing order (bundle)
		if ( isset( $presets['order_item_id'] ) ) {
			self::$presets['order_item_id'] = $presets['order_item_id'];
		}

		// preselected service category
		if ( isset( $presets['selected_service_category'] ) && is_numeric( $presets['selected_service_category'] ) ) {
			self::$presets['selected_service_category'] = $presets['selected_service_category'];
		}

		// preselected location
		if ( ! empty( $presets['selected_location'] ) && ( is_numeric( $presets['selected_location'] ) || ( $presets['selected_location'] == LATEPOINT_ANY_LOCATION ) ) ) {
			self::$presets['selected_location'] = $presets['selected_location'];
		}
		// preselected agent
		if ( ! empty( $presets['selected_agent'] ) && ( is_numeric( $presets['selected_agent'] ) || ( $presets['selected_agent'] == LATEPOINT_ANY_AGENT ) ) ) {
			self::$presets['selected_agent'] = $presets['selected_agent'];
		}

		// preselected service
		if ( isset( $presets['selected_service'] ) && is_numeric( $presets['selected_service'] ) ) {
			self::$presets['selected_service'] = $presets['selected_service'];
		}

		// preselected bundle
		if ( isset( $presets['selected_bundle'] ) && is_numeric( $presets['selected_bundle'] ) ) {
			self::$presets['selected_bundle'] = $presets['selected_bundle'];
		}

		// preselected duration
		if ( isset( $presets['selected_duration'] ) && is_numeric( $presets['selected_duration'] ) ) {
			self::$presets['selected_duration'] = $presets['selected_duration'];
		}

		// preselected total attendees
		if ( isset( $presets['selected_total_attendees'] ) && is_numeric( $presets['selected_total_attendees'] ) ) {
			self::$presets['selected_total_attendees'] = $presets['selected_total_attendees'];
		}

		// preselected date
		if ( isset( $presets['selected_start_date'] ) && OsTimeHelper::is_valid_date( $presets['selected_start_date'] ) ) {
			self::$presets['selected_start_date'] = $presets['selected_start_date'];
		}

		// preselected time
		if ( isset( $presets['selected_start_time'] ) && is_numeric( $presets['selected_start_time'] ) ) {
			self::$presets['selected_start_time'] = $presets['selected_start_time'];
		}

		// set source id
		if ( isset( $presets['source_id'] ) ) {
			self::$presets['source_id'] = $presets['source_id'];
		}

		/**
		 * Sets presets array of a StepHelper class
		 *
		 * @param {array} $presets Array of presets set on StepHelper class
		 * @param {array} $presets Array of presets to be used to set presets on StepHelper class
		 *
		 * @returns {array} Filtered array of presets
		 * @since 5.0.0
		 * @hook latepoint_set_presets
		 *
		 */
		return apply_filters( 'latepoint_set_presets', self::$presets, $presets );
	}


	public static function set_restrictions( array $restrictions = [] ): array {
		self::set_default_restrictions();
		if ( ! empty( $restrictions ) ) {
			// filter locations
			if ( isset( $restrictions['show_locations'] ) ) {
				self::$restrictions['show_locations'] = $restrictions['show_locations'];
			}

			// filter agents
			if ( isset( $restrictions['show_agents'] ) ) {
				self::$restrictions['show_agents'] = $restrictions['show_agents'];
			}

			// filter service category
			if ( isset( $restrictions['show_service_categories'] ) ) {
				self::$restrictions['show_service_categories'] = $restrictions['show_service_categories'];
			}

			// filter services
			if ( isset( $restrictions['show_services'] ) ) {
				self::$restrictions['show_services'] = $restrictions['show_services'];
			}

			// preselected calendar start date
			if ( isset( $restrictions['calendar_start_date'] ) && OsTimeHelper::is_valid_date( $restrictions['calendar_start_date'] ) ) {
				self::$restrictions['calendar_start_date'] = $restrictions['calendar_start_date'];
			}


		}

		/**
		 * Sets restrictions array of a StepHelper class
		 *
		 * @param {array} $restrictions Array of restrictions set on StepHelper class
		 * @param {array} $restrictions Array of restrictions to be used to set restrictions on StepHelper class
		 *
		 * @returns {array} Filtered array of restrictions
		 * @since 5.0.0
		 * @hook latepoint_set_restrictions
		 *
		 */
		return apply_filters( 'latepoint_set_restrictions', self::$restrictions, $restrictions );
	}

	/**
	 * Sets booking object properties when a single option is available
	 *
	 * If a booking object has a service selected and only one agent is offering that service -
	 * that agent will be preselected. Same for location
	 *
	 * @return OsBookingModel
	 */
	public static function set_booking_properties_for_single_options(): OsBookingModel {

		// if only 1 location exists or assigned to selected agent - set it to this booking object
		if ( OsLocationHelper::count_locations() == 1 ) {
			self::$booking_object->location_id = OsLocationHelper::get_default_location_id();
		}
		// if only 1 agent exists - set it to this booking object
		if ( OsAgentHelper::count_agents() == 1 ) {
			self::$booking_object->agent_id = OsAgentHelper::get_default_agent_id();
		}

		return self::$booking_object;
	}

	public static function set_booking_object( $booking_object_params = [] ): OsBookingModel {
		self::$booking_object = new OsBookingModel();
		self::$booking_object->set_data( $booking_object_params );

        self::$booking_object->convert_start_datetime_into_server_timezone(OsTimeHelper::get_timezone_name_from_session());

		if ( ! empty( $booking_object_params['intent_key'] ) ) {
			self::$booking_object->intent_key = $booking_object_params['intent_key'];
		}

		// set based on presets

		// preselected service
		if ( isset( self::$presets['selected_service'] ) && is_numeric( self::$presets['selected_service'] ) ) {
			self::$booking_object->service_id = self::$presets['selected_service'];
			$service                          = new OsServiceModel( self::$booking_object->service_id );
			self::$booking_object->service    = $service;
			if ( empty( $booking_object_params['duration'] ) ) {
				self::$booking_object->duration = $service->duration;
			}
			if ( empty( $booking_object_params['total_attendees'] ) ) {
				self::$booking_object->total_attendees = $service->capacity_min;
			}
		}

		// preselected agent
		if ( ! empty( self::$presets['selected_agent'] ) && ( is_numeric( self::$presets['selected_agent'] ) || ( self::$presets['selected_agent'] == LATEPOINT_ANY_AGENT ) ) ) {
			self::$booking_object->agent_id = self::$presets['selected_agent'];
		}

		// preselected location
		if ( ! empty( self::$presets['selected_location'] ) && ( is_numeric( self::$presets['selected_location'] ) || ( self::$presets['selected_location'] == LATEPOINT_ANY_LOCATION ) ) ) {
			self::$booking_object->location_id = self::$presets['selected_location'];
		}

		// preselected duration
		if ( isset( self::$presets['selected_duration'] ) && is_numeric( self::$presets['selected_duration'] ) ) {
			self::$booking_object->duration = self::$presets['selected_duration'];
		}
		// preselected attendees
		if ( isset( self::$presets['selected_total_attendees'] ) && is_numeric( self::$presets['selected_total_attendees'] ) ) {
			self::$booking_object->total_attendees = self::$presets['selected_total_attendees'];
		}
		// preselected date
		if ( isset( self::$presets['selected_start_date'] ) && OsTimeHelper::is_valid_date( self::$presets['selected_start_date'] ) ) {
			self::$booking_object->start_date = self::$presets['selected_start_date'];
		}
		// preselected time
		if ( isset( self::$presets['selected_start_time'] ) && is_numeric( self::$presets['selected_start_time'] ) ) {
			self::$booking_object->start_time = self::$presets['selected_start_time'];
		}
		// preselected time
		if ( isset( self::$presets['order_item_id'] ) && is_numeric( self::$presets['order_item_id'] ) ) {
			self::$booking_object->order_item_id = self::$presets['order_item_id'];
			// TODO - move to pro
			// it's a bundle, preset values from a bundle
			$order_item                            = new OsOrderItemModel( self::$booking_object->order_item_id );
			$bundle                                = new OsBundleModel( $order_item->get_item_data_value_by_key( 'bundle_id' ) );
			self::$booking_object->total_attendees = $bundle->total_attendees_for_service( self::$booking_object->service_id );
			self::$booking_object->duration        = $bundle->duration_for_service( self::$booking_object->service_id );
		}


		// get buffers from service and set to booking object
		self::$booking_object->set_buffers();
		if ( self::$booking_object->is_start_date_and_time_set() ) {
			self::$booking_object->calculate_end_date_and_time();
			self::$booking_object->set_utc_datetimes();
		}
		self::$booking_object->customer_id = OsAuthHelper::get_logged_in_customer_id();

		return self::$booking_object;
	}

	public static function load_order_object( $order_id = false ) {
		if ( $order_id ) {
			self::$order_object = new OsOrderModel( $order_id );
		} else {
			self::$order_object = new OsOrderModel();
		}
	}

	public static function is_bundle_scheduling() : bool {
		return self::$booking_object->is_bundle_scheduling();
	}

	/**
	 * Checks if there were supposed to be some fields for this step - now they have to be carried over to next step, because this step is skipped
	 *
	 * @param string $current_step_code
	 * @param string $next_step_code
	 *
	 * @return array
	 */
	public static function carry_preset_fields_to_next_step( string $current_step_code, string $next_step_code ): void {
		if ( ! empty( self::$preset_fields[ $current_step_code ] ) ) {
			self::$preset_fields[ $next_step_code ] = array_merge( self::$preset_fields[ $next_step_code ], self::$preset_fields[ $current_step_code ] );
		}
	}

	public static function should_step_be_skipped( string $step_code ): bool {
		$skip = false;

		switch ( $step_code ) {
			case 'booking__agents':
				if ( OsAgentHelper::count_agents() == 1 ) {
					$skip = true;
				}
				if ( self::$active_cart_item->is_bundle() ) {
					$skip = true;
				}
				break;
			case 'booking__locations':
				if ( OsLocationHelper::count_locations() == 1 ) {
					$skip = true;
				}
				if ( self::$active_cart_item->is_bundle() ) {
					$skip = true;
				}
				break;
			case 'booking__datepicker':
				if ( self::$active_cart_item->is_bundle() ) {
					$skip = true;
				}
				break;
			case 'booking__services':
				if ( self::is_bundle_scheduling() ) {
					$skip = true;
				}
				break;
			case 'payment__times':
			case 'payment__portions':
			case 'payment__methods':
			case 'payment__processors':
			case 'payment__pay':
				if ( self::is_bundle_scheduling() || empty( OsPaymentsHelper::get_enabled_payment_times() ) ) {
					// scheduling a bundle or no enabled payment times
					$skip = true;
					self::set_zero_cost_payment_fields();
				} else {
					if ( self::$cart_object->is_empty() ) {
						$skip = true;
					} else {
						$original_amount      = self::$cart_object->get_subtotal();
						$after_coupons_amount = self::$cart_object->get_total();
						$deposit_amount       = self::$cart_object->deposit_amount_to_charge();
						if ( $original_amount > 0 && $after_coupons_amount <= 0 ) {
							// original price was set, but coupon was applied and charge amount is now 0, we can skip step, even if deposit is not 0
							$is_zero_cost = true;
						} else {
							if ( $after_coupons_amount <= 0 && $deposit_amount <= 0 ) {
								$is_zero_cost = true;
							} else {
								$is_zero_cost = false;
							}
						}
						// if nothing to charge - don't show it, no matter what
						if ( $is_zero_cost && ! OsSettingsHelper::is_env_demo() ) {
							$skip = true;
							self::set_zero_cost_payment_fields();
						} else {
							if ( $step_code == 'payment__times' ) {
								if ( ! empty( self::$cart_object->payment_time ) ) {
									$skip = true;
								} else {
									// try to check if one only available and preset it
									$enabled_payment_times = OsPaymentsHelper::get_enabled_payment_times();
									if ( count( $enabled_payment_times ) == 1 ) {
										$skip                                                = true;
										self::$cart_object->payment_time                     = array_key_first( $enabled_payment_times );
										self::$preset_fields['verify']['cart[payment_time]'] = OsFormHelper::hidden_field( 'cart[payment_time]', self::$cart_object->payment_time, [ 'skip_id' => true ] );
										// assign preset field value for next step
										self::$preset_fields['payment__portions']['cart[payment_time]'] = OsFormHelper::hidden_field( 'cart[payment_time]', self::$cart_object->payment_time, [ 'skip_id' => true ] );
										self::carry_preset_fields_to_next_step( 'payment__times', 'payment__portions' );
									}
								}
							}
							if ( $step_code == 'payment__portions' ) {
								if ( ! empty( self::$cart_object->payment_portion ) ) {
									$skip = true;
								} else {
									if ( $is_zero_cost || ( self::$cart_object->payment_time == LATEPOINT_PAYMENT_TIME_LATER ) || ( $after_coupons_amount > 0 && $deposit_amount <= 0 ) ) {
										// zero cost, pay later or 0 deposit, means it's a full portion payment preset
										self::$cart_object->payment_portion = LATEPOINT_PAYMENT_PORTION_FULL;
									} elseif ( $deposit_amount > 0 && $after_coupons_amount <= 0 ) {
										self::$cart_object->payment_portion = LATEPOINT_PAYMENT_PORTION_DEPOSIT;
									}

									if ( ! empty( self::$cart_object->payment_portion ) ) {
										$skip                                                             = true;
										self::$preset_fields['verify']['cart[payment_portion]']           = OsFormHelper::hidden_field( 'cart[payment_portion]', self::$cart_object->payment_portion, [ 'skip_id' => true ] );
										self::$preset_fields['payment__methods']['cart[payment_portion]'] = OsFormHelper::hidden_field( 'cart[payment_portion]', self::$cart_object->payment_portion, [ 'skip_id' => true ] );

										self::carry_preset_fields_to_next_step( 'payment__portions', 'payment__methods' );
									}
								}
							}
							if ( $step_code == 'payment__methods' ) {
								if ( ! empty( self::$cart_object->payment_method ) ) {
									$skip = true;
								} else {
									if ( self::$cart_object->payment_time ) {
										$enabled_payment_methods = OsPaymentsHelper::get_enabled_payment_methods_for_payment_time( self::$cart_object->payment_time );
										if ( count( $enabled_payment_methods ) <= 1 ) {
											$skip                                                               = true;
											self::$cart_object->payment_method                                  = array_key_first( $enabled_payment_methods );
											self::$preset_fields['verify']['cart[payment_method]']              = OsFormHelper::hidden_field( 'cart[payment_method]', self::$cart_object->payment_method, [ 'skip_id' => true ] );
											self::$preset_fields['payment__processors']['cart[payment_method]'] = OsFormHelper::hidden_field( 'cart[payment_method]', self::$cart_object->payment_method, [ 'skip_id' => true ] );

											self::carry_preset_fields_to_next_step( 'payment__methods', 'payment__processors' );
										}
									}
								}
							}
							if ( $step_code == 'payment__processors' ) {
								if ( ! empty( self::$cart_object->payment_processor ) ) {
									$skip = true;
								} else {
									if ( self::$cart_object->payment_time && self::$cart_object->payment_method ) {
										$enabled_payment_processors = OsPaymentsHelper::get_enabled_payment_processors_for_payment_time_and_method( self::$cart_object->payment_time, self::$cart_object->payment_method );
										if ( count( $enabled_payment_processors ) <= 1 ) {
											$skip                                                           = true;
											self::$cart_object->payment_processor                           = array_key_first( $enabled_payment_processors );
											self::$preset_fields['verify']['cart[payment_processor]']       = OsFormHelper::hidden_field( 'cart[payment_processor]', self::$cart_object->payment_processor, [ 'skip_id' => true ] );
											self::$preset_fields['payment__pay']['cart[payment_processor]'] = OsFormHelper::hidden_field( 'cart[payment_processor]', self::$cart_object->payment_processor, [ 'skip_id' => true ] );

											self::carry_preset_fields_to_next_step( 'payment__processors', 'payment__pay' );
										}
									}
								}
							}
							if ( $step_code == 'payment__pay' ) {
								if ( self::$cart_object->payment_time == LATEPOINT_PAYMENT_TIME_LATER || empty( OsPaymentsHelper::get_enabled_payment_times() ) ) {
									$skip = true;
								}
							}
						}
					}
				}
				break;
		}

		$skip = apply_filters( 'latepoint_should_step_be_skipped', $skip, $step_code, self::$cart_object, self::$active_cart_item, self::$booking_object );

		return $skip;
	}

	public static function set_zero_cost_payment_fields() {
		self::$preset_fields['verify']['cart[payment_time]']      = OsFormHelper::hidden_field( 'cart[payment_time]', LATEPOINT_PAYMENT_TIME_LATER, [ 'skip_id' => true ] );
		self::$preset_fields['verify']['cart[payment_method]']    = OsFormHelper::hidden_field( 'cart[payment_method]', 'other', [ 'skip_id' => true ] );
		self::$preset_fields['verify']['cart[payment_processor]'] = OsFormHelper::hidden_field( 'cart[payment_processor]', 'other', [ 'skip_id' => true ] );
		self::$preset_fields['verify']['cart[payment_portion]']   = OsFormHelper::hidden_field( 'cart[payment_portion]', LATEPOINT_PAYMENT_PORTION_FULL, [ 'skip_id' => true ] );
	}

	public static function output_preset_fields( string $step_code ) {
		if ( ! empty( self::$preset_fields[ $step_code ] ) ) {
			foreach ( self::$preset_fields[ $step_code ] as $preset_field_html ) {
				echo $preset_field_html;
			}
		}
	}

	public static function get_next_step_code( $current_step_code ) {
		$all_step_codes     = self::get_step_codes_in_order( true );
		$active_step_codes  = self::get_step_codes_in_order();
		$current_step_index = array_search( $current_step_code, $all_step_codes );
		if ( $current_step_index === false || ( ( $current_step_index + 1 ) == count( $all_step_codes ) ) ) {
			// no more steps or not found
			return false;
		}
		$next_step_code = $all_step_codes[ $current_step_index + 1 ];

		if ( ! in_array( $next_step_code, $active_step_codes ) ) {
			// if is skipped - get next step in order and try again
			$next_step_code = self::get_next_step_code( $next_step_code );
		}

		/**
		 * Get the next step code, based on a current step
		 *
		 * @param {string} $next_step_code The next step code
		 * @param {string} $current_step_code The current step code
		 * @param {array} $all_step_codes List of all step codes
		 * @param {array} $active_step_codes List of active step codes
		 * @returns {string} The filtered next step code
		 *
		 * @since 5.0.16
		 * @hook latepoint_get_next_step_code
		 *
		 */
		return apply_filters( 'latepoint_get_next_step_code', $next_step_code, $current_step_code, $all_step_codes, $active_step_codes );
	}

	public static function get_prev_step_code( $current_step_code ) {
		$all_step_codes     = self::get_step_codes_in_order( true );
		$current_step_index = array_search( $current_step_code, $all_step_codes );

		if ( ! $current_step_index ) {
			// first step or not found - return the same code
			return $current_step_code;
		}
		$prev_step_code = $all_step_codes[ $current_step_code - 1 ];
		if ( self::should_step_be_skipped( $prev_step_code ) ) {
			// if skipped - get previous in order and try again
			$prev_step_code = self::get_prev_step_code( $prev_step_code );
		}

		/**
		 * Get the next step code, based on a current step
		 *
		 * @param {string} $next_step_code The next step code
		 * @param {string} $current_step_code The current step code
		 * @param {array} $all_step_codes List of all step codes
		 * @returns {string} The filtered next step code
		 *
		 * @since 5.0.16
		 * @hook latepoint_get_previous_step_code
		 *
		 */
		return apply_filters( 'latepoint_get_previous_step_code', $prev_step_code, $current_step_code, $all_step_codes );
	}


	public static function is_first_step( $step_code ) {
		$step_index = array_search( $step_code, self::get_step_codes_in_order() );

		return $step_index == 0;
	}

	public static function is_last_step( $step_code ) {
		$step_index = array_search( $step_code, self::get_step_codes_in_order() );

		return ( ( $step_index + 1 ) == count( self::get_step_codes_in_order() ) );
	}

	public static function is_pre_last_step( $step_code ) {
		$next_step_code = self::get_next_step_code( $step_code );
		$step_index     = array_search( $next_step_code, self::get_step_codes_in_order() );

		return ( ( $step_index + 1 ) == count( self::get_step_codes_in_order() ) );
	}

	public static function can_step_show_prev_btn( $step_code ) {
		$step_index = array_search( $step_code, self::get_step_codes_in_order() );
		// if first or last step
		if ( $step_index == 0 || ( ( $step_index + 1 ) == count( self::get_step_codes_in_order() ) ) ) {
			return false;
		} else {
			return true;
		}
	}

	public static function get_next_btn_label_for_step( $step_code ) {
		$label         = __( 'Next', 'latepoint' );
		$custom_labels = [
			'payment__pay' => __( 'Submit', 'latepoint' ),
			'verify'       => OsStepsHelper::should_step_be_skipped( 'payment__pay' ) ? __( 'Submit', 'latepoint' ) : __( 'Checkout', 'latepoint' )
		];


		/**
		 * Returns an array of custom labels for "next" button with step codes as keys
		 *
		 * @param {array} $custom_labels Current array of labels for "next" button
		 *
		 * @returns {array} Filtered array of labels for "next" button
		 * @since 4.7.0
		 * @hook latepoint_next_btn_labels_for_steps
		 *
		 */
		$custom_labels = apply_filters( 'latepoint_next_btn_labels_for_steps', $custom_labels );
		if ( ! empty( $custom_labels[ $step_code ] ) ) {
			$label = $custom_labels[ $step_code ];
		}

		return $label;
	}

	public static function can_step_show_next_btn( $step_code ) {
		$step_show_btn_rules = [
			'booking__services'   => false,
			'booking__agents'     => false,
			'booking__datepicker' => false,
			'customer'            => true,
			'payment__times'      => false,
			'payment__portions'   => false,
			'payment__methods'    => false,
			'payment__pay'        => false,
			'verify'              => true,
			'confirmation'        => false
		];

		/**
		 * Returns an array of rules of whether to show a next button on not, step codes are keys in this array
		 *
		 * @param {array} $step_show_btn_rules Current array of labels for "next" button
		 * @param {string} $step_code Current array of labels for "next" button
		 *
		 * @returns {array} Filtered array of labels for "next" button
		 * @since 4.7.0
		 * @hook latepoint_step_show_next_btn_rules
		 *
		 */
		$step_show_btn_rules = apply_filters( 'latepoint_step_show_next_btn_rules', $step_show_btn_rules, $step_code );

		return $step_show_btn_rules[ $step_code ] ?? false;
	}

	/**
	 * @throws Exception
	 */
	public static function add_current_item_to_cart() {
		if ( self::$active_cart_item->is_new_record() ) {
			if ( self::$active_cart_item->is_bundle() ) {
				self::$cart_object->add_item( self::$active_cart_item );
				self::$fields_to_update['active_cart_item[id]'] = self::$active_cart_item->id;
			} elseif ( self::$active_cart_item->is_booking() ) {
                $original_booking = clone self::$booking_object; // we need to clone it, because is_bookable will set location and agent to set values from ANY, and we don't want that for our recurring bookings
				if ( self::$booking_object->is_bookable( [ 'skip_customer_check' => true ] ) ) {
					// create recurring record and assign it to this booking
					if ( ! empty( $original_booking->generate_recurrent_sequence ) ) {
						// Recurring booking
						$recurrence            = new OsRecurrenceModel();
						$recurrence->rules     = wp_json_encode( $original_booking->generate_recurrent_sequence['rules'] );
						$recurrence->overrides = wp_json_encode( $original_booking->generate_recurrent_sequence['overrides'] );
						if ( $recurrence->save() ) {
							$original_booking->recurrence_id = $recurrence->id;
							// we don't need these attributes anymore as we will get them from the recurrence model by ID
							$original_booking->generate_recurrent_sequence = [];
							$customer_timezone                                 = $original_booking->get_customer_timezone();
							$recurring_bookings_data_and_errors                          = OsFeatureRecurringBookingsHelper::generate_recurring_bookings_data( $original_booking, $recurrence->get_rules(), $recurrence->get_overrides(), $customer_timezone );
                            $main_cart_item_id = false;
							foreach ( $recurring_bookings_data_and_errors['bookings_data'] as $recurrence_bookings_datum ) {
								if ( $recurrence_bookings_datum['unchecked'] == 'yes' || !$recurrence_bookings_datum['is_bookable'] ) {
									continue;
								}
								self::$booking_object = $recurrence_bookings_datum['booking'];
								// set it again as booking object might have changed if agent or location were set to ANY, they are assigned now
								self::set_active_cart_item_object();
                                if(!empty($main_cart_item_id)){
                                    self::$active_cart_item->connected_cart_item_id = $main_cart_item_id;
                                }
								self::$cart_object->add_item( self::$active_cart_item );
                                if(empty($main_cart_item_id)) $main_cart_item_id = self::$active_cart_item->id;
							}
                            if($main_cart_item_id) self::$fields_to_update['active_cart_item[id]'] = $main_cart_item_id;
						}
					} else {
						// Single time booking
                        // only do this for new cart item, if modifying existing one - then the set_active_cart_item method will take care of updating it
						// set it again as booking object might have changed if agent or location were set to ANY, they are assigned now
						self::set_active_cart_item_object();
						if ( self::is_bundle_scheduling() ) {
							// we don't need to use a cart for bundle scheduling
						} else {
							self::$cart_object->add_item( self::$active_cart_item );
							self::$fields_to_update['active_cart_item[id]'] = self::$active_cart_item->id;
						}
					}
					self::reset_booking_object();

					return true;
				} else {
					throw new Exception( implode( ',', self::$booking_object->get_error_messages() ) );
				}
			}
		}
	}

	public static function process_step_booking() {

		if ( ! self::is_bundle_scheduling() ) {
			// check if we are processing the last step of a booking sequence
			$booking_steps = [];
			foreach ( self::$step_codes_in_order as $step_code ) {
				if ( strpos( $step_code, 'booking__' ) !== false ) {
					$booking_steps[] = $step_code;
				}
			}
			if ( end( $booking_steps ) == self::$step_to_process ) {
				try {
					self::add_current_item_to_cart();
				} catch ( Exception $e ) {
					return new WP_Error( 'booking_slot_not_available', $e->getMessage() );
				}
			}
		}


	}

	public static function reset_booking_object() {
		self::set_booking_object( [] );
	}

	public static function prepare_step_booking() {

	}


	// SERVICES

	public static function process_step_booking__services() {
	}

	public static function prepare_step_booking__services() {
		$bundles_model = new OsBundleModel();
		$bundles       = $bundles_model->should_be_active()->should_not_be_hidden()->get_results_as_models();

		$services_model              = new OsServiceModel();
		$show_selected_services_arr  = self::$restrictions['show_services'] ? explode( ',', self::$restrictions['show_services'] ) : false;
		$show_service_categories_arr = self::$restrictions['show_service_categories'] ? explode( ',', self::$restrictions['show_service_categories'] ) : false;
		$preselected_category        = self::$presets['selected_service_category'];
		$preselected_duration        = self::$presets['selected_duration'];
		$preselected_total_attendees = self::$presets['selected_total_attendees'];

		$connected_ids = OsConnectorHelper::get_connected_object_ids( 'service_id', [
			'agent_id'    => self::$booking_object->agent_id,
			'location_id' => self::$booking_object->location_id
		] );
		// if "show only specific services" is selected (restrictions) - remove ids that are not found in connection
		$show_services_arr = ( ! empty( $show_selected_services_arr ) && ! empty( $connected_ids ) ) ? array_intersect( $connected_ids, $show_selected_services_arr ) : $connected_ids;
		if ( ! empty( $show_services_arr ) ) {
			$services_model->where_in( 'id', $show_services_arr );
		}

		$services = $services_model->should_be_active()->should_not_be_hidden()->order_by( 'order_number asc' )->get_results_as_models();

		self::$vars_for_view['show_services_arr']           = $show_services_arr;
		self::$vars_for_view['show_service_categories_arr'] = $show_service_categories_arr;
		self::$vars_for_view['preselected_category']        = $preselected_category;
		self::$vars_for_view['preselected_duration']        = $preselected_duration;
		self::$vars_for_view['preselected_total_attendees'] = $preselected_total_attendees;
		self::$vars_for_view['services']                    = $services;
		self::$vars_for_view['bundles']                     = $bundles;
	}

	// AGENTS

	public static function process_step_booking__agents() {
	}

	public static function prepare_step_booking__agents() {
		$agents_model = new OsAgentModel();

		$show_selected_agents_arr = ( self::$restrictions['show_agents'] ) ? explode( ',', self::$restrictions['show_agents'] ) : false;
		// Find agents that actually offer selected service (if selected) at selected location (if selected)
		$connected_ids = OsConnectorHelper::get_connected_object_ids( 'agent_id', [
			'service_id'  => self::$booking_object->service_id,
			'location_id' => self::$booking_object->location_id
		] );

		// If date/time is selected - filter agents who are available at that time
		if ( self::$booking_object->start_date && self::$booking_object->start_time ) {
			$available_agent_ids = [];
			$booking_request     = \LatePoint\Misc\BookingRequest::create_from_booking_model( self::$booking_object );
			foreach ( $connected_ids as $agent_id ) {
				$booking_request->agent_id = $agent_id;
				if ( OsBookingHelper::is_booking_request_available( $booking_request ) ) {
					$available_agent_ids[] = $agent_id;
				}
			}
			$connected_ids = array_intersect( $available_agent_ids, $connected_ids );
		}


		// if show only specific agents are selected (restrictions) - remove ids that are not found in connection
		$show_agents_arr = ( $show_selected_agents_arr ) ? array_intersect( $connected_ids, $show_selected_agents_arr ) : $connected_ids;
		if ( ! empty( $show_agents_arr ) ) {
			$agents_model->where_in( 'id', $show_agents_arr );
			$agents                        = $agents_model->should_be_active()->get_results_as_models();
			self::$vars_for_view['agents'] = $agents;
		} else {
			// no available or connected agents
			self::$vars_for_view['agents'] = [];
		}
	}


	// DATEPICKER

	public static function prepare_step_booking__datepicker() {
		if ( empty( self::$booking_object->agent_id ) ) {
			self::$booking_object->agent_id = LATEPOINT_ANY_AGENT;
		}
        if ( OsTimeHelper::is_valid_date( OsSettingsHelper::get_earliest_possible_booking_restriction(self::$booking_object->service_id ?? false) ) ) {
            self::$restrictions['calendar_start_date'] = OsSettingsHelper::get_earliest_possible_booking_restriction(self::$booking_object->service_id ?? false);
        }
		self::$vars_for_view['calendar_start_date'] = (!empty(self::$restrictions['calendar_start_date']) && OsTimeHelper::is_valid_date(self::$restrictions['calendar_start_date'])) ? self::$restrictions['calendar_start_date'] : 'today';
	}


	public static function process_step_booking__datepicker() {
	}


	// CONTACT


	public static function prepare_step_customer() {

		if ( OsAuthHelper::is_customer_logged_in() ) {
			self::$booking_object->customer    = OsAuthHelper::get_logged_in_customer();
			self::$booking_object->customer_id = self::$booking_object->customer->id;
		} else {
			self::$booking_object->customer = new OsCustomerModel();
		}

		self::$vars_for_view['default_fields_for_customer'] = OsSettingsHelper::get_default_fields_for_customer();
		self::$vars_for_view['customer']                    = self::$booking_object->customer;
	}

	private static function customer_params(): array {
		$params = OsParamsHelper::get_param( 'customer' );
		if ( empty( $params ) ) {
			return [];
		}

		$customer_params = OsParamsHelper::permit_params( $params, [
			'first_name',
			'last_name',
			'email',
			'phone',
			'notes',
			'password',
			'password_confirmation'
		] );

		if ( ! empty( $customer_params['first_name'] ) ) {
			$customer_params['first_name'] = sanitize_text_field( $customer_params['first_name'] );
		}
		if ( ! empty( $customer_params['last_name'] ) ) {
			$customer_params['last_name'] = sanitize_text_field( $customer_params['last_name'] );
		}
		if ( ! empty( $customer_params['email'] ) ) {
			$customer_params['email'] = sanitize_email( $customer_params['email'] );
		}
		if ( ! empty( $customer_params['phone'] ) ) {
			$customer_params['phone'] = sanitize_text_field( $customer_params['phone'] );
		}
		if ( ! empty( $customer_params['notes'] ) ) {
			$customer_params['notes'] = sanitize_textarea_field( $customer_params['notes'] );
		}

		/**
		 * Filtered customer params for steps
		 *
		 * @param {array} $customer_params a filtered array of customer params
		 * @param {array} $params unfiltered 'customer' params
		 * @returns {array} $customer_params a filtered array of customer params
		 *
		 * @since 5.0.14
		 * @hook latepoint_customer_params_on_steps
		 *
		 */
		return apply_filters( 'latepoint_customer_params_on_steps', $customer_params, $params );
	}

	public static function process_step_customer() {
		$status = LATEPOINT_STATUS_SUCCESS;

		$customer_params = self::customer_params();

		$logged_in_customer = OsAuthHelper::get_logged_in_customer();


		if ( $logged_in_customer ) {
			// LOGGED IN ALREADY
			// Check if they are changing the email on file
			if ( $logged_in_customer->email != $customer_params['email'] ) {
				// Check if other customer already has this email
				$customer                  = new OsCustomerModel();
				$customer_with_email_exist = $customer->where( array(
					'email' => $customer_params['email'],
					'id !=' => $logged_in_customer->id
				) )->set_limit( 1 )->get_results_as_models();
				// check if another customer (or if wp user login enabled - another wp user) exists with the email that this user tries to update to
				if ( $customer_with_email_exist || ( OsAuthHelper::wp_users_as_customers() && email_exists( $customer_params['email'] ) ) ) {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Another customer is registered with this email.', 'latepoint' );
				}
			}
		} else {
			// NEW REGISTRATION (NOT LOGGED IN)
			if ( OsAuthHelper::wp_users_as_customers() ) {
				// WP USERS AS CUSTOMERS
				if ( email_exists( $customer_params['email'] ) ) {
					// wordpress user with this email already exists, ask to login
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'An account with that email address already exists. Please try signing in.', 'latepoint' );
				} else {
					// wp user does not exist - search for latepoint customer
					$customer = new OsCustomerModel();
					$customer = $customer->where( array( 'email' => $customer_params['email'] ) )->set_limit( 1 )->get_results_as_models();
					if ( $customer ) {
						// latepoint customer with this email exits, create wp user for them
						$wp_user       = OsCustomerHelper::create_wp_user_for_customer( $customer );
						$status        = LATEPOINT_STATUS_ERROR;
						$response_html = __( 'An account with that email address already exists. Please try signing in.', 'latepoint' );
					} else {
						// no latepoint customer or wp user with this email found, can proceed
					}
				}
			} else {
				// LATEPOINT CUSTOMERS
				$customer       = new OsCustomerModel();
				$customer_exist = $customer->where( array( 'email' => $customer_params['email'] ) )->set_limit( 1 )->get_results_as_models();
				if ( $customer_exist ) {
					// customer with this email exists - check if current customer was registered as a guest
					if ( OsSettingsHelper::is_on( 'steps_hide_login_register_tabs' ) || ( $customer_exist->can_login_without_password() && ! OsSettingsHelper::is_on( 'steps_require_setting_password' ) ) ) {
						// guest account, login automatically
						$status == LATEPOINT_STATUS_SUCCESS;
						OsAuthHelper::authorize_customer( $customer_exist->id );
					} else {
						// Not a guest account, ask to login
						$status        = LATEPOINT_STATUS_ERROR;
						$response_html = __( 'An account with that email address already exists. Please try signing in.', 'latepoint' );
					}
				} else {
					// no latepoint customer with this email found, can proceed
				}
			}
			// if not logged in - check if password has to be set
			if ( ! OsAuthHelper::is_customer_logged_in() && OsSettingsHelper::is_on( 'steps_require_setting_password' ) ) {
				if ( ! empty( $customer_params['password'] ) && $customer_params['password'] == $customer_params['password_confirmation'] ) {
					$customer_params['password'] = OsAuthHelper::hash_password( $customer_params['password'] );
					$customer_params['is_guest'] = false;
				} else {
					// Password is blank or does not match the confirmation
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Setting password is required and should match password confirmation', 'latepoint' );
				}
			}
		}
		// If no errors, proceed
		if ( $status == LATEPOINT_STATUS_SUCCESS ) {
			if ( OsAuthHelper::is_customer_logged_in() ) {
				$customer        = OsAuthHelper::get_logged_in_customer();
				$is_new_customer = $customer->is_new_record();
			} else {
				$customer        = new OsCustomerModel();
				$is_new_customer = true;
			}
			$old_customer_data = $is_new_customer ? [] : $customer->get_data_vars();
			$customer->set_data( $customer_params, LATEPOINT_PARAMS_SCOPE_PUBLIC );
			if ( $customer->save() ) {
				if ( $is_new_customer ) {
					do_action( 'latepoint_customer_created', $customer );
				} else {
					do_action( 'latepoint_customer_updated', $customer, $old_customer_data );
				}

				self::$booking_object->customer_id = $customer->id;
				if ( ! OsAuthHelper::is_customer_logged_in() ) {
					OsAuthHelper::authorize_customer( $customer->id );
				}
				$customer->set_timezone_name();
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = $customer->get_error_messages();
				if ( is_array( $response_html ) ) {
					$response_html = implode( ', ', $response_html );
				}
			}
		}
		if ( $status == LATEPOINT_STATUS_ERROR ) {
			return new WP_Error( LATEPOINT_STATUS_ERROR, $response_html );
		}

	}


	// VERIFICATION STEP

	public static function process_step_verify() {

	}

	public static function prepare_step_verify() {
		$cart = OsCartsHelper::get_or_create_cart();

		$cart->set_singular_payment_attributes();

		self::$vars_for_view['cart']                        = $cart;
		self::$vars_for_view['customer']                    = OsAuthHelper::get_logged_in_customer();
		self::$vars_for_view['default_fields_for_customer'] = OsSettingsHelper::get_default_fields_for_customer();
	}

	// PAYMENT

	public static function process_step_payment__portions() {
	}

	public static function prepare_step_payment__portions() {
	}

	public static function process_step_payment__times() {
	}

	public static function prepare_step_payment__times() {
		$enabled_payment_times = OsPaymentsHelper::get_enabled_payment_times();

		self::$vars_for_view['enabled_payment_times'] = $enabled_payment_times;
	}

	public static function process_step_payment__methods() {
	}

	public static function prepare_step_payment__methods() {
		$enabled_payment_methods                        = OsPaymentsHelper::get_enabled_payment_methods_for_payment_time( self::$cart_object->payment_time );
		self::$vars_for_view['enabled_payment_methods'] = $enabled_payment_methods;
	}

	public static function process_step_payment__processors() {
	}

	public static function prepare_step_payment__processors() {
		$enabled_payment_processors                        = OsPaymentsHelper::get_enabled_payment_processors();
		self::$vars_for_view['enabled_payment_processors'] = $enabled_payment_processors;
	}

	public static function process_step_payment__pay() {
	}

	public static function prepare_step_payment__pay() {
		$booking_form_page_url = self::$params['booking_form_page_url'] ?? OsUtilHelper::get_referrer();
		$order_intent          = OsOrderIntentHelper::create_or_update_order_intent( self::$cart_object, self::$restrictions, self::$presets, $booking_form_page_url );
	}


	// CONFIRMATION

	public static function process_step_confirmation() {
	}

	public static function prepare_step_confirmation() {
		self::$vars_for_view['customer']                    = OsAuthHelper::get_logged_in_customer();
		self::$vars_for_view['default_fields_for_customer'] = OsSettingsHelper::get_default_fields_for_customer();
		if ( ! self::$order_object->is_new_record() ) {
			self::$vars_for_view['order']                = self::$order_object;
			self::$vars_for_view['order_bookings']       = self::$order_object->get_bookings_from_order_items();
			self::$vars_for_view['order_bundles']        = self::$order_object->get_bundles_from_order_items();
			self::$vars_for_view['price_breakdown_rows'] = self::$order_object->generate_price_breakdown_rows();
			self::$vars_for_view['is_bundle_scheduling'] = false;
		} else {
			// TRY SAVING BOOKING
			// check if it's a scheduling request for an existing order item, it means its a bundle
			$is_bundle_scheduling                        = self::is_bundle_scheduling();
			self::$vars_for_view['is_bundle_scheduling'] = $is_bundle_scheduling;
			if ( $is_bundle_scheduling ) {
				$order_item                                  = new OsOrderItemModel( self::$booking_object->order_item_id );
				$order                                       = new OsOrderModel( $order_item->order_id );
				self::$vars_for_view['order']                = $order;
				self::$vars_for_view['order_bookings']       = $order->get_bookings_from_order_items();
				self::$vars_for_view['order_bundles']        = $order->get_bundles_from_order_items();
				self::$vars_for_view['price_breakdown_rows'] = self::$cart_object->generate_price_breakdown_rows();

                if(!empty(self::$booking_object->generate_recurrent_sequence)){
                    $recurrence            = new OsRecurrenceModel();
                    $recurrence->rules     = wp_json_encode( self::$booking_object->generate_recurrent_sequence['rules'] );
                    $recurrence->overrides = wp_json_encode( self::$booking_object->generate_recurrent_sequence['overrides'] );
                    if ( $recurrence->save() ) {
                        self::$booking_object->recurrence_id = $recurrence->id;
                        // we don't need these attributes anymore as we will get them from the recurrence model by ID
                        self::$booking_object->generate_recurrent_sequence = [];
                        $customer_timezone                                 = self::$booking_object->get_customer_timezone();
                        $recurring_bookings_data_and_errors                          = OsFeatureRecurringBookingsHelper::generate_recurring_bookings_data( self::$booking_object, $recurrence->get_rules(), $recurrence->get_overrides(), $customer_timezone );
                        foreach ( $recurring_bookings_data_and_errors['bookings_data'] as $recurrence_bookings_datum ) {
                            if ( $recurrence_bookings_datum['unchecked'] == 'yes' ) {
                                continue;
                            }
                            self::$booking_object = $recurrence_bookings_datum['booking'];
                            // set it again as booking object might have changed if agent or location were set to ANY, they are assigned now
                            self::set_active_cart_item_object();
                            if ( self::$booking_object->is_bookable() ) {

                                if ( self::$booking_object->save() ) {
                                    do_action( 'latepoint_booking_created', self::$booking_object );
                                } else {
                                    // error saving booking
                                    self::$booking_object->add_error( 'booking_error', self::$booking_object->get_error_messages() );
                                }
                            } else {
                                // is not bookable
                                self::$booking_object->add_error( 'booking_error', self::$booking_object->get_error_messages() );
                            }
                        }
                    }
                }else{
                    if ( self::$booking_object->is_bookable() ) {
                        self::$booking_object->calculate_end_time();
                        self::$booking_object->calculate_end_date();
                        self::$booking_object->set_utc_datetimes();
                        $service                             = new OsServiceModel( self::$booking_object->service_id );
                        self::$booking_object->buffer_before = $service->buffer_before;
                        self::$booking_object->buffer_after  = $service->buffer_after;

                        if ( self::$booking_object->save() ) {
                            do_action( 'latepoint_booking_created', self::$booking_object );
                        } else {
                            // error saving booking
                            self::$booking_object->add_error( 'booking_error', self::$booking_object->get_error_messages() );
                        }
                    } else {
                        // is not bookable
                        self::$booking_object->add_error( 'booking_error', self::$booking_object->get_error_messages() );
                    }
                }


			} else {
				$order_intent = OsOrderIntentHelper::create_or_update_order_intent( self::$cart_object, self::$restrictions, self::$presets );
				if ( $order_intent->is_processing() ) {
					return new WP_Error( LATEPOINT_STATUS_ERROR, __( 'Processing...', 'latepoint' ), [ 'send_to_step' => 'resubmit' ] );
				}
				if ( $order_intent->convert_to_order() ) {
					$order = new OsOrderModel( $order_intent->order_id );
					self::$cart_object->clear();
					self::$vars_for_view['order']                = $order;
					self::$vars_for_view['order_bookings']       = $order->get_bookings_from_order_items();
					self::$vars_for_view['order_bundles']        = $order->get_bundles_from_order_items();
					self::$vars_for_view['price_breakdown_rows'] = $order->generate_price_breakdown_rows();
				} else {
					// ERROR CONVERTING TO ORDER
					OsDebugHelper::log( 'Error saving order', 'order_error', $order_intent->get_error_messages() );
					$response_html = $order_intent->get_error_messages();
					$error_data    = ( $order_intent->get_error_data( 'send_to_step' ) ) ? [ 'send_to_step' => $order_intent->get_error_data( 'send_to_step' ) ] : '';

					return new WP_Error( LATEPOINT_STATUS_ERROR, $response_html, $error_data );
				}
			}
		}
	}

	public static function output_list_option( $option ) {
		$html = '';
		$html .= '<div tabindex="0" class="lp-option ' . esc_attr( $option['css_class'] ) . '" ' . $option['attrs'] . '>';
		$html .= '<div class="lp-option-image-w"><div class="lp-option-image" style="background-image: url(' . esc_url( $option['image_url'] ) . ')"></div></div>';
		$html .= '<div class="lp-option-label">' . esc_html( $option['label'] ) . '</div>';
		$html .= '</div>';

		return $html;
	}

	public static function get_steps_for_select(): array {
		$steps             = self::get_step_codes_in_order();
		$steps_with_labels = [];
		foreach ( $steps as $step_code ) {
			$steps_with_labels[ $step_code ] = self::get_step_label_by_code( $step_code );
		}

		return $steps_with_labels;
	}


	public static function save_step_codes_in_order( array $step_codes_in_order ): bool {
		return OsSettingsHelper::save_setting_by_name( 'step_codes_in_order', implode( ',', $step_codes_in_order ) );
	}


	public static function save_steps_settings( $steps_settings ): bool {
		self::$steps_settings = $steps_settings;

		return OsSettingsHelper::save_setting_by_name( 'steps_settings', self::$steps_settings );
	}


	public static function get_step_settings( string $step_code ): array {
		$settings = self::get_steps_settings();

		return $settings[ $step_code ] ?? [];
	}

	public static function get_steps_settings(): array {
		if ( ! empty( self::$steps_settings ) ) {
			return self::$steps_settings;
		}

		$steps_settings_from_db = OsSettingsHelper::get_settings_value( 'steps_settings', [] );
		$step_codes             = self::get_step_codes_in_order();


		if ( empty( $steps_settings_from_db ) ) {
			$steps_settings = [
				'shared' => [
					'steps_support_text' => '<h5>Questions?</h5><p>Call (858) 939-3746 for help</p>'
				]
			];
			foreach ( $step_codes as $step_code ) {
				$steps_settings[ $step_code ] = self::get_default_value_for_step_settings( $step_code );
			}
			OsSettingsHelper::save_setting_by_name( 'steps_settings', $steps_settings );
			self::$steps_settings = $steps_settings;
		} else {
			// iterate step codes to see if each has a setting
			$changed = false;
			foreach ( $step_codes as $step_code ) {
				if ( ! isset( $steps_settings_from_db[ $step_code ] ) ) {
					$steps_settings_from_db[ $step_code ] = self::get_default_value_for_step_settings( $step_code );
					$changed                              = true;
				}
			}
			if ( $changed ) {
				OsSettingsHelper::save_setting_by_name( 'steps_settings', $steps_settings_from_db );
			}
			self::$steps_settings = $steps_settings_from_db;
		}

		return self::$steps_settings;
	}

	/**
	 * @param string $step_code
	 * @param string $placement before, after
	 *
	 * @return string
	 */
	public static function get_formatted_extra_step_content( string $step_code, string $placement ): string {
		$content = self::get_step_setting_value( $step_code, 'main_panel_content_' . $placement );

		return ! empty( $content ) ? '<div class="latepoint-step-content-text-left">' . $content . '</div>' : '';
	}


	public static function get_step_setting_value( string $step_code, string $setting_key, $default = '' ) {
		$steps_settings = self::get_step_settings( $step_code );

		return $steps_settings[ $setting_key ] ?? $default;
	}

	public static function get_step_settings_edit_form_html( string $selected_step_code ): string {
		$step_settings_html = '';
		switch ( $selected_step_code ) {
			case 'booking__services':
				$step_settings_html .= OsFormHelper::toggler_field( 'settings[steps_show_service_categories]', __( 'Show service categories', 'latepoint' ), OsSettingsHelper::steps_show_service_categories(), false, false, [ 'sub_label' => __( 'If turned on, services will be displayed in categories', 'latepoint' ) ] );
				$step_settings_html .= OsFormHelper::toggler_field( 'settings[show_service_categories_count]', __( 'Show service count for categories', 'latepoint' ), OsSettingsHelper::is_on('show_service_categories_count'), false, false, [ 'sub_label' => __( 'If turned on, category tile will display a count of services', 'latepoint' ) ] );
				break;
			case 'booking__agents':
				$step_settings_html .= OsFormHelper::toggler_field( 'settings[steps_show_agent_bio]', __( 'Show Learn More about agents', 'latepoint' ), OsSettingsHelper::is_on( 'steps_show_agent_bio' ), false, false, [ 'sub_label' => __( 'A link to open information about agent will be added to each agent tile', 'latepoint' ) ] );
				$step_settings_html .= OsFormHelper::toggler_field( 'settings[steps_hide_agent_info]', __( 'Hide agent name from summary and confirmation', 'latepoint' ), OsSettingsHelper::is_on( 'steps_hide_agent_info' ), false, false, [ 'sub_label' => __( 'Check if you want to hide agent name from showing up', 'latepoint' ) ] );
				$step_settings_html .= OsFormHelper::toggler_field( 'settings[allow_any_agent]', __( 'Add "Any Agent" option to agent selection', 'latepoint' ), OsSettingsHelper::is_on( 'allow_any_agent' ), 'lp-any-agent-settings', false, [ 'sub_label' => __( 'Customers can pick "Any agent" and system will find a matching agent', 'latepoint' ) ] );
				$step_settings_html .= '<div class="control-under-toggler" id="lp-any-agent-settings" ' . ( OsSettingsHelper::is_on( 'allow_any_agent' ) ? '' : 'style="display: none;"' ) . '>';
				$step_settings_html .= OsFormHelper::select_field( 'settings[any_agent_order]', __( 'If "Any Agent" is selected then assign booking to', 'latepoint' ), OsSettingsHelper::get_order_types_list_for_any_agent_logic(), OsSettingsHelper::get_any_agent_order() );
				$step_settings_html .= '</div>';
				break;
			case 'booking__datepicker':
				$step_settings_html .= OsFormHelper::select_field( 'steps_settings[booking__datepicker][time_pick_style]', __( 'Show Time Slots as', 'latepoint' ), [
					'timebox'  => 'Time Boxes',
					'timeline' => 'Timeline'
				], OsStepsHelper::get_time_pick_style() );
				$step_settings_html .= OsFormHelper::select_field( 'steps_settings[booking__datepicker][calendar_style]', __( 'Style of Datepicker', 'latepoint' ), [
					'modern'  => 'Modern',
					'classic' => 'Classic'
				], OsStepsHelper::get_calendar_style() );
				$step_settings_html .= OsFormHelper::toggler_field( 'steps_settings[booking__datepicker][hide_timepicker_when_one_slot_available]', __( 'Hide time picker if single slot', 'latepoint' ), OsUtilHelper::is_on( self::get_step_setting_value( $selected_step_code, 'hide_timepicker_when_one_slot_available' ) ), false, false, [ 'sub_label' => __( 'If a single slot is available in a day, it will be preselected.', 'latepoint' ) ] );
				$step_settings_html .= OsFormHelper::toggler_field( 'steps_settings[booking__datepicker][hide_slot_availability_count]', __( 'Hide slot availability count', 'latepoint' ), OsStepsHelper::hide_slot_availability_count(), false, false, [ 'sub_label' => __( 'Slot counter tooltip will not appear when hovering a day.', 'latepoint' ) ] );
				$step_settings_html .= OsFormHelper::toggler_field( 'steps_settings[booking__datepicker][hide_unavailable_slots]', __( 'Hide slots that are not available', 'latepoint' ), OsStepsHelper::hide_unavailable_slots(), false, false, [ 'sub_label' => __( 'Hides time boxes that are not available, instead of showing them in gray.', 'latepoint' ) ] );
				$step_settings_html .= OsFormHelper::toggler_field( 'steps_settings[booking__datepicker][disable_searching_first_available_slot]', __( 'Disable auto searching for first available slot', 'latepoint' ), OsStepsHelper::disable_searching_first_available_slot(), false, false, [ 'sub_label' => __( 'If checked, this will stop calendar from automatically scrolling to a first available slot', 'latepoint' ) ] );
				break;
			case 'confirmation':
				$step_settings_html .= OsFormHelper::select_field( 'steps_settings[confirmation][order_confirmation_message_style]', __( 'Message Style', 'latepoint' ), [ 'green'  => __( 'Green', 'latepoint' ),
				                                                                                                                                                           'yellow' => __( 'Yellow', 'latepoint' )
				], self::get_step_setting_value( $selected_step_code, 'order_confirmation_message_style', 'green' ) );
				break;
		}
		/**
		 * Generates HTML for step settings form in the preview
		 *
		 * @param {string} $step_settings_html html that is going to be output on the step settings form
		 * @param {string} $selected_step_code step code that settings are requested for
		 * @returns {string} $step_settings_html Filtered HTML of the settings form
		 *
		 * @since 5.0.0
		 * @hook latepoint_get_step_settings_edit_form_html
		 *
		 */
		$step_settings_html = apply_filters( 'latepoint_get_step_settings_edit_form_html', $step_settings_html, $selected_step_code );
		if ( empty( $step_settings_html ) ) {
			$step_settings_html = '<div class="bf-step-no-settings-message">' . __( 'This step does not have any specific settings. You can use the selector above to check another step.', 'latepoint' ) . '</div>';
		}

		return $step_settings_html;
	}

	public static function get_default_value_for_step_settings( string $step_code ): array {
		$settings = [
			'booking__services'   => [
				'side_panel_heading'     => 'Service Selection',
				'side_panel_description' => 'Please select a service for which you want to schedule an appointment',
				'main_panel_heading'     => 'Available Services'
			],
			'booking__locations'  => [
				'side_panel_heading'     => 'Location Selection',
				'side_panel_description' => 'Please select a location where you want to schedule an appointment',
				'main_panel_heading'     => 'Available Locations'
			],
			'booking__agents'     => [
				'side_panel_heading'     => 'Agent Selection',
				'side_panel_description' => 'Please select an agent that will be providing you a service',
				'main_panel_heading'     => 'Available Agents'
			],
			'booking__datepicker' => [
				'side_panel_heading'     => 'Select Date & Time',
				'side_panel_description' => 'Please select date and time for your appointment',
				'main_panel_heading'     => 'Date & Time Selection'
			],
			'customer'            => [
				'side_panel_heading'     => 'Enter Your Information',
				'side_panel_description' => 'Please enter your contact information',
				'main_panel_heading'     => 'Customer Information'
			],
			'verify'              => [
				'side_panel_heading'     => 'Verify Order Details',
				'side_panel_description' => 'Double check your reservation details and click submit button if everything is correct',
				'main_panel_heading'     => 'Verify Order Details',
			],
			'payment__times'      => [
				'side_panel_heading'     => 'Payment Time Selection',
				'side_panel_description' => 'Please choose when you would like to pay for your appointment',
				'main_panel_heading'     => 'When would you like to pay?'
			],
			'payment__portions'   => [
				'side_panel_heading'     => 'Payment Portion Selection',
				'side_panel_description' => 'Please select how much you would like to pay now',
				'main_panel_heading'     => 'How much would you like to pay now?'
			],
			'payment__methods'    => [
				'side_panel_heading'     => 'Payment Method Selection',
				'side_panel_description' => 'Please select a payment method you would like to make a payment with',
				'main_panel_heading'     => 'Select payment method'
			],
			'payment__processors' => [
				'side_panel_heading'     => 'Payment Processor Selection',
				'side_panel_description' => 'Please select a payment processor you want to process the payment with',
				'main_panel_heading'     => 'Select payment processor'
			],
			'payment__pay'        => [
				'side_panel_heading'     => 'Make a Payment',
				'side_panel_description' => 'Please enter your payment information so we can process the payment',
				'main_panel_heading'     => 'Enter your payment information'
			],
			'confirmation'        => [
				'side_panel_heading'     => 'Confirmation',
				'side_panel_description' => 'Your order has been placed. Please retain this confirmation for your record.',
				'main_panel_heading'     => 'Order Confirmation'
			]
		];


		$settings = apply_filters( 'latepoint_settings_for_step_codes', $settings );

		return $settings[ $step_code ] ?? [];
	}


	public static function get_default_side_panel_image_html_for_step_code( string $step_code ): string {
		$svg = '';
		switch ( $step_code ) {
			case 'booking__locations':
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
					<path class="latepoint-step-svg-highlight" d="M60.3884583,4.85921c-2.8716431-0.2993164-5.8259277,0.557373-7.9927979,2.197998 c-1.0095825,0.6467285-1.8696899,1.4177246-2.4382935,2.2561035c-1.7146873,2.5291042-2.5220757,6.3280535-1.3348999,10.835206 c-5.2646828-1.1404552-4.7828903-1.0880737-4.9659424-1.052002l-2.1259766,0.4560547 c-18.4231091,3.9559402-16.4117718,3.5059223-16.6292133,3.5698242 C4.8973494,18.9566498,6.1634111,19.1396389,5.8543382,19.2293282c0.0001221-0.0048828,0.0001221-0.0097656,0.0002441-0.0146484 c-0.0184326,0.012207-0.0371094,0.0292969-0.055603,0.0419922c-0.2596664,0.100153-0.2317972,0.1285801-0.3178711,0.2409668 c-0.388855,0.3278809-0.7800293,0.7553711-1.1567383,1.2041016c-0.3962412,0.4718437-0.1706734-1.9064941,0.5690308,41.3483887 c0.0057373,0.3037109,0.1334229,0.597168,0.3482666,0.8115234c0.3456421,0.3449707,0.5272217,0.5529785,0.7957764,0.7592773 c0.0950928,0.2109375,0.2803345,0.3754883,0.5170288,0.4277306c20.0937347,4.4312515,18.6302357,4.2767105,19.0541992,3.9326172 c0.0049438-0.0039063,0.0066528-0.010498,0.0114746-0.0146484c0.10186-0.0230865,15.3084774-3.4694977,17.9484882-4.0644493 c0.0352173-0.0078125,0.0643921-0.0273438,0.0973511-0.0397949c19.0996971,4.4957237,18.2303658,4.3366661,18.4299927,4.3366661 c0.4144669,0,0.7473717-0.3352814,0.75-0.7451172c0.0791321-12.2700005,0.2286911-24.8520088,0.3359375-36.9809532 c3.2604828-5.2970676,7.2790756-13.97159,5.0361328-19.7866211C67.0105286,7.553546,63.8635559,5.2127256,60.3884583,4.85921z M24.2595501,66.4368439c-0.1054153-0.0233917-14.3338861-3.1805725-16.8095703-3.727047 C7.0617967,48.3806953,6.8420701,33.9500313,6.8132615,20.8670235c5.8759589,1.233469,11.3363876,2.3809967,17.2407227,3.6113281 C24.3160305,51.6952362,24.2979584,58.1465149,24.2595501,66.4368439z M42.6662903,62.5681953 c-2.7329216,0.6163788-16.6759109,3.7770119-16.7893696,3.8027306c-0.1231174-12.0390549-0.0782604-29.8359985-0.02948-41.9248009 c5.5739422-1.1885509,11.055666-2.3654537,17.2197285-3.6884766C43.0675392,20.8666286,42.96418,48.7001991,42.6662903,62.5681953z M61.3523254,66.5017853c-5.4633789-1.2939453-11.2871094-2.6728477-16.8710938-3.989254 c-0.1817551-17.4268951-0.0330315-7.6905823,0.1430664-41.7041016c1.5129585,0.33918,2.9774971,0.6543026,4.5148926,0.9870605 c1.2711296,3.5923672,4.1154442,8.24547,6.2368164,10.9348145c0.510498,0.6472168,1.4362793,1.4404297,2.2056885,1.7519531 c0.8912773,0.6281052,1.8476524,0.4962959,2.5943604-0.1904297c0.5303345-0.4863281,1.022644-1.03125,1.4845581-1.6137695 C61.5390205,45.8931503,61.4254494,55.6076279,61.3523254,66.5017853z M64.0022278,25.9051094 c-1.2943535,2.4604969-2.8116989,5.4206085-4.840332,7.28125c-0.1386719,0.1279297-0.296875,0.1855469-0.4130859,0.2011719 c-0.7806473-0.0199814-5.2463379-5.6790333-7.6728516-13.1708984c-0.5771484-1.7861328-1.190918-4.1210938-0.8085938-6.3457041 c0.3496094-2.03125,0.9931641-3.5849609,1.9125977-4.6152344c1.8496094-2.0751953,5.0126953-3.2119141,8.0566406-2.9042969 c2.9272461,0.2978516,5.5722656,2.2568359,6.5820313,4.8740234C68.454361,15.4667559,66.1138763,21.8956394,64.0022278,25.9051094z "/>
					<path class="latepoint-step-svg-base" d="M54.1091614,12.0506163c-2.088459,3.2326937,0.0606689,7.85254,4.3237305,7.85254 c3.6078873,0,5.8475189-3.5880222,4.8115234-6.6953135C61.9358063,9.2799187,56.3691139,8.5516081,54.1091614,12.0506163z M58.170929,18.3797188c-0.8803711-0.0610352-1.743103-0.4106445-2.3566895-1.0410156 c-1.1245117-1.1542969-1.3198242-3.1201181-0.4453125-4.4736338c0.8155251-1.2618265,2.428051-1.8824129,4.0743408-1.404541 c0.5652466,0.5754395,1.0892944,1.170166,1.3425903,1.8354492C61.5309181,15.2528019,60.553997,17.7360039,58.170929,18.3797188z" /></svg>';
				break;
			case 'booking__services':
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
					<path class="latepoint-step-svg-highlight" d="M12.4475956,46.2568436c-0.1044884,1.7254677-0.2875328,2.2941246,0.1235962,3.2275391 c0.2800293,1.0578613,1.2532349,2.0065918,2.4077148,2.4970703c2.5679932,1.0912819,3.8084583,0.576416,36.5757446,0.7905273 c1.5809326,0.0102539,4.2476807-0.1374512,5.786499-0.4538574c2.1460648-0.4416046,4.1996078-1.119503,4.6765137-3.3955078 c0.1690674-0.3930664,0.2585449-0.8137207,0.2453613-1.244873c-0.0195313-0.6503906-0.0566406-1.3046875-0.1044922-1.9511719 c-0.1210938-1.6845703-1.6621094-2.9892578-3.5175781-2.9892578c-0.015625,0-0.03125,0-0.046875,0l-42.6777344,0.5214844 C14.0725956,43.2812576,12.5491581,44.5976639,12.4475956,46.2568436z M58.6409569,44.2373123 c1.0712891,0,1.9560547,0.6972656,2.0214844,1.5976563c0.0458984,0.6259766,0.0830078,1.2587891,0.1005859,1.8876953 c0.0309868,1.0110512-0.9663086,1.7237892-2.0117188,1.7304688c-14.3534698,0.0823135-28.739151,0.728199-42.9609375,0.5419922 c-1.0929708-0.0137672-2.0631294-0.8028984-1.9785156-1.8085938c0.0527344-0.6113281,0.0957031-1.2294922,0.1337891-1.8378906 c0.0537109-0.8789063,0.9267578-1.5771484,1.9882813-1.5898438C16.0340576,44.757576,58.7426338,44.2373123,58.6409569,44.2373123z "/>
					<path class="latepoint-step-svg-base" d="M58.2141991,6.9736419l-0.5214844,4.9931645c-0.0457916,0.4391737,0.2963982,0.828125,0.7470703,0.828125 c0.3789063,0,0.7050781-0.2861328,0.7451172-0.671875l0.5214844-4.9931645 c0.0429688-0.4121094-0.2558594-0.78125-0.6679688-0.8242188C58.6360741,6.256845,58.2571678,6.5605559,58.2141991,6.9736419z"/>
					<path class="latepoint-step-svg-base" d="M65.2903671,8.9316502l-3.6796837,3.6767578c-0.4748344,0.4748325-0.1306915,1.2802734,0.5302734,1.2802734 c0.1914063,0,0.3837891-0.0732422,0.5302734-0.2197266L66.350914,9.992197c0.2929688-0.2929688,0.2929688-0.7675781,0-1.0605469 C66.0589218,8.639658,65.5843124,8.6377048,65.2903671,8.9316502z"/>
					<path class="latepoint-step-svg-base" d="M68.8108749,16.1767673c-0.1835938-0.3710938-0.6347656-0.5234375-1.0048828-0.3388672 c-1.1025391,0.5478516-2.3320313,0.7939453-3.5585938,0.7119141c-0.4033165-0.0234375-0.770504,0.2851563-0.7978477,0.6982422 s0.2851563,0.7705078,0.6982384,0.7978516c1.4586029,0.0992756,2.9659576-0.1902256,4.3242188-0.8642578 C68.8431015,16.9970798,68.9944687,16.5468845,68.8108749,16.1767673z"/>
					<path class="latepoint-step-svg-highlight" d="M7.0583744,24.3901463c1.7924805,0.6647949,3.8635864,0.6894531,5.857666,0.7006836 c12.414856,0.0710449,23.6358051,0.019043,36.0507202,0.0898438c1.8114014,0.0102539,4.8669434-0.1374512,6.630127-0.4538574 c1.7630615-0.3166504,3.4486084-0.7158203,4.5030518-1.8364258c0.5599365-0.5949707,0.8862305-1.326416,0.9301758-2.0551758 c0.1284103-0.495512,0.1391678-0.7500668-0.0229492-2.7072754c-0.125988-1.5260391-1.6530342-2.9814453-3.9726563-2.9814453 L8.1350956,15.6670017c-2.0859375,0.0224609-3.7490234,1.3085938-3.8671875,2.9931641 c-0.131978,1.8722496-0.2533808,2.0809135-0.0430298,2.7998047C4.332056,22.6867771,5.5573368,23.8335056,7.0583744,24.3901463z M5.7640018,18.764658c0.0615234-0.8681641,1.1318359-1.5849609,2.3867188-1.5976563l48.8994141-0.5205078 c1.2441406-0.0126953,2.3886719,0.7070313,2.4628906,1.6044922c0.0517578,0.625,0.09375,1.2558594,0.1142578,1.8818359 c0.0375061,1.0384789-1.2411385,1.7228012-2.4140625,1.7285156c-16.2836723,0.0816097-33.0511169,0.7308216-49.2275391,0.5429688 c-1.1799021-0.0141487-2.4750004-0.7440434-2.3740234-1.8007813C5.6712284,19.9912205,5.7220097,19.3730564,5.7640018,18.764658z" />
					<path class="latepoint-step-svg-highlight" d="M25.6985722,38.054451c1.9748383,1.0864716,2.6161232,0.5729103,28.2541523,0.7905273 c1.2214355,0.0102539,3.28125-0.1374512,4.4699707-0.4538574c1.6699829-0.4448471,2.8914299-1.0308228,3.4542236-2.7290039 c0.6960297-1.1023483,0.5326729-2.1277504,0.4388428-3.850584c-0.0966797-1.7070313-1.40625-3.0332031-2.9306641-3.0009766 l-32.9677734,0.5205078c-1.5166016,0.0253906-2.765625,1.3466797-2.8447266,3.0097637 c-0.0829926,1.7514267-0.3514214,2.8246078,0.5612793,4.0524902C24.4834843,37.0983963,25.0513554,37.698494,25.6985722,38.054451z M25.0706425,32.4111404c0.0419922-0.8740215,0.6445313-1.5683575,1.3710938-1.5800762l32.9667969-0.5205078 c0.0058594,0,0.0117188,0,0.0175781,0c0.7314453,0,1.3417969,0.6923828,1.3916016,1.5839844 c0.0351563,0.6289043,0.0634766,1.2646465,0.078125,1.8945293c0.0201225,0.8820457-0.556736,1.731514-1.3867188,1.7373047 c-10.9964714,0.0815811-22.1932869,0.7267456-33.1787109,0.5419922c-0.7375622-0.013092-1.4293518-0.7859573-1.3623047-1.8242188 C25.0081425,33.6347733,25.0423222,33.0185623,25.0706425,32.4111404z"/>
					<path class="latepoint-step-svg-highlight" d="M62.451992,63.2775955c0.5789719-1.0259094,0.4419289-1.8840179,0.3344727-3.6164551 c-0.1044922-1.6894531-1.4648438-2.9960938-3.1064453-2.9960938c-0.0146484,0-0.0302734,0-0.0449219,0l-36.3544922,0.5205078 c-1.6298828,0.0234375-2.9755859,1.3427734-3.0634766,3.0048828c-0.09375,1.795887-0.3370171,2.6628914,0.4232788,3.8208008 c0.3649292,0.8071289,1.0519409,1.5019531,1.8442383,1.8972168c2.1949348,1.0950089,3.3277054,0.5763168,31.1570454,0.7905273 c1.3469238,0.0102539,3.6184082-0.1374512,4.9293213-0.4538574C60.4500313,65.7912064,61.8896866,65.1745071,62.451992,63.2775955z M59.7708397,63.3798904c-12.1266251,0.0816307-24.4732285,0.7282944-36.5908203,0.5419922 c-0.9430161-0.0149651-1.6459942-0.8662491-1.578125-1.8183594c0.0439453-0.6103516,0.0820313-1.2265625,0.1132813-1.8339844 c0.0458984-0.8769531,0.7431641-1.5722656,1.5869141-1.5839844l36.3544922-0.5205078 c0.9013672-0.0332031,1.5761719,0.6855469,1.6328125,1.5888672c0.0390625,0.6289063,0.0693359,1.2617188,0.0859375,1.8916016 C61.4014854,62.6212692,60.6525688,63.3738251,59.7708397,63.3798904z"/>
				</svg>';
				break;
			case 'booking__agents':
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
					<path class="latepoint-step-svg-base" d="M53.4534083,0.0474242671 C53.0666895,-0.0961304329 52.6335841,0.0967406671 52.4866114,0.483947667 L50.3816309,6.05572497 C50.2351465,6.44342027 50.4309473,6.87603747 50.8181543,7.02252187 C51.2107248,7.16946117 51.6403055,6.96943747 51.7849512,6.58599847 L53.8899317,1.01422117 C54.0364161,0.626525867 53.8406153,0.193908667 53.4534083,0.0474242671 Z"></path>
					<path class="latepoint-step-svg-base" d="M55.1467677,9.54449457 L60.2917872,4.91949457 C60.5998927,4.64263907 60.624795,4.16851797 60.3479395,3.86041257 C60.0701075,3.55181877 59.5964747,3.52691647 59.2888575,3.80426027 L54.143838,8.42926027 C53.8357325,8.70611577 53.8108302,9.18023687 54.0876857,9.48834227 C54.3632441,9.79482267 54.8367587,9.82286737 55.1467677,9.54449457 Z"></path>
					<path class="latepoint-step-svg-base" d="M58.0530177,12.1817007 C58.1018458,12.5601187 58.4245997,12.8364859 58.7961818,12.8364859 C58.8279201,12.8364859 58.8601466,12.8345328 58.8923732,12.8306265 C60.810342,12.585021 62.7136623,11.9522085 64.3962795,11.0010376 C64.7566311,10.7974243 64.8840725,10.3399048 64.6799709,9.97906487 C64.4758693,9.61724847 64.0178615,9.49078357 63.6579982,9.69537347 C62.1428615,10.5518188 60.4289943,11.1211548 58.7019435,11.3423462 C58.2908106,11.3950796 58.0007716,11.7710562 58.0530177,12.1817007 Z"></path>
					<path class="latepoint-step-svg-base" d="M30.1647665,12.3430099 C34.8016087,11.2484035 39.4478623,14.1199381 40.5424644,18.7567618 C41.6370664,23.3935856 38.7655134,28.0398278 34.1286712,29.1344342 C29.491829,30.2290406 24.8455754,27.3575061 23.7509733,22.7206823 C22.6563712,18.0838585 25.5279243,13.4376163 30.1647665,12.3430099 Z M30.7048927,13.6876382 C26.8743165,14.5919117 24.5020759,18.4302508 25.406345,22.2608086 C26.3106141,26.0913663 30.1489646,28.4635885 33.9795408,27.5593151 C37.810117,26.6550416 40.1823577,22.8167025 39.2780886,18.9861448 C38.3738195,15.155587 34.535469,12.7833648 30.7048927,13.6876382 Z"></path>
					<path class="latepoint-step-svg-base" d="M21.9115992,61.4981718 C23.8270655,62.2352323 26.1083765,62.550601 28.0801173,62.8933134 C39.1328402,64.8145094 50.0195018,63.0462065 53.2110377,61.4772978 C54.3124781,60.935916 53.9811183,59.2539663 52.7560206,59.1805411 C50.270547,59.0314932 47.770608,59.1632071 45.3111353,59.5512114 C55.2235003,54.6875143 61.8597269,44.4488249 62.4270411,34.1118765 L62.4270411,34.1123648 C63.5544825,13.7695837 44.6203433,-0.201645833 26.3787013,3.15100097 C1.04216438,5.25931547 -5.22645982,35.1987143 4.08518218,48.907836 C7.82184888,54.4092207 14.728097,59.697505 21.9115992,61.4981718 Z M49.7043238,55.0174551 C38.1006632,64.1502943 22.8722105,61.8384047 13.4803858,53.7492056 C12.5408716,43.1234541 20.9689856,33.9107046 31.6687403,33.9107046 C42.9996081,33.9107046 51.4818011,44.1488142 49.7043238,55.0174551 Z M9.60721588,15.241271 C26.2435961,-6.79306413 62.4589091,6.43408397 60.9289942,34.029357 C60.8975687,34.1444121 60.8018961,44.9580946 51.3662501,53.6017447 C52.1936312,42.0003806 42.9873324,32.4107047 31.6687403,32.4107047 C20.7886057,32.4107047 11.8490992,41.2775069 11.9136133,52.293212 C2.00266698,42.3921652 1.59887988,25.849227 9.60721588,15.241271 Z"></path>
				</svg>';
				break;
			case 'booking__datepicker':
                $svg = '
<svg viewBox="0 0 73 73" xmlns="http://www.w3.org/2000/svg">
<g transform="translate(7, 3)">
<path d="M47.1718107,7.89381679 C52.9692893,8.00426143 55.2548263,8.16319441 55.8494015,8.78729154 C56.4084714,9.37436038 56.5587457,10.674785 56.5666362,18.6433642 L56.5667377,19.9578431 C56.5650641,22.2438919 56.5545013,25.0134286 56.5399042,28.3749748 C56.5247567,31.8632889 56.5174289,34.0063099 56.5157441,36.1120925 L56.5157866,38.0618721 C56.5223513,45.1710523 56.6058792,50.5892505 56.8057041,54.9084638 C56.8223695,55.3636597 56.8466881,55.7161849 56.9339613,56.8569807 C56.9488267,57.0515949 56.9488267,57.0515949 56.9635497,57.2475642 C57.1832093,60.1912764 57.1716188,61.1249641 56.5086328,61.7648729 C55.7178576,62.5281228 45.6780796,62.8940745 30.9900717,63.1124957 C28.4622528,63.1350156 26.0088374,63.1481854 23.6671818,63.1508875 L21.8184079,63.1508875 C10.573398,63.1361917 2.29081835,62.8595769 1.45589946,62.1867853 C0.422616649,61.3542409 0.176522348,56.743858 0.16272311,45.9171016 L0.162529532,43.9997362 C0.162998255,43.5049435 0.163835403,42.9984771 0.164997296,42.480145 C0.184719254,40.5900502 0.226987464,39.0029005 0.302213993,37.0682843 C0.323509476,36.520624 0.448910299,33.5345954 0.486126906,32.5610155 C0.530581302,31.3980964 0.56856845,30.2710758 0.603192323,29.054251 C0.609036029,28.8902091 0.614311564,28.728081 0.618994017,28.5667498 L0.634501665,27.9230284 C0.636539405,27.8155674 0.63829879,27.7077988 0.639772438,27.5993917 L0.643328791,27.2719236 L0.645126437,26.9386387 L0.645121089,26.5975514 C0.63897703,24.2932156 0.509680772,21.3363512 0.198181591,15.0795876 C0.11360591,13.380803 0.0560703546,12.1764262 0.00285028545,10.9612006 C-0.0710615129,9.27366985 1.30221783,7.87966142 2.9908793,7.92240124 L11.1998347,8.12849971 L11.1509194,10.0768074 L2.94176608,9.87070395 C2.38030589,9.85649346 1.92547718,10.3181876 1.94990546,10.8759262 C2.00292105,12.0864828 2.0602936,13.2874473 2.14469237,14.9826787 C2.45885226,21.292885 2.58909134,24.2704493 2.59452484,26.6106565 L2.59441121,26.9572443 L2.59246984,27.2962932 L2.58874505,27.6298247 L2.57991096,28.1241987 C2.57275052,28.4526755 2.56308122,28.7804566 2.55110258,29.1166387 C2.51651469,30.3330822 2.47831474,31.4664162 2.43362616,32.6354617 C2.39623022,33.6137329 2.27079341,36.6006183 2.24966392,37.1440099 C2.17511322,39.0612455 2.13332707,40.6302943 2.11386345,42.4924943 C2.09223299,52.15266 2.33067923,59.7135207 2.63602735,60.5908947 L2.64080943,60.6033321 L2.72722307,60.6210499 C2.82936816,60.6402001 2.95513892,60.6597074 3.10286495,60.6791131 L3.25784753,60.6984679 C3.81084169,60.7642327 4.58818592,60.824499 5.57046689,60.87833 C7.41400289,60.9793596 9.94954807,61.0563146 13.0322269,61.1091984 C18.3503337,61.2004314 25.171572,61.2153329 30.9669002,61.1637204 C32.4730262,61.1413215 34.0420274,61.1135131 35.6220724,61.0812071 L37.5201101,61.0403394 C40.6812837,60.9688229 43.8037015,60.8807747 46.4727101,60.7834888 C49.137361,60.6863619 51.3142024,60.5819179 52.8844672,60.4724191 C53.7218228,60.4140281 54.3791334,60.3546652 54.8391526,60.2954909 C54.9280418,60.2840567 55.0086179,60.2727368 55.0803586,60.2616787 L55.093112,60.2593474 L55.1050753,60.1555813 C55.1130251,60.0659089 55.1189359,59.964694 55.122671,59.8525766 L55.1266247,59.6763035 C55.1334678,59.1250416 55.0992775,58.454585 55.0200315,57.3925894 C55.0055018,57.1991931 55.0055018,57.1991931 54.9907178,57.0056427 C54.9010702,55.8338088 54.8761058,55.4719226 54.8584765,54.9891608 C54.6504368,50.4933722 54.5678124,44.8241074 54.5664588,37.3207349 L54.5668227,36.1107338 C54.5685089,34.0021415 54.5758432,31.8571901 54.591001,28.3665119 C54.6051508,25.1079543 54.6137277,22.7715891 54.6158244,20.9432538 L54.6157275,19.2689224 C54.6139566,18.0988906 54.6082485,17.1436294 54.5982019,16.2199609 L54.5942303,15.8747084 C54.5935342,15.8172939 54.5928209,15.7599133 54.5920905,15.702522 L54.5874999,15.3576873 C54.562925,13.5875286 54.5209008,12.2399822 54.4581817,11.2896609 C54.4260113,10.8022147 54.3886288,10.4249837 54.3474329,10.1659418 L54.3554452,10.2217587 L54.257178,10.2054082 C53.9357194,10.1549451 53.489879,10.1078562 52.9280578,10.0652057 L52.6802009,10.0472018 C51.3584329,9.9552879 49.494176,9.88733418 47.1346896,9.84238488 L47.1718107,7.89381679 Z M39.7566461,7.82743714 L40.4560543,7.82893932 L40.4508301,9.77785396 C40.2202657,9.77723591 39.9876287,9.77673679 39.7529527,9.77635537 L37.5867229,9.77606707 C31.9147477,9.78328085 25.2174608,9.85079506 17.9036566,9.96357131 L17.8736085,8.01488132 C25.1965684,7.90196389 31.9029228,7.83435897 37.5859747,7.82714445 L39.7566461,7.82743714 Z" id="Shape" fill-rule="nonzero"></path>
            <path d="M14.4133698,0.399513227 C16.7613963,0.373109614 18.6883346,2.25094044 18.7225705,4.59894423 L18.8534133,13.6684596 C18.7946147,15.4673213 17.3780922,16.9215052 15.6394273,16.9668065 L15.6128833,16.9671364 L13.6083401,16.9640399 C11.7503895,17.0325099 10.2703976,15.5459356 10.3310124,13.7247623 L10.2000452,4.72290599 C10.1656825,2.36401121 12.054343,0.426043277 14.4133698,0.399513227 Z M14.4352852,2.34831165 C13.1550605,2.36270929 12.1301125,3.41441577 12.1487603,4.69453641 L12.2793108,13.7424212 C12.2550586,14.4843839 12.8113661,15.0431657 13.573616,15.01578 L15.5883541,15.0181007 L15.7173406,15.0082885 C16.3115814,14.9288875 16.8107421,14.41562 16.8938022,13.7871237 L16.9051149,13.6507983 L16.7738555,4.62732463 C16.7552756,3.35305702 15.7095511,2.3339825 14.4352852,2.34831165 Z" id="Path" fill-rule="nonzero"></path>
            <path d="M43.5946856,0.000276463938 C45.9514787,-0.0261901957 47.8895852,1.85094592 47.9383592,4.2073461 L48.1241723,13.246058 C48.0646509,15.071064 46.552879,16.6164603 44.7406837,16.6832011 L44.7263547,16.6836233 L42.9728414,16.7222797 C41.1237436,16.7902408 39.5552272,15.2577756 39.4665948,13.3180986 L39.3471258,4.35353402 C39.3154917,1.97757329 41.2185634,0.0271217408 43.5946856,0.000276463938 Z M43.6166368,1.94907448 C42.3183969,1.96374191 41.2785908,3.02943555 41.2958745,4.32757566 L41.4144023,13.2603727 C41.4541415,14.1249885 42.1474663,14.8023777 42.9155157,14.7742535 L44.6832851,14.7351776 L44.6689561,14.7355998 C45.450301,14.7068239 46.1498968,13.9916678 46.1759774,13.234471 L45.9898552,4.24768908 C45.9632066,2.96022487 44.9042836,1.93461428 43.6166368,1.94907448 Z" id="Path" fill-rule="nonzero"></path>
            <path d="M15.614241,15.992189 L13.5725507,15.9898503 C12.2822671,16.0373065 11.2627862,15.0159742 11.3054676,13.7101967 L11.2425174,9.37862086 C11.2311162,9.37901064 11.219715,9.37930298 11.2083138,9.37969277 L1.66571136,9.67203101 C1.75390007,10.5661963 1.74834564,11.3472266 1.72018372,12.0024541 C1.66921942,12.5316837 1.64368855,12.7963473 1.54448844,13.4643402 C1.54448844,13.4643402 1.40572522,14.7245129 1.33790274,15.9719202 C1.28420995,16.8917138 1.29872942,18.1892084 1.60548968,19.8890578 C4.5848062,19.3512529 9.08778965,18.7335422 14.5443805,18.5287105 C20.0294255,18.3463889 23.6945676,18.7075241 27.042815,19.0374765 C28.0057771,19.1322916 28.9425263,19.224573 29.8888252,19.3006784 C35.6831642,19.7499048 44.3247802,19.9476229 55.0814634,18.2659959 C55.0474547,18.0771454 55.0282578,17.8667593 55.0091584,17.6562757 C54.990059,17.4458897 54.9708621,17.2354061 54.936756,17.0465556 C54.7818167,16.0588421 54.5465818,15.2032655 54.3256716,14.4003098 C53.8539351,12.6851613 53.4482671,11.2102174 54.0334308,9.20058687 C51.6685118,9.27727693 49.3527057,9.32200468 47.0705184,9.34500196 L47.1506191,13.2145859 C47.1077428,14.529231 46.0043608,15.6618468 44.7052097,15.7096928 L42.9374403,15.7487687 C41.6382891,15.7965173 40.5051861,14.6941097 40.4412615,13.3054056 L40.388738,9.35874186 C37.7828349,9.3465611 35.201878,9.31839918 32.620921,9.29013982 C27.7416983,9.23673936 22.8624755,9.18343636 17.8156454,9.23907807 L17.8795701,13.6361376 C17.8369861,14.9420126 16.8146793,15.9610063 15.614241,15.992189 Z" id="Path"></path>
            <path d="M13.5725507,15.9898503 L13.57372,15.0153895 C13.5614418,15.0153895 13.5490662,15.0155844 13.536788,15.0160716 L13.5725507,15.9898503 Z M15.614241,15.992189 L15.6130716,16.9666498 C15.6219392,16.9666498 15.6307094,16.9665524 15.639577,16.9663575 L15.614241,15.992189 Z M11.3054659,13.7101967 L12.2794412,13.7419641 C12.2799267,13.726665 12.2800259,13.711366 12.279831,13.6959695 L11.3054659,13.7101967 Z M11.2425174,9.37862086 L12.2167833,9.36439373 C12.212983,9.10304334 12.1043306,8.8540686 11.9151877,8.67359845 C11.7260449,8.49312831 11.4722953,8.39616946 11.2109449,8.40464727 L11.2425174,9.37862086 Z M11.2083138,9.37969277 L11.2381323,10.3536664 L11.2398864,10.3536664 L11.2083138,9.37969277 Z M1.66571136,9.67203101 L1.63589286,8.69795997 C1.36616211,8.70624289 1.11192528,8.82600413 0.933696395,9.02878942 C0.755564957,9.23147727 0.669520066,9.49896677 0.695927954,9.76762562 L1.66571136,9.67203101 Z M1.72018372,12.0024541 L2.69016202,12.0958074 C2.69172116,12.0787543 2.69298796,12.0616038 2.69367008,12.0443559 L1.72018372,12.0024541 Z M1.54448844,13.4643402 L0.580649239,13.3211919 C0.578797764,13.3333727 0.577238626,13.345456 0.575971827,13.3576367 L1.54448844,13.4643402 Z M1.33790274,15.9719202 L2.31070698,16.0287313 L2.31099932,16.0248334 L1.33790274,15.9719202 Z M1.60548968,19.8890578 L0.646522791,20.0621221 C0.692419895,20.3164563 0.837517112,20.5421415 1.04985212,20.6895774 C1.26208969,20.8369159 1.5243171,20.8939218 1.77865137,20.8480247 L1.60548968,19.8890578 Z M14.5443805,18.5287105 L14.5120284,17.5547369 L14.5078382,17.5549318 L14.5443805,18.5287105 Z M27.042815,19.0374765 L27.1383121,18.0676931 L27.1383121,18.0676931 L27.042815,19.0374765 Z M29.8888252,19.3006784 L29.8107709,20.2720209 L29.8134994,20.2722158 L29.8888252,19.3006784 Z M55.0814634,18.2659959 L55.2320176,19.2287632 C55.4913216,19.1882256 55.7232433,19.0448824 55.8754541,18.8310857 C56.0275674,18.617289 56.087107,18.3511638 56.0404303,18.0929316 L55.0814634,18.2659959 Z M54.936756,17.0465556 L53.9740861,17.197597 C53.9752555,17.2049055 53.9765223,17.2123114 53.9777891,17.2196199 L54.936756,17.0465556 Z M54.3256716,14.4003098 L53.3860965,14.6587368 L53.3860965,14.6587368 L54.3256716,14.4003098 Z M54.0334308,9.20058687 L54.9691081,9.47304611 C55.0563223,9.17320452 54.9947364,8.84978097 54.8033523,8.60314494 C54.6119682,8.3565089 54.3138806,8.21647888 54.0018582,8.22661328 L54.0334308,9.20058687 Z M47.0705184,9.34500196 L47.0606764,8.37054114 C46.8004953,8.37317218 46.5521053,8.4797782 46.370953,8.66658234 C46.1898007,8.85338648 46.090893,9.10499226 46.0962525,9.3651733 L47.0705184,9.34500196 Z M47.1506191,13.2145859 L48.1245927,13.2463533 C48.1251774,13.2290079 48.1252748,13.2116625 48.124885,13.1943171 L47.1506191,13.2145859 Z M44.7052097,15.7096928 L44.7267453,16.6839587 C44.7315201,16.6837638 44.736295,16.6836664 44.7410698,16.6834715 L44.7052097,15.7096928 Z M42.9374403,15.7487687 L42.9159047,14.7745028 C42.9111299,14.7746002 42.906355,14.7747951 42.9015801,14.7748925 L42.9374403,15.7487687 Z M40.4412615,13.3054056 L39.4668981,13.318366 C39.467093,13.3289876 39.4673853,13.3395118 39.4678725,13.3501334 L40.4412615,13.3054056 Z M40.388738,9.35874186 L41.3631014,9.34568408 C41.3559878,8.81440804 40.924594,8.38671719 40.3932205,8.38428104 L40.388738,9.35874186 Z M32.620921,9.29013982 L32.6315427,8.31577644 L32.6315427,8.31577644 L32.620921,9.29013982 Z M17.8156454,9.23907807 L17.8048289,8.26461725 C17.5458172,8.26754063 17.2985965,8.37336708 17.117834,8.55890442 C16.9369741,8.74434431 16.8374817,8.99419606 16.8412821,9.25320775 L17.8156454,9.23907807 Z M17.8795684,13.6361376 L18.8535437,13.6680025 C18.8540292,13.6527035 18.8541283,13.637307 18.8539334,13.6220079 L17.8795684,13.6361376 Z M13.5714788,16.9643111 L15.6130716,16.9666498 L15.6153129,15.0177282 L13.57372,15.0153895 L13.5714788,16.9643111 Z M10.331494,13.6783318 C10.2706876,15.5405264 11.7455341,17.032231 13.6084108,16.963629 L13.536788,15.0160716 C12.8189027,15.0424795 12.2549822,14.4914219 12.2794412,13.7419641 L10.331494,13.6783318 Z M10.268154,9.39275054 L10.3311042,13.7243263 L12.279831,13.6959695 L12.2167833,9.36439373 L10.268154,9.39275054 Z M11.2109449,8.40464727 C11.1995437,8.40503705 11.1881425,8.40542684 11.1766439,8.40571917 L11.2398864,10.3536664 C11.2511901,10.3532766 11.2625913,10.3528868 11.2739925,10.3525944 L11.2109449,8.40464727 Z M11.1784953,8.40571917 L1.63589286,8.69795997 L1.69552986,10.6460046 L11.2381323,10.3536664 L11.1784953,8.40571917 Z M0.695927954,9.76762562 C0.778269894,10.601959 0.773592482,11.3344612 0.746599917,11.9604548 L2.69367008,12.0443559 C2.72319625,11.3600895 2.72953024,10.5304335 2.63549477,9.57633896 L0.695927954,9.76762562 Z M0.750205422,11.9090033 C0.70002069,12.42995 0.676341292,12.6762937 0.580649239,13.3211919 L2.50842508,13.607391 C2.61103581,12.9164009 2.63832071,12.6335149 2.69016202,12.0958074 L0.750205422,11.9090033 Z M1.54448844,13.4643402 C0.575971827,13.3576367 0.575874381,13.3576367 0.575874381,13.3577285 C0.575874381,13.3577285 0.575874381,13.3577285 0.575874381,13.3578316 C0.575874381,13.3579291 0.575874381,13.3580265 0.575874381,13.358124 C0.575874381,13.3583189 0.575776935,13.3586112 0.575776935,13.359001 C0.575679489,13.3597805 0.575582043,13.360755 0.575387151,13.3621192 C0.575094813,13.3648477 0.574705028,13.3687456 0.574120352,13.3738128 C0.573048445,13.3839472 0.571489307,13.3986615 0.56944294,13.417761 C0.565350204,13.4558624 0.559600885,13.5113092 0.552389875,13.5815678 C0.538065301,13.7220851 0.518186301,13.9220444 0.496260932,14.161372 C0.452507641,14.6389553 0.399691865,15.2788837 0.364903613,15.919007 L2.31099932,16.0248334 C2.34393609,15.4175495 2.39451061,14.8036391 2.43709455,14.3393086 C2.4583378,14.1076792 2.47743723,13.9146385 2.49117712,13.7797732 C2.4980958,13.7124379 2.50365022,13.6597196 2.50745062,13.6240543 C2.5093021,13.6062217 2.51076379,13.5926767 2.51173825,13.5837116 C2.51222548,13.5792291 2.51261526,13.575916 2.51281016,13.5737721 C2.5129076,13.5727002 2.51300505,13.5719207 2.51310249,13.5714334 C2.51310249,13.5712385 2.51310249,13.5710436 2.51310249,13.5709462 C2.51310249,13.5709462 2.51310249,13.5709462 2.51310249,13.5709462 C2.51310249,13.5709462 2.51310249,13.5709462 2.51310249,13.5709462 C2.51310249,13.5709462 2.51310249,13.5709462 1.54448844,13.4643402 Z M0.365098506,15.9151092 C0.307410425,16.9036022 0.324658382,18.2788588 0.646522791,20.0621221 L2.56445658,19.7159936 C2.27270301,18.099558 2.26100948,16.8797279 2.31070698,16.0287313 L0.365098506,15.9151092 Z M1.77865137,20.8480247 C4.71928179,20.3172359 9.17636814,19.705372 14.5809227,19.5024892 L14.5078382,17.5549318 C8.99930861,17.7617124 4.45033061,18.3853673 1.43242544,18.9300909 L1.77865137,20.8480247 Z M14.5767326,19.5025867 C19.9959041,19.3225063 23.6094971,19.6783794 26.9472204,20.0071625 L27.1383121,18.0676931 C23.779638,17.7367662 20.062947,17.3703689 14.5120284,17.5547369 L14.5767326,19.5025867 Z M26.9472204,20.0071625 C27.9087208,20.1019775 28.8542402,20.195136 29.8107709,20.2720209 L29.9668795,18.3293358 C29.0308125,18.2541075 28.102736,18.162703 27.1383121,18.0676931 L26.9472204,20.0071625 Z M29.8134994,20.2722158 C35.6636749,20.7258273 44.3834427,20.9247148 55.2320176,19.2287632 L54.9310066,17.3032286 C44.2661176,18.9704336 35.7026534,18.7740798 29.9641511,18.3291409 L29.8134994,20.2722158 Z M56.0404303,18.0929316 C56.0154841,17.9541684 55.9996978,17.788705 55.9796239,17.568087 L54.0386929,17.7445619 C54.0569153,17.9448136 54.0794253,18.2001223 54.1225939,18.4391576 L56.0404303,18.0929316 Z M55.9796239,17.568087 C55.9614015,17.3678353 55.9388915,17.1125266 55.8957229,16.8734914 L53.9777891,17.2196199 C54.0028327,17.3583831 54.018619,17.523944 54.0386929,17.7445619 L55.9796239,17.568087 Z M55.8995233,16.8955142 C55.7349368,15.8465071 55.4851825,14.9416228 55.2652467,14.1418828 L53.3860965,14.6587368 C53.6078837,15.4649083 53.828794,16.2711771 53.9740861,17.197597 L55.8995233,16.8955142 Z M55.2652467,14.1418828 C54.7936077,12.4269292 54.4680403,11.1935541 54.9691081,9.47304611 L53.0978509,8.92812762 C52.4284938,11.2268807 52.91436,12.9433934 53.3860965,14.6587368 L55.2652467,14.1418828 Z M54.0018582,8.22661328 C51.6452222,8.30310845 49.336822,8.34773876 47.0606764,8.37054114 L47.080263,10.3193653 C49.3685894,10.2963681 51.6918014,10.2515429 54.0651008,10.1745605 L54.0018582,8.22661328 Z M48.124885,13.1943171 L48.0447844,9.32483062 L46.0962525,9.3651733 L46.1763532,13.2347572 L48.124885,13.1943171 Z M44.7410698,16.6834715 C46.5675992,16.6163312 48.0654429,15.0567066 48.1245927,13.2463533 L46.1766455,13.182721 C46.1499453,14.0017553 45.4411225,14.7074599 44.6693495,14.7359141 L44.7410698,16.6834715 Z M42.9588784,16.7229372 L44.7267453,16.6839587 L44.6836741,14.7354269 L42.9159047,14.7745028 L42.9588784,16.7229372 Z M39.4678725,13.3501334 C39.5537225,15.2178824 41.0862571,16.791929 42.973203,16.7225474 L42.9015801,14.7748925 C42.1903212,14.8011055 41.4565522,14.1703371 41.4147478,13.2605804 L39.4678725,13.3501334 Z M39.4143746,9.37170219 L39.4668981,13.318366 L41.4157223,13.2923479 L41.3631014,9.34568408 L39.4143746,9.37170219 Z M40.3932205,8.38428104 C37.7910204,8.37219772 35.2129868,8.34393836 32.6315427,8.31577644 L32.610202,10.2645032 C35.1907691,10.2927626 37.7747469,10.3210219 40.3841581,10.3331052 L40.3932205,8.38428104 Z M32.6315427,8.31577644 C27.753879,8.26237599 22.8641321,8.20887809 17.8048289,8.26461725 L17.8263645,10.2134414 C22.8609163,10.1578972 27.7295175,10.2112002 32.610202,10.2645032 L32.6315427,8.31577644 Z M18.8539334,13.6220079 L18.7900088,9.22485094 L16.8412821,9.25320775 L16.9052067,13.6503648 L18.8539334,13.6220079 Z M15.639577,16.9663575 C17.3849338,16.9209476 18.7949786,15.4599385 18.8535437,13.6680025 L16.9055965,13.6043702 C16.8788963,14.4239892 16.2444248,15.0010649 15.5888076,15.018118 L15.639577,16.9663575 Z" id="Shape" fill-rule="nonzero"></path>
            <path d="M55.2805457,18.1373671 L55.2805457,20.0862887 C51.2253356,20.0862887 47.9453954,20.1931133 41.9392932,20.4707132 C38.5194312,20.628778 37.8301843,20.6595372 36.3328823,20.7160376 C34.0824913,20.7969832 32.2624767,20.8250026 29.8448746,20.8285967 C29.7642528,20.8287165 29.6870109,20.8288137 29.6091273,20.8288904 L26.9129635,20.8295582 C24.6344138,20.8327132 23.1468091,20.8479545 21.0361871,20.8938071 C14.7156542,20.9908214 8.36997455,21.3300774 1.94602221,21.8804176 L1.77966737,19.9386088 C8.24724178,19.3845314 14.6380869,19.0428608 21.0000593,18.9452302 C23.1201913,18.8991544 24.6178414,18.8838193 26.9071273,18.8806419 L29.6071018,18.8799697 C29.6847208,18.8798934 29.7616753,18.8797966 29.8419773,18.8796772 C32.2388318,18.8761139 34.0373216,18.8484259 36.2611081,18.7684388 C36.5586499,18.7572108 36.8241741,18.7470016 37.0858918,18.7365907 L37.3473856,18.7260674 C38.309206,18.6869038 39.3462789,18.6395592 41.8493108,18.5238699 C47.8835084,18.2449714 51.1873918,18.1373671 55.2805457,18.1373671 Z" id="Path" fill-rule="nonzero"></path>
            <path d="M16.7217015,27.3064062 L18.6702618,27.3439305 C18.6678781,27.4677103 18.6642677,27.7034209 18.6606463,28.0366039 C18.6304478,30.8149698 18.6608793,33.9860897 18.798519,36.9876219 C18.8336322,37.7533417 18.8751495,38.4859006 18.9234083,39.1807492 C19.0997835,41.7196556 19.1056483,43.2274803 18.9825339,44.9472963 C18.9709268,45.1094374 18.9443907,45.439123 18.9156539,45.7900204 L18.8866549,46.1425688 C18.853067,46.5494782 18.8222586,46.9188676 18.8144421,47.0183664 C18.7312251,48.0776648 18.6733091,49.065122 18.6317587,50.2664762 C18.5517466,52.5798845 18.4902223,54.4834401 18.4448348,56.0035094 C18.4289586,56.5352203 18.4162347,56.9809501 18.4063694,57.3439941 C18.4004615,57.561406 18.3968111,57.7032356 18.3951636,57.7712486 L16.446814,57.724032 C16.4485345,57.6530686 16.4522078,57.5103519 16.458167,57.2910538 C16.4680801,56.9262479 16.4808537,56.4787769 16.4967814,55.9453428 C16.5422624,54.4221406 16.6038856,52.5155239 16.6840017,50.1991105 C16.7265875,48.967819 16.7861553,47.9521989 16.8715066,46.8657322 C16.8891133,46.6416101 17.0106175,45.1988463 17.0385868,44.8081372 C17.1549214,43.1830298 17.1494113,41.7663878 16.9791713,39.3157971 C16.9298059,38.6050159 16.88743,37.8573077 16.8516432,37.0768987 C16.7120306,34.0323426 16.6812752,30.8274682 16.7118398,28.015422 C16.7155223,27.6766167 16.7192159,27.4354788 16.7217015,27.3064062 Z" id="Path" fill-rule="nonzero"></path>
            <path d="M27.3731622,27.0118847 L29.3193453,27.1151666 C29.3143265,27.2097365 29.3054242,27.3918546 29.2939292,27.6517498 C29.2747079,28.0863285 29.255472,28.5791915 29.237511,29.1205544 C29.1530515,31.6662495 29.1260345,34.2983616 29.1907754,36.7519567 C29.2113775,37.5327498 29.2411307,38.2788444 29.2806074,38.9853664 C29.4216213,41.5083173 29.4077637,42.9914728 29.2669191,44.7294711 C29.239635,45.0661526 29.1282525,46.296357 29.1062434,46.555887 C29.0114988,47.6731094 28.947428,48.7073313 28.9025752,50.0041686 C28.8059506,52.7964598 28.8207043,54.9459719 28.9030185,56.5043623 L28.9211143,56.8196131 C28.9388366,57.1031297 28.9550379,57.2919151 28.9661164,57.3902092 L27.0294567,57.6084862 C27.0102919,57.4384465 26.9832661,57.1080363 26.9568099,56.607161 C26.8714216,54.9905712 26.8562791,52.7844186 26.9548188,49.9367857 C27.00085,48.6058746 27.066914,47.5394802 27.1642922,46.3912024 C27.1870626,46.122695 27.2979927,44.8974871 27.3243656,44.5720497 C27.4578603,42.9247479 27.4708887,41.5303394 27.3347219,39.0941094 C27.2941289,38.3676088 27.2636191,37.6025415 27.2425319,36.8033633 C27.1766307,34.3057945 27.2040225,31.6371647 27.2896611,29.0559299 C27.3078672,28.5071782 27.3273818,28.0071765 27.346911,27.565634 C27.3586662,27.2998565 27.3678475,27.1120325 27.3731622,27.0118847 Z" id="Path" fill-rule="nonzero"></path>
            <path d="M38.3910992,26.6835743 L40.3372807,26.7868853 C40.3322607,26.8814523 40.3233561,27.0635656 40.311858,27.3234546 C40.2926318,27.7580232 40.2733909,28.2508753 40.2554253,28.7922267 C40.170942,31.3379268 40.1439188,33.9700489 40.2086818,36.4236521 C40.2292908,37.2044425 40.2590535,37.9505343 40.2985445,38.6570869 C40.4395109,41.1799471 40.4256313,42.6630774 40.2847718,44.4011095 C40.2574895,44.7377393 40.1461159,45.9677931 40.124104,46.227342 C40.0293474,47.3446485 39.9652704,48.3789293 39.9204145,49.6758561 C39.8238427,52.4680037 39.8385963,54.6174733 39.9208792,56.1758945 L39.93897,56.4911824 C39.9566873,56.7747341 39.9728843,56.9635478 39.9839603,57.0618622 L38.0472902,57.2800465 C38.0281326,57.1099974 38.001116,56.779563 37.9746685,56.2786523 C37.8893141,54.6620578 37.8741719,52.4559723 37.9726575,49.6084898 C38.0186925,48.2774695 38.0847629,47.2110134 38.1821535,46.062649 C38.2049266,45.7941246 38.3158481,44.5690638 38.3422194,44.2436745 C38.475729,42.5963303 38.4887782,41.201941 38.352659,38.7658308 C38.3120529,38.0393272 38.2815331,37.2742571 38.2604387,36.4750761 C38.1945146,33.9774856 38.2219129,31.3088348 38.307576,28.7275841 C38.3257869,28.1788417 38.3453066,27.6788489 38.364841,27.2373145 C38.3765993,26.9715417 38.385783,26.7837208 38.3910992,26.6835743 Z" id="Path" fill-rule="nonzero"></path>
            <path d="M49.5802303,35.3309928 L49.598769,37.2798262 L49.5439287,37.2803632 C49.5121701,37.2806857 49.458121,37.2812475 49.3320065,37.2825583 C49.0298119,37.2858224 48.861178,37.2877188 48.5762808,37.2911107 C47.7620828,37.3008044 46.8547498,37.312943 45.8775915,37.3276945 C43.0859821,37.3698377 40.2944933,37.4244484 37.6899084,37.4928542 C33.5462411,37.6016818 30.2781598,37.7350911 28.2383983,37.8936471 C25.5103329,38.1333211 23.9026387,38.1744193 22.0145718,38.0942393 C21.8006018,38.0851527 21.5805191,38.0746666 21.2975611,38.0603306 C21.2019167,38.0554848 20.7030132,38.0297764 20.5571007,38.0224251 C19.019186,37.9449428 17.8360443,37.9111878 16.1712138,37.9157997 C13.6960432,37.9226565 11.6627218,37.9785618 10.0401755,38.0672817 C9.43571544,38.1003333 8.93305258,38.1356196 8.52757631,38.1707263 C8.28931146,38.1913556 8.13897287,38.206976 8.07195142,38.2151688 L7.83547287,36.2806472 C7.92555209,36.2696358 8.09866806,36.2516489 8.35946494,36.2290687 C8.78575822,36.1921597 9.30897856,36.1554302 9.93376856,36.1212671 C11.5907736,36.030663 13.6577033,35.9738336 16.1658149,35.9668855 C17.8686942,35.9621682 19.0851984,35.9968751 20.6551657,36.0759722 C20.8024843,36.0833943 21.3017583,36.1091218 21.3961763,36.1139055 C21.674276,36.1279954 21.8894473,36.1382475 22.0972615,36.1470726 C23.8988638,36.2235808 25.4221422,36.1846405 28.0775728,35.9513971 C30.1697219,35.7887184 33.4634267,35.6542632 37.6387403,35.5446044 C40.2514195,35.475986 43.0498567,35.4212394 45.8481732,35.3789949 C46.827489,35.3642108 47.7368819,35.3520446 48.553079,35.3423272 C48.83882,35.3389252 49.0079108,35.3370237 49.3112916,35.3337468 C49.3746731,35.333088 49.4199456,35.3326174 49.4533818,35.3322708 L49.5244536,35.3315388 C49.5324716,35.3314574 49.5390987,35.3313909 49.5451189,35.3313314 L49.5802303,35.3309928 Z" id="Path" fill-rule="nonzero"></path>
            <path d="M49.5206906,45.5122569 L49.5392295,47.4610903 L49.4843891,47.4616273 C49.4526304,47.4619498 49.3985811,47.4625116 49.272466,47.4638225 C48.9702702,47.4670867 48.8016356,47.4689832 48.516737,47.4723753 C47.7025355,47.4820695 46.7951985,47.4942091 45.818036,47.5089621 C43.0264143,47.5511095 40.2349134,47.6057274 37.6303172,47.6741443 C33.4867023,47.7829878 30.2186468,47.9164202 28.1691189,48.0758151 C25.3921036,48.2917467 23.7846821,48.327805 21.8952618,48.2546316 C21.6892184,48.2466519 21.4773823,48.2375101 21.2039696,48.2250095 C21.1187894,48.221115 20.6340644,48.1986087 20.4899724,48.1920662 C18.9603562,48.1226137 17.7815302,48.0925357 16.1117716,48.0971613 C13.6365435,48.1040181 11.6032053,48.1599414 9.98067367,48.2486911 C9.376235,48.2817529 8.87359444,48.3170501 8.46814028,48.3521675 C8.22989084,48.3728029 8.07956521,48.3884277 8.01255319,48.3966224 L7.77598689,46.4621116 C7.86606607,46.4510961 8.0391783,46.4331029 8.29996885,46.4105151 C8.726248,46.373594 9.24945384,46.3368527 9.87423009,46.3026785 C11.5312332,46.2120432 13.5981919,46.1551952 16.1063727,46.1482471 C17.8105503,46.1435261 19.0196635,46.174377 20.5783725,46.2451504 C20.7237066,46.2517493 21.2088472,46.2742749 21.2929826,46.2781216 C21.5622497,46.2904327 21.7699015,46.299394 21.9706831,46.3071699 C23.7825454,46.3773396 25.3145403,46.3429733 28.0180189,46.1327597 C30.1101887,45.9700485 33.4038735,45.8355696 37.579141,45.7258947 C40.1918334,45.6572651 42.9902845,45.6025112 45.7886149,45.5602625 C46.7679355,45.545477 47.6773329,45.5333098 48.4935341,45.5235918 C48.7792765,45.5201896 48.9483681,45.518288 49.2517505,45.515011 C49.3785142,45.5136933 49.4328415,45.5131286 49.4649136,45.5128029 L49.5206906,45.5122569 Z" id="Path" fill-rule="nonzero"></path>
            </g></svg>';
                break;
			case 'customer':
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
					<path class="latepoint-step-svg-highlight" d="M36.270771,27.7026501h16.8071289c0.4140625,0,0.75-0.3359375,0.75-0.75s-0.3359375-0.75-0.75-0.75H36.270771 c-0.4140625,0-0.75,0.3359375-0.75,0.75S35.8567085,27.7026501,36.270771,27.7026501z"/>
					<path class="latepoint-step-svg-highlight" d="M40.5549507,42.3081207c0,0.4140625,0.3359375,0.75,0.75,0.75h12.6015625c0.4140625,0,0.75-0.3359375,0.75-0.75 s-0.3359375-0.75-0.75-0.75H41.3049507C40.8908882,41.5581207,40.5549507,41.8940582,40.5549507,42.3081207z"/>
					<path class="latepoint-step-svg-highlight" d="M45.6980171,51.249527H29.9778023c-0.4140625,0-0.75,0.3359375-0.75,0.75s0.3359375,0.75,0.75,0.75h15.7202148 c0.4140625,0,0.75-0.3359375,0.75-0.75S46.1120796,51.249527,45.6980171,51.249527z"/>
					<path class="latepoint-step-svg-highlight" d="M62.1623726,11.5883932l0.3300781-3.3564453c0.0405273-0.4121094-0.2607422-0.7792969-0.6728516-0.8193359 c-0.4091797-0.0458984-0.77882,0.2597656-0.8203125,0.6728516l-0.3300781,3.3564453 c-0.0405273,0.4121094,0.2612305,0.7792969,0.6733398,0.8193359 C61.7317963,12.3070383,62.1204109,12.0155325,62.1623726,11.5883932z"/>
					<path class="latepoint-step-svg-highlight" d="M63.9743843,13.9233541c1.1010704-0.3369141,2.0717735-1.0410156,2.7333946-1.9814453 c0.2382813-0.3388672,0.1567383-0.8066406-0.1816406-1.0449219c-0.3383789-0.2392578-0.8066406-0.1572266-1.0449219,0.1816406 c-0.4711914,0.6699219-1.1621094,1.1708984-1.9462852,1.4111328c-0.3959961,0.1210938-0.6186523,0.5400391-0.4975586,0.9365234 C63.1588402,13.8212023,63.5774651,14.0450754,63.9743843,13.9233541z"/>
					<path class="latepoint-step-svg-highlight" d="M68.8601227,17.4516735c0.0356445-0.4121094-0.2695313-0.7763672-0.6826172-0.8115234l-3.859375-0.3349609 c-0.4072227-0.0390625-0.7758751,0.2695313-0.8115196,0.6826172c-0.0356445,0.4121094,0.2695313,0.7763672,0.6826134,0.8115234 l3.859375,0.3349609C68.4594727,18.1708145,68.8244781,17.8649578,68.8601227,17.4516735z"/>
					<path class="latepoint-step-svg-highlight" d="M4.7497134,18.4358044c1.0574932,1.9900436,1.9738078,2.5032253,13.2814941,11.7038574 c0.5604858,11.4355488,0.9589844,22.8789082,1.1829224,34.3259277c0.3128052,0.1918945,0.6256714,0.3835449,0.9384766,0.5751953 c0.1058846,0.3764038,0.416275,0.5851364,0.7949219,0.5466309c12.6464844-1.4892578,25.8935547-2.0419922,40.4916992-1.6767578 c0.4600639-0.0021172,0.763813-0.3514481,0.7685547-0.7421875c0.1805725-16.3819695-0.080349-32.8599472,0.0605469-49.1875 c0.003418-0.3740234-0.2685547-0.6923828-0.6376953-0.7480469c-14.1435547-2.140625-28.5092773-2.3291016-42.6953125-0.5664063 c-0.331604,0.0407715-0.5751953,0.2971191-0.6331177,0.6113281c-0.3464966,0.277832-0.6930542,0.5556641-1.0396118,0.8334961 c0.1156616,1.137207,0.0985718,2.392333,0.1765137,3.5629873c-2.2901011-1.8925772-4.5957651-3.8081045-6.9354258-5.7802725 c-0.7441406-0.6269531-1.6889648-0.9277344-2.683105-0.8378906C4.4105406,11.3600969,3.320657,15.7476349,4.7497134,18.4358044z M60.7629585,14.6196432c-0.1265907,15.9033155,0.1148987,31.8954544-0.046875,47.7734375 c-14.0498047-0.3193359-26.8598633,0.2099609-39.1044922,1.6074219c0.0154419-10.8208008-0.2228394-21.3803711-0.6828613-31.503418 c8.6963615,7.0753174,9.1210613,7.5400124,10.6517334,8.1962891c2.7804565,1.1923828,7.8590698,1.5974121,8.4487305,0.6987305 c0.0741577-0.0522461,0.1495361-0.1047363,0.2015381-0.1826172c0.1469727-0.2207031,0.1669922-0.5029297,0.0517578-0.7412109 c-1.0354347-2.1505203-2.3683548-6.0868149-3.1914063-6.7568359c-5.5252628-4.5023842-10.581501-8.5776329-16.84375-13.7214375 c-0.1300049-1.973877-0.2654419-3.9484863-0.4165039-5.9221182C33.4343452,12.4419088,47.1985054,12.6274557,60.7629585,14.6196432 z M9.5368834,13.0405416c9.0454321,7.6246099,17.5216217,14.4366217,26.5917969,21.8203125 c0.3883591,0.3987503,1.5395088,3.3786926,2.2700195,5.078125c-1.4580688-0.1650391-2.9936523-0.479248-4.7089233-0.8842773 c0.4859009-0.9790039,1.1461182-1.8769531,1.953064-2.6108398c0.3061523-0.2783203,0.3286133-0.7529297,0.0498047-1.0595703 c-0.2783203-0.3046875-0.7519531-0.328125-1.0595703-0.0498047c-0.9295654,0.8461914-1.6932373,1.8774414-2.2598877,3.0026855 c-8.9527779-7.1637478-17.1909065-14.1875877-25.8739014-21.1394062c-0.5556641-0.4443359-0.8725586-1.09375-0.8481445-1.7363272 C5.7526169,12.8167362,8.1288319,11.8543167,9.5368834,13.0405416z"/>
				</svg>';
				break;
			case 'payment__times':
			case 'payment__portions':
			case 'payment__methods':
			case 'payment__processors':
			case 'payment__pay':
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
					<path class="latepoint-step-svg-highlight" d="M58.6511116,6.1223307l-0.2675781,2.7509766c-0.0427284,0.4397869,0.3022537,0.8222656,0.7470703,0.8222656 c0.3818359,0,0.7080078-0.2900391,0.7451172-0.6777344l0.2675781-2.7509766 c0.0400391-0.4121094-0.2617188-0.7792969-0.6738281-0.8183594C59.0612679,5.3947916,58.6901741,5.7092447,58.6511116,6.1223307z" />
					<path class="latepoint-step-svg-highlight" d="M60.9724007,11.0764322c0.296711,0.2927561,0.7712784,0.2872667,1.0605469-0.0058594 c1.0693359-1.0820313,1.8466797-2.4306641,2.2470665-3.8984375c0.109375-0.3994141-0.1269531-0.8115234-0.5263634-0.9208984 c-0.4082031-0.1083984-0.8125,0.1269531-0.9208984,0.5263672c-0.3330078,1.2197266-0.9785156,2.3398438-1.8662109,3.2382813 C60.6755257,10.3108072,60.6774788,10.7854166,60.9724007,11.0764322z"/>
					<path class="latepoint-step-svg-highlight" d="M68.802475,10.2619791c-0.1806641-0.3710938-0.6279297-0.5253906-1.0029297-0.3466797l-4.2695274,2.0771484 c-0.3720703,0.1816406-0.5273438,0.6308594-0.3466797,1.0029297c0.1800232,0.3695202,0.6266098,0.5278702,1.0029259,0.3466797 l4.2695313-2.0771484C68.8278503,11.0832682,68.983139,10.6340494,68.802475,10.2619791z"/>
					<path class="latepoint-step-svg-highlight" d="M56.075428,39.6298981l-0.0135498,0.1000977c-1.02771,0.3820801-1.6018066,1.6784668-1.2001343,2.6987305 c0.4017334,1.0202637,1.6987915,1.5778809,2.7179565,1.173584c1.019165-0.404541,1.581665-1.692627,1.1917114-2.7172852 C58.3814583,39.8601227,57.1116829,39.2714996,56.075428,39.6298981z"/>
					<path class="latepoint-step-svg-highlight" d="M67.1153412,64.6347809c0.3217163-0.7180176-0.0892334-1.5942383-0.7265625-2.0559082 c-0.3763428-0.2724609-0.8133545-0.4296875-1.2661743-0.5449219c0.4932785-1.2028122,0.3154755,0.6508713,0.4796753-37.815918 c0.0175247-3.8000011-0.7661972-6.7081814-4.6874352-7.2695313c-0.3728027-0.1738281-0.7583618-0.3242188-1.1530762-0.456543 c0.0695915-1.4608269-0.0228233-2.4685307-0.0032349-3.5571299c0.0311775-1.7980299-1.4539566-3.2119141-3.1962891-3.2119141 c-0.0029297,0-0.0058594,0-0.0087891,0L17.7292366,9.8449869c-3.6554623,0.0112343-7.4443989,0.1655378-10.0129395,2.8173828 c-1.4490428,1.00739-2.4756026,2.9240465-2.9685669,4.6687021c-0.8636329,3.0560856-0.6394863,1.955822-0.4553223,44.1296387 c0.0185671,4.2640686,1.1058459,5.8280563,6.0576177,5.918457c18.1763916,0.3305664,36.4078979,0.4030762,54.4744225-1.6201172 C65.7114716,65.6596832,66.750412,65.4494781,67.1153412,64.6347809z M10.1530647,12.6457682 c2.2675781-1.2832031,5.0898438-1.2929688,7.5800781-1.3007813l38.8242188-0.1220703c0.0019531,0,0.0039063,0,0.0048828,0 c0.9442444,0,1.7127266,0.7628899,1.6962891,1.6855469c-0.0167885,0.973794,0.0510406,1.9935045,0.0214844,3.1801767 c-3.1493874-0.6768255-2.4396057-0.4888554-44.4998169-0.6098642c-0.5518799-0.0014648-5.0442505,0.4206543-6.5944219,1.3168955 C7.4678226,15.1682291,8.5861702,13.5339518,10.1530647,12.6457682z M64.0123749,45.5925446l-5.2597008,0.0493164 c-3.4698677,0.0267563-7.8461227-0.6362991-7.4550781-4.0878906c0.2425804-2.1451874,2.5993347-3.0465698,4.7382813-3.3955078 c2.6318359-0.4296875,5.3945313-0.3251953,7.9882774,0.3017578c0.0061646,0.0014648,0.012085-0.0004883,0.0182495,0.0007324 L64.0123749,45.5925446z M64.0487518,36.9409332c-2.6920738-0.6071777-5.5366783-0.7060547-8.2550621-0.2629395 c-2.8740196,0.470295-5.6615906,1.8131523-5.9863281,4.7080078c-0.5018425,4.4379425,4.47435,5.7899628,8.9589844,5.7558594 l5.2397423-0.0490723c-0.0889435,13.624691,0.1381378,14.0157204-0.5004845,14.7600098 c-0.4492188,0.5253906-2.2080078,1.0888672-3.2431641,1.1425781c-17.3261032,0.8932877-33.7187004,1.8238754-50.8261719,0.8164063 c-0.8339844-0.0488281-1.4882817-0.7509766-1.4912114-1.5986328C7.9190578,52.4376526,6.8739986,19.3938637,7.102283,19.0354176 c1.2720323,0,6.8894105-0.2661171,25.2783203-0.2939453c8.4413376-0.0108852,17.2458305-0.0266666,25.7978516-0.3779297 C65.4974823,18.0765209,64.0197983,20.7003078,64.0487518,36.9409332z"/>
					</svg>';
				break;
			case 'verify':
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80">
					<path class="latepoint-step-svg-base" d="M14.1105938,17.6527386h21.4086933c0.4140625,0,0.75-0.3359375,0.75-0.75s-0.3359375-0.75-0.75-0.75H14.1105938 c-0.4140625,0-0.75,0.3359375-0.75,0.75S13.6965313,17.6527386,14.1105938,17.6527386z"/>
					<path class="latepoint-step-svg-base" d="M48.0480957,22.5179729c0.190918-4.6103516-0.2402344-8.1689453-1.3554688-11.2001953 c-1.9773369-5.3880882-10.6812592-6.6263709-16.4194965-6.88623c-2.2271042-0.3552918-3.4171219-0.4732823-23.8388062-0.9545901 C5.5955906,3.4306827,5.2978926,3.7840867,5.309813,4.2435594c0.4078836,15.8521996,0.3535037,38.6989517,0.1298828,54.6308594 c0.0489416,0.1005783,0.1066036,0.7338486,0.7416992,0.7373047c0.0014648,0,0.003418,0,0.0048828,0 c0.1726775,0,19.3874683-0.9524536,39.9575195,1.1923828c0.5861588,0.0651283,1.0673027-0.5827713,0.6965942-1.1501465 c-0.3957596-2.2545013-0.4755592-3.6757584-0.5795288-5.1481934c0.0477905-0.0227051,0.0947876-0.0480957,0.1424561-0.0710449 c2.0167389,2.6554184,8.5339165,10.8789749,11.3917847,12.6982422c0.7129517,0.4538574,1.5125732,0.8005371,2.3395996,0.9714355 c4.5379868,1.9745102,8.1917953-3.4511719,5.8001099-6.3081055c-4.0245361-4.8284912-8.767334-10.3620605-13.5692749-15.0280762 c1.0654297-2.1257324,1.6327515-4.5004883,1.6327515-6.911377c0-4.8347168-2.2924194-9.3981953-6.1298218-12.3183613 c0.0004272-0.0112305,0.0014648-0.0220947,0.0018921-0.0332031 C47.9866676,24.0398521,48.0113487,23.3549309,48.0480957,22.5179729z M45.2601929,59.2135315 c-12.4361572-1.2451172-25.3148212-1.6257324-38.3179321-1.1262207c0.02246-8.7914352,0.4327807-31.9077263-0.112915-53.0991211 c20.4045773,0.4872842,21.7616024,0.5873499,24.1508789,1.0756836c1.9755001,0.4037867,3.2904224,4.9198499,5.040041,6.5957026 c0.3312874,0.3179483,0.834362,0.2433729,1.1196289-0.0429688c1.8201218-1.8236427,4.0447845-4.2757235,6.2490234-3.3017578 c0.7670898,0.3339844,1.4047852,1.1816406,1.8959961,2.5205078c1.0449219,2.8398438,1.4467773,6.2138672,1.2641602,10.6191406 c-0.0358124,0.8280945-0.0610733,1.5315475-0.1461792,4.076416c-2.3810425-1.4171143-5.0792236-2.1643066-7.8845825-2.1643066 c-3.1671143,0-6.135437,0.9802246-8.6168232,2.6494141c-0.4119091-0.311924,0.2382946-0.0890408-15.7840576-0.3027344 c-0.0024414,0-0.0048828,0-0.0068359,0c-0.4111328,0-0.7460938,0.3310547-0.75,0.7431641 c-0.0039063,0.4140625,0.3291016,0.7529297,0.7431641,0.7568359l14.081665,0.1290283 c-2.8327827,2.5395775-5.5364246,7.2262096-5.8631592,11.064333l-10.6237793,0.2597656 c-0.4140625,0.0107422-0.7412109,0.3544922-0.7314453,0.7685547c0.0102539,0.4072266,0.34375,0.7314453,0.7495117,0.7314453 c0.0063477,0,0.0126953,0,0.019043,0l10.5239258-0.2573242c-0.0244522,3.6942863,0.6843319,7.0339737,3.2225342,10.0561523 l-11.5189209,0.1054688c-0.4140625,0.0039063-0.7470703,0.3427734-0.7431641,0.7568359 c0.0039063,0.4121094,0.3388672,0.7431641,0.75,0.7431641c0.0019531,0,0.0043945,0,0.0068359,0l12.9440308-0.1186523 c0.0007935,0.0007324,0.0015259,0.0014648,0.0023193,0.0021973c3.6866817,3.1902428,7.7025356,4.4405403,11.8752575,4.1297493 c1.9718208-0.146862,3.978672-0.6423225,6.0023689-1.4463997C44.890686,56.5292053,45.0510254,57.889801,45.2601929,59.2135315z  M64.7839355,62.7582092c1.643486,1.9650421-1.8606987,5.9641113-4.7329102,3.5546875 c-0.2494545-0.2046814-7.4860306-8.2930336-12.2422485-14.1032715c1.5042725-1.1379395,2.7863159-2.5305176,3.7785034-4.102417 C56.248291,52.6703186,60.8580322,58.0475159,64.7839355,62.7582092z M52.498291,39.856842 c0,7.7039337-6.2337532,13.9804688-13.9799805,13.9804688c-7.7138691,0-13.989748-6.2714844-13.989748-13.9804688 c0-7.7516708,6.3275547-13.9902363,13.989748-13.9902363C46.3522835,25.8666058,52.498291,32.2686691,52.498291,39.856842z"/>
					<path class="latepoint-step-svg-base" d="M61.0549316,64.0072327c0.2964249,0.2864761,0.7709198,0.2816391,1.0605469-0.0175781 c0.2875977-0.2978516,0.2792969-0.7734375-0.0185547-1.0605469l-1.0400391-1.0039063 c-0.2978516-0.2880859-0.7734375-0.2773438-1.0605469,0.0195313c-0.2875977,0.2988281-0.2788086,0.7734375,0.0195313,1.0605469 L61.0549316,64.0072327z"/>
					<path class="latepoint-step-svg-base" d="M38.798584,28.5873089c-6.2089844,0-11.2602558,5.055666-11.2602558,11.2695332 c0,6.2089844,5.0512714,11.2597656,11.2602558,11.2597656c6.2009888,0,11.2597656-5.036171,11.2597656-11.2597656 C50.0583496,33.6183395,44.9775581,28.5873089,38.798584,28.5873089z M38.798584,49.6166077 c-5.3818359,0-9.7602558-4.3779297-9.7602558-9.7597656c0-5.3867188,4.3784199-9.7695332,9.7602558-9.7695332 c5.343029,0,9.7597656,4.3516827,9.7597656,9.7695332C48.5583496,45.2636604,44.1625519,49.6166077,38.798584,49.6166077z"/>
					<path class="latepoint-step-svg-base" d="M44.651123,39.0619202c-4.2592773-0.2041016-6.421875-0.2050781-10.8295898,0.1923828 c-0.4125977,0.0371094-0.7167969,0.4023438-0.6796875,0.8144531c0.0351563,0.3896484,0.3623047,0.6826172,0.7460938,0.6826172 c0.0229492,0,0.0454102-0.0009766,0.0683594-0.0029297c4.3188477-0.3916016,6.440918-0.3886719,10.6225586-0.1884766 c0.4106445,0.0498047,0.765625-0.2998047,0.7851563-0.7128906C45.3840332,39.4330139,45.0646973,39.0814514,44.651123,39.0619202z "/>
				</svg>';
				break;
			case 'confirmation':
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80">
					<path class="latepoint-step-svg-base" d="M17.6552105,33.4646034C8.2132654,33.6182289,3.8646491,39.9382057,3.773782,46.3166199 C3.6704469,53.57024,9.073472,60.8994293,18.7539654,59.3212318c0.0535278,1.8059692,0.1070557,3.6119995,0.1605835,5.4179649 c0.4868374,0.7426834,0.9158726,1.2552795,1.3218193,1.5758286c0.7646008,0.6037445,1.4473019,0.5261841,2.2800751,0.0214233 c0.9628239-0.5835876,2.1262512-1.7382126,3.8487892-3.0711861c1.3595581,1.338192,2.7954102,3.2556725,3.8725586,4.7504234 c0.6969604,0.1324463,1.3938599,0.2648926,2.0908184,0.3973389c0.354744,0.2420731,0.7306252,0.1458817,0.9553833-0.0870972 c1.1480217-1.1914139,0.2770538-0.5825653,5.0960693-4.9796104c1.381897,1.3053551,3.0732422,3.0024986,4.1270752,4.464901 c2.8935661,0.5499954,2.7743301,0.7335205,3.1699219,0.4522095c0.2846146-0.2016754,0.2662773-0.1645584,0.3554688-0.2646484 c1.3665047-1.5280838,3.0428238-3.2071915,4.854248-5.0933189c1.8391113,1.4305992,3.5415039,2.966732,5.0125732,4.6672935 c0.8833618,0.1398926,1.7667236,0.2797241,2.6500854,0.4195557c0.3787956,0.0587921,0.647274-0.1178513,0.7819214-0.3831787 c0.6037369-1.1866455,1.2043419-2.4298172,1.9224854-3.9011192c1.3636475,1.03265,2.6345825,2.1318321,3.7449989,3.3383751 c0.520752,0.0775146,0.9672852,0.0211792,1.4367676,0.0062256c0.6980667,0.5534744,1.3601151,0.1294708,1.392334-0.4434814 c1.1637878-20.9316826-0.4478302-32.0234108-1.8408203-43.4101563 c-1.0667953-8.7491531-3.4310074-16.6642761-17.6171913-18.6894531 C37.5750961,2.9660594,18.2152557,2.0518365,10.3015718,9.4919462 c-3.7495093,3.4759312-5.6556306,13.6249208-5.8579102,18.3261719c-0.0175781,0.4130859,0.3032227,0.7636719,0.7167969,0.78125 c0.0008545,0,0.0019531-0.0001831,0.0028076-0.0001831c0.0002441,0,0.0003662,0.0001831,0.0006104,0.0001831 c0.0022583,0.0003052,0.0042114-0.0008545,0.0064697-0.0005493c1.7694812,0.0453014,8.2837915-2.8392754,13.4412851-1.0584106 c0.3204956,1.9219971,0.4412842,3.8793335,0.4950562,5.8326435 C18.6154156,33.3746986,18.1323223,33.4094276,17.6552105,33.4646034z M19.1414165,57.7614784 c-7.5994434,0-11.3555832-5.7171745-11.3348923-11.4369698c0.0206909-5.7197952,3.8182158-11.4422112,11.3261032-11.4526787 c0.0092773,0,0.0180664,0,0.0273438,0c6.2543888,0,11.4311523,5.0988808,11.4311523,11.4394531 C30.5911236,52.5667496,25.5261116,57.7614784,19.1414165,57.7614784z M48.1580162,5.9938989 c13.5598068,1.9365721,15.3743439,9.4665871,16.3403358,17.3867188c0.7182922,5.8958893,3.0389252,18.635561,1.8983765,41.6446533 c-1.2305298-1.1603355-2.6870155-2.8059044-4.0233803-4.5684776c-0.3519096-0.4632568-1.1312485-0.3892365-1.3088379,0.2573853 c-0.0006714,0.0013428-0.0020142,0.0020142-0.0026855,0.0033569c-0.829628,1.6306496-1.5776443,3.2193794-2.6342773,5.3439903 c-1.9974098-2.2269859-3.4938774-3.9506302-5.3305054-5.9934654c-0.1636276-0.8107109-1.4189148-0.82724-1.5952148-0.0100098 c-1.9148636,2.1023941-4.205822,4.3376503-6.1530762,6.4651451c-1.4751854-1.9926682-3.3123169-4.1955643-4.62323-6.0411949 c-0.2008209-0.5232658-0.8574333-0.635643-1.2301025-0.258606c-2.1993942,2.222168-4.5591049,4.0396156-6.7687988,6.4904747 c-1.3328838-1.4328613-3.3396587-3.9911461-4.4924297-5.7590294c-0.2881527-0.4409218-0.9600582-0.4756927-1.2632446,0.0197754 c-1.7325058,1.1738968-2.8503933,2.218853-4.8071289,3.6727867l0.09198-5.7758751 c5.7322388-1.4144287,9.8353252-6.5934448,9.8353252-12.5602417c0-5.9226074-4.0585918-11.0758057-9.8167706-12.5380249 c-0.1152134-4.2746181-0.3553181-14.4360523-1.6055908-18.5303345c-0.6845055-2.2400188-2.8216324-5.7650404-5.5857553-7.1168213 C21.5624371,4.8990502,34.3388634,4.0191674,48.1580162,5.9938989z M6.0422945,26.9650288 c0.2917447-3.411478,1.0564828-7.6568089,2.2514648-10.9311523c0.883728-0.4779043,1.4030762-0.8288565,1.9675293-0.7024527 c0.9700317,0.2299805,1.9000244,1.0199575,2.710022,1.5799551c2.9155273,2.0056763,4.5519419,5.618042,5.333375,8.9669189 C13.8285227,24.7062149,8.9758253,26.2891541,6.0422945,26.9650288z"/>
					<path class="latepoint-step-svg-base" d="M20.168272,46.12183c-1.4780273-0.424263-3.6082001-0.2521667-4.2836924-1.4824219 c-0.4052734-0.7392578,0.0585938-1.7636719,0.7285166-2.2216797c0.9785156-0.6708984,2.2700195-0.5273438,2.9526367-0.3837891 c0.4052734,0.0830078,0.8032227-0.1748047,0.8886719-0.5800781s-0.1738281-0.8027344-0.5791016-0.8886719 c-0.3931274-0.0823975-0.7782593-0.130127-1.1518555-0.1454468c-0.1039429-0.53302-0.0985718-1.0831909,0.0239258-1.6152954 c0.0927734-0.4033203-0.1591797-0.8066406-0.5629883-0.8994141c-0.4038086-0.0898438-0.8061523,0.1611328-0.8989258,0.5634766 c-0.1596069,0.6945801-0.1751709,1.4108276-0.0565796,2.1081543c-0.53479,0.1254883-1.0369263,0.3114624-1.4629526,0.6027832 c-1.3994141,0.9570313-1.9360352,2.8320313-1.1962891,4.1816406c1.1052847,2.0129051,3.8100004,1.8074532,5.1850595,2.2021484 c2.1161976,0.6054153,1.8197498,2.4342194,0.3833008,3.0107422c-1.0332031,0.4150391-2.2402344,0.0205078-2.8691406-0.2519531 c-0.3808594-0.1640625-0.8217773,0.0107422-0.9863281,0.390625s0.0102539,0.8212891,0.390625,0.9863281 c0.4503174,0.1948242,1.0012817,0.3755493,1.5961304,0.4760132l0.1016235,1.6411743 c0.0249023,0.3974609,0.3549805,0.703125,0.7480469,0.703125c0.4355659,0,0.7758923-0.3669624,0.7490234-0.796875 l-0.0942383-1.5200806c0.3078613-0.0443115,0.6169434-0.112915,0.9238281-0.2357788 C23.4494343,50.8599739,23.6716747,47.1243896,20.168272,46.12183z"/>
					<path class="latepoint-step-svg-base" d="M27.5291119,20.7048359h28.2197247c0.4140625,0,0.75-0.3359375,0.75-0.75s-0.3359375-0.75-0.75-0.75H27.5291119 c-0.4140625,0-0.75,0.3359375-0.75,0.75S27.1150494,20.7048359,27.5291119,20.7048359z"/>
					<path class="latepoint-step-svg-base" d="M32.607235,31.4577656c0,0.4140625,0.3359375,0.7500019,0.75,0.7500019h23.1582031 c0.4140625,0,0.75-0.3359394,0.75-0.7500019s-0.3359375-0.75-0.75-0.75H33.357235 C32.9431725,30.7077656,32.607235,31.0437031,32.607235,31.4577656z"/>
					<path class="latepoint-step-svg-base" d="M55.2888756,41.443119H38.4182701c-0.4140625,0-0.75,0.3359375-0.75,0.75s0.3359375,0.75,0.75,0.75h16.8706055 c0.4140625,0,0.75-0.3359375,0.75-0.75S55.7029381,41.443119,55.2888756,41.443119z"/>
				</svg>';
				break;
		}

		/**
		 * Generates an SVG image for step code, if there was no custom image set
		 *
		 * @param {string} $svg image svg code
		 * @param {string} $step_code step name code
		 *
		 * @since 5.0.0
		 * @hook latepoint_svg_for_step_code
		 *
		 */
		return apply_filters( 'latepoint_svg_for_step_code', $svg, $step_code );
	}


	public static function get_time_pick_style() {
		return OsStepsHelper::get_step_setting_value( 'booking__datepicker', 'time_pick_style', 'timebox' );
	}


	public static function get_calendar_style() {
		return OsStepsHelper::get_step_setting_value( 'booking__datepicker', 'calendar_style', 'modern' );
	}

	/**
	 * Generates a preview for a selected step to show on booking form preview in settings
	 *
	 * @param string $selected_step_code
	 *
	 * @return void
	 */
	public static function get_step_content_preview( string $selected_step_code ) {
		switch ( $selected_step_code ) {
			case 'booking__services':
				OsBookingHelper::generate_services_bundles_and_categories_list();
				break;
			case 'booking__agents':
				$agents_model = new OsAgentModel();
				$agents       = $agents_model->should_be_active()->get_results_as_models();
				OsAgentHelper::generate_agents_list( $agents );
				break;
			case 'booking__datepicker':
				$booking  = new OsBookingModel();
				$services = new OsServiceModel();
				$service  = $services->should_be_active()->set_limit( 1 )->get_results_as_models();
				if ( $service ) {
					$booking->service_id = $service->id;
					echo OsCalendarHelper::generate_dates_and_times_picker( $booking, new OsWpDateTime( 'now' ), ! OsStepsHelper::disable_searching_first_available_slot() );
					?>


					<?php
				} else {
					echo 'You need to have an active service to generate the calendar';
				}
				break;
			case 'booking__locations':
				OsLocationHelper::generate_locations_and_categories_list();
				break;
			case 'customer':
				$booking                     = new OsBookingModel();
				$services                    = new OsServiceModel();
				$service                     = $services->should_be_active()->set_limit( 1 )->get_results_as_models();
				$customer                    = new OsCustomerModel();
				$default_fields_for_customer = OsSettingsHelper::get_default_fields_for_customer();

				$current_step_code = $selected_step_code;

				include LATEPOINT_VIEWS_ABSPATH . 'booking_form_settings/previews/_customer.php';
				break;
			case 'payment__times':
				echo '<div class="booking-preview-step-skipped-message">' . esc_html__( "If you have both a payment processor and pay locally enabled, customer will make a selection here.", 'latepoint' ) . '</div>';
				break;
			case 'payment__portions':
				echo '<div class="booking-preview-step-skipped-message">' . esc_html__( "If selected service has both deposit and charge amount set, customer will have to pick how much they want to pay now.", 'latepoint' ) . '</div>';
				break;
			case 'payment__methods':
				echo '<div class="booking-preview-step-skipped-message">' . esc_html__( "If you have multiple payment processors enabled, customer will be able to select how they want to pay", 'latepoint' ) . '</div>';
				break;
			case 'payment__pay':
				echo '<div class="booking-preview-step-skipped-message">' . esc_html__( "Payment form generated by selected payment processor will appear here", 'latepoint' ) . '</div>';
				break;
			case 'confirmation':
				echo '<div class="summary-status-wrapper summary-status-style-' . esc_attr( OsStepsHelper::get_step_setting_value( $selected_step_code, 'order_confirmation_message_style', 'green' ) ) . '">';
				echo '<div class="summary-status-inner">';
				echo '<div class="ss-icon"></div>';
				echo '<div class="ss-title bf-side-heading editable-setting" data-setting-key="[' . esc_attr( $selected_step_code ) . '][order_confirmation_message_title]" contenteditable="true">' . esc_html( OsStepsHelper::get_step_setting_value( $selected_step_code, 'order_confirmation_message_title', __( 'Appointment Confirmed', 'latepoint' ) ) ) . '</div>';
				echo '<div class="ss-description bf-side-heading editable-setting" data-setting-key="[' . esc_attr( $selected_step_code ) . '][order_confirmation_message_content]" contenteditable="true">' . esc_html( OsStepsHelper::get_step_setting_value( $selected_step_code, 'order_confirmation_message_content', __( 'We look forward to seeing you.', 'latepoint' ) ) ) . '</div>';
				echo '<div class="ss-confirmation-number"><span>' . esc_html__( 'Order #', 'latepoint' ) . '</span><strong>KDFJ934K</strong></div>';
				echo '</div>';
				echo '</div>';
				echo '<div class="booking-preview-step-skipped-message">' . esc_html__( "Order information will appear here.", 'latepoint' ) . '</div>';
				break;
		}
		do_action( 'latepoint_get_step_content_preview', $selected_step_code );
	}

	public static function hide_slot_availability_count(): bool {
		return OsUtilHelper::is_on( self::get_step_setting_value( 'booking__datepicker', 'hide_slot_availability_count' ) );
	}

	public static function hide_timepicker_when_one_slot_available(): bool {
		return OsUtilHelper::is_on( self::get_step_setting_value( 'booking__datepicker', 'hide_timepicker_when_one_slot_available' ) );
	}

	public static function build_booking_object_for_current_step_preview( string $current_step ): OsBookingModel {
		$booking        = new OsBookingModel();
		$steps_in_order = self::get_step_codes_in_order();

		$current_step_index = array_search( $current_step, $steps_in_order );
		if ( $current_step_index === false ) {
			return $booking;
		}
		$completed_steps = array_slice( $steps_in_order, 0, $current_step_index );
		foreach ( $completed_steps as $completed_step ) {
			self::set_booking_object_values_for_completed_step( $booking, $completed_step );
		}

		return $booking;
	}

	public static function set_booking_object_values_for_completed_step( OsBookingModel $booking, string $completed_step ): OsBookingModel {
		switch ( $completed_step ) {
			case 'booking__services':
				$services = new OsServiceModel();
				$service  = $services->should_be_active()->set_limit( 1 )->get_results_as_models();
				if ( $service ) {
					$booking->service_id = $service->id;
				}
				break;
			case 'booking__locations':
				$locations = new OsLocationModel();
				$location  = $locations->should_be_active()->set_limit( 1 )->get_results_as_models();
				if ( $location ) {
					$booking->location_id = $location->id;
				}
				break;
			case 'booking__agents':
				$agents = new OsAgentModel();
				$agent  = $agents->should_be_active()->set_limit( 1 )->get_results_as_models();
				if ( $agent ) {
					$booking->agent_id = $agent->id;
				}
				break;
			case 'customer':
				$customers = new OsCustomerModel();
				$customer  = $customers->set_limit( 1 )->get_results_as_models();
				if ( $customer ) {
					$booking->customer_id = $customer->id;
				}
				break;
			case 'booking__datepicker':
				$tomorrow            = new OsWpDateTime( 'tomorrow' );
				$booking->start_date = $tomorrow->format( 'Y-m-d' );
				$booking->start_time = 600;

				break;
		}

		/**
		 * Sets values for booking object depending on a completed step code
		 *
		 * @param {OsBookingModel} $booking booking object
		 * @param {string} $completed_step step code that was completed
		 *
		 * @since 5.0.0
		 * @hook latepoint_set_booking_object_values_for_completed_step
		 *
		 */
		return apply_filters( 'latepoint_set_booking_object_values_for_completed_step', $booking, $completed_step );
	}

	public static function generate_summary_key_value_pairs( OsBookingModel $booking ): string {
		$html = '';


		if ( $booking->location_id ) {
			$html .= '<div class="summary-box summary-box-location-info">
					<div class="summary-box-heading">
						<div class="sbh-item">' . __( 'Location', 'latepoint' ) . '</div>
						<div class="sbh-line"></div>
					</div>
					<div class="summary-box-content with-media">
						<div class="sbc-content-i">
							<div class="sbc-main-item">' . $booking->location->name . '</div>
						</div>
					</div>
				</div>';
		}
		if ( $booking->customer_id ) {
			$html                .= '<div class="summary-box summary-box-customer-info">
					<div class="summary-box-heading">
						<div class="sbh-item">' . __( 'Customer', 'latepoint' ) . '</div>
						<div class="sbh-line"></div>
					</div>
					<div class="summary-box-content with-media">
						<div class="os-avatar-w">
							<div class="os-avatar"><span>' . esc_html( $booking->customer->get_initials() ) . '</span></div>
						</div>
						<div class="sbc-content-i">
							<div class="sbc-main-item">' . esc_html( $booking->customer->full_name ) . '</div>
							<div class="sbc-sub-item">' . esc_html( $booking->customer->email ) . '</div>
						</div>
					</div>';
			$customer_attributes = [];
			$customer_attributes = apply_filters( 'latepoint_booking_summary_customer_attributes', $customer_attributes, $booking->customer );
			if ( $customer_attributes ) {
				$html .= '<div class="summary-attributes sa-clean sa-hidden">';
				foreach ( $customer_attributes as $attribute ) {
					$html .= '<span>' . esc_html( $attribute['label'] ) . ': <strong>' . esc_html( $attribute['value'] ) . '</strong></span>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}
		if ( OsSettingsHelper::is_off( 'steps_hide_agent_info' ) && $booking->agent_id && $booking->agent_id != LATEPOINT_ANY_AGENT ) {
			$bio_html = '';
			if ( OsSettingsHelper::steps_show_agent_bio() ) {
				$bio_html .= '<div class="os-trigger-item-details-popup sbc-link-item" data-item-details-popup-id="osItemDetailsPopupAgent_' . $booking->agent_id . '">' . __( 'Learn More', 'latepoint' ) . '</div>';
				$bio_html .= OsAgentHelper::generate_bio( $booking->agent );
			}
			$html .= '<div class="summary-box summary-box-agent-info">
					<div class="summary-box-heading">
						<div class="sbh-item">' . __( 'Agent', 'latepoint' ) . '</div>
						<div class="sbh-line"></div>
					</div>
					<div class="summary-box-content with-media">
						<div class="os-avatar-w"
						     style="background-image: url(' . ( ( $booking->agent->avatar_image_id ) ? $booking->agent->get_avatar_url() : '' ) . ')">
							' . ( ( ! $booking->agent->avatar_image_id ) ? '<div class="os-avatar"><span>' . esc_html( $booking->agent->get_initials() ) . '</span></div>' : '' ) . '
						</div>
						<div class="sbc-content-i">
							<div class="sbc-main-item">' . esc_html( $booking->agent->full_name ) . '</div>
							' . $bio_html . '
						</div>
					</div>
				</div>';
		}


		/**
		 * Key value pairs of summary values for the booking summary panel
		 *
		 * @param {string} $html HTML of key value pairs
		 * @param {OsBookingModel} $booking Booking object that is used to generate the summary
		 * @returns {string} $html The filtered HTML of key value pairs
		 *
		 * @since 5.0.0
		 * @hook latepoint_summary_key_value_pairs
		 *
		 */
		$html = apply_filters( 'latepoint_summary_key_value_pairs', $html, $booking );

		if ( $html ) {
			$html = '<div class="summary-boxes-columns">' . $html . '</div>';
		}

		return $html;
	}

	public static function is_ready_for_summary() {
		if ( ! empty( self::$order_object ) && ! self::$order_object->is_new_record() ) {
			// order object is set - don't need to show summary anymore
			return false;
		}
		if ( ! self::$cart_object->is_empty() ) {
			// cart has items inside - show summary
			return true;
		}
		if ( self::$active_cart_item->is_bundle() ) {
			// bundle selected already - show summary
			return true;
		}
		if ( ! empty( self::$booking_object->service_id ) ) {
			// service is selected for a booking - show summary
			return true;
		}


		return false;
	}

	public static function set_active_cart_item_object( array $cart_item_params = [] ): OsCartItemModel {
		self::$active_cart_item = new OsCartItemModel();
		if ( ! empty( $cart_item_params['id'] ) ) {
			self::$active_cart_item->id = $cart_item_params['id'];
			// try to find it in cart
			$cart_item = new OsCartItemModel( self::$active_cart_item->id );
			if ( $cart_item->is_new_record() ) {
				// not found, reset active cart item ID
				self::$active_cart_item = new OsCartItemModel();
			}
		}
		self::$active_cart_item->variant = ! empty( $cart_item_params['variant'] ) ? $cart_item_params['variant'] : ( empty( self::$presets['selected_bundle'] ) ? LATEPOINT_ITEM_VARIANT_BOOKING : LATEPOINT_ITEM_VARIANT_BUNDLE );
		if ( self::$active_cart_item->is_bundle() ) {
			if ( empty( $cart_item_params['item_data'] ) ) {
				self::$active_cart_item->item_data = empty( self::$presets['selected_bundle'] ) ? '' : wp_json_encode( [ 'bundle_id' => self::$presets['selected_bundle'] ] );
			} else {
				// bundle gets data from params
				self::$active_cart_item->item_data = is_array( $cart_item_params['item_data'] ) ? wp_json_encode( $cart_item_params['item_data'], true ) : $cart_item_params['item_data'];
			}
		} else {
			// booking gets data from booking object
			self::$active_cart_item->item_data = wp_json_encode( self::$booking_object->generate_params_for_booking_form(), true );
		}

		return self::$active_cart_item;
	}

	public static function get_cart_item_object() {
		return self::$active_cart_item;
	}


	/**
	 *
	 * Given a step code, returns the first sub step if found, or returns the parent step code if no children
	 *
	 * @param string $parent_code
	 *
	 * @return string
	 */
	public static function get_first_step_for_parent_code( string $parent_code ): string {
		$first_step_code = '';
		$step_codes      = self::$step_codes_in_order;
		foreach ( $step_codes as $step_code ) {
			$loop_parent_code = explode( '__', $step_code )[0];
			if ( $loop_parent_code == $parent_code ) {
				$first_step_code = $step_code;
				break;
			}
		}

		return $first_step_code;
	}

	public static function check_step_code_access( string $step_code_to_access ): string {
		if ( $step_code_to_access == 'confirmation' && ! self::$order_object->is_new_record() ) {
			return $step_code_to_access;
		}
		// loops through all steps and checks if they satisfy condition to be skipped
		for ( $i = 0; $i < count( self::$step_codes_in_order ); $i ++ ) {
			$code        = self::$step_codes_in_order[ $i ];
			$parent_code = explode( '__', $code )[0];

			$next_code        = ( ( $i + 1 ) < count( self::$step_codes_in_order ) ) ? self::$step_codes_in_order[ $i + 1 ] : false;
			$next_parent_code = $next_code ? explode( '__', $next_code )[0] : false;

			if ( $step_code_to_access == $code ) {
				break;
			}
			switch ( $parent_code ) {
				// even tho we are checking a parent code - make sure to assign to a $code, because it's a first one in order in that parent
				case 'customer':
					if ( ! OsAuthHelper::is_customer_logged_in() ) {
						$step_code_to_access = $code;
						break 2;
					}
					break;
				case 'booking':
					if ( $next_parent_code && $next_parent_code != $parent_code && self::$cart_object->is_empty() ) {
//						$step_code_to_access = self::get_first_step_for_parent_code($parent_code);
//						break 2;
					}
					break;
			}
		}

		/**
		 * Checks if a step code can be accessed, returns the step code that can be accessed
		 *
		 * @param {string} $step_code_to_access step code that needs to be checked for access
		 * @returns {string} $step_code_to_access The filtered step code that can be accessed
		 *
		 * @since 5.0.0
		 * @hook latepoint_check_step_code_access
		 *
		 */
		return apply_filters( 'latepoint_check_step_code_access', $step_code_to_access );
	}

	public static function get_first_step_code( string $step_code, $step_codes = false ): string {
		if ( ! $step_codes ) {
			$step_codes = self::get_step_codes_in_order();
		}
		if ( isset( $step_codes[ $step_code ] ) ) {
			return $step_code;
		}
		$unflat_step_codes = self::unflatten_steps( $step_codes );

		// TODO add support for more than 2 dimentional parent/child arrays
		if ( isset( $unflat_step_codes[ $step_code ] ) ) {
			return implode( '__', [ $step_code, array_key_first( $unflat_step_codes[ $step_code ] ) ] );
		}

		return '';
	}

	public static function build_cart_object(): OsCartModel {
		if ( ! isset( self::$cart_object ) ) {
			self::set_cart_object();
		}

		return self::$cart_object;
	}

	public static function set_order_object( array $params = [] ): OsOrderModel {
		self::$order_object = new OsOrderModel();

		return self::$order_object;
	}

	public static function set_cart_object( array $params = [] ): OsCartModel {
		self::$cart_object = OsCartsHelper::get_or_create_cart();
        if( self::$cart_object->order_intent_id ){
            $order_intent = new OsOrderIntentModel(self::$cart_object->order_intent_id);
            if($order_intent->is_converted()){
                $order_intent->mark_cart_converted(self::$cart_object);
            }
        }
		if ( self::$cart_object->order_id ) {
			self::load_order_object( self::$cart_object->order_id );
		} else {
			self::load_order_object();
			self::$cart_object->set_data( $params );

			// set source id
			if ( isset( self::$restrictions['source_id'] ) ) {
				self::$cart_object->source_id = self::$restrictions['source_id'];
			}

			self::$cart_object->calculate_prices();
		}

		return self::$cart_object;
	}

	public static function set_cart_object_from_order_intent( OsOrderIntentModel $order_intent ): OsCartModel {
		OsCartsHelper::get_or_create_cart();
		self::$cart_object->clear();


		// add items from intent
		$intent_cart_items = json_decode( $order_intent->cart_items_data, true );
		foreach ( $intent_cart_items as $cart_item_data ) {
			OsCartsHelper::add_item_to_cart( OsCartsHelper::create_cart_item_from_item_data( $cart_item_data ) );
		}

		// restore payment info
		$payment_data                         = json_decode( $order_intent->payment_data, true );
		self::$cart_object->payment_method    = $payment_data['method'];
		self::$cart_object->payment_time      = $payment_data['time'];
		self::$cart_object->payment_portion   = $payment_data['portion'];
		self::$cart_object->payment_token     = $payment_data['token'];
		self::$cart_object->payment_processor = $payment_data['processor'];

		return self::$cart_object;
	}

	public static function hide_unavailable_slots() {
		return OsUtilHelper::is_on( self::get_step_setting_value( 'booking__datepicker', 'hide_unavailable_slots' ) );
	}

	public static function disable_searching_first_available_slot() {
		return OsUtilHelper::is_on( self::get_step_setting_value( 'booking__datepicker', 'disable_searching_first_available_slot' ) );
	}

	private static function set_recurring_booking_properties( array $params ) {
		if ( ! empty( $params['is_recurring'] ) && $params['is_recurring'] == LATEPOINT_VALUE_ON ) {
			self::$booking_object->generate_recurrent_sequence = [ 'rules' => $params['recurrence']['rules'] ?? [], 'overrides' => $params['recurrence']['overrides'] ?? [] ];
		}
	}
}