<?php
/**
 * Main plugin orchestrator.
 *
 * @package BulkActionsManager
 */

namespace BAM;

use BAM\Admin\Admin_Menu;
use BAM\Admin\Admin_Assets;
use BAM\Admin\Export_Download;
use BAM\REST\REST_Bootstrap;
use BAM\Cron\Cron_Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->init_hooks();
	}

	/**
	 * Load plugin text domain.
	 */
	private function load_textdomain() {
		add_action(
			'init',
			function () {
				load_plugin_textdomain(
					'bulk-actions-manager',
					false,
					dirname( plugin_basename( BAM_PLUGIN_FILE ) ) . '/languages'
				);
			}
		);
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_db' ) );
		add_filter( 'cron_schedules', array( Cron_Scheduler::class, 'add_intervals' ) );

		if ( is_admin() ) {
			new Admin_Menu();
			new Admin_Assets();
			new Export_Download();
		}

		new REST_Bootstrap();
		new Cron_Scheduler();
	}

	/**
	 * Run database migrations if needed.
	 */
	public function maybe_upgrade_db() {
		Database\Schema::run_migrations();
	}
}
