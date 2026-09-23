<?php
/**
 * The priced menu rows. Expects $cards.
 *
 * A LIST, NOT A GRID, and that was a considered replacement for a four-across
 * photo grid: a fixed column count leaves orphans on the last row, and on a
 * phone each square costs a full screen of scrolling. Prices make it a menu
 * proper, and a menu reads as rows.
 *
 * .menu-list's column count follows the available WIDTH, never the item count
 * (auto-fill, minmax(272px, 1fr)), so items can be added and removed in the
 * admin without anything in CSS ever needing to change.
 *
 * Price, description and photo are all optional and omit their element — that
 * is what stops an unpriced or unphotographed item leaving a stray gap.
 */
if (!defined('ABSPATH')) exit;

$cards = $args['cards'] ?? [];
if (!$cards) return;
?>
<div class="menu-list">
  <?php foreach ($cards as $card) :
      $title = get_the_title($card);
      $price = get_post_meta($card->ID, '_shtob_price', true);
      $body  = trim($card->post_content);
      $thumb = get_post_thumbnail_id($card);
  ?>
    <?php // No photo, no thumbnail: a placeholder square beside a price read as
          // a missing picture on a menu, where a plain row reads as a menu. ?>
    <?php $has_thumb = $thumb && wp_attachment_is_image($thumb); ?>
    <article class="menu-item<?php echo $has_thumb ? '' : ' menu-item--plain'; ?>">
      <?php if ($has_thumb) : ?>
        <div class="menu-thumb">
          <?php echo wp_get_attachment_image($thumb, 'shtob-thumb', false,
              ['alt' => $title, 'loading' => 'lazy', 'decoding' => 'async']); ?>
        </div>
      <?php endif; ?>
      <div class="menu-body">
        <div class="menu-head">
          <h3><?php echo esc_html($title); ?></h3>
          <?php if ($price) : ?><span class="menu-price"><?php echo esc_html($price); ?></span><?php endif; ?>
        </div>
        <?php if ($body !== '') echo wp_kses_post(apply_filters('the_content', $body)); ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
