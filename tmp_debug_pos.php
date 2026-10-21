<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$b = App\Models\Business::find(15);
echo 'biz name='.$b->name."\n";
echo 'is_active='.var_export($b->is_active, true)."\n";
echo 'owner_user_id='.var_export($b->owner_user_id, true)."\n";
echo 'pending_approval='.var_export($b->pending_approval ?? null, true)."\n";

$u = App\Models\User::find(35);
auth()->login($u);
$svc = app(App\Services\ActiveBusinessService::class);
echo 'activeBusinessId='.var_export($svc->activeBusinessId(), true)."\n";
echo 'activeBusiness='.($svc->activeBusiness()?->name ?? 'NULL')."\n";
echo 'businesses count='.$svc->businesses()->count()."\n";
echo 'user.business='.($u->business?->name ?? 'NULL')."\n";
echo 'current_business_id='.current_business_id()."\n";
