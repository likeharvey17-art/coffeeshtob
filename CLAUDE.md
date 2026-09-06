# Кофештаб «Романов на Волге» — business card site

Single-page marketing site for a coffee hub ("Кофештаб") in a historic merchant
house on the Volga embankment in Romanov (Tutaev, left bank), Yaroslavl
region, Russia. Content is entirely in Russian.

## Current state

**Live on Beget at `https://coffeeshtob.ru`, and the client currently cannot
edit it.** Much of what follows in this file was written when the site was on
Cloudflare with a working Decap CMS. Both of those are gone. Where a section
below describes Cloudflare or GitLab, read it as history unless it says
otherwise.

What changed, in one pass:

- **Cloudflare is gone.** The site was unreachable from Russian IPs — RKN
  throttles Cloudflare. Now on Beget shared hosting (Apache behind nginx, PHP,
  no database). See "Moving to Russian hosting".
- **GitLab is gone.** The owner's account (`likeharvey17-art`) was blocked
  without explanation. The repo now lives at
  `https://github.com/likeharvey17-art/coffeeshtob` (private). The old GitLab
  URL is kept as a `gitlab` remote for reference; it no longer authenticates.
- **The CMS is dead as a result.** Decap authenticates through gitlab.com, so
  `/admin/` cannot log in. Moving the repo to GitHub does not fix it — Decap's
  GitHub backend needs an OAuth broker holding a client secret, which is the
  whole reason GitLab PKCE was chosen originally.
- **Deploys are automated again**, now by GitHub Actions rather than GitLab CI —
  `.github/workflows/deploy.yml` for production, `deploy-staging.yml` for the
  Grav install. The manual zip-and-extract through Beget's File Manager is
  history. See "Deploy" below, which is worth reading before touching either
  workflow: this host breaks FTP in two specific ways.

Known open items, in rough priority order:

1. **The client has no way to edit their own site.** This is the live problem
   and the reason for the Grav decision below. The Grav theme and its Russian
   editing form are written; **rendering them through Grav is not yet
   confirmed** — no PHP on the dev machine, so Twig cannot be executed locally
   and staging is the first real test. After that comes the cutover: pointing
   coffeeshtob.ru at the Grav install and retiring `render.js`,
   `content/home.json` and `admin/`.
2. **Three menu items still show `images/placeholder.svg`** — waiting on photos.
3. **`admin/` is 4.9 MB of non-functional Decap** still being deployed. It goes
   when Grav lands; deleting it earlier is harmless if the client is told.
4. **`og-image.jpg` is on the server by hand, not by deploy.** It is excluded
   from the mirror (which is what stops `--delete` removing it) and from the
   manifest check. Fold it back in once the mirror carries the working FTP
   transport — see "Deploy".

Resolved by the move, and no longer open: the Russia loading failure, the
Cloudflare-only `404.html`, and the GitLab pipeline emails.

## Decision: the CMS is being replaced with Grav

Chosen deliberately, with the alternatives ruled out on record:

- **Every git-based CMS is disqualified.** Decap *and* Sveltia both require the
  editor to hold a GitHub or GitLab account. The requirement is that the client
  logs in with a password the developer hands them, which no git-backed CMS can
  do.
- **Every hosted CMS is disqualified** (Sitepins, Tina Cloud, Contentful,
  Storyblok). They satisfy the password requirement but reintroduce a foreign
  service that can throttle, block or ban — which is precisely how both
  Cloudflare and GitLab were lost inside one week. For a Russian client base,
  treat that as fatal rather than as a trade-off.
- **Kirby is out on cost** ($105/site) despite the best panel.
- **A bespoke PHP admin was seriously considered and rejected.** It needs no
  rewrite and reuses `content/home.json` as-is, but it means hand-written
  authentication maintained across a dozen client sites forever, with no
  upstream security patches and a bus factor of one.

**Grav** wins on: free, flat-file (no database), PHP 7.3.6+, installs and
updates over FTP with no SSH, its own user accounts, community Russian
localisation, and — decisively — someone else patching the auth. It renders
server-side, which deletes `render.js` and closes the old Yandex/JS-indexing
concern as a side effect. Twig templates are *your* HTML, so Grav imposes no
design: `style.css` and `script.js` carry over intact.

**Sequencing matters.** Build Grav on a staging subdomain, port the design,
verify, and only then swap. Do not rebuild in place on the live site.

**Grav caches pages in the browser for a week by default.** Measured on staging:
the HTML comes back with `Cache-Control: max-age=604800` and a matching
`Expires`, alongside `ETag` and `Vary: Accept-Encoding` — which is Grav's
`system.pages` header block, not nginx. The effect is that an edit saved in the
admin is live on the server immediately but invisible in a browser that has
already loaded the page, for up to seven days. That looks exactly like "saving
is broken" and is not. **Set Configuration → System → Pages → Expires to 0
before handing the site to the client**, or the first thing they report is that
their edits do nothing. Verify by re-fetching and checking the header changed;
if it does not, the value is coming from nginx and needs Beget's side instead.

### The Grav theme — where the port has got to

Staging is `http://test.tryphopx.beget.tech`, deployed by
`.github/workflows/deploy-staging.yml`. **Production is still the static site**;
nothing below is live yet.

Layout under `grav/`, mirroring Grav's own tree so only *our* files are in the
repo — `system/` and `vendor/` are Grav's 60+ MB and are updated from its admin
panel:

- `user/themes/coffeeshtob/templates/home.html.twig` — the page. Every value
  comes from `page.header.*`; nothing is hardcoded.
- `user/themes/coffeeshtob/templates/partials/{base,header,footer}.html.twig` —
  head, sticky header, footer. `base` sets `<title>`, the OG tags and the
  canonical from `base_url_absolute`, which is why the 14 hardcoded absolute
  URLs of the static site do not exist here.
- `user/themes/coffeeshtob/blueprints/home.yaml` — the client's editing form,
  in Russian, tabbed by section. Without it Grav shows raw YAML front matter,
  which is how a client deletes a colon and takes the site down.
- `user/pages/01.home/home.md` — the content. Generated from
  `content/home.json`, not retyped.
- `default.html.twig` — a safety net, not a real template. Grav picks a template
  from the page filename, so a page named `default.md` (which Grav's stock
  install ships) hard-errors on a theme that only provides `home.html.twig`.
  That is exactly how first activation failed.

**Three files must name the same fields, and nothing complains when they
don't.** Twig renders a missing key as an empty string, so a rename shows up as
a silently blank section on the live site — the identical trap the Decap setup
had across `index.html`, `home.json` and `admin/config.yml`. It is checked
rather than documented: `.github/scripts/check-content-fields.py` compares
template, form and content, and runs before the staging deploy touches the
server. Run it directly after editing any of the three.

**Images are page media**, not theme assets: `home.md` stores a bare filename
which Grav resolves against `user/pages/01.home/`, so the client uploads a photo
in the same place they edit the text. The seed mirrors `images/uploads/` into
that folder rather than committing the same 5.2 MB twice. An empty filename
falls back to `theme://images/placeholder.svg` — which is what the three menu
items still awaiting photos rely on, so don't "fix" the empty values.

**The feature-card icons stay hardcoded and positional.** They are four
different drawings tied to card order, not content. A fifth card renders with no
icon on purpose: the old CMS preserved them only by cloning template nodes by
index, which is the fragile mechanism this replaces.

Two things the old CMS could not reach are fields now: the hero address badge,
and the second paragraph of each Жизнь штаба card.

**Not yet carried across: the JSON-LD.** `index.html` has a `CafeOrCoffeeShop`
block whose `openingHoursSpecification` mirrors `#schedule`. It cannot be
derived from the `hours` list, whose labels are free text («БУДНИ (ПН–ПТ)»), and
guessing a day-of-week mapping from Russian prose is exactly the kind of
invention this project avoids. Either add explicit day fields to the blueprint
or carry the block over verbatim and accept that editing hours desyncs it —
decide before cutover, not after.

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
- `render.js` — applies `content/home.json` to the page, then re-applies the
  URL hash once fonts and copy have settled (the browser's own anchor jump
  happens before that and lands in the wrong place). Falls back silently to the
  static markup if the JSON is missing. Three attributes drive it:
  - `data-cms-field="name"` — a single value. Text goes in as text; an `<img>`
    gets its `src` (via `toRelative`).
  - `data-cms-list="name"` — a repeatable container. **Its existing children are
    the template**, captured once and rebuilt from the array, so the static HTML
    stays the meaningful no-JS fallback instead of an empty div awaiting fetch.
    Templates are reused *by index*, which is what preserves the four different
    inline SVG icons on the feature cards; a fifth item reuses the first icon.
  - `data-cms-item="key"` — a slot inside a list child. Slots are iterated from
    the *template*, not from the item's keys: a key the item lacks must clear
    the slot, or the value cloned from the template stays and the item silently
    inherits another item's price.

  After rendering it fires a `cms:rendered` event, which `script.js` listens for
  to observe the rebuilt cards (see the scroll-reveal note under Responsive
  behavior). Without it those cards keep `.reveal`'s `opacity: 0` forever.
- `content/home.json` — the CMS-editable copy: **the whole page**, not just the
  hero. Section headings and subtitles, all four feature cards, the menu and
  drinks lists, both Жизнь штаба blocks, the opening hours, the two info blocks
  and the footer contact details. Field names must match the `data-cms-*`
  attributes in `index.html` **and** the field names in `admin/config.yml` —
  rename one of the three and the value silently stops being applied, because
  `render.js` skips anything it cannot find. Image paths here are
  **root-absolute** (`/images/…`), and `admin/config.yml`'s `public_folder`
  must match: the CMS preview runs at `/admin/`, so a relative path would
  resolve to `/admin/images/…` and 404. The live site does *not* want them
  absolute — that would assume a domain root — so `render.js` strips the
  leading slash when injecting them (`toRelative`). Net effect: identical at a
  domain root, and still correct if the site is ever served from a
  subdirectory. Verified by serving the site under `/my-repo/`.

  Field *names* are the stable identifiers; the visible headings are content and
  the owner changes them. `drinks_title` currently reads «Также в штабе», not
  «Другие напитки» — the label in `admin/config.yml` still says the latter, which
  is cosmetic but worth knowing when matching a heading on the page to a field.
  Refer to sections by list name (`menu_items`, `drinks_items`) rather than by
  whatever the heading says today.
- `admin/` — Decap CMS (`config.yml` + loader page), **GitLab backend with
  PKCE**. Chosen for host-portability: PKCE runs entirely in the browser, so
  the CMS needs no server component and nothing ties it to a hosting provider.
  The site can move between Cloudflare Pages, Vercel, a VPS or anywhere else
  and the CMS keeps working. There is deliberately **no** Netlify Identity
  widget anywhere — the public page stays cookie-free, as `privacy.html`
  states. `local_backend: true` only applies on localhost: run
  `npx decap-server` to edit locally, which bypasses auth entirely.

  **Decap itself is vendored** at `admin/vendor/decap-cms-3.16.0.js` (~4.8 MB,
  the one large binary in the repo) rather than loaded from `unpkg.com`. The
  editor is in Russia and unpkg is unreliable there; a CDN that does not answer
  leaves the admin page blank forever with nothing to explain why. The version
  is pinned in the filename, not by a `^3.0.0` range, so an editor can never be
  handed a new major version by surprise — to upgrade, add the new file and
  change the one `<script src>`.

  **This does not make the CMS work offline from gitlab.com.** The PKCE flow and
  every save still talk to gitlab.com, so if GitLab is throttled or blocked for
  the editor, `/admin/` still fails — just later in the flow, at login rather
  than at load. That is the open question behind the hosting migration below.

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

- **Redirect URI** — the full admin URL. This is the one host-dependent value,
  and it is the thing that breaks when the domain changes. GitLab accepts
  several URIs, one per line, so **keep both** registered:

  ```
  https://coffeeshtob.ru/admin/
  https://coffeeshtob-site-cc.haknisvouzizn.workers.dev/admin/
  ```

  Keeping the old one costs nothing and leaves a working way into the CMS if
  the domain is ever misconfigured. Register a new domain here *before*
  switching to it and no code changes are needed.

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
  what a visitor is actually affected by, which since the fonts were self-hosted
  is just server access logs. If the operator's legal details (юрлицо/ИП, ОГРН,
  ИНН, e-mail) are ever added, they belong here; they were left out rather than
  invented.

  Currently ~126 words: a lead callout plus three `<h2>` sections — Записи
  сервера, Ссылки на другие сайты, Если есть вопросы. It was rewritten twice to
  get that short, the second time explicitly to sound less machine-written, so
  **don't pad it back out** with the usual boilerplate headings ("Правовые
  основания", "Сроки хранения"). Short is the point.

  A fourth section, «Шрифты», was deleted when the fonts stopped coming from
  Google: the page now makes **no third-party requests at all**, so there is
  nothing left to disclose but the server logs. Verified — `privacy.html`
  contains zero external links. If a third party is ever added back, this
  section comes back with it.

  **The opening callout is load-bearing beyond this page.** It asserts, in
  Russian, that the site collects nothing, has no forms, no registration and no
  visit counters, and sets no cookies. Adding *any* of those — a form, Yandex
  Metrica, Google Analytics, a chat widget, a consent banner — makes this page
  factually false, and it must be rewritten in the same commit. See "Things
  deliberately not built".
- `images/placeholder.svg` — neutral blank placeholder graphic (cream
  background + line-art frame icon) used everywhere a real photo is pending

## Deployment

**Cloudflare Worker with static assets** (project `coffeeshtob-site-cc`), not
a Pages project — Cloudflare now steers new static sites to Workers. Connected
directly to this GitLab repo; every push to `main` triggers a deploy. There is
no build step, no CI config and no build command; Cloudflare serves the repo
root as static assets.

Measured: a push went live in ~40s. Verified end to end on the deployed site —
`/`, `/admin/` and `/privacy` all resolve (Worker asset serving handles
directory paths, so `/admin/` correctly maps to `admin/index.html`).
`/privacy.html` also works but 307s to `/privacy`; see the extensionless-URL note
under Icons and SEO.

Live URL: `https://coffeeshtob.ru`, with
`https://coffeeshtob-site-cc.haknisvouzizn.workers.dev` still serving the same
Worker underneath — the `workers.dev` host does not go away when a custom domain
is attached, so both resolve. Only the custom domain is advertised: every
canonical, `og:url`, sitemap entry and `llms.txt` link points at `coffeeshtob.ru`
so search engines index one host rather than two copies of the site. The
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

**GitLab CI is deliberately disabled**, and `.gitlab-ci.yml` exists to keep it
that way rather than to run anything. Deleting it does not give you "no CI" — it
gives you Auto DevOps, which tries to build an application, finds static files
with no `package.json` or `Dockerfile`, fails, and mails a failed-pipeline
notice on every push. That includes **every CMS edit**, since a content save is
a push. `workflow: rules: when: never` stops a pipeline being created at all.

**The `no-op` job must stay visible — never give it `rules: when: never`.**
GitLab requires a config to declare at least one job not excluded by its own
rules; an unreachable job makes the config invalid, and an invalid config *is* a
failed pipeline. That exact mistake was made here and produced the very emails
the file was added to stop. The `workflow` rule is what prevents it running.

**Status: not confirmed fixed.** The current version of the file landed in
`26dc1c1`, and the owner reported another failed-pipeline email *after* that. It
was never established whether that email belonged to a commit made before the
fix (GitLab mails asynchronously, and the CMS commits carry author timestamps
from a different timezone, so ordering by the log is unreliable) or whether the
config is still wrong. **The actual pipeline error text was never seen** — ask
for it before theorising, it is on the pipeline page in GitLab.

**Moot as of the move to GitHub — the whole of the rest of this section is
GitLab archaeology, kept only in case that account is ever recovered. Deploys
run on GitHub Actions now; see "Deploy" under "Moving to Russian hosting".**

**That advice was REVERSED while GitLab was still in use, and the project
setting had to stay ON.** Turning
CI/CD off was the right call while nothing needed to run — but on Beget, CI *is*
the deploy mechanism, and a disabled CI/CD makes `Settings → CI/CD` vanish from
the menu entirely, so there is nowhere to add the FTP variables and no
`Build → Pipelines` to run. That is exactly how it was found: the Settings menu
had no CI/CD entry at all.

Re-enable at **Settings → General → Visibility, project features, permissions →
CI/CD**. Enabling it does not resurrect the old failed-pipeline emails: the
`workflow` rule in `.gitlab-ci.yml` creates no pipeline while `$FTP_HOST` is
unset, which is also why CI/CD can be switched on before the variables exist
without anything firing in between.

An earlier version of that file deployed to Beget over FTPS. It was replaced
when hosting moved to Cloudflare; the FTP version is in git history if Beget
ever comes back.

## Moving to Russian hosting

**The site is unreachable from Russian IPs on Cloudflare.** Self-hosting the
fonts (which was a genuine, separate cause) did not fix it — the client retested
and reported it still does not load at all. RKN throttling of Cloudflare is the
remaining cause, and the answer is to leave Cloudflare.

**Chosen host: Beget.** Free tier to start, cheap multi-site paid plans as more
client sites are added, Apache + PHP + `.htaccess` + free Let's Encrypt. It is
also where this project deployed *before* Cloudflare, so the FTPS job already
existed in history.

**The domain does not move.** `coffeeshtob.ru` is on reg.ru nameservers with A
records pointing at Cloudflare, so the cutover is an A-record change at reg.ru.
Nothing else follows from it: none of the 14 absolute URLs change, and the GitLab
Redirect URI stays valid. Keep the `workers.dev` redirect URI registered anyway.

### `.htaccess` — and why it is not optional

Cloudflare did two things for free that Apache does not, and both are load-bearing:

1. **It trimmed `.html`.** Every canonical, `og:url`, sitemap `<loc>` and
   internal link in this repo is extensionless. Without the rewrite block,
   `/privacy` is a hard 404 and takes the canonical URL and the sitemap with it.
2. It is why `404.html` never worked. Apache serves it via `ErrorDocument`, so
   the move closes that open item.

**The file was tested against a real Apache 2.4.66**, not written from memory —
a throwaway `httpd` on port 8899 with a config in the scratchpad, serving a copy
of the site (macOS blocks Apache's user from reading `~/Desktop`, so it cannot
serve the working tree directly). Three real bugs surfaced that review would not
have caught:

- **`AddOutputFilterByType` is `mod_filter`, not `mod_deflate`.** Guarding the
  compression block on `<IfModule mod_deflate.c>` passes on a server with
  deflate but no filter, and the unknown directive then throws
  `Invalid command` — which in `.htaccess` is **a 500 on every page**, not a
  skipped feature. This is the one that would have taken the site down at
  cutover.
- **Apache serves `.js` as `text/javascript`**, not `application/javascript`.
  Listing only the latter left `script.js` and `render.js` with no expiry *and*
  no compression. Both types are now named.
- **`.webmanifest` is not in Apache's default mime.types** and was served with
  no `Content-Type` at all. The needed types are declared explicitly.

Verified end to end: `/` and `/privacy` 200; `/privacy.html` 301s to `/privacy`
and `/index.html` to `/` (not to `/index`); `/admin/` and `/style.css` are not
hijacked by the rewrite; an unknown path returns the styled `404.html` with its
`noindex`; `CLAUDE.md` and `.gitlab-ci.yml` return 403; a `.php` dropped in
`images/uploads/` returns 403.

**Beget runs nginx in front of Apache, and that makes half this file inert.**
`Server: nginx-reuseport/1.21.1` on every response. nginx serves static files
itself and never consults `.htaccess`, so the `mod_expires` and `AddType` blocks
below **do nothing on Beget** — measured: `style.css` came back `max-age=604800`
where the file asks for 1 hour, and `.js` is served as
`application/x-javascript`, a type neither the compression nor the expiry list
names. The rewrites, `ErrorDocument` and `FilesMatch` denials *do* work, because
those run in Apache. Keep the cache block anyway: it is correct, it costs
nothing, and it applies on any host that doesn't front Apache with nginx.

**The consequence is that asset caching is out of our hands, so the URLs carry a
version instead.** `style.css`, `script.js` and `render.js` are referenced as
`?v=YYYYMMDD` in all three HTML files (7 references). With nginx caching CSS for
7 days and no content hash in any filename, a deploy would otherwise leave
returning visitors on a stale stylesheet for a week. **Bump the stamp whenever
those files change** — it is the only thing that guarantees a code change
reaches anyone who has already visited. Content edits are unaffected:
`content/home.json` is served `max-age=0`.

**Apache does not know it is behind TLS.** nginx terminates HTTPS, so
`%{HTTPS}` is never `on` inside `.htaccess`. Two consequences, both already
handled: the force-HTTPS rule tests `X-Forwarded-Proto` as well, and the
`.html`-to-clean-URL redirects name `https://` explicitly. Written as `/%1`,
Apache expanded them to `Location: http://…` — an HTTPS request for
`/privacy.html` really did come back pointing at plaintext, which the force rule
then bounced back, costing two redirects and a plaintext hop per link. **Any new
`R=301` rule added here must name the scheme for the same reason.**

### Deploy

**GitHub Actions, two workflows**, both `lftp` FTPS mirrors:

- `.github/workflows/deploy.yml` — production, on every push to `main`. Four
  repository secrets: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `REMOTE_DIR`.
- `.github/workflows/deploy-staging.yml` — the Grav install, on pushes touching
  `grav/**`. One extra secret, `STAGING_DIR`. It deploys **only the theme**;
  Grav's own `system/` and `vendor/` are 60+ MB and are updated through Grav's
  admin panel, which is why the repo mirrors Grav's layout under a `grav/`
  prefix rather than at the root.

Production assembles its upload set from `git ls-files` minus
`CLAUDE.md`, `.gitignore`, `.github/`, `.claude/` and **`grav/`**. That last
exclusion is load-bearing and was learned by breaking it: adding `grav/` to the
repo without excluding it here shipped the whole Grav theme to coffeeshtob.ru,
where `/grav/user/themes/coffeeshtob/css/style.css` served a public 200. **Any
directory added for a different deploy target must be excluded here in the same
commit.**

It also stamps the `?v=` asset version from the commit SHA, so nobody has to
remember to bump it, and asserts the count afterwards — an empty `GITHUB_SHA`
would otherwise write `?v=` seven times and still pass a naive check.

#### Beget breaks FTP in two ways, and both cost days

**1. `lftp`'s exit code is meaningless on this host.** It returns 1 for things
that are not failures. The first was `SITE CHMOD`, which Beget refuses outright:

```
chmod: Access failed: 550 SITE CHMOD command failed. (./inter-latin.woff2)
```

Every file transferred, the site updated, and the job went red anyway — through
*three separate investigations* into a file that was never broken. `--no-perms`
stops lftp attempting chmod at all, and even then the exit code stayed
unreliable. **So neither workflow gates on it.** Production gates on a manifest
check that lists the server and compares it to the upload set; staging gates on
fetching the page and finding the site's own markup. Both are measurements of
the outcome rather than opinions about the transfer.

**2. Some data connections are dropped mid-transfer.** One file
(`fonts/inter-latin.woff2`, and earlier `og-image.jpg`) failed on every run
while its neighbours went through — deterministic, not flaky, and independent of
size, type and directory. lftp reported only `Fatal error: max-retries
exceeded`, which produced **four wrong theories in a row**: a parallel-transfer
race, something about the file itself, a malformed repair command, and a
per-account connection limit. Each was a guess at a message that named no cause.

Adding `debug 3` to print the FTP dialogue ended it in one run:

```
<--- 250 Directory successfully changed.
<--- 426 Failure reading network stream.
```

`426` is the data connection dying, not a permission or a refusal — and `du -hs`
in the same run reported 11M, retiring the quota theory too.

**The fix is the repair pass, and the cause is still not established.** Every
mirror is followed by `.github/scripts/ftp-repair.sh`, which lists what is
actually on the server, retries anything missing over a ladder of transports —
plaintext data channel, encrypted, active mode, passive without EPSV — and
judges each attempt by whether the file is there afterwards. It reports the
winning rung to the run summary.

Two runs, and they disagree about why:

- with the mirror on an **encrypted** data channel, the encrypted rung failed
  and the **plaintext** rung succeeded — which looked conclusive, and the
  mirrors were switched to plaintext;
- with the mirror on **plaintext**, the mirror still lost the file and the
  repair's *first* rung — the identical setting — landed it.

So encryption is not the discriminator. The remaining difference is that the
repair issues a single `put` on a fresh connection while the mirror is a
long-lived session that has already moved dozens of files. **That is an
observation, not a conclusion** — this project has already spent days on
confident explanations of this exact symptom, and two runs do not name a cause.
Plaintext is kept because it costs nothing (the control connection stays TLS, so
the password is never in the clear, and the bytes are about to be public), not
because it was shown to be the fix. What is established: the repair lands the
file every time, and the URL checks prove the outcome rather than trusting it.

**The lesson, twice over: get the server to say why.** Both of these burned days
on theories while one flag would have printed the answer.

Two cautions for whoever edits that step:

- Each rung **deletes the remote file before writing it**. Deliberate — a
  half-written remote file defeats every retry — but it must never point at a
  file whose only copy lives on the server. `og-image.jpg` is exactly that
  today.
- `dirname` of a root-level file is `.`, and `put -O '$REMOTE_DIR/.'` is
  rejected by lftp. That is why the one file that ever went missing at the root
  was also the one the repair could not fix.

#### `--delete`

`mirror --delete` makes the server match the repo exactly, which is what stops
stale assets accumulating. Three things are excluded from it, all for real
reasons:

- `cgi-bin/` — Beget creates it in every docroot; it is not in the repo.
- `.well-known/` — where Let's Encrypt writes its ACME challenge. Wiping it
  mid-renewal breaks certificate renewal every 90 days, and the failure would
  look like nothing to do with deploys.
- `og-image.jpg` — see above.

**When Grav goes live and content is edited on the server rather than committed,
`--delete` will destroy the client's work on the next push.** Add `--exclude
user/` (or whatever holds pages and uploads) at that point, or drop `--delete`.
The staging workflow already handles this: its `--delete` is scoped to the theme
directory alone, and page content is seeded only on a manual
`workflow_dispatch`, never on a push.

Unlike Cloudflare, the repo root is no longer the web root, and `.htaccess`
denies the private files anyway.

## Sections (in DOM order)

`#top` header → hero → `#about` (О штабе) → `#menu` (`menu_items` + `drinks_items`)
→ `#life` (Жизнь штаба) → `#schedule` (includes `#guests` sub-anchor) →
footer (`#social`, and `#contacts` at the very end).

**`#contacts` is a zero-height marker at the end of the footer, not the footer
element itself.** Anchoring the footer aligned its *top*, and the footer is
taller than a phone screen, so the contact details and social links stayed below
the fold — the link looked like it stopped short. A marker at the document end
makes the browser clamp to the maximum scroll and land at the actual bottom.

This is done with a native anchor rather than JS on purpose: programmatic
`scrollTo`/`scrollIntoView` with `behavior: 'smooth'` proved unreliable — in the
test browser it stalled after a few pixels while native anchor navigation worked
— and an in-app webview is exactly where that sort of thing goes wrong. Keep the
marker zero-height, or it adds a gap under the footer.

Nav links and footer nav both point at these same anchor IDs — keep them in
sync if sections are renamed or reordered.

`#schedule`'s `.info-grid` is two columns at every width above 640px: «График
работы» and «Гостям города» (which carries the `#guests` anchor) share the
first row, and the much shorter «Как перебраться» card takes
`.info-card--wide` to span the full width beneath them. Adding a fourth card
means deciding where the span sits — the class is on the element, not a
`:nth-child` rule, so move it deliberately.

The hero is a flex **column** with two children: `.hero-top` (the address
badge, cleared from the header it sits behind by the hero's top padding) and
`.hero-inner` (headline, lead, buttons). Both children carry the same
`max-width`/`padding`, so the badge stays left-aligned with the `h1`; change one
and change the other.

**The hero fills the viewport at every width: `min-height: 100svh`**, with a
`100vh` line above it as the fallback. This replaced a flat `88vh`, which could
only ever be right at one window height and otherwise left a stray cream strip
under the photo (≈8px at 900px tall, ≈20px at 1000px). Keep it `min-height`,
never `height`, so a short window grows the hero instead of clipping the copy.

**`svh`, never `dvh` — this is the important one.** `dvh` is the *dynamic*
viewport and re-resolves continuously as the mobile address bar collapses and
expands. With it, the hero grew mid-scroll and the cover-fitted photo rescaled
with it: a visible zoom-and-jank on every iOS scroll, made worse by the 4px blur
repainting each frame. That bug was shipped here and reported from a real phone.
`svh` is the *small* viewport — the height with the bar showing — and is constant
for the life of the page, so nothing reflows while scrolling. It is also the safe
end of the range: the hero can never exceed what is on screen, so `#about` stays
hidden at rest whatever the bar is doing. Once the bar collapses mid-scroll a
sliver of the next section shows, which is correct — the reader is already
scrolling.

**The hero starts at the top of the document, behind the sticky header**, via
`margin-top: calc(-1 * var(--header-h))` and a matching
`padding-top: calc(var(--header-h) + 26px)`. Before this there was a band of
cream page background above the photo, which read as a bright strip cutting the
top off the image. Running the photo to the top blends by definition — no solid
colour could, since the photo comes from the CMS and could be anything — and the
white header pill gains contrast from sitting on a darkened photo rather than on
near-white.

**The pull-up and the top padding must change together.** They cancel exactly, so
the content box is identical to when the hero began below the header, which is
why the auto-margin thirds needed no adjustment when this changed. The ≤640px
block carries its own `calc(var(--header-h) + 22px)` for the same reason.

**This makes `--header-h` load-bearing for layout, not just for scroll offsets**
— it now drives the hero's offset, its top padding and the overlay's first
gradient stop. It is measured in `script.js`; the `100px` fallback in `:root` is
what all of that resolves against if JS never runs.

`.hero-inner` grows to fill the hero (`flex: 1`), and three `auto` margins —
above the `h1`, above and below `.hero-actions` — split the leftover space into
equal thirds, which is exactly the condition for the buttons to sit midway
between the lead text and the hero's bottom edge. It re-solves at any viewport
height, so don't replace it with fixed pixel offsets.

This is **unconditional**. It used to be `≥861px` only, from when the phone hero
was content-height and had no space to divide; once the hero grew to fill the
screen that scoping was simply wrong, and while it lasted it left the 641–860px
band as the one place where the buttons sat tucked under the lead with ~300px of
empty photo beneath them. The real condition is "the hero is taller than its
content", which is now every width.

The hero's `padding-bottom` is therefore `0` — auto margins divide the *content*
box, so padding sits outside the calculation and pushes the buttons half of it
too high. **The ≤640px block deliberately re-adds 24px.** That biases the buttons
12px above true centre, which is imperceptible, and it is the only thing between
the last button and the section below on a screen too short to fit the copy —
there the free space is zero, so the bottom third provides no clearance at all.
A 320×568 phone is exactly that case. Don't "tidy" it back to zero.

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
- Type: `Playfair Display` (serif, headings) + `Inter` (sans, body),
  **self-hosted from `fonts/`** — see "Fonts are self-hosted" below.
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
`.life-grid` 40/54px, `.info-grid` 44px. Shrinking them back toward the old
20px brings back the cramped look the boxes were hiding.

## The menu is a list, not a grid

`.menu-list` / `.menu-item` replaced the old four-across photo grid, for both
both list sections in `#menu`. A grid of large square photos is fine at four items
and falls apart past that — a fixed column count leaves orphans on the last row,
and on a phone each item costs a full screen of scrolling. Prices make it a menu
proper, and a menu reads as rows: small square thumbnail, name and price on one
line, description beneath.

**The column count follows the available width, never the item count**:
`repeat(auto-fill, minmax(272px, 1fr))`. Measured against the 1140px container:
1 column up to ~600px, 2 from ~700px, 3 from ~1000px and it stays at 3 — wider
never gets narrower rows. Any number of items lays itself out, and a short last
row reads as a normal list rather than a broken grid. **Nothing needs changing
in CSS when items are added or removed in the CMS.** The 272px minimum is what
buys the two-column tablet case; at 310px a 700px screen fell back to one.

Prices are optional. `render.js` sets `hidden` on any slot whose value is empty,
so an unpriced item shows no stray gap — the same mechanism blanks a missing
description.

## Fonts are self-hosted

**Nothing on the public page is fetched from a third party.** Both families live
in `fonts/` and are declared with `@font-face` at the top of `style.css`. There
is no `fonts.googleapis.com` link anywhere, and there must not be one again.

The reason is the audience. The client reported the site taking "forever" to
load in Russia on both Wi-Fi and mobile, then settling into a stripped-down
version with the wrong fonts. The Google Fonts `<link>` was a **render-blocking
stylesheet in `<head>`**: when Google is slow or blocked, the browser paints
*nothing* until that request times out, and then falls back to system fonts.
That is precisely what was described.

- Both families are **variable** (one file per subset spans every weight used)
  and split by `unicode-range`, so a subset downloads only if the page actually
  contains a character in its range.
- Subsets kept: `latin`, `cyrillic`, `latin-ext` for both, plus `cyrillic-ext`
  for Inter (Playfair does not ship one). Greek and Vietnamese were dropped.
- **`latin-ext` is not optional, despite the name.** The ruble sign `₽` is
  U+20BD, which falls in that subset's range, so the menu prices pull it. It is
  85 KB of Inter for effectively one glyph — subsetting it down with `fonttools`
  is an easy win nobody has taken yet (`fonttools` is not installed here).
- Both are SIL Open Font License, which permits self-hosting and redistribution.

To regenerate — after a weight change, or to refresh the files:

```
python3 - <<'PY'
import re, os, subprocess
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"
REQUIRED, OPTIONAL = ["latin", "cyrillic"], ["latin-ext", "cyrillic-ext"]
FAMILIES = {
  "Inter": "https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap",
  "Playfair Display": "https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600..800&display=swap",
}
def fetch(u, b=False):
    r = subprocess.run(["curl","-sSfL","-A",UA,u], capture_output=True, timeout=60)
    assert r.returncode == 0, r.stderr.decode()[:300]
    return r.stdout if b else r.stdout.decode()
blocks = []
for fam, url in FAMILIES.items():
    found = dict(re.findall(r"/\*\s*([a-z-]+)\s*\*/\s*(@font-face\s*\{.*?\})", fetch(url), re.S))
    for s in REQUIRED: assert s in found, f"{fam} lacks {s}"
    for sub in REQUIRED + [s for s in OPTIONAL if s in found]:
        m = re.search(r"url\((https://fonts\.gstatic\.com/[^)]+\.woff2)\)", found[sub])
        fn = f"{fam.lower().replace(' ','-')}-{sub}.woff2"
        open(os.path.join("fonts", fn), "wb").write(fetch(m.group(1), True))
        assert os.path.getsize(f"fonts/{fn}") > 2000
        blocks.append(found[sub].replace(m.group(1), f"fonts/{fn}").strip())
print("\n\n".join(blocks))   # paste over the @font-face block in style.css
PY
```

Note that Python's `urllib` fails on this machine with a certificate error —
hence `curl`. Assert the subset list and the file sizes; a silent 404 from
gstatic otherwise writes an HTML error page into a `.woff2`.

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

`404.html` exists, is styled and carries `noindex, follow`, but **Cloudflare
does not serve it** — an unknown path returns an empty 404 body. Serving it
needs `assets.not_found_handling`, which can only be set from a `wrangler.jsonc`
in the repo.

**Do not add that file back without watching the next deploy.** It was tried and
reverted: the first push carrying it deployed fine, but every push after it
silently stopped deploying — the site kept serving the previous build while
pushes appeared to succeed. That is the worst failure mode this project has,
because it also breaks the CMS: an editor saves, the commit lands, and the live
site never changes. If the 404 page matters more than that risk, add the config
*and* confirm the very next commit reaches the live site before trusting it.

Until then the page is reachable at `/404` and does no harm.

**Yandex Webmaster verification lives in `yandex_11df7f8b41641d66.html`** at the
repo root, and `.htaccess` exempts it from the `.html`-stripping redirect — the
same treatment the ACME challenge gets, and for the same reason: a third party
fetches a fixed absolute URL and expects a 200, not a 301. Yandex matters more
than Google here; the client's customers search on Yandex.

**It must survive the cutover.** When Grav takes over the docroot this file has
to still be reachable at `/yandex_11df7f8b41641d66.html`, or the site quietly
loses verification. Grav's own root is where it goes.

`llms.txt` is a plain-language summary for assistants and crawlers — hours,
address, phone, what the place does. Keep it in step with the page; it is the
one file that repeats content rather than linking to it.

Absolute URLs to the production host live in **14 places across five files** —
verify with `grep -rn 'coffeeshtob\.ru' . --exclude-dir=.git`, which is the
authoritative list rather than this paragraph:

- `index.html` (6) — `canonical`, `og:url`, `og:image`, `twitter:image`, and
  `url` + `image` in the JSON-LD
- `privacy.html` (3) — `canonical`, `og:url`, `og:image`
- `sitemap.xml` (2) — both `<loc>` entries
- `llms.txt` (2) — the two links at the bottom
- `robots.txt` (1) — the `Sitemap:` line

**They must all change together** if the host ever changes again, and the new
`/admin/` URL has to be registered as a GitLab Redirect URI *before* the switch,
or the CMS login breaks the moment the new host goes live. See "GitLab OAuth
application" above.

**The canonical host is the bare domain, never `www`.** Beget creates `www`
automatically and its certificate covers both names, so `https://www.coffeeshtob.ru/`
served a byte-identical page with a 200 — two URLs for one page. All 14 absolute
URLs use the bare form, so `.htaccess` 301s `www` to bare (in a single hop,
combined with the HTTPS upgrade). If a future site wants `www` as canonical
instead, that rule and all 14 URLs flip together.

The move from `workers.dev` to `coffeeshtob.ru` was made in one commit for
exactly this reason: canonical, `og:url` and `sitemap.xml` disagreeing about
which host is real is the failure mode, and a split second where half the files
point one way is not worth the smaller diff.

The JSON-LD is `CafeOrCoffeeShop` and contains only facts that are on the page —
no invented geo coordinates, no made-up `priceRange`. Its
`openingHoursSpecification` mirrors `#schedule`; change both together or the
rich result will contradict the page.

## Image placeholders

Most spots now carry **real photos**, uploaded through the CMS into
`images/uploads/` — hero, About, all four feature cards, both Жизнь штаба blocks
and most menu items. Three list items are still on the placeholder (Романовский
квас, Горячее какао, Ароматный цикорий); `content/home.json` is the live answer
to which. That means the "generic AI landing page" risk has largely passed —
don't reintroduce stock-looking imagery.

Two things learned from those uploads:

- **HEIC does not render in any desktop browser.** An `img_4981.heic` was
  uploaded and showed as a broken image; it was deleted in `fd2c9e2`. The CMS
  will happily accept one, so if a photo silently fails to appear, check the
  extension first.
- Filenames come straight from the phone (`photo_2026-08-28-23.14.08.jpeg`) and
  are not worth renaming — the paths live in `content/home.json`, which the CMS
  rewrites.

Every remaining placeholder spot still has a real `<img>` tag pointing at
`images/placeholder.svg`, wrapped in a `.img-frame` div that fixes the aspect
ratio (so swapping images later causes no layout shift):

- `.img-frame--square` — feature/menu/drink card thumbnails (1:1)
- `.img-frame--about` — About section photo (4:3)
- `.img-frame--wide` — Жизнь штаба cards (16:10)
- `.hero-bg` — full-bleed hero background image (object-fit: cover), blurred
  4px and scaled 1.06, sitting behind the `.hero-overlay` gradient. The scale is
  not decoration: `blur()` feathers an element's own edges, which on a
  full-bleed image shows as a pale halo down the hero's borders, and scaling
  pushes that edge out of frame. The overlay is deliberately heavy because the
  photo comes from the CMS and could be anything from a bright snowy embankment
  to a dim interior — the white headline has to stay readable over all of them.
  It runs 0.74 → 0.46 → 0.84: the extra-dark first stop covers the band the
  sticky header sits over, keeping the white pill contrasting against whatever
  the top of the photo happens to be. That stop is positioned with
  `calc(var(--header-h) + 24px)` rather than a percentage, so it tracks the
  header's real height instead of drifting with the hero's.

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

The legal pages (`privacy.html`, `404.html`) carry a **two-item header** — brand,
then a single «На главную» button — with no `.main-nav` between them to take up
the slack with its `margin: 0 auto`, so the button would otherwise sit against
the brand instead of at the right edge. `.brand + .nav-social-btn
{ margin-left: auto }` fixes it. That is keyed on the adjacency rather than a
modifier class on purpose: it is the *absence of the nav* that needs correcting,
and matching it structurally covers both legal pages and any future one with no
way to add a page and forget the class. On `index.html` the nav sits between the
two, so it never matches there.

The header hides on scroll-down and reappears on scroll-up, but by **two
different mechanisms**, and they must not be merged:

- **Desktop** toggles `.is-hidden` and lets a 0.28s CSS transition play.
- **Mobile** ignores the class and moves the header with an inline
  `translateY`, scroll-driven but **frame-rendered**. The scroll handler only
  sets `wanted`; a `requestAnimationFrame` loop walks `shown` toward it at 30%
  per frame. Both halves matter: a timed CSS transition is either laggy or
  snappy (both were tried and rejected), but writing the transform straight from
  the scroll delta moved the bar in visible steps, because scroll events fire
  less often than frames. Interpolating between them is what makes it smooth
  while still following the scroll rather than a clock.

  When fully up, the header also gets `visibility: hidden`. Translating a
  sticky element off-screen is **not** the same as it being gone — a webview
  whose sticky box disagrees with the visual viewport still paints a sliver,
  which is the strip that stayed on screen in Telegram's in-app browser.

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

Scroll-reveal is a **rescan**, not a one-off pass, and it tracks handled
elements in a `WeakSet` rather than by the `.reveal` class. `render.js` rebuilds
the card grids by cloning nodes that by then already carry `.reveal`, so the
clones arrive with the class — using it as the "already handled" marker skipped
every rebuilt card, leaving them at `opacity: 0` permanently. A clone is a
different element, so identity is the only marker that works here.

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

## Things deliberately not built

**A review / feedback form.** Discussed and declined. The form itself is trivial;
what it drags in is not, and all four reasons still stand:

- The site would start processing personal data under Russian **152-ФЗ**. That
  requires a consent checkbox and a policy naming the operator (юрлицо/ИП, ОГРН,
  ИНН) — exactly the details deliberately left blank rather than invented.
- 152-ФЗ also requires personal data of Russian citizens to be stored **on
  servers in Russia**. Cloudflare KV/D1 is not, so the obvious serverless
  implementation conflicts with the hosting.
- It invalidates `privacy.html`'s opening callout (see above).
- A public form on an indexed site gets bot submissions within days, so it comes
  with a permanent moderation duty for a small café.

The recommended alternative, if reviews come up again: **link out to Yandex
Maps**, where reviews already live, are searched, and affect whether the place
gets found — zero legal exposure, zero moderation. Second-best: curated
testimonials pasted into the CMS, no form. A third shape that avoids the storage
problem is form → Worker → Turnstile → email to the owner, nothing persisted.

**Analytics.** Never added, for the same privacy-notice reason. Anything with
cookies or a visitor counter needs the notice rewritten first.

**Netlify Identity.** Deliberately absent everywhere; the CMS uses GitLab PKCE
precisely so the public page stays cookie-free.

**`wrangler.jsonc`.** See the 404 note under Icons and SEO — it was tried and
reverted, and it is suspected (never proven) to have silently stopped deploys.

## Working on this project

Conventions that come from the owner, not from the code:

- **Never invent facts.** Prices, geo coordinates, legal identifiers, e-mail
  addresses, opening hours. Ask, or leave it out. Several gaps in this project
  are gaps on purpose.
- **The owner pushes.** `git push` runs in their own terminal because the
  credential prompt is interactive. Commit locally and tell them; don't try to
  push.
- **The repo root is the web root.** Every file added is world-readable at its
  path, `CLAUDE.md` included. Nothing secret goes in.
- **Design direction: fewer boxes, less "AI landing page".** This has come up
  repeatedly — the rounded-rectangle-per-block look was removed on purpose, and
  a 20-item checklist of AI-site tells was worked through. Adding a card
  background, a gradient blob or an icon-in-a-circle walks it back.
- **Mobile is the priority surface**, and specifically **Telegram's in-app
  browser** — that webview is where the sticky-header sliver bug appeared and it
  is a real part of this café's traffic.

Verification habits that were learned the hard way here:

- **Browser memory-cache served stale JS/CSS and produced false test results
  three separate times.** Spin a *fresh* `python3 -m http.server` on a new port
  for each verification round rather than reloading the old one.
- **Assert expected counts before trusting an extraction.** A regex HTML edit
  once silently produced one item per list instead of four, and only an explicit
  count assertion caught it.
- **Measure contrast on rendered elements**, not on the token values — the
  tightest pairing on the page (`--accent-mid` on `--cream-alt`, 4.69:1) was only
  visible that way.
- **Don't conclude a deploy has broken from one stale response.** That call was
  made once on edge-cache evidence and led to a push on a wrong premise. Check
  the Cloudflare deployment log.
- Judge icons on a contact sheet at **real tab sizes**, not at 512px.
