<?php
/**
 * Карточки — the entries that make up every section.
 *
 * WHY A POST TYPE AND NOT CHILD PAGES. Child pages were the obvious core-only
 * answer and are wrong here: every one of them gets a public URL, so «Имя» and
 * «Сувенир» — the placeholders waiting to be filled in — would each be a thin,
 * indexable page in the sitemap. A post type that is `public => false` has no
 * URL at all, which is exactly right for something that only ever appears
 * inside its parent page.
 *
 * ONE TYPE COVERS THREE SHAPES: the alternating photo/text rows on the section
 * pages, the priced rows in the menu, and the drinks list under it. They differ
 * by two fields (Список and Цена), not by structure, and three post types would
 * have meant three admin screens for what the client sees as "the things on a
 * page".
 */
if (!defined('ABSPATH')) exit;

const SHTOB_CARD = 'shtob_card';

function shtob_register_cards() {
    register_post_type(SHTOB_CARD, [
        'labels' => [
            'name'               => 'Карточки',
            'singular_name'      => 'Карточка',
            'menu_name'          => 'Карточки',
            'add_new'            => 'Добавить карточку',
            'add_new_item'       => 'Новая карточка',
            'edit_item'          => 'Редактировать карточку',
            'new_item'           => 'Новая карточка',
            'view_item'          => 'Посмотреть карточку',
            'search_items'       => 'Искать карточки',
            'not_found'          => 'Карточек пока нет',
            'not_found_in_trash' => 'В корзине карточек нет',
            'all_items'          => 'Все карточки',
            'featured_image'     => 'Фотография',
            'set_featured_image' => 'Выбрать фотографию',
            'remove_featured_image' => 'Убрать фотографию',
            'use_featured_image' => 'Использовать как фотографию',
        ],
        // No public URL, no archive, no place in the sitemap — a card is only
        // ever rendered inside the page it belongs to.
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false, // classic editor: the fields below are meta boxes
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-index-card',
        'hierarchical'        => false,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'],
        'has_archive'         => false,
        'rewrite'             => false,
        'can_export'          => true,
    ]);
}
add_action('init', 'shtob_register_cards');

/** Field labels the client sees, in the terms the page uses. */
function shtob_card_labels($labels, $post) {
    if ($post && $post->post_type === SHTOB_CARD) {
        $labels['title_placeholder'] = 'Название карточки';
    }
    return $labels;
}
add_filter('post_type_labels_' . SHTOB_CARD, function ($labels) {
    $labels->edit_item = 'Редактировать карточку';
    return $labels;
});

/**
 * «Отрывок» is WordPress's name for the excerpt and means nothing to a café
 * owner. On a card it is the small line under the heading — a person's role, a
 * souvenir's price, the address of a building — so it is relabelled and moved
 * up where it will actually be seen.
 */
function shtob_rename_excerpt_box() {
    remove_meta_box('postexcerpt', SHTOB_CARD, 'normal');
    add_meta_box('postexcerpt', 'Подпись под названием', 'post_excerpt_meta_box',
        SHTOB_CARD, 'normal', 'high');
}
add_action('add_meta_boxes', 'shtob_rename_excerpt_box');

/**
 * ORDER. Cards sort by menu_order, the "Порядок" field under Атрибуты — the
 * same idea as the numeric folder prefixes the Grav pages used (03.okrestnosti,
 * 04.kuhnya). Ties fall back to the title so the order is at least stable, and
 * never to the post date: the client would otherwise find that editing a card
 * left it where it was but creating one put it in an unpredictable place.
 */
function shtob_cards_for($page_id, $list = 'main') {
    if (!$page_id) return [];
    return get_posts([
        'post_type'      => SHTOB_CARD,
        'post_status'    => 'publish',
        'numberposts'    => 100,
        'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_shtob_section', 'value' => (string) $page_id],
            $list === 'main'
                ? ['relation' => 'OR',
                   ['key' => '_shtob_list', 'value' => 'main'],
                   ['key' => '_shtob_list', 'compare' => 'NOT EXISTS']]
                : ['key' => '_shtob_list', 'value' => $list],
        ],
    ]);
}

/** Admin list table: show which section a card belongs to, and let it be filtered. */
function shtob_card_columns($cols) {
    $out = [];
    foreach ($cols as $k => $v) {
        $out[$k] = $v;
        if ($k === 'title') {
            $out['shtob_section'] = 'Раздел';
            $out['shtob_list']    = 'Список';
            $out['shtob_order']   = 'Порядок';
        }
    }
    return $out;
}
add_filter('manage_' . SHTOB_CARD . '_posts_columns', 'shtob_card_columns');

function shtob_card_column($col, $post_id) {
    if ($col === 'shtob_section') {
        $sec = (int) get_post_meta($post_id, '_shtob_section', true);
        echo $sec && get_post($sec)
            ? esc_html(get_the_title($sec))
            : '<span style="color:#b32d2e">не выбран</span>';
    }
    if ($col === 'shtob_list') {
        $l = get_post_meta($post_id, '_shtob_list', true) ?: 'main';
        echo esc_html(shtob_list_choices()[$l] ?? $l);
    }
    if ($col === 'shtob_order') {
        echo (int) get_post_field('menu_order', $post_id);
    }
}
add_action('manage_' . SHTOB_CARD . '_posts_custom_column', 'shtob_card_column', 10, 2);

function shtob_card_sortable($cols) {
    $cols['shtob_order'] = 'menu_order';
    return $cols;
}
add_filter('manage_edit-' . SHTOB_CARD . '_sortable_columns', 'shtob_card_sortable');

/**
 * Default the card list to section-then-order rather than newest-first. With two
 * dozen cards across seven sections, date order is noise: the client is always
 * looking for "the cards on Гостиная".
 */
function shtob_card_admin_order($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== SHTOB_CARD) return;
    if (!$query->get('orderby')) {
        $query->set('orderby', ['menu_order' => 'ASC', 'title' => 'ASC']);
    }
    $sec = isset($_GET['shtob_section']) ? (int) $_GET['shtob_section'] : 0;
    if ($sec) {
        $query->set('meta_query', [['key' => '_shtob_section', 'value' => (string) $sec]]);
    }
}
add_action('pre_get_posts', 'shtob_card_admin_order');

/** The section filter above the card list. */
function shtob_card_filter_ui($post_type) {
    if ($post_type !== SHTOB_CARD) return;
    $current = isset($_GET['shtob_section']) ? (int) $_GET['shtob_section'] : 0;
    echo '<select name="shtob_section"><option value="0">Все разделы</option>';
    foreach (shtob_section_pages() as $p) {
        printf('<option value="%d"%s>%s</option>',
            $p->ID, selected($current, $p->ID, false), esc_html($p->post_title));
    }
    echo '</select>';
}
add_action('restrict_manage_posts', 'shtob_card_filter_ui');
