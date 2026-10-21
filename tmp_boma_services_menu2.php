<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Business::with('plan')->find(15);
$svc = app(App\Services\PlanFeatureService::class);

echo 'operation_mode='.$b->operationMode().PHP_EOL;
echo 'isServicesOperationEnabled='.var_export($b->isServicesOperationEnabled(), true).PHP_EOL;
echo 'hasPlanFeature(services)='.var_export($b->hasPlanFeature('services'), true).PHP_EOL;
echo 'servicesMenuVisible='.var_export($b->servicesMenuVisible(), true).PHP_EOL;

$plan = $b->plan;
echo 'plan_id='.$plan->id.' name='.$plan->name.PHP_EOL;
echo 'enabled_features='.json_encode($plan->enabled_features).PHP_EOL;
echo 'features='.json_encode($plan->features).PHP_EOL;

// compare a business that likely has services
$with = App\Models\Business::query()
    ->where('operation_mode', 'both')
    ->orWhere('operation_mode', 'services')
    ->limit(5)
    ->get(['id','name','operation_mode','plan_id']);
foreach ($with as $x) {
    echo "biz {$x->id} {$x->name} mode={$x->operation_mode} servicesVis=".var_export($x->servicesMenuVisible(), true).PHP_EOL;
}
