# Кофештаб «Романов на Волге» — business card site

Single-page marketing site for a coffee hub ("Кофештаб") in a historic merchant
house on the Volga embankment in Romanov (Tutaev, left bank), Yaroslavl
region, Russia. Content is entirely in Russian.

## Stack

Plain HTML/CSS/JS. No build step, no framework, no package.json. Open
`index.html` directly in a browser (or serve the folder with any static
server) to preview.

- `index.html` — all markup and copy, single page with anchor-linked sections
- `style.css` — all styling
- `script.js` — ☰ nav dropdown + «Соцсети» popover, sticky-header shadow, scroll-reveal on
  cards, active-nav-link highlighting via `IntersectionObserver`, back-to-top
  button. Also measures the sticky header and publishes its height as
  `--header-h`, which `scroll-padding-top` uses to keep anchor targets from
  landing underneath the header.
- `render.js` — swaps CMS copy from `content/home.json` into any element
  carrying `data-cms-field`, then re-applies the URL hash once fonts and copy
  have settled (the browser's own anchor jump happens before that and lands
  in the wrong place). Falls back silently to the static markup if the JSON
  is missing.
- `content/home.json` — the CMS-editable copy. Image paths here are
  **root-absolute** (`/images/…`), and `admin/config.yml`'s `public_folder`
  must match: the CMS preview runs at `/admin/`, so a relative path would
  resolve to `/admin/images/…` and 404. The live site does *not* want them
  absolute — that would assume a domain root — so `render.js` strips the
  leading slash when injecting them (`toRelative`). Net effect: identical at a
  domain root, and still correct if the site is ever served from a
  subdirectory. Verified by serving the site under `/my-repo/`.
- `admin/` — Decap CMS (`config.yml` + loader page), **GitLab backend with
  PKCE**. Chosen for host-portability: PKCE runs entirely in the browser, so
  the CMS needs no server component and nothing ties it to a hosting provider.
  The site can move between Cloudflare Pages, Vercel, a VPS or anywhere else
  and the CMS keeps working. There is deliberately **no** Netlify Identity
  widget anywhere — the public page stays cookie-free, as `privacy.html`
  states. `local_backend: true` only applies on localhost: run
  `npx decap-server` to edit locally, which bypasses auth entirely.

### GitLab OAuth application (one-time setup)

`admin/config.yml` has two placeholders that must be filled before the
deployed CMS will work: `repo` (your `namespace/project` path) and `app_id`.
To get the `app_id`: GitLab → User Settings → Applications → Add new
application.

- **Redirect URI** — the full admin URL, e.g. `https://example.pages.dev/admin/`.
  This is the one host-dependent value. GitLab accepts several URIs, one per
  line, so register every host you might use (production domain, preview
  domain) up front and switching hosts needs no code change.
- **Confidential** — must be **unchecked**. PKCE has no client secret, and
  leaving this on breaks the flow.
- **Scopes** — `api`.

Copy the generated Application ID into `app_id`. There is no secret to store,
which is the whole point: nothing sensitive ever lives in this repo.

Signing in from `localhost` normally fails, because GitLab only redirects back
to registered URIs. `admin/index.html` detects that case and points at
`npx decap-server` instead of leaving a login button that silently does
nothing.

### Remaining files

- `privacy.html` — standalone privacy notice, linked from the footer. Shares
  `style.css` and `script.js` (see the guard note under Responsive behavior).
  Kept deliberately short and free of anything internal: no mention of the
  admin area, the CMS, the hosting provider or staff workflows. It covers only
  what a visitor is actually affected by — Google Fonts seeing their IP, and
  server access logs. If the operator's legal details (юрлицо/ИП, ОГРН, ИНН,
  e-mail) are ever added, they belong here; they were left out rather than
  invented.
- `images/placeholder.svg` — neutral blank placeholder graphic (cream
  background + line-art frame icon) used everywhere a real photo is pending

## Deployment

Cloudflare Pages, connected directly to this GitLab repo. Every push to `main`
triggers a deploy — there is no build step, no CI config and no build command;
Cloudflare just serves the repo root as static files.

That direct connection is what makes the CMS work end to end: a content edit in
`/admin/` is a commit to this repo, which Cloudflare picks up and publishes. If
edits stop appearing on the live site, check the Cloudflare deployment log
first.

Cloudflare Pages settings: framework preset **None**, build command **empty**,
build output directory **`/`**.

Because the repo root *is* the web root, every tracked file is publicly
reachable — including `CLAUDE.md` at `/CLAUDE.md`. Nothing here is secret (the
CMS `app_id` is public by design under PKCE), but don't add anything to the
repo that shouldn't be world-readable.

An earlier commit carried a `.gitlab-ci.yml` that deployed to Beget over FTPS.
It was removed when hosting moved to Cloudflare; it is in git history if Beget
ever comes back.

## Sections (in DOM order)

`#top` header → hero → `#about` (О штабе) → `#menu` (Меню + Другие напитки)
→ `#life` (Жизнь штаба) → `#schedule` (includes `#guests` sub-anchor) →
footer (`#contacts`, `#social`).

Nav links and footer nav both point at these same anchor IDs — keep them in
sync if sections are renamed or reordered.

`#schedule`'s `.info-grid` is two columns at every width above 640px: «График
работы» and «Гостям города» (which carries the `#guests` anchor) share the
first row, and the much shorter «Как перебраться» card takes
`.info-card--wide` to span the full width beneath them. Adding a fourth card
means deciding where the span sits — the class is on the element, not a
`:nth-child` rule, so move it deliberately.

The hero is a flex **column** with two children: `.hero-top` (the address
badge, sitting directly under the sticky header) and `.hero-inner` (headline,
lead, buttons). Both children carry the same `max-width`/`padding`, so the
badge stays left-aligned with the `h1`; change one and change the other.

`.hero-inner` centres itself via `margin: auto` — except above 861px, where a
desktop-only block re-does the vertical rhythm so the buttons sit midway
between the lead text and the hero's bottom edge. There, `.hero-inner` grows
to fill the hero (`flex: 1`) and three `auto` margins — above the `h1`, above
and below `.hero-actions` — split the leftover space into equal thirds, which
is exactly the centring condition. It re-solves at any viewport height, so
don't replace it with fixed pixel offsets. That block also zeroes the hero's
`padding-bottom`: auto margins divide the *content* box, so leaving the
padding on would push the buttons half of it too high. Below 861px none of
this applies and the hero stays a plain block.

## Design tokens (see `:root` in `style.css`)

- Palette: near-white background (`--cream` — the name is historical, the
  value is now `#fdfbf8`), soft cream alternating sections (`--cream-alt`),
  true-white cards (`--paper`), espresso text (`--ink`), and a coffee-brown
  accent. Dark roast hero/footer (`--dark`).
- The accent runs as a three-step brown ramp, picked by the background it sits
  on. Getting this wrong is the easy mistake — the primary brown vanishes on
  the footer, and the light one is unreadable on white:
  - `--accent` / `--accent-dark` — coffee brown, for buttons and links on
    light backgrounds.
  - `--accent-mid` — lighter brown, for small accents on light backgrounds
    (section eyebrow labels, the feature icons). Its value is pinned at
    4.69:1 against `--cream-alt`, the tightest pairing on the page; darken it
    rather than lighten it if you change it.
  - `--accent-light` — light brown, the only one legible on `--dark`
    (footer icons and links, hero badge icon).
- `--grain`: an inline-SVG noise texture applied as an extra *background
  layer* on `body` and `.section-alt` (never as an overlay element, which
  would risk painting over cards). Cards stay clean white. Alpha is baked into
  the SVG (`opacity` on its `<rect>`), so adjust it there, not in CSS.
- Type: `Playfair Display` (serif, headings) + `Inter` (sans, body), loaded
  from Google Fonts in `<head>`.
- Shape language: pill buttons/nav (`--radius-full`), rounded cards
  (`--radius-lg` / `--radius-md`).

## Content is not boxed

Entries in the About, Menu and Жизнь штаба sections (`.feature-card`,
`.menu-card`, `.life-card` — the class names are historical) sit **directly on
the page**: no background, border, radius, padding or hover lift. The photo,
the heading and the whitespace do the grouping. This was a deliberate move away
from the "every block of content in its own rounded rectangle" look.

Exactly one thing in `<main>` is still framed: `.info-card--panel`, the
opening-hours table. It keeps a box because it is the only tabular content on
the page and people scan for it — and being the *only* boxed element is what
makes it read as emphasis. Its two siblings in `#schedule` («Гостям города»,
«Как перебраться») are plain text. **Don't re-add box styling to the shared
`.info-card` selector** — that would silently re-box those two and destroy the
distinction.

Because there is no card padding any more, the grid gaps are load-bearing:
they are the only thing separating entries. `.feature-grid` 34/54px,
`.menu-grid` 28/46px, `.life-grid` 40/54px, `.info-grid` 44px. Shrinking them
back toward the old 20px brings back the cramped look the boxes were hiding.

## Image placeholders

Every spot that should eventually carry a real photo already has a real
`<img>` tag pointing at `images/placeholder.svg`, wrapped in a `.img-frame`
div that fixes the aspect ratio (so swapping images later causes no layout
shift):

- `.img-frame--square` — feature/menu/drink card thumbnails (1:1)
- `.img-frame--about` — About section photo (4:3)
- `.img-frame--wide` — Жизнь штаба cards (16:10)
- `.hero-bg` — full-bleed hero background image (object-fit: cover, sits
  behind the dark `.hero-overlay` gradient so hero text stays legible
  regardless of the photo)

To add a real photo: replace the `src` (and ideally the filename) on the
relevant `<img>`, keep the existing `alt` text (already written to describe
what should be there), and leave the wrapping `.img-frame*` class alone.

## Responsive behavior

Breakpoints: 960px (grids go 4/3-col → 2-col, About image+text stacks),
860px (nav links move into a dropdown panel opened by the ☰ toggle; the
header stays one compact row with brand + «Соцсети» + toggle), 640px
(everything single-column, hero padding tightens), 480px (further
spacing/type tightening, badge wraps, brand subtitle hidden to keep the
header compact).

Both header panels — the ☰ nav dropdown and the «Соцсети» popover — are
absolutely positioned, so opening either never changes the sticky header's
height. Opening one closes the other. The header's height is still measured
at runtime (`--header-h`) because it differs between the two layouts.

On mobile the row reads brand · «Соцсети» · ☰, with «Соцсети» pushed right by
`margin-left: auto` so it sits directly beside the toggle. The ☰ is three bare
lines — deliberately **not** a circular/bordered button; that was asked for
explicitly, so don't reintroduce a border or background on it.

Checked for text overflow and clipping down to 320px — keep it that way when
adding copy.

The header hides on scroll-down and reappears on any scroll-up (`.is-hidden`
on `.site-header`, driven by `script.js`). It always rests visible within the
top 220px, so its state above the hero is unchanged. It is held visible while
either header panel is open, and for 900ms after an in-page link is clicked so
the smooth-scroll travel doesn't hide it mid-jump.

`script.js` is shared by `index.html` and `privacy.html`, which carry
different markup. It bails out early if there is no `.site-header`, and every
reference to an element the legal pages lack (`#toTop`, `#main-nav`,
`#socialBtn`, `#navToggle`) is guarded — one unguarded null throws on
`DOMContentLoaded` and silently kills every other behaviour in the file.

The back-to-top button rides the same gesture as the header: it appears only
while scrolling **up** and past 480px, and hides again on any downward scroll.

Focus rings: the stylesheet sets `:focus:not(:focus-visible) { outline: none }`
so mouse clicks leave no ring, plus a deliberate `:focus-visible` ring in
`--accent-mid` (`--accent-light` on the dark hero and footer). Don't drop the
`:focus-visible` half — before this the site had no focus styles at all and
keyboard users relied on the browser default.

Two scroll traps worth knowing about, both caused by `scroll-padding-top`:

- Every `focus()` call in `script.js` passes `{ preventScroll: true }`. Without
  it, focusing a link inside a header panel makes the browser scroll the page
  up by the scroll-padding to "reveal" it.
- `id="top"` sits on the sticky header, so once the header is stuck the browser
  treats it as already in view and an `href="#top"` jump only scrolls by the
  scroll-padding instead of returning to the top. Both logo links are therefore
  driven by JS (`scrollToTop`); the `href` stays as a no-JS fallback.

**`overflow-x` on `html, body` must be `clip`, never `hidden`.** `hidden`
forces the used value of `overflow-y` to `auto`, which makes html/body scroll
containers and silently disables `position: sticky` on the header — the header
then scrolls away with the page and the hide/reveal behaviour above does
nothing. The rule keeps a `hidden` declaration first as a Safari < 16 fallback,
with `clip` inside `@supports`. This is a deliberate safeguard against the
absolutely-positioned hero background causing horizontal scroll on mobile —
don't remove it without checking mobile widths again.
