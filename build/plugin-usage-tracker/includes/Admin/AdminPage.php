<?php
/**
 * Admin Page.
 *
 * @package PluginUsageTracker\Admin
 */

namespace PluginUsageTracker\Admin;

/**
 * AdminPage
 *
 * Registers the Tools > Plugin Usage Tracker admin page.
 */
class AdminPage {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'plugin-usage-tracker';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add page under Tools menu.
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
	 * Enqueue admin assets only on our page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook_suffix ) {
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
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'plugin-usage-tracker' ) );
		}

		?>
		<div class="wrap put-wrap">
			<h1><?php esc_html_e( 'Plugin Usage Tracker', 'plugin-usage-tracker' ); ?></h1>
			<p class="put-description">
				<?php esc_html_e( 'Scan your active plugins and identify ones that may not be contributing to your site.', 'plugin-usage-tracker' ); ?>
			</p>

			<div class="put-actions">
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG . '&put_action=scan&_wpnonce=' . wp_create_nonce( 'put_scan' ) ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Run Scan', 'plugin-usage-tracker' ); ?>
				</a>
			</div>

			<div class="put-results">
				<p><?php esc_html_e( 'No scan results yet. Run a scan to get started.', 'plugin-usage-tracker' ); ?></p>
			</div>
		</div>
		<?php
	}
}
