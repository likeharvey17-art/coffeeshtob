<?php
/**
 * Внешний вид → Настроить → «Контакты Кофештаба».
 *
 * These four values appear in the footer of every page, on Контакты, and inside
 * the structured data search engines read. Storing them on a page would mean
 * the client editing the address in one place and the footer still showing the
 * old one — so they live once, here, and everything reads them.
 *
 * The social links are here too but are NOT content: they are the business's own
 * channels and change roughly never. They are settings rather than hardcoded
 * markup only because a dead link in the header of every page is worse than an
 * extra field.
 */
if (!defined('ABSPATH')) exit;

function shtob_options() {
    return [
        'address' => [
            'label'   => 'Адрес',
            'default' => 'Ярославская область, г. Тутаев (левый берег Романов), Волжская набережная, д. 19',
            'type'    => 'textarea',
        ],
        'address_note' => [
            'label'   => 'Ориентир',
            'default' => 'Ориентир: набережная Волги, между лестницей к переправе и Казанским храмом',
            'type'    => 'textarea',
        ],
        'phone' => [
            'label'   => 'Телефон',
            'default' => '+7 929 078 65 00',
            'type'    => 'text',
            'help'    => 'Показывается в подвале и работает как ссылка для звонка. Пишите как удобно читать — ссылка соберётся сама.',
        ],
        'footer_about' => [
            'label'   => 'Текст под логотипом в подвале',
            'default' => 'Кофейня в купеческом доме на левом берегу Волги. Хороший кофе, сыр из Борисоглеба, романовский квас, квартирники, своя 3D-мастерская и многое другое.',
            'type'    => 'textarea',
        ],
        'maps_url' => [
            'label'   => 'Ссылка на Яндекс.Карты',
            'default' => 'https://yandex.ru/maps/?text=%D0%A2%D1%83%D1%82%D0%B0%D0%B5%D0%B2%2C%20%D0%92%D0%BE%D0%BB%D0%B6%D1%81%D0%BA%D0%B0%D1%8F%20%D0%BD%D0%B0%D0%B1%D0%B5%D1%80%D0%B5%D0%B6%D0%BD%D0%B0%D1%8F%2C%2019',
            'type'    => 'url',
        ],
        'telegram' => ['label' => 'Telegram', 'default' => 'https://t.me/coffeeshtob',   'type' => 'url'],
        'vk'       => ['label' => 'ВКонтакте', 'default' => 'https://vk.com/coffeeshtob', 'type' => 'url'],
        'guide'    => ['label' => 'Гид по Романову', 'default' => 'https://romanovnavolge.ru/', 'type' => 'url'],
    ];
}

function shtob_opt($key) {
    $all = shtob_options();
    $default = $all[$key]['default'] ?? '';
    return get_theme_mod('shtob_' . $key, $default);
}

function shtob_customize($wp_customize) {
    $wp_customize->add_section('shtob_contacts', [
        'title'       => 'Контакты Кофештаба',
        'priority'    => 20,
        'description' => 'Показывается в подвале каждой страницы, на странице «Контакты» и в данных для поисковиков.',
    ]);

    foreach (shtob_options() as $key => $o) {
        $wp_customize->add_setting('shtob_' . $key, [
            'default'           => $o['default'],
            'sanitize_callback' => $o['type'] === 'url' ? 'esc_url_raw'
                : ($o['type'] === 'textarea' ? 'sanitize_textarea_field' : 'sanitize_text_field'),
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control('shtob_' . $key, [
            'label'       => $o['label'],
            'description' => $o['help'] ?? '',
            'section'     => 'shtob_contacts',
            'type'        => $o['type'] === 'textarea' ? 'textarea' : ($o['type'] === 'url' ? 'url' : 'text'),
        ]);
    }
}
add_action('customize_register', 'shtob_customize');
