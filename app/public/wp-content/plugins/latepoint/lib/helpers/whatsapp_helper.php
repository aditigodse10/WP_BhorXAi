<?php

class OsWhatsappHelper {

	static array $templates;

	public static function get_buttons_component_possible_variable_holders( array $template ): array {
		$holders = [];
		foreach ( $template['components'] as $component ) {
			if ( $component['type'] == 'BUTTONS' ) {
				$buttons = $component['buttons'];
				foreach ( $buttons as $button ) {
					switch ( $button['type'] ) {
						case 'URL':
							$holders[] = $button['text'];
							$holders[] = $button['url'];
							break;

						case 'PHONE_NUMBER':
							$holders[] = $button['text'];
							$holders[] = $button['phone_number'];
							break;
					}
				}
			}
		}

		return $holders;

	}

	public static function get_template_component_value_by_key( array $template, string $component_type, string $component_key ) {
		foreach ( $template['components'] as $component ) {
			if ( $component['type'] == $component_type ) {
				return $component[ $component_key ];
			}
		}

		return null;
	}

	/**
	 * @return array
	 */
	public static function get_template( string $template_id ): array {
		try {
			$templates = self::get_templates();
			foreach ( $templates as $template ) {
				if ( $template['id'] == $template_id ) {
					return $template;
				}
			}
		} catch ( Exception $e ) {
			return [];
		}

		return [];
	}

	public static function get_template_preview( string $template_id, \LatePoint\Misc\ProcessAction $action ): string {
		$html              = '';
		$selected_template = \OsWhatsappHelper::get_template( $template_id );
		$html              .= \OsFormHelper::hidden_field( 'process[actions][' . $action->id . '][settings][template_language]', $selected_template['language'] );
		$html              .= \OsFormHelper::hidden_field( 'process[actions][' . $action->id . '][settings][template_parameter_format]', $selected_template['parameter_format'] );
		$html              .= \OsFormHelper::hidden_field( 'process[actions][' . $action->id . '][settings][template_category]', $selected_template['category'] );
		$html              .= \OsFormHelper::hidden_field( 'process[actions][' . $action->id . '][settings][template_name]', $selected_template['name'] );

		$variables_by_type['header']  = \OsWhatsappHelper::extract_variables_from_template( \OsWhatsappHelper::get_template_component_value_by_key( $selected_template, 'HEADER', 'text' ) );
		$variables_by_type['body']    = \OsWhatsappHelper::extract_variables_from_template( \OsWhatsappHelper::get_template_component_value_by_key( $selected_template, 'BODY', 'text' ) );
		$variables_by_type['buttons'] = \OsWhatsappHelper::extract_variables_from_button_holders( \OsWhatsappHelper::get_buttons_component_possible_variable_holders( $selected_template ) );

		$html .= '<div class="latepoint-whatsapp-template-preview-wrapper">';
		$html .= '<div class="latepoint-whatsapp-template-preview-content-wrapper">';
		$html .= '<div class="latepoint-whatsapp-template-preview-heading">' . __( 'Message Preview', 'latepoint' ) . '</div>';;
		$html .= '<div class="latepoint-whatsapp-template-preview-messages">';
		if ( $selected_template ) {
			$html .= '<div class="latepoint-whatsapp-template-preview-message">';
			switch ( esc_html( \OsWhatsappHelper::get_template_component_value_by_key( $selected_template, 'HEADER', 'format' ) ) ) {
				case 'TEXT':
					$html .= '<div class="latepoint-whatsapp-template-preview-message-header">' . \OsWhatsappHelper::colorize_variables( esc_html( \OsWhatsappHelper::get_template_component_value_by_key( $selected_template, 'HEADER', 'text' ) ) ) . '</div>';
					break;
				case 'IMAGE':
					break;
				case 'VIDEO':
					break;
				case 'DOCUMENT':
					break;
				case 'LOCATION':
					break;
			}
			$html    .= '<div class="latepoint-whatsapp-template-preview-message-body">' . \OsWhatsappHelper::colorize_variables( esc_html( \OsWhatsappHelper::get_template_component_value_by_key( $selected_template, 'BODY', 'text' ) ), count( $variables_by_type['header'] ) ) . '</div>';
			$buttons = \OsWhatsappHelper::get_template_component_value_by_key( $selected_template, 'BUTTONS', 'buttons' );
			if ( $buttons ) {
				$html        .= '<div class="latepoint-whatsapp-template-preview-message-buttons">';
				$start_index = count( $variables_by_type['header'] ) + count( $variables_by_type['body'] );
				foreach ( $buttons as $button ) {
					$html .= '<div class="latepoint-whatsapp-template-preview-message-button latepoint-whatsapp-button-type-' . esc_attr( $button['type'] ) . '">';
					switch ( $button['type'] ) {
						case 'PHONE_NUMBER':
							$html .= '<i class="latepoint-icon latepoint-icon-phone"></i>' . esc_html( $button['text'] ) . '<div class="latepoint-whatsapp-button-action-value">' . \OsWhatsappHelper::colorize_variables( $button['phone_number'], $start_index ) . '</div>';
							break;
						case 'URL':
							$html .= '<i class="latepoint-icon latepoint-icon-external-link"></i>' . esc_html( $button['text'] ) . '<div class="latepoint-whatsapp-button-action-value">' . \OsWhatsappHelper::colorize_variables( $button['url'], $start_index ) . '</div>';
							break;
					}
					$html .= '</div>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= '</div>';
		if ( ! empty( $variables_by_type['header'] ) || ! empty( $variables_by_type['body'] ) || ! empty( $variables_by_type['buttons'] ) ) {

			$html                 .= '<div class="latepoint-whatsapp-template-preview-variables-wrapper parameter-format-'.esc_attr(strtolower($selected_template['parameter_format'])).'">';
			$html                 .= '<div class="latepoint-whatsapp-template-preview-heading">';
			$html                 .= '<div>' . __( 'Assign Variables', 'latepoint' ) . '</div>';
			$html                 .= '</div>';
			$html                 .= '<div class="latepoint-whatsapp-template-preview-variables-inner">';
			$smart_variables_link = '<a href="#" class="open-template-variables-panel">' . esc_html__( 'Click here', 'latepoint' ) . '</a>';
			$html                 .= '<div class="latepoint-whatsapp-note">' . sprintf( __( 'You have to assign values for each variable that is used in this template. %s to show smart variables that you can use.' ), $smart_variables_link ) . '</div>';
			$color_index          = 0;
			foreach ( $variables_by_type as $variable_type => $variables ) {
				if ( ! empty( $variables ) ) {
					$html .= '<div class="latepoint-whatsapp-variables-header"><div>' . $variable_type . '</div><div class="latepoint-whatsapp-header-line"></div></div>';
					foreach ( $variables as $variable ) {
						$html .= '<div class="latepoint-whatsapp-variable-value"><div style="background-color: ' . \OsUtilHelper::get_color_for_variable_by_index( $color_index ) . '">' . $variable . '</div><div>' . \OsFormHelper::text_field( 'process[actions][' . $action->id . '][settings][variables][' . $variable_type . '][' . $variable . ']', false, $action->settings['variables'][ $variable_type ][ $variable ] ?? '', [
								'theme'       => 'simple',
								'class'       => 'size-small',
								'placeholder' => sprintf( __( 'Enter value for %s', 'latepoint' ), $variable )
							] ) . '</div></div>';
						$color_index ++;
					}
				}
			}
			$html .= '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';

		return $html;
	}

	public static function get_templates_list( bool $force_reload = false ): array {
		try {
			$templates      = self::get_templates( $force_reload );
			$templates_list = [];
			foreach ( $templates as $template ) {
				$name             = $template['name'] . '-' . $template['language'] . ' [' . $template['status'] . ']';
				$templates_list[] = [ 'value' => $template['id'], 'label' => $name ];
			}

			return $templates_list;
		} catch ( Exception $e ) {
			return [ 'error' => $e->getMessage() ];
		}
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public static function get_templates( bool $force_reload = false ): array {
		if ( isset( self::$templates ) && ! $force_reload ) {
			return self::$templates;
		}
		try {
			return apply_filters( 'latepoint_get_whatsapp_templates', [] );
		} catch ( Exception $e ) {
			return [];
		}
	}


	/**
	 * @param $to
	 * @param $content
	 *
	 * @return array [
	 * 'status' => string,
	 * 'message' => string,
	 * 'to' => string,
	 * 'content' => string,
	 * 'processor_code' => string,
	 * 'processor_name' => string,
	 * 'processed_datetime' => string,
	 * 'extra_data' => array
	 * ]
	 */
	public static function send_whatsapp( string $to, array $data, array $activity_data = [] ): array {
		$result = [
			'status'             => LATEPOINT_STATUS_ERROR,
			'message'            => __( 'No WhatsApp processor is selected.', 'latepoint' ),
			'to'                 => $to,
			'data'               => $data,
			'processor_code'     => '',
			'processor_name'     => '',
			'processed_datetime' => '',
			'extra_data'         => [
				'activity_data' => $activity_data
			],
			'errors'             => []
		];

		if ( OsSettingsHelper::is_whatsapp_allowed() && OsNotificationsHelper::is_notification_type_enabled( 'whatsapp' ) ) {
			/**
			 * Result of sending an WhatsApp message to a recipient's phone number
			 *
			 * @param {array} $result The array of data describing the send operation
			 * @param {string} $to The recipient's phone number
			 * @param {array} $data The data array holding template message information and variables
			 * @param {array} $activity_data The data array with information about process activity
			 *
			 * @since 5.1.3
			 * @hook latepoint_notifications_send_whatsapp
			 * @returns {array} The array of descriptive data, possibly transformed by hooked WhatsApp processor(s)
			 */
			$result = apply_filters( 'latepoint_notifications_send_whatsapp', $result, $to, $data, $activity_data );
		} else {
			$result['message']  = __( 'WhatsApp notifications are disabled', 'latepoint' );
			$result['errors'][] = __( 'WhatsApp notifications are disabled', 'latepoint' );
		}

		self::log_whatsapp( $result );

		return $result;
	}

	/**
	 * @param $enabled_only
	 *
	 * @return array [
	 *   'code' => [
	 *      'code' => string,
	 *      'label' => string,
	 *      'image_url' => string
	 *   ]
	 * ]
	 */
	public static function get_whatsapp_processors( $enabled_only = false ) {
		$whatsapp_processors = [];

		/**
		 * Get the list of WhatsApp processors registered in the LatePoint ecosystem
		 *
		 * @param {array} $whatsapp_processors The list of WhatsApp processors being filtered
		 * @param {bool} $enabled_only True when filtering only enabled WhatsApp processors, false otherwise
		 * @returns {array} The filtered list of WhatsApp processors
		 *
		 * @since 5.1.3
		 * @hook latepoint_whatsapp_processors
		 *
		 */
		return apply_filters( 'latepoint_whatsapp_processors', $whatsapp_processors, $enabled_only );
	}

	public static function is_whatsapp_processor_enabled( string $whatsapp_processor_code ): bool {
		return ( OsNotificationsHelper::get_selected_processor_code_by_type( 'whatsapp' ) == $whatsapp_processor_code );
	}

	/**
	 * @param array $result
	 *
	 * @return OsActivityModel
	 */
	public static function log_whatsapp( array $result ) {
		if ( empty( $result['processed_datetime'] ) ) {
			$result['processed_datetime'] = OsTimeHelper::now_datetime_in_db_format();
		}
		$data = [
			'code'        => 'whatsapp_sent',
			'description' => wp_json_encode( $result )
		];
		if ( ! empty( $result['extra_data']['activity_data'] ) ) {
			$data = array_merge( $data, $result['extra_data']['activity_data'] );
		}
		$activity = OsActivitiesHelper::create_activity( $data );

		return $activity;
	}

	public static function colorize_variables( string $text, int $starting_index = 0 ): string {
		$colors = OsUtilHelper::get_colors_for_variables();

		// Create a map to store color assignments for each variable number
		$variableColors = [];

		return preg_replace_callback( '/\{\{([a-zA-Z0-9_]+)\}\}/', function ( $match ) use ( $colors, &$variableColors, $starting_index ) {
			$varNumber = $match[1];

			// If this variable hasn't been assigned a color yet, assign the next color
			if ( ! isset( $variableColors[ $varNumber ] ) ) {
				$variableColors[ $varNumber ] = $colors[ ( count( $variableColors ) % count( $colors ) ) + $starting_index ];
			}

			return sprintf( '<span class="latepoint-whatsapp-template-variable" style="background-color: %s" data-variable="{{%s}}">{{%s}}</span>',
				$variableColors[ $varNumber ],
				$varNumber,
				$varNumber
			);
		}, $text );
	}

	public static function extract_variables_from_template( ?string $text ): array {
		$variables = [];
		if ( empty( $text ) ) {
			return $variables;
		}

		// Match anything between {{ and }}
		preg_match_all( '/\{\{([^}]+)\}\}/', $text, $matches );

		if ( ! empty( $matches[0] ) ) {
			// Convert to and from array to get unique values while preserving original order
			$variables = array_values( array_unique( $matches[0] ) );
		}

		return $variables;
	}

	public static function extract_variables_from_button_holders( array $holders ): array {
		$joined_holders = implode( ', ', $holders );

		return self::extract_variables_from_template( $joined_holders );
	}

	public static function get_business_id() {
		/**
		 * Get business ID of a whatsapp account
		 *
		 * @param {string} $business_id WhatsApp Business ID
		 * @returns {string} The filtered business ID
		 *
		 * @since 5.1.3
		 * @hook latepoint_whatsapp_business_id
		 *
		 */
		return apply_filters( 'latepoint_whatsapp_business_id', '' );
	}
}