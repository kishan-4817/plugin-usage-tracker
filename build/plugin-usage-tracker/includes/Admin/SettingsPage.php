<?php
/**
 * Settings page.
 *
 * @package PluginUsageTracker\Admin
 */

namespace PluginUsageTracker\Admin;

use PluginUsageTracker\Data\SettingsStore;

/**
 * Register plugin settings.
 */
final class SettingsPage {

	/**
	 * Menu slug.
	 */
	public const MENU_SLUG = 'plugin-usage-tracker-settings';

	/**
	 * Capability.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Settings store.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $settings_store;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_store = new SettingsStore();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'tools.php',
			__( 'Plugin Usage Tracker Settings', 'plugin-usage-tracker' ),
			__( 'Plugin Usage Tracker Settings', 'plugin-usage-tracker' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'put_settings_group',
			SettingsStore::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => SettingsStore::defaults(),
			)
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array<string, mixed> $input Submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$exclude_plugins_text = isset( $input['exclude_plugins_text'] ) ? (string) wp_unslash( $input['exclude_plugins_text'] ) : '';
		$exclude_plugins      = preg_split( '/\r\n|\r|\n/', $exclude_plugins_text );

		$settings = $this->settings_store->sanitize(
			array(
				'exclude_plugins'     => is_array( $exclude_plugins ) ? $exclude_plugins : array(),
				'retain_results_days' => isset( $input['retain_results_days'] ) ? $input['retain_results_days'] : 30,
				'show_likely_used'    => ! empty( $input['show_likely_used'] ),
				'enable_cli'          => ! empty( $input['enable_cli'] ),
			)
		);

		return $settings;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'plugin-usage-tracker' ) );
		}

		$settings         = $this->settings_store->all();
		$exclude_plugins  = implode( PHP_EOL, isset( $settings['exclude_plugins'] ) && is_array( $settings['exclude_plugins'] ) ? $settings['exclude_plugins'] : array() );
		$retention_days   = isset( $settings['retain_results_days'] ) ? absint( $settings['retain_results_days'] ) : 30;
		$show_likely_used = ! empty( $settings['show_likely_used'] );
		$enable_cli       = ! empty( $settings['enable_cli'] );
		?>
		<div class="wrap put-wrap">
			<div class="put-hero">
				<div>
					<p class="put-eyebrow"><?php esc_html_e( 'Plugin Usage Tracker', 'plugin-usage-tracker' ); ?></p>
					<h1><?php esc_html_e( 'Settings', 'plugin-usage-tracker' ); ?></h1>
					<p class="put-description">
						<?php esc_html_e( 'Tune scan retention, exclusion rules, dashboard filtering, and WP-CLI access.', 'plugin-usage-tracker' ); ?>
					</p>
				</div>
			</div>

			<form method="post" action="options.php" class="put-settings-form">
				<?php settings_fields( 'put_settings_group' ); ?>

				<div class="put-settings-grid">
					<div class="put-settings-panel">
						<h2><?php esc_html_e( 'Scan Controls', 'plugin-usage-tracker' ); ?></h2>
						<p class="description"><?php esc_html_e( 'One plugin file per line, relative to the plugins directory.', 'plugin-usage-tracker' ); ?></p>

						<label for="put-exclude-plugins"><?php esc_html_e( 'Exclude plugins', 'plugin-usage-tracker' ); ?></label>
						<textarea id="put-exclude-plugins" name="<?php echo esc_attr( SettingsStore::OPTION_KEY ); ?>[exclude_plugins_text]" rows="8" class="large-text code"><?php echo esc_textarea( $exclude_plugins ); ?></textarea>
					</div>

					<div class="put-settings-panel">
						<h2><?php esc_html_e( 'Behavior', 'plugin-usage-tracker' ); ?></h2>

						<label for="put-retention-days"><?php esc_html_e( 'Retention days', 'plugin-usage-tracker' ); ?></label>
						<input
							type="number"
							min="1"
							id="put-retention-days"
							name="<?php echo esc_attr( SettingsStore::OPTION_KEY ); ?>[retain_results_days]"
							value="<?php echo esc_attr( (string) $retention_days ); ?>"
							class="small-text"
						/>

						<p>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( SettingsStore::OPTION_KEY ); ?>[show_likely_used]"
									value="1"
									<?php checked( $show_likely_used ); ?>
								/>
								<?php esc_html_e( 'Show likely used plugins in the dashboard table', 'plugin-usage-tracker' ); ?>
							</label>
						</p>

						<p>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( SettingsStore::OPTION_KEY ); ?>[enable_cli]"
									value="1"
									<?php checked( $enable_cli ); ?>
								/>
								<?php esc_html_e( 'Enable WP-CLI commands when available', 'plugin-usage-tracker' ); ?>
							</label>
						</p>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'plugin-usage-tracker' ) ); ?>
			</form>
		</div>
		<?php
	}
}
