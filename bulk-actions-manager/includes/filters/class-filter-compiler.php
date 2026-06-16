<?php
/**
 * Compiles filter JSON into WP_Query arguments.
 *
 * @package BulkActionsManager
 */

namespace BAM\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Class Filter_Compiler
 */
class Filter_Compiler {

	/**
	 * Content length filter callback IDs.
	 *
	 * @var array<int, string>
	 */
	private static $clause_filters = array();

	/**
	 * Compile filter payload to WP_Query args.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @return array<string, mixed>
	 */
	public static function compile( array $payload ) {
		self::$clause_filters = array();

		$post_types = isset( $payload['post_type'] ) ? array_map( 'sanitize_key', (array) $payload['post_type'] ) : array( 'post' );

		$args = array(
			'post_type'              => count( $post_types ) === 1 ? $post_types[0] : $post_types,
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
		);

		$meta_query  = array();
		$tax_query   = array();
		$date_query  = array();
		$title_search = '';
		$content_search = '';

		$conditions = isset( $payload['conditions'] ) ? (array) $payload['conditions'] : array();
		$logic      = isset( $payload['logic'] ) && 'OR' === strtoupper( $payload['logic'] ) ? 'OR' : 'AND';

		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}

			if ( isset( $condition['type'] ) && 'group' === $condition['type'] ) {
				$group_args = self::compile(
					array(
						'post_type'  => $post_types,
						'logic'      => $condition['logic'] ?? 'AND',
						'conditions' => $condition['conditions'] ?? array(),
					)
				);
				// Nested groups handled via sub-query IDs intersection - simplified for v1.
				continue;
			}

			self::apply_condition( $condition, $args, $meta_query, $tax_query, $date_query, $title_search, $content_search );
		}

		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = $logic;
			}
			$args['meta_query'] = $meta_query;
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = $logic;
			}
			$args['tax_query'] = $tax_query;
		}

		if ( ! empty( $date_query ) ) {
			$args['date_query'] = $date_query;
		}

		if ( $title_search ) {
			$args['s'] = $title_search;
		}

		if ( ! empty( self::$clause_filters ) ) {
			foreach ( self::$clause_filters as $filter_id ) {
				add_filter( 'posts_clauses', $filter_id, 10, 2 );
			}
		}

		/**
		 * Filter compiled WP_Query arguments.
		 *
		 * @param array<string, mixed> $args    Query args.
		 * @param array<string, mixed> $payload Original filter payload.
		 */
		return apply_filters( 'bam_compile_query_args', $args, $payload );
	}

	/**
	 * Apply a single condition.
	 *
	 * @param array<string, mixed>  $condition       Condition.
	 * @param array<string, mixed>  $args            Query args (by ref).
	 * @param array<string, mixed>  $meta_query      Meta query (by ref).
	 * @param array<string, mixed>  $tax_query       Tax query (by ref).
	 * @param array<string, mixed>  $date_query      Date query (by ref).
	 * @param string                $title_search    Title search (by ref).
	 * @param string                $content_search  Content search (by ref).
	 */
	private static function apply_condition( $condition, &$args, &$meta_query, &$tax_query, &$date_query, &$title_search, &$content_search ) {
		$type     = $condition['type'] ?? '';
		$operator = $condition['operator'] ?? '';
		$value    = $condition['value'] ?? '';

		switch ( $type ) {
			case 'status':
				$statuses = is_array( $value ) ? $value : array( $value );
				if ( 'not_in' === $operator ) {
					$args['post_status'] = array_diff(
						array_keys( get_post_stati() ),
						array_map( 'sanitize_key', $statuses )
					);
				} else {
					$args['post_status'] = array_map( 'sanitize_key', $statuses );
				}
				break;

			case 'taxonomy':
				$taxonomy = sanitize_key( $condition['taxonomy'] ?? 'category' );
				$term_ids = array_map( 'absint', (array) $value );
				if ( empty( $term_ids ) ) {
					break;
				}
				$tax_clause = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_ids,
				);
				if ( in_array( $operator, array( 'not_equals', 'not_contains' ), true ) ) {
					$tax_clause['operator'] = 'NOT IN';
				}
				$tax_query[] = $tax_clause;
				break;

			case 'author':
				$authors = array_map( 'absint', (array) $value );
				if ( 'not_in' === $operator ) {
					$args['author__not_in'] = $authors;
				} else {
					$args['author__in'] = $authors;
				}
				break;

			case 'date':
				$field = $condition['field'] ?? 'post_date';
				$column = 'post_modified' === $field ? 'post_modified' : 'post_date';
				$date_clause = array( 'column' => $column );

				if ( 'between' === $operator && is_array( $value ) ) {
					$date_clause['after']     = sanitize_text_field( $value[0] ?? '' );
					$date_clause['before']    = sanitize_text_field( $value[1] ?? '' );
					$date_clause['inclusive'] = true;
				} elseif ( 'after' === $operator ) {
					$date_clause['after'] = sanitize_text_field( (string) $value );
				} elseif ( 'before' === $operator ) {
					$date_clause['before'] = sanitize_text_field( (string) $value );
				}
				$date_query[] = $date_clause;
				break;

			case 'meta':
				$key = sanitize_text_field( (string) ( $condition['key'] ?? $value ) );
				if ( 'missing' === $operator ) {
					$meta_query[] = array(
						'key'     => $key,
						'compare' => 'NOT EXISTS',
					);
				} else {
					$meta_query[] = array(
						'key'     => $key,
						'compare' => 'EXISTS',
					);
				}
				break;

			case 'meta_value':
				$key   = sanitize_text_field( (string) ( $condition['key'] ?? '' ) );
				$val   = $condition['value'] ?? '';
				$compare = 'equals' === $operator ? '=' : ( 'not_equals' === $operator ? '!=' : ( 'contains' === $operator ? 'LIKE' : 'NOT EXISTS' ) );
				if ( 'empty' === $operator ) {
					$meta_query[] = array(
						'relation' => 'OR',
						array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
						array( 'key' => $key, 'value' => '', 'compare' => '=' ),
					);
				} else {
					$meta_query[] = array(
						'key'     => $key,
						'value'   => 'contains' === $operator ? '%' . $val . '%' : $val,
						'compare' => $compare,
					);
				}
				break;

			case 'content':
				$field = $condition['field'] ?? 'title';
				if ( 'title' === $field ) {
					$title_search = sanitize_text_field( (string) $value );
				} else {
					$filter_id = 'bam_content_filter_' . wp_unique_id();
					self::$clause_filters[] = function( $clauses, $query ) use ( $value, $operator ) {
						global $wpdb;
						if ( ! $query->get( 'bam_content_filter' ) ) {
							return $clauses;
						}
						$like = '%' . $wpdb->esc_like( (string) $value ) . '%';
						if ( 'not_contains' === $operator ) {
							$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_content NOT LIKE %s", $like );
						} else {
							$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_content LIKE %s", $like );
						}
						return $clauses;
					};
					$args['bam_content_filter'] = true;
				}
				break;

			case 'featured_image':
				if ( 'has' === $operator ) {
					$meta_query[] = array(
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					);
				} else {
					$meta_query[] = array(
						'relation' => 'OR',
						array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ),
						array( 'key' => '_thumbnail_id', 'value' => '0', 'compare' => '=' ),
					);
				}
				break;

			case 'content_length':
				$metric = $condition['metric'] ?? 'words';
				$threshold = absint( $value );
				$filter_id = 'bam_length_filter_' . wp_unique_id();
				self::$clause_filters[] = function( $clauses, $query ) use ( $metric, $threshold, $operator ) {
					global $wpdb;
					if ( ! $query->get( 'bam_length_filter' ) ) {
						return $clauses;
					}
					if ( 'chars' === $metric ) {
						$expr = "CHAR_LENGTH({$wpdb->posts}.post_content)";
					} else {
						$expr = "(CHAR_LENGTH({$wpdb->posts}.post_content) - CHAR_LENGTH(REPLACE({$wpdb->posts}.post_content, ' ', '')) + 1)";
					}
					$cmp = 'less_than' === $operator ? '<' : '>';
					$clauses['where'] .= $wpdb->prepare( " AND {$expr} {$cmp} %d", $threshold ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					return $clauses;
				};
				$args['bam_length_filter'] = true;
				break;

			case 'seo_yoast':
				self::apply_yoast_condition( $operator, $meta_query );
				break;

			case 'seo_rankmath':
				self::apply_rankmath_condition( $operator, $meta_query );
				break;
		}
	}

	/**
	 * Yoast SEO conditions.
	 *
	 * @param string               $operator   Operator.
	 * @param array<string, mixed> $meta_query Meta query array.
	 */
	private static function apply_yoast_condition( $operator, &$meta_query ) {
		switch ( $operator ) {
			case 'empty_focus':
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => '_yoast_wpseo_focuskw', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_yoast_wpseo_focuskw', 'value' => '', 'compare' => '=' ),
				);
				break;
			case 'missing_title':
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => '_yoast_wpseo_title', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_yoast_wpseo_title', 'value' => '', 'compare' => '=' ),
				);
				break;
			case 'missing_description':
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => '_yoast_wpseo_metadesc', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_yoast_wpseo_metadesc', 'value' => '', 'compare' => '=' ),
				);
				break;
		}
	}

	/**
	 * Rank Math SEO conditions.
	 *
	 * @param string               $operator   Operator.
	 * @param array<string, mixed> $meta_query Meta query array.
	 */
	private static function apply_rankmath_condition( $operator, &$meta_query ) {
		switch ( $operator ) {
			case 'missing_focus':
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => 'rank_math_focus_keyword', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'rank_math_focus_keyword', 'value' => '', 'compare' => '=' ),
				);
				break;
			case 'missing_description':
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => 'rank_math_description', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'rank_math_description', 'value' => '', 'compare' => '=' ),
				);
				break;
		}
	}

	/**
	 * Remove clause filters after query.
	 */
	public static function cleanup_clause_filters() {
		foreach ( self::$clause_filters as $filter_id ) {
			remove_filter( 'posts_clauses', $filter_id, 10 );
		}
		self::$clause_filters = array();
	}

	/**
	 * Run query and return IDs + total.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @param int                  $limit   Preview limit (0 = all IDs).
	 * @return array{total: int, ids: array<int, int>}
	 */
	public static function query( array $payload, $limit = 0 ) {
		if ( $limit > 0 ) {
			return self::query_page( $payload, 1, $limit );
		}

		$all_ids = self::resolve_ids( $payload );
		return array(
			'total' => count( $all_ids ),
			'ids'   => $all_ids,
		);
	}

	/**
	 * Paginated preview query - uses found_posts, does not load all matching IDs.
	 *
	 * @param array<string, mixed> $payload  Filter payload.
	 * @param int                  $page     Page number.
	 * @param int                  $per_page Posts per page.
	 * @return array{total: int, ids: array<int, int>}
	 */
	public static function query_page( array $payload, $page = 1, $per_page = 20 ) {
		$args = self::compile( $payload );
		$args['posts_per_page']         = max( 1, (int) $per_page );
		$args['paged']                  = max( 1, (int) $page );
		$args['fields']                 = 'ids';
		$args['no_found_rows']          = false;
		$args['update_post_meta_cache'] = false;
		$args['update_post_term_cache'] = false;

		$query = new \WP_Query( $args );
		self::cleanup_clause_filters();

		return array(
			'total' => (int) $query->found_posts,
			'ids'   => array_map( 'intval', $query->posts ),
		);
	}

	/**
	 * Aggregate status and category counts for preview summary.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @param int                  $total   Total from query_page (avoids extra count query).
	 * @return array{statuses: array<string, int>, categories: array<string, int>, categories_limited: bool}
	 */
	public static function get_aggregates( array $payload, $total = 0 ) {
		$result = array(
			'statuses'            => array(),
			'categories'          => array(),
			'categories_limited'    => false,
		);

		if ( $total <= 0 ) {
			return $result;
		}

		$locked_status = self::get_locked_status( $payload );

		if ( $locked_status ) {
			$result['statuses'][ $locked_status ] = $total;
		} else {
			foreach ( get_post_stati( array( 'show_in_admin_all_list' => true ), 'names' ) as $status ) {
				$status_payload = self::payload_with_status( $payload, $status );
				$count          = self::query_page( $status_payload, 1, 1 )['total'];
				if ( $count > 0 ) {
					$result['statuses'][ $status ] = $count;
				}
			}
		}

		if ( $total > 10000 ) {
			$result['categories_limited'] = true;
			return $result;
		}

		$result['categories'] = self::get_top_categories( $payload );
		return $result;
	}

	/**
	 * Get locked post status from payload if filtered to a single status.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @return string
	 */
	private static function get_locked_status( array $payload ) {
		foreach ( (array) ( $payload['conditions'] ?? array() ) as $condition ) {
			if ( ! is_array( $condition ) || ( $condition['type'] ?? '' ) !== 'status' ) {
				continue;
			}
			$values = array_map( 'sanitize_key', (array) ( $condition['value'] ?? array() ) );
			if ( 1 === count( $values ) && 'not_in' !== ( $condition['operator'] ?? '' ) ) {
				return $values[0];
			}
		}
		return '';
	}

	/**
	 * Clone payload with a single status condition.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @param string               $status  Post status slug.
	 * @return array<string, mixed>
	 */
	private static function payload_with_status( array $payload, $status ) {
		$conditions = array();
		foreach ( (array) ( $payload['conditions'] ?? array() ) as $condition ) {
			if ( is_array( $condition ) && ( $condition['type'] ?? '' ) !== 'status' ) {
				$conditions[] = $condition;
			}
		}
		$conditions[] = array(
			'type'     => 'status',
			'operator' => 'in',
			'value'    => array( sanitize_key( $status ) ),
		);

		return array_merge(
			$payload,
			array(
				'conditions' => $conditions,
			)
		);
	}

	/**
	 * Top category counts via filtered subquery (no full ID load in PHP).
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @return array<string, int> Term name => count.
	 */
	private static function get_top_categories( array $payload ) {
		global $wpdb;

		$sql = self::get_filter_subquery_sql( $payload );
		if ( ! $sql ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			"SELECT t.name, COUNT(*) AS cnt
			FROM ( {$sql} ) AS bam_filtered
			INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = bam_filtered.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			GROUP BY t.term_id
			ORDER BY cnt DESC
			LIMIT 5"
		);

		$categories = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$categories[ (string) $row->name ] = (int) $row->cnt;
			}
		}

		return $categories;
	}

	/**
	 * Build inner SQL for filtered posts (IDs only, no LIMIT).
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @return string
	 */
	private static function get_filter_subquery_sql( array $payload ) {
		$args = self::compile( $payload );
		$args['posts_per_page']         = 1;
		$args['fields']                 = 'ids';
		$args['no_found_rows']          = true;
		$args['update_post_meta_cache'] = false;
		$args['update_post_term_cache'] = false;

		$query = new \WP_Query( $args );
		self::cleanup_clause_filters();

		if ( empty( $query->request ) ) {
			return '';
		}

		$sql = preg_replace( '/\sLIMIT\s\d+(,\s*\d+)?\s*$/i', '', $query->request );
		return is_string( $sql ) ? $sql : '';
	}

	/**
	 * Resolve matching post IDs including nested condition groups.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @return array<int, int>
	 */
	public static function resolve_ids( array $payload ) {
		$post_types = isset( $payload['post_type'] ) ? array_map( 'sanitize_key', (array) $payload['post_type'] ) : array( 'post' );
		$conditions = isset( $payload['conditions'] ) ? (array) $payload['conditions'] : array();
		$logic      = isset( $payload['logic'] ) && 'OR' === strtoupper( $payload['logic'] ) ? 'OR' : 'AND';

		if ( empty( $conditions ) ) {
			return self::query_flat_ids(
				array(
					'post_type'  => $post_types,
					'conditions' => array(),
				)
			);
		}

		$sets = array();
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}

			if ( isset( $condition['type'] ) && 'group' === $condition['type'] ) {
				$sets[] = self::resolve_ids(
					array(
						'post_type'  => $post_types,
						'logic'      => $condition['logic'] ?? 'AND',
						'conditions' => $condition['conditions'] ?? array(),
					)
				);
				continue;
			}

			$sets[] = self::query_flat_ids(
				array(
					'post_type'  => $post_types,
					'conditions' => array( $condition ),
				)
			);
		}

		if ( empty( $sets ) ) {
			return array();
		}

		if ( 'OR' === $logic ) {
			$result = array_values( array_unique( array_merge( ...$sets ) ) );
		} else {
			$result = array_shift( $sets );
			foreach ( $sets as $set ) {
				$result = array_values( array_intersect( $result, $set ) );
			}
		}

		/**
		 * Maximum number of post IDs returned by filter resolution.
		 *
		 * @param int $max Maximum IDs (default 100000).
		 */
		$max = (int) apply_filters( 'bam_max_filter_results', 100000 );
		if ( $max > 0 && count( $result ) > $max ) {
			$result = array_slice( $result, 0, $max );
		}

		return $result;
	}

	/**
	 * Query IDs for a flat (non-grouped) filter payload.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @return array<int, int>
	 */
	private static function query_flat_ids( array $payload ) {
		$args = self::compile( $payload );
		$all_ids = array();
		$paged   = 1;
		$args['posts_per_page'] = 500;
		$args['fields']         = 'ids';

		/**
		 * Maximum number of post IDs returned by filter resolution.
		 *
		 * @param int $max Maximum IDs (default 100000).
		 */
		$max = (int) apply_filters( 'bam_max_filter_results', 100000 );

		do {
			$args['paged'] = $paged;
			$query = new \WP_Query( $args );
			$all_ids = array_merge( $all_ids, array_map( 'intval', $query->posts ) );
			if ( $max > 0 && count( $all_ids ) >= $max ) {
				$all_ids = array_slice( $all_ids, 0, $max );
				break;
			}
			$paged++;
		} while ( $paged <= $query->max_num_pages );

		self::cleanup_clause_filters();

		return $all_ids;
	}

	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	private static function is_yoast_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options', false );
	}

	/**
	 * Whether Rank Math is active.
	 *
	 * @return bool
	 */
	private static function is_rankmath_active() {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath\\Helper', false );
	}

	/**
	 * Build filter payload from edit.php-style admin request args.
	 *
	 * @param array<string, mixed> $request GET request parameters.
	 * @return array<string, mixed>
	 */
	public static function from_admin_request( array $request ) {
		$post_type = ! empty( $request['post_type'] ) ? sanitize_key( $request['post_type'] ) : 'post';
		$conditions = array();

		$post_status = ! empty( $request['post_status'] ) ? sanitize_key( $request['post_status'] ) : 'all';
		if ( $post_status && 'all' !== $post_status ) {
			$conditions[] = array(
				'type'     => 'status',
				'operator' => 'in',
				'value'    => array( $post_status ),
			);
		}

		if ( ! empty( $request['cat'] ) ) {
			$conditions[] = array(
				'type'     => 'taxonomy',
				'taxonomy' => 'category',
				'operator' => 'in',
				'value'    => array( absint( $request['cat'] ) ),
			);
		}

		if ( ! empty( $request['tag_id'] ) ) {
			$conditions[] = array(
				'type'     => 'taxonomy',
				'taxonomy' => 'post_tag',
				'operator' => 'in',
				'value'    => array( absint( $request['tag_id'] ) ),
			);
		}

		if ( ! empty( $request['author'] ) ) {
			$conditions[] = array(
				'type'     => 'author',
				'operator' => 'in',
				'value'    => array( absint( $request['author'] ) ),
			);
		}

		if ( ! empty( $request['m'] ) ) {
			$m = sanitize_text_field( (string) $request['m'] );
			if ( preg_match( '/^\d{6}$/', $m ) ) {
				$year  = substr( $m, 0, 4 );
				$month = substr( $m, 4, 2 );
				$start = $year . '-' . $month . '-01 00:00:00';
				$end   = gmdate( 'Y-m-t 23:59:59', strtotime( $start ) );
				$conditions[] = array(
					'type'     => 'date',
					'field'    => 'post_date',
					'operator' => 'between',
					'value'    => array( $start, $end ),
				);
			}
		}

		if ( ! empty( $request['s'] ) ) {
			$conditions[] = array(
				'type'     => 'content',
				'field'    => 'title',
				'operator' => 'contains',
				'value'    => sanitize_text_field( (string) $request['s'] ),
			);
		}

		if ( ! empty( $request['seo-filter'] ) && self::is_yoast_active() ) {
			$yoast_map = array(
				'empty-fk'          => 'empty_focus',
				'no-focuskw'        => 'empty_focus',
				'missing-seo-title' => 'missing_title',
				'missing-metadesc'  => 'missing_description',
			);
			$seo_key = sanitize_key( (string) $request['seo-filter'] );
			if ( isset( $yoast_map[ $seo_key ] ) ) {
				$conditions[] = array(
					'type'     => 'seo_yoast',
					'operator' => $yoast_map[ $seo_key ],
				);
			}
		}

		if ( ! empty( $request['rankmath-filter'] ) && self::is_rankmath_active() ) {
			$rm_map = array(
				'missing-focus-keyword' => 'missing_focus',
				'missing-description'   => 'missing_description',
			);
			$rm_key = sanitize_key( (string) $request['rankmath-filter'] );
			if ( isset( $rm_map[ $rm_key ] ) ) {
				$conditions[] = array(
					'type'     => 'seo_rankmath',
					'operator' => $rm_map[ $rm_key ],
				);
			}
		}

		if ( ! empty( $request['bam_meta_key'] ) ) {
			$meta_key = sanitize_text_field( (string) $request['bam_meta_key'] );
			$meta_op  = ! empty( $request['bam_meta_op'] ) ? sanitize_key( (string) $request['bam_meta_op'] ) : 'exists';
			if ( in_array( $meta_op, array( 'exists', 'missing' ), true ) ) {
				$conditions[] = array(
					'type'     => 'meta',
					'operator' => $meta_op,
					'key'      => $meta_key,
				);
			}
		}

		if ( ! empty( $request['bam_meta_value_key'] ) ) {
			$mv_key = sanitize_text_field( (string) $request['bam_meta_value_key'] );
			$mv_op  = ! empty( $request['bam_meta_value_op'] ) ? sanitize_key( (string) $request['bam_meta_value_op'] ) : 'equals';
			$mv_val = isset( $request['bam_meta_value'] ) ? sanitize_text_field( (string) $request['bam_meta_value'] ) : '';
			if ( in_array( $mv_op, array( 'equals', 'contains', 'empty' ), true ) ) {
				$conditions[] = array(
					'type'     => 'meta_value',
					'operator' => $mv_op,
					'key'      => $mv_key,
					'value'    => $mv_val,
				);
			}
		}

		if ( ! empty( $request['bam_featured'] ) ) {
			$featured = sanitize_key( (string) $request['bam_featured'] );
			if ( in_array( $featured, array( 'has', 'missing' ), true ) ) {
				$conditions[] = array(
					'type'     => 'featured_image',
					'operator' => $featured,
				);
			}
		}

		if ( ! empty( $request['bam_title'] ) ) {
			$conditions[] = array(
				'type'     => 'content',
				'field'    => 'title',
				'operator' => 'contains',
				'value'    => sanitize_text_field( (string) $request['bam_title'] ),
			);
		}

		if ( ! empty( $request['bam_content'] ) ) {
			$conditions[] = array(
				'type'     => 'content',
				'field'    => 'content',
				'operator' => 'contains',
				'value'    => sanitize_text_field( (string) $request['bam_content'] ),
			);
		}

		return array(
			'post_type'  => array( $post_type ),
			'logic'      => 'AND',
			'conditions' => $conditions,
		);
	}

	/**
	 * Format posts for preview table.
	 *
	 * @param array<int, int> $ids Post IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function format_preview_items( array $ids ) {
		$items = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$author = get_the_author_meta( 'display_name', $post->post_author );
			$items[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title ?: __( '(no title)', 'bulk-actions-manager' ),
				'type'   => $post->post_type,
				'status' => $post->post_status,
				'author' => $author,
				'date'   => $post->post_date,
			);
		}
		return $items;
	}
}
