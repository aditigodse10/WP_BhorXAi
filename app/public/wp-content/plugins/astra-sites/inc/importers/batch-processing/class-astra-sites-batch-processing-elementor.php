<?php
/**
 * Elementor Importer
 *
 * @package Astra Sites
 */

namespace Elementor\TemplateLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If plugin - 'Elementor' not exist then return.
if ( ! class_exists( '\Elementor\Plugin' ) ) {
	return;
}

use Elementor\Core\Base\Document;
use Elementor\Core\Editor\Editor;
use Elementor\DB;
use Elementor\Core\Settings\Manager as SettingsManager;
use Elementor\Core\Settings\Page\Model;
use Elementor\Modules\Library\Documents\Library_Document;
use Elementor\Plugin;
use Elementor\Utils;

/**
 * Elementor template library local source.
 *
 * Elementor template library local source handler class is responsible for
 * handling local Elementor templates saved by the user locally on his site.
 *
 * @since 1.2.13 Added compatibility for Elemetnor v2.5.0
 * @since 1.0.0
 */
class Astra_Sites_Batch_Processing_Elementor extends Source_Local {

	/**
	 * Import
	 *
	 * @since 1.0.14
	 * @return void
	 */
	public function import() {

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Processing "Elementor" Batch Import' );
		}
		\Astra_Sites_Importer_Log::add( '---- Processing WordPress Posts / Pages - for Elementor ----' );
		$post_types = \Astra_Sites_Batch_Processing::get_post_types_supporting( 'elementor' );

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'For post types: ' . implode( ', ', $post_types ) );
		}
		if ( empty( $post_types ) && ! is_array( $post_types ) ) {
			return;
		}

		// 🎯 Image Duplication fix: Use simple query and filter in PHP.
		$post_ids = \Astra_Sites_Batch_Processing::get_pages( $post_types );
		if ( empty( $post_ids ) && ! is_array( $post_ids ) ) {
			return;
		}

		// 🎯 PERFORMANCE FIX: Filter out already processed pages in PHP (faster than complex meta_query).
		$unprocessed_posts = $this->filter_unprocessed_posts( $post_ids );
		
		if ( empty( $unprocessed_posts ) ) {
			\Astra_Sites_Importer_Log::add( 'No Elementor pages found that need processing.' );
			return;
		}

		\Astra_Sites_Importer_Log::add( 'Found ' . count( $unprocessed_posts ) . ' Elementor pages to process.' );

		foreach ( $unprocessed_posts as $post_id ) {
			$this->import_single_post( $post_id );
		}
	}

	/**
	 * Update post meta.
	 *
	 * @since 1.0.14
	 * @param  integer $post_id Post ID.
	 * @return void
	 */
	public function import_single_post( $post_id = 0 ) {

		$is_elementor_post = get_post_meta( $post_id, '_elementor_version', true );
		if ( ! $is_elementor_post ) {
			return;
		}

		// Is page imported with Starter Sites?
		// If not then skip batch process.
		$imported_from_demo_site = get_post_meta( $post_id, '_astra_sites_enable_for_batch', true );
		if ( ! $imported_from_demo_site ) {
			return;
		}

		// 🎯 FIX: Check if this page has already been processed by Elementor batch processing.
		$already_processed = get_post_meta( $post_id, '_astra_sites_hotlink_imported', true );
		if ( $already_processed ) {
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( 'Elementor - Skipping already processed page: ' . $post_id );
			}
			\Astra_Sites_Importer_Log::add( '---- Skipping Already Processed Elementor Page ---- "' . $post_id . '"' );
			return;
		}

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Elementor - Processing page: ' . $post_id );
		}

		\Astra_Sites_Importer_Log::add( '---- Processing WordPress Page - for Elementor ---- "' . $post_id . '"' );

		if ( ! empty( $post_id ) ) {

			$data = get_post_meta( $post_id, '_elementor_data', true );

			\Astra_Sites_Importer_Log::add( wp_json_encode( $data ) );

			if ( ! empty( $data ) ) {

				// Update WP form IDs.
				$ids_mapping = get_option( 'astra_sites_wpforms_ids_mapping', array() );
				\Astra_Sites_Importer_Log::add( wp_json_encode( $ids_mapping ) );
				if ( $ids_mapping ) {
					foreach ( $ids_mapping as $old_id => $new_id ) {
						$data = str_replace( '[wpforms id=\"' . $old_id, '[wpforms id=\"' . $new_id, $data );
						$data = str_replace( '"select_form":"' . $old_id, '"select_form":"' . $new_id, $data );
						$data = str_replace( '"form_id":"' . $old_id, '"form_id":"' . $new_id, $data );
					}
				}

				if ( ! is_array( $data ) ) {
					$data = json_decode( $data, true );
				}
				\Astra_Sites_Importer_Log::add( wp_json_encode( $data ) );

				$document = Plugin::$instance->documents->get( $post_id );
				if ( $document ) {
					$data = $document->get_elements_raw_data( $data, true );
				}

				// Import the data.
				$data = $this->process_export_import_content( $data, 'on_import' );

				// Replace the site urls.
				$demo_data = \Astra_Sites_File_System::get_instance()->get_demo_content();
				\Astra_Sites_Importer_Log::add( wp_json_encode( $demo_data ) );
				if ( isset( $demo_data['astra-site-url'] ) ) {
					$data = wp_json_encode( $data );
					if ( ! empty( $data ) ) {
						$site_url      = get_site_url();
						$site_url      = str_replace( '/', '\/', $site_url );
						$demo_site_url = 'https:' . $demo_data['astra-site-url'];
						$demo_site_url = str_replace( '/', '\/', $demo_site_url );
						$data          = str_replace( $demo_site_url, $site_url, $data );
						$data          = wp_slash( $data ); // slash is added for smooth unslashing on updating metadata.
					}
				}

				// Update processed meta.
				update_metadata( 'post', $post_id, '_elementor_data', $data );
				update_metadata( 'post', $post_id, '_astra_sites_hotlink_imported', true );

				// 🎯 FIX: Add timestamp to track when processing was completed.
				update_metadata( 'post', $post_id, '_astra_sites_elementor_processed_time', time() );

				// !important, Clear the cache after images import.
				Plugin::$instance->files_manager->clear_cache();
			}

			// Clean the post excerpt.
			$clean_post_excerpt = apply_filters( 'astra_sites_pre_process_post_empty_excerpt', true );
			if ( $clean_post_excerpt ) {
				astra_sites_empty_post_excerpt( $post_id );
			}
		}
	}

	/**
	 * Filter posts to get only those that need Elementor processing
	 *
	 * @since 4.4.27
	 * @param array $post_ids Array of post IDs to filter.
	 * @return array Filtered array of post IDs that need processing.
	 */
	private function filter_unprocessed_posts( $post_ids ) {
		
		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return array();
		}

		$unprocessed_posts = array();

		foreach ( $post_ids as $post_id ) {
			// Check if it's an Elementor page.
			$is_elementor_post = get_post_meta( $post_id, '_elementor_version', true );
			if ( ! $is_elementor_post ) {
				continue;
			}

			// Check if it's enabled for batch processing.
			$enabled_for_batch = get_post_meta( $post_id, '_astra_sites_enable_for_batch', true );
			if ( ! $enabled_for_batch ) {
				continue;
			}

			// 🎯 FIX: Skip if already processed.
			$already_processed = get_post_meta( $post_id, '_astra_sites_hotlink_imported', true );
			if ( $already_processed ) {
				continue;
			}

			$unprocessed_posts[] = $post_id;
		}

		return $unprocessed_posts;
	}
}
