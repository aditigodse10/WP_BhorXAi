<?php
/**
 * Astra Sites Compatibility for 'Spectra Pro'
 *
 * @see  https://wpspectra.com/pricing/
 *
 * @package Astra Sites
 * @since 4.4.21
 */

if ( ! class_exists( 'Astra_Sites_Compatibility_Spectra_Pro' ) ) {

	/**
	 * Spectra Pro Compatibility.
	 *
	 * @since 4.4.21
	 */
	class Astra_Sites_Compatibility_Spectra_Pro {

		/**
		 * Instance.
		 *
		 * @access private
		 * @var self Class object.
		 * @since 4.4.21
		 */
		private static $instance;

		/**
		 * Initiator.
		 *
		 * @since 4.4.21
		 * @return self initialized object of class.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @since 4.4.21
		 */
		public function __construct() {
			add_action( 'astra_sites_after_plugin_activation', array( $this, 'disable_spectra_pro_redirection' ) );
		}

		/**
		 * Disables Spectra Pro  redirection during plugin activation.
		 *
		 * @param string $plugin_init The path to the plugin file that was just activated.
		 *
		 * @since 4.4.21
		 */
		public function disable_spectra_pro_redirection( $plugin_init ) {
			if ( 'spectra-pro/spectra-pro.php' === $plugin_init ) {
				delete_option( '__spectra_pro_do_redirect' );
			}
		}
	}

	/**
	 * Kicking this off by calling 'instance()' method.
	 */
	Astra_Sites_Compatibility_Spectra_Pro::instance();
}
