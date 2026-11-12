# Publishing Waypost on WordPress.org

Everything needed to get Waypost into the WordPress.org plugin directory, from account to first release. Checked against the official handbook on 12 November 2026.

## What is ready

| Item | Where |
|---|---|
| Plugin zip | `npm run zip` builds `dist/waypost.zip` (the free plugin, about 115 KB). `npm run zip:pro` builds `dist/waypost-pro.zip`, which is sold separately and never goes to WordPress.org |
| Listing text, FAQs, screenshot captions | `waypost/readme.txt` |
| Banners, icons, screenshots | `marketing/wordpress-org/assets/` (rebuild with `npm run wporg-assets`) |
| Plugin Check | `npm run plugin-check`: no errors. 5 warnings about `tax_query`, which is how Waypost filters by section; warnings do not block submission |
| Slug | `waypost` is not taken (checked with the WordPress.org API) |
| Tested up to | 7.1 (WordPress 7.1.4 is current) |

## Decide before you submit

1. **Free and Pro are already split.** Document search, synonyms and topic filters live in the separate `waypost-pro` plugin; the free zip contains no Pro code. WordPress.org does not allow locked or trial features, so keep it that way: new paid features go into Waypost Pro, which plugs into the free plugin's hooks. Upgrade prompts are allowed but must be limited and dismissible.
2. **The slug.** It is taken from the Plugin Name header (`Waypost`) and **cannot be changed after you submit**. The display name can change later.
3. **The account.** The WordPress.org username becomes the SVN username and must match `Contributors:` in `readme.txt` (currently `anerisdesigns`).

## 1. Set up the account (once)

1. Register at https://login.wordpress.org/register with an email you check daily. A company address (for example on anerisdesigns.com) is recommended for a company plugin.
2. Use the username `anerisdesigns`, or change `Contributors:` in `waypost/readme.txt` to match the one you choose. Usernames are case sensitive.
3. Turn on **two-factor authentication**: Profile, Edit Profile, Account & Security. It is required to submit a plugin.
4. Add `plugins@wordpress.org` to your email contacts so review emails do not go to spam.

## 2. Final checks before submitting

From the Waypost folder, with the local sites running (`npm run start`):

```bash
npm test
```

```bash
npm run plugin-check
```

```bash
npm run zip
```

Make sure that:

- all tests pass and Plugin Check shows no `ERROR` lines;
- `Version` in `waypost/waypost.php`, the `WAYPOST_VERSION` constant and `Stable tag` in `readme.txt` all say the same version (1.0.0 now);
- `Tested up to` is the current WordPress major version.

## 3. Submit

1. Go to https://wordpress.org/plugins/developers/add/ while logged in.
2. Upload `dist/waypost.zip` and accept the terms.
3. The upload runs Plugin Check automatically; any error blocks the submission until it is fixed.
4. Confirm the slug it offers (`waypost`).

The review team reads the code by hand. The handbook says to expect a reply within about 14 business days, often sooner. They write to your account email with any changes needed; reply to that email after fixing them. The most common requests are about escaping output, sanitising input, prefixes, and calls to outside services, which Waypost already handles.

## 4. After approval: upload with SVN

Approval gives you an SVN repository at `https://plugins.svn.wordpress.org/waypost`. SVN is how every release is published.

1. **Set an SVN password:** Profile, Edit Profile, Account & Security, in the SVN password section. It is separate from your login password and can be revoked on its own.
2. **Install SVN** if needed (on a Mac: `brew install subversion`).
3. **Check out** the repository, anywhere outside this project:

   ```bash
   svn co https://plugins.svn.wordpress.org/waypost ~/waypost-svn
   ```

4. **Copy the plugin into trunk.** The contents of the `waypost/` folder go straight into `trunk/`, so `trunk/waypost.php` is at the top level, not `trunk/waypost/waypost.php`:

   ```bash
   rsync -a --delete --exclude .DS_Store ~/ANERIS/Waypost/waypost/ ~/waypost-svn/trunk/
   ```

5. **Copy the listing images into assets** (the top-level `assets/` folder, not inside trunk):

   ```bash
   rsync -a ~/ANERIS/Waypost/marketing/wordpress-org/assets/ ~/waypost-svn/assets/
   ```

6. **Add the files and set image types** so they display instead of downloading:

   ```bash
   cd ~/waypost-svn && svn add --force trunk assets
   ```

   ```bash
   cd ~/waypost-svn && svn propset svn:mime-type image/png assets/*.png && svn propset svn:mime-type image/svg+xml assets/icon.svg
   ```

7. **Tag the release** so it matches `Stable tag: 1.0.0`:

   ```bash
   cd ~/waypost-svn && svn cp trunk tags/1.0.0
   ```

8. **Commit**, using your WordPress.org username and SVN password when asked:

   ```bash
   cd ~/waypost-svn && svn ci -m "Release 1.0.0"
   ```

The plugin page appears at https://wordpress.org/plugins/waypost/ within minutes. Images sit behind a cache and can take a little longer to show.

## 5. Releasing an update

1. Make and test the changes here as usual.
2. Raise the version in three places: the `Version` header and `WAYPOST_VERSION` in `waypost/waypost.php`, and `Stable tag` in `readme.txt`. Add a `== Changelog ==` entry.
3. Update `Tested up to` when a new WordPress version comes out. To change only that without a release, edit `readme.txt` in both `trunk/` and the current `tags/` folder and commit.
4. Run `npm test`, `npm run plugin-check`, and `npm run screenshots` plus `npm run wporg-assets` if the design changed.
5. Copy into SVN as in steps 4 to 6 above, then tag and commit:

   ```bash
   cd ~/waypost-svn && svn up && svn cp trunk tags/1.0.1 && svn ci -m "Release 1.0.1"
   ```

Rules to keep in mind: commit only finished releases (SVN is not for day-to-day work), never upload zip files to SVN, and always give each release a new version number.

## Rules that matter for Waypost

From the WordPress.org detailed plugin guidelines (https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/):

- **No trialware (guideline 5):** no features locked behind payment, and nothing that stops working after a trial.
- **No contact with outside servers without opt-in (7):** Waypost makes none today. Any future licence check, analytics or AI feature needs an explicit opt-in.
- **No remote code, and scripts and styles bundled locally (8):** already the case.
- **"Powered by" links off by default (10):** Waypost shows none.
- **Admin notices limited and dismissible (11):** keep any future upgrade prompts to Waypost's own screens.
- **Use WordPress's bundled libraries (13):** Waypost uses WordPress's jQuery and jQuery UI. The PDF library is only in Waypost Pro, not in the free plugin.
- **No keyword stuffing in the readme (12):** five tags at most; the current ones are fine.

## Useful links

- Submit: https://wordpress.org/plugins/developers/add/
- Plugin assets guide: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- Using SVN: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- 2FA and Plugin Check announcement: https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/
- Readme validator: https://wordpress.org/plugins/developers/readme-validator/
