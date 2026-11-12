# Selling Waypost Pro with Freemius

Waypost Pro is sold through Freemius as a **paid-only (premium-only) plugin**. Freemius handles checkout, VAT and sales tax (it is the merchant of record), licence keys and automatic updates. The free Waypost plugin on WordPress.org has no Freemius code at all.

Checked against the Freemius documentation on 12 November 2026.

## What is already done in the code

- The Freemius WordPress SDK 2.13.5 is bundled in `waypost-pro/freemius/`.
- `waypost-pro/includes/freemius.php` starts the SDK as a paid-only plugin with product ID 44694 and its public key (both public by design). The local test sites set both to empty in `.wp-env.json`, which switches licensing off there so Pro runs without a licence.
- Without an active licence (or trial), Pro's features pause and the free plugin carries on as normal. Freemius shows an "activate your licence" notice and page.
- Licence activation and the Freemius Account page sit under **Waypost, Waypost Pro**, so the free plugin's own screens are never blocked.
- `waypost-pro/readme.txt` is in place and there is no `uninstall.php`, both required by Freemius. Clean-up on deletion runs through Freemius instead.
- I tested it with a dummy ID and key: the SDK loaded, Pro paused, and the activation screen appeared on the Waypost Pro page.

## 1. Set up your Freemius account

1. Sign up at https://dashboard.freemius.com as a developer.
2. Fill in your business details, and under **My Profile, Payouts** choose PayPal, Payoneer or bank wire. Payouts are monthly once you have at least $100.

## 2. Create the product

1. **Add Product, WordPress Plugin.**
2. Name: `Waypost Pro`. Slug: `waypost-pro` (it must match the code).
3. Choose to sell it as a **paid-only** plugin, not freemium, and not as an add-on. Freemius advises against the add-on model for most cases, and it would need the SDK in the free plugin.

## 3. Plans and prices

1. Under **Plans**, delete the default **free** plan. With no free plan and at least one paid plan, Freemius treats the product as paid-only.
2. Add your paid plans. A common shape for docs plugins:
   - **Personal:** 1 site
   - **Business:** 5 sites
   - **Agency:** unlimited sites
3. For each plan choose annual and, if you like, lifetime prices. Freemius can also offer a free trial (with or without a card); the code treats a trial like an active licence.

The prices are yours to set; check competitors such as BetterDocs and Heroic KB first.

## 4. Copy the keys into the plugin (done: ID 44694)

1. In the product, open **Settings, Keys**.
2. Copy the **Product ID** and the **Public Key** (it starts with `pk_`).
3. Paste them into the two constants at the top of `waypost-pro/includes/freemius.php`, or send them to me and I will add them:

   ```php
   define( 'WAYPOST_PRO_FS_ID', '12345' );
   define( 'WAYPOST_PRO_FS_PUBLIC_KEY', 'pk_…' );
   ```

Never put the **Secret Key** in the plugin.

The dashboard's **SDK Integration** page shows a generated code snippet. The bundled code already does the same; if your snippet has extra settings that ours lacks, send it to me and I will merge them.

## 5. Test a purchase in sandbox mode

1. Turn on **Sandbox** in the Freemius dashboard and buy a plan with their test card details.
2. Install the free Waypost plugin and `dist/waypost-pro.zip` on a staging site (not WordPress.org).
3. Go to **Waypost, Waypost Pro**, enter the test licence key and check that documents, synonyms and topic filters appear.
4. Check the Account page, then deactivate the licence and check that Pro pauses again.

## 6. Upload the first version

1. Build the zip:

   ```bash
   npm run zip:pro
   ```

2. In Freemius, go to **Deployment, Add New Version**, upload `dist/waypost-pro.zip`, then set the version to **Released**.

Freemius stores the zip and sends it to customers after purchase and as automatic updates. Updates appear in WordPress like any other plugin update, for customers with a valid licence (allow up to about 12 hours to show).

## 7. Start selling

- Use Freemius **Checkout** links or buttons on your website's pricing page (the dashboard generates them).
- Replace "Waypost Pro is coming soon." in `waypost/readme.txt` with a link to that page, and do the same on your website.

## Releasing an update

1. Raise the version in `waypost-pro/waypost-pro.php` (the `Version` header and `WAYPOST_PRO_VERSION`) and `Stable tag` in `waypost-pro/readme.txt`, and add a changelog entry.
2. Run `npm test` and `npm run zip:pro`.
3. Upload the zip under **Deployment, Add New Version** and release it.

## Fees

From https://freemius.com/pricing/ (check for changes): 3.8% plus 50 cents per transaction plus payment gateway fees, merchant of record included, with an extra 2.3% for the WordPress solution. The revenue share drops as monthly sales grow, to 0.5% above $100,000.

## Links

- Developer dashboard: https://dashboard.freemius.com
- SDK integration: https://freemius.com/help/documentation/wordpress/integration-with-sdk/
- Integration snippet options: https://freemius.com/help/documentation/wordpress-sdk/integration/integration-snippet/
- Deployment: https://freemius.com/help/documentation/selling-with-freemius/deployment/
- Software updates: https://freemius.com/help/documentation/wordpress/software-updates-distribution/
