<?php
/**
 * Uninstall Plugin Usage Tracker.
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all options and transients created by the plugin.
 *
 * @package PluginUsageTracker
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options.
delete_option( 'put_settings' );
delete_option( 'put_scan_results' );
delete_option( 'put_scan_history' );
delete_option( 'put_plugin_overrides' );
delete_option( 'put_activated_at' );

// Remove transients.
delete_transient( 'put_runtime_hooks' );
