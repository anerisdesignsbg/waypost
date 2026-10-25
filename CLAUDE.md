# Waypost

WordPress plugin that turns existing pages into a documentation site: sidebar menu, docs-only search with Ctrl/Cmd+K, table of contents, previous/next links, breadcrumbs, search insights, setup wizard and in-admin tutorials. Commercial product by Aneris Designs (free version planned for WordPress.org, Pro later).

Origin: generalised from a client plugin built for FastNetMon.com (`fnm-docs-menu` + `doc-search-pro`). Do not copy FastNetMon branding or code back in.

## Repository layout

- `waypost/` the plugin itself. This folder is what ships (`npm run zip`).
  - `waypost.php` bootstrap, constants, activation.
  - `includes/` front end and shared logic: settings, taxonomy, tree (menu data and cache), headings (anchor IDs), render (all HTML output), search (REST, results page, search log), documents (PDF/Word/text files in sections: reading their text for search), frontend (shortcodes, assets, auto-insert), blocks, Divi.
  - `admin/` admin screens (`class-admin.php`), help and tutorials (`class-help.php`), setup wizard (`class-wizard.php`).
  - `lib/pdfparser/` bundled smalot/pdfparser (LGPL-3.0), namespace renamed to `Waypost\Vendor\PdfParser`. Do not edit; see its README to update.
  - `assets/` CSS and JS. No build step: plain JS, blocks use `wp.element.createElement` and server-side rendering.
- `bin/seed.php` dummy content (docs for Magpie, a fictional photo library; about 150 doc pages plus edge cases, and sample files from `bin/seed-documents/` in a Downloads section, including a scanned PDF with no text). Idempotent.
- `tests/e2e/` Playwright tests (front end, admin, wizard).
- `.wp-env.json` local WordPress in Docker. `.wp-env.override.json` (gitignored) adds Divi from `themes/Divi` (licensed, never commit).

## Commands

- `npm run start` / `npm run stop` start or stop WordPress at http://localhost:8888 (admin / password).
- `npm run seed` rebuild dummy content. `npm run seed:divi` does the same and switches to Divi.
- `npm run reset` wipe the database and reseed.
- `npm test` run all browser tests (site must be running and seeded). `npm run test:headed` to watch.
- `npm run logs` tail `debug.log`. `npm run lint:php` syntax-check every PHP file.
- `npm run wp -- <command>` any WP-CLI command, e.g. `npm run wp -- option get waypost_settings`.
- `npm run zip` build `dist/waypost.zip` for installing elsewhere.

After changing PHP, JS or CSS: reload the page (wp-env mounts the folder live). Run `npm test` before committing.

## Conventions

- Prefixes: PHP classes `Waypost_`, functions/options/meta `waypost_`, front-end CSS `.waypost-*`, admin CSS `.wpst-*` (never `wp-*`, that collides with core), text domain `waypost`.
- One source of HTML: shortcodes, blocks and Divi modules all call `Waypost_Render`. Change output there only.
- Heading anchors: `Waypost_Headings` is the single slug algorithm used by the TOC, the IDs added to content and search deep links. Keep them in sync by never generating anchors elsewhere.
- Security: escape all output (`esc_html`, `esc_attr`, `esc_url`), nonces plus capability checks on every AJAX/admin-post handler, sanitise through `Waypost_Settings::sanitize`. Search results are rendered client-side with `textContent`, never `innerHTML` with data.
- Search matches titles and `_waypost_text`, a plain-text copy of each page saved on `save_post` and filled in for older pages by `Waypost_Search::fill_text()` (batches, tracked in `waypost_text_ready`). It is not raw `post_content`, which for Divi 5 is JSON layout data. Read content through `Waypost_Render::content_html()` / `Waypost_Search::plain_text()`, never raw. Pages are also split into heading sections (`_waypost_passages`); `Waypost_Match` handles word forms, synonyms, typo suggestions and scoring, and `Waypost_Search::run()` returns the best answer, related sections and topic counts. PHP (`Waypost_Render::results()`) and JS (`renderResults`) build the same results markup; change both.
- Docs layout (`Waypost_Layout`, Settings, Appearance, Layout): on doc pages, the docs home and the results page, `template_include` (priority 100, after Divi's Theme Builder) swaps in `templates/docs.php` (classic themes: `get_header()` / `get_footer()`) or `templates/docs-block.php` (block themes: renders before `wp_head()` like core's template-canvas, with the header and footer template parts taken from the current page template). Both print `Waypost_Render::layout()`. Builder editors (`et_fb`, Elementor preview and so on) are skipped. While the layout renders, `the_content` leaves breadcrumbs and previous / next to it.
- Cache: the menu tree is a transient (`waypost_tree`); call `Waypost_Tree::flush()` after changing terms, order or links.
- Settings live in one option array (`waypost_settings`); add new keys to `defaults()` and `sanitize()`.
- Copy and docs: UK English, plain wording, no em dashes anywhere (code comments, UI strings, readme, commit messages).
- Minimum support: WordPress 6.0, PHP 7.4. Avoid PHP 8-only syntax in `waypost/` (no `match`, `str_starts_with`, named arguments, arrow functions are fine).
- Wrap all user-facing strings for translation.

## Testing notes

- The seed creates deliberate edge cases: four-level sections (Installation > Cloud > Marketplace images), an empty section and a drafts-only section (both must stay hidden), a private page, a page in two sections, a 78-heading Glossary, duplicate and HTML-containing headings (`edge-case-tricky-headings`), Vietnamese content, non-doc pages and blog posts sharing keywords (must not appear in docs search).
- Tests run as a logged-out visitor for the front end and as admin for admin screens. Keep `workers: 1`; tests share one site.
- Divi modules can only be tested with Divi installed (see README). `divi-modules.php` is included on `et_builder_ready` and creates each module once at the bottom: Divi only registers a module when it is constructed. Set inherited properties (`vb_support`, `type`, `module_credits`) in `init()`, never as trait properties, or PHP fails with a fatal error.
- Divi 5 runs these as Divi 4 modules in Backwards Compatibility Mode. They render and can be moved, copied and saved to the Library, but are not in the Divi 5 module list, so the help tells Divi 5 users to put shortcodes in a Code module. Divi 5 stores text as JSON inside block comments; `Waypost_Render::toc()` unpacks it before reading headings.

## Known gaps / roadmap

1. No native Divi 5 modules yet (Waypost modules are missing from the Divi 5 module list). Divi 4 itself not yet tested with the current modules.
2. Free / Pro split and licensing (Freemius planned). Candidate Pro features: search insights, Ctrl+K, multiple docs sets, Divi 5 module.
3. "Was this helpful?" feedback, multiple docs sets, import/export of menu order.
4. FastNetMon's old `fnm_menu_order_v2` order is not migrated.
