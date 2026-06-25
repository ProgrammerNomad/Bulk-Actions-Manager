<?php
/**
 * Preview results summary for New Job workflow.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin;

use BAM\Filters\Filter_Compiler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Preview_Summary
 */
class Preview_Summary {

	/**
	 * Build summary data.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @param int                  $total   Total matching posts.
	 * @return array<string, mixed>
	 */
	public static function build( array $payload, $total ) {
		$aggregates = Filter_Compiler::get_aggregates( $payload, $total );

		return array(
			'total'              => $total,
			'statuses'           => $aggregates['statuses'],
			'categories'         => $aggregates['categories'],
			'categories_limited' => $aggregates['categories_limited'],
			'post_type'          => sanitize_key( (string) ( $payload['post_type'][0] ?? 'post' ) ),
		);
	}

	/**
	 * Render prominent results summary card.
	 *
	 * @param array<string, mixed> $summary Summary from build().
	 */
	public static function render( array $summary ) {
		$post_types = \BAM\Filters\Filter_Registry::get_post_types();
		$type_label = isset( $post_types[ $summary['post_type'] ] ) ? $post_types[ $summary['post_type'] ] : $summary['post_type'];
		?>
		<div class="bam-results-summary">
			<h3><?php esc_html_e( 'Results Summary', 'bulk-actions-manager' ); ?></h3>
			<div class="bam-results-summary__stats">
				<div class="bam-results-summary__stat">
					<span class="bam-results-summary__stat-label"><?php echo esc_html( $type_label ); ?></span>
					<span class="bam-results-summary__stat-value"><?php echo esc_html( number_format_i18n( (int) $summary['total'] ) ); ?></span>
				</div>
				<?php if ( ! empty( $summary['statuses'] ) ) : ?>
					<?php foreach ( $summary['statuses'] as $status => $count ) : ?>
						<?php
						$status_obj = get_post_status_object( $status );
						$label      = $status_obj ? $status_obj->label : $status;
						?>
						<div class="bam-results-summary__stat">
							<span class="bam-results-summary__stat-label"><?php echo esc_html( $label ); ?></span>
							<span class="bam-results-summary__stat-value"><?php echo esc_html( number_format_i18n( (int) $count ) ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $summary['categories'] ) ) : ?>
				<p class="bam-results-summary__subheading"><strong><?php esc_html_e( 'Top Categories', 'bulk-actions-manager' ); ?></strong></p>
				<ul class="bam-results-summary__list">
					<?php foreach ( $summary['categories'] as $name => $count ) : ?>
						<li><span class="bam-results-summary__cat-name"><?php echo esc_html( $name ); ?></span> - <strong><?php echo esc_html( number_format_i18n( (int) $count ) ); ?></strong></li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( ! empty( $summary['categories_limited'] ) ) : ?>
				<p class="description"><?php esc_html_e( 'Category breakdown omitted for large result sets.', 'bulk-actions-manager' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render inline count notice for Step 1.
	 *
	 * @param int $total Total matching posts.
	 */
	public static function render_count_notice( $total ) {
		printf(
			'<div class="bam-result-notice notice notice-info inline"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: formatted number */
					_n( 'Found %s matching item.', 'Found %s matching items.', (int) $total, 'bulk-actions-manager' ),
					number_format_i18n( (int) $total )
				)
			)
		);
	}
}
