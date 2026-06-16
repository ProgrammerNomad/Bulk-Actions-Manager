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
					'confirm'        => __( 'Are you sure?', 'bulk-actions-manager' ),
					'processing'     => __( 'Processing...', 'bulk-actions-manager' ),
					'error'          => __( 'An error occurred.', 'bulk-actions-manager' ),
					'completed'      => __( 'Completed', 'bulk-actions-manager' ),
					'paused'         => __( 'Paused', 'bulk-actions-manager' ),
					'cancelled'      => __( 'Cancelled', 'bulk-actions-manager' ),
					'noValue'        => __( 'No value needed', 'bulk-actions-manager' ),
					'downloadExport' => __( 'Download Export', 'bulk-actions-manager' ),
				),
			)
		);

		if ( in_array( $page, array( 'bam-dashboard', 'bam-new-job', 'bam-jobs', 'bam-tools' ), true ) ) {
			wp_enqueue_script( 'postbox' );
			wp_add_inline_script(
				'postbox',
				'jQuery(function($){if(typeof postboxes!=="undefined"){postboxes.add_postbox_toggles(' . wp_json_encode( $hook ) . ');}});'
			);
		}

		if ( 'bam-new-job' === $page ) {
			wp_enqueue_script( 'common' );
			wp_enqueue_script( 'list' );

			wp_enqueue_script(
				'bam-new-job-actions',
				BAM_PLUGIN_URL . 'assets/js/new-job-actions.js',
				array( 'bam-admin-common' ),
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

		if ( 'bam-jobs' === $page && isset( $_GET['job_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_script(
				'bam-job-runner',
				BAM_PLUGIN_URL . 'assets/js/job-runner.js',
				array( 'bam-admin-common' ),
				BAM_VERSION,
				true
			);
		}
	}
}
