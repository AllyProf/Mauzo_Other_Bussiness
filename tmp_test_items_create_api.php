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
    'device_name' => 'items-create-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

echo "1) GET /items/create-form\n";
$r = api(Illuminate\Http\Request::create('/api/v1/items/create-form', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode()
    .' categories='.count($j['data']['categories'] ?? [])
    .' packagings='.count($j['data']['packagings'] ?? [])
    .' can_add='.json_encode($j['data']['plan']['can_add'] ?? null)."\n";

if (! ($j['success'] ?? false)) {
    echo $r->getContent()."\n";
    exit(1);
}

$categoryId = $j['data']['categories'][0]['id'] ?? null;
$typeKey = $j['data']['default_business_type_key']
    ?? ($j['data']['categories'][0]['business_type_key'] ?? null);

$packagings = collect($j['data']['packagings'] ?? []);
if ($typeKey) {
    $typed = $packagings->where('business_type_key', $typeKey)->values();
    if ($typed->isNotEmpty()) {
        $packagings = $typed;
    }
}
$recv = $packagings->firstWhere('name', 'Carton') ?? $packagings->first();
$piece = $packagings->first(fn ($p) => stripos($p['name'], 'Piece') !== false || stripos($p['name'], 'Pcs') !== false)
    ?? $packagings->skip(1)->first()
    ?? $recv;

echo "2) GET /items/check-name\n";
$cn = api(Illuminate\Http\Request::create('/api/v1/items/check-name?name=Afya', 'GET', [], [], [], $auth));
$cnj = json_decode($cn->getContent(), true);
echo '   status='.$cn->getStatusCode().' matches='.count($cnj['data']['matches'] ?? [])."\n";

$name = 'API Create Item '.time();
echo "3) POST /items ($name)\n";
$body = [
    'name' => $name,
    'category_id' => $categoryId,
    'receiving_packaging_id' => $recv['id'],
    'units_per_receiving_pack' => 12,
    'cost_price' => 1000,
    'selling_packagings' => array_values(array_filter([
        [
            'packaging_id' => $piece['id'],
            'quantity_per_unit' => 1,
            'selling_price' => 500,
        ],
        $piece['id'] !== $recv['id'] ? [
            'packaging_id' => $recv['id'],
            'quantity_per_unit' => 12,
            'selling_price' => 5000,
        ] : null,
    ])),
];
if (($j['data']['multi_business'] ?? false) && $typeKey) {
    $body['business_type_key'] = $typeKey;
}

$create = api(Illuminate\Http\Request::create('/api/v1/items', 'POST', [], [], [], array_merge($auth, [
    'CONTENT_TYPE' => 'application/json',
]), json_encode($body)));
$cj = json_decode($create->getContent(), true);
echo '   status='.$create->getStatusCode().' id='.($cj['data']['item']['id'] ?? 'n/a').' sku='.($cj['data']['item']['sku'] ?? '')."\n";
if (! ($cj['success'] ?? false)) {
    echo '   '.$create->getContent()."\n";
}

$newId = $cj['data']['item']['id'] ?? null;

echo "4) GET /items?q=API Create\n";
$list = api(Illuminate\Http\Request::create('/api/v1/items?q='.urlencode('API Create'), 'GET', [], [], [], $auth));
$lj = json_decode($list->getContent(), true);
echo '   status='.$list->getStatusCode().' count='.count($lj['data']['items'] ?? [])."\n";

if ($newId) {
    echo "5) PUT update\n";
    $body['name'] = $name.' Renamed';
    $upd = api(Illuminate\Http\Request::create('/api/v1/items/'.$newId, 'PUT', [], [], [], array_merge($auth, [
        'CONTENT_TYPE' => 'application/json',
    ]), json_encode($body)));
    echo '   status='.$upd->getStatusCode()."\n";

    echo "6) DELETE\n";
    $del = api(Illuminate\Http\Request::create('/api/v1/items/'.$newId, 'DELETE', [], [], [], $auth));
    echo '   status='.$del->getStatusCode().' '.substr($del->getContent(), 0, 120)."\n";
}

echo "Done.\n";
