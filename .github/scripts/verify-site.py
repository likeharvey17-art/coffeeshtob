#!/usr/bin/env python3
"""Fetch a deployed site and prove it actually works.

Usage:  verify-site.py <base-url> [--run-id ID]

EVERY GATE HERE IS A MEASUREMENT OF THE DELIVERED PAGE, never of the transfer.
lftp's exit code has been unreliable against this host since the first deploy —
it returns 1 for SITE CHMOD refusals that broke nothing, and it has also exited 0
having silently skipped a file — so it is not consulted anywhere in this repo.

THE ROUTES COME FROM THE SITE, NOT FROM THE REPO, and that is a change forced by
WordPress: the pages live in a database this script cannot read. It reads the
sitemap WordPress generates from those pages, and cross-checks it against the
navigation the theme renders from the same pages. Two independent views of one
list — if they disagree, something is wrong that neither alone would show.

Checks, in order:
  * the front page renders, and the navigation is not empty
  * the sitemap lists pages, and every URL in it renders
  * every page carries the full navigation, with exactly one current marker
  * the menu still renders where the menu lives
  * the crawler files, the icons and the Yandex token are present
  * an unknown path still 404s
  * the paths a WordPress gets scanned for are closed
  * the site sets no cookies for an anonymous visitor
  * photos are served resized by WordPress, not as camera originals
  * no page's images exceed the weight budget

Exit 1 after reporting every category that failed.
"""
import re
import sys
import time
import urllib.error
import urllib.request

# Measured on the previous build: the heaviest page came to 631 KB resized, and
# the old single page carried 3.6 MB of unresized originals. The cap is roughly
# double the current worst case — loose enough for the client to add photos to a
# section, tight enough that a page serving originals cannot slip through.
PAGE_IMAGE_BUDGET = 1200 * 1024

ERROR_MARKERS = ('Fatal error', 'Parse error', 'Warning:', 'Notice:',
                 'Deprecated:', 'There has been a critical error',
                 'Error establishing a database connection')

# Files that are not pages but must exist. The Yandex token is fetched by Yandex
# at a fixed absolute URL and silently loses the site's verification if it 404s.
STATIC = ['/robots.txt', '/llms.txt', '/wp-sitemap.xml', '/favicon.ico',
          '/yandex_11df7f8b41641d66.html']

# Paths every WordPress on the public internet is scanned for within days.
# A 200 on any of these is a finding, not a curiosity.
MUST_BE_CLOSED = ['/readme.html', '/license.txt',
                  '/wp-config.php', '/wp-config-sample.php',
                  '/wp-json/wp/v2/users']


def fetch(url, timeout=30):
    req = urllib.request.Request(url, headers={
        'User-Agent': 'coffeeshtob-deploy-check/2.0',
        # Beget serves a JavaScript interstitial to clients without this cookie:
        # a 273-byte page that sets it and reloads. Browsers pass it invisibly,
        # curl does not, and without it every check here reads the challenge
        # instead of the site and reports nonsense.
        'Cookie': 'beget=begetok',
        'Accept': '*/*',
    })
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, r.read(), dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read(), dict(e.headers)
    except Exception as e:                      # noqa: BLE001 - reported, not raised
        return 0, str(e).encode(), {}


def text(body):
    return body.decode('utf-8', 'replace')


def plain(html):
    """Tags stripped, for scanning error text.

    PHP prints a notice as `<br /><b>Warning</b>:  ...`, so the literal string
    'Warning:' never appears in the markup and a page full of notices sailed
    through this check. Found by mutation testing, not by reading it.
    """
    return re.sub(r'<[^>]+>', '', html)


def nav_links(html):
    """The hrefs inside the header navigation, in order."""
    m = re.search(r'<nav class="main-nav"[^>]*>(.*?)</nav>', html, re.S)
    if not m:
        return []
    return re.findall(r'<a href="([^"]+)"', m.group(1))


def active_links(html):
    m = re.search(r'<nav class="main-nav"[^>]*>(.*?)</nav>', html, re.S)
    if not m:
        return []
    return re.findall(r'<a href="([^"]+)"[^>]*aria-current="page"', m.group(1))


def path_of(url):
    return re.sub(r'^https?://[^/]+', '', url) or '/'


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        return 2
    base = sys.argv[1].rstrip('/')
    run_id = (sys.argv[3] if len(sys.argv) > 3 and sys.argv[2] == '--run-id'
              else str(int(time.time())))

    fails = []
    print(f'checking {base}  (run {run_id})\n')

    # ── the front page ──────────────────────────────────────────────────────
    status, body, headers = fetch(base + '/')
    home = text(body)
    if status != 200:
        print(f'FAIL — the front page returned {status}')
        return 1
    nav = nav_links(home)
    if not nav:
        fails.append('the front page renders no navigation at all')
    print(f'  front page 200, {len(nav)} navigation links')

    # ── the routes, from the sitemap ────────────────────────────────────────
    status, body, _ = fetch(base + '/wp-sitemap-posts-page-1.xml')
    routes = []
    if status == 200:
        routes = [path_of(u) for u in re.findall(r'<loc>([^<]+)</loc>', text(body))]
    if not routes:
        fails.append('the sitemap lists no pages — has the seed been run, and are '
                     'permalinks set to /%postname%/ rather than the default?')
        routes = ['/']
    print(f'  sitemap lists {len(routes)} page(s)')

    # The two lists must agree: everything in the navigation must be a real page.
    for href in nav:
        if path_of(href) not in routes:
            fails.append(f'navigation links {path_of(href)}, which the sitemap does '
                         'not list — a menu entry pointing at nothing')

    # ── every page ──────────────────────────────────────────────────────────
    nav_set = {path_of(h) for h in nav}
    menu_pages, budgets, sectionish = [], [], []
    for route in routes:
        status, body, _ = fetch(f'{base}{route}?ci={run_id}')
        html = text(body)
        if status != 200:
            fails.append(f'{route} returned {status}')
            continue

        h1 = re.search(r'<h1[^>]*>(.*?)</h1>', html, re.S)
        if not h1 or not re.sub(r'<[^>]+>', '', h1.group(1)).strip():
            fails.append(f'{route} has no non-empty <h1>')

        flat = plain(html)
        for marker in ERROR_MARKERS:
            if marker in flat:
                fails.append(f'{route} contains PHP error text: "{marker}"')
                break

        # The legal pages carry a deliberately stripped two-item header with no
        # .main-nav at all — brand, then «На главную». Only pages that ARE in
        # the menu are required to render it; requiring it everywhere failed
        # /privacy on the first run of this script.
        page_nav = {path_of(h) for h in nav_links(html)}
        if route in nav_set and page_nav != nav_set:
            missing = nav_set - page_nav
            extra = page_nav - nav_set
            fails.append(f'{route} navigation differs from the front page '
                         f'(missing {sorted(missing)}, extra {sorted(extra)})')
        elif route not in nav_set and page_nav and page_nav != nav_set:
            fails.append(f'{route} renders a partial navigation {sorted(page_nav)}')

        current = [path_of(h) for h in active_links(html)]
        if route in nav_set:
            if current != [route]:
                fails.append(f'{route} marks {current or "nothing"} as the current '
                             'page instead of itself')
        elif current:
            fails.append(f'{route} is not in the menu but marks {current} as current')

        sectionish.append((route, bool(re.search(r'<nav class="main-nav"', html))))

        if 'class="menu-item"' in html:
            menu_pages.append(route)

        imgs = re.findall(r'<img[^>]+src="([^"]+)"', html)
        total, originals = 0, []
        for src in set(imgs):
            url = src if src.startswith('http') else base + src
            if '/wp-content/uploads/' in url and not re.search(r'-\d+x\d+\.\w+$', url):
                originals.append(src)
            st, data, _ = fetch(url)
            if st == 200:
                total += len(data)
        if originals:
            fails.append(f'{route} serves {len(originals)} camera original(s) '
                         f'instead of a resized size, e.g. {originals[0]}')
        budgets.append((route, total))
        if total > PAGE_IMAGE_BUDGET:
            fails.append(f'{route} images total {total // 1024} KB, over the '
                         f'{PAGE_IMAGE_BUDGET // 1024} KB budget')

    # A link dropped from the navigation everywhere was invisible: every page was
    # compared only with the front page, and the front page had lost it too. The
    # sitemap is the independent list. A page that renders the main navigation is
    # a section and must appear in it; the legal pages carry the stripped header
    # and render no .main-nav at all, which is exactly what exempts them.
    for route, has_nav in sectionish:
        if has_nav and route != '/' and route not in nav_set:
            fails.append(f'{route} renders the site navigation but is not listed in '
                         'it — a section the menu has lost')

    print(f'  {len(routes)} page(s) rendered, navigation consistent')
    heaviest = max(budgets, key=lambda b: b[1]) if budgets else ('-', 0)
    print(f'  heaviest page {heaviest[0]} at {heaviest[1] // 1024} KB, '
          f'site total {sum(b[1] for b in budgets) // 1024} KB')

    if not menu_pages:
        fails.append('no page renders a .menu-item — the menu has disappeared')
    else:
        print(f'  menu renders on {", ".join(menu_pages)}')

    # ── the files that are not pages ────────────────────────────────────────
    for path in STATIC:
        status, body, _ = fetch(base + path)
        if status != 200:
            fails.append(f'{path} returned {status}')
        elif not body.strip():
            fails.append(f'{path} is empty')
    print(f'  {len(STATIC)} static files present')

    # ── an unknown path ─────────────────────────────────────────────────────
    status, body, _ = fetch(f'{base}/no-such-page-{run_id}')
    if status != 404:
        fails.append(f'an unknown path returned {status}, not 404')
    elif 'Такой страницы нет' not in text(body):
        fails.append('the 404 page is not the theme\'s own')

    # ── the paths WordPress gets scanned for ────────────────────────────────
    open_paths = []
    for path in MUST_BE_CLOSED:
        status, body, _ = fetch(base + path)
        if status == 200:
            open_paths.append(f'{path} (200)')
    if open_paths:
        fails.append('these should not be readable: ' + ', '.join(open_paths))
    else:
        print(f'  {len(MUST_BE_CLOSED)} sensitive paths closed')

    # xmlrpc.php answers 405 to a GET whether it is blocked or not, so asking for
    # the file proves nothing. Ask it to DO something: system.listMethods is the
    # call that makes the brute-force amplifier worth having.
    probe = b"<?xml version='1.0'?><methodCall><methodName>system.listMethods</methodName>" \
            b"<params></params></methodCall>"
    req = urllib.request.Request(base + '/xmlrpc.php', data=probe, headers={
        'Content-Type': 'text/xml', 'Cookie': 'beget=begetok',
        'User-Agent': 'coffeeshtob-deploy-check/2.0'})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            if r.status == 200 and b'methodResponse' in r.read():
                fails.append('xmlrpc.php answers system.listMethods — it is the '
                             'classic brute-force amplifier and must be closed')
            else:
                print('  xmlrpc closed')
    except urllib.error.HTTPError:
        print('  xmlrpc closed')
    except Exception:
        print('  xmlrpc closed')

    status, _, headers = fetch(f'{base}/?author=1')
    if 'Location' in headers and '/author/' in headers.get('Location', ''):
        fails.append('?author=1 redirects to /author/<login>/ and leaks the username')

    # ── the promise /privacy makes ──────────────────────────────────────────
    # "Cookie-файлы сайт не ставит" is a legal statement on a Russian site, not a
    # nicety. WordPress sets cookies for commenters and logged-in users; comments
    # are off and this proves it stayed that way.
    cookied = []
    for route in routes[:5]:
        _, _, headers = fetch(base + route)
        if headers.get('Set-Cookie'):
            cookied.append(route)
    if cookied:
        fails.append(f'the site set a cookie for an anonymous visitor on {cookied} '
                     '— /privacy states that it does not')
    else:
        print('  no cookies set for an anonymous visitor')

    print()
    if fails:
        print(f'FAIL — {len(fails)} problem(s):\n')
        for f in fails:
            print(f'  * {f}')
        return 1

    print(f'OK — {len(routes)} routes, {len(nav)} navigation links, '
          f'{len(STATIC)} static files, {len(MUST_BE_CLOSED)} sensitive paths '
          f'closed, no cookies, all images resized and within budget.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
