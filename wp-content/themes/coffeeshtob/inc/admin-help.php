<?php
/**
 * A short note on the dashboard, in Russian, explaining where things are.
 *
 * The person editing this site runs a café and did not choose WordPress. Three
 * things are genuinely not guessable — that карточки are a separate menu, that
 * the address lives in the Customiser, and that the top menu builds itself — and
 * each of them is a support call. Better to answer them on the first screen.
 */
if (!defined('ABSPATH')) exit;

function shtob_dashboard_widget() {
    wp_add_dashboard_widget('shtob_help', 'Как редактировать сайт', 'shtob_dashboard_html');
}
add_action('wp_dashboard_setup', 'shtob_dashboard_widget');

function shtob_dashboard_html() {
    $cards  = admin_url('edit.php?post_type=' . SHTOB_CARD);
    $pages  = admin_url('edit.php?post_type=page');
    $custom = admin_url('customize.php');
    ?>
    <p><strong>Тексты и фотографии разделов</strong> — <a href="<?php echo esc_url($pages); ?>">Страницы</a>.
       Каждый раздел сайта это одна страница. Фото в шапке раздела — блок
       «Изображение записи» справа.</p>

    <p><strong>Карточки внутри разделов и позиции меню</strong> —
       <a href="<?php echo esc_url($cards); ?>">Карточки</a>.
       У каждой карточки выбирается раздел, в котором она показывается. Порядок
       задаётся числом в поле «Порядок» (блок «Атрибуты» справа): меньше — выше.</p>

    <p><strong>Адрес, телефон и текст в подвале</strong> —
       <a href="<?php echo esc_url($custom); ?>">Внешний вид → Настроить → Контакты Кофештаба</a>.
       Они показываются сразу на всех страницах, поэтому и хранятся в одном месте.</p>

    <p><strong>Верхнее меню собирается само</strong> из страниц — новая страница
       появится в нём сразу. Чтобы убрать страницу из меню, поставьте галочку
       «Не показывать в меню» в блоке «Меню сайта» при её редактировании.</p>

    <p style="border-top:1px solid #dcdcde;padding-top:1em;margin-top:1em">
       Пустое поле — это нормально: блок просто не покажется. Если у карточки нет
       фотографии, на её месте будет аккуратная заглушка, а не пустое место.</p>
    <?php
}

/** The «Записи» menu is unused — every piece of content here is a page or a card. */
function shtob_hide_posts_menu() {
    remove_menu_page('edit.php');
}
add_action('admin_menu', 'shtob_hide_posts_menu', 11);
