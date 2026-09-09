<?php
/**
 * Инструменты → Наполнить сайт — the one-time content seed.
 *
 * WHY THIS EXISTS AT ALL. A fresh WordPress on a new hosting account is empty,
 * and this site is eight pages, thirty-one cards and twelve photographs. Typing
 * that back in by hand is hours of work and a guaranteed source of typos in
 * someone else's Russian; a WXR import needs the importer plugin and a
 * reachable source URL for every photo. This reads seed/content.php and
 * seed/photos/, which travel with the theme, and needs neither.
 *
 * IT REFUSES TO RUN TWICE, and that guard is the important part of the file. The
 * dangerous version of this feature is the one that runs a second time on a site
 * the client has been editing for a month and replaces their work with the
 * placeholders it shipped with. So it checks for its own marker AND for pages it
 * did not create, and stops on either.
 *
 * Everything it makes is ordinary WordPress content from that moment on. Nothing
 * in the theme reads seed/ again.
 */
if (!defined('ABSPATH')) exit;

const SHTOB_SEED_DONE = 'shtob_seed_done';

function shtob_seed_dir() {
    return get_template_directory() . '/seed';
}

function shtob_seed_available() {
    return file_exists(shtob_seed_dir() . '/content.php');
}

function shtob_seed_menu() {
    if (!shtob_seed_available() || get_option(SHTOB_SEED_DONE)) return;
    add_management_page('Наполнить сайт', 'Наполнить сайт', 'manage_options',
        'shtob-seed', 'shtob_seed_page');
}
add_action('admin_menu', 'shtob_seed_menu');

/** A nudge on a site that is still empty — otherwise the page is never found. */
function shtob_seed_notice() {
    if (!shtob_seed_available() || get_option(SHTOB_SEED_DONE)) return;
    if (!current_user_can('manage_options')) return;
    printf('<div class="notice notice-info"><p><strong>Сайт ещё не наполнен.</strong> '
        . 'Страницы, карточки и фотографии можно создать одним действием: '
        . '<a href="%s">Инструменты → Наполнить сайт</a>.</p></div>',
        esc_url(admin_url('tools.php?page=shtob-seed')));
}
add_action('admin_notices', 'shtob_seed_notice');

function shtob_seed_page() {
    if (!current_user_can('manage_options')) wp_die('Недостаточно прав.');

    $existing = get_pages(['post_status' => 'publish,draft,private']);
    $blocked  = count($existing) > 1;

    if (isset($_POST['shtob_seed_go']) && check_admin_referer('shtob_seed')) {
        if ($blocked) {
            echo '<div class="notice notice-error"><p>На сайте уже есть страницы — наполнение отменено.</p></div>';
        } else {
            $report = shtob_seed_run();
            echo '<div class="wrap"><h1>Сайт наполнен</h1><ul style="list-style:disc;margin-left:2em">';
            foreach ($report as $line) echo '<li>' . esc_html($line) . '</li>';
            echo '</ul><p><a class="button button-primary" href="' . esc_url(home_url('/')) . '">Открыть сайт</a></p></div>';
            return;
        }
    }
    ?>
    <div class="wrap">
      <h1>Наполнить сайт</h1>
      <p>Создаст восемь страниц с текстами и фотографиями, карточки внутри
         разделов и позиции меню — то, с чем сайт был собран. Дальше всё
         редактируется обычным образом.</p>
      <p>Несколько разделов намеренно оставлены заготовками: у них есть названия,
         но нет текста и фотографий — их добавите вы.</p>
      <?php if ($blocked) : ?>
        <div class="notice notice-error"><p><strong>Наполнение недоступно:</strong>
          на сайте уже есть страницы. Это защита от того, чтобы случайно затереть
          вашу работу. Если сайт нужно наполнить с нуля, сначала удалите
          существующие страницы.</p></div>
      <?php else : ?>
        <form method="post">
          <?php wp_nonce_field('shtob_seed'); ?>
          <p><button type="submit" name="shtob_seed_go" value="1" class="button button-primary button-hero">
            Наполнить сайт</button></p>
        </form>
      <?php endif; ?>
    </div>
    <?php
}

/** Put one seed photo into the media library, once, and return its attachment id. */
function shtob_seed_photo($filename, &$cache) {
    if ($filename === '') return 0;
    if (isset($cache[$filename])) return $cache[$filename];

    $path = shtob_seed_dir() . '/photos/' . $filename;
    if (!file_exists($path)) return $cache[$filename] = 0;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Copy first: media_handle_sideload MOVES the file it is given, and moving
    // it out of the theme would empty seed/photos/ halfway through a run that
    // then failed.
    $tmp = wp_tempnam($filename);
    if (!$tmp || !copy($path, $tmp)) return $cache[$filename] = 0;

    $id = media_handle_sideload(
        ['name' => $filename, 'tmp_name' => $tmp],
        0,
        null,
        ['post_title' => pathinfo($filename, PATHINFO_FILENAME)]
    );
    if (is_wp_error($id)) {
        @unlink($tmp);
        return $cache[$filename] = 0;
    }
    return $cache[$filename] = (int) $id;
}

function shtob_seed_run() {
    $data   = require shtob_seed_dir() . '/content.php';
    $report = [];
    $photos = [];
    $ids    = [];

    // WordPress's own sample content, which would otherwise sit in the page list
    // and in the navigation. Removed by path, so nothing the client made is at
    // risk.
    foreach (['sample-page', 'privacy-policy'] as $slug) {
        $p = get_page_by_path($slug);
        if ($p) wp_delete_post($p->ID, true);
    }
    $hello = get_page_by_path('hello-world', OBJECT, 'post');
    if ($hello) wp_delete_post($hello->ID, true);

    foreach ($data['pages'] as $p) {
        $slug = $p['slug'];
        $id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $p['title'],
            'post_name'    => $slug !== '' ? $slug : 'home',
            'menu_order'   => (int) $p['menu_order'],
            'post_content' => '',
        ], true);
        if (is_wp_error($id)) { $report[] = 'Не удалось создать «' . $p['title'] . '»'; continue; }
        $ids[$slug] = $id;

        if ($p['template']) update_post_meta($id, '_wp_page_template', $p['template']);
        update_post_meta($id, '_shtob_menu_label', $p['menu_label']);
        if ($p['hide_in_menu']) update_post_meta($id, '_shtob_hide_in_menu', '1');
        foreach ($p['meta'] as $k => $v) update_post_meta($id, '_shtob_' . $k, $v);

        if (!empty($p['thumbnail'])) {
            $att = shtob_seed_photo($p['thumbnail'], $photos);
            if ($att) set_post_thumbnail($id, $att);
        }
        if (!empty($p['about_image'])) {
            $att = shtob_seed_photo($p['about_image'], $photos);
            if ($att) update_post_meta($id, '_shtob_about_image', (string) $att);
        }
        $report[] = 'Страница: ' . $p['title'];
    }

    $made = 0;
    foreach ($data['cards'] as $c) {
        $section = $ids[$c['page']] ?? 0;
        $id = wp_insert_post([
            'post_type'    => SHTOB_CARD,
            'post_status'  => 'publish',
            'post_title'   => $c['title'],
            'post_excerpt' => $c['excerpt'],
            'post_content' => $c['content'],
            'menu_order'   => (int) $c['order'],
        ], true);
        if (is_wp_error($id)) continue;
        update_post_meta($id, '_shtob_section', (string) $section);
        update_post_meta($id, '_shtob_list', $c['list']);
        update_post_meta($id, '_shtob_icon', $c['icon']);
        update_post_meta($id, '_shtob_price', $c['price']);
        if ($c['image']) {
            $att = shtob_seed_photo($c['image'], $photos);
            if ($att) set_post_thumbnail($id, $att);
        }
        $made++;
    }
    // Unattached photos: nothing points at them, they simply appear in the
    // media library for the sections that are still waiting on content.
    foreach ($data['library'] ?? [] as $file) {
        shtob_seed_photo($file, $photos);
    }

    $report[] = "Карточек создано: $made";
    $report[] = 'Фотографий загружено: ' . count(array_filter($photos));

    // The front page, and the permalink shape every URL in the sitemap and the
    // structured data depends on. Left at WordPress's default (?p=123) the whole
    // site would be query strings.
    if (!empty($ids[''])) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $ids['']);
    }
    if (get_option('permalink_structure') === '') {
        update_option('permalink_structure', '/%postname%/');
    }
    update_option('blogname', 'Кофештаб «Романов на Волге»');
    update_option('blogdescription', 'Кофейня в купеческом доме на Волжской набережной в Романове');
    update_option('timezone_string', 'Europe/Moscow');
    update_option('default_comment_status', 'closed');
    update_option('default_ping_status', 'closed');
    update_option('blog_public', 1);

    flush_rewrite_rules();
    update_option(SHTOB_SEED_DONE, gmdate('c'));

    // The seed is 5 MB of starting content that is never read again, and the
    // ordinary deploy deliberately does not upload it, so nothing puts it back.
    // Removing it here keeps the theme small and makes a second run impossible
    // by a second route. If the files are not ours to delete — different owner
    // between the FTP account and PHP, which happens on shared hosting — say so
    // rather than pretending.
    $report[] = match (shtob_seed_remove()) {
        'skipped' => 'Файлы с исходным содержимым оставлены (локальная установка).',
        true      => 'Файлы с исходным содержимым удалены — они больше не нужны.',
        default   => 'Файлы с исходным содержимым остались в теме (нет прав на удаление); '
                     . 'их можно удалить вручную: wp-content/themes/coffeeshtob/seed/',
    };
    $report[] = 'Готово. Этот пункт меню больше не появится.';

    return $report;
}

/**
 * Delete the seed folder. Returns true if it is gone, 'skipped' if it was
 * deliberately left alone, false if it could not be removed.
 *
 * The three-way answer exists because the first version returned a bare true for
 * "skipped" and the success report then claimed it had deleted files it had
 * pointedly not deleted. Caught by running the build script and reading what it
 * said against what was on disk.
 */
function shtob_seed_remove() {
    $dir = shtob_seed_dir();
    if (!is_dir($dir)) return true;

    // NEVER on the development install. There the theme directory is a SYMLINK
    // into the git working tree, so deleting "the theme's seed folder" would
    // delete the committed source files — and now that the previous build is
    // gone, seed/content.php is the only record of the starting content. A real
    // deploy is a plain directory and is unaffected.
    if (is_link(get_template_directory())) {
        return 'skipped';
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
    return !is_dir($dir);
}
