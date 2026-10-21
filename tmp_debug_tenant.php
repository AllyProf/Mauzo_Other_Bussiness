<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::find(24);
$ctx = app(App\Services\Api\ApiTenantContext::class);
$ctx->setUser($user);

echo "role={$user->role} business_id={$user->business_id}\n";
echo "ownerBusinesses: ".$ctx->ownerBusinesses()->pluck('id', 'name')->toJson()."\n";
echo "get(): ".json_encode($ctx->get())."\n";
echo "businessId: ".$ctx->businessId()."\n";

// Check Business owner_user_id
$b = App\Models\Business::find(10);
echo "Business 10 owner_user_id={$b->owner_user_id} is_active={$b->is_active}\n";
