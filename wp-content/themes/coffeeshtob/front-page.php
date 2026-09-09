<?php
/**
 * The front page: hero, a short introduction, and a way in to each section.
 *
 * THE SECTION TILES ARE GENERATED FROM THE PAGES, not typed out. Each section
 * supplies its own photo (its featured image) and one-line teaser, so renaming a
 * page, reordering them or adding an eighth updates this grid with no edit here
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

    <div class="hero-top">
      <?php $badge = get_post_meta($id, '_shtob_hero_badge', true); ?>
      <?php if ($badge) : ?>
        <p class="badge">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"></path>
            <circle cx="12" cy="9.5" r="2.3"></circle>
          </svg>
          <?php echo esc_html($badge); ?>
        </p>
      <?php endif; ?>
    </div>

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
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow">О ШТАБЕ</span>
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
      <div class="section-head">
        <span class="eyebrow">РАЗДЕЛЫ</span>
        <h2><?php echo esc_html(get_post_meta($id, '_shtob_sections_title', true)); ?></h2>
        <?php $ssub = get_post_meta($id, '_shtob_sections_sub', true); ?>
        <?php if ($ssub) : ?><p class="section-sub"><?php echo esc_html($ssub); ?></p><?php endif; ?>
      </div>

      <div class="card-grid section-grid">
        <?php foreach (shtob_nav_pages() as $p) :
            $teaser = get_post_meta($p->ID, '_shtob_card_teaser', true);
        ?>
          <a class="card-item card-link" href="<?php echo esc_url(get_permalink($p)); ?>">
            <?php shtob_frame(get_post_thumbnail_id($p->ID), 'shtob-card', 'img-frame--card', $p->post_title); ?>
            <h3><?php echo esc_html($p->post_title); ?></h3>
            <?php if ($teaser) : ?><p><?php echo esc_html($teaser); ?></p><?php endif; ?>
            <span class="link-arrow" aria-hidden="true">Смотреть →</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

</main>
<?php get_footer(); ?>
