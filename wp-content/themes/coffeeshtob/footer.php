<?php
/**
 * The footer. Its navigation comes from shtob_nav(), the same function the
 * header calls, so the two can no longer disagree — they were separate
 * hardcoded lists once and had already drifted.
 *
 * #social stays as an id: it is the no-JS fallback target for the header's
 * «Соцсети» button, which is a dialog trigger when JavaScript is running.
 *
 * The zero-height #contacts marker that used to close this element is gone with
 * the anchors — Контакты is a real page now. If an in-page link to the very
 * bottom of a document is ever needed again, that was the trick: anchoring the
 * footer itself aligns its TOP, which on a footer taller than a phone screen
 * leaves the contact details below the fold.
 */
if (!defined('ABSPATH')) exit;

$privacy = get_page_by_path('privacy');
?>
<footer class="site-footer">
  <div class="container footer-grid">

    <div class="footer-col footer-brand">
      <a class="brand brand-footer" href="<?php echo esc_url(home_url('/')); ?>" data-home>
        <span class="brand-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
            <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
            <line x1="6" y1="1" x2="6" y2="4"></line>
            <line x1="10" y1="1" x2="10" y2="4"></line>
            <line x1="14" y1="1" x2="14" y2="4"></line>
          </svg>
        </span>
        <span class="brand-text">
          <strong>Кофештаб</strong>
          <small>РОМАНОВ НА ВОЛГЕ</small>
        </span>
      </a>
      <p><?php echo esc_html(shtob_opt('footer_about')); ?></p>
    </div>

    <div class="footer-col">
      <h4>ГДЕ МЫ НАХОДИМСЯ</h4>
      <p><?php echo esc_html(shtob_opt('address')); ?></p>
      <p class="note"><?php echo esc_html(shtob_opt('address_note')); ?></p>
      <?php if (shtob_opt('maps_url')) : ?>
        <a class="link-arrow" href="<?php echo esc_url(shtob_opt('maps_url')); ?>" target="_blank" rel="noopener">Открыть на Яндекс.Картах →</a>
      <?php endif; ?>
    </div>

    <div class="footer-col">
      <h4>НАВИГАЦИЯ</h4>
      <nav class="footer-nav" aria-label="Разделы сайта (подвал)">
        <?php shtob_nav(); ?>
      </nav>
    </div>

    <div class="footer-col" id="social">
      <h4>МЫ НА СВЯЗИ</h4>
      <?php
      // One setting drives both the label and the tel: target. Written as two
      // values, editing the number would change what the footer SHOWS while the
      // link kept dialling the old one — a break nobody notices until a customer
      // calls the wrong number.
      $phone = shtob_opt('phone');
      if ($phone) : ?>
        <a class="footer-phone" href="tel:<?php echo esc_attr(shtob_tel($phone)); ?>">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"></path></svg>
          <span><?php echo esc_html($phone); ?></span>
        </a>
      <?php endif; ?>
      <div class="social-links">
        <?php shtob_social_links(); ?>
      </div>
    </div>

  </div>

  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <span>© <?php echo esc_html(wp_date('Y')); ?> «Кофештаб»</span>
      <span class="dot">·</span>
      <span>Волжская набережная, 19 · Ярославская область, Тутаев, левый берег</span>
      <?php if ($privacy) : ?>
        <span class="dot">·</span>
        <a class="footer-legal-link" href="<?php echo esc_url(get_permalink($privacy)); ?>">Политика конфиденциальности</a>
      <?php endif; ?>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
