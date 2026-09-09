<?php
/**
 * Tests af_activity_device() — the Audit page's Device column.
 *
 * Runs the REAL function, lifted out of functions.php by name rather than
 * copied, so this cannot drift away from what ships. Plain `php` runs it; no
 * WordPress needed, because the classifier is pure.
 *
 *     php tools/test-device-detect.php
 */
$src = file_get_contents(__DIR__ . '/../functions.php');
foreach (array('af_activity_device', 'af_activity_device_label') as $fn) {
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}\n/s', $src, $m)) {
        fwrite(STDERR, "could not find {$fn}() in functions.php\n");
        exit(2);
    }
    eval($m[0]);
}

$pass = 0; $fail = 0;
function is_device($ua, $want, $note = '') {
    global $pass, $fail;
    $got = af_activity_device($ua);
    if ($got === $want) { $pass++; printf("  OK    %-9s %s\n", $want === '' ? 'unknown' : $want, $note); }
    else {
        $fail++;
        printf("  FAIL  want %-8s got %-8s %s\n        %s\n",
               $want === '' ? 'unknown' : $want, $got === '' ? 'unknown' : $got, $note, substr($ua, 0, 100));
    }
}

echo "=== phones ===\n";
is_device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'mobile', 'iPhone Safari');
is_device('Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'mobile', 'Android Chrome (Galaxy)');
is_device('Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Mobile Safari/537.36', 'mobile', 'Pixel');
is_device('Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/119.0 Mobile/15E148 Safari/605.1.15', 'mobile', 'Firefox iOS');
is_device('Opera/9.80 (Android; Opera Mini/7.5) Presto/2.8 Version/11.10', 'mobile', 'Opera Mini');
is_device('Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1) Mobile Safari/537.36 Edge/15', 'mobile', 'Windows Phone');

echo "\n=== tablets ===\n";
is_device('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'tablet', 'iPad — before the phone test, or it reads as mobile');
is_device('Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36', 'tablet', 'Android tablet — Android WITHOUT "Mobile"');
is_device('Mozilla/5.0 (Linux; Android 11; KFTRWI) AppleWebKit/537.36 (KHTML, like Gecko) Silk/119.1 like Chrome/119 Safari/537.36', 'tablet', 'Kindle Fire / Silk');

echo "\n=== computers ===\n";
is_device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Windows Chrome');
is_device('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', 'desktop', 'Mac Safari');
is_device('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36', 'desktop', 'Linux');
is_device('Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36', 'desktop', 'ChromeOS');

echo "\n=== crawlers and tools ===\n";
is_device('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'bot', 'Googlebot');
is_device('Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'bot', 'Googlebot smartphone — says Android AND Mobile, must still be a bot');
is_device('Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 'bot', 'bingbot');
is_device('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'bot', 'Facebook link preview');
is_device('curl/8.4.0', 'bot', 'curl');
is_device('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0 Safari/537.36', 'bot', 'HeadlessChrome — our own deploy checks');

echo "\n=== nothing recorded, or unrecognised ===\n";
is_device('', '', 'empty — every entry written before the ua column existed');
is_device('   ', '', 'whitespace only');
is_device('SomeBrandNewThing/1.0', '', 'unrecognised — says unknown rather than guessing desktop');

echo "\n=== labels ===\n";
foreach (array('mobile' => 'Phone', 'tablet' => 'Tablet', 'desktop' => 'Computer',
               'bot' => 'Bot', '' => 'Unknown') as $k => $want) {
    $got = af_activity_device_label($k);
    if ($got === $want) { $pass++; printf("  OK    %-8s -> %s\n", $k === '' ? "''" : $k, $got); }
    else { $fail++; printf("  FAIL  %-8s -> %s (want %s)\n", $k, $got, $want); }
}

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
