<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode(['email' => 'sindato@gmail.com', 'password' => '123456', 'device_name' => 'test']));
$r = $kernel->handle($req);
echo $r->getStatusCode()."\n";
$j = json_decode($r->getContent(), true);
echo ($j['success'] ?? false) ? 'Login OK: '.$j['data']['user']['name']."\n" : $r->getContent();
