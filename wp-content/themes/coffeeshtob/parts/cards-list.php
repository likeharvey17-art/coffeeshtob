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

$hx = ($args['heading'] ?? 'h3') === 'h2' ? 'h2' : 'h3';

/* A CARD WITH NO PHOTO IS NOT A ROW. It used to get a full-size placeholder
   frame, so Окрестности opened on four empty 4:3 frames each beside one lone
   heading: four screens of "unfinished" for four names. Consecutive cards
   without a photo now collect into one quiet text list in their place, in
   order, and a card moves up into the rows the moment its photo is added.
   Only the alternating layout does this; the others keep their frames. */
$has_photo = fn($c) => ($t = get_post_thumbnail_id($c)) && wp_attachment_is_image($t);
$runs = [];
foreach ($cards as $card) {
    $kind = ($layout !== 'alt' || $has_photo($card)) ? 'row' : 'text';
    if ($runs && end($runs)[0] === $kind && $kind === 'text') {
        $runs[count($runs) - 1][1][] = $card;
    } else {
        $runs[] = [$kind, [$card]];
    }
}

$render = function ($card, $framed) use ($conf, $hx) {
    $title = get_the_title($card);
    $sub   = $card->post_excerpt;
    $icon  = get_post_meta($card->ID, '_shtob_icon', true);
    $body  = trim($card->post_content);
    if ($framed) shtob_frame(get_post_thumbnail_id($card), $conf['size'], $conf['frame'], $title);
    if ($framed && $conf['body']) echo '<div class="feature-body">';
    if ($framed) shtob_icon($icon);
    echo "<$hx>" . esc_html($title) . "</$hx>";
    if ($sub) echo '<p class="card-sub">' . esc_html($sub) . '</p>';
    // the_content filters give the client real paragraphs and links from the
    // editor. wp_kses_post is what keeps that from becoming an injection route
    // for a compromised author account.
    if ($body !== '') echo wp_kses_post(apply_filters('the_content', $body));
    if ($framed && $conf['body']) echo '</div>';
};
?>
<div class="<?php echo esc_attr($conf['wrap']); ?>">
  <?php foreach ($runs as [$kind, $group]) : ?>
    <?php if ($kind === 'text') : ?>
      <div class="text-list">
        <?php foreach ($group as $card) : ?>
          <article class="text-item"><?php $render($card, false); ?></article>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <?php foreach ($group as $card) : ?>
        <article class="<?php echo esc_attr($conf['item']); ?>"><?php $render($card, true); ?></article>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
