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
    'device_name' => 'suppliers-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

echo "1) GET /suppliers/create-form\n";
$r = api(Illuminate\Http\Request::create('/api/v1/suppliers/create-form', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode().' branches='.count($j['data']['branches'] ?? []).' regions='.count($j['data']['regions'] ?? [])."\n";
$branchId = $j['data']['default_branch_id'] ?? ($j['data']['branches'][0]['id'] ?? null);

echo "2) POST /suppliers\n";
$create = api(Illuminate\Http\Request::create('/api/v1/suppliers', 'POST', [], [], [], array_merge($auth, [
    'CONTENT_TYPE' => 'application/json',
]), json_encode([
    'name' => 'API Test Supplier '.time(),
    'phone' => '712490001',
    'email' => 'api.supplier@example.com',
    'region' => 'Arusha',
    'branch_id' => $branchId,
])));
$cj = json_decode($create->getContent(), true);
echo '   status='.$create->getStatusCode().' id='.($cj['data']['supplier']['id'] ?? 'n/a').' phone='.($cj['data']['supplier']['phone'] ?? '')."\n";
$newId = $cj['data']['supplier']['id'] ?? null;

echo "3) GET /suppliers\n";
$list = api(Illuminate\Http\Request::create('/api/v1/suppliers?q=API Test', 'GET', [], [], [], $auth));
$lj = json_decode($list->getContent(), true);
echo '   status='.$list->getStatusCode().' count='.count($lj['data']['suppliers'] ?? [])."\n";

if ($newId) {
    echo "4) PUT update\n";
    $upd = api(Illuminate\Http\Request::create('/api/v1/suppliers/'.$newId, 'PUT', [], [], [], array_merge($auth, [
        'CONTENT_TYPE' => 'application/json',
    ]), json_encode([
        'name' => 'API Test Supplier Renamed',
        'phone' => '712490001',
        'region' => 'Arusha',
        'branch_id' => $branchId,
    ])));
    echo '   status='.$upd->getStatusCode()."\n";

    echo "5) DELETE\n";
    $del = api(Illuminate\Http\Request::create('/api/v1/suppliers/'.$newId, 'DELETE', [], [], [], $auth));
    echo '   status='.$del->getStatusCode().' '.substr($del->getContent(), 0, 120)."\n";
}

echo "Done.\n";
