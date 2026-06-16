<?php
/**
 * New Job admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Actions\Action_Registry;
use BAM\Admin\Admin_UI;
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

		$payload        = Filter_Bar::parse_request_to_payload( $request );
		$filters_active = self::has_active_filters( $request );
		$show_preview   = ! empty( $request['preview'] );
		$total          = 0;
		$page_ids       = array();

		if ( $filters_active ) {
			$all_ids = Filter_Compiler::resolve_ids( $payload );
			$total   = count( $all_ids );
			if ( $show_preview ) {
				$per_page = 20;
				$paged    = isset( $request['paged'] ) ? max( 1, absint( $request['paged'] ) ) : 1;
				$offset   = ( $paged - 1 ) * $per_page;
				$page_ids = array_slice( $all_ids, $offset, $per_page );
			}
		}

		self::header( __( 'New Job', 'bulk-actions-manager' ) );
		?>
		<div id="bam-new-job">
			<?php
			Admin_UI::postbox_open( 'bam-step-filter', __( 'Step 1: Filter Content', 'bulk-actions-manager' ) );
			?>
			<form method="get" id="posts-filter">
				<input type="hidden" name="page" value="bam-new-job" />
				<?php if ( ! empty( $request['post_status'] ) ) : ?>
					<input type="hidden" name="post_status" value="<?php echo esc_attr( sanitize_key( $request['post_status'] ) ); ?>" />
				<?php else : ?>
					<input type="hidden" name="post_status" value="all" />
				<?php endif; ?>
				<?php Filter_Bar::render( $request ); ?>
			</form>
			<?php if ( $filters_active ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of posts */
							_n( '%d item matches your filters.', '%d items match your filters.', $total, 'bulk-actions-manager' ),
							$total
						)
					);
					?>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Apply filters to see how many items match.', 'bulk-actions-manager' ); ?></p>
			<?php endif; ?>
			<?php
			Admin_UI::postbox_close();

			Admin_UI::postbox_open( 'bam-step-preview', __( 'Step 2: Preview Results', 'bulk-actions-manager' ) );
			if ( ! $filters_active ) {
				echo '<p class="description">' . esc_html__( 'Complete step 1 first.', 'bulk-actions-manager' ) . '</p>';
			} elseif ( ! $show_preview ) {
				$preview_url = add_query_arg( array_merge( $request, array( 'page' => 'bam-new-job', 'preview' => '1' ) ), admin_url( 'admin.php' ) );
				printf(
					'<p><a class="button button-secondary" href="%s">%s</a></p>',
					esc_url( $preview_url ),
					esc_html__( 'Preview Matching Posts', 'bulk-actions-manager' )
				);
			} else {
				$preview_table = new Posts_Preview_List_Table();
				$preview_table->set_preview_data( $page_ids, $total );
				$preview_table->display();
			}
			Admin_UI::postbox_close();

			Admin_UI::postbox_open( 'bam-step-action', __( 'Step 3: Select Action', 'bulk-actions-manager' ) );
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bam-action-type"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select id="bam-action-type" name="action_type">
							<?php self::render_action_options(); ?>
						</select>
						<div id="bam-action-safety-wrap"><?php echo Admin_UI::safety_hint( 'safe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					</td>
				</tr>
			</table>
			<table class="form-table" role="presentation" id="bam-action-fields"></table>
			<?php
			Admin_UI::postbox_close();

			Admin_UI::postbox_open( 'bam-step-run', __( 'Step 4: Execute', 'bulk-actions-manager' ) );
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Dry Run', 'bulk-actions-manager' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="bam-dry-run" name="is_dry_run" value="1" />
							<?php esc_html_e( 'Dry run (no changes will be made)', 'bulk-actions-manager' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-batch-size"><?php esc_html_e( 'Batch Size', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select id="bam-batch-size" name="batch_size">
							<?php foreach ( array( 10, 25, 50, 100 ) as $size ) : ?>
								<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( (int) $settings['default_batch_size'], $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-processing-mode"><?php esc_html_e( 'Processing Mode', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select id="bam-processing-mode" name="processing_mode">
							<option value="ajax" <?php selected( $settings['default_processing_mode'], 'ajax' ); ?>><?php esc_html_e( 'AJAX (Recommended)', 'bulk-actions-manager' ); ?></option>
							<option value="background" <?php selected( $settings['default_processing_mode'], 'background' ); ?>><?php esc_html_e( 'Background Queue', 'bulk-actions-manager' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-job-name"><?php esc_html_e( 'Job Name', 'bulk-actions-manager' ); ?></label></th>
					<td><input type="text" id="bam-job-name" name="job_name" class="regular-text" /></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button button-primary" id="bam-start-job"><?php esc_html_e( 'Start Job', 'bulk-actions-manager' ); ?></button>
			</p>
			<?php
			Admin_UI::postbox_close();

			Admin_UI::postbox_open( 'bam-job-progress', __( 'Job Progress', 'bulk-actions-manager' ), 'bam-hidden' );
			?>
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
			<?php
			Admin_UI::postbox_close();
			?>
		</div>

		<script type="application/json" id="bam-filter-payload"><?php echo wp_json_encode( $payload ); ?></script>
		<?php
		self::footer();
	}

	/**
	 * Whether filter query args are active.
	 *
	 * @param array<string, mixed> $request Request args.
	 * @return bool
	 */
	private static function has_active_filters( array $request ) {
		$keys = array( 'm', 'cat', 'author', 's', 'seo-filter', 'rankmath-filter', 'tag_id', 'preview' );
		foreach ( $keys as $key ) {
			if ( ! empty( $request[ $key ] ) ) {
				return true;
			}
		}

		if ( ! empty( $request['post_type'] ) && 'post' !== sanitize_key( (string) $request['post_type'] ) ) {
			return true;
		}

		$post_status = ! empty( $request['post_status'] ) ? sanitize_key( (string) $request['post_status'] ) : 'all';
		return 'all' !== $post_status;
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
