Files here are deployed to the Grav web ROOT, not into the theme.

Each one is here because something OUTSIDE this site asks for it by absolute
path, so serving it from /user/themes/coffeeshtob/images/ would 404:

  robots.txt, sitemap.xml    crawlers fetch these at the root by convention
  llms.txt                   same
  favicon.ico                browsers and crawlers fetch /favicon.ico blindly
  apple-touch-icon.png       iOS fetches /apple-touch-icon.png blindly
  icon-192.png, icon-512.png site.webmanifest lists them as "/icon-192.png" —
                             root-absolute — so they must exist at the root even
                             though the manifest itself is served from the theme
  og-image.jpg               the static site advertised
                             https://coffeeshtob.ru/og-image.jpg for months, and
                             Telegram and VK have that URL cached against every
                             link ever shared
  yandex_...html             Yandex fetches its verification token at a fixed URL
  .htaccess                  Grav's own, plus this site's redirects

They are copies of files that also exist elsewhere in the repo. That duplication
is deliberate and cheap: the alternative is a deploy step that reaches into
other directories to assemble a root, which is harder to see and easier to break
than eight copied files.

.htaccess is NEVER deployed to staging — it forces HTTPS and staging is plain
http, so it would break that site. See the note in deploy-staging.yml.
