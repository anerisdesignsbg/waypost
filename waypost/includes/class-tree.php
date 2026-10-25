<?php
/**
 * Builds and caches the documentation tree.
 *
 * Node shape:
 *   type     'term' | 'post'
 *   id       term ID or post ID
 *   post_id  the post a node links to (post nodes: itself; term nodes: linked post or 0)
 *   title    label
 *   url      link or '' when the node is a plain heading
 *   children child nodes
 *   file     only on documents (PDF, Word and so on): the badge text, e.g. PDF.
 *            Documents link to the file and are left out of reading order.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Tree {

	const CACHE_KEY    = 'waypost_tree';
	const ORDER_OPTION = 'waypost_order';
	const LINK_META    = 'waypost_linked_post';

	private static $flat = null;
	/** Section trail of each document in the menu: [ attachment_id => trail ]. */
	private static $files = array();

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'maybe_flush_post' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'flush' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'maybe_flush_terms' ), 10, 4 );
		add_action( 'created_term', array( __CLASS__, 'maybe_flush_tax' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'maybe_flush_tax' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'maybe_flush_tax' ), 10, 3 );
	}

	public static function flush() {
		delete_transient( self::CACHE_KEY );
		self::$flat = null;
	}

	public static function maybe_flush_post( $post_id, $post ) {
		if ( $post && in_array( $post->post_type, Waypost_Settings::post_types(), true ) ) {
			self::flush();
		}
	}

	public static function maybe_flush_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( $taxonomy === Waypost_Settings::taxonomy() ) {
			self::flush();
		}
	}

	public static function maybe_flush_tax( $term_id, $tt_id, $taxonomy ) {
		if ( $taxonomy === Waypost_Settings::taxonomy() ) {
			self::flush();
		}
	}

	/** Cached tree for the front end. */
	public static function get() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$tree = self::build();
		set_transient( self::CACHE_KEY, $tree, 12 * HOUR_IN_SECONDS );
		return $tree;
	}

	/** Uncached build, used by the admin ordering screen. */
	public static function build( $include_empty = false ) {
		$tax = Waypost_Settings::taxonomy();
		if ( ! taxonomy_exists( $tax ) ) {
			return array();
		}

		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$by_id    = array();
		$children = array();
		foreach ( $terms as $t ) {
			$by_id[ $t->term_id ]      = $t;
			$children[ $t->parent ][] = $t->term_id;
		}

		$ctx = array(
			'terms'    => $by_id,
			'children' => $children,
			'posts'    => self::posts_by_term( array_keys( $by_id ) ),
			'files'    => Waypost_Settings::get( 'documents_menu' ) ? Waypost_Documents::by_term( array_keys( $by_id ) ) : array(),
			'order'    => (array) get_option( self::ORDER_OPTION, array() ),
			'empty'    => $include_empty,
		);

		return self::term_nodes( 0, $ctx );
	}

	private static function term_nodes( $parent, array &$ctx ) {
		if ( empty( $ctx['children'][ $parent ] ) ) {
			return array();
		}
		$ids   = $ctx['children'][ $parent ];
		$order = $ctx['order'];
		$terms = $ctx['terms'];

		usort(
			$ids,
			function ( $a, $b ) use ( $order, $terms ) {
				$oa = isset( $order[ 'term_' . $a ] ) ? $order[ 'term_' . $a ] : PHP_INT_MAX;
				$ob = isset( $order[ 'term_' . $b ] ) ? $order[ 'term_' . $b ] : PHP_INT_MAX;
				if ( $oa !== $ob ) {
					return $oa < $ob ? -1 : 1;
				}
				return strnatcasecmp( $terms[ $a ]->name, $terms[ $b ]->name );
			}
		);

		$nodes = array();
		foreach ( $ids as $tid ) {
			$term   = $terms[ $tid ];
			$linked = (int) get_term_meta( $tid, self::LINK_META, true );
			$url    = '';
			if ( $linked && 'publish' === get_post_status( $linked ) ) {
				$url = get_permalink( $linked );
			} else {
				$linked = 0;
			}

			$sub_terms = self::term_nodes( $tid, $ctx );
			$posts     = array_merge( self::post_nodes( $tid, $ctx, $linked ), self::file_nodes( $tid, $ctx ) );

			if ( ! $ctx['empty'] && ! $sub_terms && ! $posts && ! $url ) {
				continue; // A section with nothing in it would be a dead heading.
			}

			$nodes[] = array(
				'type'     => 'term',
				'id'       => $tid,
				'post_id'  => $linked,
				'title'    => $term->name,
				'url'      => $url,
				'children' => self::merge( $tid, $sub_terms, $posts, $order ),
			);
		}
		return $nodes;
	}

	private static function post_nodes( $tid, array &$ctx, $exclude ) {
		if ( empty( $ctx['posts'][ $tid ] ) ) {
			return array();
		}
		$list  = $ctx['posts'][ $tid ];
		unset( $list[ $exclude ] );
		$order = $ctx['order'];

		uksort(
			$list,
			function ( $a, $b ) use ( $order, $list ) {
				$oa = isset( $order[ 'post_' . $a ] ) ? $order[ 'post_' . $a ] : PHP_INT_MAX;
				$ob = isset( $order[ 'post_' . $b ] ) ? $order[ 'post_' . $b ] : PHP_INT_MAX;
				if ( $oa !== $ob ) {
					return $oa < $ob ? -1 : 1;
				}
				return strnatcasecmp( $list[ $a ], $list[ $b ] );
			}
		);

		$nodes = array();
		foreach ( $list as $pid => $title ) {
			$nodes[] = array(
				'type'     => 'post',
				'id'       => $pid,
				'post_id'  => $pid,
				'title'    => $title,
				'url'      => get_permalink( $pid ),
				'children' => array(),
			);
		}
		return $nodes;
	}

	/** Documents in a section, after its pages unless the order screen says otherwise. */
	private static function file_nodes( $tid, array &$ctx ) {
		if ( empty( $ctx['files'][ $tid ] ) ) {
			return array();
		}
		$list = $ctx['files'][ $tid ];
		uasort( $list, 'strnatcasecmp' );
		$nodes = array();
		foreach ( $list as $id => $title ) {
			$nodes[] = array(
				'type'     => 'post',
				'id'       => $id,
				'post_id'  => $id,
				'title'    => $title,
				'url'      => wp_get_attachment_url( $id ),
				'children' => array(),
				'file'     => Waypost_Documents::badge( $id ),
			);
		}
		return $nodes;
	}

	/** Sub-sections and pages share one sortable list inside a section. */
	private static function merge( $parent, array $terms, array $posts, array $order ) {
		$prefix = 'child_' . $parent . '_';
		$items  = array();
		foreach ( $terms as $n ) {
			$items[ $prefix . 'term' . $n['id'] ] = $n;
		}
		foreach ( $posts as $n ) {
			$items[ $prefix . 'post' . $n['id'] ] = $n;
		}

		$has_order = false;
		foreach ( array_keys( $items ) as $k ) {
			if ( isset( $order[ $k ] ) ) {
				$has_order = true;
				break;
			}
		}
		if ( ! $has_order ) {
			return array_merge( $terms, $posts );
		}

		$keys = array_keys( $items );
		$pos  = array_flip( $keys ); // Original position breaks ties for new items.
		usort(
			$keys,
			function ( $a, $b ) use ( $order, $pos ) {
				$oa = isset( $order[ $a ] ) ? $order[ $a ] : PHP_INT_MAX;
				$ob = isset( $order[ $b ] ) ? $order[ $b ] : PHP_INT_MAX;
				if ( $oa !== $ob ) {
					return $oa < $ob ? -1 : 1;
				}
				return $pos[ $a ] - $pos[ $b ];
			}
		);
		$out = array();
		foreach ( $keys as $k ) {
			$out[] = $items[ $k ];
		}
		return $out;
	}

	/** [ term_id => [ post_id => title ] ] for published docs, in two queries. */
	public static function posts_by_term( array $term_ids ) {
		if ( ! $term_ids ) {
			return array();
		}
		$tax = Waypost_Settings::taxonomy();
		$ids = get_posts(
			array(
				'post_type'        => Waypost_Settings::post_types(),
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'tax_query'        => array(
					array(
						'taxonomy'         => $tax,
						'field'            => 'term_id',
						'terms'            => $term_ids,
						'include_children' => false,
					),
				),
			)
		);
		if ( ! $ids ) {
			return array();
		}

		$rel = wp_get_object_terms( $ids, $tax, array( 'fields' => 'all_with_object_id' ) );
		if ( is_wp_error( $rel ) ) {
			return array();
		}
		_prime_post_caches( $ids, false, false );

		$out = array();
		foreach ( $rel as $t ) {
			$out[ $t->term_id ][ (int) $t->object_id ] = get_the_title( $t->object_id );
		}
		return $out;
	}

	// ── Navigation helpers ──────────────────────────────────────────────────

	/** Linkable nodes in reading order, each with its ancestor trail. */
	public static function flat() {
		if ( null !== self::$flat ) {
			return self::$flat;
		}
		self::$flat  = array();
		self::$files = array();
		self::walk( self::get(), array() );
		return self::$flat;
	}

	private static function walk( array $nodes, array $trail ) {
		foreach ( $nodes as $n ) {
			if ( ! empty( $n['file'] ) ) {
				self::$files[ $n['post_id'] ] = $trail;
				continue;
			}
			if ( $n['url'] && $n['post_id'] ) {
				$dupe = false;
				foreach ( self::$flat as $f ) {
					if ( $f['post_id'] === $n['post_id'] ) {
						$dupe = true;
						break;
					}
				}
				if ( ! $dupe ) {
					self::$flat[] = array(
						'post_id' => $n['post_id'],
						'title'   => $n['title'],
						'url'     => $n['url'],
						'trail'   => $trail,
					);
				}
			}
			if ( $n['children'] ) {
				$next   = $trail;
				$next[] = array( 'title' => $n['title'], 'url' => $n['url'] );
				self::walk( $n['children'], $next );
			}
		}
	}

	/** Section trail of a document in the menu, or null. */
	public static function file_trail( $post_id ) {
		self::flat();
		return isset( self::$files[ $post_id ] ) ? self::$files[ $post_id ] : null;
	}

	/** Position of a post in reading order, or -1. */
	public static function index_of( $post_id ) {
		foreach ( self::flat() as $i => $f ) {
			if ( (int) $f['post_id'] === (int) $post_id ) {
				return $i;
			}
		}
		return -1;
	}

	/** Is this post part of the docs? */
	public static function is_doc( $post_id ) {
		return $post_id && self::index_of( $post_id ) >= 0;
	}
}
