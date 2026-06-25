<?php
/**
 * Tools admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Admin\Admin_UI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Tools
 */
class Page_Tools extends Page_Base {

	/**
	 * Available tools.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_tools() {
		return array(
			'remove_revisions'   => array(
				'label'             => __( 'Remove Revisions', 'bulk-actions-manager' ),
				'description'       => __( 'Delete post revisions to free database space.', 'bulk-actions-manager' ),
				'group'             => 'cleanup',
				'kind'              => 'cleanup',
				'destructive'       => false,
				'batched'           => true,
				'logged'            => true,
				'undo'              => false,
				'instant_download'  => false,
				'button'            => 'run_cleanup',
				'confirm_detail'    => __( 'Runs in background batches and creates an audit log entry.', 'bulk-actions-manager' ),
			),
			'remove_auto_drafts' => array(
				'label'             => __( 'Remove Auto Drafts', 'bulk-actions-manager' ),
				'description'       => __( 'Delete auto-draft posts.', 'bulk-actions-manager' ),
				'group'             => 'cleanup',
				'kind'              => 'cleanup',
				'destructive'       => false,
				'batched'           => true,
				'logged'            => true,
				'undo'              => false,
				'instant_download'  => false,
				'button'            => 'run_cleanup',
				'confirm_detail'    => __( 'Runs in background batches and creates an audit log entry.', 'bulk-actions-manager' ),
			),
			'empty_trash'        => array(
				'label'             => __( 'Empty Trash', 'bulk-actions-manager' ),
				'description'       => __( 'Permanently delete all trashed posts.', 'bulk-actions-manager' ),
				'group'             => 'cleanup',
				'kind'              => 'cleanup',
				'destructive'       => true,
				'batched'           => true,
				'logged'            => true,
				'undo'              => false,
				'instant_download'  => false,
				'button'            => 'run_cleanup',
				'confirm_detail'    => __( 'Permanently deletes all trashed posts. Cannot be undone.', 'bulk-actions-manager' ),
			),
			'orphan_attachments' => array(
				'label'             => __( 'Orphan Attachments', 'bulk-actions-manager' ),
				'description'       => __( 'Find and remove attachments not attached to any post.', 'bulk-actions-manager' ),
				'group'             => 'orphan',
				'kind'              => 'orphan',
				'destructive'       => true,
				'batched'           => true,
				'logged'            => true,
				'undo'              => false,
				'instant_download'  => false,
				'button'            => 'run_cleanup',
				'confirm_detail'    => __( 'Removes unattached media files. Cannot be undone.', 'bulk-actions-manager' ),
			),
			'orphan_metadata'    => array(
				'label'             => __( 'Orphan Metadata', 'bulk-actions-manager' ),
				'description'       => __( 'Remove post meta for deleted posts.', 'bulk-actions-manager' ),
				'group'             => 'orphan',
				'kind'              => 'orphan',
				'destructive'       => false,
				'batched'           => true,
				'logged'            => true,
				'undo'              => false,
				'instant_download'  => false,
				'button'            => 'run_cleanup',
				'confirm_detail'    => __( 'Runs in background batches and creates an audit log entry.', 'bulk-actions-manager' ),
			),
			'export_jobs'        => array(
				'label'             => __( 'Export Jobs', 'bulk-actions-manager' ),
				'description'       => __( 'Download all jobs as JSON.', 'bulk-actions-manager' ),
				'group'             => 'export',
				'kind'              => 'export',
				'destructive'       => false,
				'batched'           => false,
				'logged'            => false,
				'undo'              => false,
				'instant_download'  => true,
				'button'            => 'download_jobs',
				'confirm_detail'    => '',
			),
			'export_logs'        => array(
				'label'             => __( 'Export Logs', 'bulk-actions-manager' ),
				'description'       => __( 'Download all logs as JSON.', 'bulk-actions-manager' ),
				'group'             => 'export',
				'kind'              => 'export',
				'destructive'       => false,
				'batched'           => false,
				'logged'            => false,
				'undo'              => false,
				'instant_download'  => true,
				'button'            => 'download_logs',
				'confirm_detail'    => '',
			),
		);
	}

	/**
	 * Button label for a tool.
	 *
	 * @param array<string, mixed> $tool Tool definition.
	 * @return string
	 */
	private static function tool_button_label( array $tool ) {
		$labels = array(
			'run_cleanup'    => __( 'Run Cleanup', 'bulk-actions-manager' ),
			'download_jobs'  => __( 'Download Jobs', 'bulk-actions-manager' ),
			'download_logs'  => __( 'Download Logs', 'bulk-actions-manager' ),
		);
		$key = $tool['button'] ?? 'run_cleanup';
		return $labels[ $key ] ?? __( 'Run', 'bulk-actions-manager' );
	}

	/**
	 * Render tools page.
	 */
	public static function render() {
		self::header( __( 'Tools', 'bulk-actions-manager' ) );

		$groups = array(
			'cleanup' => __( 'Cleanup Tools', 'bulk-actions-manager' ),
			'orphan'  => __( 'Orphan Cleanup', 'bulk-actions-manager' ),
			'export'  => __( 'Export Tools', 'bulk-actions-manager' ),
		);

		echo '<div id="bam-tools" class="metabox-holder">';

		foreach ( $groups as $group_key => $group_label ) {
			Admin_UI::postbox_open( 'bam-tools-' . $group_key, $group_label );
			?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Tool', 'bulk-actions-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'bulk-actions-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'bulk-actions-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( self::get_tools() as $tool_id => $tool ) {
						if ( $tool['group'] !== $group_key ) {
							continue;
						}
						$is_export = ! empty( $tool['instant_download'] );
						?>
						<tr>
							<td><strong><?php echo esc_html( $tool['label'] ); ?></strong></td>
							<td><?php echo esc_html( $tool['description'] ); ?></td>
							<td><?php echo Admin_UI::tool_meta_badges( $tool ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td>
								<button type="button"
									class="button button-secondary bam-run-tool"
									data-tool="<?php echo esc_attr( $tool_id ); ?>"
									data-destructive="<?php echo ! empty( $tool['destructive'] ) ? '1' : '0'; ?>"
									data-export="<?php echo $is_export ? '1' : '0'; ?>"
									data-label="<?php echo esc_attr( $tool['label'] ); ?>"
									data-detail="<?php echo esc_attr( $tool['confirm_detail'] ?? '' ); ?>">
									<?php echo esc_html( self::tool_button_label( $tool ) ); ?>
								</button>
								<span class="description bam-tool-result" id="bam-tool-result-<?php echo esc_attr( $tool_id ); ?>"></span>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
			<?php
			Admin_UI::postbox_close();
		}

		echo '</div>';
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.bam-run-tool').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var isExport = btn.dataset.export === '1';
					var isDestructive = btn.dataset.destructive === '1';
					var toolLabel = btn.dataset.label || '';
					var detail = btn.dataset.detail || '';

					function runTool() {
						var tool = btn.dataset.tool;
						var resultEl = document.getElementById('bam-tool-result-' + tool);
						btn.disabled = true;
						bamApi.post('tools/' + tool, {}).then(function(res) {
							if (res.download && res.data) {
								var blob = new Blob([res.data], { type: 'application/json' });
								var url = URL.createObjectURL(blob);
								var a = document.createElement('a');
								a.href = url;
								a.download = res.filename || (tool + '.json');
								document.body.appendChild(a);
								a.click();
								setTimeout(function() { URL.revokeObjectURL(url); a.remove(); }, 1000);
							}
							if (res.job_id && res.jobs_url) {
								if (resultEl) {
									resultEl.innerHTML = (res.message || bamAdmin.i18n.completed) +
										' <a href="' + res.jobs_url + '">' + (bamAdmin.i18n.backgroundJobsLink || 'View job') + '</a>';
								}
							} else if (!res.download) {
								if (resultEl) resultEl.textContent = res.message || bamAdmin.i18n.completed;
							}
						}).catch(function() {
							if (resultEl) resultEl.textContent = bamAdmin.i18n.error;
						}).finally(function() {
							btn.disabled = false;
						});
					}

					if (isExport) {
						runTool();
						return;
					}

					bamConfirm({
						title: toolLabel,
						message: btn.textContent.trim(),
						detail: detail,
						destructive: isDestructive
					}).then(function(confirmed) {
						if (confirmed) runTool();
					});
				});
			});
		});
		</script>
		<?php
		self::footer();
	}
}
