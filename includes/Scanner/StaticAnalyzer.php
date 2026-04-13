<?php
/**
 * Static analyzer.
 *
 * @package PluginUsageTracker\Scanner
 */

namespace PluginUsageTracker\Scanner;

/**
 * Inspect plugin files for usage signals.
 */
final class StaticAnalyzer {

	/**
	 * Ignored directories during analysis.
	 *
	 * @var array<int, string>
	 */
	private array $ignored_directories = array(
		'.git',
		'build',
		'dist',
		'node_modules',
		'tests',
		'test',
		'vendor',
	);

	/**
	 * Analyze a plugin.
	 *
	 * @param string               $plugin_file Plugin file.
	 * @param array<string, mixed> $plugin_data Plugin header data.
	 */
	public function analyze( string $plugin_file, array $plugin_data = array() ): array {
		$plugin_dir = $this->resolve_plugin_dir( $plugin_file );
		$files      = $this->collect_files( $plugin_dir );

		$signals = array(
			'hooks'       => array(),
			'shortcodes'  => array(),
			'cpts'        => array(),
			'taxonomies'  => array(),
			'rest_routes' => array(),
			'blocks'      => array(),
			'widgets'     => array(),
			'assets'      => array(),
		);

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin files only.
			$contents = file_get_contents( $file );

			if ( false === $contents ) {
				continue;
			}

			$this->extract_signals( $contents, $signals );

			if ( str_ends_with( strtolower( $file ), 'block.json' ) ) {
				$this->extract_block_metadata( $contents, $signals );
			}
		}

		return array(
			'plugin_file' => $plugin_file,
			'plugin_dir'  => $plugin_dir,
			'plugin_data' => $plugin_data,
			'file_count'  => count( $files ),
			'signals'     => array(
				'hooks'       => array_values( array_unique( $signals['hooks'] ) ),
				'shortcodes'  => array_values( array_unique( $signals['shortcodes'] ) ),
				'cpts'        => array_values( array_unique( $signals['cpts'] ) ),
				'taxonomies'  => array_values( array_unique( $signals['taxonomies'] ) ),
				'rest_routes' => array_values( array_unique( $signals['rest_routes'] ) ),
				'blocks'      => array_values( array_unique( $signals['blocks'] ) ),
				'widgets'     => array_values( array_unique( $signals['widgets'] ) ),
				'assets'      => array_values( array_unique( $signals['assets'] ) ),
			),
		);
	}

	/**
	 * Resolve plugin directory.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return string
	 */
	private function resolve_plugin_dir( string $plugin_file ): string {
		$relative_dir = dirname( $plugin_file );

		if ( '.' === $relative_dir || '/' === $relative_dir || '\\' === $relative_dir ) {
			return trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
		}

		return trailingslashit( wp_normalize_path( WP_PLUGIN_DIR . '/' . $relative_dir ) );
	}

	/**
	 * Collect files for analysis.
	 *
	 * @param string $plugin_dir Plugin directory.
	 * @return array<int, string>
	 */
	private function collect_files( string $plugin_dir ): array {
		if ( ! is_dir( $plugin_dir ) ) {
			return array();
		}

		$directory_iterator = new \RecursiveDirectoryIterator( $plugin_dir, \FilesystemIterator::SKIP_DOTS );
		$filter_iterator    = new \RecursiveCallbackFilterIterator(
			$directory_iterator,
			function ( \SplFileInfo $current ) {
				if ( $current->isDir() ) {
					return ! in_array( $current->getFilename(), $this->ignored_directories, true );
				}

				$extension = strtolower( $current->getExtension() );

				return in_array( $extension, array( 'php', 'js', 'json' ), true ) || 'block.json' === strtolower( $current->getFilename() );
			}
		);

		$iterator = new \RecursiveIteratorIterator( $filter_iterator );
		$files    = array();

		foreach ( $iterator as $file ) {
			if ( $file instanceof \SplFileInfo && $file->isFile() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Extract signals from file contents.
	 *
	 * @param string                            $contents File contents.
	 * @param array<string, array<int, string>> $signals Signal buckets.
	 */
	private function extract_signals( string $contents, array &$signals ): void {
		$this->collect_matches( $contents, '/add_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['hooks'] );
		$this->collect_matches( $contents, '/add_filter\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['hooks'] );
		$this->collect_matches( $contents, '/do_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['hooks'] );
		$this->collect_matches( $contents, '/apply_filters\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['hooks'] );
		$this->collect_matches( $contents, '/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['shortcodes'] );
		$this->collect_matches( $contents, '/register_post_type\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['cpts'] );
		$this->collect_matches( $contents, '/register_taxonomy\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['taxonomies'] );
		$this->collect_matches( $contents, '/register_rest_route\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['rest_routes'] );
		$this->collect_matches( $contents, '/register_widget\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['widgets'] );
		$this->collect_matches( $contents, '/register_block_type(?:_from_metadata)?\s*\(\s*[\'"]([^\'"]+)[\'"]/', $signals['blocks'] );
		$this->collect_matches( $contents, '/assets\/[A-Za-z0-9_\-\.\/]+\.(?:js|css|png|svg|jpe?g|webp|woff2?)/i', $signals['assets'] );
	}

	/**
	 * Extract block metadata.
	 *
	 * @param string                            $contents File contents.
	 * @param array<string, array<int, string>> $signals Signal buckets.
	 */
	private function extract_block_metadata( string $contents, array &$signals ): void {
		$data = json_decode( $contents, true );

		if ( ! is_array( $data ) || empty( $data['name'] ) || ! is_string( $data['name'] ) ) {
			return;
		}

		$signals['blocks'][] = $data['name'];
	}

	/**
	 * Collect regex matches into a signal bucket.
	 *
	 * @param string             $contents File contents.
	 * @param string             $pattern Regex pattern.
	 * @param array<int, string> $bucket Signal bucket.
	 */
	private function collect_matches( string $contents, string $pattern, array &$bucket ): void {
		if ( ! preg_match_all( $pattern, $contents, $matches ) ) {
			return;
		}

		if ( empty( $matches[1] ) ) {
			return;
		}

		foreach ( $matches[1] as $match ) {
			if ( is_string( $match ) && '' !== $match ) {
				$bucket[] = sanitize_text_field( $match );
			}
		}
	}
}
