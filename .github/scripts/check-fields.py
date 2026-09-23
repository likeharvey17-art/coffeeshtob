#!/usr/bin/env python3
"""Prove the theme's field names agree everywhere they appear.

THE BUG THIS EXISTS TO CATCH IS THE OLDEST ONE IN THIS PROJECT, and it has now
survived three platforms. Under Decap a field name lived in the markup, the JSON
and the CMS config; under Grav in the template, the blueprint and the page. In
both, renaming it in one place produced no error at all — the value simply
stopped appearing on the live site, and nobody noticed for weeks.

WordPress makes it quieter still: get_post_meta() on a key that does not exist
returns an empty string, so a typo is a blank section with a 200 response.

inc/fields.php is the single declaration, which removes most of the danger. What
remains is drift between that declaration and the four things that consume it:
the templates, the seeder, the icon partial and the page-template files. That is
what this checks.

Run:  python3 .github/scripts/check-fields.py
"""
import os
import re
import sys

THEME = 'wp-content/themes/coffeeshtob'
FIELDS = f'{THEME}/inc/fields.php'
ICONS = f'{THEME}/parts/icon.php'
SEED = f'{THEME}/seed/content.php'

# Keys the theme reads that are NOT declared in shtob_field_groups(): WordPress's
# own, and the two the seeder sets directly.
NOT_DECLARED = {'wp_page_template'}

# Fields declared for the editor but deliberately never read by a template.
# Empty on purpose — an entry here is a claim that a field is write-only, which
# is almost always a bug, so adding one should require saying why.
WRITE_ONLY: dict[str, str] = {}


def read(path):
    with open(path, encoding='utf-8') as fh:
        return fh.read()


def strip_comments(src):
    """PHP source with comments removed.

    Needed because these files are heavily commented and several comments NAME
    the very functions being checked for. The "is the navigation still
    generated?" test passed on footer.php's own docblock — which says the nav
    comes from shtob_nav() — while the actual call had been replaced by a
    hardcoded link. Caught by mutation testing, not by reading.
    """
    src = re.sub(r'/\*.*?\*/', '', src, flags=re.S)
    src = re.sub(r'^\s*//.*$', '', src, flags=re.M)
    return src


def php_files(root):
    for base, _dirs, names in os.walk(root):
        if os.path.basename(base) == 'seed':
            continue
        for n in sorted(names):
            if n.endswith('.php'):
                yield os.path.join(base, n)


def declared_fields(src):
    """Field keys from shtob_field_groups(), with the group each belongs to."""
    out = {}
    group = None
    for line in src.splitlines():
        m = re.match(r"\s{8}'([a-z0-9_]+)' => \[$", line)
        if m:
            group = m.group(1)
            continue
        m = re.match(r"\s{16}'([a-z0-9_]+)'\s*=> \['type'", line)
        if m and group:
            out[m.group(1)] = group
    return out


def declared_groups(src):
    """group -> the `screen` value, so template names can be checked."""
    out = {}
    group = None
    for line in src.splitlines():
        m = re.match(r"\s{8}'([a-z0-9_]+)' => \[$", line)
        if m:
            group = m.group(1)
            continue
        m = re.match(r"\s{12}'screen'\s*=> (.+),$", line)
        if m and group:
            out[group] = re.findall(r"'([^']+)'", m.group(1))
    return out


def used_keys(paths):
    """_shtob_<key> read or written anywhere in the theme's PHP."""
    found = {}
    for p in paths:
        for m in re.finditer(r"'_shtob_([a-z0-9_]+)'", read(p)):
            found.setdefault(m.group(1), set()).add(p)
        # The front page builds its two button keys by concatenation.
        for m in re.finditer(r"'_shtob_'\s*\.\s*\$k\s*\.\s*'_(text|url)'", read(p)):
            for prefix in ('cta1', 'cta2'):
                found.setdefault(f'{prefix}_{m.group(1)}', set()).add(p)
    return found


def icon_names(src):
    return set(re.findall(r"^\s{4}'([a-z]+)'\s*=>", src, re.M))


def icon_choices(src):
    block = re.search(r"function shtob_icon_choices\(\) \{(.*?)\n\}", src, re.S)
    if not block:
        return set()
    return {n for n in re.findall(r"'([a-z]*)'\s*=>", block.group(1)) if n}


def seed_meta_keys(src):
    """Keys the seed writes: the meta map plus the ones set by name."""
    keys = set(re.findall(r"update_post_meta\(\$id, '_shtob_([a-z0-9_]+)'", src))
    return keys


def seed_content_keys(src):
    """Keys inside each page's 'meta' => [...] block in seed/content.php."""
    keys = set()
    depth_meta = False
    for line in src.splitlines():
        if re.match(r"\s+'meta' => \[$", line):
            depth_meta = True
            continue
        if depth_meta:
            if re.match(r"\s+\],$", line):
                depth_meta = False
                continue
            m = re.match(r"\s+'([a-z0-9_]+)' => ", line)
            if m:
                keys.add(m.group(1))
    return keys


def seed_templates(src):
    return set(re.findall(r"'template' => '([^']*)'", src))


def main():
    problems = []
    notes = []

    fields_src = read(FIELDS)
    declared = declared_fields(fields_src)
    groups = declared_groups(fields_src)
    if len(declared) < 20:
        problems.append(f'only {len(declared)} fields parsed out of {FIELDS} — the '
                        'parser has drifted from the file, which would make every '
                        'check below pass vacuously')

    theme_php = list(php_files(THEME))
    used = used_keys(theme_php)

    # 1. Everything the theme reads must be declared, or the editor can never set it.
    for key, where in sorted(used.items()):
        if key in declared or key in NOT_DECLARED:
            continue
        files = ', '.join(sorted(os.path.relpath(w, THEME) for w in where))
        problems.append(f'field "{key}" is read in {files} but declared nowhere — '
                        'it will always be empty')

    # 2. Everything declared must be read, or the client fills in a field that
    #    does nothing.
    for key, group in sorted(declared.items()):
        if key in used or key in WRITE_ONLY:
            continue
        problems.append(f'field "{key}" (group "{group}") is offered in the editor '
                        'but no template reads it')

    # 3. The screens a group targets must be real template files.
    for group, screens in sorted(groups.items()):
        for s in screens:
            if s in ('card', 'page', 'front'):
                continue
            if not s.startswith('template-'):
                problems.append(f'group "{group}" targets unknown screen "{s}"')
            elif not os.path.exists(os.path.join(THEME, s)):
                problems.append(f'group "{group}" targets "{s}", which does not exist')

    # 4. The icon dropdown and the icon partial must offer the same names, or the
    #    client picks an icon that renders nothing.
    names = icon_names(read(ICONS))
    choices = icon_choices(fields_src)
    for n in sorted(choices - names):
        problems.append(f'icon "{n}" is offered in the dropdown but parts/icon.php '
                        'has no SVG for it — it would render nothing')
    for n in sorted(names - choices):
        problems.append(f'icon "{n}" exists in parts/icon.php but is not offered in '
                        'the dropdown — the client can never choose it')

    # 5. The seed must only write keys the editor also knows about, or its content
    #    becomes uneditable.
    if os.path.exists(SEED):
        seed_src = read(SEED)
        for key in sorted(seed_content_keys(seed_src)):
            if key not in declared:
                problems.append(f'seed/content.php sets "{key}", which is not a '
                                'declared field — that content could never be edited')
        for tpl in sorted(t for t in seed_templates(seed_src) if t):
            if not os.path.exists(os.path.join(THEME, tpl)):
                problems.append(f'seed/content.php assigns template "{tpl}", '
                                'which does not exist')
        notes.append(f'{len(seed_content_keys(seed_src))} seeded fields')
    else:
        notes.append('seed already removed')

    # 6. The navigation must stay generated. Three hardcoded copies of it is what
    #    the previous two builds each started with, and both had drifted.
    for p in theme_php:
        src = read(p)
        if re.search(r'href="#(about|menu|life|schedule|guests|contacts)"', src):
            problems.append(f'{os.path.relpath(p, THEME)} contains an in-page nav '
                            'anchor — the sections are real pages now')
        # The footer carries no navigation since 1.3.0; what must never come
        # back anywhere is a section link typed out by hand.
        if re.search(r'href="[^"]*/(okrestnosti|kuhnya|gostinaya|komanda|mastera|skazki|kontakty)/?"',
                     strip_comments(src)):
            problems.append(f'{os.path.relpath(p, THEME)} links a section by a '
                            'hardcoded URL — is the navigation hardcoded again?')
        if os.path.basename(p) in ('header.php', '404.php'):
            if 'shtob_nav(' not in strip_comments(src):
                problems.append(f'{os.path.relpath(p, THEME)} does not call '
                                'shtob_nav() — is the navigation hardcoded again?')

    # 7. The version string is what reaches a returning visitor past nginx's
    #    week-long cache, so it must exist and be a real version.
    fn = read(f'{THEME}/functions.php')
    m = re.search(r"define\('SHTOB_VERSION', '([^']+)'\)", fn)
    if not m:
        problems.append('SHTOB_VERSION is not defined in functions.php')
    elif not re.match(r'^\d+\.\d+\.\d+$', m.group(1)):
        problems.append(f'SHTOB_VERSION "{m.group(1)}" is not a version number')

    if problems:
        print(f'FAIL — {len(problems)} problem(s):\n')
        for p in problems:
            print(f'  * {p}')
        return 1

    print(f'OK — {len(declared)} fields across {len(groups)} groups, '
          f'{len(names)} icons, {len(theme_php)} PHP files; '
          + ', '.join(notes) + '; navigation generated everywhere.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
