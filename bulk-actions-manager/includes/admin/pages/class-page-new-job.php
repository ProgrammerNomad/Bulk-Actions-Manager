<?php
/**
 * New Job admin page - single workflow editor for jobs and schedules.
 *
 * URL modes:
 * ?page=bam-new-job                  → new job
 * ?page=bam-new-job&schedule_id={n}  → edit existing schedule
 * ?page=bam-new-job&job_id={n}       → edit queued/paused job
 * ?page=bam-new-job&clone_job_id={n} → clone job (prefilled new)
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Actions\Action_Registry;
use BAM\Admin\Admin_UI;
use BAM\Admin\List_Tables\Filter_Bar;
use BAM\Admin\List_Tables\Posts_Preview_List_Table;
use BAM\Admin\Preview_Summary;
use BAM\Cron\Schedule_Runner;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Filters\Filter_Compiler;
use BAM\Jobs\Job_Manager;
use BAM\Settings;
use BAM\Utils\Capabilities;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_New_Job
 */
class Page_New_Job extends Page_Base {

	/**
	 * Render new job / schedule editor page.
	 */
	public static function render() {
		// Handle schedule form POST save (from this page).
		if ( isset( $_POST['bam_save_schedule'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::save_schedule();
			return;
		}

		$settings = Settings::all();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request = wp_unslash( $_GET );
		$request = is_array( $request ) ? $request : array();

		$schedule_id  = ! empty( $request['schedule_id'] ) ? absint( $request['schedule_id'] ) : 0;
		$job_id       = ! empty( $request['job_id'] ) ? absint( $request['job_id'] ) : 0;
		$clone_job_id = ! empty( $request['clone_job_id'] ) ? absint( $request['clone_job_id'] ) : 0;

		$existing_schedule = $schedule_id ? Schedule_Repository::find( $schedule_id ) : null;
		$existing_job      = $job_id ? Job_Repository::find( $job_id ) : null;
		$clone_source      = $clone_job_id ? Job_Repository::find( $clone_job_id ) : null;

		// Redirect if IDs are invalid or job is not editable.
		if ( $schedule_id && ! $existing_schedule ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bam-new-job' ) );
			exit;
		}

		if ( $job_id && ( ! $existing_job || ! in_array( $existing_job->status, array( 'queued', 'paused' ), true ) ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&job_id=' . $job_id ) );
			exit;
		}

		$is_edit_schedule = $existing_schedule !== null;
		$is_edit_job      = $existing_job !== null;
		$is_clone         = $clone_source !== null;

		// Determine pre-fill source.
		$prefill = null;
		if ( $is_edit_schedule ) {
			$prefill = array(
				'filter'         => Sanitizer::json_decode( $existing_schedule->filter_payload ),
				'action_type'    => $existing_schedule->action_type,
				'action_payload' => Sanitizer::json_decode( $existing_schedule->action_payload ),
			);
		} elseif ( $is_edit_job ) {
			$prefill = array(
				'filter'         => Sanitizer::json_decode( $existing_job->filter_payload ),
				'action_type'    => $existing_job->action_type,
				'action_payload' => Sanitizer::json_decode( $existing_job->action_payload ),
				'name'           => $existing_job->name,
				'batch_size'     => (int) $existing_job->batch_size,
				'processing_mode' => $existing_job->processing_mode,
			);
		} elseif ( $is_clone ) {
			$prefill = array(
				'filter'         => Sanitizer::json_decode( $clone_source->filter_payload ),
				'action_type'    => $clone_source->action_type,
				'action_payload' => Sanitizer::json_decode( $clone_source->action_payload ),
				'name'           => sprintf(
					/* translators: %s: original job name */
					__( 'Clone of %s', 'bulk-actions-manager' ),
					$clone_source->name
				),
				'batch_size'     => (int) $clone_source->batch_size,
				'processing_mode' => $clone_source->processing_mode,
			);
		}

		$payload  = $prefill ? $prefill['filter'] : Filter_Bar::parse_request_to_payload( $request );
		$per_page = 20;
		$paged    = isset( $request['paged'] ) ? max( 1, absint( $request['paged'] ) ) : 1;
		$result   = Filter_Compiler::query_page( $payload, $paged, $per_page );
		$total    = $result['total'];
		$page_ids = $result['ids'];
		$summary  = Preview_Summary::build( $payload, $total );

		// Has processing already begun? (only relevant for edit-job mode)
		$has_progress  = $is_edit_job && (int) $existing_job->processed_items > 0;
		$limited_edit  = $has_progress;

		$page_title = __( 'New Job', 'bulk-actions-manager' );
		if ( $is_edit_schedule ) {
			$page_title = __( 'Edit Schedule', 'bulk-actions-manager' );
		} elseif ( $is_edit_job ) {
			$page_title = sprintf(
				/* translators: %d: job ID */
				__( 'Edit Job #%d', 'bulk-actions-manager' ),
				$job_id
			);
		} elseif ( $is_clone ) {
			$page_title = __( 'Clone Job', 'bulk-actions-manager' );
		}

		self::header( $page_title );
		?>
		<?php if ( isset( $request['schedule_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Schedule saved.', 'bulk-actions-manager' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $limited_edit ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'This job has already started processing. Only the name, batch size, and processing mode can be changed.', 'bulk-actions-manager' ); ?></p>
			</div>
		<?php endif; ?>

		<div id="bam-new-job" class="metabox-holder">
			<?php if ( ! $limited_edit ) : ?>
			<?php Admin_UI::postbox_open( 'bam-step-filter', __( 'Step 1: Filter Content', 'bulk-actions-manager' ) ); ?>
			<form method="get" id="posts-filter" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="bam-new-job" />
				<?php if ( $schedule_id ) : ?><input type="hidden" name="schedule_id" value="<?php echo esc_attr( (string) $schedule_id ); ?>" /><?php endif; ?>
				<?php if ( $job_id ) : ?><input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $job_id ); ?>" /><?php endif; ?>
				<?php if ( $clone_job_id ) : ?><input type="hidden" name="clone_job_id" value="<?php echo esc_attr( (string) $clone_job_id ); ?>" /><?php endif; ?>
				<?php if ( ! empty( $request['post_status'] ) ) : ?>
					<input type="hidden" name="post_status" value="<?php echo esc_attr( sanitize_key( $request['post_status'] ) ); ?>" />
				<?php else : ?>
					<input type="hidden" name="post_status" value="all" />
				<?php endif; ?>
				<?php Filter_Bar::render( $request ); ?>
			</form>
			<?php Preview_Summary::render_count_notice( $total ); ?>
			<?php Admin_UI::postbox_close(); ?>

			<?php Admin_UI::postbox_open( 'bam-step-preview', __( 'Step 2: Preview Results', 'bulk-actions-manager' ) ); ?>
			<?php Preview_Summary::render( $summary ); ?>
			<?php if ( $total > 0 ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: shown count, 2: total count */
							__( 'Showing %1$s of %2$s records', 'bulk-actions-manager' ),
							number_format_i18n( min( $per_page, count( $page_ids ) ) ),
							number_format_i18n( $total )
						)
					);
					?>
				</p>
				<?php
				$preview_table = new Posts_Preview_List_Table();
				$preview_table->set_preview_data( $page_ids, $total, $paged );
				$preview_table->display();
				?>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No posts match the current filters.', 'bulk-actions-manager' ); ?></p>
			<?php endif; ?>
			<?php Admin_UI::postbox_close(); ?>

			<?php Admin_UI::postbox_open( 'bam-step-action', __( 'Step 3: Select Action', 'bulk-actions-manager' ) ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bam-action-type"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select id="bam-action-type" name="action_type">
							<option value=""><?php esc_html_e( 'Select an action', 'bulk-actions-manager' ); ?></option>
							<?php self::render_action_options( $prefill ? ( $prefill['action_type'] ?? '' ) : '' ); ?>
						</select>
					</td>
				</tr>
			</table>
			<div id="bam-action-description" class="bam-action-description"></div>
			<table class="form-table" role="presentation" id="bam-action-fields"></table>
			<?php Admin_UI::postbox_close(); ?>
			<?php endif; // ! $limited_edit ?>

			<?php Admin_UI::postbox_open( 'bam-step-run', __( 'Step 4: Execute', 'bulk-actions-manager' ) ); ?>
			<table class="form-table" role="presentation">
				<?php if ( ! $limited_edit && ! $is_edit_schedule ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dry Run', 'bulk-actions-manager' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="bam-dry-run" name="is_dry_run" value="1" />
							<?php esc_html_e( 'Dry run (no changes will be made)', 'bulk-actions-manager' ); ?>
						</label>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><label for="bam-batch-size"><?php esc_html_e( 'Batch Size', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select id="bam-batch-size" name="batch_size">
							<?php
							$current_batch = $prefill['batch_size'] ?? (int) $settings['default_batch_size'];
							foreach ( array( 10, 25, 50, 100 ) as $size ) :
								?>
								<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $current_batch, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-processing-mode"><?php esc_html_e( 'Processing Mode', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select id="bam-processing-mode" name="processing_mode">
							<?php $current_mode = $prefill['processing_mode'] ?? $settings['default_processing_mode']; ?>
							<option value="ajax" <?php selected( $current_mode, 'ajax' ); ?>><?php esc_html_e( 'AJAX (Recommended)', 'bulk-actions-manager' ); ?></option>
							<option value="background" <?php selected( $current_mode, 'background' ); ?>><?php esc_html_e( 'Background Queue', 'bulk-actions-manager' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-job-name"><?php esc_html_e( 'Job Name', 'bulk-actions-manager' ); ?></label></th>
					<td><input type="text" id="bam-job-name" name="job_name" class="regular-text" value="<?php echo esc_attr( $prefill['name'] ?? '' ); ?>" /></td>
				</tr>
			</table>

			<?php if ( ! $is_edit_schedule ) : ?>
			<p class="submit">
				<?php if ( ! $limited_edit ) : ?>
				<button type="button" class="button button-secondary" id="bam-preview-job" disabled><?php esc_html_e( 'Preview Job', 'bulk-actions-manager' ); ?></button>
				<?php endif; ?>
				<?php if ( $is_edit_job ) : ?>
				<button type="button" class="button button-primary" id="bam-update-job"
					data-job-id="<?php echo esc_attr( (string) $job_id ); ?>"
					data-has-progress="<?php echo esc_attr( $limited_edit ? '1' : '0' ); ?>">
					<?php esc_html_e( 'Update Job', 'bulk-actions-manager' ); ?>
				</button>
				<?php else : ?>
				<button type="button" class="button button-primary" id="bam-start-job" disabled><?php esc_html_e( 'Start Job', 'bulk-actions-manager' ); ?></button>
				<?php endif; ?>
				<?php if ( $clone_job_id ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs&job_id=' . $clone_job_id ) ); ?>"><?php esc_html_e( 'View Original Job', 'bulk-actions-manager' ); ?></a>
				<?php endif; ?>
			</p>
			<?php endif; // ! $is_edit_schedule ?>

			<?php if ( ! $limited_edit && ! $is_edit_job ) : ?>
			<hr />
			<p>
				<label>
					<input type="checkbox" id="bam-save-as-schedule" value="1" aria-controls="bam-schedule-panel" aria-expanded="false" />
					<?php esc_html_e( 'Save as recurring schedule', 'bulk-actions-manager' ); ?>
				</label>
			</p>
			<div id="bam-schedule-panel" class="bam-hidden">
			<p class="description"><?php esc_html_e( 'Instead of running now, save this as a recurring scheduled job.', 'bulk-actions-manager' ); ?></p>
			<form method="post" id="bam-schedule-form">
				<?php wp_nonce_field( 'bam_save_schedule' ); ?>
				<input type="hidden" name="bam_save_schedule" value="1" />
				<input type="hidden" name="page" value="bam-new-job" />
				<input type="hidden" name="schedule_id" value="<?php echo esc_attr( (string) $schedule_id ); ?>" />
				<input type="hidden" id="bam-sched-filter-payload" name="schedule_filter_payload" value="" />
				<input type="hidden" id="bam-sched-action-type" name="schedule_action_type" value="" />
				<input type="hidden" id="bam-sched-action-payload" name="schedule_action_payload" value="" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="schedule_name"><?php esc_html_e( 'Schedule Name', 'bulk-actions-manager' ); ?></label></th>
						<td><input type="text" name="schedule_name" id="schedule_name" class="regular-text" value="<?php echo $is_edit_schedule && $existing_schedule ? esc_attr( $existing_schedule->name ) : ''; ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cron_expression"><?php esc_html_e( 'Frequency', 'bulk-actions-manager' ); ?></label></th>
						<td>
							<select name="cron_expression" id="cron_expression">
								<?php
								$cron = $is_edit_schedule && $existing_schedule ? $existing_schedule->cron_expression : 'daily';
								foreach ( array( 'hourly', 'daily', 'weekly', 'monthly' ) as $freq ) :
									?>
									<option value="<?php echo esc_attr( $freq ); ?>" <?php selected( $cron, $freq ); ?>><?php echo esc_html( ucfirst( $freq ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Active', 'bulk-actions-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( ! $is_edit_schedule || ! $existing_schedule || (int) $existing_schedule->is_active ); ?> />
								<?php esc_html_e( 'Run this schedule automatically', 'bulk-actions-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary" id="bam-save-schedule"><?php esc_html_e( 'Save Schedule', 'bulk-actions-manager' ); ?></button>
				</p>
			</form>
			</div>
			<?php elseif ( $is_edit_schedule && $existing_schedule ) : ?>
			<hr />
			<h3><?php esc_html_e( 'Schedule Settings', 'bulk-actions-manager' ); ?></h3>
			<form method="post" id="bam-schedule-form">
				<?php wp_nonce_field( 'bam_save_schedule' ); ?>
				<input type="hidden" name="bam_save_schedule" value="1" />
				<input type="hidden" name="page" value="bam-new-job" />
				<input type="hidden" name="schedule_id" value="<?php echo esc_attr( (string) $schedule_id ); ?>" />
				<input type="hidden" id="bam-sched-filter-payload" name="schedule_filter_payload" value="" />
				<input type="hidden" id="bam-sched-action-type" name="schedule_action_type" value="" />
				<input type="hidden" id="bam-sched-action-payload" name="schedule_action_payload" value="" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="schedule_name"><?php esc_html_e( 'Schedule Name', 'bulk-actions-manager' ); ?></label></th>
						<td><input type="text" name="schedule_name" id="schedule_name" class="regular-text" value="<?php echo esc_attr( $existing_schedule->name ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cron_expression"><?php esc_html_e( 'Frequency', 'bulk-actions-manager' ); ?></label></th>
						<td>
							<select name="cron_expression" id="cron_expression">
								<?php
								$cron = $existing_schedule->cron_expression;
								foreach ( array( 'hourly', 'daily', 'weekly', 'monthly' ) as $freq ) :
									?>
									<option value="<?php echo esc_attr( $freq ); ?>" <?php selected( $cron, $freq ); ?>><?php echo esc_html( ucfirst( $freq ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Active', 'bulk-actions-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( (int) $existing_schedule->is_active ); ?> />
								<?php esc_html_e( 'Run this schedule automatically', 'bulk-actions-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<p>
					<?php submit_button( __( 'Save Schedule', 'bulk-actions-manager' ), 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs&type=schedule' ) ); ?>"><?php esc_html_e( 'Cancel', 'bulk-actions-manager' ); ?></a>
				</p>
			</form>
			<?php endif; ?>

			<div id="bam-dry-run-notice" class="bam-hidden notice notice-success inline"><p></p></div>
			<div id="bam-background-notice" class="bam-hidden notice notice-info inline">
				<p>
					<?php esc_html_e( 'Job queued for background processing.', 'bulk-actions-manager' ); ?>
					<a class="bam-background-notice__link" href="#"><?php esc_html_e( 'View job progress', 'bulk-actions-manager' ); ?></a>
				</p>
			</div>
			<?php Admin_UI::postbox_close(); ?>

			<div id="bam-job-progress" class="bam-hidden metabox-holder">
				<?php Admin_UI::postbox_open( 'bam-job-progress-box', __( 'Job Progress', 'bulk-actions-manager' ) ); ?>
				<div class="bam-job-progress">
					<progress id="bam-progress-bar" max="100" value="0"></progress>
					<p id="bam-progress-text" class="description">0%</p>
					<p id="bam-progress-stats" class="description"></p>
					<p>
						<button type="button" class="button button-secondary" id="bam-pause-job"><?php esc_html_e( 'Pause', 'bulk-actions-manager' ); ?></button>
						<button type="button" class="button button-secondary" id="bam-resume-job"><?php esc_html_e( 'Resume', 'bulk-actions-manager' ); ?></button>
						<button type="button" class="button button-link-delete" id="bam-cancel-job"><?php esc_html_e( 'Cancel', 'bulk-actions-manager' ); ?></button>
					</p>
					<div id="bam-job-errors"></div>
				</div>
				<?php Admin_UI::postbox_close(); ?>
			</div>
		</div>

		<script type="application/json" id="bam-filter-payload"><?php echo wp_json_encode( $payload ); ?></script>
		<script type="application/json" id="bam-prefill-data"><?php echo wp_json_encode( $prefill ); ?></script>
		<script>
		(function() {
			var prefill = <?php echo wp_json_encode( $prefill ); ?>;
			var isEditSchedule = <?php echo $is_edit_schedule ? 'true' : 'false'; ?>;
			var isEditJob = <?php echo $is_edit_job ? 'true' : 'false'; ?>;
			var isClone = <?php echo $is_clone ? 'true' : 'false'; ?>;
			var hasProgress = <?php echo $limited_edit ? 'true' : 'false'; ?>;

			document.addEventListener('DOMContentLoaded', function() {
				// Prefill schedule form fields when submitting.
				var schedForm = document.getElementById('bam-schedule-form');
				if (schedForm) {
					schedForm.addEventListener('submit', function() {
						var filterEl = document.getElementById('bam-sched-filter-payload');
						var actionEl = document.getElementById('bam-sched-action-type');
						var payloadEl = document.getElementById('bam-sched-action-payload');
						var filterData = document.getElementById('bam-filter-payload');
						var actionSelect = document.getElementById('bam-action-type');
						if (filterEl && filterData) filterEl.value = filterData.textContent;
						if (actionEl && actionSelect) actionEl.value = actionSelect ? actionSelect.value : '';
						if (payloadEl) payloadEl.value = JSON.stringify(typeof buildPayload === 'function' && actionSelect ? buildPayload(actionSelect.value) : {});
					});
				}

				// Attach "Update Job" handler for edit-job mode.
				var updateBtn = document.getElementById('bam-update-job');
				if (updateBtn) {
					updateBtn.addEventListener('click', function() {
						var jobId = updateBtn.getAttribute('data-job-id');
						var hasProgressFlag = updateBtn.getAttribute('data-has-progress') === '1';
						var payload = {};
						payload.name = (document.getElementById('bam-job-name') || {}).value || '';
						payload.batch_size = parseInt((document.getElementById('bam-batch-size') || {}).value, 10);
						payload.processing_mode = (document.getElementById('bam-processing-mode') || {}).value || 'ajax';

						if (!hasProgressFlag) {
							var actionSelect = document.getElementById('bam-action-type');
							if (actionSelect) {
								payload.action_type = actionSelect.value;
								if (typeof buildPayload === 'function') {
									payload.action_payload = buildPayload(actionSelect.value);
								}
							}
							var filterData = document.getElementById('bam-filter-payload');
							if (filterData) {
								try { payload.filter = JSON.parse(filterData.textContent); } catch(e) {}
							}
						}

						updateBtn.disabled = true;
						bamApi.put('jobs/' + jobId, payload).then(function() {
							window.location.href = bamAdmin.jobsUrl + '&job_id=' + jobId;
						}).catch(function() {
							bamAlert({ title: bamAdmin.i18n.errorTitle, message: bamAdmin.i18n.error });
							updateBtn.disabled = false;
						});
					});
				}
			});
		})();
		</script>
		<?php
		self::footer();
	}

	/**
	 * Handle schedule save POST (from this page).
	 */
	private static function save_schedule() {
		check_admin_referer( 'bam_save_schedule' );

		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'Permission denied.', 'bulk-actions-manager' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$id          = isset( $_POST['schedule_id'] ) ? absint( $_POST['schedule_id'] ) : 0;
		$name        = isset( $_POST['schedule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_name'] ) ) : '';
		$cron        = isset( $_POST['cron_expression'] ) ? sanitize_key( wp_unslash( $_POST['cron_expression'] ) ) : 'daily';
		$is_active   = ! empty( $_POST['is_active'] ) ? 1 : 0;

		// Filter and action come from hidden fields (populated by JS on submit).
		$filter_raw     = isset( $_POST['schedule_filter_payload'] ) ? wp_unslash( $_POST['schedule_filter_payload'] ) : '{}';
		$action_type    = isset( $_POST['schedule_action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_action_type'] ) ) : '';
		$action_payload = isset( $_POST['schedule_action_payload'] ) ? wp_unslash( $_POST['schedule_action_payload'] ) : '{}';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$filter         = json_decode( $filter_raw, true ) ?: array();
		$action_payload = json_decode( $action_payload, true ) ?: array();

		$allowed_cron = array( 'hourly', 'daily', 'weekly', 'monthly' );
		if ( ! in_array( $cron, $allowed_cron, true ) ) {
			$cron = 'daily';
		}

		$data = array(
			'name'            => $name,
			'cron_expression' => $cron,
			'is_active'       => $is_active,
			'action_type'     => $action_type,
			'action_payload'  => $action_payload,
			'filter_payload'  => $filter,
		);

		if ( $id ) {
			Schedule_Repository::update( $id, $data );
		} else {
			$data['next_run_at'] = Schedule_Runner::calculate_next_run( $cron );
			$id = Schedule_Repository::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bam-new-job&schedule_id=' . (int) $id . '&schedule_saved=1' ) );
		exit;
	}

	/**
	 * Render action select options, optionally marking a pre-selected value.
	 *
	 * @param string $selected_action Pre-selected action type.
	 */
	private static function render_action_options( $selected_action = '' ) {
		$registry = new Action_Registry();
		foreach ( $registry->get_grouped() as $group => $actions ) {
			echo '<optgroup label="' . esc_attr( $group ) . '">';
			foreach ( $actions as $action ) {
				printf(
					'<option value="%1$s" data-safety="%2$s" data-undo="%3$s" data-description="%4$s"%5$s>%6$s</option>',
					esc_attr( $action['id'] ),
					esc_attr( $action['safety_level'] ),
					esc_attr( $action['supports_undo'] ? '1' : '0' ),
					esc_attr( $action['description'] ),
					$selected_action === $action['id'] ? ' selected' : '',
					esc_html( $action['label'] )
				);
			}
			echo '</optgroup>';
		}
	}
}
