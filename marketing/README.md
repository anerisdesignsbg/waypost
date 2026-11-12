# Waypost marketing assets

Everything needed to list and sell Waypost. Not part of the plugin zip.

| What | Where | Notes |
|---|---|---|
| Listing copy and FAQs | `../waypost/readme.txt` | WordPress.org format. The Description, FAQ and Screenshots sections can be reused on any marketplace or the product website. |
| Screenshots | `screenshots/` | 14 PNGs at 2x (2880 × 1800; the phone shot is 1170 × 2532). Captions in `screenshots/captions.txt`, in the same order as the readme. |
| Tutorials | `tutorials/` | 11 step-by-step guides for the product website or docs. |

## Refresh the screenshots

The site must be running (`npm run start`). Then:

```bash
npm run screenshots
```

It reseeds the test site (http://localhost:8889), applies the right settings for each shot, saves the images here and restores the seeded settings afterwards. To retake only some: `npm run screenshots -- 03 07`. Shots are defined in `bin/screenshots.js`.

## WordPress.org

WordPress.org reads screenshots from the plugin's SVN `assets/` folder, named `screenshot-1.png`, `screenshot-2.png` and so on, matching the numbered list under `== Screenshots ==` in `readme.txt`. Copy `01-docs-page.png` to `screenshot-1.png`, `02-search-home.png` to `screenshot-2.png`, and so on.

## Still to make

- Banner: 772 × 250 and 1544 × 500 (`banner-772x250.png`, `banner-1544x500.png`).
- Icon: 128 × 128 and 256 × 256 (`icon-128x128.png`, `icon-256x256.png`).
- A short demo video or GIF of smart search (optional, but listings with video convert better).
- `Contributors: anerisdesigns` in `readme.txt` must match the WordPress.org account that submits the plugin.
