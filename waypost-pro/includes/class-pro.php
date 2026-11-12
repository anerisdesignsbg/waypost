<?php
/**
 * Waypost Pro features, added to the free plugin through its hooks:
 * document search, synonyms and topic filters on search results.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Pro {

	public static function init() {
		// Settings: Pro keys live in the same option as the free plugin's.
		add_filter( 'waypost_settings_defaults', array( __CLASS__, 'defaults' ) );
		add_filter( 'waypost_settings_sanitize', array( __CLASS__, 'sanitize' ), 10, 2 );
		add_filter( 'waypost_settings_changes', array( __CLASS__, 'changes' ), 10, 2 );

		// Search: synonyms and topic filters.
		add_filter( 'waypost_synonyms', array( __CLASS__, 'synonyms' ) );
		add_filter( 'waypost_topic_filters', array( __CLASS__, 'topic_filters' ) );

		// Search and menu: documents.
		add_filter( 'waypost_search_query_args', array( __CLASS__, 'search_args' ) );
		add_filter( 'waypost_search_where', array( __CLASS__, 'search_where' ), 10, 2 );
		add_filter( 'waypost_result_file', array( __CLASS__, 'result_file' ), 10, 2 );
		add_filter( 'waypost_menu_files', array( __CLASS__, 'menu_files' ), 10, 2 );

		if ( is_admin() ) {
			add_filter( 'waypost_settings_sections', array( __CLASS__, 'sections' ) );
			add_action( 'waypost_settings_panels', array( __CLASS__, 'panel_documents' ) );
			add_action( 'waypost_settings_search_rows', array( __CLASS__, 'search_rows' ) );
			add_action( 'waypost_settings_notices', array( __CLASS__, 'notices' ) );
			add_action( 'waypost_settings_after_form', array( __CLASS__, 'after_form' ) );
			add_action( 'admin_post_waypost_read_documents', array( __CLASS__, 'read_documents' ) );
			add_filter( 'waypost_assign_views', array( __CLASS__, 'assign_views' ) );
			add_filter( 'waypost_assign_query_args', array( __CLASS__, 'assign_args' ), 10, 2 );
			add_filter( 'waypost_assign_item_type', array( __CLASS__, 'assign_type' ), 10, 2 );
			add_filter( 'waypost_assign_item_status', array( __CLASS__, 'assign_status' ), 10, 3 );
			add_filter( 'waypost_can_assign', array( __CLASS__, 'can_assign' ), 10, 2 );
			add_filter( 'waypost_help_topics', array( __CLASS__, 'help_topics' ) );
			add_action( 'waypost_help_articles', array( __CLASS__, 'help_articles' ) );
			add_action( 'waypost_insights_no_results_tip', array( __CLASS__, 'insights_tip' ) );
		}
	}

	// ── Settings ────────────────────────────────────────────────────────────

	public static function defaults( $d ) {
		return array_merge(
			$d,
			array(
				'topic_filters'  => 1,  // results: filter by top-level section
				'synonyms'       => '', // one set per line, comma separated, e.g. photo, picture, image
				'documents'      => 1,  // search inside PDF, Word and other files placed in doc sections
				'documents_menu' => 1,  // list those files in the docs menu
			)
		);
	}

	public static function sanitize( $out, $in ) {
		foreach ( array( 'topic_filters', 'documents', 'documents_menu' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$lines = array();
		foreach ( preg_split( '/\r?\n/', isset( $in['synonyms'] ) ? (string) $in['synonyms'] : '' ) as $line ) {
			$words = array_filter( array_map( 'trim', explode( ',', mb_strtolower( sanitize_text_field( $line ) ) ) ), 'strlen' );
			if ( count( $words ) > 1 ) {
				$lines[] = implode( ', ', array_unique( $words ) );
			}
		}
		$out['synonyms'] = implode( "\n", array_slice( $lines, 0, 200 ) );
		return $out;
	}

	/** Fields posted from the Settings form. */
	public static function changes( $changes, $in ) {
		foreach ( array( 'topic_filters', 'documents', 'documents_menu' ) as $k ) {
			$changes[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		if ( isset( $in['synonyms'] ) ) {
			$changes['synonyms'] = $in['synonyms'];
		}
		return $changes;
	}

	public static function synonyms() {
		return (string) Waypost_Settings::get( 'synonyms' );
	}

	public static function topic_filters() {
		return (bool) Waypost_Settings::get( 'topic_filters' );
	}

	// ── Documents in search and the menu ────────────────────────────────────

	public static function search_args( $args ) {
		if ( Waypost_Pro_Documents::enabled() && is_object_in_taxonomy( 'attachment', Waypost_Settings::taxonomy() ) ) {
			$args['post_type'][]   = 'attachment';
			$args['post_status'][] = 'inherit';
		}
		return $args;
	}

	/** Attachments count only when they are readable documents, never images. */
	public static function search_where( $sql, $query ) {
		global $wpdb;
		if ( ! in_array( 'attachment', (array) $query->get( 'post_type' ), true ) ) {
			return $sql;
		}
		$mimes = "'" . implode( "','", array_map( 'esc_sql', array_keys( Waypost_Pro_Documents::types() ) ) ) . "'";
		return $sql . " AND ( {$wpdb->posts}.post_type <> 'attachment' OR {$wpdb->posts}.post_mime_type IN ( {$mimes} ) ) ";
	}

	public static function result_file( $badge, $post ) {
		$file = Waypost_Pro_Documents::badge( $post );
		return '' !== $file ? $file : $badge;
	}

	public static function menu_files( $files, $term_ids ) {
		if ( ! Waypost_Settings::get( 'documents_menu' ) ) {
			return $files;
		}
		foreach ( Waypost_Pro_Documents::by_term( $term_ids ) as $tid => $docs ) {
			foreach ( $docs as $id => $title ) {
				$files[ $tid ][ $id ] = array(
					'title' => $title,
					'url'   => wp_get_attachment_url( $id ),
					'badge' => Waypost_Pro_Documents::badge( $id ),
				);
			}
		}
		return $files;
	}

	// ── Settings screen ─────────────────────────────────────────────────────

	public static function sections( $sections ) {
		$out = array();
		foreach ( $sections as $id => $s ) {
			if ( 'search' === $id ) {
				$s[1] = __( 'Results, answers and synonyms', 'waypost-pro' );
			}
			$out[ $id ] = $s;
			if ( 'search-home' === $id ) {
				$out['documents'] = array( __( 'Documents', 'waypost-pro' ), __( 'PDF, Word and other files', 'waypost-pro' ) );
			}
		}
		return $out;
	}

	/** Topic filters and synonyms, added to the Search section. */
	public static function search_rows( $o ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Topic filters', 'waypost-pro' ); ?></th>
			<td>
				<label class="wpst-check"><input type="checkbox" name="topic_filters" value="1" <?php checked( $o['topic_filters'] ); ?>> <?php esc_html_e( 'Let readers narrow results by topic (your top-level sections)', 'waypost-pro' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wpst-synonyms"><?php esc_html_e( 'Synonyms', 'waypost-pro' ); ?></label></th>
			<td>
				<textarea id="wpst-synonyms" name="synonyms" rows="5" class="large-text code" placeholder="<?php esc_attr_e( "photo, picture, image
sign in, log in, login", 'waypost-pro' ); ?>"><?php echo esc_textarea( $o['synonyms'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Words your readers use that mean the same as words in your docs. One group per line, separated by commas. A search for any word in a group finds them all. Search insights shows searches that found nothing, a good source of new synonyms.', 'waypost-pro' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function panel_documents( $o ) {
		?>
		<section class="wpst-card" id="documents">
			<div class="wpst-card__head"><h2><?php esc_html_e( 'Documents', 'waypost-pro' ); ?></h2><?php echo Waypost_Admin::help_link( 'documents' ); // phpcs:ignore ?></div>
			<p><?php esc_html_e( 'Put PDF, Word, PowerPoint, Excel, OpenDocument and text files from the Media Library into doc sections. Docs search then finds words inside them, and they can be listed in the docs menu.', 'waypost-pro' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Documents', 'waypost-pro' ); ?></th>
					<td>
						<label class="wpst-check"><input type="checkbox" name="documents" value="1" <?php checked( $o['documents'] ); ?>> <?php esc_html_e( 'Let documents join doc sections and search inside them', 'waypost-pro' ); ?></label>
						<label class="wpst-check"><input type="checkbox" name="documents_menu" value="1" <?php checked( $o['documents_menu'] ); ?>> <?php esc_html_e( 'List documents in the docs menu, under their sections', 'waypost-pro' ); ?></label>
						<p class="description"><?php esc_html_e( 'To add one, open it in Media, tick its sections under Doc sections, or use Structure, Assign pages with the Documents filter.', 'waypost-pro' ); ?></p>
					</td>
				</tr>
				<?php if ( $o['documents'] ) : $stats = Waypost_Pro_Documents::stats(); ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Reading status', 'waypost-pro' ); ?></th>
					<td class="wpst-doc-status">
						<?php if ( ! $stats['total'] ) : ?>
							<p><?php esc_html_e( 'No documents are in doc sections yet.', 'waypost-pro' ); ?></p>
						<?php else : ?>
							<p>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: documents read, 2: documents in sections */
										__( '%1$s of %2$s documents are searchable.', 'waypost-pro' ),
										number_format_i18n( $stats['ok'] ),
										number_format_i18n( $stats['total'] )
									)
								);
								if ( $stats['waiting'] ) {
									/* translators: %s: number of documents */
									echo ' ' . esc_html( sprintf( _n( '%s is waiting to be read.', '%s are waiting to be read.', $stats['waiting'], 'waypost-pro' ), number_format_i18n( $stats['waiting'] ) ) );
								}
								?>
							</p>
							<?php if ( $stats['failed_list'] ) : ?>
								<ul class="wpst-doc-problems">
									<?php foreach ( array_slice( $stats['failed_list'], 0, 10, true ) as $doc_id => $doc_status ) : ?>
										<li><a href="<?php echo esc_url( get_edit_post_link( $doc_id ) ); ?>"><?php echo esc_html( get_the_title( $doc_id ) ); ?></a> <span class="wpst-muted"><?php echo esc_html( Waypost_Pro_Documents::status_text( $doc_id, $doc_status ) ); ?></span></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<p>
								<?php if ( $stats['waiting'] ) : ?>
									<button type="submit" class="button" form="wpst-read-docs" name="mode" value="waiting"><?php esc_html_e( 'Read waiting documents now', 'waypost-pro' ); ?></button>
								<?php endif; ?>
								<button type="submit" class="button" form="wpst-read-docs" name="mode" value="all"><?php esc_html_e( 'Read all documents again', 'waypost-pro' ); ?></button>
							</p>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Text is read once, when a document is added to a section or changed. Scanned PDFs hold pictures of text, not text, so only their title, caption and description can be found.', 'waypost-pro' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</section>
		<?php
	}

	public static function notices() {
		if ( isset( $_GET['docs_read'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$read = absint( $_GET['docs_read'] ); // phpcs:ignore WordPress.Security.NonceVerification
			/* translators: %s: number of documents */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%s document read.', '%s documents read.', $read, 'waypost-pro' ), number_format_i18n( $read ) ) ) . '</p></div>';
		}
	}

	public static function after_form() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpst-read-docs" hidden>
			<input type="hidden" name="action" value="waypost_read_documents">
			<?php wp_nonce_field( 'waypost_read_documents' ); ?>
		</form>
		<?php
	}

	/** Reads waiting documents (or all of them again) now, instead of waiting for WP-Cron. */
	public static function read_documents() {
		if ( ! current_user_can( Waypost_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'waypost-pro' ) );
		}
		check_admin_referer( 'waypost_read_documents' );
		if ( isset( $_POST['mode'] ) && 'all' === $_POST['mode'] ) {
			Waypost_Pro_Documents::forget_all();
		}
		$done = Waypost_Pro_Documents::read_queue();
		wp_safe_redirect( admin_url( 'admin.php?page=waypost-settings&docs_read=' . (int) $done ) . '#documents' );
		exit;
	}

	// ── Structure, Assign pages ─────────────────────────────────────────────

	private static function docs_on() {
		return Waypost_Pro_Documents::enabled() && is_object_in_taxonomy( 'attachment', Waypost_Settings::taxonomy() );
	}

	public static function assign_views( $views ) {
		if ( self::docs_on() ) {
			$views['documents'] = __( 'Documents', 'waypost-pro' );
		}
		return $views;
	}

	public static function assign_args( $args, $filter ) {
		if ( 'documents' === $filter && self::docs_on() ) {
			unset( $args['tax_query'] );
			$args['post_type']      = 'attachment';
			$args['post_status']    = 'inherit';
			$args['post_mime_type'] = array_keys( Waypost_Pro_Documents::types() );
		}
		return $args;
	}

	public static function assign_type( $label, $post ) {
		return 'attachment' === $post->post_type ? Waypost_Pro_Documents::badge( $post ) : $label;
	}

	public static function assign_status( $status, $post, $names ) {
		if ( 'attachment' !== $post->post_type ) {
			return $status;
		}
		return $names ? Waypost_Pro_Documents::status_text( $post->ID ) : __( 'Not in docs', 'waypost-pro' );
	}

	public static function can_assign( $allowed, $id ) {
		return $allowed || ( Waypost_Pro_Documents::enabled() && Waypost_Pro_Documents::is_document( $id ) );
	}

	// ── Help and Search insights ────────────────────────────────────────────

	public static function help_topics( $topics ) {
		$out = array();
		foreach ( $topics as $id => $label ) {
			$out[ $id ] = $label;
			if ( 'search' === $id ) {
				$out['pro-search'] = __( 'Synonyms and topics', 'waypost-pro' );
				$out['documents']  = __( 'Documents (PDF, Word and more)', 'waypost-pro' );
			}
		}
		return $out;
	}

	public static function help_articles() {
		?>
				<article class="wpst-card wpst-topic" id="pro-search">
					<h2><?php esc_html_e( 'Synonyms and topics', 'waypost-pro' ); ?></h2>
					<ul class="wpst-list">
						<li><strong><?php esc_html_e( 'Topics', 'waypost-pro' ); ?></strong>: <?php esc_html_e( 'buttons above the results narrow them to one of your top-level sections, with a count for each.', 'waypost-pro' ); ?></li>
						<li><strong><?php esc_html_e( 'Synonyms', 'waypost-pro' ); ?></strong>: <?php esc_html_e( 'add your readers’ own words in Settings, Search, for example “pic” for “photo”. Search insights shows which searches find nothing, so you know what to add.', 'waypost-pro' ); ?></li>
					</ul>
				</article>

				<article class="wpst-card wpst-topic" id="documents">
					<h2><?php esc_html_e( 'Documents (PDF, Word and more)', 'waypost-pro' ); ?></h2>
					<p><?php esc_html_e( 'Files in your Media Library can join doc sections just like pages. Docs search then finds words inside them, and the docs menu can list them with a small label such as PDF.', 'waypost-pro' ); ?></p>
					<p><?php esc_html_e( 'Readable types: PDF, Word (.docx), PowerPoint (.pptx), Excel (.xlsx), OpenDocument (.odt, .odp, .ods), plain text and CSV. Older formats such as .doc cannot be read; save them as PDF or .docx first.', 'waypost-pro' ); ?></p>
					<h3><?php esc_html_e( 'Add a document', 'waypost-pro' ); ?></h3>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Upload the file under Media, Add New.', 'waypost-pro' ); ?></li>
						<li><?php esc_html_e( 'Open it in the Media Library and tick one or more sections under Doc sections. It saves straight away.', 'waypost-pro' ); ?></li>
						<li><?php esc_html_e( 'Or use Waypost, Structure, Assign pages, choose the Documents filter and add several at once.', 'waypost-pro' ); ?></li>
					</ol>
					<h3><?php esc_html_e( 'How the text is read', 'waypost-pro' ); ?></h3>
					<p><?php esc_html_e( 'Waypost reads the text once, when a document joins a section or is edited, and keeps a plain copy for search. Small files are read at once; larger ones are read in the background within a minute or two. Settings, Documents shows how many are searchable and lists any that could not be read.', 'waypost-pro' ); ?></p>
					<ul class="wpst-list">
						<li><strong><?php esc_html_e( 'Scanned PDFs', 'waypost-pro' ); ?></strong>: <?php esc_html_e( 'hold pictures of text, not text, so there is nothing to read. Write a short description of the document in the Media Library and search finds that instead.', 'waypost-pro' ); ?></li>
						<li><strong><?php esc_html_e( 'Password-protected files', 'waypost-pro' ); ?></strong>: <?php esc_html_e( 'cannot be read. Remove the password and upload the file again.', 'waypost-pro' ); ?></li>
						<li><strong><?php esc_html_e( 'Very large files', 'waypost-pro' ); ?></strong>: <?php esc_html_e( 'files over 30 MB are skipped, and only the first 500,000 characters of a document are kept.', 'waypost-pro' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Results for a document open the file itself. Documents are not part of previous and next links or breadcrumbs.', 'waypost-pro' ); ?></p>
				</article>
		<?php
	}

	public static function insights_tip() {
		echo ' ';
		esc_html_e( 'If your docs use a different word for the same thing, add a synonym.', 'waypost-pro' );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=waypost-settings#search' ) ) . '">' . esc_html__( 'Edit synonyms', 'waypost-pro' ) . '</a>';
	}
}
