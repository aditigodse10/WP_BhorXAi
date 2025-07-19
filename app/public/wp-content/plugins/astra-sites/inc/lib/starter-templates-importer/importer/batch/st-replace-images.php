<?php
/**
 * Replace Images
 *
 * @since 3.1.4
 * @package Astra Sites
 */

namespace STImporter\Importer\Batch;

use STImporter\Importer\ST_Importer_Helper;
use AiBuilder\Inc\Traits\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace Images
 */
class ST_Replace_Images {

	/**
	 * Member Variable
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Image index
	 *
	 * @since 4.1.0
	 * @var int
	 */
	public static $image_index = 0;

	/**
	 * Old Images ids
	 *
	 * @var array<int,int>
	 * @since 4.1.0
	 */
	public static $old_image_urls = array();

	/**
	 * Filtered images.
	 *
	 * @var array<int, array<string, string>>
	 */
	public static $filtered_images = array();

	/**
	 * Initiator
	 *
	 * @since 3.1.4
	 *
	 * @return self
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
	 * @since 3.1.4
	 */
	public function __construct() {
	}


	/**
	 * Replace images in pages.
	 *
	 * @since 4.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function replace_images() {

		$pages_replacement      = $this->replace_in_pages();
		$posts_replacement      = $this->replace_in_post();
		$customizer_replacement = array(
			'success' => true,
		);

		// Replace customizer content.
		if ( function_exists( 'astra_update_option' ) && function_exists( 'astra_get_option' ) ) {
			$this->replace_in_customizer();
		} else {
			$customizer_replacement = array(
				'success' => false,
				'msg'     => __( 'Astra functions not available', 'astra-sites' ),
			);
		}

		$this->cleanup();

		if ( ! $pages_replacement['success'] ) {
			return array(
				'success' => false,
				'msg'     => $pages_replacement['msg'],
			);
		}

		if ( ! $posts_replacement['success'] ) {
			return array(
				'success' => false,
				'msg'     => $posts_replacement['msg'],
			);
		}

		if ( ! $customizer_replacement['success'] ) {
			return array(
				'success' => false,
				'msg'     => $customizer_replacement['msg'],
			);
		}

		return array(
			'success' => true,
			'msg'     => __( 'Image Replacement completed.', 'astra-sites' ),
		);

	}

	/**
	 * Replace images in post.
	 *
	 * @since 4.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function replace_in_post() {

		$posts = $this->get_pages( 'post' );

		if ( empty( $posts ) ) {
			return array(
				'success' => true,
				'msg'     => __( 'Posts are empty. Nothing to process.', 'astra-sites' ),
			);
		}

		foreach ( $posts as $key => $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$this->parse_featured_image( $post );
		}
		return array(
			'success' => true,
			'msg'     => __( 'Posts are replaced', 'astra-sites' ),
		);
	}

	/** Parses images and other content in the Spectra Info Box block.
	 *
	 * @since {{since}}
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function parse_featured_image( $post ) {

		$image = $this->get_image( self::$image_index );

		if ( empty( $image ) || ! is_array( $image ) ) {
			return;
		}

		$image = ST_Importer_Helper::download_image( $image );

		if ( is_wp_error( $image ) ) {
			return;
		}

		$attachment = wp_prepare_attachment_for_js( absint( $image ) );
		if ( ! is_array( $attachment ) ) {
			return;
		}

		set_post_thumbnail( $post, $attachment['id'] );

		$this->increment_image_index();
	}

	/**
	 * Cleanup the old images.
	 *
	 * @return void
	 * @since 4.1.0
	 */
	public function cleanup() {
		$old_image_urls = self::$old_image_urls;
		if ( ! empty( $old_image_urls ) ) {

			$guid_list = implode( "', '", $old_image_urls );

			global $wpdb;
			$query = "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid IN ('$guid_list')";

			$old_image_ids = $wpdb->get_results( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $old_image_ids as $old_image_id ) {
				wp_delete_attachment( $old_image_id->ID, true );
			}
		}
		delete_option( 'ast_sites_downloaded_images' );
		delete_option( 'astra_sites_ai_imports' );
		delete_option( 'astra_sites_sureforms_id_map' );
		delete_option( 'astra_sites_surecart_forms_id_map' );
	}

	/**
	 * Replace images in customizer.
	 *
	 * @since 4.1.0
	 *
	 * @return void
	 */
	public function replace_in_customizer() {

		$footer_image_obj = astra_get_option( 'footer-bg-obj-responsive' );
		if ( isset( $footer_image_obj ) && ! empty( $footer_image_obj ) ) {
			$footer_image_obj = $this->get_updated_astra_option( $footer_image_obj );
			astra_update_option( 'footer-bg-obj-responsive', $footer_image_obj );
		}

		$header_image_obj = astra_get_option( 'header-bg-obj-responsive' );
		if ( isset( $header_image_obj ) && ! empty( $header_image_obj ) ) {
			$header_image_obj = $this->get_updated_astra_option( $header_image_obj );
			astra_update_option( 'header-bg-obj-responsive', $header_image_obj );
		}

		$blog_archieve_image_obj = astra_get_option( 'ast-dynamic-archive-post-banner-custom-bg' );
		if ( isset( $blog_archieve_image_obj ) && ! empty( $blog_archieve_image_obj ) ) {
			$blog_archieve_image_obj = $this->get_updated_astra_option( $blog_archieve_image_obj );
			astra_update_option( 'ast-dynamic-archive-post-banner-custom-bg', $blog_archieve_image_obj );
		}

		$sc_product_banner_image = astra_get_option( 'ast-dynamic-archive-sc_product-banner-custom-bg' );
		if ( isset( $sc_product_banner_image ) && ! empty( $sc_product_banner_image ) ) {
			$sc_product_banner_image = $this->get_updated_astra_option( $sc_product_banner_image );
			astra_update_option( 'ast-dynamic-archive-sc_product-banner-custom-bg', $sc_product_banner_image );
		}

		$wc_shop_banner_image = astra_get_option( 'ast-dynamic-archive-product-banner-custom-bg' );
		if ( isset( $wc_shop_banner_image ) && ! empty( $wc_shop_banner_image ) ) {
			$wc_shop_banner_image = $this->get_updated_astra_option( $wc_shop_banner_image );
			astra_update_option( 'ast-dynamic-archive-product-banner-custom-bg', $wc_shop_banner_image );
		}

		$social_options = $this->get_options();

		/**
		 * Social Element Options
		 */
		$this->update_social_options( $social_options );
	}

	/**
	 * Update the Social options
	 *
	 * @param array<int, string> $options Social Options.
	 * @since  {{since}}
	 * @return void
	 */
	public function update_social_options( $options ) {
		if ( ! empty( $options ) ) {
			$social_profiles = ST_Importer_Helper::get_business_details( 'social_profiles' );
			$business_phone  = ST_Importer_Helper::get_business_details( 'business_phone' );
			$business_email  = ST_Importer_Helper::get_business_details( 'business_email' );
			if ( is_array( $options ) && is_array( $social_profiles ) ) {
				foreach ( $options as $key => $name ) {
					$value        = astra_get_option( $name );
					$items        = isset( $value['items'] ) ? $value['items'] : array();
					$social_icons = array_map(
						function( $item ) {
							return $item['type'];
						},
						$social_profiles
					);

					$social_icons = array_merge( $social_icons, array( 'phone', 'email' ) );

					if ( is_array( $items ) && ! empty( $items ) ) {
						foreach ( $items as $index => &$item ) {

							$cached_first_item = isset( $items[0] ) ? $items[0] : [];

							if ( ! in_array( $item['id'], $social_icons, true ) ) {
								unset( $items[ $index ] );
								continue;
							}

							if ( $item['enabled'] && false !== strpos( $item['id'], 'phone' ) && '' !== $business_phone ) {
								$item['url'] = $business_phone;
							}
							if ( $item['enabled'] && false !== strpos( $item['id'], 'email' ) && '' !== $business_email ) {
								$item['url'] = $business_email;
							}
							if ( is_array( $social_profiles ) && ! empty( $social_profiles ) ) {
								$id  = $item['id'];
								$src = array_reduce(
									$social_profiles,
									function ( $carry, $element ) use ( $id ) {
										if ( ! $carry && $element['type'] === $id ) {
											$carry = $element;
										}
										return $carry;
									}
								);
								if ( ! empty( $src ) ) {
									$item['url'] = $src['url'];
								}
							}
						}
						$yelp_google = [ 'yelp', 'google' ];

						foreach ( $yelp_google as $yelp_google_item ) {
							if ( in_array( $yelp_google_item, $social_icons, true ) && ! empty( $cached_first_item ) ) {
								$new_inner_item          = $cached_first_item;
								$new_inner_item['id']    = $yelp_google_item;
								$new_inner_item['icon']  = $yelp_google_item;
								$new_inner_item['label'] = ucfirst( $yelp_google_item );
								$link                    = '#';
								if ( is_array( $social_profiles ) ) {
									foreach ( $social_profiles as $social_icon ) {
										if ( $yelp_google_item === $social_icon['type'] ) {
											$link = $social_icon['url'];
											break;
										}
									}
								}
								$new_inner_item['url'] = $link;
								$items[]               = $new_inner_item;
							}
						}
						$value['items'] = array_values( $items );
						astra_update_option( $name, $value );
					}
				}
			}
		}
	}


	/**
	 * Gather all options eligible for replacement algorithm.
	 * All elements placed in Header and Footer builder.
	 *
	 * @since  {{since}}
	 * @return array<int, string> $options Options.
	 */
	public function get_options() {
		$zones          = array( 'above', 'below', 'primary', 'popup' );
		$header         = astra_get_option( 'header-desktop-items', array() );
		$header_mobile  = astra_get_option( 'header-mobile-items', array() );
		$footer         = astra_get_option( 'footer-desktop-items', array() );
		$social_options = array();

		foreach ( $zones as $locations ) {

			// Header - Desktop Scanning for replacement text.
			if ( ! empty( $header[ $locations ] ) ) {
				foreach ( $header[ $locations ] as $location ) {

					if ( empty( $location ) ) {
						continue;
					}

					foreach ( $location as $loc ) {
						if ( false !== strpos( $loc, 'social-icons' ) ) {
							$social_options[] = 'header-' . $loc;
						}
					}
				}
			}

			// Header - Mobile Scanning for replacement text.
			if ( ! empty( $header_mobile[ $locations ] ) ) {
				foreach ( $header_mobile[ $locations ] as $location ) {

					if ( empty( $location ) ) {
						continue;
					}

					foreach ( $location as $loc ) {
						if ( false !== strpos( $loc, 'social-icons' ) ) {
							$social_options[] = 'header-' . $loc;
						}
					}
				}
			}

			// Footer Scanning for replacement text.
			if ( ! empty( $footer[ $locations ] ) ) {
				foreach ( $footer[ $locations ] as $location ) {

					if ( empty( $location ) ) {
						continue;
					}

					foreach ( $location as $loc ) {
						if ( false !== strpos( $loc, 'social-icons' ) ) {
							$social_options[] = 'footer-' . $loc;
						}
					}
				}
			}
		}

		return $social_options;
	}

	/**
	 * Updating the header and footer background image.
	 *
	 * @since 4.1.0
	 * @param array<string,array<string,string>> $obj Reference of Block array.
	 * @return array<string,array<string,int|string>> $obj Updated Block array.
	 */
	public function get_updated_astra_option( $obj ) {
		$image_id = ( isset( $obj['desktop']['background-media'] ) ) ? $obj['desktop']['background-media'] : 0;
		if ( 0 === $image_id ) {
			return $obj;
		}
		$image = $this->get_image( self::$image_index );

		if ( empty( $image ) || ! is_array( $image ) ) {
			return $obj;
		}

		$image = ST_Importer_Helper::download_image( $image );

		if ( is_wp_error( $image ) ) {
			return $obj;
		}

		$attachment = wp_prepare_attachment_for_js( absint( $image ) );

		$obj['desktop']['background-image'] = $attachment['url'] ?? '';
		$obj['desktop']['background-media'] = $attachment['id'] ?? 0;

		$this->increment_image_index();

		return $obj;
	}

	/**
	 * Replace the content with AI generated data in all Pages.
	 *
	 * @since 4.1.0
	 * @return array<string, mixed>
	 */
	public function replace_in_pages() {

		$pages = $this->get_pages();

		if ( empty( $pages ) ) {
			return array(
				'success' => false,
				'msg'     => __( 'Pages are empty', 'astra-sites' ),
			);
		}

		$required_plugins = astra_get_site_data( 'required-plugins' );
		$plugins_slug     = array_column( $required_plugins, 'slug' );

		$plugin_instance = in_array( 'elementor', $plugins_slug, true ) ? ST_Replace_Elementor_Images::get_instance() : ST_Replace_Blocks_Images::get_instance();

		foreach ( $pages as $key => $post ) {

			if ( ! is_object( $post ) ) {
				continue;
			}

			// Replaced content.
			// @phpstan-ignore-next-line.
			$new_content = $plugin_instance->parse_replace_images( $post );

			// Update content.
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $new_content ?? '',
				)
			);

		}

		return array(
			'success' => true,
			'msg'     => __( 'Pages are replaced', 'astra-sites' ),
		);
	}


	/**
	 * Get pages.
	 *
	 * @return array<int|\WP_Post> Array for pages.
	 * @param string $type Post type.
	 * @since 4.1.0
	 */
	public static function get_pages( $type = 'page' ) {

		$posts               = get_option( 'astra_sites_ai_imports', array() );
		$post_ids_to_include = ! empty( $posts[ $type ] ) ? $posts[ $type ] : array();

		$query_args = array(
			'post_type'           => array( $type ),
			// Query performance optimization.
			'fields'              => array( 'ids', 'post_content', 'post_title' ),
			'posts_per_page'      => '10',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'post__in'            => $post_ids_to_include,
		);

		$query = new \WP_Query( $query_args );

		$desired_first_page_id = intval( get_option( 'page_on_front', 0 ) );
		$pages                 = $query->posts ? $query->posts : [];

		$desired_page_index = false;

		if ( is_array( $pages ) && ! empty( $pages ) && ! empty( $desired_first_page_id ) ) {
			foreach ( $pages as $key => $page ) {

				if ( isset( $page->ID ) && $page->ID === $desired_first_page_id ) {
					$desired_page_index = $key;
					break;
				}
			}

			if ( false !== $desired_page_index ) {
				$desired_page = $pages[ $desired_page_index ];
				unset( $pages[ $desired_page_index ] );
				array_unshift( $pages, $desired_page );
			}
		}

		return $pages;
	}



	/**
	 * Check if we need to skip the URL.
	 *
	 * @param string $url URL to check.
	 * @return boolean
	 * @since 4.1.0
	 */
	public static function is_skipable( $url ) {
		if ( strpos( $url, 'skip' ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * Get Image for the specified index
	 *
	 * @param int $index Index of the image.
	 * @return array<string, string>|boolean Array of images or false.
	 * @since 4.1.0
	 */
	public function get_image( $index = 0 ) {

		$this->set_images();
		return ( isset( self::$filtered_images[ $index ] ) ) ? self::$filtered_images[ $index ] : false;
	}

	/**
	 * Set Image as per oriantation
	 *
	 * @return void
	 */
	public function set_images() {
		if ( empty( self::$filtered_images ) ) {
			$images = ST_Importer_Helper::get_business_details( 'images' );
			if ( is_array( $images ) ) {
				foreach ( $images as $image ) {
					self::$filtered_images[] = $image;
				}
			} else {
				if ( class_exists( 'AiBuilder\Inc\Traits\Helper' ) ) {
					$placeholder_images      = Helper::get_image_placeholders();
					self::$filtered_images[] = $placeholder_images[0];
					self::$filtered_images[] = $placeholder_images[1];
				}
			}
		}
	}

	/**
	 * Increment Image index
	 *
	 * @return void
	 */
	public function increment_image_index() {

		$this->set_images();

		$new_index = (int) self::$image_index + 1;

		if ( ! isset( self::$filtered_images[ $new_index ] ) ) {
			$new_index = 0;
		}

		self::$image_index = $new_index;
	}
}

ST_Replace_Images::get_instance();
