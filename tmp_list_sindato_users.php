<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$business = App\Models\Business::query()
    ->where('name', 'like', '%SINDATO%')
    ->orWhere('name', 'like', '%Sindato%')
    ->get(['id', 'name']);

if ($business->isEmpty()) {
    echo "No Sindato business found\n";
    exit(1);
}

foreach ($business as $b) {
    echo "Business #{$b->id}: {$b->name}\n";
    $users = App\Models\User::query()->where('business_id', $b->id)->get(['id', 'name', 'email', 'role']);
    foreach ($users as $u) {
        echo "  - #{$u->id} {$u->name} <{$u->email}> ({$u->role})\n";
    }
}
