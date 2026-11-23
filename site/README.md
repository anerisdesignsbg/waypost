# Aneris Docsway product site

A landing page for Aneris Docsway at **https://anerisdesigns.co/aneris-docsway/**, on its own WordPress. The `aneris-docsway-site` plugin draws the whole page (hero, live search box, how it works, features, Pro, Free and Pro table, pricing with a yearly / lifetime switch, FAQ), so it looks the same with any theme. Every other address on that WordPress goes to the landing page.

## Work on it locally

| Command | What it does |
|---|---|
| `npm run site:start` | Starts the local copy at http://localhost:8892 (admin / password) |
| `npm run site:stop` | Stops it |
| `npm run site:zip` | Builds `dist/aneris-docsway-site-<version>.zip` |

The page is `aneris-docsway-site/templates/landing.php`, the styles `assets/site.css`, and the menu and price switch `assets/site.js`. Prices, Buy links, the WordPress.org and demo links and the launch offer are in `Docsway_Site::config()` in `includes/class-site.php`. The launch offer hides itself after its end date.

Images are copies of `marketing/website/screenshots/`. After retaking the screenshots, copy the ones used here into `aneris-docsway-site/assets/img/` again.

## Putting it live

### 1. Install WordPress in the folder

In cPanel, use Softaculous (or your host's WordPress installer):

1. Domain: `anerisdesigns.co`. Directory: `aneris-docsway`.
2. Site name: `Aneris Docsway`. Choose an admin username (not `admin`), a strong password and your email.
3. Install. The site is at https://anerisdesigns.co/aneris-docsway/ and its dashboard at https://anerisdesigns.co/aneris-docsway/wp-admin/.

It uses the main domain's SSL certificate, so nothing else needs setting up.

### 2. Install the plugin

1. **Plugins, Add New, Upload Plugin:** choose `aneris-docsway-site-1.0.0.zip`, press **Install Now**, then **Activate**.
2. That is all. Activating it creates the home page, sets it as the front page, sets the site title and icon, turns on plain page addresses and moves WordPress's sample post and page to the bin.
3. Open https://anerisdesigns.co/aneris-docsway/ to check it.

If something looks wrong, **Tools, Docsway site, Set up again** repeats the setup.

### 3. Optional

- **Settings, Discussion:** untick comments, as the site has none.
- **Settings, Reading:** leave "Discourage search engines" unticked so the page can be found.
- Any theme works; the default one is fine.

## Updating

Raise `Version` and `DOCSWAY_SITE_VERSION` in `aneris-docsway-site.php`, run `npm run site:zip`, then upload the new zip under **Plugins, Add New, Upload Plugin** and choose **Replace current with uploaded**.
