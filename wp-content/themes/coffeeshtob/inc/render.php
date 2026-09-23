<?php
/** Small rendering helpers shared by the templates. */
if (!defined('ABSPATH')) exit;

/**
 * A photo inside its .img-frame, or the placeholder if there is none.
 *
 * THE PLACEHOLDER IS LOAD-BEARING, not a stopgap to be tidied away. Several
 * sections are waiting on photos the owner has not taken yet, and every one of
 * those cards still needs a correctly-proportioned frame: the frame is what
 * stops the page reflowing when a real photo finally arrives. Removing the
 * fallback would collapse those rows instead.
 */
function shtob_frame($attachment_id, $size, $frame_class, $alt = '', $eager = false) {
    $classes = 'img-frame ' . $frame_class;
    echo '<div class="' . esc_attr($classes) . '">';
    if ($attachment_id && wp_attachment_is_image($attachment_id)) {
        echo wp_get_attachment_image((int) $attachment_id, $size, false, [
            'alt'      => $alt,
            'loading'  => $eager ? 'eager' : 'lazy',
            'decoding' => 'async',
        ]);
    } else {
        printf('<img src="%s" alt="%s" loading="%s" decoding="async">',
            esc_url(get_template_directory_uri() . '/assets/images/placeholder.svg'),
            esc_attr($alt), $eager ? 'eager' : 'lazy');
    }
    echo '</div>';
}

/** A bare <img> with the same fallback — for the hero and the banner, which are not framed. */
function shtob_bare_image($attachment_id, $size, $class) {
    $src = $attachment_id && wp_attachment_is_image($attachment_id)
        ? wp_get_attachment_image_url((int) $attachment_id, $size)
        : get_template_directory_uri() . '/assets/images/placeholder.svg';
    printf('<img class="%s" src="%s" alt="" loading="eager" decoding="async">',
        esc_attr($class), esc_url($src));
}

/**
 * A blank line starts a new paragraph.
 *
 * The old render.js set textContent, which destroyed the <p> children that
 * .about-text's flex gap depends on, so the paragraphs ran together. Splitting
 * here and wrapping each line keeps the gap working. Output is escaped: a `<`
 * typed into the admin is text, not markup.
 */
function shtob_paragraphs($text, $class = '') {
    $attr = $class ? ' class="' . esc_attr($class) . '"' : '';
    foreach (preg_split('/\R/u', (string) $text) as $line) {
        $line = trim($line);
        if ($line !== '') echo '<p' . $attr . '>' . esc_html($line) . '</p>';
    }
}

/** Echo one named line icon, or nothing at all for an empty/unknown name. */
function shtob_icon($name) {
    if (!$name) return;
    include get_template_directory() . '/parts/icon.php';
}

/**
 * The opening hours, parsed from one textarea.
 *
 * Each line is  «label | visible time | days», e.g.
 *   БУДНИ (ПН–ПТ) | 15:15–19:00 | пн-пт
 *
 * The label and the time are shown verbatim — they are the client's words. The
 * machine-readable half of a row is DERIVED, never typed twice: the days token
 * maps to schema.org names and the opening/closing times are pulled out of the
 * displayed string with a regex. That is the whole point. Grav asked for
 * `days`, `opens` and `closes` as separate fields beside the text, so the panel
 * could say 15:15 while the rich result said something else and nothing would
 * have flagged it.
 *
 * A row that cannot be parsed still DISPLAYS; it just contributes nothing to the
 * structured data. Failing that way round is deliberate — an absent rich result
 * is a small loss where a wrong one sends people to a closed café.
 */
function shtob_parse_hours($raw) {
    $map = ['пн' => 'Monday', 'вт' => 'Tuesday', 'ср' => 'Wednesday', 'чт' => 'Thursday',
            'пт' => 'Friday', 'сб' => 'Saturday', 'вс' => 'Sunday'];
    $order = array_keys($map);
    $rows  = [];

    foreach (preg_split('/\R/u', (string) $raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line));
        $row = ['label' => $parts[0] ?? '', 'time' => $parts[1] ?? '', 'days' => [], 'opens' => '', 'closes' => ''];

        // Times, from the string the visitor reads. Two HH:MM in order.
        if (preg_match_all('/\b(\d{1,2}:\d{2})\b/u', $row['time'], $m) && count($m[1]) >= 2) {
            $row['opens']  = $m[1][0];
            $row['closes'] = $m[1][1];
        }

        $days = mb_strtolower($parts[2] ?? '', 'UTF-8');
        if ($days !== '') {
            if (str_contains($days, 'ежеднев')) {
                $row['days'] = array_values($map);
            } elseif (preg_match('/^(пн|вт|ср|чт|пт|сб|вс)\s*[-–—]\s*(пн|вт|ср|чт|пт|сб|вс)$/u', $days, $m)) {
                $a = array_search($m[1], $order, true);
                $b = array_search($m[2], $order, true);
                if ($a !== false && $b !== false && $a <= $b) {
                    foreach (array_slice($order, $a, $b - $a + 1) as $d) $row['days'][] = $map[$d];
                }
            } else {
                foreach (preg_split('/[,\s]+/u', $days) as $d) {
                    if (isset($map[$d])) $row['days'][] = $map[$d];
                }
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

/** The phone as a tel: target — one field, so the link can never dial a stale number. */
function shtob_tel($phone) {
    return preg_replace('/[^\d+]/u', '', (string) $phone);
}

/**
 * The three social links, rendered identically in the header popover and the
 * footer. Written out twice once, they drifted — the footer gained a link the
 * header never got.
 */
/**
 * The business's channels, declared once. The footer and the header popover
 * list all three; Контакты turns the two social ones into buttons. One list
 * means a channel added here reaches every place it should appear.
 */
function shtob_socials() {
    $all = [
        'telegram' => ['url' => shtob_opt('telegram'), 'label' => 'Telegram-канал', 'name' => 'Telegram',
            'svg' => '<path d="M22 2 11 13"></path><path d="M22 2 15 22l-4-9-9-4 20-7z"></path>', 'w' => 2],
        'vk'       => ['url' => shtob_opt('vk'), 'label' => 'ВКонтакте', 'name' => 'ВКонтакте',
            'svg' => '<rect x="2.5" y="2.5" width="19" height="19" rx="5.5"></rect><path d="M6.6 8.9l2.3 6.2 2.3-6.2"></path><path d="M14.3 8.9v6.2"></path><path d="M17.9 8.9l-3.6 3.1 3.6 3.1"></path>', 'w' => 1.7],
        'guide'    => ['url' => shtob_opt('guide'), 'label' => 'Гид по Романову', 'name' => 'Гид по Романову',
            'svg' => '<circle cx="12" cy="12" r="10"></circle><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"></path>', 'w' => 2],
    ];
    return array_filter($all, fn($l) => (bool) $l['url']);
}

function shtob_social_svg($l, $size) {
    return sprintf('<svg viewBox="0 0 24 24" width="%d" height="%d" fill="none" stroke="currentColor" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        $size, $size, esc_attr((string) $l['w']), $l['svg']);
}

function shtob_social_links() {
    foreach (shtob_socials() as $l) {
        printf('<a href="%s" target="_blank" rel="noopener">%s %s</a>' . "\n",
            esc_url($l['url']), shtob_social_svg($l, 18), esc_html($l['label']));
    }
}

/**
 * Where a social link actually goes, in the form people say it aloud:
 * @coffeeshtob for Telegram, vk.com/coffeeshtob for VK. DERIVED from the URL in
 * the Customiser, never typed separately, so the button cannot promise one
 * address and open another. An address with no path gets no handle.
 */
function shtob_social_handle($key, $url) {
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    if ($path === '') return '';
    return $key === 'telegram' ? '@' . $path : 'vk.com/' . $path;
}

/** Контакты' buttons: the two social channels only — the guide is not one. */
function shtob_social_buttons() {
    $buttons = array_intersect_key(shtob_socials(), ['telegram' => 1, 'vk' => 1]);
    foreach ($buttons as $key => $l) {
        $handle = shtob_social_handle($key, $l['url']);
        printf('<a class="social-btn" href="%s" target="_blank" rel="noopener">'
            . '<span class="social-btn-icon">%s</span>'
            . '<span class="social-btn-text"><span class="social-btn-name">%s</span>%s</span>'
            . '<svg class="social-btn-go" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7"></path><path d="M8 7h9v9"></path></svg>'
            . '</a>' . "\n",
            esc_url($l['url']), shtob_social_svg($l, 22), esc_html($l['name']),
            $handle !== '' ? '<span class="social-btn-handle">' . esc_html($handle) . '</span>' : '');
    }
    return count($buttons);
}

/**
 * The opening hours as they are set on Контакты — for the footer, which shows
 * them on every page. Read from the same textarea the hours panel and the
 * structured data use, so there is still exactly one place they are typed.
 */
function shtob_hours_rows() {
    $kontakty = get_page_by_path('kontakty');
    return $kontakty ? shtob_parse_hours(get_post_meta($kontakty->ID, '_shtob_hours', true)) : [];
}
