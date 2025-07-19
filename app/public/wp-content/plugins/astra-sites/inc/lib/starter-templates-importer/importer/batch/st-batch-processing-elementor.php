<?php
/**
 * Batch Processing
 *
 * @package ST Importer
 * @since 1.2.14
 */

namespace STImporter\Importer\Batch;

use STImporter\Importer\Batch\ST_Batch_Processing;
use STImporter\Importer\ST_Importer_File_System;
use STImporter\Importer\Helpers\ST_Image_Importer;

if ( ! class_exists( 'ST_Batch_Processing_Elementor' ) ) :

	/**
	 * Astra Sites Batch Processing Brizy
	 *
	 * @since 1.2.14
	 */
	class ST_Batch_Processing_Elementor {

		/**
		 * Instance
		 *
		 * @since 1.2.14
		 * @access private
		 * @var object Class object.
		 */
		private static $instance = null;

		/**
		 * Initiator
		 *
		 * @since 1.2.14
		 * @return object initialized object of class.
		 */
		public static function get_instance() {

			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.2.14
		 */
		public function __construct() {}

		/**
		 * Import
		 *
		 * @since 1.2.14
		 * @return array<string, mixed>
		 */
		public function import() {

			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( 'Processing Batch Import' );
			}

			$post_types = apply_filters( 'astra_sites_elementor_batch_process_post_types', array( 'page', 'post', 'wp_block', 'wp_template', 'wp_navigation', 'wp_template_part', 'wp_global_styles', 'sc_form' ) );
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( 'For post types: ' . implode( ', ', $post_types ) );
			}

			$post_ids = St_Batch_Processing::get_pages( $post_types );

			if ( ! is_array( $post_ids ) ) {
				return array(
					'success' => false,
					'msg'     => __( 'Post ids are empty', 'astra-sites' ),
				);
			}

			foreach ( $post_ids as $post_id ) {
				$this->import_single_post( $post_id );
			}

			return array(
				'success' => true,
				'msg'     => __( 'Batch process completed.', 'astra-sites' ),
			);
		}

		/**
		 * Update post meta.
		 *
		 * @param int $post_id Post ID.
		 * @return void
		 */
		public function import_single_post( $post_id = 0 ) {

			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( 'Elementor - Processing page: ' . $post_id );
			}

			// Is page imported with Starter Sites?
			// If not then skip batch process.
			$imported_from_demo_site = get_post_meta( $post_id, '_astra_sites_enable_for_batch', true );
			if ( ! $imported_from_demo_site ) {
				return;
			}

			$is_elementor_page = get_post_meta( $post_id, '_elementor_version', true );
			$elementor_data    = get_post_meta( $post_id, '_elementor_data', true );

			if ( ! $is_elementor_page || empty( $elementor_data ) ) {
				return;
			}

			$widget_data = is_array( $elementor_data ) ? $elementor_data : json_decode( $elementor_data, true );

			if ( ! is_array( $widget_data ) ) {
				return;
			}

			$this->process_elementor_widgets( $widget_data );

			$updated_data = wp_json_encode( $widget_data );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				update_metadata( 'post', $post_id, '_elementor_data', wp_slash( $updated_data ) );
			}

			if ( class_exists( '\Elementor\Plugin' ) ) {
				$elementor = \Elementor\Plugin::instance();
				$elementor->files_manager->clear_cache();
			}
		}

		/**
		 * Process widgets recursively to replace strings in settings.
		 *
		 * @param array<int, array<mixed>> $widgets The widgets array to process.
		 *
		 * @return void
		 */
		public function process_elementor_widgets( &$widgets ) {

			foreach ( $widgets as &$widget ) {

				if ( isset( $widget['widgetType'] ) && 'shortcode' === $widget['widgetType'] && has_shortcode( $widget['settings']['shortcode'], 'sureforms' ) ) {
					$this->replace_sureforms_ids( $widget );
				}

				if ( isset( $widget['widgetType'] ) && 'sureforms_form' === $widget['widgetType'] ) {
					$this->replace_sureforms_widget_id( $widget );
				}

				if ( isset( $widget['widgetType'] ) && 'shortcode' === $widget['widgetType'] && has_shortcode( $widget['settings']['shortcode'], 'sc_form' ) ) {
					$this->replace_surecart_forms_ids( $widget );
				}

				if ( isset( $widget['elements'] ) && is_array( $widget['elements'] ) ) {
					$this->process_elementor_widgets( $widget['elements'] );
				}
			}
		}

		/**
		 * Replace SureForm IDs in content.
		 *
		 * @since 1.1.9
		 *
		 * @param array<string, mixed> $widget Widget data.
		 * @return void
		 */
		public function replace_sureforms_widget_id( &$widget ) {
			$sureform_id_map = get_option( 'astra_sites_sureforms_id_map', array() );

			if ( empty( $sureform_id_map ) ) {
				return;
			}

			$widget['settings']['srfm_form_block'] = $sureform_id_map[ $widget['settings']['srfm_form_block'] ] ?? $widget['settings']['srfm_form_block'];
		}

		/**
		 * Replace SureForm IDs in content.
		 *
		 * @since 1.1.9
		 *
		 * @param array<string, mixed> $widget Widget data.
		 * @return void
		 */
		public function replace_sureforms_ids( &$widget ) {
			$sureform_id_map = get_option( 'astra_sites_sureforms_id_map', array() );

			if ( empty( $sureform_id_map ) ) {
				return;
			}

			foreach ( $sureform_id_map as $old_id => $new_id ) {
				$widget['settings']['shortcode'] = str_replace( '[sureforms id="' . $old_id . '"]', '[sureforms id="' . $new_id . '"]', $widget['settings']['shortcode'] );
			}
		}

		/**
		 * Replace SureForm IDs in content.
		 *
		 * @since 1.1.9
		 *
		 * @param array<string, mixed> $widget Widget data.
		 * @return void
		 */
		public function replace_surecart_forms_ids( &$widget ) {
			$surecart_forms_id_map = get_option( 'astra_sites_surecart_forms_id_map', array() );

			if ( empty( $surecart_forms_id_map ) ) {
				return;
			}

			foreach ( $surecart_forms_id_map as $old_id => $new_id ) {
				$widget['settings']['shortcode'] = str_replace( '[sc_form id=' . $old_id . ']', '[sc_form id=' . $new_id . ']', $widget['settings']['shortcode'] );
			}

		}
	}
	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	ST_Batch_Processing_Elementor::get_instance();

endif;
