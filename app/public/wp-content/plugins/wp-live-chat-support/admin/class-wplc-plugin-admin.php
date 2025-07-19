<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.3cx.com
 * @since      10.0.0
 *
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 *
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/admin
 * @author     3CX <wordpress@3cx.com>
 */
class wplc_Plugin_Admin
{

  /**
   * The ID of this plugin.
   *
   * @since    10.0.0
   * @access   private
   * @var      string    $plugin_name    The ID of this plugin.
   */
  private $plugin_name;

  /**
   * The version of this plugin.
   *
   * @since    10.0.0
   * @access   private
   * @var      string    $version    The current version of this plugin.
   */
  private $version;

  /**
   * Initialize the class and set its properties.
   *
   * @since    10.0.0
   * @param      string    $plugin_name       The name of this plugin.
   * @param      string    $version    The version of this plugin.
   */
  public function __construct($plugin_name, $version)
  {

    $this->plugin_name = $plugin_name;
    $this->version = $version;

    $this->load_dependencies();
  }

  /**
   * Load the required dependencies for the Admin facing functionality.
   *
   * Include the following files that make up the plugin:
   *
   * - wplc_Plugin_Admin_Settings. Registers the admin settings and page.
   *
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
    require_once plugin_dir_path(dirname(__FILE__)) .  'admin/class-wplc-plugin-settings.php';
  }
  /**
   * Register the stylesheets for the admin area.
   *
   * @since    10.0.0
   */
  public function enqueue_styles()
  {
    wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wplc-plugin-admin.css', array(), $this->version, 'all');
  }

  /**
   * Register the JavaScript for the admin area.
   *
   * @since    10.0.0
   */
  public function enqueue_scripts()
  {
    wp_enqueue_script($this->plugin_name . '-admin-main', plugin_dir_url(__FILE__) . 'js/wplc-plugin-admin.js', array('jquery'), $this->version, false);
    wp_localize_script($this->plugin_name . '-admin-main', 'ajax_object', array( 
      'ajax_url' => admin_url('admin-ajax.php'),
      'msg_reset_config' => htmlentities(__('Do you really want to reset 3CX Live Chat Configuration?','wp-live-chat-support')),
      'activated' => intval(get_option('wplc_activated'))
    ));
  }

  /**
   * Add links to plugins dashboard
   *
   * @since    10.0.2
   */
  public function add_custom_links($links, $file)
  {
    $plugin = explode('/' , plugin_basename( __FILE__ ))[0];
    $file = explode('/' ,$file )[0];

		if ( $file == $plugin ) {
			return array_merge(
				$links,
				array( '<a target="_blank" href="'.wplc_generate_startup_url(false).'">' . __( 'Setup your free 3CX account', 'wp-live-chat-support' ) . '</a>' )
			);
		}
		return $links;
  }  

  public function check_update() {
    $activated=get_option('wplc_activated');
    if (isset($_REQUEST['value']) && intval($_REQUEST['value'])==1) {
      if ($activated==0) {
        update_option('wplc_activated',1);
      }
    }
    echo ($activated==2) ? '1' : '0';
    wp_die();
  }
  

}
