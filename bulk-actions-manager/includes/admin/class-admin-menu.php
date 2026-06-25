<?php
/**
 * Admin menu registration.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin;

use BAM\Admin\Pages\Page_Dashboard;
use BAM\Admin\Pages\Page_New_Job;
use BAM\Admin\Pages\Page_Jobs;
use BAM\Admin\Pages\Page_Logs;
use BAM\Admin\Pages\Page_Tools;
use BAM\Admin\Pages\Page_Settings;
use BAM\Utils\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Menu
 */
class Admin_Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_scheduled_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_conflicting_query_args' ), 1 );
		add_filter(
			'plugin_action_links_' . plugin_basename( BAM_PLUGIN_FILE ),
			array( $this, 'add_plugin_action_links' )
		);
	}

	/**
	 * Add Settings link on the Plugins list row.
	 *
	 * @param array<string, string> $links Existing action links.
	 * @return array<string, string>
	 */
	public function add_plugin_action_links( $links ) {
		if ( ! Capabilities::current_user_can() ) {
			return $links;
		}

		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=bam-settings' ) ),
			esc_html__( 'Settings', 'bulk-actions-manager' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Strip post_type from BAM admin URLs.
	 *
	 * WordPress treats ?post_type= as a post list screen parent (admin.php?post_type=post),
	 * which breaks plugin page hook resolution and shows "Cannot load bam-new-job".
	 */
	public function redirect_conflicting_query_args() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( 0 !== strpos( $page, 'bam-' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['post_type'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = wp_unslash( $_GET );
		$args['bam_post_type']          = sanitize_key( (string) $args['post_type'] );
		unset( $args['post_type'] );

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Redirect old Scheduled Jobs menu slug to unified Jobs page.
	 */
	public function redirect_legacy_scheduled_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'bam-scheduled' !== $page ) {
			return;
		}

		$args = array(
			'page' => 'bam-jobs',
			'type' => 'schedule',
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['edit'] ) ) {
			$args['edit'] = absint( $_GET['edit'] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['add'] ) ) {
			$args['add'] = 1;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_menus() {
		$cap = Capabilities::get_capability();

		add_menu_page(
			__( 'Bulk Actions Manager', 'bulk-actions-manager' ),
			__( 'Bulk Actions', 'bulk-actions-manager' ),
			$cap,
			'bam-dashboard',
			array( Page_Dashboard::class, 'render' ),
			'dashicons-performance',
			30
		);

		$pages = array(
			'bam-dashboard' => array( __( 'Dashboard', 'bulk-actions-manager' ), Page_Dashboard::class ),
			'bam-new-job'   => array( __( 'New Job', 'bulk-actions-manager' ), Page_New_Job::class ),
			'bam-jobs'      => array( __( 'Jobs', 'bulk-actions-manager' ), Page_Jobs::class ),
			'bam-logs'      => array( __( 'Logs', 'bulk-actions-manager' ), Page_Logs::class ),
			'bam-tools'     => array( __( 'Tools', 'bulk-actions-manager' ), Page_Tools::class ),
			'bam-settings'  => array( __( 'Settings', 'bulk-actions-manager' ), Page_Settings::class ),
		);

		$first = true;
		foreach ( $pages as $slug => $page ) {
			if ( $first ) {
				$first = false;
				continue;
			}

			add_submenu_page(
				'bam-dashboard',
				$page[0],
				$page[0],
				$cap,
				$slug,
				array( $page[1], 'render' )
			);
		}
	}
}
