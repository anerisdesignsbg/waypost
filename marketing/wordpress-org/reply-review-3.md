Hello,

Thank you for the review and for changing the slug to aneris-docsway. I have uploaded a corrected version.

- Password-protected pages: the search endpoint is meant to be public, as it only searches published docs, so it keeps `__return_true`. Password-protected pages are now left out of search completely (results, snippets, headings and the best answer), so their text is never shown without the password.
- Escaping: every place that printed built HTML now escapes it as it is echoed with `wp_kses()` and an allowed list of the plugin's own tags. The docs layout, which wraps the page content and the theme's header and footer parts, is printed through `wp_kses()` with the post content list. All `phpcs:ignore` comments for output are gone, and inline CSS values are validated when printed.
- Breadcrumb JSON-LD is now encoded with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` and printed in the footer with `wp_print_inline_script_tag()`.

I tested on a clean WordPress install with WP_DEBUG on, and Plugin Check shows no errors.

Kind regards,
Aneris Designs
