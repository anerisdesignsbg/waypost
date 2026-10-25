<?php
/**
 * Docs search: REST endpoint for instant results, the results page
 * shortcode, and the search log behind Search insights.
 *
 * Search understands the reader rather than matching raw text: question words
 * are ignored, other forms of a word count ("installing" finds "install"),
 * the site's synonyms are added, misspelt words are corrected, and pages are
 * scored section by section so the best answer and the exact heading that
 * holds it come first. The matching itself lives in Waypost_Match.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Search {

	const LOG_OPTION = 'waypost_search_log';
	const LOG_MAX    = 500;

	/** Plain text of each doc page, kept up to date on save. Search reads this instead of raw content. */
	const TEXT_META = '_waypost_text';
	/** Which post types and text version have been filled in for existing pages. */
	const TEXT_OPTION = 'waypost_text_ready';
	const TEXT_VERSION = 2; // 2: pages are also split into heading sections.
	const TEXT_BATCH   = 200;
	/** Most pages scored for one search. */
	const CANDIDATES = 400;

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
		return self::html_text( Waypost_Render::content_html( $content ) );
	}

	/** Readable text of an HTML fragment. */
	public static function html_text( $html ) {
		$html = Waypost_Headings::strip_shortcode_tags( $html );
		$html = preg_replace( '/<(script|style)\b.*?<\/\1>/is', ' ', (string) $html );
		// Block-level tags only: a space inside <em>Mag</em>pie would split the word.
		$html = preg_replace( '#<(/?)(p|h[1-6]|li|dt|dd|div|section|article|br|hr|td|th|tr|ul|ol|pre|blockquote|figcaption)\b#i', ' <$1$2', (string) $html );
		$text = wp_strip_all_tags( (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** Saves the plain text and the heading sections of a page. */
	public static function index_post( $post_id, $content ) {
		$html = Waypost_Render::content_html( $content );
		update_post_meta( $post_id, self::TEXT_META, wp_slash( self::html_text( $html ) ) );
		update_post_meta( $post_id, Waypost_Match::PASSAGES_META, wp_slash( Waypost_Match::passages( $html ) ) );
	}

	public static function save_text( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! in_array( $post->post_type, Waypost_Settings::post_types(), true ) ) {
			return;
		}
		self::index_post( $post_id, $post->post_content );
		delete_transient( Waypost_Match::VOCAB_CACHE );
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
				'meta_query'     => array( array( 'key' => Waypost_Match::PASSAGES_META, 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			self::index_post( $id, get_post_field( 'post_content', $id ) );
		}
		if ( count( $ids ) < self::TEXT_BATCH ) {
			update_option( self::TEXT_OPTION, $key, false );
			delete_transient( Waypost_Match::VOCAB_CACHE );
		}
	}

	/**
	 * Finds candidate pages for run(): each word group (a word, its other forms
	 * and synonyms) must appear in the title or plain text, or any group when
	 * waypost_any is set. A page without plain text yet falls back to its raw content.
	 */
	public static function where( $search, $query ) {
		global $wpdb;
		$groups = $query->get( 'waypost_groups' );
		if ( ! $query->get( 'waypost' ) || ! is_array( $groups ) || ! $groups ) {
			return $search;
		}
		$clauses = array();
		foreach ( $groups as $forms ) {
			$or = array();
			foreach ( (array) $forms as $form ) {
				$like = '%' . $wpdb->esc_like( $form ) . '%';
				$or[] = $wpdb->prepare(
					"{$wpdb->posts}.post_title LIKE %s
					OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s )
					OR ( {$wpdb->posts}.post_content LIKE %s AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = {$wpdb->posts}.ID AND m.meta_key = %s ) )",
					$like,
					self::TEXT_META,
					$like,
					$like,
					self::TEXT_META
				);
			}
			$clauses[] = '( ' . implode( ' OR ', $or ) . ' )';
		}
		$join = $query->get( 'waypost_any' ) ? ' OR ' : ' AND ';
		// Attachments count only when they are readable documents, never images.
		$mimes = "'" . implode( "','", array_map( 'esc_sql', array_keys( Waypost_Documents::types() ) ) ) . "'";
		return ' AND ( ' . implode( $join, $clauses ) . " ) AND ( {$wpdb->posts}.post_type <> 'attachment' OR {$wpdb->posts}.post_mime_type IN ( {$mimes} ) ) ";
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
					'q'     => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'page'  => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
					'per'   => array( 'type' => 'integer', 'default' => 8, 'sanitize_callback' => 'absint' ),
					'topic' => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
					'log'   => array( 'type' => 'boolean', 'default' => false ),
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
			return rest_ensure_response( self::empty_result( $q ) );
		}
		$data = self::run( $q, $page, max( 1, min( 50, (int) $req['per'] ) ), (int) $req['topic'] );
		if ( $req['log'] && 1 === $page ) {
			self::log( $q, $data['total'] );
		}
		return rest_ensure_response( $data );
	}

	private static function empty_result( $q ) {
		return array(
			'query'     => $q,
			'corrected' => '',
			'partial'   => false,
			'marks'     => array(),
			'total'     => 0,
			'pages'     => 0,
			'topic'     => 0,
			'topics'    => array(),
			'answer'    => null,
			'related'   => array(),
			'results'   => array(),
		);
	}

	/**
	 * Runs a docs search and returns plain data (no HTML):
	 *   corrected  the search actually run when the reader's words looked misspelt
	 *   partial    true when no page has every word, so pages with some of them are shown
	 *   marks      words to highlight, including other forms and synonyms
	 *   topics     top-level sections with how many results each holds, for filtering
	 *   answer     on page 1: the section that best answers the search
	 *   related    on page 1: other pages that cover it, at their best section
	 *   results    each with its section path, a snippet and up to 3 matching headings
	 */
	public static function run( $q, $page = 1, $per_page = 10, $topic = 0 ) {
		$out = self::empty_result( $q );
		if ( ! taxonomy_exists( Waypost_Settings::taxonomy() ) ) {
			return $out;
		}
		self::fill_text();

		$groups = Waypost_Match::groups( $q );
		if ( ! $groups ) {
			return $out;
		}
		$posts = self::candidates( $groups, false );
		if ( ! $posts ) {
			$fixed = Waypost_Match::suggest( $q );
			if ( '' !== $fixed ) {
				$fixed_groups = Waypost_Match::groups( $fixed );
				$posts        = self::candidates( $fixed_groups, false );
				if ( $posts ) {
					$out['corrected'] = $fixed;
					$groups           = $fixed_groups;
				}
			}
		}
		if ( ! $posts && count( $groups ) > 1 ) {
			$posts          = self::candidates( $groups, true );
			$out['partial'] = (bool) $posts;
		}
		$out['marks'] = Waypost_Match::marks( $groups );
		if ( ! $posts ) {
			return $out;
		}

		// Score every candidate and rank them.
		$phrase = count( $groups ) > 1 ? mb_strtolower( trim( '' !== $out['corrected'] ? $out['corrected'] : implode( ' ', Waypost_Match::words( $q ) ) ) ) : '';
		$ranked = array();
		foreach ( $posts as $post ) {
			$text     = (string) get_post_meta( $post->ID, self::TEXT_META, true );
			$passages = Waypost_Match::stored_passages( $post->ID, '' !== $text ? $text : self::plain_text( $post->post_content ) );
			$title    = html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) );
			$s        = Waypost_Match::score( $title, $passages, $groups, $phrase );
			$file     = Waypost_Documents::badge( $post );
			$score    = $s['score'] * ( $file ? 0.9 : 1 );
			// Every word somewhere on the page beats a higher count of a single word.
			$score   += 4 * $s['matched'];
			$ranked[] = array( $post, $title, $passages, $s, $file, $score );
		}
		usort(
			$ranked,
			function ( $a, $b ) {
				if ( $a[5] === $b[5] ) {
					return strnatcasecmp( $a[1], $b[1] );
				}
				return $a[5] < $b[5] ? 1 : -1;
			}
		);

		// Filter by topic after counting, so the counts always show the whole picture.
		$tops          = self::top_sections( wp_list_pluck( wp_list_pluck( $ranked, 0 ), 'ID' ) );
		$out['topics'] = self::topic_counts( $ranked, $tops );
		if ( $topic && isset( $out['topics'][ $topic ] ) ) {
			$out['topic'] = $topic;
			$ranked       = array_values(
				array_filter(
					$ranked,
					function ( $r ) use ( $tops, $topic ) {
						return isset( $tops[ $r[0]->ID ] ) && in_array( $topic, $tops[ $r[0]->ID ], true );
					}
				)
			);
		}
		$out['topics'] = array_values( $out['topics'] );

		$out['total'] = count( $ranked );
		$out['pages'] = (int) ceil( $out['total'] / $per_page );
		$slice        = array_slice( $ranked, ( $page - 1 ) * $per_page, $per_page );
		$marks        = $out['marks'];

		foreach ( $slice as $r ) {
			$out['results'][] = self::result( $r, $marks );
		}

		if ( 1 === (int) $page && $ranked && Waypost_Settings::get( 'best_answer' ) ) {
			$top = $ranked[0];
			// Only a confident match becomes the answer: every word found, in one section or the title.
			if ( ! $out['partial'] && $top[3]['matched'] === count( $groups ) && $top[3]['best'] >= 0 ) {
				$out['answer'] = self::answer( $top, $marks );
				foreach ( array_slice( $ranked, 1, 3 ) as $r ) {
					if ( $r[3]['best'] >= 0 ) {
						$out['related'][] = self::related( $r, $marks );
					}
				}
			}
		}
		return $out;
	}

	/** Published docs matching every group (or any group), limited to pages in doc sections. */
	private static function candidates( array $groups, $any ) {
		$tax    = Waypost_Settings::taxonomy();
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
				'posts_per_page'      => self::CANDIDATES,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'modified',
				'waypost'             => 1, // Picked up by where().
				'waypost_groups'      => $groups,
				'waypost_any'         => $any,
				'tax_query'           => array(
					array(
						'taxonomy' => $tax,
						'operator' => 'EXISTS',
					),
				),
			)
		);
		return $query->posts;
	}

	private static function link( $r ) {
		return $r[4] ? wp_get_attachment_url( $r[0]->ID ) : get_permalink( $r[0] );
	}

	/** Link to a section of a page: its heading anchor, or the page itself. */
	private static function section_link( $url, array $passage, $file ) {
		return ( ! $file && '' !== (string) $passage[1] ) ? $url . '#' . $passage[1] : $url;
	}

	private static function result( array $r, array $marks ) {
		list( $post, $title, $passages, $s, $file ) = $r;
		$url = self::link( $r );

		// Up to three headings that hold the search, strongest first.
		$sections = $s['sections'];
		arsort( $sections );

		// The snippet comes from the strongest section whose text (not just its heading) holds a search word.
		$best = $passages ? $passages[0] : array( '', '', '' );
		foreach ( array_keys( $sections ) as $pi ) {
			if ( self::has_mark( $passages[ $pi ][2], $marks ) ) {
				$best = $passages[ $pi ];
				break;
			}
		}
		$headings = array();
		foreach ( array_keys( $sections ) as $pi ) {
			$p = $passages[ $pi ];
			if ( '' === (string) $p[0] || '' === (string) $p[1] || $file ) {
				continue;
			}
			$headings[] = array(
				'text'    => $p[0],
				'url'     => $url . '#' . $p[1],
				'snippet' => Waypost_Match::snippet( $p[2], $marks, 110 ),
			);
			if ( count( $headings ) >= 3 ) {
				break;
			}
		}
		return array(
			'id'       => $post->ID,
			'title'    => $title,
			'url'      => $url,
			'section'  => self::section_label( $post->ID ),
			'excerpt'  => Waypost_Match::snippet( $best[2], $marks, 180 ),
			'headings' => $headings,
			'file'     => $file,
		);
	}

	private static function has_mark( $text, array $marks ) {
		$text = mb_strtolower( (string) $text );
		foreach ( $marks as $m ) {
			if ( false !== mb_strpos( $text, $m ) ) {
				return true;
			}
		}
		return false;
	}

	private static function answer( array $r, array $marks ) {
		list( $post, $title, $passages, $s, $file ) = $r;
		$best = $passages[ $s['best'] ];
		$url  = self::link( $r );
		$path = self::section_label( $post->ID );
		return array(
			'id'      => $post->ID,
			'title'   => '' !== (string) $best[0] ? $best[0] : $title,
			'page'    => '' !== (string) $best[0] ? $title : '',
			'path'    => $path,
			'url'     => self::section_link( $url, $best, $file ),
			'text'    => Waypost_Match::snippet( $best[2], $marks, 320 ),
			'file'    => $file,
		);
	}

	private static function related( array $r, array $marks ) {
		list( $post, $title, $passages, $s, $file ) = $r;
		$best = $passages[ $s['best'] ];
		return array(
			'id'      => $post->ID,
			'title'   => $title,
			'heading' => '' !== (string) $best[0] && $best[0] !== $title ? $best[0] : '',
			'url'     => self::section_link( self::link( $r ), $best, $file ),
			'text'    => Waypost_Match::snippet( $best[2], $marks, 110 ),
			'file'    => $file,
		);
	}

	/** [ post_id => [ top-level term ids ] ] */
	private static function top_sections( array $ids ) {
		$tax = Waypost_Settings::taxonomy();
		$out = array();
		if ( ! $ids ) {
			return $out;
		}
		$rel = wp_get_object_terms( $ids, $tax, array( 'fields' => 'all_with_object_id' ) );
		if ( is_wp_error( $rel ) ) {
			return $out;
		}
		foreach ( $rel as $t ) {
			$chain = get_ancestors( $t->term_id, $tax, 'taxonomy' );
			$top   = $chain ? (int) end( $chain ) : (int) $t->term_id;
			$out[ (int) $t->object_id ][] = $top;
		}
		foreach ( $out as $id => $list ) {
			$out[ $id ] = array_values( array_unique( $list ) );
		}
		return $out;
	}

	/** [ term_id => { id, name, count } ], largest first. */
	private static function topic_counts( array $ranked, array $tops ) {
		if ( ! Waypost_Settings::get( 'topic_filters' ) ) {
			return array();
		}
		$counts = array();
		foreach ( $ranked as $r ) {
			foreach ( isset( $tops[ $r[0]->ID ] ) ? $tops[ $r[0]->ID ] : array() as $tid ) {
				$counts[ $tid ] = isset( $counts[ $tid ] ) ? $counts[ $tid ] + 1 : 1;
			}
		}
		arsort( $counts );
		$out = array();
		foreach ( $counts as $tid => $n ) {
			$term = get_term( $tid, Waypost_Settings::taxonomy() );
			if ( $term && ! is_wp_error( $term ) ) {
				$out[ $tid ] = array( 'id' => $tid, 'name' => html_entity_decode( $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) ), 'count' => $n );
			}
		}
		return count( $out ) > 1 ? $out : array(); // One topic gives nothing to choose between.
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

	// ── Results page ────────────────────────────────────────────────────────

	public static function results_shortcode( $atts = array() ) {
		$q    = isset( $_GET['dq'] ) ? sanitize_text_field( wp_unslash( $_GET['dq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$page = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="waypost-results">' . Waypost_Render::search_box( array( 'context' => 'results' ) ) . Waypost_Render::results( $q, $page, self::results_url() ) . '</div>';
	}

	/**
	 * Escapes text and wraps matched words in <mark>. $marks is a list of words
	 * (from run()) or a search string. A match grows to the end of its word,
	 * so "install" marks the whole of "installing".
	 */
	public static function highlight( $text, $marks ) {
		$marks = is_array( $marks ) ? $marks : Waypost_Match::words( $marks );
		$marks = array_filter( $marks, 'strlen' );
		if ( ! $marks ) {
			return esc_html( $text );
		}
		$pattern = '/((?:' . implode( '|', array_map( function ( $w ) { return preg_quote( $w, '/' ); }, $marks ) ) . ')[\p{L}\p{N}]*)/iu';
		$parts   = preg_split( $pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out     = '';
		foreach ( (array) $parts as $i => $part ) {
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
