<?php
/**
 * Runtime observer.
 *
 * @package PluginUsageTracker\Scanner
 */

namespace PluginUsageTracker\Scanner;

/**
 * Capture hook activity during a runtime test request.
 */
final class RuntimeObserver {

	/**
	 * Token transient key.
	 */
	public const TOKEN_KEY = 'put_runtime_hooks_token';

	/**
	 * Capture data transient key.
	 */
	public const DATA_KEY = 'put_runtime_hooks';

	/**
	 * Whether a capture is running.
	 *
	 * @var bool
	 */
	private bool $capture_active = false;

	/**
	 * Captured hooks.
	 *
	 * @var array<int, string>
	 */
	private array $captured_hooks = array();

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_start_capture' ), 1 );
		add_action( 'shutdown', array( $this, 'maybe_store_capture' ), 1 );
	}

	/**
	 * Set the next capture token.
	 *
	 * @param string $token Token.
	 * @return void
	 */
	public function set_token( string $token ): void {
		set_transient( self::TOKEN_KEY, $token, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Build capture URL.
	 *
	 * @param string $base_url Base URL.
	 * @param string $token Token.
	 * @return string
	 */
	public function build_capture_url( string $base_url, string $token ): string {
		return add_query_arg(
			array(
				'put_runtime_capture' => '1',
				'put_runtime_token'   => $token,
			),
			$base_url
		);
	}

	/**
	 * Check whether this request should capture hooks.
	 *
	 * @return bool
	 */
	public function is_capture_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Runtime capture token is validated against a transient.
		$enabled = isset( $_GET['put_runtime_capture'] ) ? sanitize_text_field( wp_unslash( $_GET['put_runtime_capture'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Runtime capture token is validated against a transient.
		$token = isset( $_GET['put_runtime_token'] ) ? sanitize_text_field( wp_unslash( $_GET['put_runtime_token'] ) ) : '';
		$saved = (string) get_transient( self::TOKEN_KEY );

		return '1' === $enabled && '' !== $token && '' !== $saved && hash_equals( $saved, $token );
	}

	/**
	 * Start capture when requested.
	 *
	 * @return void
	 */
	public function maybe_start_capture(): void {
		if ( ! $this->is_capture_request() ) {
			return;
		}

		$this->capture_active = true;
		$this->captured_hooks = array();

		add_action( 'all', array( $this, 'capture_hook' ), 1, 1 );
	}

	/**
	 * Capture hook names.
	 *
	 * @param string $hook_name Hook name.
	 * @return void
	 */
	public function capture_hook( $hook_name ): void {
		if ( ! $this->capture_active || ! is_string( $hook_name ) || '' === $hook_name ) {
			return;
		}

		if ( ! in_array( $hook_name, $this->captured_hooks, true ) ) {
			$this->captured_hooks[] = $hook_name;
		}
	}

	/**
	 * Store the runtime capture.
	 *
	 * @return void
	 */
	public function maybe_store_capture(): void {
		if ( ! $this->capture_active ) {
			return;
		}

		set_transient(
			self::DATA_KEY,
			array(
				'captured_at_gmt' => gmdate( 'c' ),
				'captured_at'     => current_time( 'mysql' ),
				'hook_count'      => count( $this->captured_hooks ),
				'hooks'           => $this->captured_hooks,
			),
			HOUR_IN_SECONDS
		);

		delete_transient( self::TOKEN_KEY );
	}

	/**
	 * Get the latest capture.
	 *
	 * @return array<string, mixed>
	 */
	public function get_capture(): array {
		$capture = get_transient( self::DATA_KEY );

		return is_array( $capture ) ? $capture : array();
	}

	/**
	 * Clear stored runtime data.
	 *
	 * @return void
	 */
	public function clear_capture(): void {
		delete_transient( self::DATA_KEY );
		delete_transient( self::TOKEN_KEY );
	}
}
