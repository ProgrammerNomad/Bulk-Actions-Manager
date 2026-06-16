<?php
/**
 * Tools admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Tools
 */
class Page_Tools extends Page_Base {

	/**
	 * Available tools.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_tools() {
		return array(
			'remove_revisions'   => array(
				'label'       => __( 'Remove Revisions', 'bulk-actions-manager' ),
				'description' => __( 'Delete post revisions to free database space.', 'bulk-actions-manager' ),
				'group'       => 'cleanup',
			),
			'remove_auto_drafts' => array(
				'label'       => __( 'Remove Auto Drafts', 'bulk-actions-manager' ),
				'description' => __( 'Delete auto-draft posts.', 'bulk-actions-manager' ),
				'group'       => 'cleanup',
			),
			'empty_trash'        => array(
				'label'       => __( 'Empty Trash', 'bulk-actions-manager' ),
				'description' => __( 'Permanently delete all trashed posts.', 'bulk-actions-manager' ),
				'group'       => 'cleanup',
			),
			'orphan_attachments' => array(
				'label'       => __( 'Orphan Attachments', 'bulk-actions-manager' ),
				'description' => __( 'Find and remove attachments not attached to any post.', 'bulk-actions-manager' ),
				'group'       => 'orphan',
			),
			'orphan_metadata'    => array(
				'label'       => __( 'Orphan Metadata', 'bulk-actions-manager' ),
				'description' => __( 'Remove post meta for deleted posts.', 'bulk-actions-manager' ),
				'group'       => 'orphan',
			),
			'export_jobs'        => array(
				'label'       => __( 'Export Jobs', 'bulk-actions-manager' ),
				'description' => __( 'Download all jobs as JSON.', 'bulk-actions-manager' ),
				'group'       => 'export',
			),
			'export_logs'        => array(
				'label'       => __( 'Export Logs', 'bulk-actions-manager' ),
				'description' => __( 'Download all logs as JSON.', 'bulk-actions-manager' ),
				'group'       => 'export',
			),
		);
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

		foreach ( $groups as $group_key => $group_label ) {
			echo '<h2>' . esc_html( $group_label ) . '</h2>';
			?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Tool', 'bulk-actions-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'bulk-actions-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( self::get_tools() as $tool_id => $tool ) {
						if ( $tool['group'] !== $group_key ) {
							continue;
						}
						?>
						<tr>
							<td><strong><?php echo esc_html( $tool['label'] ); ?></strong></td>
							<td><?php echo esc_html( $tool['description'] ); ?></td>
							<td>
								<button type="button" class="button button-secondary bam-run-tool" data-tool="<?php echo esc_attr( $tool_id ); ?>">
									<?php esc_html_e( 'Run', 'bulk-actions-manager' ); ?>
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
		}
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.bam-run-tool').forEach(function(btn) {
				btn.addEventListener('click', function() {
					bamConfirm({
						title: bamAdmin.i18n.confirmRunTool,
						message: bamAdmin.i18n.confirmRunToolMessage
					}).then(function(confirmed) {
						if ( !confirmed ) return;
						var tool = btn.dataset.tool;
						var resultEl = document.getElementById('bam-tool-result-' + tool);
						btn.disabled = true;
						bamApi.post('tools/' + tool, {}).then(function(res) {
							if ( resultEl ) resultEl.textContent = res.message || bamAdmin.i18n.completed;
						}).catch(function() {
							if ( resultEl ) resultEl.textContent = bamAdmin.i18n.error;
						}).finally(function() {
							btn.disabled = false;
						});
					});
				});
			});
		});
		</script>
		<?php
		self::footer();
	}
}
