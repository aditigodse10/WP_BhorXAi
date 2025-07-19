<?php
/**
 * Astra Sites Compatibility for 'LatePoint'
 *
 * @see  https://wordpress.org/plugins/latepoint/
 *
 * @package Astra Sites
 * @since 4.4.14
 */

if ( ! class_exists( 'Astra_Sites_Compatibility_LatePoint' ) ) :

	/**
	 * LatePoint Compatibility
	 *
	 * @since 4.4.14
	 */
	class Astra_Sites_Compatibility_LatePoint {

		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 4.4.14
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 4.4.14
		 * @return object initialized object of class.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 4.4.14
		 */
		public function __construct() {
			add_action( 'astra_sites_after_plugin_activation', array( $this, 'disable_latepoint_redirection' ) );
		}

		/**
		 * Disables LatePoint redirection during plugin activation.
		 *
		 * @param string $plugin_init The path to the plugin file that was just activated.
		 *
		 * @since 4.4.14
		 */
		public function disable_latepoint_redirection( $plugin_init ) {
			if ( 'latepoint/latepoint.php' === $plugin_init ) {
				update_option( 'latepoint_redirect_to_wizard', false );
				update_option( 'latepoint_show_version_5_modal', false );
			}
		}
	}

	/**
	 * Kicking this off by calling 'instance()' method
	 */
	Astra_Sites_Compatibility_LatePoint::instance();

endif;
