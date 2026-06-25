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
				'jobsUrl'   => admin_url( 'admin.php?page=bam-jobs' ),
				'i18n'      => array(
					'confirm'              => __( 'Are you sure?', 'bulk-actions-manager' ),
					'confirmTitle'         => __( 'Please confirm', 'bulk-actions-manager' ),
					'confirmOk'            => __( 'Continue', 'bulk-actions-manager' ),
					'confirmCancel'        => __( 'Cancel', 'bulk-actions-manager' ),
					'confirmDeleteTitle'   => __( 'Permanently delete items?', 'bulk-actions-manager' ),
					'confirmDeleteMessage' => __( 'This will permanently delete the matching items. This action cannot be undone.', 'bulk-actions-manager' ),
					'confirmDeleteOk'      => __( 'Delete permanently', 'bulk-actions-manager' ),
					'confirmDeleteJobTitle'   => __( 'Delete selected jobs?', 'bulk-actions-manager' ),
					'confirmDeleteJobMessage' => __( 'Remove the selected job records from the jobs list.', 'bulk-actions-manager' ),
					'confirmDeleteJobDetail'  => __( 'Audit log entries are kept. This cannot be undone.', 'bulk-actions-manager' ),
					'confirmCancelJob'   => __( 'Cancel this job?', 'bulk-actions-manager' ),
					'confirmCancelJobMessage' => __( 'Processing will stop and the job will be marked as cancelled.', 'bulk-actions-manager' ),
					'confirmCancelJobOk'      => __( 'Yes, cancel job', 'bulk-actions-manager' ),
					'confirmUndoLogTitle'     => __( 'Undo this job?', 'bulk-actions-manager' ),
					'confirmUndoLogMessage'   => __( 'A new undo job will be created to reverse the changes from this log entry.', 'bulk-actions-manager' ),
					'confirmUndoLogOk'        => __( 'Undo', 'bulk-actions-manager' ),
					'confirmScheduleDeleteTitle'   => __( 'Delete this schedule?', 'bulk-actions-manager' ),
					'confirmScheduleDeleteMessage' => __( 'This recurring schedule will be removed. Existing job runs are not deleted.', 'bulk-actions-manager' ),
					'confirmScheduleRunTitle'      => __( 'Run this schedule now?', 'bulk-actions-manager' ),
					'confirmScheduleRunMessage'    => __( 'A new background job will be created using this schedule configuration.', 'bulk-actions-manager' ),
					'confirmRunTool'       => __( 'Run this tool?', 'bulk-actions-manager' ),
					'confirmRunToolMessage' => __( 'This maintenance tool may change or remove data on your site.', 'bulk-actions-manager' ),
					'noticeTitle'          => __( 'Notice', 'bulk-actions-manager' ),
					'errorTitle'           => __( 'Error', 'bulk-actions-manager' ),
					'processing'     => __( 'Processing...', 'bulk-actions-manager' ),
					'error'          => __( 'An error occurred.', 'bulk-actions-manager' ),
					'completed'      => __( 'Completed', 'bulk-actions-manager' ),
					'paused'         => __( 'Paused', 'bulk-actions-manager' ),
					'cancelled'      => __( 'Cancelled', 'bulk-actions-manager' ),
					'statusLabels'   => array(
						'queued'    => __( 'Queued', 'bulk-actions-manager' ),
						'running'   => __( 'Running', 'bulk-actions-manager' ),
						'paused'    => __( 'Paused', 'bulk-actions-manager' ),
						'completed' => __( 'Completed', 'bulk-actions-manager' ),
						'failed'    => __( 'Failed', 'bulk-actions-manager' ),
						'cancelled' => __( 'Cancelled', 'bulk-actions-manager' ),
					),
					'noValue'        => __( 'No value needed', 'bulk-actions-manager' ),
					'downloadExport' => __( 'Download Export', 'bulk-actions-manager' ),
					'undoSupported'  => __( 'Undo supported', 'bulk-actions-manager' ),
					'cannotUndo'     => __( 'Cannot be undone', 'bulk-actions-manager' ),
					'recoverable'    => __( 'Recoverable', 'bulk-actions-manager' ),
					'noUndo'              => __( 'Undo not available', 'bulk-actions-manager' ),
					'backgroundQueued'    => __( 'Job queued for background processing.', 'bulk-actions-manager' ),
					'backgroundJobsLink'  => __( 'View job progress', 'bulk-actions-manager' ),
					'handled'             => __( 'Processed', 'bulk-actions-manager' ),
					'errorsHeading'       => __( 'Errors', 'bulk-actions-manager' ),
					'skippedHeading'      => __( 'Skipped', 'bulk-actions-manager' ),
				),
			)
		);

		$needs_postbox = ( 'bam-new-job' === $page ) || ( 'bam-jobs' === $page && isset( $_GET['job_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $needs_postbox ) {
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

		if ( 'bam-jobs' === $page && ! isset( $_GET['job_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_script(
				'bam-jobs-list',
				BAM_PLUGIN_URL . 'assets/js/jobs-list.js',
				array( 'bam-admin-common', 'jquery' ),
				BAM_VERSION,
				true
			);
		}

		if ( 'bam-logs' === $page && ! isset( $_GET['log_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_script(
				'bam-logs-list',
				BAM_PLUGIN_URL . 'assets/js/logs-list.js',
				array( 'bam-admin-common' ),
				BAM_VERSION,
				true
			);
		}

		if ( 'bam-logs' === $page && isset( $_GET['log_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_script(
				'bam-logs-detail',
				BAM_PLUGIN_URL . 'assets/js/logs-list.js',
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
