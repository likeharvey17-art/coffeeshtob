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

Both `repo` and `app_id` in `admin/config.yml` are filled in and working. The
application is group-owned under `webtyr-group1` (note: that group's *display
name* is "webtyr-group", the same as an unrelated second group — the path is
what disambiguates them).

The `app_id` is a public client identifier, not a secret: PKCE has no client
secret, which is why `config.yml` can be served to browsers as-is. If the app
is ever recreated: GitLab → the group → Settings → Applications → Add new
application (projects have no Applications section — it exists only on groups
and users).

- **Redirect URI** — the full admin URL, currently
  `https://coffeeshtob-site-cc.haknisvouzizn.workers.dev/admin/`. This is the
  one host-dependent value. GitLab accepts several URIs, one per line, so
  register any new domain here *before* switching to it and no code changes.
  Cloudflare preview URLs are wildcards and can never match, so the CMS only
  logs in from production — that is expected.
- **Confidential** — must be **unchecked**. PKCE has no client secret, and
  leaving this on breaks the flow.
- **Scopes** — `api`.

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

**Cloudflare Worker with static assets** (project `coffeeshtob-site-cc`), not
a Pages project — Cloudflare now steers new static sites to Workers. Connected
directly to this GitLab repo; every push to `main` triggers a deploy. There is
no build step, no CI config and no build command; Cloudflare serves the repo
root as static assets.

Measured: a push went live in ~40s. Verified end to end on the deployed site —
`/`, `/admin/` and `/privacy.html` all resolve (Worker asset serving handles
directory paths, so `/admin/` correctly maps to `admin/index.html`).

Live URL: `https://coffeeshtob-site-cc.haknisvouzizn.workers.dev`. The
`workers.dev` *Preview* URL is deliberately left disabled: it is a wildcard,
so it could never match a GitLab redirect URI, and enabling it would make every
deployed version publicly reachable.

That direct connection is what makes the CMS work end to end: a content edit in
`/admin/` is a commit to this repo, which Cloudflare picks up and publishes. If
edits stop appearing on the live site, check the Cloudflare deployment log
first.

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

## Icons and SEO

`favicon.svg` is the source of truth for the brand mark at small sizes. It is
**not** the header's cup: that one is stroked at width 2 and turns to mush in a
16px tab, so the favicon redraws the same cup in solid silhouette with nothing
thinner than 5/64 of the canvas. The saucer earns its place — without it the cup
reads as an anonymous white blob at tab size — and the gap above it is
deliberate, because closing it makes the two shapes merge when downscaled.

The raster icons (`favicon.ico`, `apple-touch-icon.png`, `icon-192/512.png`)
are generated from the **same geometry** with Pillow, not traced by hand; if the
SVG changes they must be regenerated to match. Two things that bit during
generation and will bite again:

- The working canvas must be **several times** the output size. Drawing at 1:1
  produces hard jaggies — an early `icon-512.png` came out with 3 distinct
  colours because the supersample happened to land exactly on 512 and the
  downscale became a no-op.
- `apple-touch-icon.png` is deliberately **square with no transparency**. iOS
  applies its own corner mask, so baked-in rounding leaves dark wedges.

`og-image.jpg` (1200×630) is generated too, in Georgia — which is the CSS
fallback for Playfair Display, so the social card matches the site.

**Cloudflare trims `.html` from asset URLs.** `/privacy.html` returns a 307 to
`/privacy`, and `/index.html` a 307 to `/`. Every internal link, `canonical`,
`og:url` and sitemap entry therefore uses the **extensionless** form — a
canonical or sitemap URL that redirects is one search engines disregard, and it
put a needless redirect on every footer click. Adding a page means linking it
without the extension too.

`404.html` exists and is styled, and carries `noindex, follow`. Whether
Cloudflare actually serves it depends on the Worker's `not_found_handling`
setting; if a missing path returns a bare 404 body, that is the setting, not the
file.

`llms.txt` is a plain-language summary for assistants and crawlers — hours,
address, phone, what the place does. Keep it in step with the page; it is the
one file that repeats content rather than linking to it.

Absolute URLs live in four places: `<link rel="canonical">`, the `og:`/`twitter:`
tags and the JSON-LD in `index.html`, the same in `privacy.html`, plus
`robots.txt` and `sitemap.xml`. **They all point at the current production host
and must be changed together** if a custom domain is added.

The JSON-LD is `CafeOrCoffeeShop` and contains only facts that are on the page —
no invented geo coordinates, no made-up `priceRange`. Its
`openingHoursSpecification` mirrors `#schedule`; change both together or the
rich result will contradict the page.

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

The header hides on scroll-down and reappears on scroll-up, but by **two
different mechanisms**, and they must not be merged:

- **Desktop** toggles `.is-hidden` and lets a 0.28s CSS transition play.
- **Mobile** ignores the class and drives an inline `translateY` straight from
  the scroll delta, one-to-one with the finger, clamped to `hiddenDistance()`.
  There is deliberately **no transition below 860px** — a timed animation can
  only be slow (the bar lingers while the page moves under it) or abrupt (it
  snaps); both were tried and rejected. Tracking the scroll has no duration to
  get wrong.

`hiddenDistance()` is measured off the element (`offsetHeight + 16`) rather
than written as `-100%`. A percentage resolves against the element's own box,
and in some in-app webviews — Telegram's among them — the sticky box and the
visual viewport disagree, which left a sliver of the bar stranded on screen.

`hideAfter()` — how far down the page the header stays pinned — is 220px on
desktop, 12px on mobile. It rests visible above the hero either way.

**The pinned check runs before the small-movement bail, and must stay there.**
Pinning is a fact about where the page *is*, not about how far it just
travelled. With the order reversed, a jump straight to the top (the back-to-top
button, a hash link) or a slow sub-threshold drift never reset the header and
left it stranded off-screen at `y=0`. Both cases are regression-tested by
hand: jump-to-top and a 2px-per-frame drift must both land at `transform: 0`. It is held visible while
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
- Focus is only moved into a panel for **keyboard** activation
  (`cameFromKeyboard`, i.e. `event.detail === 0`). Focusing a link after a
  *pointer* click makes the browser paint its `:focus-visible` ring, which drew
  a box around the first item every time «Соцсети» was opened with the mouse.
  Pointer users lose nothing: each panel follows its trigger in the DOM, so Tab
  still walks straight into it.
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
