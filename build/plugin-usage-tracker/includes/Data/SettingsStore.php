<?php
/**
 * Settings store.
 *
 * @package PluginUsageTracker\Data
 */

namespace PluginUsageTracker\Data;

/**
 * Persistent plugin settings.
 */
final class SettingsStore {

	/**
	 * Option key.
	 */
	public const OPTION_KEY = 'put_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'exclude_plugins'     => array(),
			'retain_results_days' => 30,
			'show_likely_used'    => true,
			'enable_cli'          => false,
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['exclude_plugins'] = isset( $settings['exclude_plugins'] ) && is_array( $settings['exclude_plugins'] )
			? array_values( array_filter( $settings['exclude_plugins'] ) )
			: array();

		$settings['retain_results_days'] = isset( $settings['retain_results_days'] ) ? (int) $settings['retain_results_days'] : 30;
		$settings['show_likely_used']    = ! empty( $settings['show_likely_used'] );
		$settings['enable_cli']          = ! empty( $settings['enable_cli'] );

		return wp_parse_args( $settings, self::defaults() );
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $settings Settings to save.
	 * @return void
	 */
	public function update( array $settings ): void {
		$normalized = $this->sanitize( $settings );

		update_option( self::OPTION_KEY, $normalized );
	}

	/**
	 * Normalize raw settings input.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $settings ): array {
		$normalized                        = wp_parse_args( $settings, self::defaults() );
		$normalized['exclude_plugins']     = array_values(
			array_filter(
				array_map( 'strval', (array) $normalized['exclude_plugins'] )
			)
		);
		$normalized['retain_results_days'] = max( 1, absint( $normalized['retain_results_days'] ) );
		$normalized['show_likely_used']    = ! empty( $normalized['show_likely_used'] );
		$normalized['enable_cli']          = ! empty( $normalized['enable_cli'] );

		return $normalized;
	}

	/**
	 * Check if a plugin is excluded.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	public function is_excluded( string $plugin_file ): bool {
		$settings = $this->all();

		return in_array( $plugin_file, $settings['exclude_plugins'], true );
	}

	/**
	 * Retention window in days.
	 *
	 * @return int
	 */
	public function retention_days(): int {
		$settings = $this->all();

		return (int) $settings['retain_results_days'];
	}
}
