<?php
/**
 * Template Name: Контакты и график работы
 *
 * The hours table is the ONE framed element on the whole site
 * (.info-card--panel) and that is deliberate: it is the only tabular content,
 * people scan for it, and being the only box is what makes it read as emphasis.
 * Its siblings are plain text. Never move the box styling onto the shared
 * .info-card selector — that re-boxes everything and destroys the distinction.
 *
 * The address and phone come from the Customiser, not from this page, because
 * they also appear in the footer of every page and in the structured data. One
 * value, one truth: written twice, the admin would change what the page SHOWS
 * while the tel: link kept dialling the old number.
 */
if (!defined('ABSPATH')) exit;

get_header();

$id    = get_queried_object_id();
$hours = shtob_parse_hours(get_post_meta($id, '_shtob_hours', true));
$note  = get_post_meta($id, '_shtob_hours_note', true);
$ferry_t = get_post_meta($id, '_shtob_ferry_title', true);
$ferry_x = get_post_meta($id, '_shtob_ferry_text', true);
$phone = shtob_opt('phone');
?>
<main id="content">

  <?php get_template_part('parts/banner'); ?>

  <section class="section">
    <div class="container">
      <div class="info-grid">

        <article class="info-card info-card--panel">
          <h3><?php echo esc_html(get_post_meta($id, '_shtob_hours_title', true) ?: 'График работы'); ?></h3>
          <dl class="schedule-list">
            <?php foreach ($hours as $row) : ?>
              <div>
                <dt><?php echo esc_html($row['label']); ?></dt>
                <dd><?php echo esc_html($row['time']); ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
          <?php if ($note) : ?><p class="note"><?php echo esc_html($note); ?></p><?php endif; ?>
        </article>

        <article class="info-card">
          <h3><?php echo esc_html(get_post_meta($id, '_shtob_address_title', true) ?: 'Где мы находимся'); ?></h3>
          <p><?php echo esc_html(shtob_opt('address')); ?></p>
          <p class="note"><?php echo esc_html(shtob_opt('address_note')); ?></p>
          <?php if ($phone) : ?>
            <p><a class="legal-link" href="tel:<?php echo esc_attr(shtob_tel($phone)); ?>"><?php echo esc_html($phone); ?></a></p>
          <?php endif; ?>
          <?php if (shtob_opt('maps_url')) : ?>
            <a class="link-arrow" href="<?php echo esc_url(shtob_opt('maps_url')); ?>" target="_blank" rel="noopener">Открыть на Яндекс.Картах →</a>
          <?php endif; ?>
        </article>

        <?php if ($ferry_t || $ferry_x) : ?>
          <article class="info-card info-card--wide">
            <?php if ($ferry_t) : ?><h3><?php echo esc_html($ferry_t); ?></h3><?php endif; ?>
            <?php if ($ferry_x) : ?><p><?php echo esc_html($ferry_x); ?></p><?php endif; ?>
          </article>
        <?php endif; ?>

      </div>
    </div>
  </section>

</main>
<?php get_footer(); ?>
