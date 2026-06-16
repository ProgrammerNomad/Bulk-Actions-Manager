<?php
/**
 * Export actions - writes file during job completion.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Export_Action
 */
class Export_Action extends Abstract_Action {

	/**
	 * Action ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Format: ids, csv, json.
	 *
	 * @var string
	 */
	private $format;

	/**
	 * Label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Constructor.
	 *
	 * @param string $id     Action ID.
	 * @param string $format Format.
	 * @param string $label  Label.
	 */
	public function __construct( $id, $format, $label ) {
		$this->id     = $id;
		$this->format = $format;
		$this->label  = $label;
	}

	/** @inheritDoc */
	public function get_id() {
		return $this->id;
	}

	/** @inheritDoc */
	public function get_group() {
		return 'export';
	}

	/** @inheritDoc */
	public function get_label() {
		return $this->label;
	}

	/** @inheritDoc */
	public function get_safety_level() {
		return 'safe';
	}

	/** @inheritDoc */
	public function supports_undo() {
		return false;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		return array();
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		if ( ! $this->get_post( $object_id ) ) {
			return new Action_Result( false, __( 'Post not found.', 'bulk-actions-manager' ) );
		}
		// Export collects IDs during batch; file written on job completion.
		return new Action_Result( true );
	}

	/**
	 * Write export file for collected IDs.
	 *
	 * @param array<int, int> $ids    Post IDs.
	 * @param int             $job_id Job ID.
	 * @return string|false File URL or false.
	 */
	public function write_export_file( array $ids, $job_id ) {
		$upload_dir = wp_upload_dir();
		$export_dir = trailingslashit( $upload_dir['basedir'] ) . 'bam-exports';

		if ( ! wp_mkdir_p( $export_dir ) ) {
			return false;
		}

		// Protect directory.
		$htaccess = $export_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$filename = 'bam-export-' . $job_id . '-' . gmdate( 'Y-m-d-His' );
		$filepath = '';

		switch ( $this->format ) {
			case 'csv':
				$filepath = $export_dir . '/' . $filename . '.csv';
				$fp = fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				if ( ! $fp ) {
					return false;
				}
				fputcsv( $fp, array( 'ID', 'Title', 'Type', 'Status', 'Author', 'Date' ) );
				foreach ( $ids as $id ) {
					$post = get_post( $id );
					if ( ! $post ) {
						continue;
					}
					fputcsv(
						$fp,
						array(
							$post->ID,
							$post->post_title,
							$post->post_type,
							$post->post_status,
							get_the_author_meta( 'display_name', $post->post_author ),
							$post->post_date,
						)
					);
				}
				fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				break;

			case 'json':
				$filepath = $export_dir . '/' . $filename . '.json';
				$data = array();
				foreach ( $ids as $id ) {
					$post = get_post( $id );
					if ( $post ) {
						$data[] = array(
							'id'     => $post->ID,
							'title'  => $post->post_title,
							'type'   => $post->post_type,
							'status' => $post->post_status,
						);
					}
				}
				file_put_contents( $filepath, wp_json_encode( $data, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				break;

			default:
				$filepath = $export_dir . '/' . $filename . '.txt';
				file_put_contents( $filepath, implode( "\n", $ids ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				break;
		}

		return $filepath;
	}
}
