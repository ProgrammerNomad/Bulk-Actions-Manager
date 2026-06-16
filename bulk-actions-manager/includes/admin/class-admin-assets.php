<?php
/**
 * Admin assets loader.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Assets
 */
class Admin_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'bam-' ) ) {
			return;
		}

		wp_enqueue_style(
			'bam-admin',
			BAM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BAM_VERSION
		);

		wp_enqueue_script(
			'bam-admin-common',
			BAM_PLUGIN_URL . 'assets/js/admin-common.js',
			array( 'wp-api-fetch' ),
			BAM_VERSION,
			true
		);

		wp_localize_script(
			'bam-admin-common',
			'bamAdmin',
			array(
				'restRoot'  => esc_url_raw( rest_url() ),
				'restNs'    => BAM_REST_NAMESPACE,
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => BAM_PLUGIN_URL,
				'i18n'      => array(
					'confirm'     => __( 'Are you sure?', 'bulk-actions-manager' ),
					'processing'  => __( 'Processing...', 'bulk-actions-manager' ),
					'error'       => __( 'An error occurred.', 'bulk-actions-manager' ),
					'completed'   => __( 'Completed', 'bulk-actions-manager' ),
					'paused'      => __( 'Paused', 'bulk-actions-manager' ),
					'cancelled'   => __( 'Cancelled', 'bulk-actions-manager' ),
				),
			)
		);

		if ( 'bam-new-job' === $page ) {
			wp_enqueue_style(
				'bam-admin-new-job',
				BAM_PLUGIN_URL . 'assets/css/admin-new-job.css',
				array( 'bam-admin' ),
				BAM_VERSION
			);

			wp_enqueue_script(
				'bam-filter-builder',
				BAM_PLUGIN_URL . 'assets/js/filter-builder.js',
				array( 'bam-admin-common' ),
				BAM_VERSION,
				true
			);

			wp_enqueue_script(
				'bam-preview-panel',
				BAM_PLUGIN_URL . 'assets/js/preview-panel.js',
				array( 'bam-admin-common', 'bam-filter-builder' ),
				BAM_VERSION,
				true
			);

			wp_enqueue_script(
				'bam-job-runner',
				BAM_PLUGIN_URL . 'assets/js/job-runner.js',
				array( 'bam-admin-common' ),
				BAM_VERSION,
				true
			);
		}

		if ( 'bam-jobs' === $page ) {
			wp_enqueue_script(
				'bam-job-list',
				BAM_PLUGIN_URL . 'assets/js/job-list.js',
				array( 'bam-admin-common' ),
				BAM_VERSION,
				true
			);
		}
	}
}
