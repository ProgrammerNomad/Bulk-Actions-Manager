<?php
/**
 * New Job admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Actions\Action_Registry;
use BAM\Admin\List_Tables\Filter_Bar;
use BAM\Admin\List_Tables\Posts_Preview_List_Table;
use BAM\Filters\Filter_Compiler;
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request = wp_unslash( $_GET );
		$request = is_array( $request ) ? $request : array();

		$payload   = Filter_Bar::parse_request_to_payload( $request );
		$per_page  = 20;
		$paged     = isset( $request['paged'] ) ? max( 1, absint( $request['paged'] ) ) : 1;
		$all_ids   = Filter_Compiler::resolve_ids( $payload );
		$total     = count( $all_ids );
		$offset    = ( $paged - 1 ) * $per_page;
		$page_ids  = array_slice( $all_ids, $offset, $per_page );

		self::header( __( 'New Job', 'bulk-actions-manager' ) );
		?>
		<p class="description">
			<?php esc_html_e( '1. Filter posts below (optional) → 2. Choose an action → 3. Click Start Job', 'bulk-actions-manager' ); ?>
		</p>
		<div id="bam-new-job" class="bam-new-job">
			<section class="bam-panel">
				<h2><?php esc_html_e( 'Filter Posts', 'bulk-actions-manager' ); ?></h2>
				<div class="bam-panel__body">
					<form method="get" id="posts-filter">
						<input type="hidden" name="page" value="bam-new-job" />
						<?php if ( ! empty( $request['post_status'] ) ) : ?>
							<input type="hidden" name="post_status" value="<?php echo esc_attr( sanitize_key( $request['post_status'] ) ); ?>" />
						<?php else : ?>
							<input type="hidden" name="post_status" value="all" />
						<?php endif; ?>
						<?php Filter_Bar::render( $request ); ?>

						<?php
						$preview_table = new Posts_Preview_List_Table();
						$preview_table->set_preview_data( $page_ids, $total );
						if ( $total > 0 ) {
							printf(
								'<p class="bam-preview-count">%s</p>',
								esc_html(
									sprintf(
										/* translators: %d: number of posts */
										_n( '%d item', '%d items', $total, 'bulk-actions-manager' ),
										$total
									)
								)
							);
						}
						$preview_table->display();
						?>
					</form>
				</div>
			</section>

			<section class="bam-panel" id="bam-action-selection">
				<h2><?php esc_html_e( 'Action Selection', 'bulk-actions-manager' ); ?></h2>
				<div class="bam-panel__body">
					<div class="bam-field">
						<label for="bam-action-type"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></label>
						<select id="bam-action-type" name="action_type">
							<?php self::render_action_options(); ?>
						</select>
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
								<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( (int) $settings['default_batch_size'], $size ); ?>>
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

		<script type="application/json" id="bam-filter-payload"><?php echo wp_json_encode( $payload ); ?></script>
		<?php
		self::footer();
	}

	/**
	 * Render action select options.
	 */
	private static function render_action_options() {
		$registry = new Action_Registry();
		foreach ( $registry->get_grouped() as $group => $actions ) {
			echo '<optgroup label="' . esc_attr( $group ) . '">';
			foreach ( $actions as $action ) {
				printf(
					'<option value="%1$s" data-safety="%2$s" data-undo="%3$s">%4$s</option>',
					esc_attr( $action['id'] ),
					esc_attr( $action['safety_level'] ),
					esc_attr( $action['supports_undo'] ? '1' : '0' ),
					esc_html( $action['label'] )
				);
			}
			echo '</optgroup>';
		}
	}
}
