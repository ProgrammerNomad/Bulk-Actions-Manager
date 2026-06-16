<?php
/**
 * New Job admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_New_Job
 */
class Page_New_Job extends Page_Base {

	/**
	 * Render new job page.
	 */
	public static function render() {
		$settings = Settings::all();
		self::header( __( 'New Job', 'bulk-actions-manager' ) );
		?>
		<div id="bam-new-job" class="bam-new-job">
			<section class="bam-panel" id="bam-filter-builder">
				<h2><?php esc_html_e( 'Filter Builder', 'bulk-actions-manager' ); ?></h2>
				<div class="bam-panel__body">
					<div class="bam-field">
						<label for="bam-post-type"><?php esc_html_e( 'Content Type', 'bulk-actions-manager' ); ?></label>
						<select id="bam-post-type" name="post_type"></select>
					</div>
					<div id="bam-conditions"></div>
					<button type="button" class="button" id="bam-add-condition"><?php esc_html_e( 'Add Condition', 'bulk-actions-manager' ); ?></button>
				</div>
			</section>

			<section class="bam-panel" id="bam-preview-panel">
				<h2><?php esc_html_e( 'Results Preview', 'bulk-actions-manager' ); ?></h2>
				<div class="bam-panel__body">
					<div class="bam-preview-toolbar">
						<span id="bam-preview-count"><?php esc_html_e( 'No preview yet.', 'bulk-actions-manager' ); ?></span>
						<button type="button" class="button" id="bam-refresh-preview"><?php esc_html_e( 'Refresh Preview', 'bulk-actions-manager' ); ?></button>
						<button type="button" class="button" id="bam-export-preview"><?php esc_html_e( 'Export Preview', 'bulk-actions-manager' ); ?></button>
					</div>
					<table class="widefat striped" id="bam-preview-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'bulk-actions-manager' ); ?></th>
								<th><?php esc_html_e( 'Title', 'bulk-actions-manager' ); ?></th>
								<th><?php esc_html_e( 'Type', 'bulk-actions-manager' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bulk-actions-manager' ); ?></th>
								<th><?php esc_html_e( 'Author', 'bulk-actions-manager' ); ?></th>
								<th><?php esc_html_e( 'Date', 'bulk-actions-manager' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</section>

			<section class="bam-panel" id="bam-action-selection">
				<h2><?php esc_html_e( 'Action Selection', 'bulk-actions-manager' ); ?></h2>
				<div class="bam-panel__body">
					<div class="bam-field">
						<label for="bam-action-type"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></label>
						<select id="bam-action-type" name="action_type"></select>
						<span id="bam-action-safety" class="bam-badge"></span>
					</div>
					<div id="bam-action-fields"></div>
				</div>
			</section>

			<section class="bam-panel" id="bam-execution-settings">
				<h2><?php esc_html_e( 'Execution Settings', 'bulk-actions-manager' ); ?></h2>
				<div class="bam-panel__body">
					<div class="bam-field">
						<label>
							<input type="checkbox" id="bam-dry-run" name="is_dry_run" value="1" />
							<?php esc_html_e( 'Dry Run (no changes will be made)', 'bulk-actions-manager' ); ?>
						</label>
					</div>
					<div class="bam-field">
						<label for="bam-batch-size"><?php esc_html_e( 'Batch Size', 'bulk-actions-manager' ); ?></label>
						<select id="bam-batch-size" name="batch_size">
							<?php foreach ( array( 10, 25, 50, 100 ) as $size ) : ?>
								<option value="<?php echo esc_attr( $size ); ?>" <?php selected( (int) $settings['default_batch_size'], $size ); ?>>
									<?php echo esc_html( (string) $size ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="bam-field">
						<label for="bam-processing-mode"><?php esc_html_e( 'Processing Mode', 'bulk-actions-manager' ); ?></label>
						<select id="bam-processing-mode" name="processing_mode">
							<option value="ajax" <?php selected( $settings['default_processing_mode'], 'ajax' ); ?>><?php esc_html_e( 'AJAX (Recommended)', 'bulk-actions-manager' ); ?></option>
							<option value="background" <?php selected( $settings['default_processing_mode'], 'background' ); ?>><?php esc_html_e( 'Background Queue', 'bulk-actions-manager' ); ?></option>
						</select>
					</div>
					<div class="bam-field">
						<label for="bam-job-name"><?php esc_html_e( 'Job Name (optional)', 'bulk-actions-manager' ); ?></label>
						<input type="text" id="bam-job-name" name="job_name" class="regular-text" />
					</div>
					<p>
						<button type="button" class="button button-primary button-hero" id="bam-start-job">
							<?php esc_html_e( 'Start Job', 'bulk-actions-manager' ); ?>
						</button>
					</p>
				</div>
			</section>

			<section class="bam-panel bam-panel--hidden" id="bam-job-progress">
				<h2><?php esc_html_e( 'Job Progress', 'bulk-actions-manager' ); ?></h2>
				<div class="bam-panel__body">
					<div class="bam-progress">
						<div class="bam-progress__bar" id="bam-progress-bar" style="width:0%"></div>
					</div>
					<p id="bam-progress-text">0%</p>
					<p id="bam-progress-stats"></p>
					<p>
						<button type="button" class="button" id="bam-pause-job"><?php esc_html_e( 'Pause', 'bulk-actions-manager' ); ?></button>
						<button type="button" class="button" id="bam-resume-job"><?php esc_html_e( 'Resume', 'bulk-actions-manager' ); ?></button>
						<button type="button" class="button" id="bam-cancel-job"><?php esc_html_e( 'Cancel', 'bulk-actions-manager' ); ?></button>
					</p>
					<div id="bam-job-errors"></div>
				</div>
			</section>
		</div>
		<?php
		self::footer();
	}
}
