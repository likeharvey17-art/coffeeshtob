<?php
/**
 * The safety net, and it matters more than a fallback usually does: a client
 * adding a page in the admin and not picking a template lands HERE. Grav's
 * equivalent rendered a blank body, which is exactly how the first activation of
 * that theme failed.
 *
 * So this is not an empty stub — it is the cards template, which is what a new
 * section almost always wants.
 */
if (!defined('ABSPATH')) exit;
require get_template_directory() . '/template-cards.php';
