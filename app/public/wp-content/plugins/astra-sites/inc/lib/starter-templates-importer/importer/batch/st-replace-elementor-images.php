<?php
/**
 * AI content generator and replacer file.
 *
 * @package {{package}}
 * @since 1.1.13
 */

namespace STImporter\Importer\Batch;

use STImporter\Importer\Batch\ST_Replace_Images;
use STImporter\Importer\ST_Importer_Helper;


if ( ! class_exists( 'ST_Replace_Elementor_Images' ) ) :

	/**
	 * Block Editor Blocks Replacer
	 *
	 * @since 1.1.13
	 */
	class ST_Replace_Elementor_Images {

		/**
		 * Instance
		 *
		 * @since 1.1.13
		 * @access private
		 * @var object Class object.
		 */
		private static $instance = null;

		/**
		 * Initiator
		 *
		 * @since 1.1.13
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
		 * @since 1.1.13
		 */
		public function __construct() {}

		/**
		 * Replace the content with AI generated data in Elementor Pages.
		 *
		 * @param object $post Post object.
		 *
		 * @since 1.1.13
		 * @return void
		 */
		public function parse_replace_images( $post ) {

			$post_id        = $post->ID ?? 0;
			$elementor_data = get_post_meta( $post_id, '_elementor_data', true );

			if ( empty( $elementor_data ) ) {
				return;
			}

			$data = is_array( $elementor_data ) ? $elementor_data : json_decode( $elementor_data, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return;
			}

			$this->process_elementor_widgets( $data );

			$updated_data = wp_json_encode( $data );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				update_metadata( 'post', $post_id, '_elementor_data', wp_slash( $updated_data ) );
			}

			if ( class_exists( '\Elementor\Plugin' ) ) {
				$elementor = \Elementor\Plugin::instance();
				$elementor->files_manager->clear_cache();
			}
		}

		/**
		 * Replace placeholder strings in the format {{STRING}} with actual values.
		 *
		 * @param string               $key The key to process.
		 * @param array<string, mixed> $value The value to process.
		 * @return mixed The replaced value.
		 */
		public function replace_widget_image( $key, $value ) {

			switch ( $key ) {

				case 'background_slideshow_gallery':
				case 'wp_gallery':
					foreach ( $value as $index => $image ) {
						if ( ! empty( $image['url'] ) && ! ST_Replace_Images::is_skipable( $image['url'] ) ) {
							$attachment             = $this->get_new_image_attachment( $image );
							$value[ $index ]['url'] = $attachment['url'];
							$value[ $index ]['id']  = $attachment['id'];
						}
					}
					break;

				case 'background_image':
				case 'background_overlay_image':
				case 'image':
					if ( ! empty( $value['url'] ) && ! ST_Replace_Images::is_skipable( $value['url'] ) ) {
						$attachment   = $this->get_new_image_attachment( $value );
						$value['url'] = $attachment['url'];
						$value['id']  = $attachment['id'];
					}
					break;

				case 'wp':
					if ( ! empty( $value['url'] ) && ! ST_Replace_Images::is_skipable( $value['url'] ) ) {
						$attachment             = $this->get_new_image_attachment( $value );
						$value['url']           = $attachment['url'];
						$value['attachment_id'] = $attachment['id'];
					}
					break;

				default:
					break;

			}

			return $value;

		}

		/**
		 * Replace the image URL with the AI generated image.
		 *
		 * @param array<string, mixed> $old_image The old image URL.
		 * @return array<string, mixed> The replaced image URL.
		 */
		public function get_new_image_attachment( $old_image ) {

			$image = ST_Replace_Images::get_instance()->get_image( ST_Replace_Images::$image_index );

			if ( empty( $image ) || ! is_array( $image ) ) {
				return $old_image;
			}

			$image = ST_Importer_Helper::download_image( $image );

			if ( is_wp_error( $image ) ) {
				return $old_image;
			}

			$attachment = wp_prepare_attachment_for_js( absint( $image ) );

			if ( ! is_array( $attachment ) || empty( $attachment['url'] ) ) {
				return $old_image;
			}

			ST_Replace_Images::get_instance()->increment_image_index();

			return $attachment;
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
				if ( isset( $widget['settings'] ) && is_array( $widget['settings'] ) ) {
					foreach ( $widget['settings'] as $key => $value ) {
						if ( is_array( $value ) ) {
							$widget['settings'][ $key ] = $this->replace_widget_image( $key, $value );
						}
					}
				}

				if ( isset( $widget['elements'] ) && is_array( $widget['elements'] ) ) {
					$this->process_elementor_widgets( $widget['elements'] );
				}

				if ( isset( $widget['widgetType'] ) && 'google_maps' === $widget['widgetType'] ) {
					$this->replace_google_map_address( $widget );
				}

				if ( isset( $widget['widgetType'] ) && 'social-icons' === $widget['widgetType'] ) {
					$this->replace_social_icons( $widget );
				}
			}
		}

		/**
		 * Replace the google address.
		 *
		 * @param array<string, mixed> $widget The widget array to process.
		 * @return void
		 */
		public function replace_google_map_address( &$widget ) {

			$address = ST_Importer_Helper::get_business_details( 'business_address' );
			if ( ! empty( $address ) ) {
				$widget['settings']['address'] = $address;
			}
		}


		/**
		 * Replace social icons.
		 *
		 * @param array<string, mixed> $widget The widget array to process.
		 * @return void
		 */
		public function replace_social_icons( &$widget ) {
			$social_profile    = ST_Importer_Helper::get_business_details( 'social_profiles' );
			$social_icons_list = $widget['settings']['social_icon_list'];

			if ( ! is_array( $social_profile ) ) {
				$social_profile = [];
			}
			if ( ! is_array( $social_icons_list ) ) {
				$social_icons_list = [];
			}

			$existing_icons = [];
			$profile_map    = [];

			foreach ( $social_profile as $profile ) {
				$profile_map[ 'fab fa-' . $profile['type'] ] = $profile['url'];
			}

			foreach ( $social_icons_list as $index => $icon ) {
				$icon_type = $icon['social_icon']['value'];

				if ( isset( $profile_map[ $icon_type ] ) ) {
					$widget['settings']['social_icon_list'][ $index ]['link']['url'] = $profile_map[ $icon_type ];
					$existing_icons[] = $icon_type;
				} else {
					unset( $widget['settings']['social_icon_list'][ $index ] );
				}
			}

			foreach ( $social_profile as $profile ) {
				$icon_class = 'fab fa-' . $profile['type'];

				if ( ! in_array( $icon_class, $existing_icons, true ) ) {
					$widget['settings']['social_icon_list'][] = [
						'_id'         => uniqid(),
						'social_icon' => [
							'value'   => $icon_class,
							'library' => 'fa-brands',
						],
						'link'        => [
							'url'               => $profile['url'],
							'is_external'       => true,
							'nofollow'          => '',
							'custom_attributes' => '',
						],
					];
				}
			}
			$widget['settings']['social_icon_list'] = array_values( $widget['settings']['social_icon_list'] );
		}
	}
	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	ST_Replace_Elementor_Images::get_instance();

endif;
