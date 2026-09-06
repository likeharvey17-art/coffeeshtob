# Why grav/root/ exists

**This file deliberately sits OUTSIDE `grav/root/`.** Everything in that
directory is copied to the web root, so a note left in there would have been
served at `https://coffeeshtob.ru/README-why-these-files.txt`. Grav's rules
block `.md` and a few named files, but not an arbitrary `.txt`. Nothing in it
was secret; publishing your own build notes on a client's site is just
sloppy. **Anything added to `grav/root/` is public — check before adding.**

Each file is there because something OUTSIDE this site asks for it by
absolute path, so serving it from `/user/themes/coffeeshtob/images/` would
404:

| file | why it must be at the root |
|---|---|
| `robots.txt`, `sitemap.xml`, `llms.txt` | crawlers fetch these at the root by convention |
| `favicon.ico` | browsers and crawlers fetch `/favicon.ico` blindly |
| `apple-touch-icon.png` | iOS fetches `/apple-touch-icon.png` blindly |
| `icon-192.png`, `icon-512.png` | `site.webmanifest` lists them as `/icon-192.png` — root-absolute — so they must exist at the root even though the manifest itself is served from the theme |
| `og-image.jpg` | the static site advertised `https://coffeeshtob.ru/og-image.jpg` for months, and Telegram and VK have that URL cached against every link ever shared |
| `yandex_...html` | Yandex fetches its verification token at a fixed URL |
| `.htaccess` | Grav's own rules, plus this site's redirects |

These duplicate files that also exist elsewhere in the repo. The duplication
is deliberate and cheap: the alternative is a deploy step that reaches into
other directories to assemble a root, which is harder to see and easier to
break than eight copied files.

`.htaccess` is **never** deployed to staging — it forces HTTPS and staging is
plain http, so it would break that site. See the note in `deploy-staging.yml`.
