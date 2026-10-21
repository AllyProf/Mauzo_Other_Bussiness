<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Business::with('plan')->find(15);
echo 'name='.$b->name.PHP_EOL;
echo 'business_type='.($b->business_type ?? 'null').PHP_EOL;
foreach (['business_mode', 'operation_mode', 'services_enabled', 'enable_services', 'sales_mode'] as $c) {
    if (\Illuminate\Support\Facades\Schema::hasColumn('businesses', $c)) {
        echo $c.'='.var_export($b->{$c}, true).PHP_EOL;
    }
}
echo 'plan='.($b->plan->name ?? '?').PHP_EOL;
$features = $b->plan->features ?? null;
if (is_string($features)) {
    $features = json_decode($features, true);
}
echo 'plan_features='.json_encode($features).PHP_EOL;
echo 'has services feature key: ';
if (is_array($features)) {
    echo array_key_exists('services', $features) ? var_export($features['services'], true) : 'missing';
}
echo PHP_EOL;
