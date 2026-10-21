<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$r = $kernel->handle(Illuminate\Http\Request::create('/api/v1/register-business', 'GET', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
]));
echo $r->getStatusCode()."\n".$r->getContent()."\n";
