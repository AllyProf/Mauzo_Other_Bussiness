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
    'device_name' => 'customers-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

echo "1) GET /customers/create-form\n";
$r = api(Illuminate\Http\Request::create('/api/v1/customers/create-form', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode().' regions='.count($j['data']['regions'] ?? [])."\n";

$suffix = substr((string) time(), -6);
$phone = '71'.str_pad($suffix, 7, '0', STR_PAD_LEFT);
if (strlen($phone) > 9) {
    $phone = substr($phone, 0, 9);
}

echo "2) POST /customers phone={$phone}\n";
$create = api(Illuminate\Http\Request::create('/api/v1/customers', 'POST', [], [], [], array_merge($auth, [
    'CONTENT_TYPE' => 'application/json',
]), json_encode([
    'name' => 'API Test Customer '.$suffix,
    'phone' => $phone,
    'email' => 'api.customer'.$suffix.'@example.com',
    'address' => 'Test Street',
    'region' => 'Arusha',
    'notes' => 'Created by smoke test',
    'is_active' => true,
])));
$cj = json_decode($create->getContent(), true);
echo '   status='.$create->getStatusCode().' id='.($cj['data']['customer']['id'] ?? 'n/a').' phone='.($cj['data']['customer']['phone'] ?? '')."\n";
if ($create->getStatusCode() !== 201) {
    echo $create->getContent()."\n";
    exit(1);
}
$id = $cj['data']['customer']['id'];

echo "3) GET /customers\n";
$list = api(Illuminate\Http\Request::create('/api/v1/customers', 'GET', [], [], [], $auth));
$lj = json_decode($list->getContent(), true);
echo '   status='.$list->getStatusCode().' total='.($lj['data']['stats']['total'] ?? 0)."\n";

echo "4) GET /customers/{$id}\n";
$show = api(Illuminate\Http\Request::create("/api/v1/customers/{$id}", 'GET', [], [], [], $auth));
echo '   status='.$show->getStatusCode()."\n";

echo "5) PUT /customers/{$id}\n";
$upd = api(Illuminate\Http\Request::create("/api/v1/customers/{$id}", 'PUT', [], [], [], array_merge($auth, [
    'CONTENT_TYPE' => 'application/json',
]), json_encode([
    'name' => 'API Test Customer Updated '.$suffix,
    'phone' => $phone,
    'region' => 'Dar es Salaam',
    'is_active' => true,
])));
echo '   status='.$upd->getStatusCode()."\n";

echo "6) DELETE /customers/{$id}\n";
$del = api(Illuminate\Http\Request::create("/api/v1/customers/{$id}", 'DELETE', [], [], [], $auth));
echo '   status='.$del->getStatusCode().' msg='.(json_decode($del->getContent(), true)['message'] ?? '')."\n";

echo "OK\n";
