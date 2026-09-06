#!/usr/bin/env python3
"""Assert the page template, its editing form and the seeded content agree.

Three files have to name the same fields, and nothing in Grav complains when
they drift: Twig prints an empty string for a key that is not there, so a
renamed field shows up as a silently blank section on the live site rather than
as an error. The old Decap setup had the identical three-way coupling
(index.html / home.json / admin config.yml) and CLAUDE.md still carries the
warning that renaming one of the three makes the value "silently stop being
applied".

So the coupling is checked instead of documented:

  templates/home.html.twig   what gets rendered      page.header.X / item.X
  blueprints/home.yaml       what the client edits   header.X / .X
  pages/01.home/home.md      what is actually set    top-level YAML keys

Exit 1 lists every mismatch. Run it from the repo root.
"""
import re
import sys

THEME = 'grav/user/themes/coffeeshtob'
TWIG = f'{THEME}/templates/home.html.twig'
FOOTER = f'{THEME}/templates/partials/footer.html.twig'
BASE = f'{THEME}/templates/partials/base.html.twig'
PRIVACY_TPL = f'{THEME}/templates/privacy.html.twig'
ERROR_TPL = f'{THEME}/templates/error.html.twig'
BLUEPRINT = f'{THEME}/blueprints/home.yaml'
PAGE = 'grav/user/pages/01.home/home.md'
PRIVACY_PAGE = 'grav/user/pages/02.privacy/privacy.md'
THEME_BLUEPRINT = f'{THEME}/blueprints.yaml'
THEME_CONFIG = f'{THEME}/coffeeshtob.yaml'

# Grav's own page keys, not content fields.
GRAV_OWNED = {'title'}


def read(p):
    return open(p, encoding='utf-8').read()


def twig_fields():
    """Top-level fields the templates render, and the per-item keys of each loop.

    All three templates are read, not just home.html.twig: seo_title and
    seo_description are rendered by the base layout, and scanning it is the
    difference between checking what the site renders and maintaining a list of
    exceptions that goes stale.
    """
    src = read(TWIG) + read(FOOTER) + read(BASE)
    top = set(re.findall(r'page\.header\.([a-z0-9_]+)', src))
    loops = {}
    for m in re.finditer(r'{%\s*for\s+(\w+)\s+in\s+page\.header\.([a-z0-9_]+)\s*%}', src):
        var, field = m.group(1), m.group(2)
        # Body of this loop, up to its matching endfor. The templates do not
        # nest for-loops, so the next endfor is the right one.
        body = src[m.end():]
        body = body[:body.index('{% endfor %}')]
        loops[field] = set(re.findall(rf'\b{var}\.([a-z0-9_]+)', body))
    return top, loops


def blueprint_fields():
    src = read(BLUEPRINT)
    top, lists, current = set(), {}, None
    for line in src.splitlines():
        m = re.match(r'\s*header\.([a-z0-9_]+):\s*$', line)
        if m:
            current = m.group(1)
            top.add(current)
            continue
        m = re.match(r'\s*\.([a-z0-9_]+):\s*$', line)
        if m and current:
            lists.setdefault(current, set()).add(m.group(1))
    return top, lists


def page_fields():
    src = read(PAGE)
    fm = src.split('---')[1]
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


def main():
    t_top, t_lists = twig_fields()
    b_top, b_lists = blueprint_fields()
    p_top, p_lists = page_fields()

    # Loop variables are not fields; drop the list names themselves.
    t_top -= set(t_lists)
    b_top -= set(b_lists)
    p_top -= set(p_lists)

    problems = []

    def cmp(name, a, a_label, b, b_label):
        for f in sorted(a - b):
            problems.append(f'{name}: `{f}` is in {a_label} but not in {b_label}')

    cmp('field', t_top, 'the template', b_top, 'the editing form')
    cmp('field', b_top, 'the editing form', t_top, 'the template')
    cmp('field', t_top, 'the template', p_top, 'the seeded page')
    cmp('field', p_top - GRAV_OWNED, 'the seeded page', t_top, 'the template')

    for name in sorted(set(t_lists) | set(b_lists) | set(p_lists)):
        if name not in t_lists:
            problems.append(f'list: `{name}` is never looped over in the template')
            continue
        if name not in b_lists:
            problems.append(f'list: `{name}` has no fields in the editing form')
            continue
        cmp(f'{name} item', t_lists[name], 'the template', b_lists[name], 'the editing form')
        cmp(f'{name} item', b_lists[name], 'the editing form', t_lists[name], 'the template')
        if name in p_lists:
            cmp(f'{name} item', t_lists[name], 'the template', p_lists[name], 'the seeded page')

    # Contact details live in theme config, not on a page, because the footer
    # renders on every page and the phone number appears in three places. Same
    # coupling, different files: every theme_config.X a template renders must
    # exist both in the theme's form and in its defaults, or the footer goes
    # blank and nothing says why.
    all_templates = (read(TWIG) + read(FOOTER) + read(BASE)
                     + read(PRIVACY_TPL) + read(ERROR_TPL))
    used = set(re.findall(r'theme_config\.([a-z0-9_]+)', all_templates))
    form = set(re.findall(r'^\s{8}([a-z0-9_]+):\s*$', read(THEME_BLUEPRINT), re.M))
    values = set(re.findall(r'^([a-z0-9_]+):', read(THEME_CONFIG), re.M)) - {'enabled'}
    for f in sorted(used - form):
        problems.append(f'theme_config: `{f}` is rendered but is not in the theme form')
    for f in sorted(used - values):
        problems.append(f'theme_config: `{f}` is rendered but has no default value')
    for f in sorted(form - used):
        problems.append(f'theme_config: `{f}` is in the theme form but nothing renders it')

    # The privacy page has no editing form on purpose (its copy is legal text,
    # see the note in privacy.html.twig), so only the template and the page file
    # are compared.
    priv_used = set(re.findall(r'page\.header\.([a-z0-9_]+)', read(PRIVACY_TPL)))
    priv_have = set(re.findall(r'^([a-z0-9_]+):', read(PRIVACY_PAGE), re.M))
    for f in sorted(priv_used - priv_have):
        problems.append(f'privacy: `{f}` is rendered but is not set in privacy.md')

    # A count worth asserting on its own: the whole point of the port is that
    # nothing is hardcoded any more.
    leftovers = re.findall(r'data-cms-(?:field|list|item)', read(TWIG) + read(FOOTER))
    if leftovers:
        problems.append(f'{len(leftovers)} leftover data-cms-* attributes — those are Decap\'s, and mean copy is still hardcoded')

    if problems:
        print('Content fields disagree across the three files:\n')
        for p in problems:
            print('  ' + p)
        print(f'\n{len(problems)} problem(s).')
        return 1

    print(f'OK — {len(t_top)} page fields and {len(t_lists)} lists agree across '
          f'template, editing form and seeded page;')
    print(f'     {len(used)} theme-config fields agree across template, theme form '
          f'and defaults.')
    for name in sorted(t_lists):
        print(f'  {name}: {", ".join(sorted(t_lists[name]))}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
