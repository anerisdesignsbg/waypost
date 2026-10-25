<?php
/**
 * Documents: PDF, Word and other files from the Media Library placed in doc
 * sections. Their text is read once and kept in the same plain-text copy that
 * search uses for pages, so docs search finds words inside them.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Documents {

	/** ok | empty (no text, e.g. a scanned PDF) | error | too_big | missing */
	const STATUS_META = '_waypost_doc_status';
	const CRON        = 'waypost_read_documents';
	/** Longest text kept per document, in characters. */
	const MAX_CHARS = 500000;
	/** Seconds one request may spend reading before the rest waits for the next run. */
	const BUDGET = 20;

	private static $spent = 0.0;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 100 );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'save_fields' ), 10, 2 );
		add_action( 'add_meta_boxes_attachment', array( __CLASS__, 'remove_meta_box' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms' ), 20, 4 );
		add_action( 'edit_attachment', array( __CLASS__, 'on_edit' ) );
		add_action( self::CRON, array( __CLASS__, 'read_queue' ) );
	}

	public static function enabled() {
		return (bool) Waypost_Settings::get( 'documents' );
	}

	/** Readable file types: mime type => extension shown on the badge. */
	public static function types() {
		return array(
			'application/pdf' => 'pdf',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'application/vnd.oasis.opendocument.text' => 'odt',
			'application/vnd.oasis.opendocument.presentation' => 'odp',
			'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
			'text/plain' => 'txt',
			'text/csv'   => 'csv',
		);
	}

	/** Older Office formats that cannot be read; the media screen suggests saving them again. */
	private static function legacy_types() {
		return array( 'application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/rtf' );
	}

	/** Badge text such as PDF or DOCX, or '' when the post is not a readable document. */
	public static function badge( $post ) {
		$post  = get_post( $post );
		$types = self::types();
		if ( ! $post || 'attachment' !== $post->post_type || ! isset( $types[ $post->post_mime_type ] ) ) {
			return '';
		}
		return strtoupper( $types[ $post->post_mime_type ] );
	}

	public static function is_document( $post ) {
		return '' !== self::badge( $post );
	}

	/** Documents can sit in doc sections. */
	public static function register() {
		if ( ! self::enabled() ) {
			return;
		}
		$tax = Waypost_Settings::taxonomy();
		if ( taxonomy_exists( $tax ) && ! is_object_in_taxonomy( 'attachment', $tax ) ) {
			register_taxonomy_for_object_type( $tax, 'attachment' );
		}
	}

	// ── Media Library ───────────────────────────────────────────────────────

	/** Section checkboxes in the media modal and on the attachment edit screen. */
	public static function fields( $fields, $post ) {
		if ( ! self::enabled() || ! taxonomy_exists( Waypost_Settings::taxonomy() ) ) {
			return $fields;
		}
		$label = __( 'Doc sections', 'waypost' );
		if ( in_array( $post->post_mime_type, self::legacy_types(), true ) ) {
			$fields['waypost_sections'] = array(
				'label' => $label,
				'input' => 'html',
				'html'  => '<p class="description">' . esc_html__( 'Waypost cannot read this older file format. Save it as PDF or in the newer format (for example .docx) and upload that to add it to the docs.', 'waypost' ) . '</p>',
			);
			return $fields;
		}
		if ( ! self::is_document( $post ) ) {
			return $fields;
		}

		$tax   = Waypost_Settings::taxonomy();
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		$have  = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'ids' ) );
		$have  = is_wp_error( $have ) ? array() : array_map( 'intval', $have );
		$name  = 'attachments[' . (int) $post->ID . '][waypost_sections]';

		// Every box has its own name: the media modal keeps only the last value of a repeated name.
		$html = '<input type="hidden" name="' . esc_attr( $name . '[0]' ) . '" value="1">';
		if ( ! $terms ) {
			$html .= '<p class="description">' . esc_html__( 'No doc sections yet. Create one under Waypost, Structure.', 'waypost' ) . '</p>';
		} else {
			$kids = array();
			foreach ( $terms as $t ) {
				$kids[ $t->parent ][] = $t;
			}
			$html .= '<ul class="wpst-doc-sections" style="margin:0;max-height:12em;overflow:auto">';
			$walk  = function ( $parent, $depth ) use ( &$walk, &$html, $kids, $have, $name ) {
				if ( empty( $kids[ $parent ] ) ) {
					return;
				}
				usort( $kids[ $parent ], function ( $a, $b ) { return strnatcasecmp( $a->name, $b->name ); } );
				foreach ( $kids[ $parent ] as $t ) {
					$html .= '<li style="margin:0 0 2px ' . ( $depth * 14 ) . 'px"><label><input type="checkbox" name="' . esc_attr( $name . '[' . $t->term_id . ']' ) . '" value="1"' . checked( in_array( (int) $t->term_id, $have, true ), true, false ) . '> ' . esc_html( $t->name ) . '</label></li>';
					$walk( $t->term_id, $depth + 1 );
				}
			};
			$walk( 0, 0 );
			$html .= '</ul>';
		}
		if ( $have ) {
			$html .= '<p class="description">' . esc_html( self::status_text( $post->ID ) ) . '</p>';
		}

		$fields['waypost_sections'] = array(
			'label' => $label,
			'input' => 'html',
			'html'  => $html,
			'helps' => __( 'The document shows in the docs menu and docs search finds words inside it.', 'waypost' ),
		);
		return $fields;
	}

	public static function save_fields( $post, $data ) {
		if ( ! self::enabled() || ! isset( $data['waypost_sections'] ) || ! is_array( $data['waypost_sections'] ) || empty( $post['ID'] ) ) {
			return $post;
		}
		$id  = (int) $post['ID'];
		$tax = Waypost_Settings::taxonomy();
		if ( ! current_user_can( 'edit_post', $id ) || ! self::is_document( $id ) || ! taxonomy_exists( $tax ) ) {
			return $post;
		}
		$ids = array();
		foreach ( array_keys( $data['waypost_sections'] ) as $term_id ) {
			$term_id = absint( $term_id );
			if ( $term_id && term_exists( $term_id, $tax ) ) {
				$ids[] = $term_id;
			}
		}
		wp_set_object_terms( $id, $ids, $tax );
		return $post;
	}

	/** The checkboxes replace the standard sections box on the attachment edit screen. */
	public static function remove_meta_box() {
		$tax = Waypost_Settings::taxonomy();
		remove_meta_box( $tax . 'div', 'attachment', 'side' );
		remove_meta_box( 'tagsdiv-' . $tax, 'attachment', 'side' );
	}

	/** A document placed in a section is read straight away if it is small, otherwise soon after. */
	public static function on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( $taxonomy !== Waypost_Settings::taxonomy() || ! self::enabled() || ! self::is_document( $object_id ) || ! $tt_ids ) {
			return;
		}
		if ( '' === (string) get_post_meta( $object_id, self::STATUS_META, true ) ) {
			self::read_soon( $object_id );
		}
	}

	/** A new caption, description or replaced file means reading the document again. */
	public static function on_edit( $post_id ) {
		if ( ! self::enabled() || ! self::is_document( $post_id ) || ! self::in_section( $post_id ) ) {
			return;
		}
		delete_post_meta( $post_id, self::STATUS_META );
		self::read_soon( $post_id );
		Waypost_Tree::flush();
	}

	private static function in_section( $post_id ) {
		$terms = wp_get_object_terms( $post_id, Waypost_Settings::taxonomy(), array( 'fields' => 'ids' ) );
		return ! is_wp_error( $terms ) && $terms;
	}

	private static function read_soon( $post_id ) {
		$file = get_attached_file( $post_id );
		$size = $file && file_exists( $file ) ? (int) filesize( $file ) : 0;
		if ( $size && $size <= 5 * MB_IN_BYTES && self::$spent < 8 ) {
			self::read( $post_id );
			return;
		}
		self::schedule();
	}

	public static function schedule( $delay = 0 ) {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + $delay, self::CRON );
		}
	}

	// ── Reading ─────────────────────────────────────────────────────────────

	/** Documents in sections that have not been read yet. */
	public static function unread( $limit = 50 ) {
		$tax = Waypost_Settings::taxonomy();
		if ( ! taxonomy_exists( $tax ) || ! is_object_in_taxonomy( 'attachment', $tax ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'post_mime_type'   => array_keys( self::types() ),
				'posts_per_page'   => $limit,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'tax_query'        => array( array( 'taxonomy' => $tax, 'operator' => 'EXISTS' ) ),
				'meta_query'       => array( array( 'key' => self::STATUS_META, 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	/** WP-Cron job: reads waiting documents until the time budget runs out, then books another run. */
	public static function read_queue() {
		if ( ! self::enabled() ) {
			return 0;
		}
		$done = 0;
		foreach ( self::unread() as $id ) {
			if ( self::$spent >= self::BUDGET ) {
				break;
			}
			self::read( $id );
			$done++;
		}
		if ( self::unread( 1 ) ) {
			self::schedule( 10 );
		}
		return $done;
	}

	/** Reads one document into the search text and records how it went. */
	public static function read( $post_id ) {
		$start = microtime( true );
		$post  = get_post( $post_id );
		$types = self::types();
		if ( ! $post || ! isset( $types[ $post->post_mime_type ] ) ) {
			return '';
		}
		$file   = get_attached_file( $post_id );
		$text   = '';
		$status = 'ok';
		$max    = (int) apply_filters( 'waypost_document_max_size', 30 * MB_IN_BYTES );

		if ( ! $file || ! is_readable( $file ) ) {
			$status = 'missing';
		} elseif ( filesize( $file ) > $max ) {
			$status = 'too_big';
		} else {
			try {
				$text = self::extract( $file, $types[ $post->post_mime_type ] );
			} catch ( \Throwable $e ) {
				$status = 'error';
			}
		}
		$text = self::tidy( $text );
		if ( 'ok' === $status && '' === $text ) {
			$status = 'empty';
		}

		// The caption and description count too, so a scanned PDF can be found by what its description says.
		$extra = self::tidy( $post->post_excerpt . "\n" . wp_strip_all_tags( $post->post_content ) );
		$all   = trim( $extra . "\n" . $text );
		if ( mb_strlen( $all ) > self::MAX_CHARS ) {
			$all = mb_substr( $all, 0, self::MAX_CHARS );
		}

		update_post_meta( $post_id, Waypost_Search::TEXT_META, wp_slash( $all ) );
		update_post_meta( $post_id, self::STATUS_META, $status );
		self::$spent += microtime( true ) - $start;
		return $status;
	}

	/** Clears every result so all documents are read again. */
	public static function forget_all() {
		delete_metadata( 'post', 0, self::STATUS_META, '', true );
		self::schedule();
	}

	private static function tidy( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
		}
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text );
		$text = preg_replace( '/[ \t\x{00A0}]+/u', ' ', (string) $text );
		$text = preg_replace( '/\s*\n\s*/u', "\n", (string) $text );
		return trim( (string) $text );
	}

	private static function extract( $file, $ext ) {
		switch ( $ext ) {
			case 'pdf':
				return self::pdf_text( $file );
			case 'docx':
				return self::zip_text( $file, '#^word/(document|footnotes|endnotes)\.xml$#' );
			case 'pptx':
				return self::zip_text( $file, '#^ppt/(slides/slide|notesSlides/notesSlide)\d+\.xml$#' );
			case 'xlsx':
				return self::zip_text( $file, '#^xl/sharedStrings\.xml$#' );
			case 'odt':
			case 'odp':
			case 'ods':
				return self::zip_text( $file, '#^content\.xml$#' );
			default: // txt, csv
				$text = (string) file_get_contents( $file, false, null, 0, 10 * MB_IN_BYTES ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		}
	}

	/** Text of the XML parts of an Office or OpenDocument file, in document order. */
	private static function zip_text( $file, $pattern ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'The PHP zip extension is missing.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			throw new RuntimeException( 'Not a valid file.' );
		}
		$parts = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			// Skip anything that would unpack to more than 50 MB.
			if ( $stat && preg_match( $pattern, $stat['name'] ) && $stat['size'] <= 50 * MB_IN_BYTES ) {
				$parts[ $stat['name'] ] = $i;
			}
		}
		uksort( $parts, 'strnatcmp' ); // slide2 before slide10.

		$out = array();
		foreach ( $parts as $index ) {
			$xml = $zip->getFromIndex( $index );
			if ( false !== $xml ) {
				$out[] = self::xml_text( $xml );
			}
		}
		$zip->close();
		return implode( "\n", $out );
	}

	private static function xml_text( $xml ) {
		// Field codes and deleted tracked changes are not part of the visible text.
		$xml = preg_replace( '#<w:(instrText|delText)\b[^>]*>.*?</w:\1>#s', '', $xml );
		// Paragraph ends, line breaks, cells and shared strings become new lines; tabs and spaces become spaces.
		$xml = preg_replace( '#</(w:p|a:p|text:p|text:h|si)>|<(w:br|a:br|text:line-break)\b[^>]*/>#', "\n", (string) $xml );
		$xml = preg_replace( '#<(w:tab|text:tab|text:s)\b[^>]*/>|</(table:table-cell|w:tc)>#', ' ', (string) $xml );
		$text = strip_tags( (string) $xml );
		return html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	private static function pdf_text( $file ) {
		if ( ! function_exists( 'iconv' ) || ! function_exists( 'mb_convert_encoding' ) || ! function_exists( 'gzuncompress' ) ) {
			throw new RuntimeException( 'Reading PDFs needs the PHP iconv, mbstring and zlib extensions.' );
		}
		self::autoload();
		wp_raise_memory_limit( 'waypost' );
		$config = new \Waypost\Vendor\PdfParser\Config();
		$config->setRetainImageContent( false );
		$parser = new \Waypost\Vendor\PdfParser\Parser( array(), $config );
		return $parser->parseFile( $file )->getText();
	}

	/** Loads the bundled PDF library (lib/pdfparser) on first use. */
	private static function autoload() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		spl_autoload_register(
			function ( $class ) {
				$prefix = 'Waypost\\Vendor\\PdfParser\\';
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}
				$path = WAYPOST_DIR . 'lib/pdfparser/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $path ) ) {
					require $path;
				}
			}
		);
	}

	// ── Menu and status ─────────────────────────────────────────────────────

	/** [ term_id => [ attachment_id => title ] ] for documents in sections. */
	public static function by_term( array $term_ids ) {
		$tax = Waypost_Settings::taxonomy();
		if ( ! $term_ids || ! self::enabled() || ! is_object_in_taxonomy( 'attachment', $tax ) ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'post_mime_type'   => array_keys( self::types() ),
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
		$out = array();
		foreach ( $rel as $t ) {
			$out[ $t->term_id ][ (int) $t->object_id ] = get_the_title( $t->object_id );
		}
		return $out;
	}

	/** How each document in a section was read: counts for the Settings screen. */
	public static function stats() {
		$out = array( 'total' => 0, 'ok' => 0, 'empty' => 0, 'failed' => 0, 'waiting' => 0, 'failed_list' => array() );
		$tax = Waypost_Settings::taxonomy();
		if ( ! taxonomy_exists( $tax ) || ! is_object_in_taxonomy( 'attachment', $tax ) ) {
			return $out;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'post_mime_type'   => array_keys( self::types() ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'tax_query'        => array( array( 'taxonomy' => $tax, 'operator' => 'EXISTS' ) ),
			)
		);
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			$out['total']++;
			$status = (string) get_post_meta( $id, self::STATUS_META, true );
			if ( '' === $status ) {
				$out['waiting']++;
			} elseif ( 'ok' === $status || 'empty' === $status ) {
				$out[ $status ]++;
				if ( 'empty' === $status ) {
					$out['failed_list'][ $id ] = $status;
				}
			} else {
				$out['failed']++;
				$out['failed_list'][ $id ] = $status;
			}
		}
		return $out;
	}

	/** One line on how a document was read. */
	public static function status_text( $post_id, $status = null ) {
		$status = null === $status ? (string) get_post_meta( $post_id, self::STATUS_META, true ) : $status;
		switch ( $status ) {
			case 'ok':
				$words = count( preg_split( '/\s+/u', (string) get_post_meta( $post_id, Waypost_Search::TEXT_META, true ), -1, PREG_SPLIT_NO_EMPTY ) );
				/* translators: %s: number of words */
				return sprintf( _n( 'Searchable: %s word read.', 'Searchable: %s words read.', $words, 'waypost' ), number_format_i18n( $words ) );
			case 'empty':
				return __( 'No text found. It may be a scanned image. Add a description so search can still find it.', 'waypost' );
			case 'too_big':
				return __( 'Too large to read. Only its title, caption and description are searchable.', 'waypost' );
			case 'missing':
				return __( 'The file is missing from the server.', 'waypost' );
			case 'error':
				return __( 'Could not be read. It may be damaged or password protected.', 'waypost' );
			default:
				return __( 'Waiting to be read. This happens in the background within a minute or two.', 'waypost' );
		}
	}
}
