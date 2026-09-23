<?php
/**
 * Template Name: Раздел с карточками
 *
 * The workhorse: Окрестности, Гостиная, Команда, Мастера and Сказки are all
 * "a banner, some words, then a list of things with photos", so they share one
 * template rather than being five near-copies.
 *
 * This is also what `page.php` falls back to, so a page the client creates
 * without choosing a template still renders correctly instead of landing on
 * something blank.
 */
if (!defined('ABSPATH')) exit;

get_header();

$id         = get_queried_object_id();
$layout     = get_post_meta($id, '_shtob_layout', true) ?: 'alt';
$lead_title = get_post_meta($id, '_shtob_lead_title', true);
$lead       = get_post_meta($id, '_shtob_lead', true);
$outro_t    = get_post_meta($id, '_shtob_outro_title', true);
$outro_x    = get_post_meta($id, '_shtob_outro_text', true);
$outro_lt   = get_post_meta($id, '_shtob_outro_link_text', true);
$outro_lu   = get_post_meta($id, '_shtob_outro_link_url', true);
$cards      = shtob_cards_for($id, 'main');
// With no lead heading the cards sit directly under the page's h1, so they
// are the h2s — otherwise the outline skips a level.
$hx         = $lead_title ? 'h3' : 'h2';
?>
<main id="content">

  <?php get_template_part('parts/banner'); ?>

  <section class="section">
    <div class="container">

      <?php if ($lead || $lead_title) : ?>
        <div class="section-head">
          <?php if ($lead_title) : ?><h2><?php echo esc_html($lead_title); ?></h2><?php endif; ?>
          <?php shtob_paragraphs($lead, 'section-sub'); ?>
        </div>
      <?php endif; ?>

      <?php get_template_part('parts/cards-list', null, ['cards' => $cards, 'layout' => $layout, 'heading' => $hx]); ?>

      <?php // The page's own editor content, if the client typed any. Rare, but
            // it is the only place a section can say something that is not a
            // card, and a page with no cards yet would otherwise be silent. ?>
      <?php if (trim(get_post_field('post_content', $id)) !== '') : ?>
        <div class="about-text page-content">
          <?php echo wp_kses_post(apply_filters('the_content', get_post_field('post_content', $id))); ?>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <?php // An optional closing note — Окрестности uses it to hand visitors off to
        // the tourist guide. The link renders only when it has both its fields,
        // so a half-filled form cannot produce a bare arrow pointing nowhere. ?>
  <?php if ($outro_t || $outro_x) : ?>
    <section class="section section-alt">
      <div class="container">
        <div class="info-grid">
          <article class="info-card info-card--wide">
            <?php if ($outro_t) : ?><<?php echo $hx; ?>><?php echo esc_html($outro_t); ?></<?php echo $hx; ?>><?php endif; ?>
            <?php if ($outro_x) : ?><p><?php echo esc_html($outro_x); ?></p><?php endif; ?>
            <?php if ($outro_lt && $outro_lu) : ?>
              <a class="link-arrow" href="<?php echo esc_url($outro_lu); ?>" target="_blank" rel="noopener"><?php echo esc_html($outro_lt); ?> →</a>
            <?php endif; ?>
          </article>
        </div>
      </div>
    </section>
  <?php endif; ?>

</main>
<?php get_footer(); ?>
