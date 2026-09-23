<?php
/**
 * The compact band that opens every page except the front one.
 *
 * Home keeps the full-screen hero. Repeating a 100svh photo on seven more pages
 * would put the actual content below the fold everywhere and make the site read
 * as seven landing pages rather than one site with sections — so this is short,
 * with the same blur, scale and overlay so the treatment stays in the family.
 *
 * It reuses .hero-bg's mechanics deliberately: blur() feathers an element's own
 * edges, which on a full-bleed image shows as a pale halo down the sides, and
 * the scale exists to push that feathered edge out of frame. Don't drop the
 * scale while keeping the blur.
 */
if (!defined('ABSPATH')) exit;

$page_id = get_queried_object_id();
$banner  = get_post_thumbnail_id($page_id);
$intro   = get_post_meta($page_id, '_shtob_intro', true);
$has_img = $banner && wp_attachment_is_image($banner);
?>
<?php // With no photo yet this is a plain dark band, not a blurred placeholder:
      // the placeholder graphic is a line-art picture frame, and stretched
      // across a banner it reads as a broken image rather than as "a photo is
      // coming" — the opposite of what a placeholder is for. ?>
<section class="page-banner<?php echo $has_img ? '' : ' page-banner--plain'; ?>">
  <?php if ($has_img) : ?>
    <?php echo wp_get_attachment_image($banner, 'shtob-banner', false, [
        'class' => 'banner-bg', 'alt' => '', 'loading' => 'eager', 'decoding' => 'async',
    ]); ?>
    <div class="banner-overlay"></div>
  <?php endif; ?>
  <div class="container banner-inner">
    <?php // Named to match this page's row in the front page's index, so on a
          // browser with cross-document view transitions the title you clicked
          // travels up into place instead of being replaced. ?>
    <h1 style="view-transition-name: page-title-<?php echo (int) $page_id; ?>"><?php echo esc_html(get_the_title($page_id)); ?></h1>
    <?php if ($intro) : ?><p class="banner-lead"><?php echo esc_html($intro); ?></p><?php endif; ?>
  </div>
</section>
