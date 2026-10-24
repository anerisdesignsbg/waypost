# Waypost

Documentation menu, search and page navigation for the pages you already have. This repository holds the plugin (`waypost/`) and a local test environment with dummy content and browser tests.

## First-time setup (Mac)

1. Install **Docker Desktop** from docker.com and open it once so it is running.
2. Install **Node.js** (the LTS version) from nodejs.org.
3. In Terminal, from this folder:

   ```bash
   npm install
   npx playwright install chromium
   npm run start
   npm run seed
   ```

   The first `npm run start` downloads WordPress and takes a few minutes.

4. Open http://localhost:8888/documentation/ for the docs, or http://localhost:8888/wp-admin (user `admin`, password `password`).

## Everyday use

| Command | What it does |
|---|---|
| `npm run start` | Start the test site |
| `npm run stop` | Stop it (your content is kept) |
| `npm run seed` | Rebuild the dummy docs content |
| `npm run reset` | Wipe everything and reseed |
| `npm test` | Run all browser tests |
| `npm run test:headed` | Run tests in a visible browser |
| `npm run logs` | Show the latest PHP errors |
| `npm run zip` | Build `dist/waypost.zip` to install on another site |

Edits to files in `waypost/` show up straight away; just reload the page.

## Testing with Divi

Divi is licensed, so it is never committed.

1. Download Divi from your Elegant Themes account and unzip it into `themes/Divi`.
2. Copy `.wp-env.override.example.json` to `.wp-env.override.json`.
3. Run `npm run stop`, `npm run start`, then `npm run seed:divi`.
4. In wp-admin, go to Divi, Theme Builder and create a template for the doc pages with a sidebar holding the Waypost Docs Search and Waypost Docs Menu modules (step-by-step in Waypost, Help, Placing the menu).

## What the dummy content covers

About 150 doc pages for a fictional self-hosted photo library, Magpie, in sections up to four levels deep, plus edge cases: an empty section, a drafts-only section, a private page, a page in two sections, a very long page with 78 headings, duplicate and unusual headings, Vietnamese pages, and non-doc pages and blog posts that share keywords so you can check search stays inside the docs. Search insights is pre-filled with sample searches.

Extra logins: `editor` / `password` (Editor role) for checking permissions.
