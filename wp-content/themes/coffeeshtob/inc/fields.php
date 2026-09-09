<?php
/**
 * Every editable field on the site, declared ONCE.
 *
 * THE PROBLEM THIS SOLVES, AND IT IS THE OLDEST BUG IN THIS PROJECT. Under
 * Decap the same field name had to be written in three files — the markup, the
 * JSON and the CMS config — and nothing complained when they disagreed: the
 * value simply stopped appearing on the site. Grav had the same trap across
 * template, blueprint and page. Here the declaration below is the only place a
 * field name is written; the meta box, the sanitiser, the save handler and the
 * templates' accessor all read it. A typo is impossible to make in only one
 * place because there is only one place.
 *
 * Adding a field: add one entry here. Nothing else needs editing.
 */
if (!defined('ABSPATH')) exit;

/** The named icons, matched to the SVGs in parts/icon.php. */
function shtob_icon_choices() {
    return [
        ''        => '— без значка —',
        'coffee'  => 'Чашка кофе',
        'crate'   => 'Ящик (местные продукты)',
        'house'   => 'Дом',
        'people'  => 'Люди',
        'book'    => 'Книга',
        'printer' => '3D-принтер',
        'music'   => 'Музыка',
        'film'    => 'Кино',
        'game'    => 'Настолки',
        'leaf'    => 'Травы',
        'clock'   => 'Часы',
    ];
}

function shtob_list_choices() {
    return [
        'main'   => 'Карточки раздела',
        'menu'   => 'Меню (с ценой)',
        'drinks' => 'Также в штабе (с ценой)',
    ];
}

function shtob_layout_choices() {
    return [
        'alt'  => 'Крупные ряды: фото и текст по очереди (по умолчанию)',
        'wide' => 'Широкие блоки: одно большое фото на запись',
        'grid' => 'Компактная сетка: много коротких записей',
    ];
}

/** Pages that can hold cards — everything except the front page and the policy. */
function shtob_section_pages() {
    $pages = get_pages(['sort_column' => 'menu_order,post_title', 'post_status' => 'publish,draft']);
    $front = (int) get_option('page_on_front');
    return array_values(array_filter($pages, function ($p) use ($front) {
        return $p->ID !== $front
            && get_page_template_slug($p->ID) !== 'template-privacy.php';
    }));
}

/**
 * The declaration. `screen` says where a box appears:
 *   'card'                      — the Карточки editor
 *   'page'                      — every page
 *   'template-*.php'            — pages using that template
 *   'front'                     — the page set as the front page
 */
function shtob_field_groups() {
    return [
        'card' => [
            'title'  => 'Настройки карточки',
            'screen' => 'card',
            'help'   => 'Фотография — блок «Фотография» справа. Текст карточки — в основном окне.',
            'fields' => [
                'section' => ['type' => 'page_select', 'label' => 'Раздел',
                    'help' => 'На какой странице показывать эту карточку.'],
                'list'    => ['type' => 'select', 'label' => 'Список', 'choices' => 'shtob_list_choices',
                    'default' => 'main',
                    'help' => 'Меняйте только на странице «Кухня»: там три списка. В остальных разделах оставьте «Карточки раздела».'],
                'price'   => ['type' => 'text', 'label' => 'Цена',
                    'help' => 'Например «от 100 ₽». Показывается только в списках меню. Пусто — строка без цены.'],
                'icon'    => ['type' => 'select', 'label' => 'Значок', 'choices' => 'shtob_icon_choices',
                    'help' => 'Необязательно. Если значок есть не у всех карточек в ряду, заголовки встают неровно — либо у всех, либо ни у кого.'],
            ],
        ],

        'seo' => [
            'title'  => 'Заголовок и описание для поиска',
            'screen' => 'page',
            'help'   => 'То, что видно в Яндексе и при отправке ссылки в мессенджер. Пусто — берётся название страницы.',
            'fields' => [
                'seo_title'       => ['type' => 'text', 'label' => 'Заголовок для поиска',
                    'help' => 'До ~60 знаков.'],
                'seo_description' => ['type' => 'textarea', 'label' => 'Описание для поиска', 'rows' => 3,
                    'help' => 'До ~160 знаков.'],
            ],
        ],

        'menu' => [
            'title'  => 'Меню сайта',
            'screen' => 'page',
            'help'   => 'Верхнее меню собирается из страниц автоматически — новая страница появляется в нём сама.',
            'fields' => [
                'menu_label' => ['type' => 'text', 'label' => 'Короткое название в меню',
                    'help' => 'Семь пунктов должны помещаться в одну строку: «Окрестности», а не «Окрестности штаба». Пусто — берётся название страницы.'],
                'hide_in_menu' => ['type' => 'checkbox', 'label' => 'Не показывать в меню',
                    'help' => 'Для главной и политики конфиденциальности — на них ведут логотип и нижняя строка подвала.'],
            ],
        ],

        'banner' => [
            'title'  => 'Шапка страницы',
            'screen' => ['template-cards.php', 'template-kuhnya.php', 'template-kontakty.php'],
            'help'   => 'Фото шапки — блок «Изображение записи» справа. Без фото шапка станет тёмной полосой, и это нормально: размытая заглушка выглядит как сломанная картинка.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Надпись над заголовком',
                    'help' => 'Мелкими прописными, например «ОКРЕСТНОСТИ ШТАБА».'],
                'intro'   => ['type' => 'textarea', 'label' => 'Строка под заголовком', 'rows' => 2],
                'card_teaser' => ['type' => 'textarea', 'label' => 'Описание на главной', 'rows' => 2,
                    'help' => 'Одна строка под названием раздела в плитке на главной странице.'],
            ],
        ],

        'cards_page' => [
            'title'  => 'Карточки раздела',
            'screen' => 'template-cards.php',
            'help'   => 'Сами карточки редактируются в разделе «Карточки» слева.',
            'fields' => [
                'layout'     => ['type' => 'select', 'label' => 'Как показывать карточки',
                    'choices' => 'shtob_layout_choices', 'default' => 'alt',
                    'help' => 'Весь сайт использует «крупные ряды» — так страницы читаются как одно целое.'],
                'lead_title' => ['type' => 'text', 'label' => 'Заголовок вступления'],
                'lead'       => ['type' => 'textarea', 'label' => 'Вступление', 'rows' => 3,
                    'help' => 'Показывается над карточками. Пусто — блока не будет.'],
                'outro_title'     => ['type' => 'text', 'label' => 'Заголовок блока внизу'],
                'outro_text'      => ['type' => 'textarea', 'label' => 'Текст блока внизу', 'rows' => 4],
                'outro_link_text' => ['type' => 'text', 'label' => 'Подпись ссылки внизу'],
                'outro_link_url'  => ['type' => 'url', 'label' => 'Адрес ссылки внизу',
                    'help' => 'Ссылка появится, только если заполнены обе строки.'],
            ],
        ],

        'kuhnya' => [
            'title'  => 'Заголовки списков',
            'screen' => 'template-kuhnya.php',
            'help'   => 'Сами позиции меню — в разделе «Карточки», поле «Список».',
            'fields' => [
                'menu_title'    => ['type' => 'text', 'label' => 'Заголовок меню'],
                'menu_sub'      => ['type' => 'text', 'label' => 'Подзаголовок меню'],
                'drinks_title'  => ['type' => 'text', 'label' => 'Заголовок второго списка'],
                'drinks_sub'    => ['type' => 'text', 'label' => 'Подзаголовок второго списка'],
                'items_eyebrow' => ['type' => 'text', 'label' => 'Надпись над карточками'],
                'items_title'   => ['type' => 'text', 'label' => 'Заголовок карточек'],
                'items_sub'     => ['type' => 'text', 'label' => 'Подзаголовок карточек'],
            ],
        ],

        'kontakty' => [
            'title'  => 'График работы и переправа',
            'screen' => 'template-kontakty.php',
            'help'   => 'Адрес и телефон здесь не хранятся: они в «Внешний вид → Настроить», потому что показываются ещё и в подвале каждой страницы.',
            'fields' => [
                'hours_title'   => ['type' => 'text', 'label' => 'Заголовок графика', 'default' => 'График работы'],
                'hours'         => ['type' => 'textarea', 'label' => 'Часы работы', 'rows' => 5,
                    'help' => 'По строке на режим, через вертикальную черту: <code>БУДНИ (ПН–ПТ) | 15:15–19:00 | пн-пт</code>. Третья часть нужна поисковикам, чтобы показывать «открыто до…»; допустимо <code>пн-пт</code>, <code>сб,вс</code>, <code>ежедневно</code>.'],
                'hours_note'    => ['type' => 'text', 'label' => 'Примечание под графиком'],
                'address_title' => ['type' => 'text', 'label' => 'Заголовок блока с адресом', 'default' => 'Где мы находимся'],
                'ferry_title'   => ['type' => 'text', 'label' => 'Заголовок блока о переправе'],
                'ferry_text'    => ['type' => 'textarea', 'label' => 'Текст о переправе', 'rows' => 3],
            ],
        ],

        'privacy' => [
            'title'  => 'Политика конфиденциальности',
            'screen' => 'template-privacy.php',
            'help'   => 'Сам текст политики хранится в теме, а не здесь: это юридические утверждения о том, как работает сайт, и менять их можно только вместе с сайтом. Дата — единственное, что редактируется.',
            'fields' => [
                'updated' => ['type' => 'text', 'label' => 'Дата последнего изменения',
                    'default' => '5 сентября 2026 года',
                    'help' => 'Показывается строкой «Обновлено …» под заголовком.'],
            ],
        ],

        'home' => [
            'title'  => 'Главная страница',
            'screen' => 'front',
            'help'   => 'Фото на весь первый экран — блок «Изображение записи» справа. Плитка разделов внизу собирается из страниц сама.',
            'fields' => [
                'hero_title' => ['type' => 'text', 'label' => 'Заголовок на фото'],
                'hero_lead'  => ['type' => 'textarea', 'label' => 'Текст под заголовком', 'rows' => 3],
                'hero_badge' => ['type' => 'text', 'label' => 'Адрес в уголке'],
                'cta1_text'  => ['type' => 'text', 'label' => 'Первая кнопка — подпись'],
                'cta1_url'   => ['type' => 'text', 'label' => 'Первая кнопка — адрес', 'default' => '/kuhnya/'],
                'cta2_text'  => ['type' => 'text', 'label' => 'Вторая кнопка — подпись'],
                'cta2_url'   => ['type' => 'text', 'label' => 'Вторая кнопка — адрес', 'default' => '/kontakty/'],
                'about_title' => ['type' => 'text', 'label' => 'Заголовок «О штабе»'],
                'about_image' => ['type' => 'image', 'label' => 'Фотография «О штабе»'],
                'about_text'  => ['type' => 'textarea', 'label' => 'Текст «О штабе»', 'rows' => 8,
                    'help' => 'Пустая строка начинает новый абзац.'],
                'sections_title' => ['type' => 'text', 'label' => 'Заголовок плитки разделов'],
                'sections_sub'   => ['type' => 'text', 'label' => 'Подзаголовок плитки разделов'],
            ],
        ],
    ];
}

/** Read one field, falling back to its declared default. */
function shtob_get($post_id, $key) {
    $value = get_post_meta($post_id, '_shtob_' . $key, true);
    if ($value !== '' && $value !== null) return $value;
    foreach (shtob_field_groups() as $g) {
        if (isset($g['fields'][$key]['default'])) return $g['fields'][$key]['default'];
    }
    return '';
}

/* ── Meta boxes ─────────────────────────────────────────────────────────── */

function shtob_box_applies($group, $screen_post) {
    $want = (array) $group['screen'];
    $type = $screen_post->post_type;
    foreach ($want as $w) {
        if ($w === 'card' && $type === SHTOB_CARD) return true;
        if ($w === 'page' && $type === 'page') return true;
        if ($w === 'front' && $type === 'page'
            && (int) $screen_post->ID === (int) get_option('page_on_front')) return true;
        if (str_starts_with($w, 'template-') && $type === 'page'
            && get_page_template_slug($screen_post->ID) === $w) return true;
    }
    return false;
}

function shtob_add_meta_boxes($post_type, $post) {
    foreach (shtob_field_groups() as $id => $group) {
        if (!shtob_box_applies($group, $post)) continue;
        add_meta_box('shtob_' . $id, $group['title'], 'shtob_render_box',
            $post_type, 'normal', 'high', ['group' => $id]);
    }
}
add_action('add_meta_boxes', 'shtob_add_meta_boxes', 10, 2);

function shtob_render_box($post, $args) {
    $group = shtob_field_groups()[$args['args']['group']];
    wp_nonce_field('shtob_save_' . $post->ID, 'shtob_nonce');
    if (!empty($group['help'])) {
        echo '<p class="description" style="margin:.2em 0 1em">' . wp_kses_post($group['help']) . '</p>';
    }
    echo '<table class="form-table" role="presentation"><tbody>';
    foreach ($group['fields'] as $key => $f) {
        $value = get_post_meta($post->ID, '_shtob_' . $key, true);
        if ($value === '' && isset($f['default'])) $value = $f['default'];
        $name = 'shtob_' . $key;
        $id   = 'shtob-' . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($f['label']) . '</label></th><td>';
        shtob_render_field($f, $name, $id, $value);
        if (!empty($f['help'])) {
            echo '<p class="description">' . wp_kses($f['help'], ['code' => [], 'strong' => [], 'em' => [], 'a' => ['href' => []]]) . '</p>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}

function shtob_render_field($f, $name, $id, $value) {
    switch ($f['type']) {
        case 'textarea':
            printf('<textarea class="large-text" rows="%d" name="%s" id="%s">%s</textarea>',
                (int) ($f['rows'] ?? 4), esc_attr($name), esc_attr($id), esc_textarea($value));
            break;

        case 'select':
            $choices = call_user_func($f['choices']);
            printf('<select name="%s" id="%s">', esc_attr($name), esc_attr($id));
            foreach ($choices as $k => $label) {
                printf('<option value="%s"%s>%s</option>',
                    esc_attr($k), selected($value, $k, false), esc_html($label));
            }
            echo '</select>';
            break;

        case 'page_select':
            printf('<select name="%s" id="%s"><option value="">— не выбран —</option>', esc_attr($name), esc_attr($id));
            foreach (shtob_section_pages() as $p) {
                printf('<option value="%d"%s>%s</option>',
                    $p->ID, selected((int) $value, $p->ID, false), esc_html($p->post_title));
            }
            echo '</select>';
            break;

        case 'checkbox':
            printf('<label><input type="checkbox" name="%s" id="%s" value="1"%s> %s</label>',
                esc_attr($name), esc_attr($id), checked($value, '1', false), esc_html($f['label']));
            break;

        case 'image':
            $url = $value ? wp_get_attachment_image_url((int) $value, 'medium') : '';
            printf('<div class="shtob-image-field" data-target="%s">', esc_attr($id));
            printf('<input type="hidden" name="%s" id="%s" value="%s">', esc_attr($name), esc_attr($id), esc_attr($value));
            printf('<img src="%s" alt="" style="max-width:220px;height:auto;display:%s;border-radius:6px;margin-bottom:.5em">',
                esc_url($url), $url ? 'block' : 'none');
            echo '<button type="button" class="button shtob-pick">Выбрать фотографию</button> ';
            echo '<button type="button" class="button-link shtob-clear"' . ($url ? '' : ' style="display:none"') . '>Убрать</button>';
            echo '</div>';
            break;

        case 'url':
            printf('<input type="url" class="large-text" name="%s" id="%s" value="%s">',
                esc_attr($name), esc_attr($id), esc_attr($value));
            break;

        default:
            printf('<input type="text" class="large-text" name="%s" id="%s" value="%s">',
                esc_attr($name), esc_attr($id), esc_attr($value));
    }
}

/**
 * Saving. Sanitising is driven by the same declaration that rendered the field,
 * so a new field cannot arrive without one — the default branch strips tags.
 */
function shtob_save_meta($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!isset($_POST['shtob_nonce']) || !wp_verify_nonce($_POST['shtob_nonce'], 'shtob_save_' . $post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach (shtob_field_groups() as $group) {
        if (!shtob_box_applies($group, $post)) continue;
        foreach ($group['fields'] as $key => $f) {
            $name = 'shtob_' . $key;

            if ($f['type'] === 'checkbox') {
                // An unchecked box sends nothing at all, so absence is the value.
                update_post_meta($post_id, '_shtob_' . $key, isset($_POST[$name]) ? '1' : '');
                continue;
            }
            if (!isset($_POST[$name])) continue;
            $raw = wp_unslash($_POST[$name]);

            switch ($f['type']) {
                case 'textarea':    $clean = sanitize_textarea_field($raw); break;
                case 'url':         $clean = esc_url_raw($raw); break;
                case 'image':
                case 'page_select': $clean = $raw === '' ? '' : (string) (int) $raw; break;
                case 'select':
                    $choices = call_user_func($f['choices']);
                    $clean = array_key_exists($raw, $choices) ? $raw : (string) ($f['default'] ?? '');
                    break;
                default:            $clean = sanitize_text_field($raw);
            }
            update_post_meta($post_id, '_shtob_' . $key, $clean);
        }
    }
}
add_action('save_post', 'shtob_save_meta', 10, 2);

/** The media picker for `image` fields. Only loaded on screens that have one. */
function shtob_admin_assets($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    wp_enqueue_media();
    wp_enqueue_script('shtob-admin', get_template_directory_uri() . '/js/admin.js',
        ['jquery'], SHTOB_VERSION, true);
}
add_action('admin_enqueue_scripts', 'shtob_admin_assets');
