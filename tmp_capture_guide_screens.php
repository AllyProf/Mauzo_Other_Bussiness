<?php
/**
 * Login via HTTP and capture full-window guide screenshots with Chrome headless + cookies.
 * Usage: php tmp_capture_guide_screens.php [password]
 */
require __DIR__.'/vendor/autoload.php';

$base = rtrim(getenv('APP_URL') ?: 'http://127.0.0.1/SpareParts/public', '/');
$email = 'hsindato@yahoo.com';
$password = $argv[1] ?? '12345678';

$pages = [
    'business_types' => '/categories',
    'packaging' => '/packagings',
    'roles' => '/roles',
    'employees' => '/employees',
    'settings' => '/settings',
    'suppliers' => '/suppliers',
    'items' => '/items',
    'receiving' => '/receivings',
    'shifts' => '/shifts',
    'sales' => '/sales/create',
    'reports' => '/reports',
];

$outDir = __DIR__.'/public/gp-assets/img/guide/pages';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$cookieFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sp_guide_cookies.txt';
@unlink($cookieFile);

function guide_http(string $url, string $cookieFile, ?string $post = null, array $extraHeaders = []): array
{
    $ch = curl_init($url);
    $headers = array_merge(['User-Agent: SparePartsGuideCapture/1.0'], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HEADER => true,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = substr((string) $raw, $headerSize);

    return [$code, $body];
}

[$code, $html] = guide_http($base.'/login', $cookieFile);
if ($code !== 200 || ! preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
    fwrite(STDERR, "Failed to load login (HTTP $code)\n");
    exit(1);
}

$post = http_build_query([
    '_token' => $m[1],
    'email' => $email,
    'password' => $password,
    'remember' => '1',
]);
[$code, $afterLogin] = guide_http($base.'/login', $cookieFile, $post, [
    'Content-Type: application/x-www-form-urlencoded',
    'Referer: '.$base.'/login',
]);
if ($code >= 400) {
    fwrite(STDERR, "Login POST failed HTTP $code\n");
    exit(1);
}

// Verify session
[$code, $home] = guide_http($base.'/home', $cookieFile);
if ($code !== 200 || str_contains($home, 'SIGN IN')) {
    fwrite(STDERR, "Session not established after login\n");
    exit(1);
}
echo "Logged in as $email\n";

$chrome = null;
foreach ([
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
] as $c) {
    if (is_file($c)) {
        $chrome = $c;
        break;
    }
}
if (! $chrome) {
    fwrite(STDERR, "Chrome not found.\n");
    exit(2);
}

$userDataDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sp_guide_chrome_'.getmypid();
@mkdir($userDataDir, 0755, true);

// Export cookies for Chrome — inject via CDP is hard; use a small HTML wrapper that sets document.cookie won't work for httpOnly.

// Pass Laravel session via Netscape cookies file
$netscape = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sp_guide_netscape.txt';
$lines = ["# Netscape HTTP Cookie File"];
foreach (file($cookieFile) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    $parts = explode("\t", $line);
    if (count($parts) >= 7) {
        $lines[] = implode("\t", $parts);
    }
}
file_put_contents($netscape, implode("\n", $lines)."\n");

foreach ($pages as $key => $path) {
    $url = $base.$path;
    $out = $outDir.DIRECTORY_SEPARATOR.$key.'.png';
    $cmd = sprintf(
        '"%s" --headless=new --disable-gpu --hide-scrollbars --window-size=1440,900 --virtual-time-budget=12000 --cookies=%s --screenshot=%s %s',
        $chrome,
        escapeshellarg($netscape),
        escapeshellarg($out),
        escapeshellarg($url)
    );
    exec($cmd, $output, $exit);
    $ok = $exit === 0 && is_file($out) && filesize($out) > 5000;
    echo ($ok ? 'OK' : 'FAIL')." $key ($url)\n";
}

echo "Done. Images in public/gp-assets/img/guide/pages/\n";
