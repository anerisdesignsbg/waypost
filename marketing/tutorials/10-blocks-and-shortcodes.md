# Blocks and shortcodes

With the Docsway docs layout you rarely need these. They are for placing Docsway pieces yourself: with **My theme's layout**, in a site header, a widget area or any page builder.

Each piece is available three ways: a block, a Divi module and a shortcode. All three show exactly the same thing.

| Block | Divi module | Shortcode | Shows |
|---|---|---|---|
| Docs Menu | Docsway Docs Menu | `[docsway_menu]` | Sidebar menu built from your sections |
| Docs Search | Docsway Docs Search | `[docsway_search]` | Search box with instant results |
| Docs Search Home | Docsway Search Home | `[docsway_search_home]` | Heading, large search box, quick searches, topic cards and full results |
| Docs: On This Page | Docsway On This Page | `[docsway_toc]` | Contents list for the current page |
| Docs: Previous / Next | Docsway Previous / Next | `[docsway_prev_next]` | Links to the previous and next page in menu order |
| Docs: Breadcrumbs | Docsway Breadcrumbs | `[docsway_breadcrumbs]` | Where the page sits in your docs |
| | | `[docsway_search_results]` | Full results list, for a separate results page |

## Shortcode options

| Shortcode | Option | Example |
|---|---|---|
| `[docsway_menu]` | `title`: a heading above the menu | `[docsway_menu title="Documentation"]` |
| `[docsway_search]` | `placeholder`: hint text in the box | `[docsway_search placeholder="Search the manual…"]` |
| | `button="no"`: hide the Search button | `[docsway_search button="no"]` |
| `[docsway_toc]` | `title`: heading above the list | `[docsway_toc title="In this guide"]` |
| | `min`: headings needed before it shows | `[docsway_toc min="2"]` |
| `[docsway_search_home]` | `title`, `intro`, `topics_title`: override the texts from Settings | `[docsway_search_home title="How can we help?"]` |

Blocks and modules have the same options in their settings panels.

## Avoid doubles

Docsway can add the contents list, previous and next links and breadcrumbs to every doc page automatically. If you place them yourself, untick them in **Docsway, Settings, Navigation, Add to every doc page**.

## Keyboard shortcut

On doc pages, and any page with a Docs Search box, **Ctrl+K** (**Cmd+K** on a Mac) or the **/** key opens a search window. Turn it off in **Docsway, Settings, Search, Extras**.
