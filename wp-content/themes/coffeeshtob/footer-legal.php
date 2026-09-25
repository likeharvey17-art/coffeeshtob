<?php
/** One line, no columns, no contact block — the full footer would be taller
 *  than the page it sits under. */
if (!defined('ABSPATH')) exit;
?>
<footer class="site-footer legal-footer">
  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <span>© <?php echo esc_html(wp_date('Y')); ?> «Кофештаб»</span>
      <span><?php echo esc_html(shtob_opt('address')); ?></span>
    </div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
