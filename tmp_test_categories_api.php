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
    'device_name' => 'categories-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

echo "1) GET /categories\n";
$r = api(Illuminate\Http\Request::create('/api/v1/categories', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode().' count='.count($j['data']['categories'] ?? []).' templates='.count($j['data']['meta']['templates'] ?? [])."\n";
if (! ($j['success'] ?? false)) {
    echo '   '.$r->getContent()."\n";
    exit(1);
}

$first = $j['data']['categories'][0] ?? null;
$branchId = $j['data']['meta']['writable_branches'][0]['id'] ?? ($first['branch']['id'] ?? null);
$importedKey = $j['data']['meta']['imported_types'][0]['key'] ?? null;

echo "2) POST create category\n";
if ($importedKey && $branchId) {
    $create = api(Illuminate\Http\Request::create('/api/v1/categories', 'POST', [], [], [], array_merge($auth, [
        'CONTENT_TYPE' => 'application/json',
    ]), json_encode([
        'name' => 'API Test Category '.time(),
        'source_business_type_key' => $importedKey,
        'branch_id' => $branchId,
    ])));
    $cj = json_decode($create->getContent(), true);
    echo '   status='.$create->getStatusCode().' id='.($cj['data']['category']['id'] ?? 'n/a')."\n";
    $newId = $cj['data']['category']['id'] ?? null;

    if ($newId) {
        echo "3) PUT rename\n";
        $upd = api(Illuminate\Http\Request::create('/api/v1/categories/'.$newId, 'PUT', [], [], [], array_merge($auth, [
            'CONTENT_TYPE' => 'application/json',
        ]), json_encode(['name' => 'API Test Category Renamed'])));
        echo '   status='.$upd->getStatusCode()."\n";

        echo "4) DELETE category\n";
        $del = api(Illuminate\Http\Request::create('/api/v1/categories/'.$newId, 'DELETE', [], [], [], $auth));
        echo '   status='.$del->getStatusCode().' '.substr($del->getContent(), 0, 120)."\n";
    }
} else {
    echo "   skipped (no imported type/branch)\n";
}

echo "Done.\n";
