<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$login = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode(['email' => 'sindato@gmail.com', 'password' => '123456', 'device_name' => 'test']));
$kernel->handle($login);
$token = json_decode($login->getContent() ?? '', true);
// get token from response - need to capture response
$lr = $kernel->handle(Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode(['email' => 'sindato@gmail.com', 'password' => '123456', 'device_name' => 'test2'])));
$token = json_decode($lr->getContent(), true)['data']['token'];

$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json'];

foreach (['', 'Afya', 'Absolute'] as $q) {
    $r = $kernel->handle(Illuminate\Http\Request::create('/api/v1/items/search?q='.urlencode($q).'&limit=30', 'GET', [], [], [], $auth));
    $j = json_decode($r->getContent(), true);
    $count = count($j['data']['items'] ?? []);
    $first = $j['data']['items'][0]['name'] ?? '-';
    echo "q=".($q === '' ? '(empty)' : $q)." => $count items, first=$first\n";
}
