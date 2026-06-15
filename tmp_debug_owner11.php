<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ownerId = 11;
echo "Businesses for owner_user_id=$ownerId:\n";
foreach (App\Models\Business::where('owner_user_id', $ownerId)->get() as $b) {
    echo "  {$b->id}: {$b->name}\n";
}

echo "\nActiveBusinessService simulation for owner 11:\n";
$user = App\Models\User::find(11);
$businesses = App\Models\Business::query()
    ->where(function ($query) use ($user) {
        $query->where('owner_user_id', $user->id);
        if ($user->business_id) {
            $query->orWhere('id', $user->business_id);
        }
    })
    ->where('is_active', true)
    ->orderBy('name')
    ->get();
foreach ($businesses as $b) {
    echo "  {$b->id}: {$b->name}\n";
}

echo "\nBranch 7 businesses via businessesForBranch logic:\n";
$assignableIds = $businesses->pluck('id')->all();
$branch = App\Models\Branch::find(7);
$pivot = $branch->businesses()->whereIn('businesses.id', $assignableIds)->where('businesses.is_active', true)->get();
foreach ($pivot as $b) {
    echo "  {$b->id}: {$b->name}\n";
}
