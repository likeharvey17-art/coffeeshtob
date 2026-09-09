<?php
/** Theme supports, image sizes and asset loading. */
if (!defined('ABSPATH')) exit;

/**
 * IMAGE SIZES REPLACE GRAV'S cropZoom().
 *
 * Grav resized on demand from the template — cropZoom(1000, 750) — so a size
 * was whatever the template asked for. WordPress crops on upload instead, which
 * means these must be registered BEFORE the client uploads anything: a size
 * added later exists only for photos uploaded after it, and older ones silently
 * fall back to the full-size original. That is the failure mode to watch for if
 * one of these is ever changed — the fix is to regenerate thumbnails, which on
 * shared hosting means re-uploading.
 *
 * The numbers are carried over from the Grav templates unchanged, so the
 * per-page image budget the deploy checks still holds.
 */
function shtob_setup() {
    load_theme_textdomain('coffeeshtob', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');

    // The photos are the content here, so the client needs a real media library.
    add_image_size('shtob-hero',    1400, 880, true);
    add_image_size('shtob-banner',  1600, 700, true);
    add_image_size('shtob-feature', 1000, 750, true);
    add_image_size('shtob-wide',    1100, 688, true);
    add_image_size('shtob-card',     760, 570, true);
    add_image_size('shtob-thumb',    160, 160, true);
}
add_action('after_setup_theme', 'shtob_setup');

/** Names the client sees in the "size" dropdown when inserting an image. */
function shtob_image_size_names($sizes) {
    return array_merge($sizes, [
        'shtob-card'    => __('Карточка', 'coffeeshtob'),
        'shtob-feature' => __('Фото раздела', 'coffeeshtob'),
        'shtob-banner'  => __('Шапка страницы', 'coffeeshtob'),
    ]);
}
add_filter('image_size_names_choose', 'shtob_image_size_names');

/**
 * ONE STYLESHEET, ONE SCRIPT, NO JQUERY.
 *
 * script.js is plain DOM API on purpose — it was written for the static site and
 * carried through Grav untouched. Loading jQuery to run it would add ~30 KB for
 * nothing.
 *
 * The version string is the theme version, not a timestamp: nginx on Beget
 * caches assets for a week regardless of what .htaccess asks for, and no
 * filename here carries a content hash, so the query string is the only thing
 * that reaches a returning visitor after a deploy. Bump SHTOB_VERSION when
 * style.css or script.js changes — the deploy asserts it moved.
 */
function shtob_assets() {
    wp_enqueue_style('coffeeshtob', get_stylesheet_uri(), [], SHTOB_VERSION);
    wp_enqueue_script('coffeeshtob', get_template_directory_uri() . '/js/script.js', [], SHTOB_VERSION, true);
}
add_action('wp_enqueue_scripts', 'shtob_assets');

/**
 * The fonts are self-hosted (see the licence note in style.css) and every page
 * uses both families immediately, so preloading the two Cyrillic subsets buys a
 * real first-paint win. The other subsets stay lazy — latin-ext exists for the
 * ruble sign and cyrillic-ext for a handful of glyphs, and preloading either
 * would download 85 KB most visitors never need.
 */
function shtob_preload_fonts() {
    $dir = get_template_directory_uri() . '/assets/fonts/';
    foreach (['inter-cyrillic.woff2', 'playfair-display-cyrillic.woff2'] as $f) {
        printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url($dir . $f));
    }
}
add_action('wp_head', 'shtob_preload_fonts', 1);
