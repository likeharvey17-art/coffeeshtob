<?php
/**
 * One-time content fixes that ship with a theme version.
 *
 * The pages live in the database, so a copy change the owner asked for cannot
 * be made by editing the seed alone — the seed only ever runs on an empty
 * install. These run once, on the first request after the theme updates.
 *
 * EVERY FIX IS CONDITIONAL ON THE VALUE STILL BEING THE ORIGINAL SEEDED TEXT.
 * If the client has typed their own words into a field, it is left alone: a
 * theme update must never overwrite something a person wrote.
 */
if (!defined('ABSPATH')) exit;

function shtob_migrate() {
    $done = (string) get_option('shtob_migrated', '');
    if (version_compare($done, '1.3.1', '>=')) return;

    /* 1.3.1 — Кухня, trimmed at the owner's request. The menu subtitle
       repeated the banner line above it, the drinks subtitle repeated the
       Песоченский фарфор card below, and the cards' subtitle said nothing the
       heading did not. «Также в штабе» becomes the plain «Другие напитки». */
    $kuhnya = get_page_by_path('kuhnya');
    if ($kuhnya) {
        $clear = [
            '_shtob_menu_sub'   => 'Зерно ярославской обжарки, местный хлеб, сыр и квас',
            '_shtob_drinks_sub' => 'Подаем в антикварном Песоченском фарфоре',
            '_shtob_items_sub'  => 'Откуда всё берётся и в чём мы это подаём',
        ];
        foreach ($clear as $key => $seeded) {
            if (get_post_meta($kuhnya->ID, $key, true) === $seeded) {
                delete_post_meta($kuhnya->ID, $key);
            }
        }
        if (get_post_meta($kuhnya->ID, '_shtob_drinks_title', true) === 'Также в штабе') {
            update_post_meta($kuhnya->ID, '_shtob_drinks_title', 'Другие напитки');
        }
    }

    update_option('shtob_migrated', '1.3.1', true);
}
add_action('init', 'shtob_migrate', 20);
