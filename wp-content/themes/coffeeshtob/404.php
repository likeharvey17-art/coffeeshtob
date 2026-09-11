<?php
/**
 * Not found.
 *
 * `noindex, follow` is the correct pair: a 404 must not be indexed, but its
 * links should still be crawled so the crawler finds its way back to real pages.
 *
 * The section list is the same shtob_nav() the header and footer call, so it can
 * never advertise a page that no longer exists — the static site's 404 carried
 * its own hardcoded copy, which was a third place to forget. Nothing is marked
 * current here, correctly: you are not on any of them.
 */
if (!defined('ABSPATH')) exit;

add_filter('wp_robots', function ($robots) {
    $robots['noindex'] = true;
    $robots['follow']  = true;
    return $robots;
});

get_header('legal');
?>
<main class="legal" id="content">
  <div class="legal-inner">
    <span class="eyebrow">Ошибка 404</span>
    <h1>Такой страницы нет</h1>
    <p>Возможно, она переехала или в адресе опечатка. А кофе на месте — заходите
    на главную и посмотрите, что у нас есть.</p>
    <nav class="notfound-nav" aria-label="Разделы сайта">
      <?php shtob_nav(-1); ?>
    </nav>
    <p class="legal-back">
      <a class="link-arrow" href="<?php echo esc_url(home_url('/')); ?>">← Вернуться на главную</a>
    </p>
  </div>
</main>
<?php get_footer('legal'); ?>
