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
		$args = wp_parse_args( $args, array( 'placeholder' => '', 'button' => 'yes' ) );
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
		if ( false !== strpos( $html, '<!-- wp:divi/' ) ) {
			$html = self::divi5_html( $html );
		}
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

	/**
	 * Divi 5 saves module text inside block attributes as JSON, so headings in
	 * raw post content look like <h2>. Collect the HTML strings from
	 * every block, in page order, so headings can be read from them.
	 */
	private static function divi5_html( $content ) {
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
