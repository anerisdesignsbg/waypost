<?php
/**
 * Help and tutorials page, plus the contextual Help tabs on each screen.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Help {

	public static function init() {}

	private static function url( $topic ) {
		return admin_url( 'admin.php?page=waypost-help#' . $topic );
	}

	/** Contextual help (the "Help" tab at the top right of each Waypost screen). */
	public static function add_tabs( $key ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$tabs = array(
			'dashboard' => array(
				'overview' => array( __( 'Overview', 'waypost' ), '<p>' . esc_html__( 'The checklist shows what is left to do before your docs are ready. Each step links to the screen that does it and to a short tutorial.', 'waypost' ) . '</p><p>' . esc_html__( 'Waypost builds a documentation area from pages you already have. You put pages into sections, and Waypost turns that into a sidebar menu, a docs-only search, a table of contents, previous and next links and breadcrumbs.', 'waypost' ) . '</p>' ),
			),
			'structure' => array(
				'order'  => array( __( 'Menu order', 'waypost' ), '<p>' . esc_html__( 'Drag rows by their handle to change the order in the menu, then press Save order. Items only move within their own group.', 'waypost' ) . '</p><p>' . esc_html__( 'To nest one section inside another, edit the section and set its Parent.', 'waypost' ) . '</p>' ),
				'assign' => array( __( 'Assign pages', 'waypost' ), '<p>' . esc_html__( 'Tick pages, choose a section and press Add to section. Move replaces existing sections. Remove from docs takes the page out of the menu and docs search without deleting it.', 'waypost' ) . '</p><p>' . esc_html__( 'You can also set sections while editing a page, in the Doc sections panel of the editor sidebar.', 'waypost' ) . '</p>' ),
				'links'  => array( __( 'Section links', 'waypost' ), '<p>' . esc_html__( 'Link a section heading to an overview page so the heading itself becomes clickable. Without a link the heading just opens and closes its list.', 'waypost' ) . '</p>' ),
			),
			'settings'  => array(
				'content'  => array( __( 'Content', 'waypost' ), '<p>' . esc_html__( 'Choose which content types hold docs and whether to group them with Waypost sections or a taxonomy you already use.', 'waypost' ) . '</p>' ),
				'features' => array( __( 'Page features', 'waypost' ), '<p>' . esc_html__( 'Automatic features are added to every page that belongs to a section. Turn them off if you place the blocks or Divi modules yourself, so they do not appear twice.', 'waypost' ) . '</p>' ),
			),
			'insights'  => array(
				'insights' => array( __( 'Using insights', 'waypost' ), '<p>' . esc_html__( 'Searches with no results are the most useful list here. Each one is a gap in your docs or a word your readers use that your pages do not.', 'waypost' ) . '</p>' ),
			),
			'help'      => array(),
		);
		foreach ( isset( $tabs[ $key ] ) ? $tabs[ $key ] : array() as $id => $tab ) {
			$screen->add_help_tab( array( 'id' => 'waypost-' . $id, 'title' => $tab[0], 'content' => $tab[1] ) );
		}
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'More help', 'waypost' ) . '</strong></p>'
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=waypost-help' ) ) . '">' . esc_html__( 'Tutorials', 'waypost' ) . '</a></p>'
			. '<p><a href="' . esc_url( Waypost_Wizard::url() ) . '">' . esc_html__( 'Setup wizard', 'waypost' ) . '</a></p>'
		);
	}

	public static function page() {
		$topics = array(
			'getting-started'  => __( 'Getting started', 'waypost' ),
			'sections'         => __( 'Sections', 'waypost' ),
			'assigning'        => __( 'Adding pages to the docs', 'waypost' ),
			'ordering'         => __( 'Ordering the menu', 'waypost' ),
			'section-links'    => __( 'Clickable section headings', 'waypost' ),
			'placing'          => __( 'Placing the menu and search', 'waypost' ),
			'search'           => __( 'Search', 'waypost' ),
			'page-features'    => __( 'Contents, previous / next, breadcrumbs', 'waypost' ),
			'insights'         => __( 'Search insights', 'waypost' ),
			'styling'          => __( 'Styling', 'waypost' ),
			'existing'         => __( 'Using a taxonomy you already have', 'waypost' ),
			'troubleshooting'  => __( 'Troubleshooting', 'waypost' ),
		);

		Waypost_Admin::header( __( 'Help and tutorials', 'waypost' ), __( 'Short guides for everything Waypost does.', 'waypost' ) );
		?>
		<div class="wpst-help">
			<nav class="wpst-help__nav" aria-label="<?php esc_attr_e( 'Help topics', 'waypost' ); ?>">
				<ul>
					<?php foreach ( $topics as $id => $label ) : ?>
						<li><a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<a class="button button-primary" href="<?php echo esc_url( Waypost_Wizard::url() ); ?>"><?php esc_html_e( 'Open setup wizard', 'waypost' ); ?></a>
			</nav>

			<div class="wpst-help__body">

				<article class="wpst-card wpst-topic" id="getting-started">
					<h2><?php esc_html_e( 'Getting started', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'Waypost turns ordinary pages into a documentation area. Nothing is moved or copied, and page addresses stay exactly as they are.', 'waypost' ); ?></p>
					<p><strong><?php esc_html_e( 'The idea in one sentence:', 'waypost' ); ?></strong> <?php esc_html_e( 'you put pages into sections, and Waypost builds the menu, search and page navigation from those sections.', 'waypost' ); ?></p>
					<h3><?php esc_html_e( 'Five steps to a working docs area', 'waypost' ); ?></h3>
					<ol class="wpst-tut">
						<li><strong><?php esc_html_e( 'Run the setup wizard.', 'waypost' ); ?></strong> <?php esc_html_e( 'It asks where your docs live and creates your first sections.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Put pages into sections.', 'waypost' ); ?></strong> <?php esc_html_e( 'Use Structure, then Assign pages, or the Doc sections panel when editing a page.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Set the order.', 'waypost' ); ?></strong> <?php esc_html_e( 'Drag sections and pages into place on Structure, then Menu order.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Place the menu.', 'waypost' ); ?></strong> <?php esc_html_e( 'Add the Docs Menu block, Divi module or shortcode to the sidebar of your doc pages.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Check a doc page.', 'waypost' ); ?></strong> <?php esc_html_e( 'You should see the menu with the current page highlighted, contents at the top and previous and next links at the bottom.', 'waypost' ); ?></li>
					</ol>
				</article>

				<article class="wpst-card wpst-topic" id="sections">
					<h2><?php esc_html_e( 'Sections', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'Sections are the headings in your docs menu. They work like categories: a section can contain pages and other sections, and a page can belong to more than one section.', 'waypost' ); ?></p>
					<div class="wpst-example">
						<pre>Getting started
  Installation
  First steps
Configuration
  Email settings
Troubleshooting</pre>
						<p class="wpst-muted"><?php esc_html_e( 'Each line is a section. Indented lines are sections inside the one above.', 'waypost' ); ?></p>
					</div>
					<h3><?php esc_html_e( 'Create sections', 'waypost' ); ?></h3>
					<ul class="wpst-list">
						<li><?php esc_html_e( 'Quickest: the setup wizard lets you type an outline like the one above and creates every section in one go.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'One at a time: Structure, then Assign pages, has a New section box.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Full control: Waypost, then Sections, is the standard WordPress category screen where you can rename, set parents and delete.', 'waypost' ); ?></li>
					</ul>
					<p class="wpst-note"><?php esc_html_e( 'An empty section is hidden on the site, so you can prepare sections before writing their pages.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="assigning">
					<h2><?php esc_html_e( 'Adding pages to the docs', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'A page becomes part of the docs as soon as it belongs to a section. Only those pages appear in the menu and in docs search.', 'waypost' ); ?></p>
					<h3><?php esc_html_e( 'Several pages at once', 'waypost' ); ?></h3>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Go to Waypost, Structure, Assign pages.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'The list starts with pages that are not in the docs yet. Use the filter box to find pages by title.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Tick the pages, choose a section at the bottom and press Add to section.', 'waypost' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Move to section replaces a page’s current sections. Remove from docs clears its sections without touching the page itself.', 'waypost' ); ?></p>
					<h3><?php esc_html_e( 'While editing a page', 'waypost' ); ?></h3>
					<p><?php esc_html_e( 'Open the page in the editor. In the right-hand sidebar, find the Doc sections panel and tick a section. With Divi, the panel is in the WordPress editor screen, not inside the Visual Builder.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="ordering">
					<h2><?php esc_html_e( 'Ordering the menu', 'waypost' ); ?></h2>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Go to Waypost, Structure, Menu order.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Drag a row by the handle on its left. Sections and pages inside the same section can be mixed in any order.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Press Save order. The menu on the site updates straight away.', 'waypost' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'New pages and sections appear at the end of their group, in A to Z order, until you move them. Reset to A–Z clears all custom ordering.', 'waypost' ); ?></p>
					<p class="wpst-note"><?php esc_html_e( 'Dragging only changes the order within a level. To move a section under a different parent, edit the section on the Sections screen.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="section-links">
					<h2><?php esc_html_e( 'Clickable section headings', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'A section heading normally just opens and closes its list. If the section has an overview page, you can link the heading to it.', 'waypost' ); ?></p>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Make sure the overview page is in that section.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Go to Structure, Section links.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Choose the page next to the section and press Save.', 'waypost' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'The linked page now appears as the heading rather than as an item inside the list, so it is not shown twice.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="placing">
					<h2><?php esc_html_e( 'Placing the menu and search', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'The menu usually goes in a sidebar that appears on every doc page. Pick the method that matches how your site is built.', 'waypost' ); ?></p>

					<h3><?php esc_html_e( 'Divi Theme Builder', 'waypost' ); ?></h3>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Go to Divi, Theme Builder and add a new template.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Under “Use on”, choose the doc pages. If your docs share a parent page, “Children of” that page is the simplest choice.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Build a custom body with two columns: a narrow one for the sidebar and a wide one with the Post Content module.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'In the sidebar column, add the Waypost Docs Search and Waypost Docs Menu modules.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Optionally add Waypost On This Page in a third column, and turn off the automatic contents in Settings so it is not shown twice.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Save the template. The modules show a placeholder in the builder and the real menu on the site.', 'waypost' ); ?></li>
					</ol>
					<p class="wpst-note"><?php echo wp_kses( __( '<strong>Divi 5:</strong> the Waypost modules are not in the Divi 5 module list yet. Add a Code module instead and type the shortcodes, for example <code>[waypost_search]</code> and <code>[waypost_menu]</code>. Layouts that already use the Waypost modules keep working.', 'waypost' ), array( 'strong' => array(), 'code' => array() ) ); ?></p>

					<h3><?php esc_html_e( 'Block editor and block themes', 'waypost' ); ?></h3>
					<p><?php esc_html_e( 'In the block inserter, search for “Docs”. You will find Docs Menu, Docs Search, Docs: On This Page, Docs: Previous / Next and Docs: Breadcrumbs. In a block theme, add them to the Page template in the Site Editor. They also work in sidebar widget areas.', 'waypost' ); ?></p>

					<h3><?php esc_html_e( 'Shortcodes (any theme or builder)', 'waypost' ); ?></h3>
					<table class="wpst-codes">
						<tr><td><?php echo Waypost_Admin::copy_field( '[waypost_menu]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Sidebar menu. Optional: title="Docs".', 'waypost' ); ?></td></tr>
						<tr><td><?php echo Waypost_Admin::copy_field( '[waypost_search]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Search box with instant results. Optional: placeholder="…" button="no".', 'waypost' ); ?></td></tr>
						<tr><td><?php echo Waypost_Admin::copy_field( '[waypost_toc]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'On this page contents. Optional: title="…" min="2".', 'waypost' ); ?></td></tr>
						<tr><td><?php echo Waypost_Admin::copy_field( '[waypost_prev_next]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Previous and next links.', 'waypost' ); ?></td></tr>
						<tr><td><?php echo Waypost_Admin::copy_field( '[waypost_breadcrumbs]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Breadcrumbs with search engine markup.', 'waypost' ); ?></td></tr>
						<tr><td><?php echo Waypost_Admin::copy_field( '[waypost_search_results]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Full results list. Put it on your search results page only.', 'waypost' ); ?></td></tr>
					</table>
					<p><?php esc_html_e( 'In a PHP template:', 'waypost' ); ?> <code>&lt;?php echo do_shortcode( '[waypost_menu]' ); ?&gt;</code></p>
				</article>

				<article class="wpst-card wpst-topic" id="search">
					<h2><?php esc_html_e( 'Search', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'Waypost search only looks at doc pages, so readers are not shown blog posts or shop pages.', 'waypost' ); ?></p>
					<ul class="wpst-list">
						<li><strong><?php esc_html_e( 'Instant results', 'waypost' ); ?></strong>: <?php esc_html_e( 'appear under the box as people type, with matching words highlighted.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Jump to a heading', 'waypost' ); ?></strong>: <?php esc_html_e( 'when a heading on a page matches, it is listed under the result and links straight to that part of the page.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Keyboard', 'waypost' ); ?></strong>: <?php esc_html_e( 'arrow keys move through results, Enter opens one, Esc closes the list.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Search anywhere', 'waypost' ); ?></strong>: <?php esc_html_e( 'on doc pages, Ctrl+K (Cmd+K on Mac) or the / key opens a search window.', 'waypost' ); ?></li>
					</ul>
					<h3><?php esc_html_e( 'Where full results show', 'waypost' ); ?></h3>
					<p><?php esc_html_e( 'When someone presses Enter, they see a full list of results. Choose where in Settings, Search, Full results show:', 'waypost' ); ?></p>
					<ul class="wpst-list">
						<li><strong><?php esc_html_e( 'On the docs home page', 'waypost' ); ?></strong>: <?php esc_html_e( 'results appear inside the search home, below the search box, without leaving the page. Your layout and sidebar menu stay in place, quick searches stay on top and the topic cards step aside until the reader goes back. Search boxes anywhere else on the site send readers here too. Set the Docs home page under Navigation.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'On a separate results page', 'waypost' ); ?></strong>: <?php esc_html_e( 'a page of its own that lists results. The setup wizard can create it.', 'waypost' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Either way the address includes the search words, so readers can share a link to their results and the Back button works.', 'waypost' ); ?></p>
					<h3><?php esc_html_e( 'A separate results page', 'waypost' ); ?></h3>
					<p><?php esc_html_e( 'The setup wizard creates this page for you. To make one yourself:', 'waypost' ); ?></p>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Create a page called Search results.', 'waypost' ); ?></li>
						<li><?php echo wp_kses( sprintf( /* translators: %s: shortcode */ __( 'Add the shortcode %s to it and publish.', 'waypost' ), '<code>[waypost_search_results]</code>' ), array( 'code' => array() ) ); ?></li>
						<li><?php esc_html_e( 'Choose it as the Results page in Waypost, Settings, Search.', 'waypost' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Without a results page, search still works: Enter simply keeps the instant results open.', 'waypost' ); ?></p>
					<p><?php esc_html_e( 'On the results page, other docs search boxes (for example one in a sidebar) are hidden, because the page has its own. Untick this in Settings, Search if you prefer to keep them.', 'waypost' ); ?></p>

					<h3><?php esc_html_e( 'A search home for your docs', 'waypost' ); ?></h3>
					<p><?php esc_html_e( 'The search home is a start page that helps people find answers quickly: a heading, a large search box, up to six quick search buttons and up to six topic cards.', 'waypost' ); ?></p>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'In Settings, Search home, write a heading and add a few quick searches. Use the questions people really ask; Search insights shows you what they type.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Add topic cards for your most useful pages, such as an install guide, a quick start or your API reference. Pick an icon, a title, one short line and the page.', 'waypost' ); ?></li>
						<li><?php echo wp_kses( sprintf( /* translators: %s: shortcode */ __( 'Put it on your docs home page with the Docs Search Home block, the Waypost Search Home Divi module or %s.', 'waypost' ), '<code>[waypost_search_home]</code>' ), array( 'code' => array() ) ); ?></li>
						<li><?php esc_html_e( 'A Docs Menu block below it gives readers the full list of pages too.', 'waypost' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Quick searches also appear in the Ctrl+K search window before anything is typed.', 'waypost' ); ?></p>

					<h3><?php esc_html_e( 'How search finds pages', 'waypost' ); ?></h3>
					<p><?php esc_html_e( 'Waypost uses WordPress’s own search, limited to your doc pages, so there is no index to build and no extra plugin to install. New and edited pages are searchable as soon as they are published. It matches the words people type in page titles and text; it does not correct spelling or match different word forms, so “installing” will not find a page that only says “install”. Search insights shows which searches find nothing, so you can add those words to the right pages.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="page-features">
					<h2><?php esc_html_e( 'Contents, previous / next, breadcrumbs', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'These are added to every doc page automatically. Switch each one on or off in Settings, Navigation.', 'waypost' ); ?></p>
					<ul class="wpst-list">
						<li><strong><?php esc_html_e( 'On this page', 'waypost' ); ?></strong>: <?php esc_html_e( 'lists the headings on the page and highlights the one you are reading. It appears when a page has at least three headings; you can change that number and which heading levels count.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Previous and next', 'waypost' ); ?></strong>: <?php esc_html_e( 'follow the menu order, so readers can go through the docs like a book.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Breadcrumbs', 'waypost' ); ?></strong>: <?php esc_html_e( 'show the sections above the current page. Set a Docs home page in Settings to start the trail there. Search engines can read them too.', 'waypost' ); ?></li>
						<li><strong><?php esc_html_e( 'Copy buttons', 'waypost' ); ?></strong>: <?php esc_html_e( 'appear on code blocks when you hover over them.', 'waypost' ); ?></li>
					</ul>
					<p class="wpst-note"><?php esc_html_e( 'If you place any of these yourself with a block or Divi module, Waypost notices and does not add a second copy to the page content. In a Theme Builder sidebar it cannot see your module, so switch the automatic version off.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="insights">
					<h2><?php esc_html_e( 'Search insights', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'Waypost keeps a simple tally of what people search for in your docs and whether they found anything.', 'waypost' ); ?></p>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Open Waypost, Search insights once a week.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Start with Searches with no results. Click a search to try it yourself.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'If the answer exists, add the reader’s word to that page, for example in a heading. If it does not, that is your next page to write.', 'waypost' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Only the search words, a count and the number of results are stored. No personal data is kept.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="styling">
					<h2><?php esc_html_e( 'Styling', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'Pick a style in Settings, Appearance. Follow my theme uses your theme’s fonts and text colour. Modern, Minimalist and Classic each bring their own fonts and colours. Light and Dark put the menu and other parts on their own panels, so they stay readable on any background. Each style has a matching accent colour, which you can change.', 'waypost' ); ?></p>
					<p><?php esc_html_e( 'To fine-tune a style, override these CSS variables on body in your theme or in Divi, Theme Options, Custom CSS:', 'waypost' ); ?></p>
					<pre class="wpst-pre">body {
  --waypost-accent: #2563eb;     /* current page, links, buttons */
  --waypost-text: #1d2333;       /* main text (inherit = theme) */
  --waypost-font: Georgia, serif; /* font for all components */
  --waypost-muted: #64708a;      /* secondary text */
  --waypost-border: #e2e6ee;     /* lines and outlines */
  --waypost-surface: #ffffff;    /* search box and dropdown background */
  --waypost-soft: #f4f6fa;       /* hover and panel backgrounds */
  --waypost-radius: 8px;         /* corner rounding */
  --waypost-indent: 14px;        /* indent per menu level */
  --waypost-font-size: 15px;     /* base size for all components */
  --waypost-speed: 200ms;        /* open and close animation */
}</pre>
					<p><?php esc_html_e( 'Every component has its own class to target: .waypost-menu, .waypost-search, .waypost-toc, .waypost-pager, .waypost-crumbs and .waypost-results.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="existing">
					<h2><?php esc_html_e( 'Using a taxonomy you already have', 'waypost' ); ?></h2>
					<p><?php esc_html_e( 'If your docs are already grouped with a custom taxonomy, for example one created with CPT UI, Waypost can use it directly instead of its own sections.', 'waypost' ); ?></p>
					<ol class="wpst-tut">
						<li><?php esc_html_e( 'Go to Settings, Content.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Choose “A category system I already have” and pick it from the list.', 'waypost' ); ?></li>
						<li><?php esc_html_e( 'Save. The menu is rebuilt from that taxonomy straight away.', 'waypost' ); ?></li>
					</ol>
					<p class="wpst-note"><?php esc_html_e( 'Keep the plugin that registers the taxonomy active. If it is deactivated, Waypost shows a warning on the Structure screen.', 'waypost' ); ?></p>
				</article>

				<article class="wpst-card wpst-topic" id="troubleshooting">
					<h2><?php esc_html_e( 'Troubleshooting', 'waypost' ); ?></h2>
					<dl class="wpst-faq">
						<dt><?php esc_html_e( 'The menu says there are no sections.', 'waypost' ); ?></dt>
						<dd><?php esc_html_e( 'Sections only show when they contain at least one published page. Check Structure, Assign pages, “Already in docs”.', 'waypost' ); ?></dd>
						<dt><?php esc_html_e( 'A page is missing from the menu.', 'waypost' ); ?></dt>
						<dd><?php esc_html_e( 'It must be published and in a section. Drafts and private pages are left out. If it was just changed, press Rebuild menu cache on Menu order.', 'waypost' ); ?></dd>
						<dt><?php esc_html_e( 'Contents or previous / next appear twice.', 'waypost' ); ?></dt>
						<dd><?php esc_html_e( 'You have placed them in a template and the automatic version is still on. Switch the automatic one off in Settings, Navigation.', 'waypost' ); ?></dd>
						<dt><?php esc_html_e( 'A search result does not jump to the right heading.', 'waypost' ); ?></dt>
						<dd><?php esc_html_e( 'Two headings with the same text get numbered anchors. Give headings distinct wording, or add your own id to the heading.', 'waypost' ); ?></dd>
						<dt><?php esc_html_e( 'Search finds nothing.', 'waypost' ); ?></dt>
						<dd><?php esc_html_e( 'Search only covers published pages that are in a section and match the content types in Settings. Words need at least two letters.', 'waypost' ); ?></dd>
						<dt><?php esc_html_e( 'The Divi module shows a grey box.', 'waypost' ); ?></dt>
						<dd><?php esc_html_e( 'That is expected inside the Visual Builder. View the page on the site to see the real menu.', 'waypost' ); ?></dd>
					</dl>
				</article>

			</div>
		</div>
		<?php
		Waypost_Admin::footer();
	}
}
