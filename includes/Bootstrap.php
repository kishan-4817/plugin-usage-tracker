<?php
/**
 * Bootstrap class.
 *
 * @package PluginUsageTracker
 */

namespace PluginUsageTracker;

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
		// Set default options on first activation.
		if ( false === get_option( 'put_settings' ) ) {
			update_option(
				'put_settings',
				array(
					'exclude_plugins'     => array(),
					'retain_results_days' => 30,
					'show_likely_used'    => false,
				)
			);
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
			$admin = new Admin\AdminPage();
			$admin->register();
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
