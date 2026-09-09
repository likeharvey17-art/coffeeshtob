<?php
/**
 * Template Name: Кухня (меню и карточки)
 *
 * Its own template because menu rows carry a price and a square thumbnail, which
 * no other section has. The three lists are the same post type differing only by
 * the «Список» field — see inc/cards.php for why that is one type and not three.
 *
 * The cards below the menu use the same alternating rows as every other section.
 */
if (!defined('ABSPATH')) exit;

get_header();

$id     = get_queried_object_id();
$menu   = shtob_cards_for($id, 'menu');
$drinks = shtob_cards_for($id, 'drinks');
$items  = shtob_cards_for($id, 'main');
?>
<main id="content">

  <?php get_template_part('parts/banner'); ?>

  <section class="section">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow">ЧЕМ УГОЩАЕМ</span>
        <h2><?php echo esc_html(get_post_meta($id, '_shtob_menu_title', true)); ?></h2>
        <?php $sub = get_post_meta($id, '_shtob_menu_sub', true); ?>
        <?php if ($sub) : ?><p class="section-sub"><?php echo esc_html($sub); ?></p><?php endif; ?>
      </div>

      <?php get_template_part('parts/menu-list', null, ['cards' => $menu]); ?>

      <?php if ($drinks) : ?>
        <div class="subsection">
          <div class="section-head section-head-sm">
            <span class="eyebrow">ДРУГИЕ НАПИТКИ</span>
            <h3><?php echo esc_html(get_post_meta($id, '_shtob_drinks_title', true)); ?></h3>
            <?php $dsub = get_post_meta($id, '_shtob_drinks_sub', true); ?>
            <?php if ($dsub) : ?><p class="section-sub"><?php echo esc_html($dsub); ?></p><?php endif; ?>
          </div>
          <?php get_template_part('parts/menu-list', null, ['cards' => $drinks]); ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($items) : ?>
    <section class="section section-alt">
      <div class="container">
        <div class="section-head">
          <span class="eyebrow"><?php echo esc_html(get_post_meta($id, '_shtob_items_eyebrow', true) ?: 'ЧТО ЕЩЁ'); ?></span>
          <h2><?php echo esc_html(get_post_meta($id, '_shtob_items_title', true)); ?></h2>
          <?php $isub = get_post_meta($id, '_shtob_items_sub', true); ?>
          <?php if ($isub) : ?><p class="section-sub"><?php echo esc_html($isub); ?></p><?php endif; ?>
        </div>
        <?php get_template_part('parts/cards-list', null, ['cards' => $items, 'layout' => 'alt']); ?>
      </div>
    </section>
  <?php endif; ?>

</main>
<?php get_footer(); ?>
