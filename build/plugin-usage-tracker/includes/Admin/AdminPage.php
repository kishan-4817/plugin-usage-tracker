<?php
/**
 * Admin page.
 *
 * @package PluginUsageTracker\Admin
 */

namespace PluginUsageTracker\Admin;

use PluginUsageTracker\Data\ResultsStore;
use PluginUsageTracker\Data\SettingsStore;
use PluginUsageTracker\Scanner\RuntimeObserver;
use PluginUsageTracker\Scanner\PluginScanner;

/**
 * Register the plugin dashboard.
 */
final class AdminPage {

	/**
	 * Menu slug.
	 */
	public const MENU_SLUG = 'plugin-usage-tracker';

	/**
	 * Required capability.
	 */
	public const CAPABILITY = 'manage_options';

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
	 * Scanner.
	 *
	 * @var PluginScanner
	 */
	private PluginScanner $scanner;

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
		$this->results_store    = new ResultsStore();
		$this->settings_store   = new SettingsStore();
		$this->scanner          = new PluginScanner();
		$this->runtime_observer = new RuntimeObserver();
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_put_run_scan', array( $this, 'handle_run_scan' ) );
		add_action( 'admin_post_put_run_runtime_test', array( $this, 'handle_run_runtime_test' ) );
		add_action( 'admin_post_put_toggle_override', array( $this, 'handle_toggle_override' ) );
		add_action( 'admin_post_put_export_results', array( $this, 'handle_export_results' ) );
	}

	/**
	 * Add page under Tools.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_management_page(
			__( 'Plugin Usage Tracker', 'plugin-usage-tracker' ),
			__( 'Plugin Usage Tracker', 'plugin-usage-tracker' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook_suffix && 'tools_page_' . SettingsPage::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'put-admin',
			PUT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			PUT_VERSION
		);

		wp_enqueue_script(
			'put-admin',
			PUT_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			PUT_VERSION,
			true
		);
	}

	/**
	 * Handle scan requests.
	 *
	 * @return void
	 */
	public function handle_run_scan(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to run scans.', 'plugin-usage-tracker' ) );
		}

		check_admin_referer( 'put_run_scan' );

		$this->scanner->scan();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::MENU_SLUG,
					'put_notice' => 'scan_complete',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Run a runtime hook capture against the front end.
	 *
	 * @return void
	 */
	public function handle_run_runtime_test(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to run runtime tests.', 'plugin-usage-tracker' ) );
		}

		check_admin_referer( 'put_run_runtime_test' );

		$token = wp_generate_password( 24, false, false );
		$this->runtime_observer->set_token( $token );

		$response = wp_remote_get(
			$this->runtime_observer->build_capture_url( home_url( '/' ), $token ),
			array(
				'timeout'     => 30,
				'blocking'    => true,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html( $response->get_error_message() ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::MENU_SLUG,
					'put_notice' => 'runtime_test_complete',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Toggle manual needed override.
	 *
	 * @return void
	 */
	public function handle_toggle_override(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to update overrides.', 'plugin-usage-tracker' ) );
		}

		$plugin_file = isset( $_GET['plugin'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) ) : '';
		$needed      = isset( $_GET['needed'] ) ? '1' === sanitize_text_field( wp_unslash( $_GET['needed'] ) ) : false;

		if ( '' === $plugin_file ) {
			wp_die( esc_html__( 'Missing plugin file.', 'plugin-usage-tracker' ) );
		}

		check_admin_referer( 'put_toggle_override_' . $plugin_file );

		$this->results_store->set_needed( $plugin_file, $needed );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::MENU_SLUG,
					'put_notice' => $needed ? 'override_added' : 'override_removed',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Export results.
	 *
	 * @return void
	 */
	public function handle_export_results(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to export results.', 'plugin-usage-tracker' ) );
		}

		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'json';
		check_admin_referer( 'put_export_results_' . $format );

		$payload = $this->results_store->get_latest_scan();
		$rows    = $this->results_store->flatten_payload( $payload );

		if ( empty( $payload ) ) {
			wp_die( esc_html__( 'No scan results available to export.', 'plugin-usage-tracker' ) );
		}

		if ( 'csv' === $format ) {
			$this->send_csv_export( $rows );
			return;
		}

		$this->send_json_export( $payload );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'plugin-usage-tracker' ) );
		}

		$results         = $this->results_store->get_latest_scan();
		$summary         = isset( $results['summary'] ) && is_array( $results['summary'] ) ? $results['summary'] : array();
		$runtime_capture = $this->runtime_observer->get_capture();
		$export_json_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'put_export_results',
					'format' => 'json',
				),
				admin_url( 'admin-post.php' )
			),
			'put_export_results_json'
		);
		$export_csv_url  = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'put_export_results',
					'format' => 'csv',
				),
				admin_url( 'admin-post.php' )
			),
			'put_export_results_csv'
		);
		?>
		<div class="wrap put-wrap">
			<div class="put-hero">
				<div class="put-hero-copy">
					<p class="put-eyebrow"><?php esc_html_e( 'Plugin Usage Tracker', 'plugin-usage-tracker' ); ?></p>
					<h1><?php esc_html_e( 'See which plugins are actually carrying weight.', 'plugin-usage-tracker' ); ?></h1>
					<p class="put-description">
						<?php esc_html_e( 'Static code signals, content usage, and a runtime hook capture work together to separate active value from quiet bloat.', 'plugin-usage-tracker' ); ?>
					</p>
					<div class="put-hero-points">
						<span><?php esc_html_e( 'Static scan', 'plugin-usage-tracker' ); ?></span>
						<span><?php esc_html_e( 'Content usage', 'plugin-usage-tracker' ); ?></span>
						<span><?php esc_html_e( 'Runtime test', 'plugin-usage-tracker' ); ?></span>
					</div>
				</div>

				<div class="put-hero-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="put-scan-form">
						<input type="hidden" name="action" value="put_run_scan" />
						<?php wp_nonce_field( 'put_run_scan' ); ?>
						<button type="submit" class="button button-primary button-hero">
							<?php esc_html_e( 'Run Scan', 'plugin-usage-tracker' ); ?>
						</button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="put-scan-form">
						<input type="hidden" name="action" value="put_run_runtime_test" />
						<?php wp_nonce_field( 'put_run_runtime_test' ); ?>
						<button type="submit" class="button button-secondary">
							<?php esc_html_e( 'Run Runtime Test', 'plugin-usage-tracker' ); ?>
						</button>
					</form>
					<div class="put-export-actions">
						<a class="button" href="<?php echo esc_url( $export_json_url ); ?>"><?php esc_html_e( 'Export JSON', 'plugin-usage-tracker' ); ?></a>
						<a class="button" href="<?php echo esc_url( $export_csv_url ); ?>"><?php esc_html_e( 'Export CSV', 'plugin-usage-tracker' ); ?></a>
					</div>
				</div>
			</div>

			<div class="put-snapshot-strip">
				<div class="put-snapshot-item">
					<span class="put-snapshot-label"><?php esc_html_e( 'Plugins scanned', 'plugin-usage-tracker' ); ?></span>
					<strong><?php echo esc_html( (string) absint( isset( $summary['total'] ) ? $summary['total'] : 0 ) ); ?></strong>
				</div>
				<div class="put-snapshot-item">
					<span class="put-snapshot-label"><?php esc_html_e( 'Runtime hooks', 'plugin-usage-tracker' ); ?></span>
					<strong><?php echo esc_html( (string) absint( isset( $runtime_capture['hook_count'] ) ? $runtime_capture['hook_count'] : 0 ) ); ?></strong>
				</div>
				<div class="put-snapshot-item">
					<span class="put-snapshot-label"><?php esc_html_e( 'Last test', 'plugin-usage-tracker' ); ?></span>
					<strong><?php echo esc_html( ! empty( $runtime_capture['captured_at'] ) ? (string) $runtime_capture['captured_at'] : __( 'Not run yet', 'plugin-usage-tracker' ) ); ?></strong>
				</div>
			</div>

			<?php $this->render_notices(); ?>

			<?php if ( empty( $results ) || empty( $results['plugins'] ) ) : ?>
				<div class="put-empty-state">
					<h2><?php esc_html_e( 'No scan results yet', 'plugin-usage-tracker' ); ?></h2>
					<p>
						<?php esc_html_e( 'Start with a scan to collect plugin signals. The first pass focuses on static code analysis and content usage checks.', 'plugin-usage-tracker' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="put-summary-grid">
					<?php
					$this->render_summary_card( __( 'Total plugins', 'plugin-usage-tracker' ), isset( $summary['total'] ) ? absint( $summary['total'] ) : 0 );
					$this->render_summary_card( __( 'Likely used', 'plugin-usage-tracker' ), isset( $summary['likely-used'] ) ? absint( $summary['likely-used'] ) : 0 );
					$this->render_summary_card( __( 'Possibly unused', 'plugin-usage-tracker' ), isset( $summary['possibly-unused'] ) ? absint( $summary['possibly-unused'] ) : 0 );
					$this->render_summary_card( __( 'Likely unused', 'plugin-usage-tracker' ), isset( $summary['likely-unused'] ) ? absint( $summary['likely-unused'] ) : 0 );
					?>
				</div>

				<div class="put-results">
					<div class="put-results-header">
						<div>
							<h2><?php esc_html_e( 'Scan results', 'plugin-usage-tracker' ); ?></h2>
							<?php if ( ! empty( $results['generated_at'] ) ) : ?>
								<p class="put-results-meta">
									<?php
									printf(
										/* translators: %s = date string */
										esc_html__( 'Last scanned %s', 'plugin-usage-tracker' ),
										esc_html( (string) $results['generated_at'] )
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					</div>
					<?php
					$table = new ResultsTable( $results, $this->results_store );
					$table->prepare_items();
					$table->display();
					?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $runtime_capture['hooks'] ) && is_array( $runtime_capture['hooks'] ) ) : ?>
				<div class="put-runtime-panel">
					<h2><?php esc_html_e( 'Runtime snapshot', 'plugin-usage-tracker' ); ?></h2>
					<p><?php esc_html_e( 'This request captured hooks from a live front-end pass. Matching hooks are fed back into scoring.', 'plugin-usage-tracker' ); ?></p>
					<p class="put-runtime-list">
						<?php echo esc_html( implode( ', ', array_slice( array_map( 'strval', $runtime_capture['hooks'] ), 0, 10 ) ) ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="put-settings-link">
				<a href="<?php echo esc_url( menu_page_url( SettingsPage::MENU_SLUG, false ) ); ?>"><?php esc_html_e( 'Open settings', 'plugin-usage-tracker' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	private function render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice flag.
		$notice = isset( $_GET['put_notice'] ) ? sanitize_key( wp_unslash( $_GET['put_notice'] ) ) : '';

		if ( '' === $notice ) {
			return;
		}

		$messages = array(
			'scan_complete'         => __( 'Scan completed successfully.', 'plugin-usage-tracker' ),
			'runtime_test_complete' => __( 'Runtime test completed successfully.', 'plugin-usage-tracker' ),
			'override_added'        => __( 'The plugin has been marked as needed.', 'plugin-usage-tracker' ),
			'override_removed'      => __( 'The manual override was removed.', 'plugin-usage-tracker' ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $messages[ $notice ] )
		);
	}

	/**
	 * Render a summary card.
	 *
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @return void
	 */
	private function render_summary_card( string $label, int $value ): void {
		?>
		<div class="put-summary-card">
			<span class="put-summary-value"><?php echo esc_html( (string) $value ); ?></span>
			<span class="put-summary-label"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	/**
	 * Send JSON export.
	 *
	 * @param array<string, mixed> $payload Results payload.
	 * @return void
	 */
	private function send_json_export( array $payload ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=plugin-usage-tracker-results.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Send CSV export.
	 *
	 * @param array<int, array<string, mixed>> $rows Export rows.
	 * @return void
	 */
	private function send_csv_export( array $rows ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=plugin-usage-tracker-results.csv' );

		$handle = fopen( 'php://output', 'w' );

		if ( false === $handle ) {
			wp_die( esc_html__( 'Unable to open export output.', 'plugin-usage-tracker' ) );
		}

		if ( ! empty( $rows ) ) {
			fputcsv( $handle, array_keys( $rows[0] ) );

			foreach ( $rows as $row ) {
				fputcsv( $handle, $row );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Temp output stream.
		fclose( $handle );
		exit;
	}
}
