# Smart search

Waypost search only looks at your docs, never blog posts or shop pages, and it is built to answer questions rather than just list pages. There is nothing to set up: it works as soon as pages are in sections.

## What readers get

- **Best answer.** The section that answers the search best is shown on top: where it sits, the heading, a short summary and a **Read this section** link straight to it.
- **Also covered in.** Up to three other pages that cover the same thing, each linking to its best section.
- **Matching sections.** Under every result, up to three headings that hold the search, each with a line of text.
- **Topics.** Buttons above the results narrow them to one of your top-level sections, with a count on each.
- **Instant results** under the search box as readers type, with the best answer first.
- **Ctrl+K or Cmd+K** (or the / key) opens a search window on any doc page.
- **Highlights on arrival.** The page a reader opens highlights the words they searched for and briefly lights up the matching section. A **Clear** button removes the highlights.

## How it understands readers

- **Questions:** words like "how", "do", "I" and "the" are ignored, so "How do I share an album?" searches for share and album.
- **Word forms:** "installing", "installed" and "installation" all find "install".
- **Typing mistakes:** when nothing matches, Waypost tries the closest words from your page titles and headings, so "dokcer" finds "docker". A note above the results says what it searched for instead.
- **Some of the words:** if no page has every word, readers see pages with some of them, with a note, rather than an empty page.

Waypost reads every doc page section by section and keeps a plain-text copy, updated each time a page is saved. Page builder content, including Divi 5, is read as text, so layout settings never turn up in results.

## Add synonyms

Readers do not always use your words. Teach search theirs:

1. Go to **Waypost, Settings, Search**.
2. In **Synonyms**, type one group per line, with words separated by commas:

   ```
   photo, picture, image, pic
   sign in, log in, login
   ```

3. Press **Save settings**.

A search for any word in a group now finds pages with any of the others. The best source of synonyms is the **Searches with no results** list in Search insights.

## Settings

In **Waypost, Settings, Search**:

| Setting | What it does |
|---|---|
| Full results show | On the docs home page, or on a separate results page |
| Results page | The separate results page, if you use one |
| Placeholder text | The hint inside every search box |
| Results per page | 5 to 50 |
| Extras | Ctrl+K shortcut; hide other search boxes on the results page; log searches for Search insights |
| Smart results | Best answer on top; topic filters |
| Synonyms | Your readers' words, as above |

## Search boxes elsewhere on your site

Add the **Docs Search** block, the **Waypost Docs Search** Divi module or `[waypost_search]` anywhere, for example in your site header. Results open on your docs home or results page.
