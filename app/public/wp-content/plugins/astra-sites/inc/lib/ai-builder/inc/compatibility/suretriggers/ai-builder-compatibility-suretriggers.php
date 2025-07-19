<?php
/**
 * Astra Sites Compatibility for 'Suretriggers'
 *
 * @see  https://wordpress.org/plugins/ultimate-addons-for-beaver-builder-lite/
 *
 * @package Astra Sites
 * @since 4.0.8
 */

namespace AiBuilder\Inc\Compatibility\SureCart;

/**
 * Suretriggers compatibility for Starter Templates.
 */
class Ai_Builder_Compatibility_Suretriggers {
	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class object.
	 * @since 4.0.8
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'astra_sites_after_plugin_activation', array( $this, 'suretriggers_activation' ), 10 );
	}

	/**
	 * Initiator
	 *
	 * @since 4.0.8
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof Ai_Builder_Compatibility_Suretriggers ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Disable redirect after installing and activating UABB.
	 *
	 * @since 4.0.8
	 * @param string $plugin_init The path to the plugin file that was just activated.
	 * @return void
	 */
	public function suretriggers_activation( $plugin_init ) {
		if ( 'suretriggers/suretriggers.php' === $plugin_init ) {
			delete_transient( 'st-redirect-after-activation' );
		}
	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
Ai_Builder_Compatibility_Suretriggers::get_instance();
