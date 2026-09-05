<?php
// TEMPORARY probe — confirms this host can actually run Grav before we upload
// 30 MB of it. Deliberately prints only the version and a yes/no per extension,
// never phpinfo(), which would dump paths, modules and config to anyone who
// found the URL. Random suffix in the filename so it is not guessable, and it
// is deleted in the very next commit.
header('Content-Type: text/plain; charset=utf-8');
echo "php " . PHP_VERSION . "\n";
echo "meets grav 2.0 minimum (8.3.11): " . (version_compare(PHP_VERSION, '8.3.11', '>=') ? 'yes' : 'NO') . "\n\n";
$required = ['curl','ctype','dom','gd','json','libxml','mbstring','openssl','session','simplexml','zip'];
$missing = [];
foreach ($required as $e) {
    $ok = extension_loaded($e);
    if (!$ok) { $missing[] = $e; }
    echo str_pad($e, 12) . ($ok ? 'ok' : 'MISSING') . "\n";
}
echo "\nmissing required: " . ($missing ? implode(', ', $missing) : 'none') . "\n";
$opt = ['fileinfo','intl','exif','apcu','opcache','yaml'];
echo "optional present: ";
echo implode(', ', array_filter($opt, 'extension_loaded')) ?: '(none)';
echo "\n\nwritable docroot: " . (is_writable(__DIR__) ? 'yes' : 'no') . "\n";
