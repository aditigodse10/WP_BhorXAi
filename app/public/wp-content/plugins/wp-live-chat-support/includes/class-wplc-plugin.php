<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.3cx.com
 * @since      10.0.0
 *
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      10.0.0
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/includes
 * @author     3CX <wordpress@3cx.com>
 */
class wplc_Plugin
{

  private $plugin_settings;

  /**
   * The loader that's responsible for maintaining and registering all hooks that power
   * the plugin.
   *
   * @since    10.0.0
   * @access   protected
   * @var      wplc_Plugin_Loader    $loader    Maintains and registers all hooks for the plugin.
   */
  protected $loader;

  /**
   * The unique identifier of this plugin.
   *
   * @since    10.0.0
   * @access   protected
   * @var      string    $plugin_name    The string used to uniquely identify this plugin.
   */
  protected $plugin_name;

  /**
   * The current version of the plugin.
   *
   * @since    10.0.0
   * @access   protected
   * @var      string    $version    The current version of the plugin.
   */
  protected $version;

  /**
   * Define the core functionality of the plugin.
   *
   * Set the plugin name and the plugin version that can be used throughout the plugin.
   * Load the dependencies, define the locale, and set the hooks for the admin area and
   * the public-facing side of the site.
   *
   * @since    10.0.0
   */
  public function __construct()
  {
    global $WPLC_PLUGIN_VERSION;

    $this->plugin_name = 'wp-live-chat-support';
    $this->version = $WPLC_PLUGIN_VERSION;

    $this->load_dependencies();
    $this->set_locale();
    $this->define_admin_hooks();
    $this->define_public_hooks();
  }

  /**
   * Load the required dependencies for this plugin.
   *
   * Include the following files that make up the plugin:
   *
   * - wplc_Plugin_Loader. Orchestrates the hooks of the plugin.
   * - wplc_Plugin_i18n. Defines internationalization functionality.
   * - wplc_Plugin_Admin. Defines all hooks for the admin area.
   * - wplc_Plugin_Public. Defines all hooks for the public side of the site.
   *
   * Create an instance of the loader which will be used to register the hooks
   * with WordPress.
   *
   * @since    10.0.0
   * @access   private
   */
  private function load_dependencies()
  {

    /**
     * The class responsible for orchestrating the actions and filters of the
     * core plugin.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wplc-plugin-loader.php';

    /**
     * The class responsible for defining internationalization functionality
     * of the plugin.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wplc-plugin-i18n.php';

    /**
     * The class responsible for defining all actions that occur in the admin area.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wplc-plugin-admin.php';
    //require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wplc-plugin-admin-settings.php';


    /**
     * The class responsible for defining all actions that occur in the public-facing
     * side of the site.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wplc-plugin-public.php';

    $this->loader = new wplc_Plugin_Loader();
  }

  /**
   * Define the locale for this plugin for internationalization.
   *
   * Uses the wplc_Plugin_i18n class in order to set the domain and to register the hook
   * with WordPress.
   *
   * @since    10.0.0
   * @access   private
   */
  private function set_locale()
  {
    $plugin_i18n = new wplc_Plugin_i18n();
    $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
  }

  /**
   * Register all of the hooks related to the admin area functionality
   * of the plugin.
   *
   * @since    10.0.0
   * @access   private
   */
  private function define_admin_hooks()
  {

    $plugin_admin = new wplc_Plugin_Admin($this->get_plugin_name(), $this->get_version());
    $this->plugin_settings = new wplc_Admin_Settings($this->get_plugin_name(), $this->get_version());

    $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
    $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    $this->loader->add_action('plugin_row_meta', $plugin_admin, 'add_custom_links', 10, 2 );
    $this->loader->add_action('wp_ajax_check_update', $plugin_admin, 'check_update');

    $this->loader->add_action('admin_menu', $this->plugin_settings, 'setup_plugin_options_menu');
    $this->loader->add_action('admin_init', $this->plugin_settings, 'initialize_display_options');
  }

  /**
   * Register all of the hooks related to the public-facing functionality
   * of the plugin.
   *
   * @since    10.0.0
   * @access   private
   */
  private function define_public_hooks()
  {
    $plugin_public = new wplc_Plugin_Public($this->get_plugin_name(), $this->get_version());
    $this->loader->add_action('rest_api_init', $plugin_public, 'register_autoconfig_request');
    $this->loader->add_action('wp_footer', $plugin_public, 'add_chat_element');
    $this->loader->add_filter('script_loader_tag', $plugin_public, 'defer_callus_js', 10);
    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
  }

  /**
   * Run the loader to execute all of the hooks with WordPress.
   *
   * @since    10.0.0
   */
  public function run()
  {
    $this->loader->run();
  }

  /**
   * The name of the plugin used to uniquely identify it within the context of
   * WordPress and to define internationalization functionality.
   *
   * @since     10.0.0
   * @return    string    The name of the plugin.
   */
  public function get_plugin_name()
  {
    return $this->plugin_name;
  }

  /**
   * The reference to the class that orchestrates the hooks with the plugin.
   *
   * @since     10.0.0
   * @return    wplc_Plugin_Loader    Orchestrates the hooks of the plugin.
   */
  public function get_loader()
  {
    return $this->loader;
  }

  /**
   * Retrieve the version number of the plugin.
   *
   * @since     10.0.0
   * @return    string    The version number of the plugin.
   */
  public function get_version()
  {
    return $this->version;
  }
}
