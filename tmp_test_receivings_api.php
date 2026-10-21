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
    'device_name' => 'receivings-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

echo "1) GET /receivings/create-form\n";
$r = api(Illuminate\Http\Request::create('/api/v1/receivings/create-form', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode()
    .' suppliers='.count($j['data']['suppliers'] ?? [])
    .' categories='.count($j['data']['categories'] ?? [])
    ."\n";

$supplierId = $j['data']['suppliers'][0]['id'] ?? null;
$itemId = null;
$itemPackagingId = null;
foreach ($j['data']['items_by_category'] ?? [] as $catItems) {
    foreach ($catItems as $item) {
        if (($item['current_stock'] ?? 0) >= 0) {
            $itemId = $item['id'];
            $itemPackagingId = $item['packagings'][0]['id'] ?? null;
            $cost = $item['cost_price'] ?? 1000;
            $sell = max($cost, $item['selling_price'] ?? $cost);
            break 2;
        }
    }
}

if (! $supplierId || ! $itemId) {
    echo "   No supplier/item found for create test\n";
    exit(1);
}

echo "2) POST /receivings (item={$itemId}, supplier={$supplierId})\n";
$body = [
    'supplier_id' => $supplierId,
    'received_date' => date('Y-m-d'),
    'items' => [
        [
            'id' => $itemId,
            'qty' => 1,
            'cost' => $cost,
            'selling' => $sell,
        ],
    ],
];
if ($itemPackagingId) {
    $body['items'][0]['selling_prices'] = [(string) $itemPackagingId => $sell];
}

$create = api(Illuminate\Http\Request::create('/api/v1/receivings', 'POST', [], [], [], array_merge($auth, [
    'CONTENT_TYPE' => 'application/json',
]), json_encode($body)));
$cj = json_decode($create->getContent(), true);
echo '   status='.$create->getStatusCode()
    .' ref='.($cj['data']['receiving']['receiving']['reference_no'] ?? 'n/a')
    .' msg='.($cj['message'] ?? $cj['message'] ?? '')
    ."\n";

if ($create->getStatusCode() !== 201) {
    echo $create->getContent()."\n";
    exit(1);
}

$recvId = $cj['data']['receiving']['receiving']['id'] ?? null;

echo "3) GET /receivings/{$recvId}\n";
$show = api(Illuminate\Http\Request::create("/api/v1/receivings/{$recvId}", 'GET', [], [], [], $auth));
$sj = json_decode($show->getContent(), true);
echo '   status='.$show->getStatusCode().' lines='.count($sj['data']['lines'] ?? [])."\n";

echo "4) GET /receivings\n";
$list = api(Illuminate\Http\Request::create('/api/v1/receivings?period=today', 'GET', [], [], [], $auth));
$lj = json_decode($list->getContent(), true);
echo '   status='.$list->getStatusCode().' total='.($lj['data']['meta']['total'] ?? 0)."\n";

echo "OK\n";
