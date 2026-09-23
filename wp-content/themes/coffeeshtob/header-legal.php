<?php
/**
 * The two-item header for the legal pages: brand, then «На главную».
 *
 * There is no .main-nav between them, and style.css keys the button's
 * right-alignment off exactly that absence — `.brand + .nav-social-btn
 * { margin-left: auto }` — rather than off a modifier class. Matching it
 * structurally means any future page with no nav gets it right with no class to
 * remember.
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
<header class="site-header" id="top">
  <div class="header-inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Кофештаб — на главную">
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
        <small>Романов на Волге</small>
      </span>
    </a>
    <a class="btn btn-accent nav-social-btn" href="<?php echo esc_url(home_url('/')); ?>">На главную</a>
  </div>
</header>
