<?php
/**
 * WP-CLI integration.
 *
 * @package PluginUsageTracker\CLI
 */

namespace PluginUsageTracker\CLI;

use PluginUsageTracker\Data\ResultsStore;
use PluginUsageTracker\Scanner\PluginScanner;

/**
 * WP-CLI command handlers.
 */
final class Command {

	/**
	 * Scan installed plugins.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv).
	 *
	 * @param array<int, string>   $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function scan( array $args, array $assoc_args ): void {
		$scanner = new PluginScanner();
		$payload = $scanner->scan();
		$format  = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		$this->render_results( $payload, $format );
	}

	/**
	 * Report the latest scan.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv).
	 *
	 * @param array<int, string>   $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function report( array $args, array $assoc_args ): void {
		$results_store = new ResultsStore();
		$payload       = $results_store->get_latest_scan();
		$format        = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		if ( empty( $payload ) ) {
			\WP_CLI::warning( 'No scan results available yet.' );
			return;
		}

		$this->render_results( $payload, $format );
	}

	/**
	 * Render payload in the requested format.
	 *
	 * @param array<string, mixed> $payload Results payload.
	 * @param string               $format Output format.
	 * @return void
	 */
	private function render_results( array $payload, string $format ): void {
		$rows  = array();
		$items = isset( $payload['plugins'] ) && is_array( $payload['plugins'] ) ? $payload['plugins'] : array();

		foreach ( $items as $item ) {
			$rows[] = array(
				'plugin'      => isset( $item['name'] ) ? (string) $item['name'] : '',
				'status'      => ! empty( $item['is_active'] ) ? 'active' : 'inactive',
				'confidence'  => isset( $item['confidence_label'] ) ? (string) $item['confidence_label'] : '',
				'score'       => isset( $item['confidence_score'] ) ? (int) $item['confidence_score'] : 0,
				'override'    => ! empty( $item['override_needed'] ) ? 'yes' : 'no',
				'plugin_file' => isset( $item['plugin_file'] ) ? (string) $item['plugin_file'] : '',
			);
		}

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		if ( 'csv' === $format ) {
			\WP_CLI\Utils\format_items( 'csv', $rows, array_keys( $rows[0] ?? array( 'plugin' => '' ) ) );
			return;
		}

		\WP_CLI::line(
			sprintf(
				'Scan generated at %s with %d plugin records.',
				isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '',
				count( $rows )
			)
		);

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			array( 'plugin', 'status', 'confidence', 'score', 'override', 'plugin_file' )
		);
	}
}
