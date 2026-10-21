<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Business::find(15);
echo 'feature_overrides='.json_encode($b->feature_overrides).PHP_EOL;

// which plans have services?
foreach (App\Models\Plan::all(['id','name','enabled_features']) as $p) {
    $has = $p->hasFeature('services') ? 'YES' : 'no';
    echo "plan {$p->id} {$p->name}: services={$has}\n";
}
