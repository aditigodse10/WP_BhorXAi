<?php
/**
 * Plugin Name: Getting Started
 * Description: It is a library which helps you to complete the website setup.
 * Author: Brainstorm Force
 * Version: 1.0.0
 * License: GPL v2
 * Text Domain: getting-started
 *
 * @package getting-started
 */

if ( defined( 'GS_FILE' ) ) {
	return;
}
/**
 * Set constants
 */
define( 'GS_FILE', __FILE__ );
define( 'GS_BASE', plugin_basename( GS_FILE ) );
define( 'GS_DIR', plugin_dir_path( GS_FILE ) );
define( 'GS_URL', plugins_url( '/', GS_FILE ) );
define( 'GS_VER', '1.0.0' );

require_once 'getting-started-plugin-loader.php';
