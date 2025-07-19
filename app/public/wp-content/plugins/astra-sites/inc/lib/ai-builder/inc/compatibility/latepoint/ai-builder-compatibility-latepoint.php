<?php
/**
 * AI Builder Compatibility for 'LatePoint'
 *
 * @see  https://wordpress.org/plugins/latepoint/
 *
 * @package AI Builder
 * @since 1.2.30
 */

if ( ! class_exists( 'Ai_Builder_Compatibility_LatePoint' ) ) {

	/**
	 * LatePoint Compatibility
	 *
	 * @since 1.2.30
	 */
	class Ai_Builder_Compatibility_LatePoint {
		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 1.2.30
		 */
		private static $instance;

		/**
		 * Constructor
		 *
		 * @since 1.2.30
		 */
		public function __construct() {
			add_action( 'astra_sites_after_plugin_activation', array( $this, 'disable_latepoint_redirection' ) );
		}

		/**
		 * Initiator
		 *
		 * @since 1.2.30
		 * @return object initialized object of class.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Disables LatePoint redirection during plugin activation.
		 *
		 * @param string $plugin_init The path to the plugin file that was just activated.
		 *
		 * @since 1.2.30
		 * @return void
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
	Ai_Builder_Compatibility_LatePoint::instance();

}
