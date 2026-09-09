#!/usr/bin/env bash
# Build a real WordPress to develop the theme against.
#
# WHY THIS EXISTS. The theme is PHP that runs inside WordPress: page templates,
# meta boxes, the Customiser, a post type. None of it can be checked by reading
# it, and the previous two versions of this site each shipped a bug that a single
# page load would have caught — a Grav 1.x variable name that rendered every
# footer address blank on the live site for weeks, and a template that resolved
# to a blank body.
#
# It runs on SQLite rather than MySQL, through WordPress's own
# sqlite-database-integration drop-in, so there is no database server to install.
# Production uses MySQL on Beget; the theme cannot tell the difference.
#
#   ./.github/scripts/wp-dev.sh          build (or rebuild) .wp-dev/
#   ./.github/scripts/wp-dev.sh --seed   ... and load the starting content
#
# Then start it from the editor's preview (.claude/launch.json), or by hand:
#   php -d realpath_cache_size=0 -S 127.0.0.1:8766 -t .wp-dev .wp-dev/dev-router.php
#
# realpath_cache_size=0 IS NOT DECORATION. PHP caches path→inode for 120 seconds,
# and an editor that saves atomically (write a temp file, rename it over the
# original) changes the inode. Without this, edits appear to have no effect for
# up to two minutes, and worse, a test run against them silently measures the OLD
# code. That produced three flatly wrong mutation-test results before it was
# found — the same class of false result that stale browser caches produced twice
# earlier in this project.

set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DEV="$REPO/.wp-dev"
CACHE="${TMPDIR:-/tmp}/coffeeshtob-wp-cache"
PORT=8766
SEED=0
[ "${1:-}" = "--seed" ] && SEED=1

mkdir -p "$CACHE"

# The Russian build, so the client's admin is in Russian from the first login
# rather than after somebody finds Settings → Site Language.
if [ ! -f "$CACHE/wordpress.tar.gz" ]; then
  echo "downloading WordPress (ru_RU)…"
  curl -sSfL --max-time 180 -o "$CACHE/wordpress.tar.gz" \
    https://ru.wordpress.org/latest-ru_RU.tar.gz
fi
if [ ! -f "$CACHE/sqlite.zip" ]; then
  echo "downloading the SQLite drop-in…"
  curl -sSfL --max-time 120 -o "$CACHE/sqlite.zip" \
    https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
fi

echo "building $DEV …"
rm -rf "$DEV" "$CACHE/unpacked"
mkdir -p "$DEV" "$CACHE/unpacked"
tar -xzf "$CACHE/wordpress.tar.gz" -C "$CACHE/unpacked"
cp -R "$CACHE/unpacked/wordpress/." "$DEV/"
unzip -q -o "$CACHE/sqlite.zip" -d "$CACHE/unpacked/sq"
cp -R "$CACHE/unpacked/sq/sqlite-database-integration" "$DEV/wp-content/plugins/"

# The drop-in ships as a template with two placeholders.
cp "$DEV/wp-content/plugins/sqlite-database-integration/db.copy" "$DEV/wp-content/db.php"
php -r '
$f = $argv[1];
$s = file_get_contents($f);
$s = str_replace("{SQLITE_IMPLEMENTATION_FOLDER_PATH}",
                 dirname($f) . "/plugins/sqlite-database-integration", $s);
$s = str_replace("{SQLITE_PLUGIN}", "sqlite-database-integration/load.php", $s);
file_put_contents($f, $s);
' "$DEV/wp-content/db.php"

cat > "$DEV/wp-config.php" <<PHP
<?php
// Local development only. Rebuilt by .github/scripts/wp-dev.sh; never deployed.
// The DB_* values are ignored — wp-content/db.php routes everything to SQLite.
define('DB_NAME', 'wordpress');
define('DB_USER', '');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY',
          'AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as \$k) {
    define(\$k, 'dev-only-' . \$k);
}
\$table_prefix = 'wp_';

// Notices and warnings are displayed rather than hidden: verify-site.py fails a
// deploy on error text in a page, so they must be visible here first.
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);

define('WP_HOME', 'http://127.0.0.1:$PORT');
define('WP_SITEURL', 'http://127.0.0.1:$PORT');
define('WPLANG', 'ru_RU');
define('FS_METHOD', 'direct');
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
PHP

# The theme is a symlink to the working tree, so an edit is live on the next
# request with no copy step to forget.
rm -rf "$DEV/wp-content/themes/coffeeshtob"
ln -s "$REPO/wp-content/themes/coffeeshtob" "$DEV/wp-content/themes/coffeeshtob"

# The docroot files, so a local run rehearses the real one rather than something
# nearby: without them verify-site.py reports the Yandex token missing.
cp "$REPO/web-root/favicon.ico" "$REPO/web-root/robots.txt" \
   "$REPO/web-root/yandex_11df7f8b41641d66.html" "$DEV/"

cat > "$DEV/dev-router.php" <<'PHP'
<?php
/**
 * Router for PHP's built-in server.
 *
 * Apache sends unknown paths to index.php through .htaccess; the built-in server
 * has no rewrite engine, so pretty permalinks 404 without this. Real files —
 * /wp-admin/*.php, the theme's CSS, the uploads — must still be served directly,
 * which is what `return false` asks the server to do.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

/**
 * The paths web-root/.htaccess denies on the real server. Repeated here because
 * the built-in server has no .htaccess, and without them a local run of
 * verify-site.py reports wp-config.php and readme.html as publicly readable —
 * true of this dev server, false of the deployed site. Local should rehearse
 * production, not a different thing that happens to be nearby.
 */
foreach (['/wp-config.php', '/wp-config-sample.php', '/readme.html',
          '/license.txt', '/xmlrpc.php'] as $denied) {
    if (strcasecmp($path, $denied) === 0) {
        http_response_code(403);
        exit;
    }
}
if (preg_match('#^/wp-content/uploads/.*\.(php|phtml|phar)$#i', $path)) {
    http_response_code(403);
    exit;
}

$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file)) {
    if (is_file($file)) return false;
    if (is_dir($file) && file_exists($file . '/index.php')) {
        $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
        require $file . '/index.php';
        return true;
    }
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
PHP

echo "installing WordPress…"
( cd "$DEV" && php -r '
$_SERVER["HTTP_HOST"] = "127.0.0.1:'"$PORT"'";
$_SERVER["REQUEST_URI"] = "/";
$_SERVER["SERVER_NAME"] = "127.0.0.1";
define("WP_INSTALLING", true);
require_once "wp-load.php";
require_once ABSPATH . "wp-admin/includes/upgrade.php";
$r = wp_install("Кофештаб", "shtob", "dev@example.invalid", true, "", "devpassword");
echo "  admin: shtob / devpassword\n";
' )

( cd "$DEV" && php -r '
$_SERVER["HTTP_HOST"] = "127.0.0.1:'"$PORT"'";
$_SERVER["REQUEST_URI"] = "/";
$_SERVER["SERVER_NAME"] = "127.0.0.1";
require_once "wp-load.php";
switch_theme("coffeeshtob");
echo "  theme: ", wp_get_theme()->get("Name"), "\n";
' )

if [ "$SEED" = "1" ]; then
  echo "loading the starting content…"
  ( cd "$DEV" && php -r '
  $_SERVER["HTTP_HOST"] = "127.0.0.1:'"$PORT"'";
  $_SERVER["REQUEST_URI"] = "/wp-admin/";
  $_SERVER["SERVER_NAME"] = "127.0.0.1";
  $_SERVER["REQUEST_METHOD"] = "GET";
  require_once "wp-load.php";
  wp_set_current_user(1);
  require_once ABSPATH . "wp-admin/includes/admin.php";
  foreach (shtob_seed_run() as $line) echo "  $line\n";
  ' )
fi

echo
echo "ready. Start it with the editor preview, or:"
echo "  php -d realpath_cache_size=0 -S 127.0.0.1:$PORT -t .wp-dev .wp-dev/dev-router.php"
