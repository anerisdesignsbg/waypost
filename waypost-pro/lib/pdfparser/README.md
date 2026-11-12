# PdfParser

Bundled copy of [smalot/pdfparser](https://github.com/smalot/pdfparser) v2.13.0, licensed LGPL-3.0 (see LICENSE.txt).

The namespace is changed from `Smalot\PdfParser` to `Waypost\Vendor\PdfParser` so it cannot clash with another plugin that bundles a different version. Nothing else is changed apart from `phpcs:ignore` comments on a few lines, so WordPress.org's Plugin Check accepts the library (its exception messages are never printed, and its one temporary file is in memory). Loaded by `Waypost_Pro_Documents::pdf_text()`.

To update: download a release, copy `src/Smalot/PdfParser/` here and repeat the namespace replacement in every file.
