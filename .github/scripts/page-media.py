#!/usr/bin/env python3
"""List which uploaded photo belongs in which Grav page folder.

Grav resolves a page's images as PAGE MEDIA — a bare filename in the front
matter, looked up in that page's own folder. So a photo used on /kuhnya has to
physically sit in user/pages/04.kuhnya/, and the same photo used on two pages
has to sit in both.

The repo does not commit the photos twice. They live once in images/uploads/,
and this works out where each one has to be copied by reading the page files
themselves — so the mapping cannot drift from the content the way a hand-kept
manifest would. Add a photo to a page in the admin and it is covered; the only
thing that has to stay true is that the filename appears in the front matter.

Prints one `<page-dir>\t<filename>` pair per line, sorted. Used by
deploy-staging.yml to upload the photos and locally to build a test install.

Exit 1 if a page names a photo that is not in images/uploads/ — that is either
a typo or a file the client uploaded straight into Grav, and the difference
matters: the second kind will not survive a reseed onto a fresh install.
"""
import os
import re
import sys

PAGES = 'grav/user/pages'
UPLOADS = 'images/uploads'

# Any value that looks like an image filename, wherever it sits in the front
# matter — `image:`, `hero_image:`, `banner_image:`, `card_image:` and whatever
# a future blueprint calls it. Matching the VALUE rather than a list of key
# names is what keeps this working when fields are added.
IMAGE = re.compile(r"[\w.\-]+\.(?:jpe?g|png|webp|gif|svg)", re.I)


def front_matter(path):
    with open(path, encoding='utf-8') as fh:
        src = fh.read()
    parts = src.split('---')
    return parts[1] if len(parts) > 2 else ''


def main():
    if not os.path.isdir(PAGES):
        sys.exit(f'{PAGES} not found — run from the repo root')
    available = set(os.listdir(UPLOADS)) if os.path.isdir(UPLOADS) else set()

    pairs, missing = set(), []
    for entry in sorted(os.listdir(PAGES)):
        page_dir = os.path.join(PAGES, entry)
        if not os.path.isdir(page_dir):
            continue
        for name in sorted(os.listdir(page_dir)):
            if not name.endswith('.md'):
                continue
            for filename in IMAGE.findall(front_matter(os.path.join(page_dir, name))):
                if filename in available:
                    pairs.add((entry, filename))
                else:
                    missing.append((entry, name, filename))

    for page, filename in sorted(pairs):
        print(f'{page}\t{filename}')

    if missing:
        print('\nNamed by a page but not in %s:' % UPLOADS, file=sys.stderr)
        for page, name, filename in missing:
            print(f'  {page}/{name}: {filename}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
