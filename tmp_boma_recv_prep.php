<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Business::find(15);
echo 'Business: '.$b->name.PHP_EOL;
echo 'Owners/users:'.PHP_EOL;
foreach (App\Models\User::where('business_id', 15)->get(['id','name','role']) as $u) {
    echo "  {$u->id} {$u->name} {$u->role}\n";
}
echo 'Suppliers:'.PHP_EOL;
foreach (App\Models\Supplier::where('business_id', 15)->get(['id','name']) as $s) {
    echo "  {$s->id} {$s->name}\n";
}
echo 'Items: '.App\Models\Item::where('business_id', 15)->count().PHP_EOL;
echo 'Stock >0: '.App\Models\Item::where('business_id', 15)->where('current_stock', '>', 0)->count().PHP_EOL;
