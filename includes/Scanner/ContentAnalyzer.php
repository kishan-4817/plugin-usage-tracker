<?php
/**
 * Content analyzer.
 *
 * @package PluginUsageTracker\Scanner
 */

namespace PluginUsageTracker\Scanner;

/**
 * Check for content-level usage signals.
 */
final class ContentAnalyzer {

	/**
	 * Analyze content usage for static signals.
	 *
	 * @param array<string, mixed> $static_analysis Static analysis payload.
	 * @return array<string, mixed>
	 */
	public function analyze( array $static_analysis ): array {
		$usage = array(
			'shortcodes' => array(),
			'blocks'     => array(),
			'cpts'       => array(),
		);

		$signals = isset( $static_analysis['signals'] ) && is_array( $static_analysis['signals'] ) ? $static_analysis['signals'] : array();

		$usage['shortcodes'] = $this->find_shortcode_usage( isset( $signals['shortcodes'] ) ? (array) $signals['shortcodes'] : array() );
		$usage['blocks']     = $this->find_block_usage( isset( $signals['blocks'] ) ? (array) $signals['blocks'] : array() );
		$usage['cpts']       = $this->find_cpt_usage( isset( $signals['cpts'] ) ? (array) $signals['cpts'] : array() );

		return array(
			'usage'     => $usage,
			'has_usage' => $this->has_usage( $usage ),
		);
	}

	/**
	 * Search content for shortcode usage.
	 *
	 * @param array<int, string> $shortcodes Shortcodes.
	 * @return array<string, int>
	 */
	private function find_shortcode_usage( array $shortcodes ): array {
		global $wpdb;

		$usage = array();

		foreach ( array_unique( $shortcodes ) as $shortcode ) {
			$shortcode = sanitize_key( $shortcode );

			if ( '' === $shortcode ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only content scan.
			$usage[ $shortcode ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_content LIKE %s",
					'%' . $wpdb->esc_like( '[' . $shortcode ) . '%'
				)
			);
		}

		return array_filter( $usage );
	}

	/**
	 * Search content for block usage.
	 *
	 * @param array<int, string> $blocks Blocks.
	 * @return array<string, int>
	 */
	private function find_block_usage( array $blocks ): array {
		global $wpdb;

		$usage = array();

		foreach ( array_unique( $blocks ) as $block ) {
			$block = sanitize_text_field( $block );

			if ( '' === $block ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only content scan.
			$usage[ $block ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_content LIKE %s",
					'%' . $wpdb->esc_like( '<!-- wp:' . $block ) . '%'
				)
			);
		}

		return array_filter( $usage );
	}

	/**
	 * Search content for CPT usage.
	 *
	 * @param array<int, string> $cpts CPT slugs.
	 * @return array<string, int>
	 */
	private function find_cpt_usage( array $cpts ): array {
		$usage = array();

		foreach ( array_unique( $cpts ) as $cpt ) {
			$cpt = sanitize_key( $cpt );

			if ( '' === $cpt ) {
				continue;
			}

			$post_counts = wp_count_posts( $cpt );

			if ( is_object( $post_counts ) ) {
				$usage[ $cpt ] = array_sum( array_map( 'absint', get_object_vars( $post_counts ) ) );
			}
		}

		return array_filter( $usage );
	}

	/**
	 * Determine if any usage exists.
	 *
	 * @param array<string, array<string, int>> $usage Usage map.
	 * @return bool
	 */
	private function has_usage( array $usage ): bool {
		foreach ( $usage as $bucket ) {
			foreach ( $bucket as $count ) {
				if ( $count > 0 ) {
					return true;
				}
			}
		}

		return false;
	}
}
