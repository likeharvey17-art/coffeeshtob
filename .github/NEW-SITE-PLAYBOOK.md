# Playbook: small business site for a Russian client

Distilled from building coffeeshtob.ru. Everything here was paid for once
already — the reasons matter more than the instructions, because the reasons are
what tell you when a rule stops applying.

Paste this as the primary prompt for a new client site.

---

## Locked decisions (do not re-litigate without a new reason)

**Hosting: Beget.** Free tier to start, cheap multi-site plans later, Apache +
PHP + `.htaccess` + free Let's Encrypt.

**Cloudflare is disqualified**, and so is any foreign CDN or host. RKN throttles
Cloudflare; the site was simply unreachable from Russian IPs, and the client
reported "doesn't load at all" *after* a first round of fixes aimed at the wrong
cause. Assume anything foreign can be throttled, blocked or ban your account
without explanation — a GitLab account was lost the same week, for nothing
anyone could identify.

**CMS: Grav.** Flat-file, no database, PHP 7.3.6+, installs over FTP with no
SSH, has its own user accounts, and someone else patches the auth.

Ruled out, with the reason each time:
- **Every git-backed CMS** (Decap, Sveltia) — requires the *editor* to hold a
  GitHub/GitLab account. The requirement is a password you hand the client.
- **Every hosted CMS** (Tina, Contentful, Storyblok, Sitepins) — satisfies the
  password requirement but reintroduces a foreign service that can vanish.
- **Kirby** — $105/site.
- **A bespoke PHP admin** — no rewrite needed, but it means hand-rolled auth
  maintained across a dozen client sites forever, with no upstream security
  patches and a bus factor of one.

**Repo: GitHub, private.** Deploys by GitHub Actions over FTPS.

**Twig templates are your own HTML**, so Grav imposes no design. Build the
static design first if you like, then port it — the CSS and JS carry over
untouched.

---

## Beget setup, in order

1. Domain: point the registrar's nameservers at Beget's (all of them). DNS takes
   hours; do this first.
2. Create the site, then **set PHP to 8.x on that specific site**. Beget
   defaults to **5.6 per-site**, and Grav dies with a blank 500 whose only clue
   is the `X-Powered-By` header. This costs an evening if you skip it.
3. Issue the free Let's Encrypt certificate.
4. Install Grav (upload the zip, extract in File Manager). Delete the zip after.
5. Create **two** sites: production and a staging subdomain. Build on staging.
6. FTP credentials → four GitHub repository secrets: `FTP_HOST`, `FTP_USER`,
   `FTP_PASSWORD`, `REMOTE_DIR` (+ `STAGING_DIR`).

**Beget runs nginx in front of Apache.** Consequences you cannot avoid:
- nginx serves static files itself and **never reads `.htaccess`**, so
  `mod_expires` and `AddType` blocks do nothing. Cache headers are out of your
  hands (measured: 7-day `max-age` regardless of what you ask for).
- Apache never sees TLS, so `%{HTTPS}` is never `on`. Every redirect rule must
  test `X-Forwarded-Proto` **and** name `https://` explicitly in the target —
  written as `/%1`, Apache emits `Location: http://…` and the force-HTTPS rule
  then bounces it back, costing two redirects and a plaintext hop per link.
- Beget serves an anti-bot JS interstitial to clients with no `beget=begetok`
  cookie. Browsers pass it invisibly; **curl does not**, so every CI check needs
  `-H 'Cookie: beget=begetok'` or it reads the challenge and reports nonsense.
- `SITE CHMOD` is refused outright. Use `lftp --no-perms` or every run goes red
  while working perfectly.

---

## Deploy architecture

Two workflows, both `lftp` FTPS mirrors:

- **production** — on push to `main`
- **staging** — on pushes touching `grav/**`, plus `workflow_dispatch`

**The repo mirrors Grav's tree under a `grav/` prefix, and holds only your
files.** `system/` and `vendor/` are Grav's 60+ MB and are updated from its own
admin panel. Committing them makes every version bump a thousand-file diff.

**Rules that exist because breaking them hurt:**

- **`--delete` may only ever point at your own theme directory.** The client's
  pages, uploaded photos and account live under `user/` and exist nowhere else.
  A mirror with `--delete` at the docroot erases all of it, unrecoverably.
- **Page content is seeded on manual runs only**, never on push. Seeding
  overwrites what the client typed.
- **Any directory added to the repo for a different deploy target must be
  excluded from the other deploy in the same commit.** Adding `grav/` without
  excluding it from the static deploy published the whole theme on the live
  site.
- **Never gate on `lftp`'s exit code.** On this host it returns 1 for
  non-failures. Gate on *what the server actually serves*: fetch the URLs and
  assert. Three separate investigations went into a file that was never broken
  because a red job was read as "something failed" instead of being opened.
- **Beget loses files mid-mirror**, deterministically the same ones, regardless
  of size or type. Follow every mirror with a repair pass that lists what is on
  the server, retries anything missing over a different transport, and judges by
  presence afterwards. Cause still unestablished after many runs; the repair
  works every time, which is what matters.
- Deploy the asset version stamp from the commit SHA rather than a hand-edited
  date, and assert the replacement count — an empty `GITHUB_SHA` writes `?v=`
  and still passes a naive check.

**If a deploy or a transfer fails opaquely, make the server say why before
theorising.** `lftp`'s `debug 3` printed `426 Failure reading network stream` in
one run, after four wrong theories built on a message that named no cause. Same
lesson twice in this project.

---

## The Grav theme contract

```
grav/
  root/                     files that must sit at the web ROOT (see below)
  user/pages/01.home/       home.md + the client's photos as page media
  user/themes/<name>/
    blueprints.yaml         theme form: site-wide contact details
    <name>.yaml             their default values
    blueprints/home.yaml    THE CLIENT'S EDITING FORM, in Russian
    templates/
      home.html.twig        every value from page.header.*
      privacy.html.twig     legal copy lives in the template, not a form
      error.html.twig       404
      default.html.twig     safety net — see below
      partials/{base,header,footer}.html.twig
```

**`default.html.twig` is mandatory even though nothing uses it.** Grav picks a
template from the page's filename, and its stock install ships `default.md`. A
theme with only `home.html.twig` hard-errors on first activation. Also delete
Grav's demo pages by name when seeding.

**Write `blueprints/home.yaml` or the client edits raw YAML front matter.** That
is exactly how a client deletes a colon and takes the site down. Label every
field in Russian; the person maintaining it is not a developer and should never
have to guess what `hero_lead` means.

**Three files must name the same fields and nothing complains when they drift**
— template, blueprint, content. Twig renders a missing key as an empty string,
so a rename becomes a silently blank section on the live site. **Write a check
script** comparing all three and run it before the deploy touches the server.
Confirm the check fails on a deliberate rename; a check that has never failed is
not yet a check.

**Site-wide contact details go in theme config, not page fields.** The phone
number appears in the footer text, the footer's `tel:` link and the privacy
notice. As page fields they drift apart the first time the client edits it, and
the site then *shows* one number and *dials* another. Derive the `tel:` href
from the same value with `|replace`. Grav writes admin edits to
`user/config/themes/<name>.yaml`, so deploys cannot overwrite them.

**Images are page media**: store a bare filename, resolved against the page
folder, so the client uploads a photo where they edit the text. Always fall back
to a placeholder — `page.media[name]` returns null for a missing or empty name.

**Resize in the template.** Photos come straight off a phone. Measured: 3.6 MB
per page, including an 821 KB JPEG behind a thumbnail displayed at 76px. With
`cropZoom(w, h).quality(75)` matched to the real CSS display size, the same page
came to 851 KB. Grav caches the result, so it applies to whatever the client
uploads with no step for them to remember. **Gate the total page image weight**
in CI — if image processing is unavailable on the host, Grav silently serves the
originals and nothing looks broken, it is just slow again.

**Set Configuration → System → Pages → Expires to `0` before handover.** Grav's
default is 604800 — a **seven-day browser cache on the HTML**. The client's edit
is live on the server instantly and invisible in their browser for a week, which
reads exactly like "saving is broken".

---

## `.htaccess` for Grav

Start from **Grav's own file, fetched from its repo**, not from memory. Three
changes:

1. **Uncomment the `X-Forwarded-Proto` block.** Grav ships it commented out for
   exactly this setup. Left commented, Grav builds absolute URLs from its own
   idea of the scheme and puts `http://` in the canonical, `og:url` and sitemap
   of an `https://` site.
2. **Add `www`→bare and force-HTTPS — ABOVE Grav's rules, not below.** Placed
   after, `REQUEST_URI` has already been rewritten to `/index.php`, so
   `www.site.ru/privacy` redirects to `site.ru/index.php` and every `www` visitor
   lands on the home page whatever they asked for. Exempt
   `/.well-known/acme-challenge/` from the HTTPS redirect or certificate renewal
   breaks every 90 days in a way that looks nothing like a deploy.
3. **Compression guarded on BOTH `mod_filter` and `mod_deflate`.** `mod_filter`
   provides `AddOutputFilterByType`; `mod_deflate` provides the DEFLATE provider
   it names. With either missing the result is **a 500 on every page**, and with
   only `mod_deflate` missing `httpd -t` still passes — it fails at request time.

You do **not** need exemptions for `robots.txt`, the Yandex token or the icons:
Grav routes to `index.php` only when the path is not a real file.

**Test it against a real Apache with a Grav-shaped docroot before trusting it.**
This found the `www` bug, and testing the deny rules against files that *exist*
(a missing path is routed to the front controller before the security rules run)
is what proves `user/accounts/admin.yaml` actually 403s.

---

## Files that must live at the web root

`robots.txt`, `sitemap.xml`, `llms.txt`, `favicon.ico`, `apple-touch-icon.png`,
the manifest's icons, `og-image.jpg`, the Yandex verification token, `.htaccess`.

Each because something *outside* the site fetches it by absolute path.
Specifically: `site.webmanifest` lists its icons as `/icon-192.png` — root
absolute — even when the manifest itself is served from the theme, so those
icons 404 unless they are at the root. And the old site advertised
`/og-image.jpg` for months, so Telegram and VK have that URL cached against
every link ever shared.

**Anything in that directory is public.** Do not leave build notes there.

---

## Russian-market specifics

- **Self-host the fonts.** A Google Fonts `<link>` is a render-blocking
  stylesheet in `<head>`: when Google is slow or blocked the browser paints
  *nothing* until it times out, then falls back to system fonts. The client
  described exactly that. Use variable fonts split by `unicode-range`. Keep
  `latin-ext` — the ruble sign `₽` (U+20BD) lives there and menu prices pull it.
- **Yandex over Google.** Verify in Yandex Webmaster; the client's customers
  search there. Yandex Maps is also where reviews already live.
- **No forms, no analytics, no cookies** unless the client genuinely needs them.
  A form puts the site under **152-ФЗ**: consent checkbox, a policy naming the
  operator (юрлицо/ИП, ОГРН, ИНН), and personal data of Russian citizens stored
  **on servers in Russia**. Plus permanent bot-moderation duty for a small
  business. Link out to Yandex Maps for reviews instead.
- **The privacy notice is load-bearing.** If it says the site sets no cookies
  and has no forms, that stops being true the moment anyone adds one — rewrite it
  in the same commit. Keep the legal copy in the template rather than in a
  content form, so it cannot be casually edited or padded out.
- **Telegram's in-app browser is a real share of traffic.** Test there. A
  `position: sticky` header translated off-screen still paints a sliver in that
  webview unless you also set `visibility: hidden`.

---

## Cutover (static → Grav, or old site → new)

**Copy, don't convert.** Leave the old folder untouched so rollback is one
setting rather than a restore.

1. New site folder on Beget.
2. Copy the whole verified staging install into it.
3. Point the domain's document root at the new folder.
4. Update `REMOTE_DIR`; flip the deploy mode; push.

**Put the deploy mode in a tracked file, and add a server-side interlock.**
Before any destructive mirror, ask the server whether `system/defines.php`
exists and refuse if it does. Check the *server*, not the file — the dangerous
case is exactly when intent and reality disagree, and the cost is the client's
entire site.

Before handover: PHP 8.x, `Expires: 0`, and a Grav account for the client that
is not yours.

---

## Verification habits

- **Measure, don't infer.** Fetch the URL and assert on the response.
- **Assert counts** before trusting any extraction or bulk edit. A regex HTML
  edit once silently produced one list item instead of four.
- **A gate that misreports the cause is worse than no gate** — it sends the next
  person looking in the wrong place. An image-weight check once failed with
  "resizing is not being applied" while resizing was working perfectly.
- **Never conclude from one stale response.** Browser and CDN caches produced
  false results repeatedly; use a fresh server or a cache-busting query.
- **Read the log before theorising.** Every expensive detour in this project
  started by reasoning about a red job instead of opening it.
