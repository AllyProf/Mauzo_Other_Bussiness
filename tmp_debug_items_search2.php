<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Login
$login = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode(['email' => 'sindato@gmail.com', 'password' => '123456', 'device_name' => 'test']));
$kernel->handle($login);
$token = json_decode($login->response ?? '', true); // won't work

// Use sanctum token directly
$user = App\Models\User::where('email', 'sindato@gmail.com')->first();
$token = $user->createToken('debug')->plainTextToken;

$search = Illuminate\Http\Request::create('/api/v1/items/search?q=Afya&limit=30', 'GET', [], [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    'HTTP_ACCEPT' => 'application/json',
]);
$response = $kernel->handle($search);

// Inspect tenant during request via callback - too late. Call controller directly:
app(App\Services\Api\ApiTenantContext::class)->setUser($user);
$ctx = app(App\Services\Api\ApiTenantContext::class);
echo "Direct ctx businessId=".$ctx->businessId()." branchId=".json_encode($ctx->branchId())."\n";

$controller = app(App\Http\Controllers\Api\V1\ItemController::class);
$req = Illuminate\Http\Request::create('/api/v1/items/search?q=Afya&limit=30', 'GET', ['q' => 'Afya', 'limit' => 30]);
$req->setUserResolver(fn () => $user);
Illuminate\Support\Facades\Auth::setUser($user);

$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('search');
$resp = $method->invoke($controller, $req);
$content = json_decode($resp->getContent(), true);
echo "Controller direct items: ".count($content['data']['items'] ?? [])."\n";
if (!empty($content['data']['items'])) {
    echo "First: ".$content['data']['items'][0]['name']."\n";
}

// Step through query manually in controller style
$businessId = app(App\Services\Api\ApiTenantContext::class)->businessId();
echo "businessId used: $businessId\n";
$query = App\Models\Item::query()
    ->where('business_id', $businessId)
    ->where('current_stock', '>', 0)
    ->with(['category', 'packagings.packagingType', 'receivingPackaging']);
if ($user->branch_id) {
    $query->whereHas('category', fn ($q) => $q->where('branch_id', (int) $user->branch_id));
} elseif ($ctx->branchId()) {
    $query->whereHas('category', fn ($q) => $q->where('branch_id', $ctx->branchId()));
}
$keys = $user->assignedBusinessTypeKeys();
if ($keys !== []) {
    $query->whereHas('category', fn ($q) => $q->whereIn('source_business_type_key', $keys));
}
$query->where(function ($inner) {
    $inner->where('name', 'like', '%Afya%')
        ->orWhere('sku', 'like', '%Afya%')
        ->orWhere('brand', 'like', '%Afya%');
});
$items = $query->orderBy('name')->limit(30)->get();
echo "Manual query count: ".$items->count()."\n";
foreach ($items->take(2) as $i) echo " - {$i->name}\n";

echo "\nHTTP response items: ".count(json_decode($response->getContent(), true)['data']['items'] ?? [])."\n";
