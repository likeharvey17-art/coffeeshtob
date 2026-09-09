#!/usr/bin/env python3
"""Assert every page template, its editing form and its content agree.

Three files have to name the same fields, and nothing in Grav complains when
they drift: Twig prints an empty string for a key that is not there, so a
renamed field shows up as a silently blank section on the live site rather than
as an error.

  templates/<name>.html.twig    what gets rendered      page.header.X / item.X
  blueprints/<name>.yaml        what the client edits   header.X / .X
  pages/NN.slug/<name>.md       what is actually set    top-level YAML keys

THE TRIPLES ARE DISCOVERED, NOT LISTED. This used to be three hardcoded paths
pointing at the home page. With eight pages that would have left seven of them
unchecked — and unchecked is exactly where the silent blank section comes from.
A new page is now covered the moment its files exist; a template with no
blueprint, or a page whose template is missing, is itself reported.

Templates share fields through includes (partials/banner reads `eyebrow` and
`intro` for every page that includes it), so each template's field set is
resolved through its `extends` and `include` chain rather than read from one
file. That is the difference between checking what a page renders and
maintaining a list of exceptions that goes stale.

Exit 1 lists every mismatch. Run it from the repo root.
"""
import os
import re
import sys

THEME = 'grav/user/themes/coffeeshtob'
TPL_DIR = f'{THEME}/templates'
BP_DIR = f'{THEME}/blueprints'
PAGES = 'grav/user/pages'
THEME_BLUEPRINT = f'{THEME}/blueprints.yaml'
THEME_CONFIG = f'{THEME}/coffeeshtob.yaml'

# Templates that are not client-editable page types: `default` is the safety net
# for a page Grav routes to a template we do not provide, and the legal pages
# carry their copy in the template on purpose (see the note in privacy.html.twig).
NOT_PAGE_TYPES = {'default', 'privacy', 'error'}

# Keys Grav owns. They are set in the front matter but are not content fields,
# so no blueprint or template needs to name them.
GRAV_OWNED = {'title', 'menu', 'visible', 'routes', 'template', 'taxonomy',
              'published', 'date', 'slug', 'process', 'media_order'}


def read(path):
    with open(path, encoding='utf-8') as fh:
        return fh.read()


def resolve(template, seen=None):
    """A template's own source plus everything it extends or includes."""
    seen = seen if seen is not None else set()
    path = f'{TPL_DIR}/{template}.html.twig'
    if template in seen or not os.path.exists(path):
        return ''
    seen.add(template)
    src = read(path)
    out = [src]
    for ref in re.findall(r"{%\s*(?:extends|include)\s*'([^']+)'", src):
        out.append(resolve(ref.replace('.html.twig', ''), seen))
    return '\n'.join(out)


def twig_fields(src):
    """Top-level fields rendered, and the per-item keys of each loop."""
    top = set(re.findall(r'page\.header\.([a-z0-9_]+)', src))
    loops = {}
    for m in re.finditer(r'{%\s*for\s+(\w+)\s+in\s+page\.header\.([a-z0-9_]+)\s*%}', src):
        var, field = m.group(1), m.group(2)
        # Body of this loop, up to its matching endfor. The templates do not
        # nest for-loops, so the next endfor is the right one.
        body = src[m.end():]
        body = body[:body.index('{% endfor %}')]
        # A field can be looped over more than once — `hours` is rendered as a
        # table and read again for the JSON-LD — so keys accumulate across loops
        # rather than overwrite. Assigning here instead made the second loop hide
        # the first one's fields, and the check then reported the page's own
        # visible columns as missing.
        loops.setdefault(field, set()).update(
            re.findall(rf'\b{var}\.([a-z0-9_]+)', body))
    return top, loops


def blueprint_fields(path):
    top, lists, current = set(), {}, None
    for line in read(path).splitlines():
        m = re.match(r'\s*header\.([a-z0-9_]+):\s*$', line)
        if m:
            current = m.group(1)
            top.add(current)
            continue
        m = re.match(r'\s*\.([a-z0-9_]+):\s*$', line)
        if m and current:
            lists.setdefault(current, set()).add(m.group(1))
    return top, lists


def page_fields(path):
    src = read(path)
    parts = src.split('---')
    fm = parts[1] if len(parts) > 2 else ''
    top, lists, current = set(), {}, None
    for line in fm.splitlines():
        m = re.match(r'([a-z0-9_]+):', line)
        if m:
            current = m.group(1)
            top.add(current)
            continue
        m = re.match(r'\s+-?\s*([a-z0-9_]+):', line)
        if m and current:
            lists.setdefault(current, set()).add(m.group(1))
    return top, lists


def cross_page_fields(home_src):
    """Fields one page renders out of ANOTHER page's front matter.

    Two of these exist and both are invisible to a per-template comparison:

      * the landing grid loops `pages.children` and renders each section's own
        `card_image` and `card_teaser`;
      * the structured data reaches into /kontakty with `pages.find` and loops
        its `hours` for `days` / `opens` / `closes`.

    Without this, every one of those fields is reported twice — "in the form but
    not in the template" on the page that owns it, and "set but nothing renders
    it" on the page file. They are rendered; just not by their own template.

    Returns (fields rendered for every section, {route: (fields, {list: keys})}).
    """
    def loop_body(src, start):
        body = src[start:]
        return body[:body.index('{% endfor %}')]

    every = set()
    for m in re.finditer(r'{%\s*for\s+(\w+)\s+in\s+pages\.children[^%]*%}', home_src):
        every |= set(re.findall(rf'\b{m.group(1)}\.header\.([a-z0-9_]+)',
                                loop_body(home_src, m.end())))

    by_route = {}
    for m in re.finditer(r"{%\s*set\s+(\w+)\s*=\s*pages\.find\('([^']+)'\)\s*%}", home_src):
        var, route = m.group(1), m.group(2)
        top = set(re.findall(rf'\b{var}\.header\.([a-z0-9_]+)', home_src))
        lists = {}
        for lm in re.finditer(rf'{{%\s*for\s+(\w+)\s+in\s+{var}\.header\.([a-z0-9_]+)\s*%}}', home_src):
            lists.setdefault(lm.group(2), set()).update(
                re.findall(rf'\b{lm.group(1)}\.([a-z0-9_]+)', loop_body(home_src, lm.end())))
        by_route[route] = (top, lists)
    return every, by_route


def page_route(folder, src):
    m = re.search(r'^\s*default:\s*(/\S+)\s*$', src, re.M)
    return m.group(1) if m else '/' + re.sub(r'^\d+\.', '', folder)


def discover():
    """Every (template, blueprint, [page files]) triple on disk."""
    templates = sorted(
        f[:-len('.html.twig')] for f in os.listdir(TPL_DIR)
        if f.endswith('.html.twig')
    )
    templates = [t for t in templates if t not in NOT_PAGE_TYPES]

    pages = {}
    for folder in sorted(os.listdir(PAGES)):
        d = os.path.join(PAGES, folder)
        if not os.path.isdir(d):
            continue
        for name in sorted(os.listdir(d)):
            if name.endswith('.md'):
                pages.setdefault(name[:-3], []).append(os.path.join(d, name))
    return templates, pages


def main():
    problems = []
    templates, pages = discover()
    home_src = resolve('home')
    cross_every, cross_by_route = cross_page_fields(home_src)

    # Which extra fields each page file legitimately carries, because some other
    # page's template renders them.
    extra_for = {}
    for folder in sorted(os.listdir(PAGES)):
        d = os.path.join(PAGES, folder)
        if not os.path.isdir(d):
            continue
        for name in sorted(os.listdir(d)):
            if not name.endswith('.md'):
                continue
            path = os.path.join(d, name)
            route = page_route(folder, read(path))
            top = set() if name[:-3] == 'home' else set(cross_every)
            lists = {}
            if route in cross_by_route:
                rt, rl = cross_by_route[route]
                top |= rt
                for k, v in rl.items():
                    lists.setdefault(k, set()).update(v)
            extra_for[path] = (top, lists)

    checked_fields = checked_lists = 0
    for tpl in templates:
        src = resolve(tpl)
        t_top, t_lists = twig_fields(src)
        bp = f'{BP_DIR}/{tpl}.yaml'
        if not os.path.exists(bp):
            problems.append(f'{tpl}: has a template but no blueprints/{tpl}.yaml — '
                            f'the client would get a raw YAML box')
            continue
        b_top, b_lists = blueprint_fields(bp)
        if tpl not in pages:
            problems.append(f'{tpl}: has a template and a form but no page uses it')
            continue

        # Loop variables are not fields; drop the list names themselves.
        t_top -= set(t_lists)
        b_top -= set(b_lists)
        checked_fields += len(t_top)
        checked_lists += len(t_lists)

        def cmp(kind, a, a_label, b, b_label):
            for f in sorted(a - b):
                problems.append(f'{tpl} {kind}: `{f}` is in {a_label} but not in {b_label}')

        # Fields rendered by another page's template still belong in this
        # page's form — that is where the client fills them in.
        elsewhere_top, elsewhere_lists = set(), {}
        for page_path in pages[tpl]:
            et, el = extra_for[page_path]
            elsewhere_top |= et
            for k, v in el.items():
                elsewhere_lists.setdefault(k, set()).update(v)

        cmp('field', t_top, 'the template', b_top, 'the editing form')
        cmp('field', b_top - elsewhere_top, 'the editing form', t_top, 'the template')

        for page_path in pages[tpl]:
            p_top, p_lists = page_fields(page_path)
            p_top -= set(p_lists)
            where = os.path.relpath(page_path, PAGES)
            et, el = extra_for[page_path]
            # A field the page does not set is only a problem if the form does
            # not offer it either — the client fills those in. A field the page
            # DOES set that nothing renders is always drift.
            for f in sorted(t_top - p_top - b_top):
                problems.append(f'{tpl} field: `{f}` is rendered but is in neither '
                                f'{where} nor the editing form')
            for f in sorted(p_top - t_top - et - GRAV_OWNED):
                problems.append(f'{tpl} field: `{f}` is set in {where} but nothing renders it')
            for name in sorted(set(t_lists) | set(el)):
                want = t_lists.get(name, set()) | el.get(name, set())
                if name in p_lists:
                    for f in sorted(p_lists[name] - want - b_lists.get(name, set())):
                        problems.append(f'{tpl} {name} item: `{f}` is set in {where} '
                                        f'but nothing renders it')

        for name in sorted(set(t_lists) | set(b_lists)):
            if name not in t_lists:
                problems.append(f'{tpl} list: `{name}` is never looped over in the template')
                continue
            if name not in b_lists:
                problems.append(f'{tpl} list: `{name}` has no fields in the editing form')
                continue
            cmp(f'{name} item', t_lists[name], 'the template', b_lists[name], 'the editing form')
            cmp(f'{name} item', b_lists[name] - elsewhere_lists.get(name, set()),
                'the editing form', t_lists[name], 'the template')

    # ── Every section must give the landing grid something to show ───────────
    # The grid renders each section's own card_image and card_teaser. A section
    # that leaves them empty appears on the landing page with a grey placeholder
    # and no description, and nothing else would catch it.
    for tpl in templates:
        if tpl == 'home':
            continue
        for page_path in pages.get(tpl, []):
            where = os.path.relpath(page_path, PAGES)
            src = read(page_path)
            for f in sorted(cross_every):
                if not re.search(rf'^{f}:', src, re.M):
                    problems.append(f'landing page: `{f}` is rendered for every section '
                                    f'but is not set in {where}')

    # ── Cross-page lookups have to resolve ────────────────────────────────────
    # home.html.twig reaches into /kontakty for the opening hours that feed the
    # structured data. Rename that page and the hours silently vanish from search
    # results, which is invisible from the site itself.
    routes = {}
    for folder in sorted(os.listdir(PAGES)):
        d = os.path.join(PAGES, folder)
        if not os.path.isdir(d):
            continue
        for name in sorted(os.listdir(d)):
            if not name.endswith('.md'):
                continue
            route = page_route(folder, read(os.path.join(d, name)))
            routes.setdefault(route, []).append(folder)
    for route, folders in sorted(routes.items()):
        if len(folders) > 1:
            problems.append(f'route: {route} is claimed by {" and ".join(folders)}')
    for m in re.finditer(r"pages\.find\('([^']+)'\)", home_src):
        if m.group(1) not in routes:
            problems.append(f'template: pages.find(\'{m.group(1)}\') resolves to no page')

    # ── The nav is generated; nothing may reintroduce a hardcoded copy ────────
    nav_src = read(f'{TPL_DIR}/partials/nav.html.twig')
    if 'pages.children' not in nav_src:
        problems.append('nav: partials/nav.html.twig no longer builds from the page tree')
    for partial in ('header', 'footer'):
        src = read(f'{TPL_DIR}/partials/{partial}.html.twig')
        stale = re.findall(r'href="#(about|menu|life|schedule|guests|contacts)"', src)
        if stale:
            problems.append(f'nav: {partial} still links to in-page anchors {sorted(set(stale))} — '
                            f'those sections are separate pages now')

    # ── Contact details live in theme config, not on a page ───────────────────
    # The footer renders on every page and the phone number appears in three
    # places. Every `theme.X` a template renders must exist both in the theme's
    # form and in its defaults, or the footer goes blank and nothing says why.
    #
    # It is `theme`, NOT `theme_config`. That was the Grav 1.x name; it does not
    # exist in Grav 2 and rendered empty everywhere, which is how staging came to
    # serve a blank address and an empty tel: link without anyone noticing.
    all_templates = '\n'.join(
        read(os.path.join(dirpath, f))
        for dirpath, _, files in os.walk(TPL_DIR)
        for f in files if f.endswith('.twig')
    )
    if 'theme_config.' in all_templates:
        problems.append('theme: `theme_config` does not exist in Grav 2 — use `theme.` '
                        '(it renders empty with no error)')
    used = set(re.findall(r'\btheme\.([a-z0-9_]+)', all_templates))
    form = set(re.findall(r'^\s{8}([a-z0-9_]+):\s*$', read(THEME_BLUEPRINT), re.M))
    values = set(re.findall(r'^([a-z0-9_]+):', read(THEME_CONFIG), re.M)) - {'enabled'}
    for f in sorted(used - form):
        problems.append(f'theme: `{f}` is rendered but is not in the theme form')
    for f in sorted(used - values):
        problems.append(f'theme: `{f}` is rendered but has no default value')
    for f in sorted(form - used):
        problems.append(f'theme: `{f}` is in the theme form but nothing renders it')

    # The privacy page has no editing form on purpose, so only the template and
    # the page file are compared.
    priv_used = set(re.findall(r'page\.header\.([a-z0-9_]+)', read(f'{TPL_DIR}/privacy.html.twig')))
    priv_have = set(re.findall(r'^([a-z0-9_]+):', read(f'{PAGES}/02.privacy/privacy.md'), re.M))
    for f in sorted(priv_used - priv_have):
        problems.append(f'privacy: `{f}` is rendered but is not set in privacy.md')

    # The whole point of the port is that nothing is hardcoded any more.
    leftovers = re.findall(r'data-cms-(?:field|list|item)', all_templates)
    if leftovers:
        problems.append(f'{len(leftovers)} leftover data-cms-* attributes — those are '
                        f"Decap's, and mean copy is still hardcoded")

    if problems:
        print('Content fields disagree:\n')
        for p in problems:
            print('  ' + p)
        print(f'\n{len(problems)} problem(s).')
        return 1

    page_count = sum(len(v) for k, v in pages.items() if k in templates)
    print(f'OK — {len(templates)} page types, {page_count} pages, '
          f'{checked_fields} fields and {checked_lists} lists agree across '
          f'template, editing form and content.')
    print(f'     {len(used)} theme fields agree across template, theme form and defaults.')
    print(f'     {len(cross_every)} field(s) read across pages by the landing grid: '
          f'{", ".join(sorted(cross_every))}')
    print(f'     {len(routes)} routes, all distinct.')
    for tpl in templates:
        print(f'  {tpl}: {", ".join(os.path.relpath(p, PAGES) for p in pages.get(tpl, []))}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
