<?php
/**
 * Jobs admin page (runs + schedules).
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Admin\Admin_UI;
use BAM\Admin\List_Tables\Jobs_List_Table;
use BAM\Cron\Schedule_Runner;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Utils\Capabilities;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Jobs
 */
class Page_Jobs extends Page_Base {

	/**
	 * Register early admin request handlers (before headers are sent).
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_process_admin_requests' ) );
	}

	/**
	 * Process single-job or bulk actions before page output.
	 */
	public static function maybe_process_admin_requests() {
		if ( ! self::is_jobs_page_request() || ! Capabilities::current_user_can() ) {
			return;
		}

		if ( self::has_single_job_action_request() ) {
			self::handle_actions();
			return;
		}

		if ( self::has_bulk_action_request() ) {
			$table = new Jobs_List_Table();
			$table->prime_status_view_from_request();
			$table->process_bulk_action();
		}
	}

	/**
	 * Whether the current request targets the jobs admin page.
	 *
	 * @return bool
	 */
	private static function is_jobs_page_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return 'bam-jobs' === $page;
	}

	/**
	 * Whether the request is a single job or schedule action.
	 *
	 * @return bool
	 */
	private static function has_single_job_action_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['bam_action'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( wp_unslash( $_GET['bam_action'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$schedule_id = isset( $_GET['schedule_id'] ) ? absint( $_GET['schedule_id'] ) : 0;

		$job_actions      = array( 'cancel_job', 'pause_job', 'resume_job', 'delete_job' );
		$schedule_actions = array( 'delete_schedule', 'run_schedule' );

		if ( $job_id && in_array( $action, $job_actions, true ) ) {
			return true;
		}

		if ( $schedule_id && in_array( $action, $schedule_actions, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the request is a jobs list bulk action.
	 *
	 * @return bool
	 */
	private static function has_bulk_action_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['bam_action'] ) ) {
			return false;
		}

		$bulk_actions = array( 'pause', 'resume', 'cancel', 'delete' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action']
			? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
			: '';

		if ( ! $action && isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( ! in_array( $action, $bulk_actions, true ) ) {
			return false;
		}

		$ids = isset( $_REQUEST['item'] ) ? (array) wp_unslash( $_REQUEST['item'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $ids );
	}

	/**
	 * Render jobs page.
	 */
	public static function render() {
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $job_id ) {
			self::render_detail( $job_id );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type        = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'run';
		$is_schedule = ( 'schedule' === $type );

		self::header( __( 'Jobs', 'bulk-actions-manager' ) );

		self::render_notices( $is_schedule );

		if ( isset( $_GET['cancelled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job cancelled.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule deleted.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule triggered.', 'bulk-actions-manager' ) . '</p></div>';
		}

		// Schedules are created/edited on the New Job page - show a button linking there.
		if ( $is_schedule ) {
			?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-new-job' ) ); ?>">
					<?php esc_html_e( 'Add Schedule', 'bulk-actions-manager' ); ?>
				</a>
			</p>
			<?php
		}

		$list_table = new Jobs_List_Table();
		$list_table->prepare_items();
		$search_label = $is_schedule
			? __( 'Search Schedules', 'bulk-actions-manager' )
			: __( 'Search Jobs', 'bulk-actions-manager' );
		?>
		<form method="get" id="bam-jobs-list-form">
			<input type="hidden" name="page" value="bam-jobs" />
			<?php if ( $is_schedule ) : ?>
				<input type="hidden" name="type" value="schedule" />
			<?php endif; ?>
			<?php
			$list_table->views();
			$list_table->search_box( $search_label, 'bam-job-search' );
			$list_table->display();
			?>
		</form>
		<?php
		self::footer();
	}

	/**
	 * Handle admin GET actions.
	 */
	private static function handle_actions() {
		if ( ! Capabilities::current_user_can() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['bam_action'] ) ? sanitize_key( wp_unslash( $_GET['bam_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;

		if ( 'cancel_job' === $action && $job_id ) {
			check_admin_referer( 'bam_cancel_job_' . $job_id );
			( new Job_Manager() )->cancel( $job_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&job_id=' . $job_id . '&cancelled=1' ) );
			exit;
		}

		if ( 'pause_job' === $action && $job_id ) {
			check_admin_referer( 'bam_pause_job_' . $job_id );
			( new Job_Manager() )->pause( $job_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&job_id=' . $job_id . '&paused=1' ) );
			exit;
		}

		if ( 'resume_job' === $action && $job_id ) {
			check_admin_referer( 'bam_resume_job_' . $job_id );
			$result = ( new Job_Manager() )->resume( $job_id );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&bam_job_error=1' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&job_id=' . $job_id . '&resumed=1' ) );
			exit;
		}

		if ( 'delete_job' === $action && $job_id ) {
			check_admin_referer( 'bam_delete_job_' . $job_id );
			$result = ( new Job_Manager() )->delete_job( $job_id );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&bam_job_error=1' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&job_deleted=1' ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$schedule_id = isset( $_GET['schedule_id'] ) ? absint( $_GET['schedule_id'] ) : 0;

		if ( ! $action || ! $schedule_id ) {
			return;
		}

		if ( 'delete_schedule' === $action ) {
			check_admin_referer( 'bam_delete_schedule_' . $schedule_id );
			Schedule_Repository::delete( $schedule_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&type=schedule&deleted=1' ) );
			exit;
		}

		if ( 'run_schedule' === $action ) {
			check_admin_referer( 'bam_run_schedule_' . $schedule_id );
			self::run_schedule( $schedule_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&type=schedule&ran=1' ) );
			exit;
		}
	}

	/**
	 * Render admin notices for list actions.
	 *
	 * @param bool $is_schedule Whether the schedule tab is active.
	 */
	private static function render_notices( $is_schedule ) {
		if ( $is_schedule ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['bam_bulk'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$updated = isset( $_GET['bam_updated'] ) ? absint( $_GET['bam_updated'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skipped = isset( $_GET['bam_skipped'] ) ? absint( $_GET['bam_skipped'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$bulk_action = isset( $_GET['bam_bulk_action'] ) ? sanitize_key( wp_unslash( $_GET['bam_bulk_action'] ) ) : '';

			if ( $updated > 0 ) {
				$labels = array(
					'pause'  => _n( '%d job paused.', '%d jobs paused.', $updated, 'bulk-actions-manager' ),
					'resume' => _n( '%d job resumed.', '%d jobs resumed.', $updated, 'bulk-actions-manager' ),
					'cancel' => _n( '%d job cancelled.', '%d jobs cancelled.', $updated, 'bulk-actions-manager' ),
					'delete' => _n( '%d job deleted.', '%d jobs deleted.', $updated, 'bulk-actions-manager' ),
				);
				$message = isset( $labels[ $bulk_action ] )
					? sprintf( $labels[ $bulk_action ], $updated )
					: sprintf(
						/* translators: %d: number of jobs updated */
						_n( '%d job updated.', '%d jobs updated.', $updated, 'bulk-actions-manager' ),
						$updated
					);
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}

			if ( $skipped > 0 ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of jobs skipped */
						_n( '%d job was skipped because the action does not apply to its current status.', '%d jobs were skipped because the action does not apply to their current status.', $skipped, 'bulk-actions-manager' ),
						$skipped
					)
				) . '</p></div>';
			}

			if ( 0 === $updated && 0 === $skipped ) {
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'No jobs were updated.', 'bulk-actions-manager' ) . '</p></div>';
			}

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['job_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job deleted.', 'bulk-actions-manager' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['resumed'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job resumed.', 'bulk-actions-manager' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['bam_job_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'That job action could not be completed.', 'bulk-actions-manager' ) . '</p></div>';
		}
	}

	/**
	 * Run a single schedule now.
	 *
	 * @param int $schedule_id Schedule ID.
	 */
	private static function run_schedule( $schedule_id ) {
		$schedule = Schedule_Repository::find( $schedule_id );
		if ( ! $schedule ) {
			return;
		}

		$manager = new Job_Manager();
		$result  = $manager->create(
			array(
				'name'            => $schedule->name,
				'filter'          => Sanitizer::json_decode( $schedule->filter_payload ),
				'action_type'     => $schedule->action_type,
				'action_payload'  => Sanitizer::json_decode( $schedule->action_payload ),
				'processing_mode' => 'background',
			)
		);

		$update = array(
			'last_run_at' => current_time( 'mysql' ),
			'next_run_at' => Schedule_Runner::calculate_next_run( $schedule->cron_expression ),
		);

		if ( ! is_wp_error( $result ) && ! empty( $result['job_id'] ) ) {
			$update['last_job_id'] = (int) $result['job_id'];
		}

		Schedule_Repository::update( $schedule_id, $update );
	}

	/**
	 * Render job detail view with debug info, edit/clone controls.
	 *
	 * @param int $job_id Job ID.
	 */
	private static function render_detail( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job ) {
			self::header( __( 'Job Not Found', 'bulk-actions-manager' ) );
			echo '<p>' . esc_html__( 'Job not found.', 'bulk-actions-manager' ) . '</p>';
			self::footer();
			return;
		}

		$formatted = Job_Manager::format_job( $job );

		self::header(
			sprintf(
				/* translators: %d: job ID */
				__( 'Job #%d', 'bulk-actions-manager' ),
				$job_id
			)
		);

		if ( isset( $_GET['resumed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job resumed.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['paused'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job paused.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['cancelled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job cancelled.', 'bulk-actions-manager' ) . '</p></div>';
		}
		?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs' ) ); ?>">&larr; <?php esc_html_e( 'Back to Jobs', 'bulk-actions-manager' ); ?></a>
			<?php if ( in_array( $job->status, array( 'queued', 'paused' ), true ) ) : ?>
				&nbsp;|&nbsp;
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-new-job&job_id=' . $job_id ) ); ?>">
					<?php esc_html_e( 'Edit Job', 'bulk-actions-manager' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( in_array( $job->status, array( 'completed', 'failed', 'cancelled' ), true ) ) : ?>
				&nbsp;|&nbsp;
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-new-job&clone_job_id=' . $job_id ) ); ?>">
					<?php esc_html_e( 'Clone Job', 'bulk-actions-manager' ); ?>
				</a>
			<?php endif; ?>
		</p>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Name', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->name ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'bulk-actions-manager' ); ?></th>
					<td><span id="bam-job-status-badge"><?php echo Admin_UI::status_badge( $job->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( Admin_UI::action_label( $job->action_type ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->processing_mode ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Progress', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $formatted['processed_items'] . ' / ' . $formatted['total_items'] . ' (' . $formatted['percent'] . '%)' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Failed', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $formatted['failed_items'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Created', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->created_at ?: '-' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Finished', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->finished_at ?: '-' ); ?></td>
				</tr>
				<tr id="bam-job-last-error-row"<?php echo empty( $job->error_message ) ? ' style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Last Error', 'bulk-actions-manager' ); ?></th>
					<td><span id="bam-job-last-error-text" class="bam-error-message"><?php echo esc_html( $job->error_message ?: '' ); ?></span></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Undo Available', 'bulk-actions-manager' ); ?></th>
					<td><?php echo $formatted['undo_available'] ? esc_html__( 'Yes', 'bulk-actions-manager' ) : esc_html__( 'No', 'bulk-actions-manager' ); ?></td>
				</tr>
				<?php if ( ! empty( $formatted['export_download_url'] ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Export', 'bulk-actions-manager' ); ?></th>
					<td><a class="button" href="<?php echo esc_url( $formatted['export_download_url'] ); ?>"><?php esc_html_e( 'Download Export', 'bulk-actions-manager' ); ?></a></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( in_array( $job->status, array( 'running', 'queued', 'paused' ), true ) ) : ?>
		<?php
		$pause_url = wp_nonce_url(
			admin_url( 'admin.php?page=bam-jobs&bam_action=pause_job&job_id=' . $job_id ),
			'bam_pause_job_' . $job_id
		);
		$resume_url = wp_nonce_url(
			admin_url( 'admin.php?page=bam-jobs&bam_action=resume_job&job_id=' . $job_id ),
			'bam_resume_job_' . $job_id
		);
		$cancel_url = wp_nonce_url(
			admin_url( 'admin.php?page=bam-jobs&bam_action=cancel_job&job_id=' . $job_id ),
			'bam_cancel_job_' . $job_id
		);
		?>
		<p id="bam-job-controls">
			<a id="bam-control-pause" class="button" href="<?php echo esc_url( $pause_url ); ?>"<?php echo 'running' !== $job->status ? ' style="display:none;"' : ''; ?>><?php esc_html_e( 'Pause', 'bulk-actions-manager' ); ?></a>
			<a id="bam-control-resume" class="button" href="<?php echo esc_url( $resume_url ); ?>"<?php echo 'paused' !== $job->status ? ' style="display:none;"' : ''; ?>><?php esc_html_e( 'Resume', 'bulk-actions-manager' ); ?></a>
			<a id="bam-control-cancel" class="button button-link-delete" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'bulk-actions-manager' ); ?></a>
		</p>

		<div id="bam-job-detail" class="metabox-holder" data-job-id="<?php echo esc_attr( (string) $job_id ); ?>">
			<?php Admin_UI::postbox_open( 'bam-job-progress-detail', __( 'Job Progress', 'bulk-actions-manager' ) ); ?>
			<div class="bam-job-progress">
				<progress id="bam-progress-bar" max="100" value="<?php echo esc_attr( (string) $formatted['percent'] ); ?>"></progress>
				<p id="bam-progress-text" class="description"><?php echo esc_html( (string) $formatted['percent'] . '% - ' . $job->status ); ?></p>
				<p id="bam-progress-stats" class="description"></p>
				<div id="bam-job-skipped" class="bam-job-skipped-list"></div>
				<div id="bam-job-errors" class="bam-job-errors-list"></div>
			</div>
			<?php Admin_UI::postbox_close(); ?>
		</div>
		<?php endif; ?>
		<?php
		self::footer();
	}
}
