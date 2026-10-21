<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'hsindato@yahoo.com')->first();
auth()->login($user);

echo 'visible=', tenant_system_guide_visible() ? 'yes' : 'no', PHP_EOL;
echo 'def=', json_encode(config('page_manuals.routes.items.index')), PHP_EOL;
echo 'copy=', json_encode(__('page_manual.screens.items')), PHP_EOL;
$manual = page_manual_for_route('items.index');
echo json_encode($manual, JSON_PRETTY_PRINT), PHP_EOL;
