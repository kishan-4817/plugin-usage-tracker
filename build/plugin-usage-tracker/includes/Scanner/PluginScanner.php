<?php
/**
 * Plugin scanner orchestrator.
 *
 * @package PluginUsageTracker\Scanner
 */

namespace PluginUsageTracker\Scanner;

use PluginUsageTracker\Data\ResultsStore;
use PluginUsageTracker\Data\SettingsStore;
use PluginUsageTracker\Scanner\RuntimeObserver;

/**
 * Scan installed plugins and score usage.
 */
final class PluginScanner {

	/**
	 * Static analyzer.
	 *
	 * @var StaticAnalyzer
	 */
	private StaticAnalyzer $static_analyzer;

	/**
	 * Content analyzer.
	 *
	 * @var ContentAnalyzer
	 */
	private ContentAnalyzer $content_analyzer;

	/**
	 * Results store.
	 *
	 * @var ResultsStore
	 */
	private ResultsStore $results_store;

	/**
	 * Settings store.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $settings_store;

	/**
	 * Runtime observer.
	 *
	 * @var RuntimeObserver
	 */
	private RuntimeObserver $runtime_observer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->static_analyzer  = new StaticAnalyzer();
		$this->content_analyzer = new ContentAnalyzer();
		$this->results_store    = new ResultsStore();
		$this->settings_store   = new SettingsStore();
		$this->runtime_observer = new RuntimeObserver();
	}

	/**
	 * Run a scan across installed plugins.
	 *
	 * @return array<string, mixed>
	 */
	public function scan(): array {
		$this->load_plugin_api();

		$installed_plugins = get_plugins();
		$runtime_capture   = $this->runtime_observer->get_capture();
		$results           = array();

		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			if ( $this->settings_store->is_excluded( $plugin_file ) ) {
				continue;
			}

			$static_analysis  = $this->static_analyzer->analyze( $plugin_file, $plugin_data );
			$content_analysis = $this->content_analyzer->analyze( $static_analysis );
			$is_active        = $this->is_active( $plugin_file );
			$override_needed  = $this->results_store->is_needed( $plugin_file );

			$results[ $plugin_file ] = $this->build_result(
				$plugin_file,
				$plugin_data,
				$static_analysis,
				$content_analysis,
				$runtime_capture,
				$is_active,
				$override_needed
			);
		}

		$payload = array(
			'generated_at_gmt' => gmdate( 'c' ),
			'generated_at'     => current_time( 'mysql' ),
			'total'            => count( $results ),
			'summary'          => $this->build_summary( $results ),
			'plugins'          => $results,
		);

		$this->results_store->save_scan( $payload, $this->settings_store->retention_days() );

		return $payload;
	}

	/**
	 * Load plugin helper functions.
	 *
	 * @return void
	 */
	private function load_plugin_api(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Check whether a plugin is active.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return bool
	 */
	private function is_active( string $plugin_file ): bool {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( in_array( $plugin_file, $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

			return is_array( $network_plugins ) && array_key_exists( $plugin_file, $network_plugins );
		}

		return false;
	}

	/**
	 * Build a single plugin result.
	 *
	 * @param string               $plugin_file Plugin file.
	 * @param array<string, mixed> $plugin_data Plugin header data.
	 * @param array<string, mixed> $static_analysis Static analysis payload.
	 * @param array<string, mixed> $content_analysis Content analysis payload.
	 * @param array<string, mixed> $runtime_capture Runtime capture payload.
	 * @param bool                 $is_active Whether the plugin is active.
	 * @param bool                 $override_needed Whether the plugin is manually marked needed.
	 * @return array<string, mixed>
	 */
	private function build_result(
		string $plugin_file,
		array $plugin_data,
		array $static_analysis,
		array $content_analysis,
		array $runtime_capture,
		bool $is_active,
		bool $override_needed
	): array {
		$signals       = isset( $static_analysis['signals'] ) && is_array( $static_analysis['signals'] ) ? $static_analysis['signals'] : array();
		$usage         = isset( $content_analysis['usage'] ) && is_array( $content_analysis['usage'] ) ? $content_analysis['usage'] : array();
		$runtime_hooks = isset( $runtime_capture['hooks'] ) && is_array( $runtime_capture['hooks'] ) ? $runtime_capture['hooks'] : array();
		$runtime_hits  = $this->intersect_signals( isset( $signals['hooks'] ) && is_array( $signals['hooks'] ) ? $signals['hooks'] : array(), $runtime_hooks );

		$score = $is_active ? 45 : 20;
		$notes = array();

		if ( $override_needed ) {
			$score   = 100;
			$notes[] = __( 'Manually marked as needed.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $signals['hooks'] ) ) {
			$score  += 10;
			$notes[] = __( 'Registers hooks or filters.', 'plugin-usage-tracker' );
		} else {
			$score  -= 10;
			$notes[] = __( 'No obvious hooks found in the source scan.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $runtime_hits ) ) {
			$score  += 20;
			$notes[] = __( 'Runtime test observed plugin-related hooks.', 'plugin-usage-tracker' );
		} elseif ( ! empty( $runtime_hooks ) ) {
			$score  -= 5;
			$notes[] = __( 'Runtime test ran but no matching hooks were observed.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $signals['shortcodes'] ) ) {
			$score  += 10;
			$notes[] = sprintf(
				/* translators: %d = number of shortcodes. */
				_n( 'Registers %d shortcode.', 'Registers %d shortcodes.', count( $signals['shortcodes'] ), 'plugin-usage-tracker' ),
				count( $signals['shortcodes'] )
			);
		}

		if ( ! empty( $usage['shortcodes'] ) ) {
			$score  += 15;
			$notes[] = __( 'Shortcodes are referenced in content.', 'plugin-usage-tracker' );
		} elseif ( ! empty( $signals['shortcodes'] ) ) {
			$score  -= 10;
			$notes[] = __( 'Shortcodes are registered but not found in content.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $signals['cpts'] ) ) {
			$score  += 5;
			$notes[] = __( 'Registers custom post types.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $usage['cpts'] ) ) {
			$score  += 20;
			$notes[] = __( 'Custom post types contain content.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $signals['rest_routes'] ) ) {
			$score  += 10;
			$notes[] = __( 'Exposes REST API routes.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $signals['blocks'] ) ) {
			$score  += 10;
			$notes[] = __( 'Registers blocks.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $usage['blocks'] ) ) {
			$score  += 15;
			$notes[] = __( 'Blocks are referenced in content.', 'plugin-usage-tracker' );
		} elseif ( ! empty( $signals['blocks'] ) ) {
			$score  -= 5;
			$notes[] = __( 'Blocks are registered but not found in content.', 'plugin-usage-tracker' );
		}

		if ( ! empty( $signals['widgets'] ) ) {
			$score  += 5;
			$notes[] = __( 'Registers widgets.', 'plugin-usage-tracker' );
		}

		if ( ! $is_active ) {
			$score  -= 20;
			$notes[] = __( 'Plugin is inactive.', 'plugin-usage-tracker' );
		}

		$score = max( 0, min( 100, $score ) );

		return array(
			'plugin_file'      => $plugin_file,
			'name'             => isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : basename( dirname( $plugin_file ) ),
			'slug'             => sanitize_title( isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : basename( dirname( $plugin_file ) ) ),
			'version'          => isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '',
			'description'      => isset( $plugin_data['Description'] ) ? (string) $plugin_data['Description'] : '',
			'author'           => isset( $plugin_data['AuthorName'] ) ? (string) $plugin_data['AuthorName'] : '',
			'is_active'        => $is_active,
			'override_needed'  => $override_needed,
			'confidence_score' => $score,
			'confidence_label' => $this->score_to_label( $score, $override_needed ),
			'signals'          => $signals,
			'content_usage'    => $usage,
			'runtime_capture'  => $runtime_capture,
			'runtime_hits'     => $runtime_hits,
			'notes'            => array_values( array_unique( $notes ) ),
			'file_count'       => isset( $static_analysis['file_count'] ) ? (int) $static_analysis['file_count'] : 0,
		);
	}

	/**
	 * Build summary counts.
	 *
	 * @param array<int, array<string, mixed>> $results Plugin results.
	 * @return array<string, int>
	 */
	private function build_summary( array $results ): array {
		$summary = array(
			'total'             => count( $results ),
			'likely-used'       => 0,
			'possibly-unused'   => 0,
			'insufficient-data' => 0,
			'likely-unused'     => 0,
			'active'            => 0,
			'inactive'          => 0,
			'overridden'        => 0,
		);

		foreach ( $results as $result ) {
			$label = isset( $result['confidence_label'] ) ? (string) $result['confidence_label'] : 'insufficient-data';

			if ( ! empty( $result['is_active'] ) ) {
				++$summary['active'];
			} else {
				++$summary['inactive'];
			}

			if ( ! empty( $result['override_needed'] ) ) {
				++$summary['overridden'];
			}

			if ( isset( $summary[ $label ] ) ) {
				++$summary[ $label ];
			}
		}

		return $summary;
	}

	/**
	 * Convert score to confidence label.
	 *
	 * @param int  $score Confidence score.
	 * @param bool $override_needed Whether the plugin is manually marked needed.
	 */
	private function score_to_label( int $score, bool $override_needed ): string {
		if ( $override_needed ) {
			return 'likely-used';
		}

		if ( $score >= 75 ) {
			return 'likely-used';
		}

		if ( $score >= 50 ) {
			return 'possibly-unused';
		}

		if ( $score >= 25 ) {
			return 'insufficient-data';
		}

		return 'likely-unused';
	}

	/**
	 * Find matching hooks between static and runtime signal sets.
	 *
	 * @param array<int, string> $static_hooks Static hooks.
	 * @param array<int, string> $runtime_hooks Runtime hooks.
	 * @return array<int, string>
	 */
	private function intersect_signals( array $static_hooks, array $runtime_hooks ): array {
		$matches = array();

		foreach ( array_map( 'strval', $static_hooks ) as $hook ) {
			if ( in_array( $hook, array_map( 'strval', $runtime_hooks ), true ) ) {
				$matches[] = $hook;
			}
		}

		return array_values( array_unique( $matches ) );
	}
}
