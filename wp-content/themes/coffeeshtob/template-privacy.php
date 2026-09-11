<?php
/**
 * Template Name: Политика конфиденциальности
 *
 * THE COPY LIVES HERE, NOT IN AN EDITOR, AND THAT IS DELIBERATE.
 *
 * This page makes factual legal assertions about how the site behaves: that it
 * collects nothing, has no forms, no registration, no visit counters and sets no
 * cookies. Those statements are true of the site as built — see inc/privacy.php,
 * which is what keeps them true on a platform whose defaults include a comment
 * form — and they stop being true the moment anyone adds a contact form, Yandex
 * Metrica, a chat widget or a consent banner.
 *
 * So adding any of those means rewriting this page in the same commit. That is a
 * code change, and it belongs with the code. Handing it to the editor invites it
 * to be padded back out with the boilerplate headings it was twice rewritten to
 * remove.
 *
 * Word count is load-bearing: ~126 words across three <h2> sections. Short is
 * the point. Don't grow it.
 *
 * The two things that are not legal text — the phone and the address — come from
 * the Customiser, so they cannot drift from the footer.
 */
if (!defined('ABSPATH')) exit;

get_header('legal');
$phone = shtob_opt('phone');
$updated = get_post_meta(get_queried_object_id(), '_shtob_updated', true) ?: '5 сентября 2026 года';
?>
<main class="legal" id="content">
  <div class="legal-inner">
    <span class="eyebrow">Правовая информация</span>
    <h1>Политика конфиденциальности</h1>
    <p class="legal-updated">Обновлено <?php echo esc_html($updated); ?></p>

    <div class="legal-callout">
      <p>Мы не собираем данные посетителей. На сайте нет форм, регистрации
      и счётчиков посещений. Cookie-файлы сайт не ставит, поэтому и баннера
      про согласие здесь нет.</p>
    </div>

    <h2>Записи сервера</h2>
    <p>Сервер записывает обращения к сайту: адрес страницы, время, IP
    и браузер. Без этих записей нельзя починить поломку или отсеять ботов.
    Мы их не разбираем.</p>

    <h2>Ссылки на другие сайты</h2>
    <p>С сайта можно перейти в наш Telegram, во ВКонтакте, на гид «Романов
    на Волге» и в Яндекс.Карты. Там действуют их правила, а не наши.</p>

    <h2>Если есть вопросы</h2>
    <p>Данных о вас у нас нет, удалять нечего. Если что-то непонятно,
    позвоните или заходите.</p>
    <p>
      <?php if ($phone) : ?>
        Телефон: <a class="legal-link" href="tel:<?php echo esc_attr(shtob_tel($phone)); ?>"><?php echo esc_html($phone); ?></a><br>
      <?php endif; ?>
      Адрес: <?php echo esc_html(shtob_opt('address')); ?>
    </p>

    <p class="legal-back">
      <a class="link-arrow" href="<?php echo esc_url(home_url('/')); ?>">← Вернуться на главную</a>
    </p>
  </div>
</main>
<?php get_footer('legal'); ?>
