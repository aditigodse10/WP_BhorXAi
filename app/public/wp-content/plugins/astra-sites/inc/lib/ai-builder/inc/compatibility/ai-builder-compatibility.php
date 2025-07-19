<?php
/**
 * AI Builder Compatibility for 3rd party plugins.
 *
 * @package AI Builder
 * @since 1.0.11
 */

if ( ! class_exists( 'Ai_Builder_Compatibility' ) ) {

	/**
	 * AI Builder Compatibility
	 *
	 * @since 1.0.11
	 */
	class Ai_Builder_Compatibility {
		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 1.0.11
		 */
		private static $instance = null;

		/**
		 * Constructor
		 *
		 * @since 1.0.11
		 */
		public function __construct() {

			// Plugin - Spectra.
			require_once AI_BUILDER_DIR . 'inc/compatibility/uag/ai-builder-compatibility-uag.php';

			// Plugin - WooCommerce.
			require_once AI_BUILDER_DIR . 'inc/compatibility/surecart/ai-builder-compatibility-surecart.php';

			// Plugin - Elementor.
			require_once AI_BUILDER_DIR . 'inc/compatibility/elementor/ai-builder-compatibility-elementor.php';

			// Plugin - Cartflows.
			require_once AI_BUILDER_DIR . 'inc/compatibility/cartflows/ai-builder-compatibility-cartflows.php';

			// Plugin - Suretriggers.
			require_once AI_BUILDER_DIR . 'inc/compatibility/suretriggers/ai-builder-compatibility-suretriggers.php';

			// Plugin - CPSW.
			require_once AI_BUILDER_DIR . 'inc/compatibility/checkout-plugins-stripe-woo/ai-builder-compatibility-cpsw.php';

			// Plugin - SureForms.
			require_once AI_BUILDER_DIR . 'inc/compatibility/sureforms/ai-builder-compatibility-sureforms.php';

			// Plugin - SureMail.
			require_once AI_BUILDER_DIR . 'inc/compatibility/suremail/ai-builder-compatibility-suremail.php';

			// Plugin - Latepoint.
			require_once AI_BUILDER_DIR . 'inc/compatibility/latepoint/ai-builder-compatibility-latepoint.php';

			// Plugin - Ultimate Addons for Elementor.
			require_once AI_BUILDER_DIR . 'inc/compatibility/uae/ai-builder-compatibility-uae.php';

			// Plugin - Ultimate Addons for Elementor Lite.
			require_once AI_BUILDER_DIR . 'inc/compatibility/uae-lite/ai-builder-compatibility-uae-lite.php';

			// Plugin - WooCommerce Payments.
			require_once AI_BUILDER_DIR . 'inc/compatibility/woocommerce-payments/ai-builder-compatibility-woocommerce-payments.php';

			// Plugin - SureRank.
			require_once AI_BUILDER_DIR . 'inc/compatibility/surerank/ai-builder-compatibility-surerank.php';
		}

		/**
		 * Initiator
		 *
		 * @since 1.0.11
		 * @return object initialized object of class.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

	}

	/**
	 * Kicking this off by calling 'instance()' method
	 */
	Ai_Builder_Compatibility::instance();

}
