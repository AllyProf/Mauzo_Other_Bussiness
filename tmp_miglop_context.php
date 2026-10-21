<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$bid = 28;
echo "Categories:\n";
foreach (App\Models\Category::where('business_id', $bid)->orderBy('name')->get() as $c) {
    echo "  {$c->id} | {$c->name} | type={$c->source_business_type_key} branch={$c->branch_id}\n";
}
echo "Packagings:\n";
foreach (App\Models\Packaging::where('business_id', $bid)->orderBy('name')->get() as $p) {
    echo "  {$p->id} | {$p->name} | type={$p->source_business_type_key}\n";
}
$b = App\Models\Business::find($bid);
echo "Business types: ".json_encode($b->posBusinessTypesMeta())."\n";
