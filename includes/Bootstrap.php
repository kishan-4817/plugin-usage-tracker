<?php
/**
 * Bootstrap class.
 *
 * @package PluginUsageTracker
 */

namespace PluginUsageTracker;

use PluginUsageTracker\Admin\AdminPage;
use PluginUsageTracker\Admin\SettingsPage;
use PluginUsageTracker\CLI\Command as CliCommand;
use PluginUsageTracker\Data\SettingsStore;
use PluginUsageTracker\Scanner\RuntimeObserver;

/**
 * Bootstrap
 *
 * Registers hooks and bootstraps all plugin components.
 * No heavy lifting here — just wiring.
 */
final class Bootstrap {

	/**
	 * Plugin instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — use Bootstrap::init().
	 */
	private function __construct() {}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self();
		self::$instance->register_hooks();
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$settings_store = new SettingsStore();

		// Set default options on first activation.
		if ( false === get_option( 'put_settings' ) ) {
			$settings_store->update( SettingsStore::defaults() );
		}

		// Store activation timestamp.
		update_option( 'put_activated_at', time() );
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clean up transients only — keep scan results and settings.
		delete_transient( 'put_runtime_hooks' );
	}

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Load plugin text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin-only hooks.
		if ( is_admin() ) {
			$admin = new AdminPage();
			$admin->register();

			$settings_page = new SettingsPage();
			$settings_page->register();
		}

		$runtime_observer = new RuntimeObserver();
		$runtime_observer->register();

		$settings = ( new SettingsStore() )->all();

		if ( defined( 'WP_CLI' ) && WP_CLI && ! empty( $settings['enable_cli'] ) ) {
			$this->register_cli();
		}
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	private function register_cli(): void {
		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'unused-plugins', new CliCommand() );
		}
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'plugin-usage-tracker',
			false,
			dirname( PUT_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
