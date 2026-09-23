# Кофештаб «Романов на Волге» — site

Eight-page site for a coffee hub ("Кофештаб") in a historic merchant house on
the Volga embankment in Romanov (Tutaev, left bank), Yaroslavl region, Russia.
All content is Russian. **WordPress**, with a custom theme that is the whole
design.

## Where this has been, and why it matters

The site has been rebuilt three times in one year and the reasons were never
aesthetic. Read this before proposing a fourth platform.

1. **Static HTML + Decap CMS on Cloudflare, repo on GitLab.** Cloudflare is
   throttled by RKN, so the site was **unreachable from Russian IPs** — the
   client's own customers could not open it. GitLab then blocked the owner's
   account without explanation, which killed Decap too (it authenticates through
   gitlab.com). Two of the three foreign services this site depended on were
   gone inside a week.
2. **Grav on Beget.** Flat-file, no database, its own logins. Built, ported,
   verified — and it worked. It is gone anyway, by the owner's decision: Grav is
   a niche CMS in Russia, and the person who has to run this site day to day
   cannot ask anyone for help with it.
3. **WordPress on Beget.** Where it is now.

**The lesson that survived all three: the failure mode is never the software, it
is depending on someone who can withdraw.** Cloudflare, GitLab and Decap each
withdrew. WordPress on Russian shared hosting has no such party — Beget is
Russian, the theme is in this repo, and there are no plugins.

## Why WordPress, with the alternatives on record

- **Every git-based CMS is disqualified.** Decap and Sveltia both require the
  editor to hold a GitHub or GitLab account. The requirement is that the client
  logs in with a password the developer hands them.
- **Every hosted CMS is disqualified** (Tina Cloud, Contentful, Storyblok).
  They satisfy the password requirement but reintroduce a foreign service that
  can throttle, block or ban — which is exactly how Cloudflare and GitLab were
  lost.
- **Grav** satisfied everything technically and was actually built. It failed on
  the thing that is not in any feature list: nobody in Russia knows it.
- **WordPress** is what the client can get help with — from a friend, a forum, a
  YouTube video in Russian, or any freelancer. Beget installs it in one click,
  the admin ships in Russian, and someone else patches the auth.

**No plugins.** Not purity: every plugin is another thing that breaks on a PHP
bump, another update the client must understand, and another party that can
disappear. The custom fields are ~250 lines in the theme and WordPress core
patches them along with everything else.

## Structure

```
wp-content/themes/coffeeshtob/   the entire site — design, templates, fields
web-root/                        four files that must sit at the document root
.github/                         two check scripts, one deploy workflow
.wp-dev/                         local WordPress (gitignored, rebuilt by script)
```

WordPress core, plugins, `wp-config.php` and `wp-content/uploads/` are **the
server's**, never the repo's. The client's pages live in the database and their
photographs in uploads; neither can be recreated from git. The deploy writes the
theme directory and four root files and nothing else — see "Deploy".

### The eight pages

| Route | Page | Template |
|---|---|---|
| `/` | Кофештаб | `front-page.php` |
| `/okrestnosti/` | Окрестности штаба | `template-cards.php` |
| `/kuhnya/` | Кухня штаба | `template-kuhnya.php` |
| `/gostinaya/` | Гостиная штаба | `template-cards.php` |
| `/komanda/` | Команда штаба | `template-cards.php` |
| `/mastera/` | Мастера штаба | `template-cards.php` |
| `/skazki/` | Сказки штаба | `template-cards.php` |
| `/kontakty/` | Контакты и график работы | `template-kontakty.php` |
| `/privacy/` | Политика конфиденциальности | `template-privacy.php` |

Four content templates cover nine pages. `page.php` and `index.php` both defer
to `template-cards.php`, so a page the client creates without picking a template
renders correctly instead of blank — Grav's equivalent rendered an empty body,
which is how the first activation of that theme failed.

### The content model

- **Pages** are the sections. Featured image = the banner photo. The rest of
  their copy is meta fields (`inc/fields.php`).
- **Карточки** (`shtob_card`) are the entries inside a section, and also the
  menu rows. `public => false`, so a card has **no URL**: placeholders like
  «Имя» and «Сувенир» never become thin indexable pages. Child pages were the
  obvious core-only answer and were rejected for exactly that.
  One post type covers three shapes — section cards, menu rows, drinks — because
  they differ by two fields (`Список`, `Цена`), not by structure.
- **Внешний вид → Настроить** holds the address, phone, the footer blurb and
  the three social links. They appear in the footer of every page, on Контакты
  and in the structured data, so they live once.

Order is `menu_order` («Порядок»), the same idea as Grav's numeric folder
prefixes. Ties fall back to title, never to date.

**Every field is declared once**, in `shtob_field_groups()`. The meta box, the
sanitiser, the save handler and the CI check all read that declaration. Adding a
field is one entry; nothing else needs editing.

### The navigation is generated, and must stay that way

Built from the published top-level pages, minus the front page and anything
ticked «Не показывать в меню». The header and the 404 call `shtob_nav()`; the
front page's section index reads the same `shtob_nav_pages()`. The footer has
carried no navigation since 1.3.0, and `check-fields.py` now fails on any
section linked by a hardcoded URL anywhere in the theme.

It was hardcoded three times on the static site — header, footer, 404 — and had
already drifted. A WordPress menu (Внешний вид → Меню) is the native answer and
was rejected: it makes a new page invisible until somebody remembers to add it,
which is the same failure. `is-active` is rendered server-side, so it is right
before first paint and right with JavaScript off.

**The scrollspy that used to compute that highlight is deleted and must not come
back.** It matched `href === '#' + id`; with real URLs no link can ever match,
and because it called `toggle` on every intersection it would actively strip the
class the theme now renders.

## Editing the site — what the client actually does

Three places, all native WordPress, all in Russian:

- **Страницы** — section copy, the banner photo, the SEO title and description.
- **Карточки** — the entries inside each section, and the menu with prices.
- **Внешний вид → Настроить → Контакты Кофештаба** — address, phone, footer text,
  and the social links (which also become the buttons on Контакты).

A dashboard note (`inc/admin-help.php`) says this on the first screen they see,
because three things are genuinely not guessable: that cards are a separate
menu, that the address is in the Customiser, and that the top menu builds itself.
The «Записи» menu is hidden — nothing on this site is a blog post.

**Инструменты → Наполнить сайт** appears only on an empty install. It creates
the eight pages, thirty-one cards and sixteen photographs from
`theme/seed/content.php`, then deletes its own files. It **refuses to run if any
page already exists** — the dangerous version of this feature is the one that
runs a second time on a site the client has been editing for a month.

**A brand-new WordPress trips that guard**, which is worth knowing before it
looks like a bug: the install ships «Пример страницы» published and «Политика
конфиденциальности» as a draft, the guard counts drafts, and so the button is
refused on the emptiest site there is. Move both to the корзина first — trashed
pages are not counted, and the seeder deletes those two by slug anyway once it
runs.

`seed/content.php` was generated from the previous build's page files, not typed:
the Russian copy, the prices and the filenames are the client's. The old files
are gone, so **this is now the only record of the starting content**.

## The design, which did not change

`style.css` and `js/script.js` came across from the previous build unchanged
apart from font paths and one appended WordPress-integration section at the
bottom, and have since had two passes — 1.1.0 (polish) and 1.2.0 (new faces,
album corners, developing photographs, social buttons), both below — that kept
the layout and the palette tokens. Everything below is still live.

**Content is not boxed.** Entries in the About, Menu and card sections sit
directly on the page — no background, border, radius, padding or hover lift. The
photo, the heading and the whitespace do the grouping. This was a deliberate move
away from "every block in its own rounded rectangle", and it has come up
repeatedly with the owner.

Exactly one thing in `<main>` is framed: `.info-card--panel`, the opening-hours
table. Being the **only** boxed element is what makes it read as emphasis.
**Never move box styling onto the shared `.info-card` selector** — that re-boxes
its two plain-text siblings and destroys the distinction.

Because there is no card padding, the grid gaps are load-bearing — they are the
only thing separating entries. `.life-grid` 40/54px, `.info-grid` 44px.

**Every section uses the alternating rows** (`layout: alt`): full-width rows,
text left and photo right, flipping each row, so the site reads as one continuous
zig-zag from the front page's About block onward. `wide` and `grid` are kept and
unused. `alt` is the default in both the template fallback and the editing form,
so a page the client creates matches without their knowing why.

- The two-column row and the alternation live in `@media (min-width: 961px)`,
  matched to where `.about-grid` also goes two-column, so the section is never
  half zig-zag and half stacked.
- **The column ratio flips with the side.** Written once as `0.95fr 1fr` the
  narrow column stays left, so photos came out 506px on even rows and 532px on
  odd — they changed size as they changed sides. Each parity sets its own ratio.
  Verified again on WordPress: every row 506px on every page.
- **Every rule is scoped to `.feature-grid >`.** On `.feature-card` alone the
  alternation applied to the same card reused inside the compact grid.

**The menu is a list, not a grid.** `repeat(auto-fill, minmax(272px, 1fr))` — the
column count follows the available width, never the item count, so items can be
added and removed in the admin and nothing in CSS ever changes. Measured against
the 1140px container: 1 column to ~600px, 2 from ~700px, 3 from ~1000px. The
272px minimum is what buys the two-column tablet case.

**The hero fills the viewport: `min-height: 100svh`.**

**`svh`, never `dvh` — this is the important one.** `dvh` re-resolves as the
mobile address bar collapses, so the hero grew mid-scroll and the cover-fitted
photo rescaled with it: visible zoom-and-jank on every iOS scroll, made worse by
the 4px blur repainting each frame. That bug shipped and was reported from a real
phone.

The hero starts at the top of the document behind the sticky header, via
`margin-top: calc(-1 * var(--header-h))` and a matching `padding-top`. **The
pull-up and the top padding must change together** — they cancel exactly. That
makes `--header-h` load-bearing for layout, not just scroll offsets; it is
measured in `script.js`, and the `100px` fallback is what everything resolves
against if JS never runs.

`--hero-progress` drives the scroll response: copy lifts 36px and fades, photo
drifts down 42px. **It only ever touches opacity and transform, never a height.**
The fade holds full opacity for the first 18% — a plain `1 - p * 1.6` left the
headline at 52% after 30% of the hero, which reads as the text bailing out while
still in view. `.hero-bg`'s scale is 1.12 *because* of that drift: the scale
pushes blur's feathered edge out of frame, and 6% no longer covers it once the
image also travels 42px.

**Photos sit IN the page, not on top of it, and that took four things at once.**
`.img-frame` used to carry a 1px solid border, which against a textured
background read as a cut-out pasted on. What replaced it: an inset hairline at
half the old alpha, a soft warm low shadow, an `::after` with a wash of the page
colour and a faint inner vignette, and `filter: saturate(0.94)` on the image.
None of it works alone. The `::after` must keep `pointer-events: none` —
`.card-link` wraps whole frames.

**Since 1.3.0 a card with no photo in the alternating layout is NOT given a
placeholder frame** — consecutive photo-less cards collect into one hairline
`.text-list` in their place (see 1.3.0 below), and a menu row with no photo has
no thumbnail. The paragraph that follows is still true for the other layouts
and the single frames (About, `wide`, `grid`).

**A missing photo falls back to `assets/images/placeholder.svg`, and that is
load-bearing.** Several sections are still waiting on the owner's photographs,
and each of those cards needs a correctly-proportioned frame or the row collapses
and the page reflows when a real photo arrives. **A page with no banner photo
gets a plain dark band instead** (`.page-banner--plain`): the placeholder is a
line-art picture frame, and blurred across a banner it reads as a broken image
rather than as "a photo is coming".

**Photos are mounted in album corners** (`.img-frame::before`), top-left and
bottom-right, in kraft card with a fold line. That is the THIRD answer to
"clipped to the paper". A solid binder clip came first and read as a handbag
handle; a stroked paperclip came second and still read as an icon stuck on a
photograph. Both were objects lying ON the picture. Album corners are the page
holding the picture, which was the idea — and it is how every family album on
a Russian bookshelf holds its prints. Kraft beat cream (vanished on the page)
and album black (the heaviest thing on screen), judged side by side on real
photos on both backgrounds.

The corners are gradients, not an SVG, so `--mount` and `--mount-edge` are
tokens. The pseudo-element sits 3px outside the frame so each pocket runs past
the print's edges, as a real corner does, and that is why **`.img-frame` is
`overflow: visible`**: the rounding sits on the image and the vignette
(`border-radius: inherit`) instead. The prints went from 14px rounding to 6px
with it — a fully rounded print in square pockets reads as a phone screenshot.
The empty placeholder frames get corners too, on purpose: an empty album slot
is exactly what they are.

**Icons are named, never positional.** They were a `loop.index == 1..4` chain
inherited from the first CMS: reordering cards silently swapped their icons and a
fifth card got none. Adding one means adding a case in `parts/icon.php` **and**
an option in `shtob_icon_choices()` — the CI check fails if they disagree.
Гостиная deliberately uses none: its entries are too varied, and an icon on only
some cards in a row knocks the headings 21px out of line.

### The 1.1.0 polish: warm light and printed-menu details

Asked for as "more modern, warm and welcoming, not generic", with the feel,
layout and content kept. What it changed, and the rule each one carries:

- **The hero's primary button is cream, not brown.** `--accent` is built for
  light pages; on the darkened photo it sank beside its own ghost sibling, so
  the one thing the hero asks you to do was the least visible mark on it. Scoped
  to `.hero .btn-accent` — the header's «Соцсети» keeps the brown.
- **`--glow` is light, never paint.** A warm radial over the hero, banner and
  footer, placed where each overlay is already darkest so it warms the photo
  without lifting it under white text. Nothing is *coloured* with it; that is
  what stops it drifting back to the orange browns the palette left behind.
- **The label over a heading is a section mark, not a kicker.** It was 0.78rem
  tracked capitals — the stock landing-page eyebrow. Now it is the serif at
  reading size in sentence case, led by one bean drawn like the background ones,
  as a `mask` filled with `currentColor` so it takes the label's colour on light
  and on the banner. Six of the seven seeded banner labels were the page title
  again in capitals, printing the same words twice; they are gone from the seed,
  and the field's help text now says not to repeat the title.
- **The menu has dotted leaders** from name to price, as the row's own
  `::before` flex item. It takes only free space (basis 0, no minimum): a name
  that wraps gets no leader, because the first version's 18px minimum left a stub
  of dots floating in the gap a wrapped line leaves. `:has(.menu-price)` keeps an
  unpriced row from trailing dots to nothing.
- **The hours panel rests on the page like the photos** and, since 1.2.0, is
  mounted in the same album corners. Still the only box in `<main>`.
- **Links are real underlines**, faint at rest, full on hover — a
  `border-bottom` rules off the box, so a wrapped label was underlined beneath
  its last line only.
- **Footer column heads** are the serif in sentence case, same reason as the
  section marks.
- **Pages crossfade** via `@view-transition { navigation: auto }` with the header
  named so it holds still. CSS only, off under reduced motion, and a browser
  without it navigates as before.
- `text-wrap: balance` on headings and `pretty` on paragraphs; selection, caret,
  scrollbar and form accents themed from the palette; one `--ease-out` curve.

**Two contrast failures were found on the dark footer and fixed**, both outside
the 19 light-background pairings: `.note` is `--muted`, which measured **2.8:1**
on `--dark` (now cream at 66%, 6.7:1), and the bottom line at 45% white was
**4.4:1** (now 58%, 6.5:1). Measured at the warm glow's centre as well, where
they are 6.2 and 6.0.

**What it deliberately did not touch:** the nav's font weight (the 1000px
breakpoint is measured against today's label widths), the fonts (replaced in 1.2.0, when
the owner asked), the photo treatment, and the texture layers —
so the composited backgrounds, and every pairing measured on them, are
unchanged.

### 1.2.0: new faces, album corners, developing photographs, social buttons

- **Alegreya and Commissioner replaced Playfair Display and Inter** — see
  "Fonts are self-hosted" for why these two and how they were chosen. Alegreya
  has a smaller x-height than Playfair, so every serif size went up 6-12% and
  the display weight went back to 700; set at Playfair's sizes it read timid.
  **The nav was re-measured with the wider Commissioner** and still fits the
  1001-1200px band: the gap between the last link and «Соцсети» is 79px at
  1060px and grows from there. Re-measure on any label change, as before.
- **Notes (`.note`) are Alegreya's italic.** Commissioner ships no italic, and a
  browser-slanted roman is what `font-style: italic` produced before.
- **The fade-and-rise on every card is gone; photographs develop instead.**
  Each photo enters pale, warm-toned and slightly soft and resolves to its own
  colour over 1.4s, like a print in the tray — the one scroll moment, and the
  only one that belongs on a site of mounted prints. Text never moves: it is
  there when you get there. **The safety model is the point:** `script.js`
  marks only photos BELOW the fold when it runs, so nothing already painted
  flashes back to sepia, and with JavaScript off or reduced motion on no photo
  is ever undeveloped. A row of three develops left to right, 140ms apart.
- **Контакты has real social buttons** — Telegram and VK as 62px brown pills
  with the mark, the name and the handle, under «Мы на связи». The handle is
  DERIVED from the Customiser URL (`@coffeeshtob`, `vk.com/coffeeshtob`), never
  typed, so the button cannot promise one address and open another. The
  channel list now lives once, in `shtob_socials()`; the footer, the header
  popover and the buttons all read it. Deliberately not Telegram blue and VK
  blue: the marks carry the recognition, and two saturated brand colours would
  be the loudest thing on the page. The guide is a link, not a social channel,
  so it stays out of the buttons.

### 1.3.0: distilled, and one authored arrival

Asked for as "more minimal, no unnecessary text, smoother animation, keep all
the content". Critiqued first (design review + detector, run independently),
then changed. Nothing in the database was touched; everything below is theme.

- **Every label over a heading is gone** («О штабе», «Разделы», «Чем
  угощаем», «Другие напитки», the banner label). They restated the heading
  under them. The `eyebrow`, `items_eyebrow` and `sections_sub` fields were
  removed with them (and from the seed); values already stored in the database
  are simply no longer read. Section heads are **left-aligned** now, sharing an
  edge with the rows beneath.
- **The front page's tiles became an index**: one row per section, title and
  teaser left, the section's print small on the right, a hairline between.
  Seven tiles in threes left «Контакты» orphaned and showed empty frames for
  sections without photos; rows take any count and a missing photo is a row
  without a thumbnail. No «Смотреть →» — the row is the link. Every row has
  the same minimum height so a text-only row is not a thin one.
- **Cards without a photo collect into `.text-list`** (parts/cards-list.php),
  in order, instead of a 4:3 placeholder frame beside one heading each.
  Окрестности went from four screens of empty frames to one short list. A card
  moves into the zig-zag the moment its photo is added. The alternation counts
  photo rows only (`:nth-child(odd of .feature-card)`), so a list between them
  does not flip the sides.
- **The hero address is a plain ruled line at the foot of the photo**, not a
  frosted pill at the top; the copy sits as one group in the lower half.
- **The footer lost its sitemap column and the hardcoded address** in its
  bottom line (a second copy of the Customiser value that would not have
  followed an edit), and **gained the opening hours**, read from the Контакты
  textarea via `shtob_hours_rows()` — still typed once. On Контакты itself the
  footer's address column is omitted; the page shows it directly above.
- Headings no longer skip levels (detector finding): card titles are `h2` when
  the page has no lead heading, Контакты's blocks and the footer heads are `h2`.
- «РОМАНОВ НА ВОЛГЕ» (9.9px tracked caps) is set as Alegreya italic sentence
  case; the hours labels are *set* in sentence case with `text-transform`, the
  client's capitals left as typed.

**Motion, and where the "text never moves" rule now stands.** Scrolled text
still never moves. What was added:
- **Arrival** — the one authored entrance: on load the hero/banner photo pulls
  into focus (blur 14px → 4px) while the headline rises out of a clip mask and
  the lead, buttons and address follow. CSS only, `backwards` fill so the
  normal rules (including the hero scroll drift) own the end state. Skipped
  under reduced motion, and the banner title's rise is skipped when arriving
  through a view transition (`html.vt-arrival`, set on `pagereveal`).
- **The index row's title and the banner h1 share `view-transition-name:
  page-title-{ID}`**, so in browsers with cross-document view transitions the
  title you click travels into place. Names are per page ID and unique.
- **Prints are laid in as they develop** — a 10px settle and the album corners
  pressed on a beat later — and **menu leaders draw in** name-to-price. Both
  ride the develop observer and its safety model (below-the-fold only).
- Index-row hover (title steps in, rule draws, print tilts 1.2°), a cascade
  on the ☰ panel's links.

**Snapshotting caveat:** an offscreen WKWebView reports `visibilityState:
hidden` and freezes every animation at 0ms — so the arrival's `backwards`
state (headline clipped, photo blurred) is what it captures. Call
`document.getAnimations().forEach(a => a.finish())` before judging a frame.
Real, visible pages play normally.

### Palette and texture

Warm off-white background (`--cream`, now `#f7f3ec`), soft cream alternating
sections (`--cream-alt`), true-white cards (`--paper`), espresso text (`--ink`),
dark roast hero/footer (`--dark`).

The accent runs as a three-step brown ramp, picked by the background it sits on.
Getting this wrong is the easy mistake — the primary brown vanishes on the
footer, and the light one is unreadable on white. `--accent`/`--accent-dark` for
buttons and links on light; `--accent-mid` for small accents on light; and
`--accent-light` is **the only one legible on `--dark`**.

`--grain`, `--beans` and `--fibers` are three inline-SVG textures applied as
extra *background layers* on `body` and `.section-alt` — never as overlay
elements, which would risk painting over cards. `--beans` is the motif (7 beans
on a 344px tile at 6.8% stroke opacity), `--grain` the tooth (baseFrequency 0.5
at 13%), `--fibers` the paper (low-frequency mottling at 0.011 plus 70 hairlines
on a 700px tile).

The beans have been resized twice and both times the tile moved with them: 13%
on a 200px tile read as wallpaper with the repeat visible in rows, and the
current 344px tile came with the beans being enlarged and simultaneously faded
(0.10 → 0.068) — larger shapes at a lighter weight, which is what "bigger but
blending in more" resolves to. **Generate the data URI rather than hand-editing
it**: it is a one-line URL-encoded string with seven transform groups in it, and
nudging coordinates by hand is how a bean ends up half off the tile with nothing
to show it.

**The paper layer's first version was invisible, and that was measured rather
than argued about.** 18 hairlines on a 340px tile is ~0.4% of pixels at 5% alpha
— it moved the background's mean by 0.03 of one level out of 255. Sparse strokes
cannot make a surface read as paper; low-frequency mottling can. The current
version moves the mean 237 → 234.4 with sd 1.19. The 700px tile is not decoration
either: low-frequency noise on a small tile repeats as a visible plaid.

**THE TEXTURE CHANGES THE CONTRAST MATHS AND THAT IS NOT OBVIOUS.** `--cream-alt`
renders as rgb(231,223,210), not its token rgb(237,228,213). The tightest
pairings — `--accent-mid` and `--muted` on `--cream-alt` — are 4.62 and 4.63:1
against the **composited** background (re-measured after the beans were
enlarged and faded; all 16 pairings clear 4.6). They have been darkened twice, both times
because a texture layer quietly took them under the line (4.62 → 4.53 when the
paper went in, 4.62 → 4.48 when the beans did). **Any change to a background or
a texture means re-measuring every pairing**, by compositing the layers onto the
background in a canvas — not against the token, and not against the rendered
element alone. All 19 currently clear 4.6.

### Responsive

Breakpoints: **1000px** (nav collapses to ☰), 960px (grids go two-column, About
stacks), 640px, 480px.

**THE DROPDOWN BREAKPOINT IS 1000px AND THE NUMBER IS MEASURED.** It was 860px
with six in-page anchors. Seven section links do not fit: at a 900px viewport the
nav ran to x=901 and the «Соцсети» button landed at x=925..1032 — entirely
outside the window, clipped silently by `overflow-x: clip` rather than showing a
scrollbar. Nothing looked broken; the button was simply gone. **`script.js`
carries the same number twice (`min-width: 1001px`); all three move together**,
and if a nav label is added or renamed, re-measure — the failure mode is
invisible.

**`overflow-x` on `html, body` must be `clip`, never `hidden`.** `hidden` forces
`overflow-y` to `auto`, which makes html/body scroll containers and silently
disables `position: sticky` on the header.

The ☰ is three bare lines — **deliberately not** a circular or bordered button;
that was asked for explicitly.

The header hides on scroll-down and reappears on scroll-up by one mechanism at
every width. When hidden it also gets `visibility: hidden`, delayed by the
transition duration: translating a sticky element off-screen is **not** the same
as it being gone — a webview whose sticky box disagrees with the visual viewport
still paints a sliver, which is the strip that stayed on screen in Telegram's
in-app browser. Travel distance is `--header-hide`, published from
`offsetHeight + 16` rather than written as `-100%`, for the same reason.

**The pinned check runs before the small-movement bail, and must stay there.**
Pinning is a fact about where the page *is*, not how far it just travelled. With
the order reversed, a jump to the top or a slow sub-threshold drift left the
header stranded off-screen at `y=0`.

`script.js` is shared by every page and bails out early if there is no
`.site-header`; every reference to an element the legal pages lack (`#toTop`,
`#main-nav`, `#socialBtn`, `#navToggle`) is guarded — one unguarded null throws
on `DOMContentLoaded` and silently kills every other behaviour in the file.

**The legal pages hold their footer at the bottom of the viewport**
(`body.is-legal`). The 404 and the policy are both shorter than a screen, and
without it their one-line footer floated wherever the text happened to end with a
field of empty cream below — it read as the page having failed to finish loading
rather than as a short page. It is keyed on `is-legal`, a class the theme adds
itself, rather than on WordPress's `page-template-…` class, which is derived from
the filename and would break silently if the template were renamed. The marketing
pages must NOT get it: making `<body>` a flex container there fights the sticky
header for no gain.

**The 404's section links are a grid, not a wrapped row**, for the same reason
`.menu-list` is one: the column count follows the width, never the item count.
Seven pills at their natural widths broke 6 + 1 at 1280px, leaving «Контакты»
stranded alone and looking like a mistake. Equal columns break 4 + 3, and a
renamed or added section cannot bring the orphan back.

The legal pages carry a **two-item header** — brand, then «На главную» — with no
`.main-nav` to take up the slack. `.brand + .nav-social-btn { margin-left: auto }`
fixes it, keyed on the adjacency rather than a modifier class on purpose: it is
the *absence of the nav* that needs correcting, so any future page with no nav
gets it right with no class to remember.

Both logos point at the real home URL and carry `data-home`; JS intercepts only
the case where you are already on that page. Checked for overflow down to 320px —
keep it that way.

## Privacy, and why it is code

`/privacy` states in Russian that the site collects nothing, has no forms, no
registration and no visit counters, and sets no cookies. Under 152-ФЗ, collecting
personal data means naming an operator with its ОГРН and ИНН — details this
project has deliberately never invented.

**A stock WordPress breaks that sentence on day one:** comments are a form that
stores a name, an e-mail, an IP address and a cookie. `inc/privacy.php` is what
keeps the page true, and **none of it is optional decoration**:

- comments and pings off everywhere, and the UI removed so nobody turns them back
  on by accident
- XML-RPC disabled (and denied in `.htaccess` as well — a PHP filter only runs
  after WordPress has booted, and the point of `system.multicall` is the hundreds
  of password attempts it fits in one request)
- the REST API closed to anonymous callers; `/wp-json/wp/v2/users` otherwise
  lists every account with its login name
- `?author=1` redirected, which leaks the same thing by another door
- the emoji script removed — it fetches from `s.w.org`, a third-party request on
  a page that promises none
- the block library's CSS dequeued, ~70 KB styling blocks these templates never
  use

**The copy lives in `template-privacy.php`, not in the editor,** and that is
deliberate: these are factual legal assertions about how the site behaves, and
they stop being true the moment anyone adds a contact form, Yandex Metrica, a
chat widget or a consent banner. Adding any of those means rewriting the page in
the same commit. Word count is load-bearing too: ~126 words across three `<h2>`
sections. It was rewritten twice to get that short. **Don't pad it back out.**

`verify-site.py` asserts no page sets a cookie for an anonymous visitor, so this
cannot rot silently.

## Deploy

**One GitHub Actions workflow**, `.github/workflows/deploy.yml`, on pushes that
touch the theme or the root files.

- Secrets: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `REMOTE_DIR`
- Variable: `SITE_URL`

`SITE_URL` is a **repository variable, not a hardcoded string**, because this
project has changed hosting three times and each move meant editing a URL buried
in a workflow.

It writes two places and no others:

```
wp-content/themes/coffeeshtob/   mirrored WITH --delete
the document root                four files, WITHOUT --delete
```

**The second one matters more than it looks.** Its neighbours are
`wp-config.php`, `wp-content/` and WordPress's core. A `--delete` there would
remove the installation and every photograph the client has uploaded.

**The interlock:** before writing anything the job asks the server whether
`REMOTE_DIR/wp-load.php` exists and refuses if it does not. A stale `REMOTE_DIR`
after an account move would otherwise scatter a theme directory into somebody
else's docroot and still report success, because the URL checks run against
`SITE_URL` rather than against what was written.

`seed/` (5 MB) is excluded from ordinary pushes and uploaded only by a manual run
with `upload_seed` ticked.

### Beget breaks FTP in two ways, and both cost days

**1. `lftp`'s exit code is meaningless on this host.** It returns 1 for things
that are not failures — `SITE CHMOD` refusals were the first, and `--no-perms`
did not make it exit 0, so there is at least one more. It has also exited 0
having silently skipped a file. Three separate investigations went into a font
that was never broken. **So nothing in this repo gates on it.** The repair pass
lists what is actually on the server, and `verify-site.py` fetches the delivered
pages. Both measure the outcome instead of trusting the tool's opinion of it.

**2. Some data connections are dropped mid-transfer.** One file failed on every
run while its neighbours went through — deterministic, not flaky, independent of
size, type and directory. lftp reported only `max-retries exceeded`, which
produced **four wrong theories in a row**. Adding `debug 3` ended it in one run:

```
<--- 250 Directory successfully changed.
<--- 426 Failure reading network stream.
```

`426` is the data connection dying, not a permission or a refusal, and `du -hs`
in the same run retired the quota theory. **The cause is still not established**
— two runs contradicted each other about whether encryption mattered. What is
established: `ftp-repair.sh` lands the file every time by issuing a single `put`
on a fresh connection over a ladder of transports, and judges each attempt by
whether the file is there afterwards.

**The lesson, twice over: get the server to say why.** Both of these burned days
on theories while one flag would have printed the answer.

Two cautions for whoever edits the repair: each rung **deletes the remote file
before writing it** (deliberate — a half-written remote file defeats every retry)
so it must never point at a file whose only copy lives on the server; and
`dirname` of a root-level file is `.`, which lftp rejects as `put -O '.../.'`.

### nginx, and why half of `.htaccess` is inert

Beget runs nginx in front of Apache. nginx serves static files itself and never
consults `.htaccess`, so the `mod_expires` and `AddType` blocks **do nothing
there** — measured: CSS came back with a week-long `max-age` where the file asked
for an hour, and `.js` was served as `application/x-javascript`. The rewrites,
`Files` denials and `ErrorDocument` *do* work, because those run in Apache. The
cache block is kept because it is correct and costs nothing.

**The denials work only for what Apache is asked to serve, and `.txt` is not.**
On a rebuilt install `verify-site.py` failed on `/license.txt (200)` while
`readme.html` and `wp-config.php` returned 403 from the very same `FilesMatch` —
nginx serves `.txt` itself and Apache never sees the request. So the fix is to
delete `public_html/license.txt`, not to edit `.htaccess`: no rule there can
reach a file Apache is never asked about. **A WordPress core update restores
that file**, so expect this check to fail again after one, and delete it again.

The consequence is that **asset caching is out of our hands, so the URL carries a
version instead**: `SHTOB_VERSION` in `functions.php` is appended to `style.css`
and `script.js`. **Bump it whenever either changes** — with nginx caching CSS for
a week and no content hash in any filename, it is the only thing that reaches a
returning visitor. The CI check fails if it is not a version number.

**Apache does not know it is behind TLS.** nginx terminates HTTPS, so `%{HTTPS}`
is never `on` inside `.htaccess`, and WordPress must be told the same thing or it
builds every canonical, `og:url` and sitemap entry as `http://` on an `https://`
site. **Any new `R=301` rule must name the scheme explicitly** — written as
`/%1`, Apache expands it with its own idea of the protocol and emits
`Location: http://…`, which the force rule then bounces back: two redirects and a
plaintext hop per link.

**The canonical-host block must sit ABOVE WordPress's.** Placed after it,
`REQUEST_URI` has already been rewritten to `/index.php`, so
`https://www.coffeeshtob.ru/kuhnya/` redirects to `https://coffeeshtob.ru/index.php`
— every `www` visitor landing on the front page whatever they asked for. Caught
against a real Apache on the previous build; it is invisible by reading.

**A wrong directive in `.htaccess` is a 500 on every page, not a skipped
feature.** `AddOutputFilterByType` belongs to `mod_filter`, not `mod_deflate`;
guarding it on the wrong module passes on a server that has one and not the
other and then throws `Invalid command`. Both guards are present.

**`web-root/.htaccess` was tested against a real Apache 2.4.66**, not written
from memory — a throwaway `httpd` on port 8899 with a WordPress-shaped docroot
and `X-Forwarded-Proto` simulated. What that run established:

- the file parses: `/` and an arbitrary deep path both reach the front
  controller, so there is no 500
- `wp-config.php`, `readme.html`, `license.txt`, `xmlrpc.php`, `.htaccess` and a
  `.php` dropped in `wp-content/uploads/` all return 403, while a `.jpg` in the
  same folder still returns 200
- `robots.txt` and the Yandex token are not swallowed by the rewrite
- the ACME challenge is reachable over plain HTTP, so certificate renewal is not
  redirected into a scheme the challenge cannot follow
- **`X-Forwarded-Proto: https` returns 200, not a redirect** — this is the one
  worth re-testing after any edit here, because getting it wrong is not a
  cosmetic bug but an infinite redirect loop on every page of the live site
- `www` → bare happens in a single hop, preserves the path (`/kuhnya/`, not
  `/index.php`) and names `https://` explicitly

The only lines in Apache's error log were the intended `client denied by server
configuration` entries.

### `wp-config.php` on the server

Not in the repo (it holds the database password). It must contain, because of
nginx:

```php
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
    && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

Also set `DISALLOW_FILE_EDIT` to `true` — the built-in theme editor lets anyone
with the admin password execute PHP, and the theme comes from this repo anyway.

## Moving to another Beget account

The domain does not move: `coffeeshtob.ru` is on reg.ru nameservers, so the
cutover is an A-record change there.

1. New account, new site folder, PHP **8.4**, MySQL database, WordPress
   installed (Beget does this in one click; pick the Russian build).
2. Add the `X-Forwarded-Proto` block and `DISALLOW_FILE_EDIT` to `wp-config.php`.
3. Update `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `REMOTE_DIR` and `SITE_URL`.
4. Run the workflow manually with **upload_seed ticked**. It ships the theme, the
   root files and the starting content, then tells you what to do next.
5. In wp-admin: **Инструменты → Наполнить сайт**, press the button.
6. Check Настройки → Постоянные ссылки is `/%postname%/` (the seed sets it, but
   confirm — every URL in the sitemap and the structured data depends on it).
7. Create the client's own administrator account and remove or demote yours.
8. Point the A record at the new server, then re-run the workflow so
   `verify-site.py` measures the real domain.

**If the site is being moved with content already in it**, skip steps 4–5 and
migrate the database and `wp-content/uploads/` instead — the seed refuses to run
on a site that has pages, by design.

### Rebuilding in place, and what 24.09.2026 established

Beget's one-click CMS installer was run against the live site folder by
accident. It installed a fresh WordPress over the existing one and reused
`tryphopx_coffee`, leaving `wp_posts` at 5 default rows: the pages, the cards
and the media library were gone in one action.

**Beget's automatic backups did not save it, and that is the part to plan
around.** The only copy on offer was 10.09.2026 00:42, from before WordPress
existed on the server, and its **Базы данных** tab said «Нет данных» — a file
archive with no database beside it. The automatic copies are not a safety net
for this site; **Backup по требованию before anything structural** is.

The rebuild, which is the procedure if it happens again:

1. Check first, wipe second. phpMyAdmin says whether the content survived —
   `SELECT post_type, COUNT(*) FROM wp_posts GROUP BY post_type` returns roughly
   31 `shtob_card`, 9 `page` and 16 `attachment` on a live site, and five rows
   in total on a fresh one. Download `wp-content/uploads/` before deleting
   anything.
2. Empty `coffeeshtob.ru/public_html/` but keep the folder — the domain is bound
   to it and `REMOTE_DIR` points inside it. Drop the database, install
   WordPress into the same folder, add the two `wp-config.php` lines.
3. Ship the theme. The workflow is the normal route, but it failed on FTP that
   day, and **the theme installs by hand just as well**: zip
   `wp-content/themes/coffeeshtob` (with `seed/` in it, which the workflow
   excludes) and upload it at Внешний вид → Темы → Загрузить тему; the four
   `web-root/` files go into the document root through the file manager, hidden
   files shown so `.htaccess` actually lands. WordPress's own `.htaccess` is
   meant to be overwritten by ours.
4. Then the usual: activate the theme, trash WordPress's two default pages,
   Наполнить сайт, check the permalinks, re-enter the Customiser contacts,
   delete whatever plugin the installer added.
5. `verify-site.py https://coffeeshtob.ru` is what says it worked, and it is
   worth running even when the deploy could not.

What the seed cannot bring back is anything the client changed after the site
was first filled. **Ask them what they edited before declaring it finished.**

## Local development

```bash
./.github/scripts/wp-dev.sh --seed
```

Builds a real WordPress in `.wp-dev/` on SQLite (no database server), symlinks
the theme from the working tree, copies the docroot files in, and loads the
starting content. Admin: `shtob` / `devpassword`. `.claude/launch.json` serves it
on 8766.

**The theme symlink is relative, and that is a fix.** It was absolute; when the
repo moved from `~/Desktop/web-project` the link dangled, WordPress lost its
active theme, and every page answered **200 with an empty body** — no error
anywhere, and `verify-site.py` reported every route as missing its `<h1>`. If a
dev install predates the fix, rerun the script or repoint the link.

**`realpath_cache_size=0` is not decoration.** PHP caches path→inode for 120
seconds, and an editor that saves atomically changes the inode. Without it, edits
appear to have no effect for up to two minutes — and worse, a test run against
them silently measures the OLD code. That produced three flatly wrong
mutation-test results before it was found, which is the same class of false
result that stale browser caches produced twice earlier in this project.

The dev router repeats `.htaccess`'s denials so a local run rehearses production
rather than something nearby.

## The two checks

**`.github/scripts/check-fields.py`** — offline, runs before anything uploads.
WordPress returns an empty string for a meta key that does not exist, so a
renamed field is a blank section with a 200 response. This is the third platform
on which that exact bug was possible. It checks that every field read by a
template is declared and vice versa, that group screens name real template files,
that the icon dropdown and the icon SVGs agree, that the seed only writes
editable fields, that the navigation is still generated, and that
`SHTOB_VERSION` is a version.

**`.github/scripts/verify-site.py`** — fetches the deployed site. **The routes
come from the site, not the repo**, because the pages live in a database this
script cannot read: it reads WordPress's generated sitemap and cross-checks it
against the navigation the theme renders. Two independent views of one list.

Both were **mutation-tested**, which is the only reason to believe them:
11/11 on the field checker, and 9 of 10 on the verifier with the tenth a correct
non-failure (flipping `xmlrpc_enabled` cannot expose xmlrpc, because the server
denies it before WordPress boots).

Two bugs the mutation testing found that reading had not:

- the "is the navigation still generated?" test passed on `footer.php`'s own
  **doc comment**, which mentions `shtob_nav()`, while the real call had been
  replaced by a hardcoded link. Comments are stripped now.
- PHP prints a notice as `<br /><b>Warning</b>:`, so the literal marker
  `Warning:` never appeared and a page full of notices sailed through. Error
  scanning runs on tag-stripped text now.

And one real gap it closed: a link dropped from the navigation *everywhere* was
invisible, because each page was only compared with the front page and the front
page had lost it too. The sitemap is the independent list.

## Icons and SEO

`favicon.svg` is the source of truth for the brand mark at small sizes. It is
**not** the header's cup: that one is stroked at width 2 and turns to mush in a
16px tab, so the favicon redraws it in solid silhouette with nothing thinner than
5/64 of the canvas. The raster icons are generated from the same geometry with
Pillow; if the SVG changes they must be regenerated. Two things that bit during
generation: the working canvas must be **several times** the output size (drawing
1:1 produced an `icon-512.png` with 3 distinct colours), and
`apple-touch-icon.png` is deliberately **square with no transparency**, because
iOS applies its own corner mask and baked-in rounding leaves dark wedges.

**The sitemap and `/llms.txt` are both generated now, and that is a real
improvement.** Both previous builds shipped hand-written files listing every URL,
so adding a page meant remembering to edit two more files — and nothing checked.
WordPress generates `/wp-sitemap.xml` from the pages themselves (trimmed to
pages: no posts, no taxonomies, and **no users sitemap**, which is the
author-enumeration leak arriving by another door). `/llms.txt` is a rewrite rule
in `inc/seo.php` built from the page tree. `/sitemap.xml` 301s to the WordPress
one, because that is the URL search engines already know.

**Nothing hardcodes the domain.** Every absolute URL comes from `home_url()`, so
the site advertises whatever it is served from. The static site had the
production host in 14 places across five files.

**Yandex Webmaster verification lives in `web-root/yandex_11df7f8b41641d66.html`**
and `verify-site.py` asserts it still returns 200 — Yandex fetches a fixed
absolute URL, and a 404 silently loses verification. Yandex matters more than
Google here; the client's customers search on Yandex.

The JSON-LD is `CafeOrCoffeeShop` and contains only facts that are on the page —
no invented geo coordinates, no made-up `priceRange`. The opening hours are
parsed out of one textarea on Контакты: the label and the visible time are the
client's words, and the machine-readable days/opens/closes are **derived** from
them, never typed twice. Grav asked for them as separate fields beside the text,
so the panel could say 15:15 while the rich result said something else and
nothing would have flagged it. A row that cannot be parsed still displays but
contributes nothing — an absent rich result is a small loss where a wrong one
sends people to a closed café.

## Fonts are self-hosted

**Nothing on the public page is fetched from a third party.** Both families live
in `theme/assets/fonts/` with `@font-face` at the top of `style.css`. There is no
`fonts.googleapis.com` link anywhere and there must not be one again: the client
reported the site taking "forever" to load in Russia and then settling into the
wrong fonts, which is exactly what a render-blocking Google Fonts stylesheet does
when Google is slow or blocked.

**Alegreya** sets the headings, the section marks (in its true italic) and the
notes; **Commissioner** sets everything else. They replaced Playfair Display and
Inter in 1.2.0 — the stock "coffee shop" serif and the stock interface sans.
Alegreya is a book face drawn for literature, calligraphic and warm, which suits
a merchant house of stories and crafts; Commissioner is a humanist sans whose
flared strokes sit beside it. **Chosen by rendering six pairings in real Cyrillic
copy from this site**, not from a list: Alegreya Sans was the designed partner
and lost on its small x-height at body size, and Old Standard TT was the most
period-correct for the house and read as a museum label.

All three files (roman, italic, sans) are **variable** — one per subset spans
every weight — and split by `unicode-range` exactly as Google serves them.
Subsets kept: `latin`, `latin-ext`, `cyrillic`, `cyrillic-ext`; 20-43 KB each,
twelve files. **`latin-ext` is not optional despite the name** — the ruble sign
`₽` is U+20BD and lives there, so the menu prices pull it. Both families are SIL
Open Font License. If they ever need regenerating: fetch the css2 URL for the
family with a modern browser user agent, download each subset's woff2, keep
Google's `unicode-range` lines verbatim.

The two Cyrillic romans are preloaded; the italic and the other subsets are not,
because they set little text and swapping in late there costs nothing.

## Things deliberately not built

**A review / feedback form.** Discussed and declined, and all four reasons still
stand: it puts the site under 152-ФЗ, which needs a consent checkbox and an
operator's ОГРН/ИНН — details deliberately left blank rather than invented; 152-ФЗ
also requires Russian citizens' personal data to be stored on servers in Russia;
it invalidates `/privacy`'s opening callout; and a public form on an indexed site
gets bot submissions within days, which is a permanent moderation duty for a
small café. **The recommended alternative is to link out to Yandex Maps**, where
reviews already live, are searched, and affect whether the place gets found.

**Analytics.** Never added, for the same reason. Anything with cookies or a
visitor counter needs `/privacy` rewritten first.

**Plugins.** See the top of this file.

## Working on this project

Conventions that come from the owner, not from the code:

- **Never invent facts.** Prices, geo coordinates, legal identifiers, e-mail
  addresses, opening hours. Ask, or leave it out. Several gaps here are gaps on
  purpose.
- **The owner pushes.** `git push` runs in their own terminal because the
  credential prompt is interactive. Commit locally and tell them.
- **Placeholders are named, never invented.** The empty cards carry the names the
  owner gave and nothing else; Команда and Мастера say «Имя мастера» / «Цена» so
  they read as a form to fill in. No description, address or price has been
  written for a place, person or object nobody has described.
- **Design direction: fewer boxes, less "AI landing page".** This has come up
  repeatedly. Adding a card background, a gradient blob or an icon-in-a-circle
  walks it back.
- **Mobile is the priority surface**, and specifically **Telegram's in-app
  browser** — that webview is where the sticky-header sliver bug appeared and it
  is a real part of this café's traffic.

Verification habits learned the hard way here:

- **Measure; don't assert.** Contrast on the composited background, texture as a
  mean/sd shift, nav fit at real viewport widths, layout across route × width
  sweeps. Every one of those caught something that reading had not.
- **A check that has never failed has not been shown to work.** Mutation-test it.
- **Stale caches have produced false results three times** — browser memory cache
  twice, PHP's realpath cache once. When a change appears to have no effect, or a
  test result looks impossible, suspect the cache before the code.
- **Assert expected counts before trusting an extraction.** A regex HTML edit
  once silently produced one item per list instead of four.
- **In the Browser pane the tab can be hidden**, and then CSS transitions never
  run and scrolled screenshots come back blank — innerWidth even reads 0, so no
  layout measurement means anything. Check `document.hidden` before believing
  any of it. A small WKWebView program (`WKWebView.takeSnapshot`, compiled with
  `swiftc`) screenshots and measures real WebKit at any width without the pane,
  and was how 1.1.0 and 1.2.0 were verified. Photos below the fold carry
  `.develop` until scrolled to: disable transitions, set `loading='eager'`, and
  scroll before judging a photo's colour.

## Content still owed by the owner

All of it renders cleanly as-is — empty fields omit their element and a missing
photo falls back to the placeholder — so none of it blocks anything:

- Окрестности: four named-but-empty cards (Казанский храм и колокольня, Склон
  труда и отдыха, Библиотека, Зелёный дом)
- Гостиная: six placeholder cards; Команда and Мастера: placeholders throughout;
  Сказки: one empty card
- Мастера, Сказки and Контакты have no banner photo
- Four photographs are in the media library unattached, waiting for cards
