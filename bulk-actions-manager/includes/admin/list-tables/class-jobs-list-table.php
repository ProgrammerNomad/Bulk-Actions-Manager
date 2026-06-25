<?php
/**
 * Jobs and schedules admin list table.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\List_Tables;

use BAM\Admin\Admin_UI;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Utils\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class Jobs_List_Table
 */
class Jobs_List_Table extends List_Table_Base {

	/**
	 * List mode: run (job executions) or schedule (recurring configs).
	 *
	 * @var string
	 */
	private $type = 'run';

	/**
	 * Current job status view.
	 *
	 * @var string
	 */
	private $status_view = '';

	/**
	 * Current schedule active view.
	 *
	 * @var string
	 */
	private $active_view = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_REQUEST['type'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['type'] ) ) : 'run';
		$this->type = ( 'schedule' === $type ) ? 'schedule' : 'run';
	}

	/**
	 * Whether listing schedules.
	 *
	 * @return bool
	 */
	private function is_schedule_type() {
		return 'schedule' === $this->type;
	}

	/**
	 * Get columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		if ( $this->is_schedule_type() ) {
			return array(
				'type'            => __( 'Type', 'bulk-actions-manager' ),
				'id'              => __( 'ID', 'bulk-actions-manager' ),
				'name'            => __( 'Name', 'bulk-actions-manager' ),
				'action_type'     => __( 'Action', 'bulk-actions-manager' ),
				'cron_expression' => __( 'Frequency', 'bulk-actions-manager' ),
				'next_run_at'     => __( 'Next Run', 'bulk-actions-manager' ),
				'is_active'       => __( 'Active', 'bulk-actions-manager' ),
				'last_run_at'     => __( 'Last Run', 'bulk-actions-manager' ),
			);
		}

		return array(
			'cb'          => '<input type="checkbox" />',
			'type'        => __( 'Type', 'bulk-actions-manager' ),
			'id'          => __( 'ID', 'bulk-actions-manager' ),
			'name'        => __( 'Name', 'bulk-actions-manager' ),
			'action_type' => __( 'Action', 'bulk-actions-manager' ),
			'status'      => __( 'Status', 'bulk-actions-manager' ),
			'records'     => __( 'Records', 'bulk-actions-manager' ),
			'created_at'  => __( 'Created', 'bulk-actions-manager' ),
			'finished_at' => __( 'Finished', 'bulk-actions-manager' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, bool>>
	 */
	protected function get_sortable_columns() {
		if ( $this->is_schedule_type() ) {
			return array(
				'id'          => array( 'id', false ),
				'name'        => array( 'name', false ),
				'next_run_at' => array( 'next_run_at', false ),
				'last_run_at' => array( 'last_run_at', true ),
			);
		}

		return array(
			'id'         => array( 'id', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		if ( $this->is_schedule_type() ) {
			return array();
		}

		$view = $this->get_status_view();

		switch ( $view ) {
			case 'running':
				return array(
					'pause'  => __( 'Pause', 'bulk-actions-manager' ),
					'cancel' => __( 'Cancel', 'bulk-actions-manager' ),
				);
			case 'paused':
				return array(
					'resume' => __( 'Resume', 'bulk-actions-manager' ),
					'cancel' => __( 'Cancel', 'bulk-actions-manager' ),
				);
			case 'queued':
				return array(
					'cancel' => __( 'Cancel', 'bulk-actions-manager' ),
				);
			case 'completed':
			case 'failed':
			case 'cancelled':
				return array(
					'delete' => __( 'Delete', 'bulk-actions-manager' ),
				);
			default:
				return array(
					'pause'  => __( 'Pause', 'bulk-actions-manager' ),
					'resume' => __( 'Resume', 'bulk-actions-manager' ),
					'cancel' => __( 'Cancel', 'bulk-actions-manager' ),
					'delete' => __( 'Delete', 'bulk-actions-manager' ),
				);
		}
	}

	/**
	 * Current runs status filter from request or prepare_items.
	 *
	 * @return string
	 */
	private function get_status_view() {
		if ( '' !== $this->status_view ) {
			return $this->status_view;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_REQUEST['status'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['status'] ) ) : '';
	}

	/**
	 * Prime status view from the current request (for early bulk handling).
	 */
	public function prime_status_view_from_request() {
		if ( $this->is_schedule_type() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->status_view = isset( $_REQUEST['status'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['status'] ) ) : '';
	}

	/**
	 * Build redirect URL after a bulk action.
	 *
	 * @param string $action  Bulk action slug.
	 * @param int    $updated Number of jobs updated.
	 * @param int    $skipped Number of jobs skipped.
	 * @return string
	 */
	private function get_bulk_redirect_url( $action, $updated, $skipped ) {
		$args = array(
			'page'            => 'bam-jobs',
			'bam_bulk'        => 1,
			'bam_bulk_action' => $action,
			'bam_updated'     => $updated,
			'bam_skipped'     => $skipped,
		);

		$status_map = array(
			'resume' => 'running',
			'pause'  => 'paused',
			'cancel' => 'cancelled',
		);

		if ( isset( $status_map[ $action ] ) ) {
			$args['status'] = $status_map[ $action ];
		} elseif ( 'delete' === $action ) {
			$terminal = array( 'completed', 'failed', 'cancelled' );
			if ( in_array( $this->status_view, $terminal, true ) ) {
				$args['status'] = $this->status_view;
			}
		}

		return \add_query_arg( $args, \admin_url( 'admin.php' ) );
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		if ( $this->is_schedule_type() || ! Capabilities::current_user_can() ) {
			return;
		}

		$action = $this->current_action();
		if ( ! $action || ! in_array( $action, array( 'pause', 'resume', 'cancel', 'delete' ), true ) ) {
			return;
		}

		\check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$ids = isset( $_REQUEST['item'] ) ? \array_map( 'absint', (array) \wp_unslash( $_REQUEST['item'] ) ) : array();
		if ( empty( $ids ) ) {
			return;
		}

		$manager = new Job_Manager();
		$updated = 0;
		$skipped = 0;

		foreach ( $ids as $id ) {
			$result = null;

			switch ( $action ) {
				case 'pause':
					$result = $manager->pause( $id );
					break;
				case 'resume':
					$result = $manager->resume( $id );
					break;
				case 'cancel':
					$result = $manager->cancel( $id );
					break;
				case 'delete':
					$result = $manager->delete_job( $id );
					break;
			}

			if ( is_wp_error( $result ) ) {
				$skipped++;
			} else {
				$updated++;
			}
		}

		\wp_safe_redirect( $this->get_bulk_redirect_url( $action, $updated, $skipped ) );
		exit;
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		if ( $this->is_schedule_type() ) {
			$this->prepare_schedule_items();
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->status_view = isset( $_REQUEST['status'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['status'] ) ) : '';

		$per_page     = $this->get_items_per_page_value();
		$current_page = max( 1, $this->get_pagenum() );

		$orderby = isset( $_REQUEST['orderby'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order   = isset( $_REQUEST['order'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		$search  = isset( $_REQUEST['s'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ) ) : '';

		$args = array(
			'status'  => $this->status_view,
			'search'  => $search,
			'limit'   => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
			'orderby' => $orderby,
			'order'   => $order,
		);

		$total_items = Job_Repository::count( $args );
		$this->items = Job_Repository::list( $args );

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
	 * Prepare schedule rows.
	 */
	private function prepare_schedule_items() {
		$per_page     = $this->get_items_per_page_value();
		$current_page = max( 1, $this->get_pagenum() );
		$this->active_view = isset( $_REQUEST['active'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['active'] ) ) : '';

		$is_active = '';
		if ( 'active' === $this->active_view ) {
			$is_active = 1;
		} elseif ( 'inactive' === $this->active_view ) {
			$is_active = 0;
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order   = isset( $_REQUEST['order'] ) ? \sanitize_key( \wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		$search  = isset( $_REQUEST['s'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ) ) : '';

		$args = array(
			'is_active' => $is_active,
			'search'    => $search,
			'limit'     => $per_page,
			'offset'    => ( $current_page - 1 ) * $per_page,
			'orderby'   => $orderby,
			'order'     => $order,
		);

		$total_items = Schedule_Repository::count( $args );
		$this->items = Schedule_Repository::list( $args );

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
	 * Type and filter views.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$views = $this->get_type_views();

		if ( $this->is_schedule_type() ) {
			$counts  = Schedule_Repository::count_by_active();
			$current = $this->active_view;

			$views['all_schedules']      = $this->view_link( __( 'All', 'bulk-actions-manager' ), '', $counts['total'], $current, 'active' );
			$views['active_schedules']   = $this->view_link( __( 'Active', 'bulk-actions-manager' ), 'active', $counts['active'], $current, 'active' );
			$views['inactive_schedules'] = $this->view_link( __( 'Inactive', 'bulk-actions-manager' ), 'inactive', $counts['inactive'], $current, 'active' );

			return $views;
		}

		$counts  = Job_Repository::count_by_status();
		$current = $this->status_view;

		$views['all_runs'] = $this->view_link( __( 'All', 'bulk-actions-manager' ), '', $counts['total'], $current );

		$labels = array(
			'running'   => __( 'Running', 'bulk-actions-manager' ),
			'queued'    => __( 'Queued', 'bulk-actions-manager' ),
			'completed' => __( 'Completed', 'bulk-actions-manager' ),
			'failed'    => __( 'Failed', 'bulk-actions-manager' ),
			'paused'    => __( 'Paused', 'bulk-actions-manager' ),
			'cancelled' => __( 'Cancelled', 'bulk-actions-manager' ),
		);

		foreach ( $labels as $slug => $label ) {
			if ( $counts[ $slug ] > 0 || $current === $slug ) {
				$views[ 'run_' . $slug ] = $this->view_link( $label, $slug, $counts[ $slug ], $current );
			}
		}

		return $views;
	}

	/**
	 * Runs vs Scheduled type switcher links.
	 *
	 * @return array<string, string>
	 */
	private function get_type_views() {
		$run_label      = __( 'Runs', 'bulk-actions-manager' );
		$schedule_label = __( 'Scheduled', 'bulk-actions-manager' );
		$run_url        = $this->page_url( array( 'type' => 'run' ) );
		$schedule_url   = $this->page_url( array( 'type' => 'schedule' ) );
		$run_class      = ! $this->is_schedule_type() ? ' class="current"' : '';
		$schedule_class = $this->is_schedule_type() ? ' class="current"' : '';

		return array(
			'type_run' => '<a href="' . \esc_url( $run_url ) . '"' . $run_class . '>' . \esc_html( $run_label ) . '</a>',
			'type_schedule' => '<a href="' . \esc_url( $schedule_url ) . '"' . $schedule_class . '>' . \esc_html( $schedule_label ) . '</a>',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function view_link( $label, $slug, $count, $current, $param = 'status' ) {
		$args = array( 'page' => 'bam-jobs' );
		if ( $this->is_schedule_type() ) {
			$args['type'] = 'schedule';
		}
		if ( $slug ) {
			$args[ $param ] = $slug;
		}
		$url   = \add_query_arg( $args, \admin_url( 'admin.php' ) );
		$class = ( (string) $current === (string) $slug ) ? ' class="current"' : '';

		return '<a href="' . \esc_url( $url ) . '"' . $class . '>' . \esc_html( $label ) . ' <span class="count">(' . (int) $count . ')</span></a>';
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="item[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Type column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_type( $item ) {
		if ( $this->is_schedule_type() ) {
			return \esc_html__( 'Scheduled', 'bulk-actions-manager' );
		}

		if ( ! empty( $item->parent_job_id ) ) {
			return \esc_html__( 'Undo', 'bulk-actions-manager' );
		}

		return \esc_html__( 'One-time', 'bulk-actions-manager' );
	}

	/**
	 * Name column with row actions.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_name( $item ) {
		if ( $this->is_schedule_type() ) {
			return $this->column_name_schedule( $item );
		}

		return $this->column_name_job( $item );
	}

	/**
	 * Job name column with View / Edit / Clone / Cancel row actions.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	private function column_name_job( $item ) {
		$view_url = $this->page_url( array( 'job_id' => (int) $item->id ) );

		$actions = array(
			'view' => \sprintf( '<a href="%s">%s</a>', \esc_url( $view_url ), \esc_html__( 'View', 'bulk-actions-manager' ) ),
		);

		if ( 'running' === $item->status ) {
			$pause_url = \wp_nonce_url(
				$this->page_url(
					array(
						'bam_action' => 'pause_job',
						'job_id'     => (int) $item->id,
					)
				),
				'bam_pause_job_' . (int) $item->id
			);
			$actions['pause'] = \sprintf( '<a href="%s">%s</a>', \esc_url( $pause_url ), \esc_html__( 'Pause', 'bulk-actions-manager' ) );
		}

		// Edit: queued or paused jobs can be edited on the New Job page.
		if ( \in_array( $item->status, array( 'queued', 'paused' ), true ) ) {
			$edit_url = \add_query_arg(
				array(
					'page'   => 'bam-new-job',
					'job_id' => (int) $item->id,
				),
				\admin_url( 'admin.php' )
			);
			$actions['edit'] = \sprintf( '<a href="%s">%s</a>', \esc_url( $edit_url ), \esc_html__( 'Edit', 'bulk-actions-manager' ) );
		}

		if ( 'paused' === $item->status ) {
			$resume_url = \wp_nonce_url(
				$this->page_url(
					array(
						'bam_action' => 'resume_job',
						'job_id'     => (int) $item->id,
					)
				),
				'bam_resume_job_' . (int) $item->id
			);
			$actions['resume'] = \sprintf( '<a href="%s">%s</a>', \esc_url( $resume_url ), \esc_html__( 'Resume', 'bulk-actions-manager' ) );
		}

		// Clone: terminal jobs (completed/failed/cancelled) can be cloned.
		if ( Job_Manager::is_terminal_status( $item->status ) ) {
			$clone_url = \add_query_arg(
				array(
					'page'         => 'bam-new-job',
					'clone_job_id' => (int) $item->id,
				),
				\admin_url( 'admin.php' )
			);
			$actions['clone'] = \sprintf( '<a href="%s">%s</a>', \esc_url( $clone_url ), \esc_html__( 'Clone', 'bulk-actions-manager' ) );

			$delete_url = \wp_nonce_url(
				$this->page_url(
					array(
						'bam_action' => 'delete_job',
						'job_id'     => (int) $item->id,
					)
				),
				'bam_delete_job_' . (int) $item->id
			);
			$actions['delete'] = \sprintf(
				'<a href="%1$s" class="bam-delete-job-link">%2$s</a>',
				\esc_url( $delete_url ),
				\esc_html__( 'Delete', 'bulk-actions-manager' )
			);
		}

		if ( \in_array( $item->status, array( 'running', 'queued', 'paused' ), true ) ) {
			$cancel_url = \wp_nonce_url(
				$this->page_url(
					array(
						'bam_action' => 'cancel_job',
						'job_id'     => (int) $item->id,
					)
				),
				'bam_cancel_job_' . (int) $item->id
			);
			$actions['cancel'] = \sprintf( '<a href="%s">%s</a>', \esc_url( $cancel_url ), \esc_html__( 'Cancel', 'bulk-actions-manager' ) );
		}

		return \sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>%3$s',
			\esc_url( $view_url ),
			\esc_html( $item->name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Schedule name column.
	 * Edit → New Job page with schedule_id prefilled.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	private function column_name_schedule( $item ) {
		$edit_url = \add_query_arg(
			array(
				'page'        => 'bam-new-job',
				'schedule_id' => (int) $item->id,
			),
			\admin_url( 'admin.php' )
		);

		$delete_url = \wp_nonce_url(
			$this->page_url(
				array(
					'type'        => 'schedule',
					'bam_action'  => 'delete_schedule',
					'schedule_id' => (int) $item->id,
				)
			),
			'bam_delete_schedule_' . (int) $item->id
		);

		$run_url = \wp_nonce_url(
			$this->page_url(
				array(
					'type'        => 'schedule',
					'bam_action'  => 'run_schedule',
					'schedule_id' => (int) $item->id,
				)
			),
			'bam_run_schedule_' . (int) $item->id
		);

		$actions = array(
			'edit'   => \sprintf( '<a href="%s">%s</a>', \esc_url( $edit_url ), \esc_html__( 'Edit', 'bulk-actions-manager' ) ),
			'run'    => \sprintf( '<a href="%s">%s</a>', \esc_url( $run_url ), \esc_html__( 'Run Now', 'bulk-actions-manager' ) ),
			'delete' => \sprintf( '<a href="%s">%s</a>', \esc_url( $delete_url ), \esc_html__( 'Delete', 'bulk-actions-manager' ) ),
		);

		return \sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>%3$s',
			\esc_url( $edit_url ),
			\esc_html( $item->name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * ID column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_id( $item ) {
		return (string) (int) $item->id;
	}

	/**
	 * Action column with human-readable label.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_action_type( $item ) {
		return esc_html( \BAM\Admin\Admin_UI::action_label( $item->action_type ) );
	}

	/**
	 * Records column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_records( $item ) {
		return (int) $item->processed_items . ' / ' . (int) $item->total_items;
	}

	/**
	 * Status column with badge.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_status( $item ) {
		return Admin_UI::status_badge( $item->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Active column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_is_active( $item ) {
		return (int) $item->is_active ? \esc_html__( 'Yes', 'bulk-actions-manager' ) : \esc_html__( 'No', 'bulk-actions-manager' );
	}

	/**
	 * Next run column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_next_run_at( $item ) {
		return $item->next_run_at ? \esc_html( $item->next_run_at ) : '-';
	}

	/**
	 * Last run column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_last_run_at( $item ) {
		if ( ! $item->last_run_at ) {
			return '-';
		}

		if ( ! empty( $item->last_job_id ) ) {
			$url = $this->page_url(
				array(
					'job_id' => (int) $item->last_job_id,
				)
			);

			return \sprintf(
				'<a href="%1$s">%2$s</a>',
				\esc_url( $url ),
				\esc_html( $item->last_run_at )
			);
		}

		return \esc_html( $item->last_run_at );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_page_slug() {
		return 'bam-jobs';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function page_url( array $args = array() ) {
		$defaults = array( 'page' => 'bam-jobs' );
		if ( $this->is_schedule_type() && ! isset( $args['type'] ) && ! isset( $args['job_id'] ) ) {
			$defaults['type'] = 'schedule';
		}

		return \add_query_arg( \wp_parse_args( $args, $defaults ), \admin_url( 'admin.php' ) );
	}
}
