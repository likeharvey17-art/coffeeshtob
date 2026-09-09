<?php
/**
 * Titles, social cards, structured data, sitemap and /llms.txt.
 *
 * NOTHING HERE HARDCODES THE DOMAIN. The static site had the production host
 * written into 14 places across five files, all of which had to change together
 * — and the host moved twice in one week. Every absolute URL below comes from
 * home_url(), so the site advertises whatever it is actually served from and
 * there is nothing to keep in sync.
 */
if (!defined('ABSPATH')) exit;

/** The per-page SEO title, falling back to the page's own name. */
function shtob_document_title($title) {
    $id = get_queried_object_id();
    if ($id && is_page()) {
        $custom = get_post_meta($id, '_shtob_seo_title', true);
        if ($custom !== '') return $custom;
    }
    return $title;
}
add_filter('pre_get_document_title', 'shtob_document_title', 20);

function shtob_meta_description() {
    $id = get_queried_object_id();
    if ($id && is_page()) {
        $d = get_post_meta($id, '_shtob_seo_description', true);
        if ($d !== '') return $d;
        $e = get_the_excerpt($id);
        if ($e) return wp_strip_all_tags($e);
    }
    return get_bloginfo('description');
}

/**
 * Icons, the manifest and the social card.
 *
 * og-image.jpg is 1200×630 and lives in the theme, so it deploys with
 * everything else. On the static site it was hand-uploaded and had to be
 * excluded from the mirror to stop --delete removing it — a special case that
 * dies here.
 */
function shtob_head_meta() {
    $dir   = get_template_directory_uri() . '/assets/images/';
    $title = wp_get_document_title();
    $desc  = shtob_meta_description();
    $url   = is_front_page() ? home_url('/') : get_permalink(get_queried_object_id());
    $og    = $dir . 'og-image.jpg';
    ?>
<meta name="theme-color" content="#f7f3ec">
<link rel="icon" href="<?php echo esc_url($dir . 'favicon.ico'); ?>" sizes="any">
<link rel="icon" href="<?php echo esc_url($dir . 'favicon.svg'); ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?php echo esc_url($dir . 'apple-touch-icon.png'); ?>">
<link rel="manifest" href="<?php echo esc_url($dir . 'site.webmanifest'); ?>">
<meta name="description" content="<?php echo esc_attr($desc); ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Кофештаб «Романов на Волге»">
<meta property="og:locale" content="ru_RU">
<meta property="og:title" content="<?php echo esc_attr($title); ?>">
<meta property="og:description" content="<?php echo esc_attr($desc); ?>">
<meta property="og:url" content="<?php echo esc_url($url); ?>">
<meta property="og:image" content="<?php echo esc_url($og); ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Кофештаб — кофейня в купеческом доме на Волжской набережной">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo esc_attr($title); ?>">
<meta name="twitter:description" content="<?php echo esc_attr($desc); ?>">
<meta name="twitter:image" content="<?php echo esc_url($og); ?>">
    <?php
}
add_action('wp_head', 'shtob_head_meta', 2);

/**
 * The business's structured data, on the front page only.
 *
 * Every value is one the site actually renders — no invented geo coordinates, no
 * made-up priceRange. The hours are read from the Контакты page and each row is
 * only included when the days and both times could be derived; with no
 * qualifying row the property is omitted entirely rather than guessed at.
 *
 * wp_json_encode does the escaping. Building this by hand is how an apostrophe
 * in a business name silently invalidates the whole block.
 */
function shtob_json_ld() {
    if (!is_front_page()) return;

    $opening = [];
    $kontakty = get_page_by_path('kontakty');
    if ($kontakty) {
        foreach (shtob_parse_hours(get_post_meta($kontakty->ID, '_shtob_hours', true)) as $row) {
            if ($row['days'] && $row['opens'] && $row['closes']) {
                $opening[] = [
                    '@type'       => 'OpeningHoursSpecification',
                    'dayOfWeek'   => $row['days'],
                    'opens'       => $row['opens'],
                    'closes'      => $row['closes'],
                ];
            }
        }
    }

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'CafeOrCoffeeShop',
        'name'        => 'Кофештаб «Романов на Волге»',
        'description' => shtob_meta_description(),
        'url'         => home_url('/'),
        'image'       => get_template_directory_uri() . '/assets/images/og-image.jpg',
        'telephone'   => shtob_tel(shtob_opt('phone')),
        'address'     => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Волжская набережная, 19',
            'addressLocality' => 'Тутаев',
            'addressRegion'   => 'Ярославская область',
            'addressCountry'  => 'RU',
        ],
        'sameAs'      => array_values(array_filter([shtob_opt('telegram'), shtob_opt('vk')])),
    ];
    if ($opening) $schema['openingHoursSpecification'] = $opening;

    echo "\n<script type=\"application/ld+json\">"
       . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
       . "</script>\n";
}
add_action('wp_footer', 'shtob_json_ld');

/**
 * THE SITEMAP IS WORDPRESS'S OWN, and that is an improvement worth stating.
 * Both previous versions of this site shipped a hand-written sitemap.xml listing
 * every URL, so adding a page meant remembering to edit a second file — and
 * nothing checked. Core generates /wp-sitemap.xml from the pages themselves.
 *
 * Trimmed to pages: posts are unused, cards have no URLs, and the users sitemap
 * is the author-enumeration leak closed in privacy.php arriving by another door.
 */
add_filter('wp_sitemaps_post_types', function ($types) {
    unset($types['post']);
    return $types;
});
add_filter('wp_sitemaps_taxonomies', '__return_empty_array');
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    return $name === 'users' ? false : $provider;
}, 10, 2);

/**
 * /sitemap.xml is the URL robots.txt advertised for two years and the one search
 * engines already know. Keep it working rather than orphaning it.
 */
function shtob_legacy_sitemap() {
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    if ($path === 'sitemap.xml') {
        wp_safe_redirect(home_url('/wp-sitemap.xml'), 301);
        exit;
    }
}
add_action('template_redirect', 'shtob_legacy_sitemap', 1);

/**
 * /llms.txt — a plain-language summary for assistants and crawlers.
 *
 * GENERATED, not a file. Both earlier versions kept it as static text listing
 * every section, which is the one file in the project that repeats content
 * rather than linking to it, and it went stale the moment a page was renamed.
 * Built from the page tree it cannot.
 */
function shtob_llms_rules($rules) {
    return array_merge(['^llms\.txt$' => 'index.php?shtob_llms=1'], $rules);
}
add_filter('rewrite_rules_array', 'shtob_llms_rules');

add_filter('query_vars', function ($vars) {
    $vars[] = 'shtob_llms';
    return $vars;
});

/**
 * WordPress's canonical redirect appends a trailing slash to anything it does
 * not recognise as a file, so /llms.txt answered 301 → /llms.txt/ and the text
 * never rendered. Measured, not guessed: the response carried
 * `X-Redirect-By: WordPress`.
 */
function shtob_llms_no_canonical($redirect) {
    return get_query_var('shtob_llms') ? false : $redirect;
}
add_filter('redirect_canonical', 'shtob_llms_no_canonical');

function shtob_llms_output() {
    if (!get_query_var('shtob_llms')) return;

    $kontakty = get_page_by_path('kontakty');
    $hours = $kontakty ? shtob_parse_hours(get_post_meta($kontakty->ID, '_shtob_hours', true)) : [];

    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');

    echo "# Кофештаб «Романов на Волге»\n\n";
    echo "Кофейня в старинном купеческом доме на Волжской набережной в Романове\n";
    echo "(город Тутаев, левый берег, Ярославская область).\n\n";
    echo "Адрес: " . shtob_opt('address') . "\n";
    echo "Телефон: " . shtob_opt('phone') . "\n";
    foreach ($hours as $row) {
        if ($row['label'] && $row['time']) echo "Часы работы: {$row['label']} — {$row['time']}\n";
    }
    echo "\n## Разделы сайта\n\n";
    echo '- [Главная](' . home_url('/') . ")\n";
    foreach (shtob_nav_pages() as $p) {
        $teaser = get_post_meta($p->ID, '_shtob_card_teaser', true);
        printf("- [%s](%s)%s\n", $p->post_title, get_permalink($p), $teaser ? ' — ' . $teaser : '');
    }
    $privacy = get_page_by_path('privacy');
    if ($privacy) echo '- [Политика конфиденциальности](' . get_permalink($privacy) . ")\n";
    echo "\nСайт не собирает персональные данные, не использует cookie-файлы\n";
    echo "и не содержит форм обратной связи.\n";
    exit;
}
add_action('template_redirect', 'shtob_llms_output', 0);

/**
 * Flush rewrites once when the theme is activated, so /llms.txt works without
 * anyone having to open Настройки → Постоянные ссылки and press Save.
 */
add_action('after_switch_theme', function () {
    flush_rewrite_rules();
});
