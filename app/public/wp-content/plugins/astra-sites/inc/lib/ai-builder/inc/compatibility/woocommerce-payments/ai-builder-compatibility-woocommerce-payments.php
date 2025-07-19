<?php
/**
 * AI Builder Compatibility for 'WooCommerce Payments'
 *
 * @see  https://wordpress.org/plugins/woocommerce-payments/
 *
 * @package AI Builder
 * @since 1.2.37
 */

if ( ! class_exists( 'Ai_Builder_Compatibility_WooCommerce_Payments' ) ) {

	/**
	 * WooCommerce Payments Compatibility
	 *
	 * @since 1.2.37
	 */
	class Ai_Builder_Compatibility_WooCommerce_Payments {
		/**
		 * Instance
		 *
		 * @access private
		 * @var self Class object.
		 * @since 1.2.37
		 */
		private static $instance;

		/**
		 * Constructor
		 *
		 * @since 1.2.37
		 */
		public function __construct() {
			add_action( 'astra_sites_after_plugin_activation', array( $this, 'woo_payments_activate' ), 10, 2 );

			// Bail early if the WooCommerce Payments plugin is not active.
			if ( ! is_plugin_active( 'woocommerce-payments/woocommerce-payments.php' ) ) {
				return;
			}

			add_action( 'admin_notices', array( $this, 'woo_payments_admin_notice' ), 9 );
			add_action( 'wp_ajax_dismiss_woopayments_notice', array( $this, 'dismiss_woopayments_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_init', array( $this, 'maybe_append_woopayments_ref' ) );
			add_filter( 'cpsw_notices_add_args', array( $this, 'hide_cpsw_stripe_connect_notice' ), 10, 1 );
		}

		/**
		 * Initiator
		 *
		 * @since 1.2.37
		 * @return self Initialized object of the class.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Retrieves the WooCommerce Payments reference ID.
		 *
		 * @since 1.2.37
		 * @return string The WooCommerce Payments reference ID.
		 */
		public static function get_woopayments_ref_id() {
			return 'bsf-2025';
		}

		/**
		 * Check if WooPayments is configured and connected to Stripe.
		 *
		 * @since 1.2.38
		 * @return bool True if WooPayments is active and connected to Stripe, false otherwise.
		 */
		public static function is_woo_payments_configured() {
			// Check if WCPay account is connected to Stripe.
			// @phpstan-ignore-next-line - Dynamic class check.
			if ( class_exists( 'WC_Payments' ) && method_exists( 'WC_Payments', 'get_account_service' ) ) {
				$account_service = WC_Payments::get_account_service();
				if ( is_object( $account_service ) && method_exists( $account_service, 'is_stripe_connected' ) ) {
					return $account_service->is_stripe_connected();
				}
			}

			return false;
		}

		/**
		 * Returns an array of allowed screen IDs for displaying notices or performing trigger actions.
		 *
		 * @since 1.2.38
		 * @return array<string> An array of allowed screen IDs.
		 */
		public static function allowed_screen_ids() {
			return array(
				'dashboard', // WordPress Dashboard.
				'plugins', // Plugins Page.
				'plugins-network', // Network Plugins Page.
				'edit-product', // Products Page.
				'edit-shop_coupon', // Coupons Page.
				'woocommerce_page_wc-orders', // WooCommerce - Orders Page.
				'woocommerce_page_wc-reports', // WooCommerce - Reports Page.
				'woocommerce_page_wc-status', // WooCommerce - Status Page.
				'woocommerce_page_wc-settings', // WooCommerce - Settings Page.
				'woocommerce_page_woo-cart-abandonment-recovery', // Cart Abandonment Recovery Page.
			);
		}

		/**
		 * Enqueue scripts and localize data.
		 *
		 * @since 1.2.37
		 * @return void
		 */
		public function enqueue_scripts() {
			$can_show_notice = $this->can_show_payment_notice();

			// Check if we're on WooPayments connect page.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET used safely here.
			$is_woo_payments_page = isset( $_GET['page'], $_GET['path'] ) && 'wc-admin' === sanitize_text_field( $_GET['page'] ) && '/payments/connect' === sanitize_text_field( rawurldecode( $_GET['path'] ) );

			if ( ! $can_show_notice && ! $is_woo_payments_page ) {
				return;
			}

			wp_register_script(
				'ai-builder-woopayments',
				AI_BUILDER_URL . 'inc/assets/js/woopayments.js',
				array( 'jquery' ),
				AI_BUILDER_VER,
				true
			);

			wp_localize_script(
				'ai-builder-woopayments',
				'aiBuilderWooPayments',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'ajaxNonce'      => wp_create_nonce( 'woopayments_nonce' ),
					'dismissingText' => __( 'To complete your store setup and start selling, you must connect WooPayments now. Without it, your customers won\'t be able to pay.', 'astra-sites' ),
					'dismissedCount' => class_exists( 'Astra_Sites_Page' ) ? Astra_Sites_Page::get_instance()->get_setting( 'woopayments_banner_dismissed_count', 0 ) : 0,
				)
			);

			wp_enqueue_script( 'ai-builder-woopayments' );
		}

		/**
		 * Hide CPSW Stripe Connect Notice.
		 *
		 * @since 1.2.37
		 * @param array<string, mixed> $args Notice arguments.
		 * @return array<string, mixed>|null Modified notice arguments or null to hide.
		 */
		public function hide_cpsw_stripe_connect_notice( array $args ) {
			if (
				'connect_stripe_notice' === $args['id'] &&
				$this->can_show_payment_notice( false )
			) {
				return null; // Hide the notice.
			}
			return $args;
		}

		/**
		 * Dismiss WooCommerce Payments notice.
		 *
		 * @since 1.2.37
		 * @return void
		 */
		public function dismiss_woopayments_notice() {
			// Verify nonce.
			if ( ! isset( $_POST['_security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_security'] ) ), 'woopayments_nonce' ) ) {
				wp_send_json_error( 'Invalid nonce' );
			}

			// Get notice ID.
			$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_id'] ) ) : '';

			if ( empty( $notice_id ) ) {
				wp_send_json_error( 'Notice ID is required' );
			}

			$show_after = 72 * 60 * 60; // 72 hours in seconds.

			// Set a transient that expires in 72 hours (259200 seconds) - to dismiss the notice.
			set_transient( 'astra_sites_woopayments_notice_dismissed', true, $show_after );

			if ( class_exists( 'Astra_Sites_Page' ) ) {
				// Update the setting to track dismissed count.
				$dismissed_count = Astra_Sites_Page::get_instance()->get_setting( 'woopayments_banner_dismissed_count', 0 );
				Astra_Sites_Page::get_instance()->update_settings(
					array(
						'woopayments_banner_dismissed_count' => $dismissed_count + 1,
					)
				);
			}

			wp_send_json_success( 'Notice dismissed successfully' );
		}

		/**
		 * Determines if the payment notice should be shown.
		 *
		 * This method checks various conditions to decide if the payment notice should be displayed.
		 * It verifies the user's capability, WooPayments configuration, notice dismissal status, Astra Sites import completion,
		 * and the current screen to determine if the notice should be shown.
		 *
		 * @since 1.2.37
		 * @param bool $enable_screen_check Flag to enable or disable the screen check.
		 *
		 * @return bool Returns true if the notice should be shown, false otherwise.
		 */
		public function can_show_payment_notice( $enable_screen_check = true ) {
			// If the user does not have the required capability, return false.
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			// Check if the banner was dismissed and the transient is still valid.
			if ( get_transient( 'astra_sites_woopayments_notice_dismissed' ) ) {
				return false;
			}

			// If the Astra Sites import is not complete, return false.
			if ( 'yes' !== get_option( 'astra_sites_import_complete', 'no' ) ) {
				return false;
			}

			// If WooPayments is already configured, no need to show the notice.
			if ( self::is_woo_payments_configured() ) {
				return false;
			}

			// Skip screen check if disabled.
			if ( ! $enable_screen_check ) {
				return true;
			}

			// Screen check.
			$screen = get_current_screen();
			return $screen && in_array( $screen->id, self::allowed_screen_ids(), true );
		}

		/**
		 * Get Stripe Connect URL.
		 *
		 * @since 1.2.37
		 * @return string
		 */
		public function get_stripe_connect_url() {
			return admin_url(
				add_query_arg(
					array(
						'page'            => 'wc-admin',
						'path'            => '/payments/connect',
						'woopayments-ref' => self::get_woopayments_ref_id(),
					),
					'admin.php'
				)
			);
		}

		/**
		 * Disable redirect after installing and activating WooCommerce Payments.
		 *
		 * @param string               $plugin_init Plugin init file used for activation.
		 * @param array<string, mixed> $data        Data.
		 *
		 * @since 1.2.37
		 * @return void
		 */
		public function woo_payments_activate( $plugin_init, $data = array() ) {
			if ( 'woocommerce-payments/woocommerce-payments.php' === $plugin_init ) {
				// Prevent showing the banner if plugin was already active.
				if ( isset( $data['was_plugin_active'] ) && $data['was_plugin_active'] ) {
					return;
				}

				delete_option( 'wcpay_should_redirect_to_onboarding' );
			}
		}

		/**
		 * This method is responsible for displaying an admin notice to the user, prompting them to connect their WooCommerce Payments account.
		 * It checks if the conditions are met to show the notice, and if so, it outputs the notice content.
		 *
		 * @since 1.2.37
		 * @return void
		 */
		public function woo_payments_admin_notice() {
			// Bail early if the notice can not be shown.
			if ( ! $this->can_show_payment_notice() ) {
				return;
			}

			// Connect SVG image path.
			$image_path = esc_url( AI_BUILDER_URL . 'inc/assets/images/payments-connect.png' );

			$output = sprintf(
				'
					<div class="woop-notice-content">
						<div class="woop-notice-heading">
							%2$s
						</div>
						<div class="woop-notice-description">
							%3$s
						</div>
						<div class="woop-notice-action-container">
							<a href="%4$s" class="woop-notice-btn" data-source="banner">
							%5$s
							</a>
						</div>
					</div>
					<div class="woop-notice-image" style="display: flex;">
						<img src="%1$s" class="notice-image" alt="Connect to Stripe" itemprop="logo">
					</div>
				',
				$image_path,
				esc_html__( 'You’re One Step Away from Accepting Payments with WooPayments — Fast, Easy & Free!', 'astra-sites' ),
				sprintf(
					/* translators: %s: WooPayments for WooCommerce text with strong tag */
					__(
						'Your store is ready to sell! Connect %s now to securely accept credit cards, Apple Pay, and more — with zero setup fees and instant integration.',
						'astra-sites'
					),
					'<strong>' . __( 'WooPayments', 'astra-sites' ) . '</strong>'
				),
				esc_url( $this->get_stripe_connect_url() ),
				esc_html__( 'Start Accepting Payments', 'astra-sites' )
			);
			?>
			<style>
				#connect_woop_notice.notice-info.mega-notice.woop-dismissible-notice {
					position: relative;
					display: flex;
					border: 1px solid #e6daf9;
					gap: 10%;
					align-items: center;
					padding: 34px !important;
					background: linear-gradient(90deg, #fff 23.33%, #F2EAFF80 47.42%);
					box-shadow: 0 2px 8px -2px #00000014;
				}
				#connect_woop_notice .woop-notice-content {
					font-size: 16px;
					font-weight: 400;
					line-height: 26px;
					text-align: left;
					margin-left: 0;
					display: flex;
					flex-direction: column;
					gap: 15px;
					width: 65%;
				}
				#connect_woop_notice .woop-notice-heading {
					font-size: 20px;
					font-weight: 600;
					line-height: 32px;
					text-align: left;
					margin: 0;
				}
				#connect_woop_notice .woop-notice-action-container {
					display: flex;
					align-items: center;
					column-gap: 20px;
					margin-top: 10px;
				}
				#connect_woop_notice .woop-notice-action-container a {
					background: #873EFF;
					box-shadow: 0 1px 2px 0 #0000000d;
					color: #fff !important;
					border-color: #873EFF;
					padding: 14px 16px 14px 16px;
					border-radius: 6px;
					font-size: 16px;
					font-weight: 500;
					line-height: 16px;
					text-align: center;
					text-decoration: none;
					transition: 0.2s ease;
				}
				#connect_woop_notice .woop-notice-action-container a:hover {
					background-color: #5f2db1;
				}
				#connect_woop_notice .woop-notice-image{
					width: 35%;
					display: flex;
					justify-content: center;
					align-items: center;
				}
				#connect_woop_notice .woop-notice-image img {
					width: 56%;
				}
			</style>
			<div id="connect_woop_notice" class="notice-info mega-notice woop-dismissible-notice woop-notice woop-custom-notice notice">
				<button type="button" class="notice-dismiss"></button>
				<?php echo wp_kses_post( $output ); ?>
			</div>
			<?php
		}

		/**
		 * This method checks if the WooCommerce Payments reference option is not set and if the current page is the WooCommerce Payments connect page.
		 * If both conditions are true, it appends the WooCommerce Payments reference ID to the URL query string.
		 * This is done to ensure that the reference ID is included in the connection process for WooCommerce Payments.
		 *
		 * @since 1.2.37
		 * @return void
		 */
		public function maybe_append_woopayments_ref() {
			// Bail early if the WooCommerce Payments plugin was not activated through the Starter Templates import process and was already activated on the site.
			if ( ! class_exists( 'Astra_Sites_Page' ) || ! Astra_Sites_Page::get_instance()->get_setting( 'woopayments_ref' ) ) {
				return;
			}

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET used safely here.
			if (
				is_admin() &&
				isset( $_GET['page'], $_GET['path'] ) &&
				'wc-admin' === sanitize_text_field( $_GET['page'] ) &&
				'/payments/connect' === sanitize_text_field( rawurldecode( $_GET['path'] ) ) &&
				! isset( $_GET['woopayments-ref'] )
			) {
				// phpcs:enable WordPress.Security.NonceVerification.Recommended -- Read-only GET used safely here.
				$ref_value = self::get_woopayments_ref_id();

				// Preserve all existing query vars.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Query string is being modified safely.
				$query_args                    = $_GET;
				$query_args['woopayments-ref'] = $ref_value;

				// Build the URL with updated args.
				$new_url = admin_url( 'admin.php' ) . '?' . http_build_query( $query_args );

				// Perform safe redirect and exit.
				wp_safe_redirect( esc_url_raw( $new_url ) );
				exit;
			}
		}
	}

	/**
	 * Kicking this off by calling 'instance()' method
	 */
	Ai_Builder_Compatibility_WooCommerce_Payments::instance();
}
