# PdfParser

Bundled copy of [smalot/pdfparser](https://github.com/smalot/pdfparser) v2.13.0, licensed LGPL-3.0 (see LICENSE.txt).

The namespace is changed from `Smalot\PdfParser` to `Waypost\Vendor\PdfParser` so it cannot clash with another plugin that bundles a different version. Nothing else is changed. Loaded by `Waypost_Documents::pdf_text()`.

To update: download a release, copy `src/Smalot/PdfParser/` here and repeat the namespace replacement in every file.
