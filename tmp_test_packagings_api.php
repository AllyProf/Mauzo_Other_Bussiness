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
    'device_name' => 'packagings-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

echo "1) GET /packagings\n";
$r = api(Illuminate\Http\Request::create('/api/v1/packagings', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode().' count='.count($j['data']['packagings'] ?? []).' templates='.count($j['data']['meta']['templates'] ?? [])."\n";
if (! ($j['success'] ?? false)) {
    echo '   '.$r->getContent()."\n";
    exit(1);
}

$typeKey = $j['data']['meta']['imported_types'][0]['key']
    ?? $j['data']['meta']['branch_type_keys'][0]
    ?? null;

echo "2) POST create\n";
$create = api(Illuminate\Http\Request::create('/api/v1/packagings', 'POST', [], [], [], array_merge($auth, [
    'CONTENT_TYPE' => 'application/json',
]), json_encode([
    'name' => 'API Test Unit '.time(),
    'source_business_type_key' => $typeKey,
])));
$cj = json_decode($create->getContent(), true);
echo '   status='.$create->getStatusCode().' id='.($cj['data']['packaging']['id'] ?? 'n/a')."\n";
$newId = $cj['data']['packaging']['id'] ?? null;

if ($newId) {
    echo "3) PUT rename\n";
    $upd = api(Illuminate\Http\Request::create('/api/v1/packagings/'.$newId, 'PUT', [], [], [], array_merge($auth, [
        'CONTENT_TYPE' => 'application/json',
    ]), json_encode(['name' => 'API Test Unit Renamed'])));
    echo '   status='.$upd->getStatusCode()."\n";

    echo "4) DELETE\n";
    $del = api(Illuminate\Http\Request::create('/api/v1/packagings/'.$newId, 'DELETE', [], [], [], $auth));
    echo '   status='.$del->getStatusCode().' '.substr($del->getContent(), 0, 120)."\n";
}

if ($typeKey) {
    echo "5) POST import-templates (idempotent)\n";
    $imp = api(Illuminate\Http\Request::create('/api/v1/packagings/import-templates', 'POST', [], [], [], array_merge($auth, [
        'CONTENT_TYPE' => 'application/json',
    ]), json_encode(['business_type_key' => $typeKey])));
    $ij = json_decode($imp->getContent(), true);
    echo '   status='.$imp->getStatusCode().' created='.($ij['data']['created'] ?? '?').' skipped='.($ij['data']['skipped'] ?? '?')."\n";
}

echo "Done.\n";
