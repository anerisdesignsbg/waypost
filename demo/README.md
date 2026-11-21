# Docsway docs demo site

The Docsway tutorials, published as a live documentation site built with Docsway itself. It doubles as the product's documentation and as a live demo customers can click around.

The site is built by a small helper plugin, **Docsway Demo Content** (`aneris-docsway-demo/`). On any WordPress with Docsway active, it creates the doc sections, one page per tutorial with its screenshots, a docs home page set as the front page, and the Docsway settings (Modern style, docs bar, back link to anerisdesigns.co). Running it again replaces the tutorial pages with fresh copies, so it is also how the site is updated when the tutorials change.

The content comes from `marketing/tutorials/*.md` and `marketing/screenshots/`, so there is only one copy of the tutorials to edit.

## Commands

| Command | What it does |
|---|---|
| `npm run demo:start` | Start the local demo site at http://localhost:8890 (admin / password) |
| `npm run demo:build` | Rebuild the docs on the local demo site from the current tutorials |
| `npm run demo:zip` | Build `dist/aneris-docsway-demo-<version>.zip` for a live site |
| `npm run demo:stop` | Stop the local demo site |

## Putting it live at anerisdesigns.co/docsway-demo

You need two zips: `dist/aneris-docsway-<version>.zip` (`npm run zip`) and `dist/aneris-docsway-demo-<version>.zip` (`npm run demo:zip`).

### 1. Install WordPress in the folder

The demo is its own WordPress, separate from your main site.

**With a one-click installer** (most hosts: cPanel's Softaculous, WordPress Toolkit in Plesk, or your host's own):

1. Choose **Install WordPress**.
2. Domain: `anerisdesigns.co`. Directory (or path): `docsway-demo`.
3. Site name: `Aneris Docsway`. Choose an admin username, a strong password and your email.
4. Install. The site will be at https://anerisdesigns.co/docsway-demo and its dashboard at https://anerisdesigns.co/docsway-demo/wp-admin.

**Without an installer:** download WordPress from wordpress.org, upload its contents into a new `docsway-demo` folder in your site's web root (often `public_html`), create a database and user in your hosting panel, then open https://anerisdesigns.co/docsway-demo and follow the installer.

### 2. Install Docsway

1. In the new site's dashboard, go to **Plugins, Add New, Upload Plugin**.
2. Choose `aneris-docsway-<version>.zip`, press **Install Now**, then **Activate**.
3. The setup wizard opens. Press **Exit setup**: the demo plugin sets everything up instead.

Once Docsway is approved on WordPress.org, you can install it from **Plugins, Add New** by searching for Docsway instead, and it will update itself like any other plugin.

### 3. Build the docs

1. **Plugins, Add New, Upload Plugin**, choose `aneris-docsway-demo-<version>.zip`, install and activate.
2. Go to **Tools, Docsway demo** and press **Build the docs site**.
3. When it says "Done: 7 sections, 11 pages and 13 images", press **View the docs**.

### 4. Finishing touches

- **Settings, Reading:** make sure "Discourage search engines from indexing this site" is **unticked**, so the docs can be found on Google.
- **Back link:** the docs bar links back to https://anerisdesigns.co. To change it, go to **Docsway, Settings, Appearance, Back link**.
- **Comments:** under **Settings, Discussion**, untick "Allow people to submit comments on new posts" if you do not want comments.
- **Look:** the demo uses the Docsway docs bar, so the theme hardly shows. Any theme works; WordPress's default is fine.
- Keep **Aneris Docsway Demo Content** active. Besides rebuilding, it sends any address that is not a doc page (old links, mistyped addresses, the theme's "Page not found", blog, category, author and attachment pages, feeds and WordPress search) to the docs home. To send them to the product page instead, enter its address under **Tools, Docsway demo, Other addresses** and press Save. Leave it empty for the docs home. The docs pages, search, the dashboard and the login page are never redirected.

## Updating the tutorials later

1. Edit the files in `marketing/tutorials/` (and retake screenshots with `npm run screenshots` if the design changed).
2. Check them locally: `npm run demo:build`, then look at http://localhost:8890.
3. Run `npm run demo:zip`.
4. On the live site: **Plugins, Add New, Upload Plugin**, choose the new zip and confirm **Replace current with uploaded**.
5. **Tools, Docsway demo, Build the docs site.** The tutorial pages are rebuilt. Searches readers made and page feedback on the old pages start again from zero.
