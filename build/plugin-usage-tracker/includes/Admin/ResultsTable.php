<?php
/**
 * Results table.
 *
 * @package PluginUsageTracker\Admin
 */

namespace PluginUsageTracker\Admin;

use PluginUsageTracker\Data\ResultsStore;
use PluginUsageTracker\Data\SettingsStore;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin list table for scan results.
 */
final class ResultsTable extends \WP_List_Table {

	/**
	 * Results payload.
	 *
	 * @var array<string, mixed>
	 */
	private array $results;

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
	 * Constructor.
	 *
	 * @param array<string, mixed> $results Scan payload.
	 * @param ResultsStore         $results_store Results store.
	 */
	public function __construct( array $results, ResultsStore $results_store ) {
		parent::__construct(
			array(
				'singular' => 'plugin_result',
				'plural'   => 'plugin_results',
				'ajax'     => false,
			)
		);

		$this->results        = $results;
		$this->results_store  = $results_store;
		$this->settings_store = new SettingsStore();
	}

	/**
	 * Prepare table items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$items    = isset( $this->results['plugins'] ) && is_array( $this->results['plugins'] ) ? array_values( $this->results['plugins'] ) : array();
		$settings = $this->settings_store->all();

		if ( empty( $settings['show_likely_used'] ) ) {
			$items = array_values(
				array_filter(
					$items,
					static function ( array $item ): bool {
						return 'likely-used' !== ( isset( $item['confidence_label'] ) ? (string) $item['confidence_label'] : '' );
					}
				)
			);
		}

		$this->items = $items;

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'plugin'     => __( 'Plugin', 'plugin-usage-tracker' ),
			'status'     => __( 'Status', 'plugin-usage-tracker' ),
			'confidence' => __( 'Confidence', 'plugin-usage-tracker' ),
			'signals'    => __( 'Signals', 'plugin-usage-tracker' ),
			'notes'      => __( 'Why', 'plugin-usage-tracker' ),
			'override'   => __( 'Override', 'plugin-usage-tracker' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, string>>
	 */
	protected function get_sortable_columns(): array {
		return array();
	}

	/**
	 * Default column output.
	 *
	 * @param array<string, mixed> $item Item.
	 * @param string               $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'status':
				return $this->render_status( $item );
			case 'confidence':
				return $this->render_confidence( $item );
			case 'signals':
				return $this->render_signals( $item );
			case 'notes':
				return $this->render_notes( $item );
			case 'override':
				return $this->render_override( $item );
		}

		return '';
	}

	/**
	 * Plugin column.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return string
	 */
	protected function column_plugin( $item ) {
		$name        = isset( $item['name'] ) ? (string) $item['name'] : __( 'Unknown plugin', 'plugin-usage-tracker' );
		$plugin_file = isset( $item['plugin_file'] ) ? (string) $item['plugin_file'] : '';
		$version     = '';
		if ( isset( $item['version'] ) && '' !== $item['version'] ) {
			/* translators: %s = plugin version. */
			$version = sprintf( __( 'Version %s', 'plugin-usage-tracker' ), esc_html( (string) $item['version'] ) );
		}
		$description = isset( $item['description'] ) ? (string) $item['description'] : '';

		return sprintf(
			'<strong>%1$s</strong><div class="put-cell-meta">%2$s%3$s</div><code class="put-plugin-file">%4$s</code>',
			esc_html( $name ),
			$version ? '<span>' . $version . '</span>' : '',
			$description ? '<span>' . esc_html( $description ) . '</span>' : '',
			esc_html( $plugin_file )
		);
	}

	/**
	 * Status cell.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return string
	 */
	private function render_status( array $item ): string {
		$status = ! empty( $item['is_active'] ) ? 'active' : 'inactive';
		$label  = 'active' === $status ? __( 'Active', 'plugin-usage-tracker' ) : __( 'Inactive', 'plugin-usage-tracker' );

		return sprintf( '<span class="put-badge put-badge-%1$s">%2$s</span>', esc_attr( $status ), esc_html( $label ) );
	}

	/**
	 * Confidence cell.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return string
	 */
	private function render_confidence( array $item ): string {
		$label = isset( $item['confidence_label'] ) ? (string) $item['confidence_label'] : 'insufficient-data';
		$text  = array(
			'likely-used'       => __( 'Likely used', 'plugin-usage-tracker' ),
			'possibly-unused'   => __( 'Possibly unused', 'plugin-usage-tracker' ),
			'insufficient-data' => __( 'Insufficient data', 'plugin-usage-tracker' ),
			'likely-unused'     => __( 'Likely unused', 'plugin-usage-tracker' ),
		);

		return sprintf(
			'<span class="put-badge put-badge-%1$s">%2$s</span><span class="put-score">%3$d/100</span>',
			esc_attr( $label ),
			esc_html( isset( $text[ $label ] ) ? $text[ $label ] : $label ),
			isset( $item['confidence_score'] ) ? absint( $item['confidence_score'] ) : 0
		);
	}

	/**
	 * Signals cell.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return string
	 */
	private function render_signals( array $item ): string {
		$signals         = isset( $item['signals'] ) && is_array( $item['signals'] ) ? $item['signals'] : array();
		$runtime_capture = isset( $item['runtime_capture'] ) && is_array( $item['runtime_capture'] ) ? $item['runtime_capture'] : array();
		$parts           = array();

		$parts[] = sprintf(
			/* translators: %d = file count. */
			_n( '%d file', '%d files', isset( $item['file_count'] ) ? absint( $item['file_count'] ) : 0, 'plugin-usage-tracker' ),
			isset( $item['file_count'] ) ? absint( $item['file_count'] ) : 0
		);

		foreach ( array( 'hooks', 'shortcodes', 'cpts', 'blocks', 'rest_routes' ) as $key ) {
			if ( empty( $signals[ $key ] ) || ! is_array( $signals[ $key ] ) ) {
				continue;
			}

			$parts[] = sprintf(
				/* translators: 1: signal label, 2: count. */
				__( '%1$s: %2$d', 'plugin-usage-tracker' ),
				ucfirst( str_replace( '_', ' ', $key ) ),
				count( $signals[ $key ] )
			);
		}

		if ( ! empty( $runtime_capture['hook_count'] ) ) {
			$parts[] = sprintf(
				/* translators: %d = runtime hook count. */
				__( 'Runtime hooks: %d', 'plugin-usage-tracker' ),
				absint( $runtime_capture['hook_count'] )
			);
		}

		return '<div class="put-signal-list">' . esc_html( implode( ' | ', $parts ) ) . '</div>';
	}

	/**
	 * Notes cell.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return string
	 */
	private function render_notes( array $item ): string {
		$notes = isset( $item['notes'] ) && is_array( $item['notes'] ) ? $item['notes'] : array();

		if ( empty( $notes ) ) {
			return '';
		}

		$items = array();

		foreach ( array_slice( $notes, 0, 4 ) as $note ) {
			$items[] = '<li>' . esc_html( (string) $note ) . '</li>';
		}

		return '<ul class="put-note-list">' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Override controls.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return string
	 */
	private function render_override( array $item ): string {
		$plugin_file = isset( $item['plugin_file'] ) ? (string) $item['plugin_file'] : '';
		$is_needed   = ! empty( $item['override_needed'] );
		$action      = $is_needed ? '0' : '1';
		$label       = $is_needed ? __( 'Remove override', 'plugin-usage-tracker' ) : __( 'Mark needed', 'plugin-usage-tracker' );
		$url         = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'put_toggle_override',
					'plugin' => rawurlencode( $plugin_file ),
					'needed' => $action,
				),
				admin_url( 'admin-post.php' )
			),
			'put_toggle_override_' . $plugin_file
		);

		return sprintf(
			'<a class="button %1$s" href="%2$s">%3$s</a>',
			$is_needed ? 'button-secondary' : 'button-primary',
			esc_url( $url ),
			esc_html( $label )
		);
	}
}
