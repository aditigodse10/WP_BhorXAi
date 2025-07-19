<?php
/**
 * Plugin Loader.
 *
 * @package getting-started
 * @since 1.0.0
 */

namespace GS;

/**
 * Getting_Started_Plugin_Loader
 *
 * @since 1.0.0
 */
class Getting_Started_Plugin_Loader {

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class class name.
	 *
	 * @return void
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		$class_to_load = $class;

		$filename = strtolower(
			(string) preg_replace(
				[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
				[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
				$class_to_load
			)
		);

		$file = GS_DIR . $filename . '.php';

		// if the file redable, include it.
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		spl_autoload_register( [ $this, 'autoload' ] );

		add_action( 'wp_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'wp_loaded', [ $this, 'load_files' ] );
	}

	/**
	 * Load Plugin Text Domain.
	 * This will load the translation textdomain depending on the file priorities.
	 *      1. Global Languages /wp-content/languages/getting-started/ folder
	 *      2. Local dorectory /wp-content/plugins/getting-started/languages/ folder
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		// Default languages directory.
		$lang_dir = GS_DIR . 'languages/';

		/**
		 * Filters the languages directory path to use for plugin.
		 *
		 * @param string $lang_dir The languages directory path.
		 */
		$lang_dir = apply_filters( 'gs_languages_directory', $lang_dir );

		// Traditional WordPress plugin locale filter.
		global $wp_version;

		$get_locale = get_locale();

		if ( $wp_version >= 4.7 ) {
			$get_locale = get_user_locale();
		}

		$locale = apply_filters( 'plugin_locale', $get_locale, 'getting-started' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'getting-started', $locale );

		// Setup paths to current locale file.
		$mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;
		$mofile_local  = $lang_dir . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/getting-started/ folder.
			load_textdomain( 'getting-started', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/getting-started/languages/ folder.
			load_textdomain( 'getting-started', $mofile_local );
		} else {
			// Load the default language files.
			load_plugin_textdomain( 'getting-started', false, $lang_dir );
		}
	}

	/**
	 * Load the main plugin files.
	 *
	 * @return void
	 */
	public function load_files() {
		require_once GS_DIR . 'classes/class-gs-admin.php';
		require_once GS_DIR . 'classes/class-gs-helper.php';
		require_once GS_DIR . 'classes/class-gs-api.php';
	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
Getting_Started_Plugin_Loader::get_instance();
