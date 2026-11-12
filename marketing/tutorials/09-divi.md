# Using Waypost with Divi

Waypost works with Divi pages, the Divi Builder and the Theme Builder.

## The easy way: the Waypost docs layout

Choose **Waypost docs layout** in **Waypost, Settings, Appearance** (or in the setup wizard). Every doc page then gets the sidebar menu, contents column, breadcrumbs and previous and next links automatically, between your Divi header and footer. Pages you built with the Divi Builder keep their layouts inside the docs layout.

This is the quickest setup and needs no Theme Builder template.

Tip: if your Divi sections have a background colour, Waypost makes sections inside the docs layout transparent so your chosen Waypost style shows through.

## Building your own template with Divi modules (Divi 4)

If you prefer to design the docs page yourself, choose **My theme's layout** and use the Waypost modules. In the Divi Builder they are listed as:

| Module | Shows |
|---|---|
| Waypost Docs Menu | The sidebar menu |
| Waypost Docs Search | A docs search box with instant results |
| Waypost Search Home | Heading, large search box, quick searches and topic cards |
| Waypost On This Page | The contents list for the current page |
| Waypost Previous / Next | Links to the previous and next page in menu order |
| Waypost Breadcrumbs | Where the page sits in your docs |

A typical setup: in **Divi, Theme Builder**, create a template assigned to your doc pages, add a two-column row with the Docs Menu (and Docs Search) on the left and the Post Content module on the right, with Previous / Next below.

In the builder the modules show a placeholder; they display properly on the front end.

When you place these yourself, untick the matching automatic extras in **Waypost, Settings, Navigation, Add to every doc page**, so nothing appears twice.

## Divi 5

On Divi 5, the Waypost modules run in Backwards Compatibility Mode and are not in the Divi 5 module list yet. Add a **Code** module and type the shortcodes instead, for example:

```
[waypost_search]
[waypost_menu]
```

Or simply use the Waypost docs layout, which needs no modules at all. Divi 5 page content is read correctly for search and the contents list.

## Backwards Compatibility Mode on test sites

Some local test tools (for example wp-env) make Divi load its Divi 4 framework on every page, so the admin bar shows "Backwards Compatibility Mode Enabled" everywhere. On normal sites this only happens on pages that use Divi 4 modules.
