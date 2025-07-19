<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://www.3cx.com
 * @since      10.0.0
 *
 * @package    wplc_Plugin
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}
if (!current_user_can('activate_plugins')) {
  return;
}
delete_option('wplc_display_options');
delete_option('wplc_callback_nonce');
delete_option('wplc_activated');
