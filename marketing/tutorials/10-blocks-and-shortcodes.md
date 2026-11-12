# Blocks and shortcodes

With the Waypost docs layout you rarely need these. They are for placing Waypost pieces yourself: with **My theme's layout**, in a site header, a widget area or any page builder.

Each piece is available three ways: a block, a Divi module and a shortcode. All three show exactly the same thing.

| Block | Divi module | Shortcode | Shows |
|---|---|---|---|
| Docs Menu | Waypost Docs Menu | `[waypost_menu]` | Sidebar menu built from your sections |
| Docs Search | Waypost Docs Search | `[waypost_search]` | Search box with instant results |
| Docs Search Home | Waypost Search Home | `[waypost_search_home]` | Heading, large search box, quick searches, topic cards and full results |
| Docs: On This Page | Waypost On This Page | `[waypost_toc]` | Contents list for the current page |
| Docs: Previous / Next | Waypost Previous / Next | `[waypost_prev_next]` | Links to the previous and next page in menu order |
| Docs: Breadcrumbs | Waypost Breadcrumbs | `[waypost_breadcrumbs]` | Where the page sits in your docs |
| | | `[waypost_search_results]` | Full results list, for a separate results page |

## Shortcode options

| Shortcode | Option | Example |
|---|---|---|
| `[waypost_menu]` | `title`: a heading above the menu | `[waypost_menu title="Documentation"]` |
| `[waypost_search]` | `placeholder`: hint text in the box | `[waypost_search placeholder="Search the manual…"]` |
| | `button="no"`: hide the Search button | `[waypost_search button="no"]` |
| `[waypost_toc]` | `title`: heading above the list | `[waypost_toc title="In this guide"]` |
| | `min`: headings needed before it shows | `[waypost_toc min="2"]` |
| `[waypost_search_home]` | `title`, `intro`, `topics_title`: override the texts from Settings | `[waypost_search_home title="How can we help?"]` |

Blocks and modules have the same options in their settings panels.

## Avoid doubles

Waypost can add the contents list, previous and next links and breadcrumbs to every doc page automatically. If you place them yourself, untick them in **Waypost, Settings, Navigation, Add to every doc page**.

## Keyboard shortcut

On doc pages, and any page with a Docs Search box, **Ctrl+K** (**Cmd+K** on a Mac) or the **/** key opens a search window. Turn it off in **Waypost, Settings, Search, Extras**.
