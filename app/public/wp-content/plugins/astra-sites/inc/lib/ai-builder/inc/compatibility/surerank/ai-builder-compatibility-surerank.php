<?php
/**
 * AI Builder Compatibility for 'SureRank'.
 *
 * @package AI Builder
 * @since 1.2.44
 */

if ( ! class_exists( 'Ai_Builder_Compatibility_SureRank' ) ) {

	/**
	 * SureRank Compatibility.
	 *
	 * @since 1.2.44
	 */
	class Ai_Builder_Compatibility_SureRank {
		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 1.2.44
		 */
		private static $instance;

		/**
		 * Constructor
		 *
		 * @since 1.2.44
		 */
		public function __construct() {
			add_action( 'astra_sites_after_plugin_activation', array( $this, 'disable_surerank_redirection' ) );
		}

		/**
		 * Initiator
		 *
		 * @since 1.2.44
		 * @return object initialized object of class.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Disables SureRank redirection during plugin activation.
		 *
		 * @param string $plugin_init The path to the plugin file that was just activated.
		 *
		 * @since 1.2.44
		 * @return void
		 */
		public function disable_surerank_redirection( $plugin_init ) {
			if ( 'surerank/surerank.php' === $plugin_init ) {
				delete_option( 'surerank_redirect_on_activation' );
			}
		}
	}

	/**
	 * Kicking this off by calling 'instance()' method
	 */
	Ai_Builder_Compatibility_SureRank::instance();
}
