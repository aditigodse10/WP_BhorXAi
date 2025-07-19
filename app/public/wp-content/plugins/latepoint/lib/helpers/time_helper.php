<?php

class OsTimeHelper {

	private static $timezone = false;


	public static function get_db_weekday_by_number( $number ) {
		$weekdays = [ 'mo', 'tu', 'we', 'th', 'fr', 'sa', 'su' ];

		return $weekdays[ $number - 1 ];
	}

	public static function is_valid_date( $date_string ) {
		if(empty($date_string)) return false;
		return (bool) strtotime( $date_string );
	}

	public static function reformat_date_string( $date_string, $from_format, $to_format ) {
		$start_date_obj = OsWpDateTime::os_createFromFormat( $from_format, $date_string );

		return $start_date_obj->format( $to_format );
	}

	public static function nice_date( $date ) {
		if ( $date == OsTimeHelper::today_date( 'Y-m-d' ) ) {
			$nice_date = __( 'Today', 'latepoint' );
		} else {
			$nice_date = self::get_nice_date_with_optional_year( $date, true );
		}

		return $nice_date;
	}

	public static function date_from_db( $date_string, $format = false, string $output_timezone_name = 'UTC' ) {
		$timezone = new DateTimeZone( 'UTC' );
		$date_obj = OsWpDateTime::os_createFromFormat( LATEPOINT_DATETIME_DB_FORMAT, $date_string, $timezone );
		$date_obj->setTimezone( new DateTimeZone( $output_timezone_name ) );
		if ( $format ) {
			return $date_obj->format( $format );
		}

		return $date_obj;
	}

	public static function today_date( $date_format = 'Y-m-d' ) {
		$today = new OsWpDateTime( 'today' );

		return $today->format( $date_format );
	}

	public static function now_datetime_in_format( $date_format = LATEPOINT_DATETIME_DB_FORMAT, string $timezone = 'UTC' ) {
		$now = self::now_datetime_object();
		$now->setTimezone( new DateTimeZone( "UTC" ) );

		return $now->format( $date_format );
	}

	public static function now_datetime_utc() {
		$now = self::now_datetime_object();
		$now->setTimezone( new DateTimeZone( "UTC" ) );

		return $now;
	}

	public static function time_left_to_datetime( string $datetime, DateTimeZone $timezone ) {

		$now_datetime   = new OsWpDateTime( 'now', $timezone );
		$event_datetime = OsWpDateTime::createFromFormat( LATEPOINT_DATETIME_DB_FORMAT, $datetime, $timezone );
		$css_class      = 'left-days';

		$before    = ( $now_datetime < $event_datetime ) ? __( 'in', 'latepoint' ) . ' ' : '';
		$ago       = ( $now_datetime > $event_datetime ) ? ' ' . __( 'ago', 'latepoint' ) : '';
		$ago_class = empty( $ago ) ? '' : 'time-past';
		if ( $event_datetime ) {
			$diff = $now_datetime->diff( $event_datetime );
			if ( $diff->d > 0 ) {
				$left = $before . $diff->format( '%a ' . __( 'days', 'latepoint' ) ) . $ago;
			} else {
				if ( $diff->h > 0 ) {
					$css_class = 'left-hours';
					$left      = $before . $diff->format( '%h ' . __( 'hours', 'latepoint' ) ) . $ago;
				} else {
					$css_class = 'left-minutes';
					$left      = $before . $diff->format( '%i ' . __( 'minutes', 'latepoint' ) ) . $ago;
				}
			}
		} else {
			$left = 'n/a';
		}

		return '<span class="time-left ' . esc_attr( $css_class ) . ' ' . esc_attr( $ago_class ) . '">' . esc_html( $left ) . '</span>';
	}

	public static function now_datetime_utc_in_db_format() {
		return OsWpDateTime::datetime_in_utc( new OsWpDateTime( 'now' ), LATEPOINT_DATETIME_DB_FORMAT );
	}

	public static function custom_datetime_utc_in_db_format( string $time = 'now' ): string {
		return OsWpDateTime::datetime_in_utc( new OsWpDateTime( $time ), LATEPOINT_DATETIME_DB_FORMAT );
	}

	public static function now_datetime_in_db_format() {
		return self::now_datetime_in_format( LATEPOINT_DATETIME_DB_FORMAT );
	}

	public static function now_datetime_object() {
		return new OsWpDateTime( 'now' );
	}

	public static function get_time_system() {
		return OsSettingsHelper::get_time_system();
	}

	public static function get_time_format() {
		return self::is_army_clock() ? 'H:i' : 'g:ia';
	}

	public static function is_army_clock() {
		return ( self::get_time_system() == 24 );
	}

	public static function get_time_systems_list_for_select() {
		return array(
			array( 'value' => '12', 'label' => __( '12-hour clock', 'latepoint' ) ),
			array( 'value' => '24', 'label' => __( '24-hour clock', 'latepoint' ) )
		);
	}

	public static function get_date_formats_list_for_select() {
		return array(
			array( 'value' => 'm/d/Y', 'label' => __( 'MM/DD/YYYY', 'latepoint' ) ),
			array( 'value' => 'm.d.Y', 'label' => __( 'MM.DD.YYYY', 'latepoint' ) ),
			array( 'value' => 'd/m/Y', 'label' => __( 'DD/MM/YYYY', 'latepoint' ) ),
			array( 'value' => 'd.m.Y', 'label' => __( 'DD.MM.YYYY', 'latepoint' ) ),
			array( 'value' => 'Y-m-d', 'label' => __( 'YYYY-MM-DD', 'latepoint' ) )
		);
	}

	public static function get_time_systems_list() {
		return array( '12' => __( '12-hour clock', 'latepoint' ), '24' => __( '24-hour clock', 'latepoint' ) );
	}


	public static function format_date_with_locale( string $format, DateTime $date_obj ): string {
		return OsUtilHelper::translate_months( $date_obj->format( $format ) );
	}


	public static function get_nice_date_with_optional_year( $date, $show_year_if_not_current = true ) {
		$d = OsWpDateTime::os_createFromFormat( "Y-m-d", $date );
		if ( ! $d ) {
			return $date;
		}
		if ( ! $show_year_if_not_current || ( $d->format( 'Y' ) == OsTimeHelper::today_date( 'Y' ) ) ) {
			return OsUtilHelper::translate_months( $d->format( OsSettingsHelper::get_readable_date_format( true ) ) );
		} else {
			return OsUtilHelper::translate_months( $d->format( OsSettingsHelper::get_readable_date_format() ) );
		}
	}

	public static function get_readable_date( DateTime $date, string $output_timezone_name = '' ): string {
		if ( empty( $output_timezone_name ) ) {
			$output_timezone_name = self::get_wp_timezone_name();
		}
		$date->setTimezone( new DateTimeZone( $output_timezone_name ) );

		return self::format_date_with_locale( OsSettingsHelper::get_readable_date_format(), $date );
	}

	public static function get_readable_date_from_string( string $date, string $input_timezone_name = '', string $output_timezone_name = '' ): string {
		if ( empty( $input_timezone_name ) ) {
			$input_timezone_name = 'UTC';
		}
		if ( empty( $output_timezone_name ) ) {
			$output_timezone_name = self::get_wp_timezone_name();
		}

		$date_obj = new OsWpDateTime( $date, new DateTimeZone( $input_timezone_name ) );

		return self::get_readable_date( $date_obj, $output_timezone_name );
	}

	public static function get_wp_timezone() {
		if ( self::$timezone ) {
			return self::$timezone;
		}
		try{
			$timezone_string = get_option( 'timezone_string' );
			if ( ! empty( $timezone_string ) ) {
				return new DateTimeZone( $timezone_string );
			}
			$offset         = get_option( 'gmt_offset' );
			$hours          = (int) $offset;
			$minutes        = abs( ( $offset - (int) $offset ) * 60 );
			$offset         = sprintf( '%+03d:%02d', $hours, $minutes );
			self::$timezone = new DateTimeZone( $offset );
		}catch(Exception $e){
			return new DateTimeZone('UTC');
		}

		return self::$timezone;
	}

	public static function get_wp_timezone_name() {
		$timezone_obj = self::get_wp_timezone();
		if ( $timezone_obj ) {
			return $timezone_obj->getName();
		} else {
			return 'America/New_York';
		}
	}

	public static function get_timezone_from_session() {
		try {
			$timezone = new DateTimeZone( self::get_timezone_name_from_session() );
		} catch ( Exception $e ) {
			$timezone = new DateTimeZone( self::get_wp_timezone_name() );
		}

		return $timezone;
	}

	public static function get_timezone_name_from_session() {
		$timezone_name = self::get_wp_timezone_name();
		$timezone_name = apply_filters( 'latepoint_timezone_name_from_session', $timezone_name );

		return $timezone_name;
	}

	public static function is_timezone_saved_in_session() {
		return ( isset( $_COOKIE[ LATEPOINT_SELECTED_TIMEZONE_COOKIE ] ) && ! empty( $_COOKIE[ LATEPOINT_SELECTED_TIMEZONE_COOKIE ] ) );
	}

	public static function set_timezone_name_in_cookie( $timezone_name ) {
		OsSessionsHelper::setcookie( LATEPOINT_SELECTED_TIMEZONE_COOKIE, $timezone_name );
		$_COOKIE[ LATEPOINT_SELECTED_TIMEZONE_COOKIE ] = $timezone_name;
	}


	public static function convert_datetime_to_minutes( DateTime $datetime ) {
		return $datetime->format( 'i' ) + ( $datetime->format( 'G' ) * 60 );
	}

	public static function get_current_minutes(  ) {
		$now = new OsWpDateTime( 'now' );
		return $now->format( 'i' ) + ( $now->format( 'G' ) * 60 );
	}

	public static function convert_time_to_minutes( $time, $ampm = false ) {
		if ( strpos( $time, ':' ) === false ) {
			return 0;
		}

		list( $hours, $minutes ) = explode( ':', $time );
		if ( $hours == '12' && $ampm == 'am' ) {
			// midnight
			$hours = '0';
		}
		if ( $ampm == 'pm' && $hours < 12 ) {
			// convert to 24 hour format
			$hours = $hours + 12;
		}
		$minutes = ( $hours * 60 ) + $minutes;

		return $minutes;
	}

	public static function am_or_pm( $minutes ) {
		if ( self::is_army_clock() ) {
			return '';
		}

		return ( $minutes < 720 ) ? 'am' : 'pm';
	}

	public static function minutes_to_hours( $time ) {
		if ( $time ) {
			$hours = floor( $time / 60 );
			if ( ! self::is_army_clock() && $hours > 12 ) {
				$hours = $hours - 12;
			}
			if ( ! self::is_army_clock() && ! $hours ) {
				$hours = 12;
			}

			return $hours;
		} else {
			// if am/pm - we don't show 0, we show 12
			return ( self::is_army_clock() ) ? 0 : 12;
		}
	}


	public static function minutes_to_army_hours_and_minutes( $time_in_minutes ) {
		if ( is_null( $time_in_minutes ) || $time_in_minutes === '' ) {
			return __( 'n/a', 'latepoint' );
		}
		$hours   = floor( $time_in_minutes / 60 );
		$minutes = ( $time_in_minutes % 60 );

		return sprintf( '%02d:%02d', $hours, $minutes );
	}

	public static function minutes_to_hours_and_minutes( $minutes, $format = '%02d:%02d', $add_ampm = true, $hide_if_zero_minutes = false ) {
		if ( is_null( $minutes ) ) {
			return 'n/a';
		}
		if ( ! $format ) {
			$format = '%02d:%02d';
		}

		if ( $minutes === '' ) {
			return;
		}
		$ampm    = ( $add_ampm ) ? self::am_or_pm( $minutes ) : '';
		$hours   = self::minutes_to_hours( $minutes );
		$minutes = ( $minutes % 60 );
		if ( $hide_if_zero_minutes && ! $minutes ) {
			return $hours . ' ' . $ampm;
		} else {
			return sprintf( $format, $hours, $minutes ) . $ampm;
		}
	}


	public static function timezones_options_list_styled( $selected_zone, $locale = null ) {
		static $mo_loaded = false, $locale_loaded = null;

		$continents = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific' );

		// Load translations for continents and cities.
		if ( ! $mo_loaded || $locale !== $locale_loaded ) {
			$locale_loaded = $locale ? $locale : get_locale();
			$mofile        = WP_LANG_DIR . '/continents-cities-' . $locale_loaded . '.mo';
			unload_textdomain( 'continents-cities' );
			load_textdomain( 'continents-cities', $mofile );
			$mo_loaded = true;
		}

		$time_format = self::get_time_format();

		$zonen = array();
		foreach ( timezone_identifiers_list() as $zone ) {
			$zone = explode( '/', $zone );
			if ( ! in_array( $zone[0], $continents ) ) {
				continue;
			}

			// This determines what gets set and translated - we don't translate Etc/* strings here, they are done later
			$exists    = array(
				0 => ( isset( $zone[0] ) && $zone[0] ),
				1 => ( isset( $zone[1] ) && $zone[1] ),
				2 => ( isset( $zone[2] ) && $zone[2] ),
			);
			$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
			$exists[4] = ( $exists[1] && $exists[3] );
			$exists[5] = ( $exists[2] && $exists[3] );

			// phpcs:disable WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText
			$zonen[] = array(
				'continent'   => ( $exists[0] ? $zone[0] : '' ),
				'city'        => ( $exists[1] ? $zone[1] : '' ),
				'subcity'     => ( $exists[2] ? $zone[2] : '' ),
				't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ),
				't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ),
				't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' ),
			);
			// phpcs:enable
		}
		usort( $zonen, '_wp_timezone_choice_usort_callback' );

		$structure = array();

		foreach ( $zonen as $key => $zone ) {
			// Build value in an array to join later
			$value = array( $zone['continent'] );

			if ( empty( $zone['city'] ) ) {
				// It's at the continent level (generally won't happen)
				$display = $zone['t_continent'];
			} else {
				// It's inside a continent group

				// Continent optgroup
				if ( ! isset( $zonen[ $key - 1 ] ) || $zonen[ $key - 1 ]['continent'] !== $zone['continent'] ) {
					$label       = $zone['t_continent'];
					$structure[] = '<div class="os-timezone-group"><div class="os-timezone-group-header">'.esc_html( $label ).'</div>';
				}

				// Add the city to the value
				$value[] = $zone['city'];

				$display = $zone['t_city'];
				if ( ! empty( $zone['subcity'] ) ) {
					// Add the subcity to the value
					$value[] = $zone['subcity'];
					$display .= ' - ' . $zone['t_subcity'];
				}
			}

			// Build the value
			$value    = join( '/', $value );
			$selected = '';
			if ( $value === $selected_zone ) {
				$selected = 'selected';
			}
			try {
				$local_time = new OsWpDateTime('now', new DateTimeZone($value));
				$local_time_value = $local_time->format($time_format);
			}catch(Exception $e){
				$local_time_value = '';
			}
			$structure[] = '<div class="os-timezone-selector-option ' . $selected . '" data-value="' . esc_attr( $value ) . '"><div>' . esc_html( $display ) . '</div><div class="os-timezone-selector-option-local-time">'.$local_time_value.'</div></div>';

			// Close continent optgroup
			if ( ! empty( $zone['city'] ) && ( ! isset( $zonen[ $key + 1 ] ) || ( isset( $zonen[ $key + 1 ] ) && $zonen[ $key + 1 ]['continent'] !== $zone['continent'] ) ) ) {
				$structure[] = '</div>';
			}
		}

		// Do UTC
		$structure[] = '<div class="os-timezone-group"><div class="os-timezone-group-header">'.esc_attr__( 'UTC', 'latepoint' ).'</div>';
		$selected    = '';
		if ( 'UTC' === $selected_zone ) {
			$selected = 'selected';
		}
		$structure[] = '<div class="os-timezone-selector-option ' . $selected . ' data-value="' . esc_attr( 'UTC' ) . '">' . __( 'UTC', 'latepoint' ) . '</div>';
		$structure[] = '</div>';

		return join( "\n", $structure );
	}

	public static function timezones_options_list( $selected_zone, $locale = null ) {
		static $mo_loaded = false, $locale_loaded = null;

		$continents = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific' );

		// Load translations for continents and cities.
		if ( ! $mo_loaded || $locale !== $locale_loaded ) {
			$locale_loaded = $locale ? $locale : get_locale();
			$mofile        = WP_LANG_DIR . '/continents-cities-' . $locale_loaded . '.mo';
			unload_textdomain( 'continents-cities' );
			load_textdomain( 'continents-cities', $mofile );
			$mo_loaded = true;
		}

		$zonen = array();
		foreach ( timezone_identifiers_list() as $zone ) {
			$zone = explode( '/', $zone );
			if ( ! in_array( $zone[0], $continents ) ) {
				continue;
			}

			// This determines what gets set and translated - we don't translate Etc/* strings here, they are done later
			$exists    = array(
				0 => ( isset( $zone[0] ) && $zone[0] ),
				1 => ( isset( $zone[1] ) && $zone[1] ),
				2 => ( isset( $zone[2] ) && $zone[2] ),
			);
			$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
			$exists[4] = ( $exists[1] && $exists[3] );
			$exists[5] = ( $exists[2] && $exists[3] );

			// phpcs:disable WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText
			$zonen[] = array(
				'continent'   => ( $exists[0] ? $zone[0] : '' ),
				'city'        => ( $exists[1] ? $zone[1] : '' ),
				'subcity'     => ( $exists[2] ? $zone[2] : '' ),
				't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ),
				't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ),
				't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' ),
			);
			// phpcs:enable
		}
		usort( $zonen, '_wp_timezone_choice_usort_callback' );

		$structure = array();

		if ( empty( $selected_zone ) ) {
			$structure[] = '<option selected="selected" value="">' . __( 'Select a city', 'latepoint' ) . '</option>';
		}

		foreach ( $zonen as $key => $zone ) {
			// Build value in an array to join later
			$value = array( $zone['continent'] );

			if ( empty( $zone['city'] ) ) {
				// It's at the continent level (generally won't happen)
				$display = $zone['t_continent'];
			} else {
				// It's inside a continent group

				// Continent optgroup
				if ( ! isset( $zonen[ $key - 1 ] ) || $zonen[ $key - 1 ]['continent'] !== $zone['continent'] ) {
					$label       = $zone['t_continent'];
					$structure[] = '<optgroup label="' . esc_attr( $label ) . '">';
				}

				// Add the city to the value
				$value[] = $zone['city'];

				$display = $zone['t_city'];
				if ( ! empty( $zone['subcity'] ) ) {
					// Add the subcity to the value
					$value[] = $zone['subcity'];
					$display .= ' - ' . $zone['t_subcity'];
				}
			}

			// Build the value
			$value    = join( '/', $value );
			$selected = '';
			if ( $value === $selected_zone ) {
				$selected = 'selected="selected" ';
			}
			$structure[] = '<option ' . $selected . 'value="' . esc_attr( $value ) . '">' . esc_html( $label ) . ', ' . esc_html( $display ) . '</option>';

			// Close continent optgroup
			if ( ! empty( $zone['city'] ) && ( ! isset( $zonen[ $key + 1 ] ) || ( isset( $zonen[ $key + 1 ] ) && $zonen[ $key + 1 ]['continent'] !== $zone['continent'] ) ) ) {
				$structure[] = '</optgroup>';
			}
		}

		// Do UTC
		$structure[] = '<optgroup label="' . esc_attr__( 'UTC', 'latepoint' ) . '">';
		$selected    = '';
		if ( 'UTC' === $selected_zone ) {
			$selected = 'selected="selected" ';
		}
		$structure[] = '<option ' . $selected . 'value="' . esc_attr( 'UTC' ) . '">' . __( 'UTC', 'latepoint' ) . '</option>';
		$structure[] = '</optgroup>';

		return join( "\n", $structure );
	}

	public static function format_to_nice_time( $datetime ): string {
		if ( $datetime instanceof DateTime ) {
			$format = self::is_army_clock() ? 'H:i' : 'g:ia';

			return $datetime->format( $format );
		} else {
			return 'n/a';
		}
	}

	public static function format_to_nice_date( $datetime, $hide_year_if_current = false ): string {
		if ( $datetime instanceof DateTime ) {
			if ( $hide_year_if_current && ( $datetime->format( 'Y' ) == self::today_date( 'Y' ) ) ) {
				$format = OsSettingsHelper::get_readable_date_format( true );
			} else {
				$format = OsSettingsHelper::get_readable_date_format();
			}

			return OsUtilHelper::translate_months( $datetime->format( $format ) );
		} else {
			return 'n/a';
		}

	}

}