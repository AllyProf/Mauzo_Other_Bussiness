<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

function api(Illuminate\Http\Request $request)
{
    global $kernel;
    $request->headers->set('Accept', 'application/json');

    return $kernel->handle($request);
}

$login = api(Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
], json_encode([
    'email' => 'sindato@gmail.com',
    'password' => '123456',
    'device_name' => 'stock-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

echo "1) GET /items/stock\n";
$r = api(Illuminate\Http\Request::create('/api/v1/items/stock', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode()
    .' items='.count($j['data']['items'] ?? [])
    .' low_stock='.($j['data']['stats']['low_stock'] ?? 0)
    .' can_view_value='.(($j['data']['meta']['can_view_value'] ?? false) ? 'yes' : 'no')
    ."\n";

$itemId = $j['data']['items'][0]['id'] ?? null;
if (! $itemId) {
    echo "   No stock items to test history\n";
    exit(0);
}

echo "2) GET /items/stock?q=".urlencode(substr($j['data']['items'][0]['name'], 0, 4))."\n";
$r2 = api(Illuminate\Http\Request::create('/api/v1/items/stock?q='.urlencode(substr($j['data']['items'][0]['name'], 0, 4)), 'GET', [], [], [], $auth));
$j2 = json_decode($r2->getContent(), true);
echo '   status='.$r2->getStatusCode().' filtered='.count($j2['data']['items'] ?? [])."\n";

echo "3) GET /items/{$itemId}/history\n";
$h = api(Illuminate\Http\Request::create("/api/v1/items/{$itemId}/history", 'GET', [], [], [], $auth));
$hj = json_decode($h->getContent(), true);
echo '   status='.$h->getStatusCode()
    .' movements='.count($hj['data']['movements'] ?? [])
    .' current_stock='.($hj['data']['stats']['current_stock'] ?? 'n/a')
    ."\n";

echo "OK\n";
