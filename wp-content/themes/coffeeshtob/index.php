<?php
/**
 * WordPress requires index.php in every theme. Nothing on this site routes here
 * — there are no blog posts, and cards have no URLs — so it defers to page.php's
 * behaviour rather than inventing a listing nobody will see.
 */
if (!defined('ABSPATH')) exit;
require get_template_directory() . '/template-cards.php';
