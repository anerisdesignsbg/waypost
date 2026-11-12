# Waypost marketing assets

Everything needed to list and sell Waypost. Not part of the plugin zip.

| What | Where | Notes |
|---|---|---|
| Listing copy and FAQs | `../waypost/readme.txt` | WordPress.org format. The Description, FAQ and Screenshots sections can be reused on any marketplace or the product website. |
| Screenshots | `screenshots/` | 14 PNGs at 2x (2880 × 1800; the phone shot is 1170 × 2532). Captions in `screenshots/captions.txt`, in the same order as the readme. |
| Tutorials | `tutorials/` | 11 step-by-step guides for the product website or docs. |
| Docs demo site | `../demo/` | The tutorials as a live Waypost docs site; `demo/README.md` explains putting it live at anerisdesigns.co/waypost-plugin. |
| Brand | `brand/` | `icon.svg` and `banner.html`, the sources for the banner and icon. |
| WordPress.org | `wordpress-org/` | `assets/` is ready to copy into SVN; `SETUP.md` covers the account, submission and releases. |
| Freemius | `freemius/SETUP.md` | Selling Waypost Pro: product, plans, keys, sandbox testing, deployment and updates. |

## Refresh the screenshots

The site must be running (`npm run start`). Then:

```bash
npm run screenshots
```

It reseeds the test site (http://localhost:8889), applies the right settings for each shot, saves the images here and restores the seeded settings afterwards. To retake only some: `npm run screenshots -- 03 07`. Shots are defined in `bin/screenshots.js`.

## Rebuild the WordPress.org images

```bash
npm run wporg-assets
```

It renders the banners (772 × 250 and 1544 × 500) and icons (128, 256 and SVG) from `brand/`, and copies the screenshots as `screenshot-1.png` onwards into `wordpress-org/assets/`.

## Still to make

- A short demo video or GIF of smart search (optional, but listings with video convert better).
- `Contributors: anerisdesigns` in `readme.txt` must match the WordPress.org account that submits the plugin.
