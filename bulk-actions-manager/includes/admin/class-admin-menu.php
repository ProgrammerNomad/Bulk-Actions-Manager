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
use BAM\Admin\Pages\Page_Scheduled;
use BAM\Admin\Pages\Page_Tools;
use BAM\Admin\Pages\Page_Settings;

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
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'Bulk Actions Manager', 'bulk-actions-manager' ),
			__( 'Bulk Actions Manager', 'bulk-actions-manager' ),
			BAM_CAPABILITY,
			'bam-dashboard',
			array( Page_Dashboard::class, 'render' ),
			'dashicons-performance',
			30
		);

		$pages = array(
			'bam-dashboard'  => array( __( 'Dashboard', 'bulk-actions-manager' ), Page_Dashboard::class ),
			'bam-new-job'    => array( __( 'New Job', 'bulk-actions-manager' ), Page_New_Job::class ),
			'bam-jobs'       => array( __( 'Jobs', 'bulk-actions-manager' ), Page_Jobs::class ),
			'bam-logs'       => array( __( 'Logs', 'bulk-actions-manager' ), Page_Logs::class ),
			'bam-scheduled'  => array( __( 'Scheduled Jobs', 'bulk-actions-manager' ), Page_Scheduled::class ),
			'bam-tools'      => array( __( 'Tools', 'bulk-actions-manager' ), Page_Tools::class ),
			'bam-settings'   => array( __( 'Settings', 'bulk-actions-manager' ), Page_Settings::class ),
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
				BAM_CAPABILITY,
				$slug,
				array( $page[1], 'render' )
			);
		}
	}
}
