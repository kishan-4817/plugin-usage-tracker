<?php
/**
 * Scan results store.
 *
 * @package PluginUsageTracker\Data
 */

namespace PluginUsageTracker\Data;

/**
 * Store and retrieve scan results.
 */
final class ResultsStore {

	/**
	 * Latest results option key.
	 */
	public const OPTION_KEY = 'put_scan_results';

	/**
	 * History option key.
	 */
	public const HISTORY_KEY = 'put_scan_history';

	/**
	 * Override option key.
	 */
	public const OVERRIDE_KEY = 'put_plugin_overrides';

	/**
	 * Save a scan payload.
	 *
	 * @param array<string, mixed> $payload Scan payload.
	 * @param int                  $retention_days Retention days.
	 */
	public function save_scan( array $payload, int $retention_days = 30 ): void {
		update_option( self::OPTION_KEY, $payload, false );

		$history = $this->get_history();
		array_unshift( $history, $payload );
		$history = $this->prune_history( $history, $retention_days );

		update_option( self::HISTORY_KEY, $history, false );
	}

	/**
	 * Get latest scan.
	 *
	 * @return array<string, mixed>
	 */
	public function get_latest_scan(): array {
		$scan = get_option( self::OPTION_KEY, array() );

		return is_array( $scan ) ? $scan : array();
	}

	/**
	 * Get history entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_history(): array {
		$history = get_option( self::HISTORY_KEY, array() );

		return is_array( $history ) ? array_values( $history ) : array();
	}

	/**
	 * Check whether a plugin is manually marked needed.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return bool
	 */
	public function is_needed( string $plugin_file ): bool {
		$overrides = $this->get_overrides();

		return ! empty( $overrides[ $plugin_file ] );
	}

	/**
	 * Set manual needed override.
	 *
	 * @param string $plugin_file Plugin file.
	 * @param bool   $needed Whether the plugin is needed.
	 * @return void
	 */
	public function set_needed( string $plugin_file, bool $needed ): void {
		$overrides = $this->get_overrides();

		if ( $needed ) {
			$overrides[ $plugin_file ] = true;
		} else {
			unset( $overrides[ $plugin_file ] );
		}

		update_option( self::OVERRIDE_KEY, $overrides, false );
	}

	/**
	 * Get all overrides.
	 *
	 * @return array<string, bool>
	 */
	public function get_overrides(): array {
		$overrides = get_option( self::OVERRIDE_KEY, array() );

		if ( ! is_array( $overrides ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $overrides as $plugin_file => $needed ) {
			if ( ! is_string( $plugin_file ) ) {
				continue;
			}

			$normalized[ $plugin_file ] = (bool) $needed;
		}

		return $normalized;
	}

	/**
	 * Remove expired history entries.
	 *
	 * @param array<int, array<string, mixed>> $history History payloads.
	 * @param int                              $retention_days Retention days.
	 * @return array<int, array<string, mixed>>
	 */
	private function prune_history( array $history, int $retention_days ): array {
		$cutoff = time() - ( DAY_IN_SECONDS * max( 1, $retention_days ) );

		return array_values(
			array_filter(
				$history,
				static function ( array $entry ) use ( $cutoff ): bool {
					$generated_at = isset( $entry['generated_at_gmt'] ) ? strtotime( (string) $entry['generated_at_gmt'] ) : 0;

					return $generated_at >= $cutoff;
				}
			)
		);
	}
}
