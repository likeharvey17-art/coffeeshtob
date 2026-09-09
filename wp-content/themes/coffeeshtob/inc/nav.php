<?php
/**
 * The site navigation — generated, and it must stay generated.
 *
 * Before the site was split it was six in-page anchors written out three times:
 * the header, the footer and the 404. Three copies of one list is three chances
 * to forget one, and they had already drifted. It is now derived from the pages
 * themselves, so creating a page in the admin puts it in both navs with nothing
 * to edit here.
 *
 * WHY NOT A WORDPRESS MENU. Внешний вид → Меню is the native answer and was
 * rejected: it makes a new page invisible until somebody remembers to add it,
 * which is precisely the failure this replaced. The client gets the same control
 * through «Не показывать в меню» on the page itself, where they are already
 * standing when they create it.
 *
 * ORDER is the page's «Порядок» field (menu_order), which is also the order the
 * Страницы list shows. Renumber there, not here.
 */
if (!defined('ABSPATH')) exit;

function shtob_nav_pages() {
    $front = (int) get_option('page_on_front');
    $pages = get_pages([
        'sort_column' => 'menu_order,post_title',
        'parent'      => 0,
        'post_status' => 'publish',
    ]);
    return array_values(array_filter($pages, function ($p) use ($front) {
        if ((int) $p->ID === $front) return false;                       // the logo goes home
        return get_post_meta($p->ID, '_shtob_hide_in_menu', true) !== '1';
    }));
}

/** The short label — long page titles would not fit seven across. */
function shtob_nav_label($page) {
    $short = get_post_meta($page->ID, '_shtob_menu_label', true);
    return $short !== '' ? $short : $page->post_title;
}

/**
 * `is-active` is rendered server-side, so the current page is marked before the
 * first paint and stays marked with JavaScript off. The scrollspy that used to
 * compute this in the browser was deleted with the anchors and must not come
 * back: it compared href against '#' + id, so with real URLs no link could ever
 * match, and because it called toggle() on every intersection it would strip
 * this class off again rather than merely failing.
 */
function shtob_nav($current_id = 0) {
    $current_id = $current_id ?: get_queried_object_id();
    foreach (shtob_nav_pages() as $p) {
        $active = ((int) $p->ID === (int) $current_id);
        printf('<a href="%s"%s>%s</a>' . "\n",
            esc_url(get_permalink($p)),
            $active ? ' class="is-active" aria-current="page"' : '',
            esc_html(shtob_nav_label($p)));
    }
}
