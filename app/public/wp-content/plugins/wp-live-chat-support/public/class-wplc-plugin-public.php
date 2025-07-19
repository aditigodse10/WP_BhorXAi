<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.3cx.com
 * @since      10.0.0
 *
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * @package    wplc_Plugin
 * @subpackage wplc_Plugin/public
 * @author     3CX <wordpress@3cx.com>
 */
class wplc_Plugin_Public
{

  private $plugin_settings;
  public $options;

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
   * @param      string    $plugin_name       The name of the plugin.
   * @param      string    $version    The version of this plugin.
   */
  public function __construct($plugin_name, $version)
  {

    $this->plugin_name = $plugin_name;
    $this->version = $version;
    $this->plugin_settings = new wplc_Admin_Settings($this->plugin_name, $this->version);
    $this->options = $this->plugin_settings->read_config();
  }

  /**
   * Register the stylesheets for the public-facing side of the site.
   *
   * @since    10.0.0
   */
  public function enqueue_styles()
  {
    wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wplc-plugin-public.css', array(), $this->version, 'all');
  }

  /**
   * Check if chat has to be displayed in current page
   *
   * @since    10.0.0
   */
  private function can_display_chat()
  {
    $postId = intval(get_the_ID());
    return $this->options['show_all_pages'] || isset($this->options['include_pages'][$postId]);
  }

  /**
   * Register the scripts for the public-facing side of the site
   *
   * @since    10.0.0
   */
  public function enqueue_scripts()
  {
    if ($this->can_display_chat()) {
      wp_enqueue_script($this->plugin_name . '-callus', plugin_dir_url(__FILE__) . 'js/callus.js', '', $this->version, true);
    }
  }

  /**
   * Display chat element in wp_footer hook
   *
   * @since    10.0.0
   */
  public function add_chat_element()
  {
    if ($this->can_display_chat()) {
      $party = substr($this->options['callus_url'], strrpos($this->options['callus_url'], '/') + 1);
      $url = substr($this->options['callus_url'], 0, strrpos($this->options['callus_url'], '/'));
      $extra_params=' ';
      $extra_params.= 'enable-poweredby="'.(($this->options['powered_by']) ? 'true' : 'false').'"';
      echo '<call-us-selector phonesystem-url="' . $url . '" party="' . $party . '"'.$extra_params.'></call-us-selector>';
    }
  }

  /**
   * Add defer to callus.js script tag
   *
   * @since    10.0.0
   */
  public function defer_callus_js($url)
  {
    if (FALSE === strpos($url, 'callus.js')) return $url;
    return str_replace(' src', ' defer src', $url);
  }

  /**
   * register REST endpoint for auto configuration
   *
   * @since    10.0.7
   */
  public function register_autoconfig_request()
  {
    register_rest_route('wp-live-chat-support/v1', '/autoconfigure', array(
      'methods' => 'POST',
      'callback' => [ $this, 'autoconfigure' ],
      'show_in_index' => false,
      'permission_callback' => '__return_true'
    ));
  }  

  /**
   * handle REST request auto configuration
   *
   * @since    10.0.7
   */
  public function autoconfigure($data) {
    $results='';
    $nonce=$data->get_param('nonce');
    $chaturl=$data->get_param('chaturl');
    
    $stored_nonce=get_option('wplc_callback_nonce');
    if (empty($stored_nonce)) {
      return new WP_Error( 'already_activated', 'This WordPress instance has already an active 3CX instance', array( 'status' => 400 ) );
    }    
    if (empty($nonce)) {
      return new WP_Error( 'invalid_nonce', 'Empty nonce', array( 'status' => 400 ) );
    }    
    if ($nonce!=$stored_nonce) {
      return new WP_Error( 'invalid_nonce', 'Nonce does not match', array( 'status' => 400 ) );
    }    
    if (empty($chaturl) || !wplc_Admin_Settings::sanitize_callus_url($chaturl)){
      return new WP_Error( 'invalid_chaturl', 'Chaturl is invalid', array( 'status' => 400 ) );
    }    
    $options=$this->plugin_settings->read_config();
    $options['callus_url']=$chaturl;
    update_option('wplc_display_options', $options);
    update_option('wplc_activated', 2);
    update_option('wplc_callback_nonce', '');
    return new WP_REST_Response();
  }
}
