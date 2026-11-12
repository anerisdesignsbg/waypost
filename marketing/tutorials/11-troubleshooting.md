# Troubleshooting

## A page is missing from the menu

- Is it **published**? Drafts and private pages stay hidden.
- Is it in a **section**? Check **Waypost, Structure, Assign pages** with the **Not in docs yet** filter.
- Is its content type ticked in **Waypost, Settings, Content**?
- If you imported pages with another plugin, press **Rebuild menu cache** under **Waypost, Structure, Menu order**.

## A section is missing from the menu

Sections with no published pages are hidden on purpose, so readers never open an empty heading. Add a published page to it.

## Search does not find a page

- Search only covers pages in sections. Check the page is in the docs.
- If readers use a different word, add it to the page, or with Waypost Pro add a **synonym** in **Waypost, Settings, Search**.
- Search reads the text readers see. Text inside images cannot be found.

## The menu or search appears twice

You have both the Waypost docs layout or automatic extras and your own blocks, modules or shortcodes. Either remove your own, or untick the automatic ones in **Waypost, Settings, Navigation, Add to every doc page**.

## Search results have nowhere to show

Choose a **Docs home page** in **Waypost, Settings, Navigation**, or set **Full results show** to a separate results page in **Waypost, Settings, Search**. The setup wizard can create either page for you.

## The docs layout clashes with my theme

- Try the **Waypost docs bar** in **Waypost, Settings, Appearance, Header and footer**. Doc pages then get a header and footer of their own.
- Try a different **Style**: **Light** or **Dark** set their own backgrounds and stay readable on busy themes.
- Or choose **My theme's layout** and place the pieces yourself.

## Old styles still show after an update

Clear your caching plugin and any CDN cache. Waypost changes its file versions when they change, so browsers fetch new styles on their own.

## A document is not searchable (Waypost Pro)

Open **Waypost, Settings, Documents**. Documents that could not be read are listed with the reason:

- **No text found:** a scanned PDF. Add a description in the Media Library.
- **Could not be read:** damaged or password protected.
- **Too large to read:** over 30 MB.
- **Waiting to be read:** press **Read waiting documents now**.

## Still stuck?

Every Waypost screen has a **How this works** link and a Help tab. **Waypost, Help** has a guide for every feature.
