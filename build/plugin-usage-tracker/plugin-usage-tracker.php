<?php
/**
 * Plugin Name:       Plugin Usage Tracker
 * Plugin URI:        https://github.com/YOUR_USERNAME/plugin-usage-tracker
 * Description:       Detects and reports unused or low-activity plugins installed on your WordPress site.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://yoursite.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plugin-usage-tracker
 * Domain Path:       /languages
 *
 * @package PluginUsageTracker
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'PUT_VERSION', '0.1.0' );
define( 'PUT_PLUGIN_FILE', __FILE__ );
define( 'PUT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PUT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PUT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader via Composer.
 */
if ( file_exists( PUT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once PUT_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, array( 'PluginUsageTracker\\Bootstrap', 'activate' ) );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, array( 'PluginUsageTracker\\Bootstrap', 'deactivate' ) );

/**
 * Bootstrap the plugin on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		\PluginUsageTracker\Bootstrap::init();
	}
);
