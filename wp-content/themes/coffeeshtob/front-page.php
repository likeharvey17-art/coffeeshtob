<?php
/**
 * The front page: hero, a short introduction, and a way in to each section.
 *
 * THE SECTION INDEX IS GENERATED FROM THE PAGES, not typed out. Each section
 * supplies its own photo (its featured image) and one-line teaser, so renaming a
 * page, reordering them or adding an eighth updates this index with no edit here
 * — and the front page can never advertise a section that no longer exists.
 * It is the same list the navigation uses, for the same reason.
 */
if (!defined('ABSPATH')) exit;

get_header();

$id    = get_queried_object_id();
$hero  = get_post_thumbnail_id($id);
$about = get_post_meta($id, '_shtob_about_image', true);
?>
<main id="content">

  <section class="hero">
    <?php shtob_bare_image($hero, 'shtob-hero', 'hero-bg'); ?>
    <div class="hero-overlay"></div>

    <div class="hero-inner">
      <h1><?php echo esc_html(get_post_meta($id, '_shtob_hero_title', true) ?: get_bloginfo('name')); ?></h1>
      <?php $lead = get_post_meta($id, '_shtob_hero_lead', true); ?>
      <?php if ($lead) : ?><p class="hero-lead"><?php echo esc_html($lead); ?></p><?php endif; ?>

      <?php // Each button renders only when it has BOTH a label and a link, so a
            // half-filled form cannot produce a button that goes nowhere. ?>
      <div class="hero-actions">
        <?php foreach ([['cta1', 'btn-accent'], ['cta2', 'btn-ghost']] as [$k, $class]) :
            $text = get_post_meta($id, '_shtob_' . $k . '_text', true);
            $url  = get_post_meta($id, '_shtob_' . $k . '_url', true);
            if (!$text || !$url) continue;
            // A path is resolved against the site so the buttons keep working if
            // the site ever moves to a subdirectory; a full URL is left alone.
            $href = preg_match('#^https?://#i', $url) ? $url : home_url($url);
        ?>
          <a href="<?php echo esc_url($href); ?>" class="btn <?php echo esc_attr($class); ?>"><?php echo esc_html($text); ?></a>
        <?php endforeach; ?>
      </div>

      <?php // The address, as a plain line at the foot of the photograph. It was
            // a frosted pill at the top, which read as a badge rather than as
            // the one fact a first-time visitor needs from this screen. ?>
      <?php $badge = get_post_meta($id, '_shtob_hero_badge', true); ?>
      <?php if ($badge) : ?>
        <p class="hero-place">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"></path>
            <circle cx="12" cy="9.5" r="2.3"></circle>
          </svg>
          <?php echo esc_html($badge); ?>
        </p>
      <?php endif; ?>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2><?php echo esc_html(get_post_meta($id, '_shtob_about_title', true)); ?></h2>
      </div>

      <div class="about-grid">
        <?php shtob_frame($about, 'shtob-feature', 'img-frame--about',
                          get_post_meta($id, '_shtob_about_title', true)); ?>
        <div class="about-text">
          <?php shtob_paragraphs(get_post_meta($id, '_shtob_about_text', true)); ?>
        </div>
      </div>
    </div>
  </section>

  <section class="section section-alt">
    <div class="container">
      <?php $stitle = get_post_meta($id, '_shtob_sections_title', true); ?>
      <?php if ($stitle) : ?>
        <div class="section-head"><h2><?php echo esc_html($stitle); ?></h2></div>
      <?php endif; ?>

      <?php // An index, not a tile grid. Seven tiles in threes left one alone on
            // the last row, and a section still waiting for its photo showed an
            // empty frame on the page every visitor sees first. As rows, any
            // number of sections lays out cleanly and a missing photo simply
            // leaves the row without a thumbnail. The whole row is the link, so
            // it carries no «Смотреть» of its own. ?>
      <ol class="section-index">
        <?php foreach (shtob_nav_pages() as $p) :
            $teaser = get_post_meta($p->ID, '_shtob_card_teaser', true);
            $thumb  = get_post_thumbnail_id($p->ID);
        ?>
          <li>
            <a class="index-row" href="<?php echo esc_url(get_permalink($p)); ?>">
              <span class="index-text">
                <span class="index-title"><?php echo esc_html($p->post_title); ?></span>
                <?php if ($teaser) : ?><span class="index-teaser"><?php echo esc_html($teaser); ?></span><?php endif; ?>
              </span>
              <?php if ($thumb && wp_attachment_is_image($thumb)) : ?>
                <?php shtob_frame($thumb, 'shtob-card', 'img-frame--index', ''); ?>
              <?php endif; ?>
              <svg class="index-go" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

</main>
<?php get_footer(); ?>
