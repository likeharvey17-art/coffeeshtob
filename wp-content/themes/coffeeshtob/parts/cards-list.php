<?php
/**
 * One list of cards, in one of three layouts.
 *
 * Expects $cards (array of WP_Post) and $layout ('alt' | 'wide' | 'grid').
 *
 * EVERY SECTION USES `alt` — full-width rows, text left and photo right,
 * flipping each row — so the whole site reads as one continuous zig-zag from the
 * front page's About block onward. `wide` and `grid` are kept and currently
 * unused; `alt` is the default in both the template fallback and the editing
 * form, so a page the client creates matches the rest without their having to
 * know that.
 *
 * Every field except the title is optional and omits its element, so a
 * placeholder card carrying nothing but a name renders cleanly instead of
 * leaving empty tags behind. That matters right now: several sections are
 * waiting on the owner's photos and copy.
 */
if (!defined('ABSPATH')) exit;

// get_template_part() passes its third argument through as $args (WP 5.5+).
$cards  = $args['cards']  ?? [];
$layout = $args['layout'] ?? 'alt';
if (!$cards) return;

$conf = [
    'alt'  => ['wrap' => 'feature-grid', 'item' => 'feature-card', 'frame' => 'img-frame--feature',
               'size' => 'shtob-feature', 'body' => true],
    'wide' => ['wrap' => 'life-grid',    'item' => 'life-card',    'frame' => 'img-frame--wide',
               'size' => 'shtob-wide',   'body' => false],
    'grid' => ['wrap' => 'card-grid',    'item' => 'card-item',    'frame' => 'img-frame--card',
               'size' => 'shtob-card',   'body' => false],
][$layout] ?? null;

if (!$conf) {
    $conf = ['wrap' => 'feature-grid', 'item' => 'feature-card', 'frame' => 'img-frame--feature',
             'size' => 'shtob-feature', 'body' => true];
}
?>
<div class="<?php echo esc_attr($conf['wrap']); ?>">
  <?php foreach ($cards as $card) :
      $title = get_the_title($card);
      $sub   = $card->post_excerpt;
      $icon  = get_post_meta($card->ID, '_shtob_icon', true);
      $body  = trim($card->post_content);
  ?>
    <article class="<?php echo esc_attr($conf['item']); ?>">
      <?php shtob_frame(get_post_thumbnail_id($card), $conf['size'], $conf['frame'], $title); ?>
      <?php if ($conf['body']) : ?><div class="feature-body"><?php endif; ?>
        <?php shtob_icon($icon); ?>
        <h3><?php echo esc_html($title); ?></h3>
        <?php if ($sub) : ?><p class="card-sub"><?php echo esc_html($sub); ?></p><?php endif; ?>
        <?php
        // the_content filters give the client real paragraphs and links from the
        // editor. wp_kses_post is what keeps that from becoming an injection
        // route for a compromised author account.
        if ($body !== '') echo wp_kses_post(apply_filters('the_content', $body));
        ?>
      <?php if ($conf['body']) : ?></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
