<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.3cx.com
 * @since      10.0.0
 *
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      10.0.0
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/includes
 * @author     3CX <wordpress@3cx.com>
 */
class wplc_Plugin_i18n {

	public function load_plugin_textdomain() {

    // must contain domain string and not variable otherwise translation tools does not recognize!
		load_plugin_textdomain('wp-live-chat-support', 
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);

	}

}
