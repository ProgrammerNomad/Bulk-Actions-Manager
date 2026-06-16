<?php
/**
 * Filter registry - available filter definitions for UI.
 *
 * @package BulkActionsManager
 */

namespace BAM\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Class Filter_Registry
 */
class Filter_Registry {

	/**
	 * Custom conditions registered via hook.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $custom = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'bam_register_filters', array( $this, 'fire_registration' ) );
	}

	/**
	 * Get all filter definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all() {
		$filters = array(
			array(
				'type'       => 'status',
				'label'      => __( 'Status', 'bulk-actions-manager' ),
				'operators'  => array( 'in', 'not_in' ),
				'value_type' => 'status_select',
			),
			array(
				'type'       => 'taxonomy',
				'label'      => __( 'Category', 'bulk-actions-manager' ),
				'operators'  => array( 'equals', 'not_equals' ),
				'value_type' => 'term_select',
				'taxonomy'   => 'category',
			),
			array(
				'type'       => 'taxonomy',
				'label'      => __( 'Tag', 'bulk-actions-manager' ),
				'operators'  => array( 'contains', 'not_contains' ),
				'value_type' => 'term_select',
				'taxonomy'   => 'post_tag',
			),
			array(
				'type'       => 'author',
				'label'      => __( 'Author', 'bulk-actions-manager' ),
				'operators'  => array( 'in', 'not_in' ),
				'value_type' => 'author_select',
			),
			array(
				'type'       => 'date',
				'label'      => __( 'Created Date', 'bulk-actions-manager' ),
				'operators'  => array( 'before', 'after', 'between' ),
				'value_type' => 'date',
				'field'      => 'post_date',
			),
			array(
				'type'       => 'date',
				'label'      => __( 'Modified Date', 'bulk-actions-manager' ),
				'operators'  => array( 'before', 'after', 'between' ),
				'value_type' => 'date',
				'field'      => 'post_modified',
			),
			array(
				'type'       => 'meta',
				'label'      => __( 'Meta Key', 'bulk-actions-manager' ),
				'operators'  => array( 'exists', 'missing' ),
				'value_type' => 'text',
			),
			array(
				'type'       => 'meta_value',
				'label'      => __( 'Meta Value', 'bulk-actions-manager' ),
				'operators'  => array( 'equals', 'not_equals', 'contains', 'empty' ),
				'value_type' => 'meta_value',
			),
			array(
				'type'       => 'content',
				'label'      => __( 'Title', 'bulk-actions-manager' ),
				'operators'  => array( 'contains', 'not_contains' ),
				'value_type' => 'text',
				'field'      => 'title',
			),
			array(
				'type'       => 'content',
				'label'      => __( 'Content', 'bulk-actions-manager' ),
				'operators'  => array( 'contains', 'not_contains' ),
				'value_type' => 'text',
				'field'      => 'content',
			),
			array(
				'type'       => 'featured_image',
				'label'      => __( 'Featured Image', 'bulk-actions-manager' ),
				'operators'  => array( 'has', 'missing' ),
				'value_type' => 'none',
			),
			array(
				'type'       => 'content_length',
				'label'      => __( 'Word Count', 'bulk-actions-manager' ),
				'operators'  => array( 'less_than', 'greater_than' ),
				'value_type' => 'number',
				'metric'     => 'words',
			),
			array(
				'type'       => 'content_length',
				'label'      => __( 'Character Count', 'bulk-actions-manager' ),
				'operators'  => array( 'less_than', 'greater_than' ),
				'value_type' => 'number',
				'metric'     => 'chars',
			),
		);

		if ( defined( 'WPSEO_VERSION' ) ) {
			$filters[] = array(
				'type'       => 'seo_yoast',
				'label'      => __( 'Yoast SEO', 'bulk-actions-manager' ),
				'operators'  => array( 'empty_focus', 'missing_title', 'missing_description' ),
				'value_type' => 'none',
			);
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$filters[] = array(
				'type'       => 'seo_rankmath',
				'label'      => __( 'Rank Math SEO', 'bulk-actions-manager' ),
				'operators'  => array( 'missing_focus', 'missing_description' ),
				'value_type' => 'none',
			);
		}

		/**
		 * Register custom filter conditions.
		 *
		 * @param Filter_Registry $registry Registry instance.
		 */
		do_action( 'bam_register_filters', $this );

		return array_merge( $filters, $this->custom );
	}

	/**
	 * Register a custom filter definition.
	 *
	 * @param array<string, mixed> $definition Filter definition.
	 */
	public function register( array $definition ) {
		$this->custom[] = $definition;
	}

	/**
	 * Fire registration hook.
	 */
	public function fire_registration() {
		// Hook placeholder for external registrations.
	}

	/**
	 * Get available post types for filtering.
	 *
	 * @return array<string, string>
	 */
	public static function get_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $types as $type ) {
			$result[ $type->name ] = $type->labels->singular_name;
		}
		return $result;
	}
}
