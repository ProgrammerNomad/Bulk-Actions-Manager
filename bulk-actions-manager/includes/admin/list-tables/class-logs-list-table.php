<?php
/**
 * Logs admin list table.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\List_Tables;

use BAM\Admin\Admin_UI;
use BAM\Database\Repositories\Log_Repository;
use BAM\Utils\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class Logs_List_Table
 */
class Logs_List_Table extends List_Table_Base {

	/**
	 * Current undo_status view.
	 *
	 * @var string
	 */
	private $undo_view = '';

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox" />',
			'id'             => __( 'Log ID', 'bulk-actions-manager' ),
			'source'         => __( 'Source', 'bulk-actions-manager' ),
			'job_id'         => __( 'Job', 'bulk-actions-manager' ),
			'user'           => __( 'User', 'bulk-actions-manager' ),
			'action_type'    => __( 'Action', 'bulk-actions-manager' ),
			'affected_count' => __( 'Affected', 'bulk-actions-manager' ),
			'failed_count'   => __( 'Failed', 'bulk-actions-manager' ),
			'created_at'     => __( 'Date', 'bulk-actions-manager' ),
			'undo_status'    => __( 'Undo', 'bulk-actions-manager' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_sortable_columns() {
		return array(
			'id'             => array( 'id', false ),
			'created_at'     => array( 'created_at', true ),
			'affected_count' => array( 'affected_count', false ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'bulk-actions-manager' ),
		);
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		if ( ! Capabilities::current_user_can() ) {
			return;
		}

		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		\check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$ids = isset( $_REQUEST['item'] ) ? \array_map( 'absint', (array) \wp_unslash( $_REQUEST['item'] ) ) : array();
		foreach ( $ids as $id ) {
			Log_Repository::delete( $id );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page_value();
		$current_page = max( 1, $this->get_pagenum() );
		$this->undo_view = isset( $_REQUEST['undo_status'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['undo_status'] ) ) : '';

		$orderby = isset( $_REQUEST['orderby'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order   = isset( $_REQUEST['order'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		$search  = isset( $_REQUEST['s'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ) ) : '';

		$args = array(
			'undo_status' => $this->undo_view,
			'search'      => $search,
			'limit'       => $per_page,
			'offset'      => ( $current_page - 1 ) * $per_page,
			'orderby'     => $orderby,
			'order'       => $order,
		);

		$total_items = Log_Repository::count( $args );
		$this->items = Log_Repository::list( $args );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_views() {
		$counts  = Log_Repository::count_by_undo_status();
		$current = $this->undo_view;
		$views   = array();

		$views['all'] = $this->view_link( __( 'All', 'bulk-actions-manager' ), '', $counts['total'], $current, 'undo_status' );

		$labels = array(
			'available' => __( 'Undo Available', 'bulk-actions-manager' ),
			'used'      => __( 'Used', 'bulk-actions-manager' ),
			'expired'   => __( 'Expired', 'bulk-actions-manager' ),
		);

		foreach ( $labels as $slug => $label ) {
			if ( $counts[ $slug ] > 0 || $current === $slug ) {
				$views[ $slug ] = $this->view_link( $label, $slug, $counts[ $slug ], $current, 'undo_status' );
			}
		}

		return $views;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="item[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Log ID with row actions.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_id( $item ) {
		$view_url = \add_query_arg(
			array(
				'page'   => 'bam-logs',
				'log_id' => (int) $item->id,
			),
			\admin_url( 'admin.php' )
		);

		$actions = array(
			'view' => \sprintf( '<a href="%s">%s</a>', \esc_url( $view_url ), \esc_html__( 'View', 'bulk-actions-manager' ) ),
		);

		if ( 'available' === $item->undo_status ) {
			$undo_url = \wp_nonce_url(
				\add_query_arg(
					array(
						'page'       => 'bam-logs',
						'bam_action' => 'undo_log',
						'log_id'     => (int) $item->id,
					),
					\admin_url( 'admin.php' )
				),
				'bam_undo_log_' . (int) $item->id
			);
			$actions['undo'] = \sprintf( '<a href="%s">%s</a>', \esc_url( $undo_url ), \esc_html__( 'Undo', 'bulk-actions-manager' ) );
		}

		return \sprintf(
			'%1$d%2$s',
			(int) $item->id,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Source column: Job vs Tool (immediate) vs Tool Job.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_source( $item ) {
		if ( 0 === strpos( $item->action_type, 'tool.' ) ) {
			if ( ! empty( $item->job_id ) ) {
				return \esc_html__( 'Tool Job', 'bulk-actions-manager' );
			}
			return \esc_html__( 'Tool', 'bulk-actions-manager' );
		}
		return \esc_html__( 'Job', 'bulk-actions-manager' );
	}

	/**
	 * Job ID column - links to job detail, or blank for immediate-tool entries.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_job_id( $item ) {
		if ( empty( $item->job_id ) ) {
			return '-';
		}
		$url = \add_query_arg(
			array(
				'page'   => 'bam-jobs',
				'job_id' => (int) $item->job_id,
			),
			\admin_url( 'admin.php' )
		);
		return \sprintf( '<a href="%s">#%d</a>', \esc_url( $url ), (int) $item->job_id );
	}

	/**
	 * Failed count column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_failed_count( $item ) {
		return (string) (int) $item->failed_count;
	}

	/**
	 * Action column with human-readable label.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_action_type( $item ) {
		return esc_html( Admin_UI::action_label( $item->action_type ) );
	}

	/**
	 * User column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_user( $item ) {
		$user = \get_userdata( (int) $item->user_id );
		return $user ? \esc_html( $user->display_name ) : '-';
	}

	/**
	 * Undo status column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_undo_status( $item ) {
		$labels = array(
			'available' => __( 'Undo Available', 'bulk-actions-manager' ),
			'used'      => __( 'Used', 'bulk-actions-manager' ),
			'expired'   => __( 'Expired', 'bulk-actions-manager' ),
		);
		$status = isset( $labels[ $item->undo_status ] ) ? $labels[ $item->undo_status ] : $item->undo_status;

		if ( 'available' !== $item->undo_status ) {
			return esc_html( $status );
		}

		$undo_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'bam-logs',
					'bam_action' => 'undo_log',
					'log_id'     => (int) $item->id,
				),
				admin_url( 'admin.php' )
			),
			'bam_undo_log_' . (int) $item->id
		);

		return sprintf(
			'<div class="bam-logs-undo-cell"><span class="bam-status-badge bam-status-badge--completed">%1$s</span> <a class="button button-secondary" href="%2$s">%3$s</a></div>',
			esc_html( $status ),
			esc_url( $undo_url ),
			esc_html__( 'Undo', 'bulk-actions-manager' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_page_slug() {
		return 'bam-logs';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function page_url( array $args = array() ) {
		return \add_query_arg( \wp_parse_args( $args, array( 'page' => 'bam-logs' ) ), \admin_url( 'admin.php' ) );
	}
}
