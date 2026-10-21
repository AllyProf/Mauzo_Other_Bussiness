<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'sindato@gmail.com')->first();
echo "User #{$user->id} role={$user->role} business_id={$user->business_id} branch_id={$user->branch_id}\n";
echo "business_type_key: ".json_encode($user->business_type_key)."\n";
echo "business_type_keys: ".json_encode($user->business_type_keys)."\n";
echo "assignedBusinessTypeKeys: ".json_encode($user->assignedBusinessTypeKeys())."\n";
echo "seesBusinessWideData: ".($user->seesBusinessWideData() ? 'yes' : 'no')."\n\n";

$businessId = 10;
$total = App\Models\Item::where('business_id', $businessId)->count();
$withStock = App\Models\Item::where('business_id', $businessId)->where('current_stock', '>', 0)->count();
$withCategory = App\Models\Item::where('business_id', $businessId)->where('current_stock', '>', 0)->whereNotNull('category_id')->count();
echo "Items business 10: total=$total with_stock=$withStock with_category=$withCategory\n\n";

$sample = App\Models\Item::where('business_id', $businessId)->where('current_stock', '>', 0)
    ->where('name', 'like', '%Afya%')
    ->with('category')
    ->limit(3)->get();
foreach ($sample as $item) {
    $cat = $item->category;
    echo "Item #{$item->id} {$item->name} stock={$item->current_stock} category_id={$item->category_id}";
    if ($cat) {
        echo " cat_branch={$cat->branch_id} type_key={$cat->source_business_type_key}";
    }
    echo "\n";
}

// Simulate API query
$query = App\Models\Item::query()
    ->where('business_id', $businessId)
    ->where('current_stock', '>', 0)
    ->with(['category', 'packagings.packagingType', 'receivingPackaging']);

$keys = $user->assignedBusinessTypeKeys();
if ($keys !== []) {
    $query->whereHas('category', fn ($q) => $q->whereIn('source_business_type_key', $keys));
    echo "\nAfter type keys filter: ".$query->count()."\n";
} else {
    echo "\nNo type keys filter, count: ".$query->count()."\n";
}

// Login API test
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$login = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode(['email' => 'sindato@gmail.com', 'password' => '123456', 'device_name' => 'test']));
$lr = $kernel->handle($login);
$lj = json_decode($lr->getContent(), true);
$token = $lj['data']['token'] ?? null;
echo "\nAPI login: ".($lj['success'] ?? false ? 'OK' : 'FAIL')."\n";

$search = Illuminate\Http\Request::create('/api/v1/items/search?q=Afya&limit=30', 'GET', [], [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    'HTTP_ACCEPT' => 'application/json',
]);
$sr = $kernel->handle($search);
echo "API search status: ".$sr->getStatusCode()."\n";
echo "API raw (first 500): ".substr($sr->getContent(), 0, 500)."\n";
$sj = json_decode($sr->getContent(), true);
echo "API items count: ".count($sj['data']['items'] ?? [])."\n";
echo "API full response keys: ".json_encode(array_keys($sj))."\n";
if (isset($sj['message'])) echo "message: {$sj['message']}\n";
if (isset($sj['success']) && !$sj['success']) echo "FULL: ".$sr->getContent()."\n";

$ctx = app(App\Services\Api\ApiTenantContext::class);
$ctx->setUser($user);
echo "\nTenant businessId: ".$ctx->businessId()."\n";
echo "Tenant branchId: ".json_encode($ctx->branchId())."\n";
echo "isViewingAllBranches: ".($ctx->isViewingAllBranches() ? 'yes' : 'no')."\n";
echo "Cache context: ".json_encode($ctx->get())."\n";

echo "can process_sales: ".($user->can('process_sales') ? 'yes' : 'no')."\n";
echo "can view_inventory: ".($user->can('view_inventory') ? 'yes' : 'no')."\n";

// scopeItemsForPos simulation
$q2 = App\Models\Item::query()->where('business_id', $ctx->businessId())->where('current_stock', '>', 0);
if ($user->branch_id) {
    $q2->whereHas('category', fn ($q) => $q->where('branch_id', (int) $user->branch_id));
    echo "Applied user branch filter\n";
} elseif ($ctx->branchId()) {
    $q2->whereHas('category', fn ($q) => $q->where('branch_id', $ctx->branchId()));
    echo "Applied tenant branch filter branch=".$ctx->branchId()."\n";
}
$keys = $user->assignedBusinessTypeKeys();
if ($keys !== []) {
    $q2->whereHas('category', fn ($q) => $q->whereIn('source_business_type_key', $keys));
}
echo "Scoped count: ".$q2->count()."\n";
