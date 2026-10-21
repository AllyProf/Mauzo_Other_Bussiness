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
    'device_name' => 'day-closing-test',
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

echo "1) GET /day-closing/pending\n";
$r = api(Illuminate\Http\Request::create('/api/v1/day-closing/pending', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode().' pending='.count($j['data']['pending'] ?? [])."\n";

echo "2) GET /day-closing\n";
$r = api(Illuminate\Http\Request::create('/api/v1/day-closing?per_page=5', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode().' handovers='.count($j['data']['handovers'] ?? []).' total='.($j['data']['meta']['total'] ?? 0)."\n";

echo "3) GET /day-closing/preview\n";
$r = api(Illuminate\Http\Request::create('/api/v1/day-closing/preview', 'GET', [], [], [], $auth));
$j = json_decode($r->getContent(), true);
echo '   status='.$r->getStatusCode().' can_submit='.var_export($j['data']['can_submit'] ?? null, true)
    .' expected='.($j['data']['expected_handover'] ?? 'n/a')."\n";
if ($r->getStatusCode() >= 400) {
    echo '   body='.substr($r->getContent(), 0, 300)."\n";
}

$list = api(Illuminate\Http\Request::create('/api/v1/day-closing?per_page=1', 'GET', [], [], [], $auth));
$listJson = json_decode($list->getContent(), true);
$id = $listJson['data']['handovers'][0]['id'] ?? null;

if ($id) {
    echo "4) GET /day-closing/{$id}\n";
    $r = api(Illuminate\Http\Request::create("/api/v1/day-closing/{$id}", 'GET', [], [], [], $auth));
    $dj = json_decode($r->getContent(), true);
    echo '   status='.$r->getStatusCode().' handover_status='.($dj['data']['handover']['status'] ?? 'n/a')."\n";
} else {
    echo "4) No handover to show\n";
}

echo "OK\n";
