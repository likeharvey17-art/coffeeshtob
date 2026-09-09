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
 * Price and description are both optional and omit their element — that is what
 * stops an unpriced item leaving a stray gap.
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
    <article class="menu-item">
      <div class="menu-thumb">
        <?php if ($thumb && wp_attachment_is_image($thumb)) :
            echo wp_get_attachment_image($thumb, 'shtob-thumb', false,
                ['alt' => $title, 'loading' => 'lazy', 'decoding' => 'async']);
        else : ?>
            <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/placeholder.svg'); ?>"
                 alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
        <?php endif; ?>
      </div>
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
