<?php
/**
 * KEEPING THE PRIVACY NOTICE TRUE.
 *
 * /privacy states in Russian that the site collects nothing, has no forms, no
 * registration and no visit counters, and sets no cookies. That is not
 * boilerplate — under 152-ФЗ, collecting personal data means naming an operator
 * with its ОГРН and ИНН, details this project has deliberately never invented.
 *
 * A stock WordPress breaks that sentence on day one: comments are a form that
 * stores a name, an e-mail, an IP address and a cookie. Everything below exists
 * to keep the promise the page makes, and NONE of it is optional decoration.
 * If any of it is ever removed, /privacy has to be rewritten in the same commit.
 */
if (!defined('ABSPATH')) exit;

/* ── Comments: off, everywhere, permanently ─────────────────────────────── */

add_filter('comments_open', '__return_false', 20);
add_filter('pings_open', '__return_false', 20);
add_filter('comments_array', '__return_empty_array', 20);

/** Also take the UI away, so nobody switches it back on without meaning to. */
function shtob_remove_comment_ui() {
    foreach (get_post_types() as $type) {
        if (post_type_supports($type, 'comments')) {
            remove_post_type_support($type, 'comments');
            remove_post_type_support($type, 'trackbacks');
        }
    }
}
add_action('init', 'shtob_remove_comment_ui', 100);

function shtob_hide_comment_menus() {
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'shtob_hide_comment_menus');

function shtob_drop_comment_admin_bar($bar) {
    $bar->remove_node('comments');
}
add_action('admin_bar_menu', 'shtob_drop_comment_admin_bar', 999);

/* ── XML-RPC and the REST API ───────────────────────────────────────────── */

/**
 * XML-RPC is the classic brute-force amplifier: system.multicall lets an
 * attacker try hundreds of passwords in one request, which is how small sites on
 * shared hosting get taken. Nothing here uses it — no Jetpack, no mobile app.
 */
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});

/**
 * /wp-json/wp/v2/users lists every account with its login name, which hands an
 * attacker the username half of the pair for free. The REST API is left working
 * for logged-in editors — the block editor needs it — and closed to strangers.
 */
function shtob_rest_requires_login($result) {
    if (!empty($result)) return $result;
    if (!is_user_logged_in()) {
        return new WP_Error('shtob_rest_forbidden',
            'REST API доступен только авторизованным пользователям.',
            ['status' => rest_authorization_required_code()]);
    }
    return $result;
}
add_filter('rest_authentication_errors', 'shtob_rest_requires_login');

/** ?author=1 redirects to /author/<login>/ and leaks the same thing. */
function shtob_block_author_scan() {
    if (!is_admin() && isset($_GET['author'])) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}
add_action('template_redirect', 'shtob_block_author_scan');

/* ── Requests the public page must not make ─────────────────────────────── */

/**
 * The emoji script fetches from s.w.org for any browser that needs a polyfill —
 * a third-party request on a page that promises none, and pointless on a site
 * whose entire content is Russian prose. Every modern browser draws emoji
 * natively.
 */
function shtob_no_emoji() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('emoji_svg_url', '__return_false');
}
add_action('init', 'shtob_no_emoji');

/**
 * The block library's CSS is ~70 KB styling blocks these templates never use —
 * the layout is the theme's, and the client's text is paragraphs and lists that
 * style.css already handles.
 */
function shtob_trim_head() {
    if (!is_admin()) {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
    }
}
add_action('wp_enqueue_scripts', 'shtob_trim_head', 100);

/** Housekeeping in <head>: none of these do anything for this site. */
remove_action('wp_head', 'wp_generator');                    // announces the WP version
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('template_redirect', 'wp_shortlink_header', 11);

/** No login hint on a failed attempt — «неверный пароль» confirms the username. */
add_filter('login_errors', function () {
    return 'Неверные данные для входа.';
});
