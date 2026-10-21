<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Business::where('name', 'like', '%MIGLO%')->get() as $b) {
    echo "Business {$b->id}: {$b->name}\n";
    $branches = App\Models\Branch::where('business_id', $b->id)->get(['id', 'name']);
    foreach ($branches as $br) {
        echo "  Branch {$br->id}: {$br->name}\n";
    }
    $users = App\Models\User::where('business_id', $b->id)->whereIn('role', ['owner', 'manager'])->get(['id', 'name', 'role']);
    foreach ($users as $u) {
        echo "  User {$u->id}: {$u->name} ({$u->role})\n";
    }
    echo '  Items: '.App\Models\Item::where('business_id', $b->id)->count()."\n";
    echo '  Categories: '.App\Models\Category::where('business_id', $b->id)->count()."\n";
    echo '  Packagings: '.App\Models\Packaging::where('business_id', $b->id)->count()."\n";
}
