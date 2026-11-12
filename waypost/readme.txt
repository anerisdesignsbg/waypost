=== Waypost: Documentation Site and Smart Search for Your Existing Pages ===
Contributors: anerisdesigns
Tags: documentation, knowledge base, docs, search, table of contents
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn the pages you already have into a documentation site with a sidebar menu and smart search that answers questions. No migration, no URL changes.

== Description ==

Most documentation plugins ask you to move your content into their own post type. Waypost works the other way round: put your existing pages into sections, and Waypost builds a complete docs site around them. Your pages, addresses and page builder layouts stay exactly as they are.

Set up takes about five minutes with the setup wizard.

= A complete docs site in one click =

* **Docs layout:** doc pages get a sidebar menu, an "On this page" contents column, breadcrumbs and previous and next links, inside your theme's header and footer or with a clean docs bar of its own.
* **Sidebar menu** built from your sections, with sections inside sections, drag-and-drop ordering, the current page highlighted and a mobile menu.
* **Search home** for your docs start page: a large search box, up to six quick search buttons and topic cards with icons.
* **Seven ready-made styles:** Follow my theme, Modern, Minimalist, Classic, Light, Dark, and Auto light and dark with a switch for readers. Pick any accent colour.
* **Page extras:** last updated date, reading time, copy links on headings, copy buttons on code blocks and "Was this page helpful?" buttons.

= Smart search that answers questions =

Waypost search reads your docs section by section, so it can point readers to the exact part of a page that answers them.

* **Best answer:** the section that answers the search is shown on top with a short summary and a link straight to it, followed by other pages that cover the same thing.
* **Understands questions:** "How do I share an album?" searches for share and album.
* **Other forms of a word:** "installing" finds "install" and "installation".
* **Fixes typing mistakes:** "dokcer" finds "docker", and says so.
* **Synonyms:** add your readers' own words, for example "pic" for "photo".
* **Topic filters:** narrow results to one section, with a count for each.
* **Instant results** as readers type, from anywhere with Ctrl+K or Cmd+K.
* **Highlights on arrival:** the page a reader opens highlights their search words and the matching section.
* Only your docs are searched, never blog posts or shop pages.
* Runs entirely on your site: no index to build, no outside service, nothing to set up.

= Search inside documents =

Put PDF, Word, PowerPoint, Excel, OpenDocument and text files from the Media Library into your doc sections. Docs search finds words inside them, and the menu lists them with a file type label.

= Learn what readers need =

* **Search insights:** the most popular searches and, more usefully, the searches that found nothing.
* **Page feedback:** answers to "Was this page helpful?", least helpful pages first.

= Works with your site =

* Classic themes, block themes and Divi.
* Blocks for the block editor, Divi modules and shortcodes for everything else.
* Use pages, posts or any custom post type, and Waypost's own sections or a taxonomy you already have.
* Page builder content, including Divi 5, is read as text for search and the table of contents.
* Built-in tutorials on every screen.

== Installation ==

1. In your dashboard, go to Plugins, Add New, upload `waypost.zip` and press Install Now. Or upload the `waypost` folder to `/wp-content/plugins/`.
2. Activate the plugin. The setup wizard opens.
3. Follow the steps: choose where your docs live, create sections, add pages to them, and pick a layout and style.
4. Open your docs home page. That's it.

Everything can be changed later under Waypost, Settings. Waypost, Help has a tutorial for every feature.

== Frequently Asked Questions ==

= Do I have to move or copy my pages? =

No. Waypost never moves, copies or changes your content. You put pages into sections and Waypost builds the menu, search and navigation around them. Page addresses stay the same.

= Will it work with my theme? =

Yes. With the Waypost docs layout, doc pages get a complete layout between your theme's header and footer, on classic and block themes. If you prefer, switch on the Waypost docs bar and doc pages get a header and footer of their own. Or keep your theme's page template and place the menu, search and contents yourself with blocks, Divi modules or shortcodes.

= Does it work with Divi? =

Yes. The docs layout works with Divi, including pages built with the Divi Builder, and Waypost adds Divi modules for the menu, search, search home, contents, previous and next links and breadcrumbs. On Divi 5 the modules run in Backwards Compatibility Mode and are not in the Divi 5 module list yet, so place the Waypost shortcodes in a Code module instead, for example [waypost_menu].

= Does it work with Elementor or other page builders? =

Pages built with any builder can be docs pages. Use the Waypost shortcodes in your builder's shortcode element to place the menu, search or search home.

= Can I use posts or a custom post type instead of pages? =

Yes. Choose any public content type in Settings, Content, or in the setup wizard. You can also mix them.

= I already group my content with categories or a custom taxonomy. Can I use that? =

Yes. In Settings, Content, choose "A category system I already have" and pick it. Waypost uses its terms as sections.

= How does search work? Do I need a search plugin or an index? =

No extra plugin or index. Waypost keeps a plain-text copy of each doc page, split at its headings, and updates it whenever a page is saved. Searches run on your own site in a few milliseconds and never contact an outside service.

= Can it search inside PDF and Word files? =

Yes. Upload the file to the Media Library and tick its doc sections. Waypost reads the text once and adds it to search. It reads PDF, DOCX, PPTX, XLSX, ODT, ODP, ODS, TXT and CSV. Scanned PDFs contain pictures of text rather than text, so add a description to the file and search will find that instead. Older formats such as .doc need saving as PDF or .docx first.

= Why does a search find nothing? =

Waypost already ignores question words, matches other forms of a word and fixes small typing mistakes. If readers use a word your docs do not, add it as a synonym in Settings, Search. Search insights lists every search that found nothing, so you can see what to add or write.

= Is any personal data stored? =

Search insights store only the words searched, how often and how many results they found. No IP addresses or user details. "Was this page helpful?" stores a yes or no count per page; to stop repeat votes it keeps a one-way hash of the voter's IP address for one day, after which it is deleted. Nothing is sent to Aneris Designs or anyone else.

= Will it slow my site down? =

No. The menu is built once and cached, and Waypost's styles and scripts load only where they are used.

= Can readers search from anywhere on my site? =

Yes. Add the Docs Search block, Divi module or [waypost_search] shortcode anywhere, for example in your header or a sidebar. Results open on your docs home or results page.

= Does it work in other languages? =

Waypost is translation ready, and search works with any language, including accented letters and non-Latin scripts. Matching other forms of a word works for English.

= What happens if I uninstall it? =

Your pages are never deleted. Waypost's sections, menu order and settings are removed only if you tick "Also delete Waypost sections, menu order and settings" in Settings, Advanced before deleting the plugin.

== Screenshots ==

1. Your pages become a documentation site: sidebar menu, table of contents, breadcrumbs, reading time and previous and next links.
2. A search home for your docs with quick searches and topic cards.
3. Smart search answers questions: the best section on top with a short summary, other pages that cover it, and topic filters.
4. Instant results as you type, from anywhere with Ctrl+K or Cmd+K.
5. Readers land on the exact section, with their search words highlighted.
6. Search inside PDF, Word, PowerPoint and Excel files. Documents sit in your sections with a file type label.
7. Seven ready-made styles, including Dark and Auto light and dark with a reader switch.
8. Classic, Minimalist, Light or your own theme's look, with any accent colour.
9. Works on phones: the menu folds away and search stays one tap away.
10. A five-minute setup wizard picks your pages, sections, layout and style.
11. Drag and drop to order the menu. Sections can nest four levels deep.
12. Pick a ready-made style and accent colour in Settings.
13. Search insights show what readers look for and which searches found nothing.
14. Add a PDF or Word file to the docs by ticking its sections in the Media Library.

== Changelog ==

= 1.0.0 =
* First release.

== Credits ==

PDF reading uses PdfParser by Sébastien Malot, licensed under the LGPL 3.0, bundled in lib/pdfparser.
