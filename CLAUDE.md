# Aneris Docsway

Aneris Docsway (Docsway for short; formerly Waypost, renamed in November 2026 at WordPress.org's request) is a WordPress plugin that turns existing pages into a documentation site: sidebar menu, docs-only smart search with Ctrl/Cmd+K, table of contents, previous/next links, breadcrumbs, search insights, setup wizard and in-admin tutorials. Commercial product by Aneris Designs: the free plugin (`aneris-docsway/`) goes on WordPress.org, and Docsway Pro (`aneris-docsway-pro/`) is a separate add-on sold elsewhere.

Origin: generalised from a client plugin built for FastNetMon.com (`fnm-docs-menu` + `doc-search-pro`). Do not copy FastNetMon branding or code back in.

## Repository layout

- `aneris-docsway/` the plugin itself. This folder is what ships (`npm run zip`).
  - `aneris-docsway.php` bootstrap, constants, activation.
  - `includes/` front end and shared logic: settings, taxonomy, tree (menu data and cache), headings (anchor IDs), render (all HTML output), search (REST, results page, search log), match (word forms, typo suggestions, scoring), frontend (shortcodes, assets, auto-insert), blocks, Divi.
  - `admin/` admin screens (`class-admin.php`), help and tutorials (`class-help.php`), setup wizard (`class-wizard.php`).
  - `assets/` CSS and JS. No build step: plain JS, blocks use `wp.element.createElement` and server-side rendering.
- `aneris-docsway-pro/` the paid add-on (`npm run zip:pro`). Never shipped to WordPress.org.
  - `includes/class-pro.php` hooks Pro features into the free plugin: synonyms, topic filters, the Documents settings section, Assign pages documents view, help articles.
  - `includes/class-documents.php` (`Docsway_Pro_Documents`) reads PDF/Word/text files placed in sections for search and the menu.
  - `lib/pdfparser/` bundled smalot/pdfparser (LGPL-3.0), namespace renamed to `Docsway\Vendor\PdfParser`. Do not edit; see its README to update.
  - `includes/freemius.php` licensing and updates through the bundled Freemius SDK (`freemius/`, do not edit). Product ID 44694 and its public key are in the code; `.wp-env.json` defines both as empty so the local sites run Pro without a licence. Elsewhere Pro features run only with an active licence or trial. Freemius forbids `uninstall.php`; clean-up uses its `after_uninstall` action. See `marketing/freemius/SETUP.md`.
- `demo/` the tutorials docs site (separate wp-env on port 8890, `npm run demo:start`). `demo/aneris-docsway-demo/` is a helper plugin that builds sections and pages from `marketing/tutorials/*.md` and the screenshots (`npm run demo:build`, `npm run demo:zip` for a live site at anerisdesigns.co/docsway-demo). See `demo/README.md`.
- `bin/seed.php` dummy content (docs for Magpie, a fictional photo library; about 150 doc pages plus edge cases, and sample files from `bin/seed-documents/` in a Downloads section, including a scanned PDF with no text). Idempotent.
- `tests/e2e/` Playwright tests (front end, admin, wizard).
- `.wp-env.json` local WordPress in Docker. `.wp-env.override.json` (gitignored) adds Divi from `themes/Divi` (licensed, never commit).

## Commands

- `npm run start` / `npm run stop` start or stop WordPress at http://localhost:8888 (admin / password).
- `npm run seed` rebuild dummy content. `npm run seed:divi` does the same and switches to Divi.
- `npm run reset` wipe the database and reseed.
- `npm test` run all browser tests (site must be running and seeded). `npm run test:headed` to watch.
- `npm run logs` tail `debug.log`. `npm run lint:php` syntax-check every PHP file.
- `npm run wp -- <command>` any WP-CLI command, e.g. `npm run wp -- option get docsway_settings`.
- `npm run zip` build `dist/aneris-docsway-<version>.zip` for installing elsewhere (`bin/zip.sh` takes the version from the plugin header and checks it against the readme's Stable tag; `zip:pro` and `demo:zip` work the same way).
- `npm run plugin-check` run WordPress.org's Plugin Check on the test site (must show no errors before a release). `npm run wporg-assets` rebuild the WordPress.org banners, icons and numbered screenshots in `marketing/wordpress-org/assets/`; `marketing/wordpress-org/SETUP.md` is the submission and release guide.
- `npm run screenshots` retake the listing screenshots in `marketing/screenshots/` (uses the test site at :8889, reseeds before and after). Listing copy is `aneris-docsway/readme.txt`; tutorials are in `marketing/tutorials/`.

After changing PHP, JS or CSS: reload the page (wp-env mounts the folder live). Run `npm test` before committing.

## Conventions

- Free and Pro: the free plugin must never reference Pro classes or contain locked features (WordPress.org guideline 5). Pro plugs in through filters and actions named `docsway_*` (settings defaults, sanitize and changes, settings sections, panels and search rows, search query args and where clause, result file badge, menu files, synonyms, topic filters, Assign pages views, help topics and articles). Add a hook to free rather than special-casing Pro. Pro boots on `plugins_loaded` priority 5 because it loads before the free plugin. Pro's text domain is `aneris-docsway-pro`; free's is `aneris-docsway`.
- Prefixes: PHP classes `Docsway_`, functions/options/meta `docsway_`, front-end CSS `.docsway-*`, admin CSS `.dsw-*` (never `wp-*`, that collides with core), text domain `aneris-docsway`.
- One source of HTML: shortcodes, blocks and Divi modules all call `Docsway_Render`. Change output there only.
- Heading anchors: `Docsway_Headings` is the single slug algorithm used by the TOC, the IDs added to content and search deep links. Keep them in sync by never generating anchors elsewhere.
- Security: escape all output (`esc_html`, `esc_attr`, `esc_url`), nonces plus capability checks on every AJAX/admin-post handler, sanitise through `Docsway_Settings::sanitize`. Search results are rendered client-side with `textContent`, never `innerHTML` with data.
- Escape late (WordPress.org review): never `echo` a built HTML string or add `phpcs:ignore` for output. Print Docsway markup with `wp_kses( $html, Docsway_Render::allowed_html() )`, and the docs layout, page content and theme parts with `Docsway_Render::content_allowed_html()` (filter `docsway_content_allowed_html`). A new tag or attribute must be added to `allowed_html()`; `bin/kses-check.php` and `bin/kses-content-check.php` (run with `wp eval-file`) report anything wp_kses would remove. Inline CSS values are validated in `Docsway_Frontend::inline_css()`. Breadcrumb JSON-LD is printed in the footer by `Docsway_Render::print_schema()` with `JSON_HEX_*` flags. Password-protected pages are left out of search (`has_password => false`).
- Search matches titles and `_docsway_text`, a plain-text copy of each page saved on `save_post` and filled in for older pages by `Docsway_Search::fill_text()` (batches, tracked in `docsway_text_ready`). It is not raw `post_content`, which for Divi 5 is JSON layout data. Read content through `Docsway_Render::content_html()` / `Docsway_Search::plain_text()`, never raw. Pages are also split into heading sections (`_docsway_passages`); `Docsway_Match` handles word forms, synonyms, typo suggestions and scoring, and `Docsway_Search::run()` returns the best answer, related sections and topic counts. PHP (`Docsway_Render::results()`) and JS (`renderResults`) build the same results markup; change both.
- Docs layout (`Docsway_Layout`, Settings, Appearance, Layout): on doc pages, the docs home and the results page, `template_include` (priority 100, after Divi's Theme Builder) swaps in `templates/docs.php` (classic themes: `get_header()` / `get_footer()`) or `templates/docs-block.php` (block themes: renders before `wp_head()` like core's template-canvas, with the header and footer template parts taken from the current page template). Both print `Docsway_Render::layout()`. Builder editors (`et_fb`, Elementor preview and so on) are skipped. While the layout renders, `the_content` leaves breadcrumbs and previous / next to it.
- Cache: the menu tree is a transient (`docsway_tree`); call `Docsway_Tree::flush()` after changing terms, order or links.
- Settings live in one option array (`docsway_settings`); add new keys to `defaults()` and `sanitize()`.
- Copy and docs: UK English, plain wording, no em dashes anywhere (code comments, UI strings, readme, commit messages).
- Minimum support: WordPress 6.0, PHP 7.4. Avoid PHP 8-only syntax in `aneris-docsway/` (no `match`, `str_starts_with`, named arguments, arrow functions are fine).
- Wrap all user-facing strings for translation.

## Testing notes

- The seed creates deliberate edge cases: four-level sections (Installation > Cloud > Marketplace images), an empty section and a drafts-only section (both must stay hidden), a private page, a page in two sections, a 78-heading Glossary, duplicate and HTML-containing headings (`edge-case-tricky-headings`), Vietnamese content, non-doc pages and blog posts sharing keywords (must not appear in docs search).
- Tests run as a logged-out visitor for the front end and as admin for admin screens. Keep `workers: 1`; tests share one site.
- Divi modules can only be tested with Divi installed (see README). `divi-modules.php` is included on `et_builder_ready` and creates each module once at the bottom: Divi only registers a module when it is constructed. Set inherited properties (`vb_support`, `type`, `module_credits`) in `init()`, never as trait properties, or PHP fails with a fatal error.
- Divi 5 runs these as Divi 4 modules in Backwards Compatibility Mode. They render and can be moved, copied and saved to the Library, but are not in the Divi 5 module list, so the help tells Divi 5 users to put shortcodes in a Code module. Divi 5 stores text as JSON inside block comments; `Docsway_Render::toc()` unpacks it before reading headings.

## Known gaps / roadmap

1. No native Divi 5 modules yet (Docsway modules are missing from the Divi 5 module list). Divi 4 itself not yet tested with the current modules.
2. Pro is wired to Freemius but the Freemius product (ID, public key, plans) is not created yet. Planned Pro features: multiple docs sets, private docs by role, full insights with exports, native Divi 5 modules, AI answers.
3. "Was this helpful?" feedback, multiple docs sets, import/export of menu order.
4. FastNetMon's old `fnm_menu_order_v2` order is not migrated.
