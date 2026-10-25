<?php
/**
 * Docs search: REST endpoint for instant results, the results page
 * shortcode, and the search log behind Search insights.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Search {

	const LOG_OPTION = 'waypost_search_log';
	const LOG_MAX    = 500;

	/** Plain text of each doc page, kept up to date on save. Search reads this instead of raw content. */
	const TEXT_META = '_waypost_text';
	/** Which post types and text version have been filled in for existing pages. */
	const TEXT_OPTION = 'waypost_text_ready';
	const TEXT_VERSION = 1;
	const TEXT_BATCH   = 200;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_text' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'fill_text' ) );
		add_filter( 'posts_search', array( __CLASS__, 'where' ), 10, 2 );
	}

	// ── Searchable text ─────────────────────────────────────────────────────

	/**
	 * Readable text of post content: page builder data unpacked, shortcode tags
	 * and HTML removed, with a space wherever a tag was so words do not run together.
	 */
	public static function plain_text( $content ) {
		$html = Waypost_Render::content_html( $content );
		$html = Waypost_Headings::strip_shortcode_tags( $html );
		$html = preg_replace( '/<(script|style)\b.*?<\/\1>/is', ' ', $html );
		// Block-level tags only: a space inside <em>Mag</em>pie would split the word.
		$html = preg_replace( '#<(/?)(p|h[1-6]|li|dt|dd|div|section|article|br|hr|td|th|tr|ul|ol|pre|blockquote|figcaption)\b#i', ' <$1$2', $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	public static function save_text( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! in_array( $post->post_type, Waypost_Settings::post_types(), true ) ) {
			return;
		}
		update_post_meta( $post_id, self::TEXT_META, wp_slash( self::plain_text( $post->post_content ) ) );
	}

	/**
	 * Fills in the text for pages saved before this existed, or of a newly ticked
	 * post type. Runs in batches on admin pages and before a search, and stops
	 * checking once everything is done.
	 */
	public static function fill_text() {
		static $ran = false;
		$key = self::TEXT_VERSION . ':' . implode( ',', Waypost_Settings::post_types() );
		if ( $ran || get_option( self::TEXT_OPTION ) === $key ) {
			return;
		}
		$ran = true;
		$ids = get_posts(
			array(
				'post_type'      => Waypost_Settings::post_types(),
				'post_status'    => 'any',
				'posts_per_page' => self::TEXT_BATCH,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( array( 'key' => self::TEXT_META, 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( $id, self::TEXT_META, wp_slash( self::plain_text( get_post_field( 'post_content', $id ) ) ) );
		}
		if ( count( $ids ) < self::TEXT_BATCH ) {
			update_option( self::TEXT_OPTION, $key, false );
		}
	}

	/**
	 * Matches every search word in the title or the page's plain text, instead of
	 * WordPress's match on raw content. Raw Divi 5 content holds layout data, so it
	 * finds words like "desktop" on every page, and it writes & and quotes as codes.
	 * A page without plain text yet falls back to its raw content.
	 */
	public static function where( $search, $query ) {
		global $wpdb;
		if ( ! $query->get( 'waypost' ) || '' === $search ) {
			return $search;
		}
		$terms = (array) $query->get( 'search_terms' );
		if ( ! $terms ) {
			return $search;
		}
		$and = array();
		foreach ( $terms as $term ) {
			$like  = '%' . $wpdb->esc_like( ltrim( $term, '-' ) ) . '%';
			$and[] = $wpdb->prepare(
				"({$wpdb->posts}.post_title LIKE %s
					OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s )
					OR ( {$wpdb->posts}.post_content LIKE %s AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = {$wpdb->posts}.ID AND m.meta_key = %s ) ))",
				$like,
				self::TEXT_META,
				$like,
				$like,
				self::TEXT_META
			);
		}
		// Attachments count only when they are readable documents, never images.
		$mimes = "'" . implode( "','", array_map( 'esc_sql', array_keys( Waypost_Documents::types() ) ) ) . "'";
		$and[] = "( {$wpdb->posts}.post_type <> 'attachment' OR {$wpdb->posts}.post_mime_type IN ( {$mimes} ) )";
		return ' AND ( ' . implode( ' AND ', $and ) . ' ) ';
	}

	public static function routes() {
		register_rest_route(
			'waypost/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'    => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'page' => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
					'per'  => array( 'type' => 'integer', 'default' => 8, 'sanitize_callback' => 'absint' ),
					'log'  => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
	}

	/** The page full results open on: the docs home (its search home lists them) or a separate results page. */
	public static function results_page_id() {
		$key  = 'home' === Waypost_Settings::get( 'results_target' ) ? 'home_page' : 'results_page';
		$page = (int) Waypost_Settings::get( $key );
		return ( $page && 'publish' === get_post_status( $page ) ) ? $page : 0;
	}

	public static function results_url() {
		$page = self::results_page_id();
		return $page ? get_permalink( $page ) : '';
	}

	public static function rest_search( WP_REST_Request $req ) {
		$q    = trim( (string) $req['q'] );
		$page = max( 1, (int) $req['page'] );
		if ( mb_strlen( $q ) < 2 ) {
			return rest_ensure_response( array( 'query' => $q, 'total' => 0, 'pages' => 0, 'results' => array() ) );
		}
		$data = self::run( $q, $page, max( 1, min( 50, (int) $req['per'] ) ) );
		if ( $req['log'] && 1 === $page ) {
			self::log( $q, $data['total'] );
		}
		return rest_ensure_response( $data );
	}

	/** Runs a docs search and returns plain data (no HTML). */
	public static function run( $q, $page = 1, $per_page = 10 ) {
		$tax = Waypost_Settings::taxonomy();
		$out = array( 'query' => $q, 'total' => 0, 'pages' => 0, 'results' => array() );
		if ( ! taxonomy_exists( $tax ) ) {
			return $out;
		}
		self::fill_text();

		$types  = Waypost_Settings::post_types();
		$status = array( 'publish' );
		if ( Waypost_Documents::enabled() && is_object_in_taxonomy( 'attachment', $tax ) ) {
			$types[]  = 'attachment'; // Limited to readable documents by where().
			$status[] = 'inherit';
		}

		$query = new WP_Query(
			array(
				'post_type'           => $types,
				'post_status'         => $status,
				's'                   => $q,
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => false,
				'waypost'             => 1, // Picked up by where().
				'tax_query'           => array(
					array(
						'taxonomy' => $tax,
						'operator' => 'EXISTS',
					),
				),
			)
		);

		$out['total'] = (int) $query->found_posts;
		$out['pages'] = (int) $query->max_num_pages;

		foreach ( $query->posts as $post ) {
			$text = get_post_meta( $post->ID, self::TEXT_META, true );
			$file = Waypost_Documents::badge( $post );
			if ( $file ) {
				$out['results'][] = array(
					'id'       => $post->ID,
					'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'url'      => wp_get_attachment_url( $post->ID ),
					'section'  => self::section_label( $post->ID ),
					'excerpt'  => self::excerpt( $text, $q ),
					'headings' => array(),
					'file'     => $file,
				);
				continue;
			}
			$url  = get_permalink( $post );
			$out['results'][] = array(
				'id'       => $post->ID,
				'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'url'      => $url,
				'section'  => self::section_label( $post->ID ),
				'excerpt'  => self::excerpt( '' !== $text ? $text : self::plain_text( $post->post_content ), $q ),
				'headings' => self::matching_headings( Waypost_Render::content_html( $post->post_content ), $q, $url ),
				'file'     => '',
			);
		}
		return $out;
	}

	private static function section_label( $post_id ) {
		$i = Waypost_Tree::index_of( $post_id );
		if ( $i < 0 ) {
			return self::document_label( $post_id );
		}
		$entry = Waypost_Tree::flat()[ $i ];
		$names = wp_list_pluck( $entry['trail'], 'title' );
		// A page used as a section heading belongs to that section.
		if ( $entry['title'] !== get_the_title( $post_id ) ) {
			$names[] = $entry['title'];
		}
		return implode( ' › ', $names );
	}

	/** Section path of a document: its place in the menu, or its first section when documents are not listed there. */
	private static function document_label( $post_id ) {
		$trail = Waypost_Tree::file_trail( $post_id );
		if ( null !== $trail ) {
			return implode( ' › ', wp_list_pluck( $trail, 'title' ) );
		}
		if ( 'attachment' !== get_post_type( $post_id ) ) {
			return '';
		}
		$tax   = Waypost_Settings::taxonomy();
		$terms = wp_get_object_terms( $post_id, $tax );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return '';
		}
		$names = array();
		foreach ( array_reverse( get_ancestors( $terms[0]->term_id, $tax, 'taxonomy' ) ) as $id ) {
			$t = get_term( $id, $tax );
			if ( $t && ! is_wp_error( $t ) ) {
				$names[] = $t->name;
			}
		}
		$names[] = $terms[0]->name;
		return implode( ' › ', $names );
	}

	/** Snippet of plain text (see plain_text()) centred on the first match. */
	public static function excerpt( $text, $q, $len = 170 ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		$pos = false;
		foreach ( self::words( $q ) as $w ) {
			$pos = mb_stripos( $text, $w );
			if ( false !== $pos ) {
				break;
			}
		}
		if ( false === $pos || $pos < 60 ) {
			$snippet = mb_substr( $text, 0, $len );
			return mb_strlen( $text ) > $len ? rtrim( $snippet ) . '…' : $snippet;
		}
		$start   = max( 0, $pos - 60 );
		$snippet = mb_substr( $text, $start, $len );
		return '…' . trim( $snippet ) . ( $start + $len < mb_strlen( $text ) ? '…' : '' );
	}

	/** Headings that contain a search word, with deep links. */
	private static function matching_headings( $content, $q, $url ) {
		$words = self::words( $q );
		$out   = array();
		foreach ( Waypost_Headings::extract( $content, array( 2, 3, 4 ) ) as $h ) {
			foreach ( $words as $w ) {
				if ( false !== mb_stripos( $h[1], $w ) ) {
					$out[] = array( 'text' => $h[1], 'url' => $url . '#' . $h[2] );
					break;
				}
			}
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	private static function words( $q ) {
		$words = preg_split( '/\s+/u', mb_strtolower( $q ) );
		return array_values( array_filter( $words, function ( $w ) { return mb_strlen( $w ) > 1; } ) );
	}

	// ── Results page ────────────────────────────────────────────────────────

	public static function results_shortcode( $atts = array() ) {
		$q    = isset( $_GET['dq'] ) ? sanitize_text_field( wp_unslash( $_GET['dq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$page = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="waypost-results">' . Waypost_Render::search_box( array( 'context' => 'results' ) ) . Waypost_Render::results( $q, $page, self::results_url() ) . '</div>';
	}

	/** Escapes text and wraps matched words in <mark>. */
	public static function highlight( $text, $q ) {
		$words = self::words( $q );
		if ( ! $words ) {
			return esc_html( $text );
		}
		$pattern = '/(' . implode( '|', array_map( function ( $w ) { return preg_quote( $w, '/' ); }, $words ) ) . ')/iu';
		$parts   = preg_split( $pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out     = '';
		foreach ( $parts as $i => $part ) {
			$out .= $i % 2 ? '<mark>' . esc_html( $part ) . '</mark>' : esc_html( $part );
		}
		return $out;
	}

	// ── Search log ──────────────────────────────────────────────────────────

	public static function log( $q, $total ) {
		if ( ! Waypost_Settings::get( 'log_searches' ) ) {
			return;
		}
		$key = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $q ) ) );
		if ( mb_strlen( $key ) < 2 || mb_strlen( $key ) > 100 ) {
			return;
		}
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		if ( ! isset( $log[ $key ] ) ) {
			$log[ $key ] = array( 'count' => 0, 'hits' => 0, 'first' => time(), 'last' => 0 );
		}
		$log[ $key ]['count']++;
		$log[ $key ]['hits'] = (int) $total;
		$log[ $key ]['last'] = time();

		if ( count( $log ) > self::LOG_MAX ) {
			uasort( $log, function ( $a, $b ) { return $b['last'] - $a['last']; } );
			$log = array_slice( $log, 0, self::LOG_MAX, true );
		}
		update_option( self::LOG_OPTION, $log, false );
	}

	public static function log_rows( $only_failed = false, $limit = 25 ) {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		if ( $only_failed ) {
			$log = array_filter( $log, function ( $r ) { return 0 === (int) $r['hits']; } );
		}
		uasort(
			$log,
			function ( $a, $b ) {
				return $b['count'] === $a['count'] ? $b['last'] - $a['last'] : $b['count'] - $a['count'];
			}
		);
		return array_slice( $log, 0, $limit, true );
	}
}
