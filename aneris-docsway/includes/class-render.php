<?php
/**
 * HTML output for every front-end component. Shortcodes, blocks and the
 * Divi modules all call these methods, so output is identical everywhere.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Render {

	private static $uid = 0;

	public static function current_post_id( $post_id = 0 ) {
		if ( $post_id ) {
			return (int) $post_id;
		}
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}
		// Block editor previews set up the edited post globally.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && get_the_ID() ) {
			return (int) get_the_ID();
		}
		return 0;
	}

	private static function uid( $prefix ) {
		return $prefix . '-' . ( ++self::$uid );
	}

	/**
	 * True where full results are listed: the results page, or the docs home
	 * while it shows results for a search.
	 */
	private static function on_results_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$id = (int) get_queried_object_id();
		if ( $id && $id === (int) Docsway_Settings::get( 'results_page' ) ) {
			return true;
		}
		return $id && $id === Docsway_Search::results_page_id() && '' !== self::query();
	}

	/** The search words in the address (?dq=), if any. */
	private static function query() {
		return isset( $_GET['dq'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['dq'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** The address of the page being viewed, without search arguments. */
	private static function current_url() {
		$id = (int) get_queried_object_id();
		return $id ? get_permalink( $id ) : home_url( '/' );
	}

	// ── Menu ────────────────────────────────────────────────────────────────

	public static function menu( $args = array() ) {
		$args = wp_parse_args( $args, array( 'post_id' => 0, 'title' => '' ) );
		Docsway_Frontend::enqueue();

		$tree = Docsway_Tree::get();
		if ( ! $tree ) {
			return current_user_can( 'edit_posts' )
				? '<p class="docsway-empty">' . esc_html__( 'Docsway: no sections with published pages yet. Add pages to a section to build the menu.', 'aneris-docsway' ) . '</p>'
				: '';
		}

		$current = self::current_post_id( $args['post_id'] );
		$id      = self::uid( 'docsway-menu' );
		$label   = $args['title'] ? $args['title'] : __( 'Documentation', 'aneris-docsway' );

		ob_start();
		?>
		<nav class="docsway-menu" aria-label="<?php echo esc_attr( $label ); ?>" data-accordion="<?php echo Docsway_Settings::get( 'accordion' ) ? '1' : '0'; ?>">
			<button type="button" class="docsway-menu__mobile" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>">
				<span><?php echo esc_html( $label ); ?></span>
				<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 4h12M2 8h12M2 12h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
			</button>
			<?php if ( $args['title'] ) : ?>
				<p class="docsway-menu__title"><?php echo esc_html( $args['title'] ); ?></p>
			<?php endif; ?>
			<ul class="docsway-menu__list docsway-menu__list--root" id="<?php echo esc_attr( $id ); ?>">
				<?php
				foreach ( $tree as $node ) {
					self::menu_node( $node, $current, 0 );
				}
				?>
			</ul>
		</nav>
		<?php
		return ob_get_clean();
	}

	private static function menu_node( array $n, $current, $depth ) {
		$has_kids   = ! empty( $n['children'] );
		$is_current = $current && $n['post_id'] && (int) $n['post_id'] === $current;
		$is_parent  = $has_kids && self::contains( $n['children'], $current );
		$open       = $is_current || $is_parent;

		$cls = array( 'docsway-menu__item', 'docsway-menu__item--' . $n['type'] );
		if ( ! empty( $n['file'] ) ) {
			$cls[] = 'docsway-menu__item--file';
		}
		if ( 0 === $depth ) {
			$cls[] = 'docsway-menu__item--top';
		}
		if ( $has_kids ) {
			$cls[] = 'docsway-menu__item--parent';
		}
		if ( $is_current ) {
			$cls[] = 'is-current';
		}
		if ( $is_parent ) {
			$cls[] = 'is-ancestor';
		}
		$sub_id = $has_kids ? self::uid( 'docsway-sub' ) : '';

		echo '<li class="' . esc_attr( implode( ' ', $cls ) ) . '"><div class="docsway-menu__row">';
		if ( ! empty( $n['file'] ) ) {
			printf(
				'<a class="docsway-menu__link" href="%s">%s %s</a>',
				esc_url( $n['url'] ),
				esc_html( $n['title'] ),
				self::file_badge( $n['file'] ) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
			);
		} elseif ( $n['url'] ) {
			printf(
				'<a class="docsway-menu__link" href="%s"%s>%s</a>',
				esc_url( $n['url'] ),
				$is_current ? ' aria-current="page"' : '',
				esc_html( $n['title'] )
			);
		} elseif ( $has_kids ) {
			// A heading with no page of its own toggles its section when clicked.
			printf(
				'<button type="button" class="docsway-menu__label" aria-expanded="%s" aria-controls="%s">%s</button>',
				$open ? 'true' : 'false',
				esc_attr( $sub_id ),
				esc_html( $n['title'] )
			);
		} else {
			echo '<span class="docsway-menu__label">' . esc_html( $n['title'] ) . '</span>';
		}
		if ( $has_kids ) {
			printf(
				'<button type="button" class="docsway-menu__toggle" aria-expanded="%1$s" aria-controls="%2$s"><span class="screen-reader-text">%3$s</span><svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M4 2.5 7.5 6 4 9.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>',
				$open ? 'true' : 'false',
				esc_attr( $sub_id ),
				/* translators: %s: section name */
				esc_html( sprintf( __( 'Show or hide %s', 'aneris-docsway' ), $n['title'] ) )
			);
		}
		echo '</div>';

		if ( $has_kids ) {
			echo '<ul class="docsway-menu__list" id="' . esc_attr( $sub_id ) . '"' . ( $open ? '' : ' hidden' ) . '>';
			foreach ( $n['children'] as $c ) {
				self::menu_node( $c, $current, $depth + 1 );
			}
			echo '</ul>';
		}
		echo '</li>';
	}

	/** Small file type label such as PDF, next to a document's title. */
	public static function file_badge( $type ) {
		return '<span class="docsway-file">' . esc_html( $type ) . '</span>';
	}

	private static function contains( array $nodes, $post_id ) {
		if ( ! $post_id ) {
			return false;
		}
		foreach ( $nodes as $n ) {
			if ( (int) $n['post_id'] === $post_id ) {
				return true;
			}
			if ( $n['children'] && self::contains( $n['children'], $post_id ) ) {
				return true;
			}
		}
		return false;
	}

	// ── Search box ──────────────────────────────────────────────────────────

	public static function search_box( $args = array() ) {
		$args = wp_parse_args( $args, array( 'placeholder' => '', 'button' => 'yes', 'context' => '' ) );
		// Where results are listed there is already a box, so a second one (say, in a sidebar) is only clutter.
		if ( ! in_array( $args['context'], array( 'results', 'home' ), true ) && Docsway_Settings::get( 'hide_extra_search' ) && self::on_results_page() ) {
			return '';
		}
		Docsway_Frontend::enqueue();

		$id     = self::uid( 'docsway-search' );
		$action = 'home' === $args['context'] ? self::current_url() : Docsway_Search::results_url();
		$value  = isset( $_GET['dq'] ) ? sanitize_text_field( wp_unslash( $_GET['dq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$ph     = $args['placeholder'] ? $args['placeholder'] : Docsway_Settings::placeholder();
		$key    = Docsway_Settings::get( 'shortcut' );

		ob_start();
		?>
		<form class="docsway-search" role="search" method="get" action="<?php echo esc_url( $action ); ?>" data-docsway-search>
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Search documentation', 'aneris-docsway' ); ?></label>
			<div class="docsway-search__field">
				<svg class="docsway-search__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.6 10.6 3.4 3.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
				<input type="search" id="<?php echo esc_attr( $id ); ?>" name="dq" value="<?php echo esc_attr( $value ); ?>"
					class="docsway-search__input" placeholder="<?php echo esc_attr( $ph ); ?>" autocomplete="off"
					role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="<?php echo esc_attr( $id ); ?>-list">
				<?php if ( $key ) : ?>
					<kbd class="docsway-search__kbd" aria-hidden="true">⌘K</kbd>
				<?php endif; ?>
			</div>
			<?php if ( 'no' !== $args['button'] ) : ?>
				<button type="submit" class="docsway-search__submit"><?php esc_html_e( 'Search', 'aneris-docsway' ); ?></button>
			<?php endif; ?>
			<div class="docsway-search__panel" id="<?php echo esc_attr( $id ); ?>-list" role="listbox" hidden></div>
		</form>
		<?php
		return ob_get_clean();
	}

	// ── Table of contents ───────────────────────────────────────────────────

	public static function toc( $args = array() ) {
		$args    = wp_parse_args( $args, array( 'post_id' => 0, 'title' => '', 'min' => 0, 'content' => null ) );
		$post_id = self::current_post_id( $args['post_id'] );
		$html    = null !== $args['content'] ? $args['content'] : ( $post_id ? get_post_field( 'post_content', $post_id ) : '' );
		$html    = self::content_html( $html );
		$levels  = array_map( 'intval', (array) Docsway_Settings::get( 'toc_levels' ) );
		$items   = Docsway_Headings::extract( $html, $levels );
		$min     = $args['min'] ? (int) $args['min'] : (int) Docsway_Settings::get( 'toc_min' );

		if ( count( $items ) < $min ) {
			return '';
		}
		Docsway_Frontend::enqueue();

		$title = $args['title'] ? $args['title'] : __( 'On this page', 'aneris-docsway' );
		$top   = min( wp_list_pluck( $items, 0 ) );

		ob_start();
		?>
		<nav class="docsway-toc" aria-label="<?php echo esc_attr( $title ); ?>">
			<p class="docsway-toc__title"><?php echo esc_html( $title ); ?></p>
			<ol class="docsway-toc__list">
				<?php foreach ( $items as $it ) : ?>
					<li class="docsway-toc__item docsway-toc__item--l<?php echo (int) ( $it[0] - $top ); ?>">
						<a href="#<?php echo esc_attr( $it[2] ); ?>"><?php echo esc_html( $it[1] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php
		return ob_get_clean();
	}

	// ── Previous / next ─────────────────────────────────────────────────────

	public static function prev_next( $args = array() ) {
		$args    = wp_parse_args( $args, array( 'post_id' => 0 ) );
		$post_id = self::current_post_id( $args['post_id'] );
		$i       = Docsway_Tree::index_of( $post_id );
		if ( $i < 0 ) {
			return '';
		}
		$flat = Docsway_Tree::flat();
		$prev = $i > 0 ? $flat[ $i - 1 ] : null;
		$next = isset( $flat[ $i + 1 ] ) ? $flat[ $i + 1 ] : null;
		if ( ! $prev && ! $next ) {
			return '';
		}
		Docsway_Frontend::enqueue();

		ob_start();
		?>
		<nav class="docsway-pager" aria-label="<?php esc_attr_e( 'Previous and next pages', 'aneris-docsway' ); ?>">
			<?php if ( $prev ) : ?>
				<a class="docsway-pager__link docsway-pager__link--prev" href="<?php echo esc_url( $prev['url'] ); ?>" rel="prev">
					<span class="docsway-pager__dir"><?php esc_html_e( 'Previous', 'aneris-docsway' ); ?></span>
					<span class="docsway-pager__title"><?php echo esc_html( $prev['title'] ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $next ) : ?>
				<a class="docsway-pager__link docsway-pager__link--next" href="<?php echo esc_url( $next['url'] ); ?>" rel="next">
					<span class="docsway-pager__dir"><?php esc_html_e( 'Next', 'aneris-docsway' ); ?></span>
					<span class="docsway-pager__title"><?php echo esc_html( $next['title'] ); ?></span>
				</a>
			<?php endif; ?>
		</nav>
		<?php
		return ob_get_clean();
	}

	// ── Breadcrumbs ─────────────────────────────────────────────────────────

	public static function breadcrumbs( $args = array() ) {
		$args    = wp_parse_args( $args, array( 'post_id' => 0 ) );
		$post_id = self::current_post_id( $args['post_id'] );
		$i       = Docsway_Tree::index_of( $post_id );
		if ( $i < 0 ) {
			return '';
		}
		$entry = Docsway_Tree::flat()[ $i ];
		$items = array();

		$home = (int) Docsway_Settings::get( 'home_page' );
		if ( $home && $home !== $post_id && 'publish' === get_post_status( $home ) ) {
			$items[] = array( 'title' => get_the_title( $home ), 'url' => get_permalink( $home ) );
		}
		foreach ( $entry['trail'] as $t ) {
			$items[] = $t;
		}
		$items[] = array( 'title' => $entry['title'], 'url' => '' );
		if ( count( $items ) < 2 ) {
			return '';
		}
		Docsway_Frontend::enqueue();

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(),
		);

		ob_start();
		echo '<nav class="docsway-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'aneris-docsway' ) . '"><ol>';
		foreach ( $items as $k => $it ) {
			$last = $k === count( $items ) - 1;
			echo '<li>';
			if ( $it['url'] && ! $last ) {
				echo '<a href="' . esc_url( $it['url'] ) . '">' . esc_html( $it['title'] ) . '</a>';
			} else {
				echo '<span' . ( $last ? ' aria-current="page"' : '' ) . '>' . esc_html( $it['title'] ) . '</span>';
			}
			echo '</li>';
			$el = array( '@type' => 'ListItem', 'position' => $k + 1, 'name' => $it['title'] );
			if ( $it['url'] ) {
				$el['item'] = $it['url'];
			} elseif ( $last ) {
				$el['item'] = get_permalink( $post_id );
			}
			$schema['itemListElement'][] = $el;
		}
		echo '</ol></nav>';
		wp_print_inline_script_tag( wp_json_encode( $schema ), array( 'type' => 'application/ld+json' ) );
		return ob_get_clean();
	}

	/**
	 * Full results for a search: count, list and page links. Used by the results
	 * page and the search home. $base is the page the page links point to.
	 * frontend.js builds the same markup when results load without a page reload.
	 */
	public static function results( $q, $page, $base ) {
		if ( mb_strlen( $q ) < 2 ) {
			return '<p class="docsway-results__hint">' . esc_html__( 'Type at least two characters to search the documentation.', 'aneris-docsway' ) . '</p>';
		}
		$topic = isset( $_GET['topic'] ) ? absint( $_GET['topic'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$per   = (int) Docsway_Settings::get( 'per_page' );
		$data  = Docsway_Search::run( $q, $page, $per, $topic );
		if ( 1 === $page && ! $topic ) {
			Docsway_Search::log( $q, $data['total'] );
		}
		$marks = $data['marks'];
		$link  = function ( $args ) use ( $q, $base, $data ) {
			$args = array_merge( array( 'dq' => rawurlencode( $q ), 'topic' => $data['topic'] ? $data['topic'] : false ), $args );
			return add_query_arg( array_filter( $args ), $base );
		};

		$html = '<p class="docsway-results__count" role="status">' . esc_html(
			sprintf(
				/* translators: 1: number of results, 2: search query */
				_n( '%1$s result for “%2$s”', '%1$s results for “%2$s”', $data['total'], 'aneris-docsway' ),
				number_format_i18n( $data['total'] ),
				$q
			)
		) . '</p>';

		if ( '' !== $data['corrected'] ) {
			$html .= '<p class="docsway-results__notice">' . sprintf(
				/* translators: 1: what the reader typed, 2: the corrected search */
				esc_html__( 'Nothing matched “%1$s”, so these are results for %2$s.', 'aneris-docsway' ),
				esc_html( $q ),
				'<strong>' . esc_html( $data['corrected'] ) . '</strong>'
			) . '</p>';
		} elseif ( $data['partial'] ) {
			$html .= '<p class="docsway-results__notice">' . esc_html__( 'No page has all of your words, so these pages match some of them.', 'aneris-docsway' ) . '</p>';
		}

		if ( ! $data['results'] ) {
			return $html . '<div class="docsway-results__none"><p>' . esc_html__( 'Nothing matched. Try a shorter or different word.', 'aneris-docsway' ) . '</p></div>';
		}

		if ( $data['topics'] ) {
			$html .= '<nav class="docsway-topics" aria-label="' . esc_attr__( 'Narrow results by topic', 'aneris-docsway' ) . '">';
			$html .= sprintf(
				'<a class="docsway-topic%s" href="%s" data-topic="0"%s>%s</a>',
				$data['topic'] ? '' : ' is-current',
				esc_url( $link( array( 'topic' => false ) ) ),
				$data['topic'] ? '' : ' aria-current="true"',
				esc_html__( 'All topics', 'aneris-docsway' )
			);
			foreach ( array_slice( $data['topics'], 0, 6 ) as $t ) {
				$on    = (int) $t['id'] === (int) $data['topic'];
				$html .= sprintf(
					'<a class="docsway-topic%s" href="%s" data-topic="%d"%s>%s <span class="docsway-topic__count">%s</span></a>',
					$on ? ' is-current' : '',
					esc_url( $link( array( 'topic' => (int) $t['id'] ) ) ),
					(int) $t['id'],
					$on ? ' aria-current="true"' : '',
					esc_html( $t['name'] ),
					esc_html( number_format_i18n( $t['count'] ) )
				);
			}
			$html .= '</nav>';
		}

		if ( $data['answer'] ) {
			$a     = $data['answer'];
			$path  = trim( $a['path'] . ( '' !== $a['page'] ? ' › ' . $a['page'] : '' ), ' ›' );
			$html .= '<section class="docsway-answer" aria-label="' . esc_attr__( 'Best answer', 'aneris-docsway' ) . '">';
			$html .= '<p class="docsway-answer__label">' . self::spark() . esc_html__( 'Best answer', 'aneris-docsway' ) . '</p>';
			if ( '' !== $path ) {
				$html .= '<p class="docsway-answer__path">' . esc_html( $path ) . '</p>';
			}
			$html .= '<h3 class="docsway-answer__title"><a href="' . esc_url( $a['url'] ) . '">' . Docsway_Search::highlight( $a['title'], $marks ) . '</a>' . ( $a['file'] ? ' ' . self::file_badge( $a['file'] ) : '' ) . '</h3>';
			if ( '' !== $a['text'] ) {
				$html .= '<p class="docsway-answer__text">' . Docsway_Search::highlight( $a['text'], $marks ) . '</p>';
			}
			$html .= '<a class="docsway-answer__more" href="' . esc_url( $a['url'] ) . '">' . esc_html( $a['file'] ? __( 'Open the document', 'aneris-docsway' ) : __( 'Read this section', 'aneris-docsway' ) ) . ' <span aria-hidden="true">→</span></a>';
			if ( $data['related'] ) {
				$html .= '<div class="docsway-answer__related"><p class="docsway-answer__sub">' . esc_html__( 'Also covered in', 'aneris-docsway' ) . '</p><ul>';
				foreach ( $data['related'] as $r ) {
					$html .= '<li><a href="' . esc_url( $r['url'] ) . '"><span class="docsway-answer__page">' . esc_html( $r['title'] ) . '</span>'
						. ( '' !== $r['heading'] ? '<span class="docsway-answer__heading"> › ' . esc_html( $r['heading'] ) . '</span>' : '' )
						. ( $r['file'] ? ' ' . self::file_badge( $r['file'] ) : '' ) . '</a>'
						. ( '' !== $r['text'] ? '<span class="docsway-answer__snippet">' . Docsway_Search::highlight( $r['text'], $marks ) . '</span>' : '' ) . '</li>';
				}
				$html .= '</ul></div>';
			}
			$html .= '</section>';
			$html .= '<h2 class="docsway-results__heading">' . esc_html__( 'All results', 'aneris-docsway' ) . '</h2>';
		}

		$html .= '<ol class="docsway-results__list" start="' . esc_attr( ( $page - 1 ) * $per + 1 ) . '">';
		foreach ( $data['results'] as $r ) {
			$html .= '<li class="docsway-result">';
			if ( $r['section'] ) {
				$html .= '<p class="docsway-result__section">' . esc_html( $r['section'] ) . '</p>';
			}
			$html .= '<h3 class="docsway-result__title"><a href="' . esc_url( $r['url'] ) . '">' . Docsway_Search::highlight( $r['title'], $marks ) . '</a>' . ( $r['file'] ? ' ' . self::file_badge( $r['file'] ) : '' ) . '</h3>';
			if ( $r['excerpt'] ) {
				$html .= '<p class="docsway-result__excerpt">' . Docsway_Search::highlight( $r['excerpt'], $marks ) . '</p>';
			}
			if ( $r['headings'] ) {
				$html .= '<ul class="docsway-result__headings">';
				foreach ( $r['headings'] as $h ) {
					$html .= '<li><a href="' . esc_url( $h['url'] ) . '">' . Docsway_Search::highlight( $h['text'], $marks ) . '</a>'
						. ( '' !== $h['snippet'] ? '<span class="docsway-result__snippet">' . Docsway_Search::highlight( $h['snippet'], $marks ) . '</span>' : '' ) . '</li>';
				}
				$html .= '</ul>';
			}
			$html .= '</li>';
		}
		$html .= '</ol>';

		if ( $data['pages'] > 1 ) {
			$links = paginate_links(
				array(
					'base'      => $link( array( 'pg' => '%#%' ) ),
					'format'    => '',
					'current'   => $page,
					'total'     => $data['pages'],
					'prev_text' => __( '← Previous', 'aneris-docsway' ),
					'next_text' => __( 'Next →', 'aneris-docsway' ),
				)
			);
			$html .= '<nav class="docsway-results__pages" aria-label="' . esc_attr__( 'Search results pages', 'aneris-docsway' ) . '">' . $links . '</nav>';
		}
		return '<div class="docsway-results__body" data-marks="' . esc_attr( wp_json_encode( $marks ) ) . '" data-query="' . esc_attr( $q ) . '">' . $html . '</div>';
	}

	/** Small sparkle icon for the best answer card. */
	private static function spark() {
		return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/></svg>';
	}

	/** The site logo for the standalone docs bar: the theme's custom logo, or Divi's logo setting. */
	private static function logo_url() {
		$id  = (int) get_theme_mod( 'custom_logo' );
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		if ( ! $url && function_exists( 'et_get_option' ) ) {
			$url = (string) et_get_option( 'divi_logo' );
		}
		return (string) apply_filters( 'docsway_logo_url', $url );
	}

	/** Address of the docs home, or the site's front page when none is set. */
	private static function docs_home_url() {
		$home = (int) Docsway_Settings::get( 'home_page' );
		return ( $home && 'publish' === get_post_status( $home ) ) ? get_permalink( $home ) : home_url( '/' );
	}

	/** Button readers use to switch between light and dark (the Auto light and dark style). */
	public static function scheme_toggle() {
		if ( 'auto' !== Docsway_Settings::get( 'style' ) ) {
			return '';
		}
		return '<button type="button" class="docsway-scheme" data-docsway-scheme aria-label="' . esc_attr__( 'Switch between light and dark', 'aneris-docsway' ) . '">'
			. '<svg class="docsway-scheme__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>'
			. '<svg class="docsway-scheme__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/></svg>'
			. '</button>';
	}

	/**
	 * Standalone docs bar that replaces the theme's header on docs pages
	 * (Settings, Appearance, Header and footer). $kind as in layout().
	 */
	public static function topbar( $kind ) {
		$o    = Docsway_Settings::all();
		$logo = self::logo_url();
		$name = get_bloginfo( 'name' );
		ob_start();
		?>
		<header class="docsway-topbar docsway-topbar--<?php echo esc_attr( $o['width'] ); ?>" style="--docsway-max-width: <?php echo (int) $o['max_width']; ?>px">
			<div class="docsway-topbar__inner">
				<a class="docsway-topbar__brand" href="<?php echo esc_url( self::docs_home_url() ); ?>">
					<?php if ( $logo ) : ?>
						<img class="docsway-topbar__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>">
					<?php else : ?>
						<span class="docsway-topbar__site"><?php echo esc_html( $name ); ?></span>
					<?php endif; ?>
					<span class="docsway-topbar__label"><?php esc_html_e( 'Docs', 'aneris-docsway' ); ?></span>
				</a>
				<div class="docsway-topbar__search">
					<?php
					// The docs home and the results page have their own large search box.
					if ( 'doc' === $kind ) {
						echo self::search_box( array( 'button' => 'no' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
					}
					?>
				</div>
				<div class="docsway-topbar__links">
					<?php echo self::scheme_toggle(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
					<a class="docsway-topbar__back" href="<?php echo esc_url( self::back_url() ); ?>"><?php echo esc_html( '' !== $o['back_label'] ? $o['back_label'] : sprintf( /* translators: %s: site name */ __( 'Back to %s', 'aneris-docsway' ), $name ) ); ?></a>
				</div>
			</div>
		</header>
		<?php
		return ob_get_clean();
	}

	/** Where the docs bar and footer link back to: Settings, Appearance, Back link, or the home page. */
	private static function back_url() {
		$url = (string) Docsway_Settings::get( 'back_url' );
		return '' !== $url ? $url : home_url( '/' );
	}

	/** Footer for the standalone docs bar. */
	public static function docs_footer() {
		$o = Docsway_Settings::all();
		return '<footer class="docsway-docs-footer docsway-topbar--' . esc_attr( $o['width'] ) . '" style="--docsway-max-width: ' . (int) $o['max_width'] . 'px"><div class="docsway-topbar__inner">'
			. '<span>&copy; ' . esc_html( wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ) ) . '</span>'
			. '<a href="' . esc_url( self::back_url() ) . '">' . esc_html( '' !== $o['back_label'] ? $o['back_label'] : __( 'Back to the main site', 'aneris-docsway' ) ) . '</a>'
			. '</div></footer>';
	}

	/** "Updated 3 May 2026 · 4 min read" under a doc page title, as chosen in Settings. */
	public static function page_meta( $post_id ) {
		$parts = array();
		if ( Docsway_Settings::get( 'show_updated' ) ) {
			$parts[] = '<span class="docsway-meta__updated">' . esc_html__( 'Updated', 'aneris-docsway' ) . ' <time datetime="' . esc_attr( get_post_modified_time( 'c', true, $post_id ) ) . '">' . esc_html( get_the_modified_date( '', $post_id ) ) . '</time></span>';
		}
		if ( Docsway_Settings::get( 'show_reading' ) ) {
			$words   = count( preg_split( '/\s+/u', Docsway_Search::plain_text( get_post_field( 'post_content', $post_id ) ), -1, PREG_SPLIT_NO_EMPTY ) );
			$minutes = max( 1, (int) round( $words / 200 ) );
			/* translators: %d: minutes */
			$parts[] = '<span class="docsway-meta__reading">' . esc_html( sprintf( _n( '%d min read', '%d min read', $minutes, 'aneris-docsway' ), $minutes ) ) . '</span>';
		}
		return $parts ? '<p class="docsway-meta">' . implode( '<span class="docsway-meta__sep" aria-hidden="true">·</span>', $parts ) . '</p>' : '';
	}

	/** "Was this page helpful?" with Yes and No. Votes go to Docsway_Feedback through the REST API. */
	public static function feedback( $post_id ) {
		if ( ! Docsway_Settings::get( 'feedback' ) ) {
			return '';
		}
		Docsway_Frontend::enqueue();
		return '<div class="docsway-feedback" data-docsway-feedback="' . (int) $post_id . '">'
			. '<p class="docsway-feedback__question">' . esc_html__( 'Was this page helpful?', 'aneris-docsway' ) . '</p>'
			. '<div class="docsway-feedback__buttons">'
			. '<button type="button" class="docsway-feedback__button" data-vote="yes">' . esc_html__( 'Yes', 'aneris-docsway' ) . '</button>'
			. '<button type="button" class="docsway-feedback__button" data-vote="no">' . esc_html__( 'No', 'aneris-docsway' ) . '</button>'
			. '</div>'
			. '<p class="docsway-feedback__thanks" role="status" hidden>' . esc_html__( 'Thanks for letting us know.', 'aneris-docsway' ) . '</p>'
			. '</div>';
	}

	/**
	 * The Docsway docs layout: sidebar with search and menu, the page itself with
	 * breadcrumbs, title and previous / next, and the contents list in a column of
	 * its own. $kind is 'doc', 'home' or 'results' (see Docsway_Layout::page_kind()).
	 */
	public static function layout( $kind ) {
		$o       = Docsway_Settings::all();
		$post_id = (int) get_queried_object_id();

		// The page first: the_content adds the heading anchors the contents list links to.
		ob_start();
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		$content = ob_get_clean();
		if ( 'home' === $kind && false === strpos( $content, 'docsway-home' ) ) {
			$content = self::search_home() . $content; // A docs home without one still gets search.
		}

		$toc = '';
		if ( 'doc' === $kind && $o['toc_auto'] && 'side' === $o['toc_position'] ) {
			$toc = self::toc( array( 'post_id' => $post_id, 'content' => $content ) );
		}

		$classes = array(
			'docsway-layout',
			'docsway-layout--sidebar-' . $o['sidebar'],
			'docsway-layout--' . $o['width'],
			'docsway-layout--' . $kind,
		);
		if ( $toc ) {
			$classes[] = 'docsway-layout--with-toc';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="--docsway-max-width: <?php echo (int) $o['max_width']; ?>px">
			<div class="docsway-layout__inner">
				<aside class="docsway-layout__sidebar" aria-label="<?php esc_attr_e( 'Documentation', 'aneris-docsway' ); ?>">
					<div class="docsway-layout__sticky">
						<?php
						// The docs home and the results page have a search box of their own, and the
						// standalone docs bar holds both search and the light / dark switch.
						if ( 'theme' === $o['chrome'] ) {
							$tools = ( 'doc' === $kind ? self::search_box( array( 'button' => 'no' ) ) : '' ) . self::scheme_toggle();
							if ( $tools ) {
								echo '<div class="docsway-layout__tools">' . $tools . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
							}
						}
						echo self::menu(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
						?>
					</div>
				</aside>
				<main class="docsway-layout__main" id="wp--skip-link--target">
					<?php
					if ( 'doc' === $kind && $o['crumbs_auto'] ) {
						echo self::breadcrumbs( array( 'post_id' => $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
					}
					?>
					<article id="post-<?php echo (int) $post_id; ?>" <?php post_class( 'docsway-layout__article', $post_id ); ?>>
						<?php if ( 'home' !== $kind ) : ?>
							<h1 class="docsway-layout__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
						<?php endif; ?>
						<?php
						if ( 'doc' === $kind ) {
							echo self::page_meta( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
						}
						?>
						<?php if ( $toc ) : ?>
							<div class="docsway-layout__toc-inline"><?php echo $toc; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></div>
						<?php endif; ?>
						<div class="entry-content docsway-layout__content">
							<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- the post content, filtered by WordPress. ?>
						</div>
					</article>
					<?php
					if ( 'doc' === $kind ) {
						echo self::feedback( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
					}
					if ( 'doc' === $kind && $o['prevnext_auto'] ) {
						echo self::prev_next( array( 'post_id' => $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
					}
					$edit = get_edit_post_link( $post_id );
					if ( $edit ) {
						echo '<p class="docsway-layout__edit"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit this page', 'aneris-docsway' ) . '</a></p>';
					}
					?>
				</main>
				<?php if ( $toc ) : ?>
					<aside class="docsway-layout__toc" aria-label="<?php esc_attr_e( 'On this page', 'aneris-docsway' ); ?>">
						<div class="docsway-layout__sticky"><?php echo $toc; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></div>
					</aside>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Icons for topic cards: key => array( label, SVG path data on a 24 x 24 grid ). */
	public static function icons() {
		return array(
			'book'     => array( __( 'Book', 'aneris-docsway' ), 'M4 5a2 2 0 0 1 2-2h13v15H6a2 2 0 0 0-2 2V5Zm0 15a2 2 0 0 0 2 2h13v-4' ),
			'rocket'   => array( __( 'Rocket', 'aneris-docsway' ), 'M12 15l-3-3c1.5-4.5 4.5-8 10-9-1 5.5-4.5 8.5-9 10M9 12H5l2-4h4M12 15v4l4-2v-4M6 17c-1 1-1.5 3-1.5 3s2-.5 3-1.5' ),
			'gear'     => array( __( 'Settings', 'aneris-docsway' ), 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.3l2-1.6-2-3.4-2.4 1a7.3 7.3 0 0 0-2.2-1.3L14.4 2h-4l-.4 2.4a7.3 7.3 0 0 0-2.2 1.3l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.6l-2 1.6 2 3.4 2.4-1c.7.6 1.4 1 2.2 1.3l.4 2.4h4l.4-2.4c.8-.3 1.5-.7 2.2-1.3l2.4 1 2-3.4-2-1.6c.1-.4.1-.9.1-1.3Z' ),
			'code'     => array( __( 'Code', 'aneris-docsway' ), 'M8 8l-4 4 4 4M16 8l4 4-4 4M14 4l-4 16' ),
			'terminal' => array( __( 'Terminal', 'aneris-docsway' ), 'M3 5h18v14H3zM7 9l3 3-3 3M13 15h4' ),
			'plug'     => array( __( 'Integration', 'aneris-docsway' ), 'M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0V7Zm6 10v5' ),
			'chart'    => array( __( 'Chart', 'aneris-docsway' ), 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3' ),
			'layers'   => array( __( 'Layers', 'aneris-docsway' ), 'M12 3l9 5-9 5-9-5 9-5Zm-9 9l9 5 9-5M3 16l9 5 9-5' ),
			'shield'   => array( __( 'Security', 'aneris-docsway' ), 'M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3Z' ),
			'users'    => array( __( 'People', 'aneris-docsway' ), 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 10a7 7 0 0 1 14 0M17 3.5a4 4 0 0 1 0 7M22 21a7 7 0 0 0-4-6.3' ),
			'download' => array( __( 'Download', 'aneris-docsway' ), 'M12 3v12M7 10l5 5 5-5M4 20h16' ),
			'help'     => array( __( 'Help', 'aneris-docsway' ), 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM9.5 9a2.5 2.5 0 0 1 4.9.7c0 1.8-2.4 2.3-2.4 3.8M12 17h.01' ),
		);
	}

	public static function icon_svg( $key, $size = 24 ) {
		$icons = self::icons();
		$path  = isset( $icons[ $key ] ) ? $icons[ $key ][1] : $icons['book'][1];
		return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . esc_attr( $path ) . '"/></svg>';
	}

	/** Buttons that run a ready-made search. Without JavaScript they open $base (default: where results are listed). */
	public static function quick_searches( $class = '', $base = null ) {
		$items = (array) Docsway_Settings::get( 'quick_searches' );
		if ( ! $items ) {
			return '';
		}
		$results = null !== $base ? $base : Docsway_Search::results_url();
		$out     = '<div class="docsway-chips' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '">';
		foreach ( $items as $it ) {
			$href = $results ? add_query_arg( 'dq', rawurlencode( $it['query'] ), $results ) : '#';
			$out .= '<a class="docsway-chip" href="' . esc_url( $href ) . '" data-docsway-query="' . esc_attr( $it['query'] ) . '">' . esc_html( $it['label'] ) . '</a>';
		}
		return $out . '</div>';
	}

	/**
	 * Docs home: heading, large search box, quick searches and topic cards.
	 * Everything comes from Settings, Search home; the arguments only override text.
	 */
	public static function search_home( $args = array() ) {
		$o    = Docsway_Settings::all();
		$args = wp_parse_args( $args, array( 'title' => '', 'intro' => '', 'topics_title' => '' ) );

		$title = '' !== $args['title'] ? $args['title'] : ( '' !== $o['home_title'] ? $o['home_title'] : __( 'How can we help?', 'aneris-docsway' ) );
		$intro = '' !== $args['intro'] ? $args['intro'] : $o['home_intro'];
		$ttl   = '' !== $args['topics_title'] ? $args['topics_title'] : ( '' !== $o['topics_title'] ? $o['topics_title'] : __( 'Popular topics', 'aneris-docsway' ) );

		$cards = array();
		foreach ( (array) $o['topics'] as $t ) {
			if ( 'publish' === get_post_status( $t['page'] ) ) {
				$cards[] = $t + array( 'url' => get_permalink( $t['page'] ) );
			}
		}

		// Results are listed right here, on this page, below the search box. With JavaScript
		// they load without a page reload (frontend.js); without it the page reloads with ?dq=.
		$here = self::current_url();
		$q    = self::query();
		$page = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification

		ob_start();
		?>
		<section class="docsway-home<?php echo '' !== $q ? ' is-searching' : ''; ?>" data-docsway-home data-base="<?php echo esc_url( $here ); ?>">
			<h2 class="docsway-home__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $intro ) : ?>
				<p class="docsway-home__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
			<?php echo self::search_box( array( 'context' => 'home' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
			<?php echo self::quick_searches( '', $here ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
			<div class="docsway-home__results docsway-results" aria-live="polite"<?php echo '' === $q ? ' hidden' : ''; ?>>
				<p class="docsway-home__back"><a href="<?php echo esc_url( $here ); ?>">&larr; <?php echo esc_html( $cards ? __( 'Back to topics', 'aneris-docsway' ) : __( 'Clear search', 'aneris-docsway' ) ); ?></a></p>
				<div class="docsway-home__list"><?php echo '' !== $q ? self::results( $q, $page, $here ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></div>
			</div>
			<?php if ( $cards ) : ?>
				<div class="docsway-home__topics">
				<h3 class="docsway-home__subtitle"><?php echo esc_html( $ttl ); ?></h3>
				<div class="docsway-cards">
					<?php foreach ( $cards as $c ) : ?>
						<a class="docsway-card" href="<?php echo esc_url( $c['url'] ); ?>">
							<span class="docsway-card__icon"><?php echo self::icon_svg( $c['icon'], 28 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from a fixed list. ?></span>
							<span class="docsway-card__title"><?php echo esc_html( $c['title'] ); ?></span>
							<?php if ( '' !== $c['text'] ) : ?>
								<span class="docsway-card__text"><?php echo esc_html( $c['text'] ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Post content as HTML that headings and text can be read from.
	 *
	 * Divi 5 saves module text inside block attributes as JSON, next to layout
	 * data, with the < and > of HTML tags (and & and quotes) written as JSON
	 * escape codes. For Divi 5 content the HTML strings from every block are
	 * collected in page order; anything else is returned unchanged.
	 */
	public static function content_html( $content ) {
		$content = (string) $content;
		if ( false === strpos( $content, '<!-- wp:divi/' ) ) {
			return $content;
		}
		$out  = array();
		$walk = function ( $value ) use ( &$walk, &$out ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					$walk( $v );
				}
			} elseif ( is_string( $value ) && false !== strpos( $value, '<' ) ) {
				$out[] = $value;
			}
		};
		$blocks = function ( array $list ) use ( &$blocks, $walk ) {
			foreach ( $list as $b ) {
				$walk( $b['attrs'] );
				$blocks( $b['innerBlocks'] );
			}
		};
		$blocks( parse_blocks( $content ) );
		return implode( "\n", $out );
	}

	/**
	 * Sample menu, search, breadcrumbs, contents and pager with made-up
	 * pages, for the style preview in the admin. Uses the same classes as
	 * the real components so every style applies to it unchanged.
	 */
	public static function style_preview( $style ) {
		$chev = '<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M4 2.5 7.5 6 4 9.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		ob_start();
		?>
		<div class="dsw-look docsway-style-<?php echo esc_attr( $style ); ?>" inert>
			<div class="dsw-look__side">
				<div class="docsway-search">
					<div class="docsway-search__field">
						<svg class="docsway-search__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.6 10.6 3.4 3.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
						<input type="text" class="docsway-search__input" placeholder="<?php echo esc_attr( Docsway_Settings::placeholder() ); ?>" tabindex="-1">
						<kbd class="docsway-search__kbd">⌘K</kbd>
					</div>
				</div>
				<nav class="docsway-menu">
					<p class="docsway-menu__title"><?php esc_html_e( 'Documentation', 'aneris-docsway' ); ?></p>
					<ul class="docsway-menu__list docsway-menu__list--root">
						<li class="docsway-menu__item docsway-menu__item--top"><div class="docsway-menu__row"><span class="docsway-menu__label"><?php esc_html_e( 'Getting started', 'aneris-docsway' ); ?></span><span class="docsway-menu__toggle"><?php echo $chev; // phpcs:ignore ?></span></div></li>
						<li class="docsway-menu__item docsway-menu__item--top is-ancestor"><div class="docsway-menu__row"><span class="docsway-menu__label"><?php esc_html_e( 'Installation', 'aneris-docsway' ); ?></span><span class="docsway-menu__toggle" aria-expanded="true"><?php echo $chev; // phpcs:ignore ?></span></div>
							<ul class="docsway-menu__list">
								<li class="docsway-menu__item"><div class="docsway-menu__row"><a class="docsway-menu__link" href="#"><?php esc_html_e( 'Requirements', 'aneris-docsway' ); ?></a></div></li>
								<li class="docsway-menu__item is-current"><div class="docsway-menu__row"><a class="docsway-menu__link" href="#"><?php esc_html_e( 'Quick install', 'aneris-docsway' ); ?></a></div></li>
								<li class="docsway-menu__item"><div class="docsway-menu__row"><a class="docsway-menu__link" href="#"><?php esc_html_e( 'Upgrading', 'aneris-docsway' ); ?></a></div></li>
							</ul>
						</li>
						<li class="docsway-menu__item docsway-menu__item--top"><div class="docsway-menu__row"><span class="docsway-menu__label"><?php esc_html_e( 'Troubleshooting', 'aneris-docsway' ); ?></span><span class="docsway-menu__toggle"><?php echo $chev; // phpcs:ignore ?></span></div></li>
					</ul>
				</nav>
			</div>
			<div class="dsw-look__main">
				<nav class="docsway-crumbs"><ol><li><a href="#"><?php esc_html_e( 'Documentation', 'aneris-docsway' ); ?></a></li><li><a href="#"><?php esc_html_e( 'Installation', 'aneris-docsway' ); ?></a></li><li><span><?php esc_html_e( 'Quick install', 'aneris-docsway' ); ?></span></li></ol></nav>
				<nav class="docsway-toc">
					<p class="docsway-toc__title"><?php esc_html_e( 'On this page', 'aneris-docsway' ); ?></p>
					<ol class="docsway-toc__list">
						<li class="docsway-toc__item docsway-toc__item--l0"><a href="#" class="is-active"><?php esc_html_e( 'Before you start', 'aneris-docsway' ); ?></a></li>
						<li class="docsway-toc__item docsway-toc__item--l0"><a href="#"><?php esc_html_e( 'Install the files', 'aneris-docsway' ); ?></a></li>
						<li class="docsway-toc__item docsway-toc__item--l1"><a href="#"><?php esc_html_e( 'Check it works', 'aneris-docsway' ); ?></a></li>
					</ol>
				</nav>
				<nav class="docsway-pager">
					<a class="docsway-pager__link docsway-pager__link--prev" href="#"><span class="docsway-pager__dir"><?php esc_html_e( 'Previous', 'aneris-docsway' ); ?></span><span class="docsway-pager__title"><?php esc_html_e( 'Requirements', 'aneris-docsway' ); ?></span></a>
					<a class="docsway-pager__link docsway-pager__link--next" href="#"><span class="docsway-pager__dir"><?php esc_html_e( 'Next', 'aneris-docsway' ); ?></span><span class="docsway-pager__title"><?php esc_html_e( 'Upgrading', 'aneris-docsway' ); ?></span></a>
				</nav>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
