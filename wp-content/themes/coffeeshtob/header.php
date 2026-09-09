<?php
/**
 * The sticky header.
 *
 * Two details here took real debugging and must survive any edit:
 *
 *  - The ☰ toggle is three bare <span>s with no border and no background. A
 *    circular bordered button was built, shown, and rejected explicitly. Don't
 *    put one back.
 *  - The logo points at the real home URL and carries `data-home`. It used to be
 *    href="#top" driven by JS, because a stuck header counts as already in view
 *    and an anchor jump only scrolls by the scroll-padding. With eight pages
 *    that trick would break the logo everywhere except home, so the href is
 *    genuine and script.js intercepts it only when you are already on that page.
 *    Works with JavaScript off.
 *
 * The social links are the business's own channels, set in the Customiser.
 */
if (!defined('ABSPATH')) exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#content">Перейти к содержимому</a>

<header class="site-header" id="top">
  <div class="header-inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" data-home aria-label="Кофештаб — на главную">
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

    <nav class="main-nav" id="main-nav" aria-label="Разделы сайта">
      <?php shtob_nav(); ?>
    </nav>

    <a class="btn btn-accent nav-social-btn" id="socialBtn" href="#social"
       aria-haspopup="dialog" aria-expanded="false" aria-controls="socialPop">Соцсети</a>

    <div class="social-pop" id="socialPop" role="dialog" aria-label="Мы на связи" hidden>
      <?php shtob_social_links(); ?>
    </div>

    <button class="nav-toggle" id="navToggle" aria-label="Меню"
            aria-expanded="false" aria-controls="main-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
