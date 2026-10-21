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
    'device_name' => 'owner-reports-test',
])));

$loginJson = json_decode($login->getContent(), true);
if (! ($loginJson['success'] ?? false)) {
    echo "Login failed: ".$login->getContent()."\n";
    exit(1);
}

$token = $loginJson['data']['token'];
$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];
$role = $loginJson['data']['user']['role'] ?? '?';

echo "Logged in as role={$role}\n";

echo "1) GET /owner-reports?per_page=5\n";
$r = api(Illuminate\Http\Request::create('/api/v1/owner-reports?per_page=5', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode()
    .' ledgers='.count($j['data']['ledgers'] ?? [])
    .' pending='.count($j['data']['pending_handovers'] ?? [])
    .' total='.($j['data']['meta']['total'] ?? 0)."\n";
if ($r->getStatusCode() >= 400) {
    echo '   body='.substr($r->getContent(), 0, 400)."\n";
    exit(1);
}

$sampleDate = $j['data']['ledgers'][0]['ledger_date'] ?? '2026-06-18';

echo "2) GET /owner-reports/{$sampleDate}\n";
$r = api(Illuminate\Http\Request::create("/api/v1/owner-reports/{$sampleDate}", 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode()
    .' date='.($j['data']['date'] ?? 'n/a')
    .' ledgers='.count($j['data']['ledgers'] ?? [])."\n";
if ($r->getStatusCode() >= 400) {
    echo '   body='.substr($r->getContent(), 0, 400)."\n";
    exit(1);
}

$first = $j['data']['ledgers'][0] ?? null;
if ($first) {
    echo '   first_status='.($first['business_status'] ?? 'n/a')
        .' review='.($first['review_api_path'] ?? 'n/a')."\n";
}

echo "OK\n";
