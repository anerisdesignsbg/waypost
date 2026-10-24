<?php
/**
 * HTML output for every front-end component. Shortcodes, blocks and the
 * Divi modules all call these methods, so output is identical everywhere.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Render {

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

	private static function on_results_page() {
		$results = (int) Waypost_Settings::get( 'results_page' );
		return $results && is_singular() && (int) get_queried_object_id() === $results;
	}

	// ── Menu ────────────────────────────────────────────────────────────────

	public static function menu( $args = array() ) {
		$args = wp_parse_args( $args, array( 'post_id' => 0, 'title' => '' ) );
		Waypost_Frontend::enqueue();

		$tree = Waypost_Tree::get();
		if ( ! $tree ) {
			return current_user_can( 'edit_posts' )
				? '<p class="waypost-empty">' . esc_html__( 'Waypost: no sections with published pages yet. Add pages to a section to build the menu.', 'waypost' ) . '</p>'
				: '';
		}

		$current = self::current_post_id( $args['post_id'] );
		$id      = self::uid( 'waypost-menu' );
		$label   = $args['title'] ? $args['title'] : __( 'Documentation', 'waypost' );

		ob_start();
		?>
		<nav class="waypost-menu" aria-label="<?php echo esc_attr( $label ); ?>" data-accordion="<?php echo Waypost_Settings::get( 'accordion' ) ? '1' : '0'; ?>">
			<button type="button" class="waypost-menu__mobile" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>">
				<span><?php echo esc_html( $label ); ?></span>
				<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 4h12M2 8h12M2 12h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
			</button>
			<?php if ( $args['title'] ) : ?>
				<p class="waypost-menu__title"><?php echo esc_html( $args['title'] ); ?></p>
			<?php endif; ?>
			<ul class="waypost-menu__list waypost-menu__list--root" id="<?php echo esc_attr( $id ); ?>">
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

		$cls = array( 'waypost-menu__item', 'waypost-menu__item--' . $n['type'] );
		if ( 0 === $depth ) {
			$cls[] = 'waypost-menu__item--top';
		}
		if ( $has_kids ) {
			$cls[] = 'waypost-menu__item--parent';
		}
		if ( $is_current ) {
			$cls[] = 'is-current';
		}
		if ( $is_parent ) {
			$cls[] = 'is-ancestor';
		}
		$sub_id = $has_kids ? self::uid( 'waypost-sub' ) : '';

		echo '<li class="' . esc_attr( implode( ' ', $cls ) ) . '"><div class="waypost-menu__row">';
		if ( $n['url'] ) {
			printf(
				'<a class="waypost-menu__link" href="%s"%s>%s</a>',
				esc_url( $n['url'] ),
				$is_current ? ' aria-current="page"' : '',
				esc_html( $n['title'] )
			);
		} elseif ( $has_kids ) {
			// A heading with no page of its own toggles its section when clicked.
			printf(
				'<button type="button" class="waypost-menu__label" aria-expanded="%s" aria-controls="%s">%s</button>',
				$open ? 'true' : 'false',
				esc_attr( $sub_id ),
				esc_html( $n['title'] )
			);
		} else {
			echo '<span class="waypost-menu__label">' . esc_html( $n['title'] ) . '</span>';
		}
		if ( $has_kids ) {
			printf(
				'<button type="button" class="waypost-menu__toggle" aria-expanded="%1$s" aria-controls="%2$s"><span class="screen-reader-text">%3$s</span><svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M4 2.5 7.5 6 4 9.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>',
				$open ? 'true' : 'false',
				esc_attr( $sub_id ),
				/* translators: %s: section name */
				esc_html( sprintf( __( 'Show or hide %s', 'waypost' ), $n['title'] ) )
			);
		}
		echo '</div>';

		if ( $has_kids ) {
			echo '<ul class="waypost-menu__list" id="' . esc_attr( $sub_id ) . '"' . ( $open ? '' : ' hidden' ) . '>';
			foreach ( $n['children'] as $c ) {
				self::menu_node( $c, $current, $depth + 1 );
			}
			echo '</ul>';
		}
		echo '</li>';
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
		// The results page has its own box, so a second one (say, in a sidebar) is only clutter.
		if ( 'results' !== $args['context'] && Waypost_Settings::get( 'hide_extra_search' ) && self::on_results_page() ) {
			return '';
		}
		Waypost_Frontend::enqueue();

		$id     = self::uid( 'waypost-search' );
		$action = Waypost_Search::results_url();
		$value  = isset( $_GET['dq'] ) ? sanitize_text_field( wp_unslash( $_GET['dq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$ph     = $args['placeholder'] ? $args['placeholder'] : Waypost_Settings::placeholder();
		$key    = Waypost_Settings::get( 'shortcut' );

		ob_start();
		?>
		<form class="waypost-search" role="search" method="get" action="<?php echo esc_url( $action ); ?>" data-waypost-search>
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Search documentation', 'waypost' ); ?></label>
			<div class="waypost-search__field">
				<svg class="waypost-search__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.6 10.6 3.4 3.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
				<input type="search" id="<?php echo esc_attr( $id ); ?>" name="dq" value="<?php echo esc_attr( $value ); ?>"
					class="waypost-search__input" placeholder="<?php echo esc_attr( $ph ); ?>" autocomplete="off"
					role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="<?php echo esc_attr( $id ); ?>-list">
				<?php if ( $key ) : ?>
					<kbd class="waypost-search__kbd" aria-hidden="true">⌘K</kbd>
				<?php endif; ?>
			</div>
			<?php if ( 'no' !== $args['button'] ) : ?>
				<button type="submit" class="waypost-search__submit"><?php esc_html_e( 'Search', 'waypost' ); ?></button>
			<?php endif; ?>
			<div class="waypost-search__panel" id="<?php echo esc_attr( $id ); ?>-list" role="listbox" hidden></div>
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
		$levels  = array_map( 'intval', (array) Waypost_Settings::get( 'toc_levels' ) );
		$items   = Waypost_Headings::extract( $html, $levels );
		$min     = $args['min'] ? (int) $args['min'] : (int) Waypost_Settings::get( 'toc_min' );

		if ( count( $items ) < $min ) {
			return '';
		}
		Waypost_Frontend::enqueue();

		$title = $args['title'] ? $args['title'] : __( 'On this page', 'waypost' );
		$top   = min( wp_list_pluck( $items, 0 ) );

		ob_start();
		?>
		<nav class="waypost-toc" aria-label="<?php echo esc_attr( $title ); ?>">
			<p class="waypost-toc__title"><?php echo esc_html( $title ); ?></p>
			<ol class="waypost-toc__list">
				<?php foreach ( $items as $it ) : ?>
					<li class="waypost-toc__item waypost-toc__item--l<?php echo (int) ( $it[0] - $top ); ?>">
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
		$i       = Waypost_Tree::index_of( $post_id );
		if ( $i < 0 ) {
			return '';
		}
		$flat = Waypost_Tree::flat();
		$prev = $i > 0 ? $flat[ $i - 1 ] : null;
		$next = isset( $flat[ $i + 1 ] ) ? $flat[ $i + 1 ] : null;
		if ( ! $prev && ! $next ) {
			return '';
		}
		Waypost_Frontend::enqueue();

		ob_start();
		?>
		<nav class="waypost-pager" aria-label="<?php esc_attr_e( 'Previous and next pages', 'waypost' ); ?>">
			<?php if ( $prev ) : ?>
				<a class="waypost-pager__link waypost-pager__link--prev" href="<?php echo esc_url( $prev['url'] ); ?>" rel="prev">
					<span class="waypost-pager__dir"><?php esc_html_e( 'Previous', 'waypost' ); ?></span>
					<span class="waypost-pager__title"><?php echo esc_html( $prev['title'] ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $next ) : ?>
				<a class="waypost-pager__link waypost-pager__link--next" href="<?php echo esc_url( $next['url'] ); ?>" rel="next">
					<span class="waypost-pager__dir"><?php esc_html_e( 'Next', 'waypost' ); ?></span>
					<span class="waypost-pager__title"><?php echo esc_html( $next['title'] ); ?></span>
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
		$i       = Waypost_Tree::index_of( $post_id );
		if ( $i < 0 ) {
			return '';
		}
		$entry = Waypost_Tree::flat()[ $i ];
		$items = array();

		$home = (int) Waypost_Settings::get( 'home_page' );
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
		Waypost_Frontend::enqueue();

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(),
		);

		ob_start();
		echo '<nav class="waypost-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'waypost' ) . '"><ol>';
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
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
		return ob_get_clean();
	}

	/** Icons for topic cards: key => array( label, SVG path data on a 24 x 24 grid ). */
	public static function icons() {
		return array(
			'book'     => array( __( 'Book', 'waypost' ), 'M4 5a2 2 0 0 1 2-2h13v15H6a2 2 0 0 0-2 2V5Zm0 15a2 2 0 0 0 2 2h13v-4' ),
			'rocket'   => array( __( 'Rocket', 'waypost' ), 'M12 15l-3-3c1.5-4.5 4.5-8 10-9-1 5.5-4.5 8.5-9 10M9 12H5l2-4h4M12 15v4l4-2v-4M6 17c-1 1-1.5 3-1.5 3s2-.5 3-1.5' ),
			'gear'     => array( __( 'Settings', 'waypost' ), 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.3l2-1.6-2-3.4-2.4 1a7.3 7.3 0 0 0-2.2-1.3L14.4 2h-4l-.4 2.4a7.3 7.3 0 0 0-2.2 1.3l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.6l-2 1.6 2 3.4 2.4-1c.7.6 1.4 1 2.2 1.3l.4 2.4h4l.4-2.4c.8-.3 1.5-.7 2.2-1.3l2.4 1 2-3.4-2-1.6c.1-.4.1-.9.1-1.3Z' ),
			'code'     => array( __( 'Code', 'waypost' ), 'M8 8l-4 4 4 4M16 8l4 4-4 4M14 4l-4 16' ),
			'terminal' => array( __( 'Terminal', 'waypost' ), 'M3 5h18v14H3zM7 9l3 3-3 3M13 15h4' ),
			'plug'     => array( __( 'Integration', 'waypost' ), 'M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0V7Zm6 10v5' ),
			'chart'    => array( __( 'Chart', 'waypost' ), 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3' ),
			'layers'   => array( __( 'Layers', 'waypost' ), 'M12 3l9 5-9 5-9-5 9-5Zm-9 9l9 5 9-5M3 16l9 5 9-5' ),
			'shield'   => array( __( 'Security', 'waypost' ), 'M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3Z' ),
			'users'    => array( __( 'People', 'waypost' ), 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 10a7 7 0 0 1 14 0M17 3.5a4 4 0 0 1 0 7M22 21a7 7 0 0 0-4-6.3' ),
			'download' => array( __( 'Download', 'waypost' ), 'M12 3v12M7 10l5 5 5-5M4 20h16' ),
			'help'     => array( __( 'Help', 'waypost' ), 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM9.5 9a2.5 2.5 0 0 1 4.9.7c0 1.8-2.4 2.3-2.4 3.8M12 17h.01' ),
		);
	}

	public static function icon_svg( $key, $size = 24 ) {
		$icons = self::icons();
		$path  = isset( $icons[ $key ] ) ? $icons[ $key ][1] : $icons['book'][1];
		return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . esc_attr( $path ) . '"/></svg>';
	}

	/** Buttons that run a ready-made search. Without JavaScript they open the results page. */
	public static function quick_searches( $class = '' ) {
		$items = (array) Waypost_Settings::get( 'quick_searches' );
		if ( ! $items ) {
			return '';
		}
		$results = Waypost_Search::results_url();
		$out     = '<div class="waypost-chips' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '">';
		foreach ( $items as $it ) {
			$href = $results ? add_query_arg( 'dq', rawurlencode( $it['query'] ), $results ) : '#';
			$out .= '<a class="waypost-chip" href="' . esc_url( $href ) . '" data-waypost-query="' . esc_attr( $it['query'] ) . '">' . esc_html( $it['label'] ) . '</a>';
		}
		return $out . '</div>';
	}

	/**
	 * Docs home: heading, large search box, quick searches and topic cards.
	 * Everything comes from Settings, Search home; the arguments only override text.
	 */
	public static function search_home( $args = array() ) {
		$o    = Waypost_Settings::all();
		$args = wp_parse_args( $args, array( 'title' => '', 'intro' => '', 'topics_title' => '' ) );

		$title = '' !== $args['title'] ? $args['title'] : ( '' !== $o['home_title'] ? $o['home_title'] : __( 'How can we help?', 'waypost' ) );
		$intro = '' !== $args['intro'] ? $args['intro'] : $o['home_intro'];
		$ttl   = '' !== $args['topics_title'] ? $args['topics_title'] : ( '' !== $o['topics_title'] ? $o['topics_title'] : __( 'Popular topics', 'waypost' ) );

		$cards = array();
		foreach ( (array) $o['topics'] as $t ) {
			if ( 'publish' === get_post_status( $t['page'] ) ) {
				$cards[] = $t + array( 'url' => get_permalink( $t['page'] ) );
			}
		}

		ob_start();
		?>
		<section class="waypost-home">
			<h2 class="waypost-home__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $intro ) : ?>
				<p class="waypost-home__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
			<?php echo self::search_box(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
			<?php echo self::quick_searches(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
			<?php if ( $cards ) : ?>
				<h3 class="waypost-home__subtitle"><?php echo esc_html( $ttl ); ?></h3>
				<div class="waypost-cards">
					<?php foreach ( $cards as $c ) : ?>
						<a class="waypost-card" href="<?php echo esc_url( $c['url'] ); ?>">
							<span class="waypost-card__icon"><?php echo self::icon_svg( $c['icon'], 28 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from a fixed list. ?></span>
							<span class="waypost-card__title"><?php echo esc_html( $c['title'] ); ?></span>
							<?php if ( '' !== $c['text'] ) : ?>
								<span class="waypost-card__text"><?php echo esc_html( $c['text'] ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
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
		<div class="wpst-look waypost-style-<?php echo esc_attr( $style ); ?>" inert>
			<div class="wpst-look__side">
				<div class="waypost-search">
					<div class="waypost-search__field">
						<svg class="waypost-search__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.6 10.6 3.4 3.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
						<input type="text" class="waypost-search__input" placeholder="<?php echo esc_attr( Waypost_Settings::placeholder() ); ?>" tabindex="-1">
						<kbd class="waypost-search__kbd">⌘K</kbd>
					</div>
				</div>
				<nav class="waypost-menu">
					<p class="waypost-menu__title"><?php esc_html_e( 'Documentation', 'waypost' ); ?></p>
					<ul class="waypost-menu__list waypost-menu__list--root">
						<li class="waypost-menu__item waypost-menu__item--top"><div class="waypost-menu__row"><span class="waypost-menu__label"><?php esc_html_e( 'Getting started', 'waypost' ); ?></span><span class="waypost-menu__toggle"><?php echo $chev; // phpcs:ignore ?></span></div></li>
						<li class="waypost-menu__item waypost-menu__item--top is-ancestor"><div class="waypost-menu__row"><span class="waypost-menu__label"><?php esc_html_e( 'Installation', 'waypost' ); ?></span><span class="waypost-menu__toggle" aria-expanded="true"><?php echo $chev; // phpcs:ignore ?></span></div>
							<ul class="waypost-menu__list">
								<li class="waypost-menu__item"><div class="waypost-menu__row"><a class="waypost-menu__link" href="#"><?php esc_html_e( 'Requirements', 'waypost' ); ?></a></div></li>
								<li class="waypost-menu__item is-current"><div class="waypost-menu__row"><a class="waypost-menu__link" href="#"><?php esc_html_e( 'Quick install', 'waypost' ); ?></a></div></li>
								<li class="waypost-menu__item"><div class="waypost-menu__row"><a class="waypost-menu__link" href="#"><?php esc_html_e( 'Upgrading', 'waypost' ); ?></a></div></li>
							</ul>
						</li>
						<li class="waypost-menu__item waypost-menu__item--top"><div class="waypost-menu__row"><span class="waypost-menu__label"><?php esc_html_e( 'Troubleshooting', 'waypost' ); ?></span><span class="waypost-menu__toggle"><?php echo $chev; // phpcs:ignore ?></span></div></li>
					</ul>
				</nav>
			</div>
			<div class="wpst-look__main">
				<nav class="waypost-crumbs"><ol><li><a href="#"><?php esc_html_e( 'Documentation', 'waypost' ); ?></a></li><li><a href="#"><?php esc_html_e( 'Installation', 'waypost' ); ?></a></li><li><span><?php esc_html_e( 'Quick install', 'waypost' ); ?></span></li></ol></nav>
				<nav class="waypost-toc">
					<p class="waypost-toc__title"><?php esc_html_e( 'On this page', 'waypost' ); ?></p>
					<ol class="waypost-toc__list">
						<li class="waypost-toc__item waypost-toc__item--l0"><a href="#" class="is-active"><?php esc_html_e( 'Before you start', 'waypost' ); ?></a></li>
						<li class="waypost-toc__item waypost-toc__item--l0"><a href="#"><?php esc_html_e( 'Install the files', 'waypost' ); ?></a></li>
						<li class="waypost-toc__item waypost-toc__item--l1"><a href="#"><?php esc_html_e( 'Check it works', 'waypost' ); ?></a></li>
					</ol>
				</nav>
				<nav class="waypost-pager">
					<a class="waypost-pager__link waypost-pager__link--prev" href="#"><span class="waypost-pager__dir"><?php esc_html_e( 'Previous', 'waypost' ); ?></span><span class="waypost-pager__title"><?php esc_html_e( 'Requirements', 'waypost' ); ?></span></a>
					<a class="waypost-pager__link waypost-pager__link--next" href="#"><span class="waypost-pager__dir"><?php esc_html_e( 'Next', 'waypost' ); ?></span><span class="waypost-pager__title"><?php esc_html_e( 'Upgrading', 'waypost' ); ?></span></a>
				</nav>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
