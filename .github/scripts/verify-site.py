#!/usr/bin/env python3
"""Fetch a deployed site and prove it actually works.

Usage:  verify-site.py <base-url> [--run-id ID]

WHAT THIS REPLACES. Both deploy jobs used to gate on a handful of hand-written
URLs and one assertion — «the home page contains a .menu-item». That was true
while the site was a single page. It is now false by design (the menu lives on
/kuhnya), and a list of URLs typed into a workflow goes stale the moment a page
is added. So the routes are DERIVED from the page tree, exactly as the site's
own navigation is: add a page, and it is checked from the next deploy.

Every gate here is a measurement of the delivered page, never of the transfer.
lftp's exit code has been unreliable on this host since the first deploy and is
never consulted anywhere in this repo.

Checks, in order:
  * every page route returns 200, with a non-empty <h1> and no template error
  * the navigation on every page lists every visible section, exactly once
  * exactly one nav link is marked current, and it is the page you are on
  * the menu still renders where the menu now lives
  * the static files (icons, sitemap, the Yandex token) are all present
  * an unknown path still 404s
  * every photo is served through Grav's image processor, not as the original
  * no page's images exceed the per-page weight budget

Exit 1 on the first category that fails, after reporting all of them.
"""
import os
import re
import sys
import time
import urllib.error
import urllib.request

PAGES = 'grav/user/pages'

# Files that are not pages but must exist: the icons the manifest points at, the
# crawler files, and the Yandex Webmaster token — which is fetched by Yandex at
# a fixed absolute URL and silently loses the site's verification if it 404s.
STATIC = ['/sitemap.xml', '/robots.txt', '/llms.txt', '/favicon.ico',
          '/apple-touch-icon.png', '/icon-192.png', '/icon-512.png',
          '/og-image.jpg', '/yandex_11df7f8b41641d66.html']

# Measured: the heaviest page (the landing grid, 7 photos) comes to 631 KB
# resized. Unresized the old single page carried 3.6 MB for 13 photos, so a page
# whose processing had failed would land far above this. The cap is roughly
# double the current worst case: loose enough that the client can add photos to
# a section, tight enough that a page serving originals cannot slip through.
PAGE_IMAGE_BUDGET = 1200 * 1024

ERROR_MARKERS = ('Twig\\', 'Whoops', 'Fatal error', 'Grav Problems',
                 'Uncaught', 'Unable to find template')


def page_meta():
    """(route, is_visible) for every page, read from the page tree."""
    out = []
    for folder in sorted(os.listdir(PAGES)):
        d = os.path.join(PAGES, folder)
        if not os.path.isdir(d):
            continue
        for name in sorted(os.listdir(d)):
            if not name.endswith('.md'):
                continue
            src = open(os.path.join(d, name), encoding='utf-8').read()
            m = re.search(r'^\s*default:\s*(/\S+)\s*$', src, re.M)
            route = m.group(1) if m else '/' + re.sub(r'^\d+\.', '', folder)
            if route == '/home':
                route = '/'
            visible = not re.search(r'^visible:\s*false\s*$', src, re.M)
            out.append((route, visible, folder))
    return out


def fetch(url, retries=1):
    # Beget serves a JavaScript interstitial to clients without a
    # `beget=begetok` cookie — a 273-byte page that sets it and reloads. Without
    # the cookie every check reads the challenge instead of the site. Browsers
    # pass it invisibly; urllib does not.
    req = urllib.request.Request(url, headers={
        'Cookie': 'beget=begetok',
        'User-Agent': 'coffeeshtob-deploy-check',
    })
    last = None
    for attempt in range(retries):
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                return r.status, r.read()
        except urllib.error.HTTPError as e:
            return e.code, e.read()
        except Exception as e:                      # noqa: BLE001 - report and retry
            last = e
            if attempt + 1 < retries:
                time.sleep(10)
    return 0, str(last).encode()


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    base = sys.argv[1].rstrip('/')
    run_id = sys.argv[3] if len(sys.argv) > 3 and sys.argv[2] == '--run-id' else str(int(time.time()))

    meta = page_meta()
    routes = [r for r, _, _ in meta]
    visible = [r for r, v, _ in meta if v]
    problems = []

    print(f'{len(routes)} routes from the page tree, {len(visible)} of them in the nav\n')

    # ── pages ────────────────────────────────────────────────────────────────
    bodies = {}
    for route in routes:
        sep = '&' if '?' in route else '?'
        # Give the first page ten tries: a deploy that has only just finished can
        # still be serving mid-write, and a flaky first fetch is not a failure.
        status, raw = fetch(f'{base}{route}{sep}ci={run_id}', retries=10 if route == routes[0] else 3)
        body = raw.decode('utf-8', 'replace')
        bodies[route] = body

        h1 = re.search(r'<h1[^>]*>([^<]{3,})</h1>', body)
        errs = [m for m in ERROR_MARKERS if m in body]
        nav = re.search(r'<nav class="main-nav"[^>]*>(.*?)</nav>', body, re.S)
        links = re.findall(r'<a href="([^"]+)"([^>]*)>', nav.group(1)) if nav else []
        active = [l for l in links if 'is-active' in l[1]]
        legal = route == '/privacy'

        if status != 200:
            problems.append(f'{route}: HTTP {status}')
        if not h1:
            problems.append(f'{route}: no non-empty <h1> — the page rendered but its fields did not')
        if errs:
            problems.append(f'{route}: template error text in the response ({errs[0]})')
        if not legal:
            hrefs = [l[0].rstrip('/') or '/' for l in links]
            missing = [v for v in visible if v.rstrip('/') not in [h.rstrip('/') for h in hrefs]]
            if len(links) != len(visible):
                problems.append(f'{route}: {len(links)} nav links, expected {len(visible)}')
            if missing:
                problems.append(f'{route}: nav is missing {missing}')
            if len(hrefs) != len(set(hrefs)):
                problems.append(f'{route}: nav lists the same page twice')
            want_active = 0 if route not in visible else 1
            if len(active) != want_active:
                problems.append(f'{route}: {len(active)} links marked current, expected {want_active}')
            elif active and active[0][0].rstrip('/') != route.rstrip('/'):
                problems.append(f'{route}: the current-page marker is on {active[0][0]}')

        title = h1.group(1)[:38] if h1 else '—'
        print(f'  {status}  {route:<15} h1=«{title}» nav={len(links)} current={len(active)}')

    # The menu is the one piece of content whose loops prove page.header.* was
    # read at all. It used to be asserted on the home page; it lives here now.
    menu_items = len(re.findall(r'class="menu-item"', bodies.get('/kuhnya', '')))
    print(f'\n  /kuhnya menu items: {menu_items}')
    if menu_items < 1:
        problems.append('/kuhnya: no .menu-item rendered — the content loops did not run')

    # ── static files ─────────────────────────────────────────────────────────
    print()
    for u in STATIC:
        status, _ = fetch(f'{base}{u}?ci={run_id}', retries=2)
        print(f'  {status}  {u}')
        if status != 200:
            problems.append(f'{u}: HTTP {status}')

    status, _ = fetch(f'{base}/no-such-page-{run_id}', retries=2)
    print(f'  {status}  /no-such-page (want 404)')
    if status != 404:
        problems.append(f'an unknown path returned {status}, not 404')

    # ── images ───────────────────────────────────────────────────────────────
    # A DIRECT check that resizing happened, not a proxy for it. Grav serves
    # processed derivatives from /images/<hash path>/; an original page-media
    # file would come back from /user/pages/... . If image processing is
    # unavailable on the host Grav quietly falls back to the original and
    # nothing looks broken — the site is just slow again, which is exactly what
    # this audience reported before.
    print()
    sizes, total = {}, 0
    for route in routes:
        urls = set(re.findall(r'<img[^>]+src="([^"]+)"', bodies[route]))
        weight, unprocessed = 0, []
        for u in urls:
            if 'placeholder' in u:
                continue
            if '/user/pages/' in u:
                unprocessed.append(u)
            if u not in sizes:
                _, raw = fetch(u if u.startswith('http') else base + u, retries=2)
                sizes[u] = len(raw)
            weight += sizes[u]
        total += weight
        flag = '' if weight <= PAGE_IMAGE_BUDGET else '  OVER BUDGET'
        print(f'  {weight // 1024:>6} KB  {len(urls)} images  {route}{flag}')
        if weight > PAGE_IMAGE_BUDGET:
            problems.append(f'{route}: {weight // 1024} KB of images, over the '
                            f'{PAGE_IMAGE_BUDGET // 1024} KB per-page budget — either resizing '
                            f'has stopped or the photos have genuinely grown')
        for u in unprocessed:
            problems.append(f'{route}: {u} is served straight from the page folder, '
                            f'so Grav did not resize it')
    print(f'  {total // 1024:>6} KB  total across {len(routes)} pages')

    print()
    if problems:
        print(f'FAIL — {len(problems)} problem(s):')
        for p in problems:
            print('  - ' + p)
        return 1
    print(f'OK — {len(routes)} pages render, navigation is consistent on all of them, '
          f'{len(STATIC)} static files present, unknown paths 404, '
          f'every photo resized, {total // 1024} KB of images site-wide.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
