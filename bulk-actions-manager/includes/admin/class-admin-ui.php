<?php
/**
 * WordPress-native admin UI helpers.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_UI
 */
class Admin_UI {

	/**
	 * Open a postbox container.
	 *
	 * @param string $id    Postbox ID.
	 * @param string $title Postbox title.
	 * @param string $class Extra CSS classes.
	 */
	public static function postbox_open( $id, $title, $class = '' ) {
		printf(
			'<div id="%1$s" class="postbox %2$s">
				<div class="postbox-header">
					<h2 class="hndle ui-sortable-handle"><span>%3$s</span></h2>
					<div class="handle-actions hide-if-no-js">
						<button type="button" class="handlediv" aria-expanded="true">
							<span class="screen-reader-text">%4$s</span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
					</div>
				</div>
				<div class="inside">',
			esc_attr( $id ),
			esc_attr( $class ),
			esc_html( $title ),
			esc_html__( 'Toggle panel', 'bulk-actions-manager' )
		);
	}

	/**
	 * Close a postbox container.
	 */
	public static function postbox_close() {
		echo '</div></div>';
	}

	/**
	 * Render a compact job status badge (20% custom UI).
	 *
	 * @param string $status Job status slug.
	 * @return string
	 */
	public static function status_badge( $status ) {
		$status = sanitize_key( $status );
		$labels = array(
			'queued'    => __( 'Queued', 'bulk-actions-manager' ),
			'running'   => __( 'Running', 'bulk-actions-manager' ),
			'paused'    => __( 'Paused', 'bulk-actions-manager' ),
			'completed' => __( 'Completed', 'bulk-actions-manager' ),
			'failed'    => __( 'Failed', 'bulk-actions-manager' ),
			'cancelled' => __( 'Cancelled', 'bulk-actions-manager' ),
		);
		$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

		return sprintf(
			'<span class="bam-status-badge bam-status-badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Render undo availability label with dashicon.
	 *
	 * @param object $job Job row.
	 * @return string
	 */
	public static function undo_label( $job ) {
		if ( ! empty( $job->undo_available ) ) {
			return '<span class="description"><span class="dashicons dashicons-backup" aria-hidden="true"></span> ' . esc_html__( 'Yes', 'bulk-actions-manager' ) . '</span>';
		}

		return '<span class="description">' . esc_html__( 'No', 'bulk-actions-manager' ) . '</span>';
	}

	/**
	 * Render action safety hint for New Job step 3.
	 *
	 * @param string $level safe|recoverable|destructive.
	 * @return string
	 */
	public static function safety_hint( $level ) {
		$map = array(
			'safe'        => array( 'dashicons-yes-alt', __( 'Undo supported', 'bulk-actions-manager' ) ),
			'recoverable' => array( 'dashicons-backup', __( 'Recoverable', 'bulk-actions-manager' ) ),
			'destructive' => array( 'dashicons-warning', __( 'Cannot be undone', 'bulk-actions-manager' ) ),
		);

		if ( ! isset( $map[ $level ] ) ) {
			return '';
		}

		return sprintf(
			'<p class="description" id="bam-action-safety"><span class="dashicons %1$s" aria-hidden="true"></span> %2$s</p>',
			esc_attr( $map[ $level ][0] ),
			esc_html( $map[ $level ][1] )
		);
	}
}
