<?php
/**
 * Кофештаб — theme bootstrap.
 *
 * The design is fixed by this theme; the client edits CONTENT, never layout.
 * That is deliberate and is the reason this is a classic theme rather than a
 * block theme: the site was rebuilt twice to get away from the
 * rounded-rectangle-per-block look, and a full-site editor hands that back the
 * first time somebody drags a Cover block in.
 *
 * Everything the client can change lives in three places, all native WordPress:
 *   Страницы          — the eight sections, their intro copy and their photos
 *   Карточки          — the entries inside a section, plus the menu rows
 *   Внешний вид → Настроить — address, phone and the text under the logo
 *
 * There are no plugins. Not as a purity exercise: every plugin is another thing
 * that can break on a PHP bump, another update the client has to understand,
 * and another party that can disappear — which is exactly how this project lost
 * Cloudflare and GitLab inside one week. Custom fields are ~200 lines here and
 * are patched by WordPress core along with everything else.
 */

if (!defined('ABSPATH')) exit;

define('SHTOB_VERSION', '1.3.1');

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/cards.php';
require_once __DIR__ . '/inc/fields.php';
require_once __DIR__ . '/inc/customizer.php';
require_once __DIR__ . '/inc/nav.php';
require_once __DIR__ . '/inc/render.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/privacy.php';
require_once __DIR__ . '/inc/admin-help.php';
require_once __DIR__ . '/inc/seed.php';
require_once __DIR__ . '/inc/migrate.php';
