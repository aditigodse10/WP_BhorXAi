<?php
/**
 * Astra Sites Compatibility for 3rd party plugins.
 *
 * @package Astra Sites
 * @since 1.0.11
 */

if ( ! class_exists( 'Astra_Sites_Compatibility' ) ) :

	/**
	 * Astra Sites Compatibility
	 *
	 * @since 1.0.11
	 */
	class Astra_Sites_Compatibility {

		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 1.0.11
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.0.11
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
		 * @since 1.0.11
		 */
		public function __construct() {

			// Plugin - Astra Pro.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/astra-pro/class-astra-sites-compatibility-astra-pro.php';

			// Plugin - WooCommerce.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/woocommerce/class-astra-sites-compatibility-woocommerce.php';

			// Plugin - LearnDash LMS.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/sfwd-lms/class-astra-sites-compatibility-sfwd-lms.php';

			// Plugin - Beaver Builder.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/beaver-builder/class-astra-sites-compatibility-bb.php';

			// Plugin - LearnDash.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/learndash/class-astra-sites-compatibility-learndash.php';

			// Plugin - UABB.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/uabb/class-astra-sites-compatibility-uabb.php';

			// Plugin - Spectra Pro.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/spectra-pro/class-astra-sites-compatibility-spectra-pro.php';

			// Plugin - Cart Abandonment Recovery for WooCommerce.
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/cart-abandonment-recovery/class-astra-sites-compatibility-cartflows-ca.php';
		}

	}

	/**
	 * Kicking this off by calling 'instance()' method
	 */
	Astra_Sites_Compatibility::instance();

endif;


