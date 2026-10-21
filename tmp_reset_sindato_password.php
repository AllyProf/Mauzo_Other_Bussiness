<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$password = '123456';
$businessId = 10;

$users = User::query()->where('business_id', $businessId)->get();

foreach ($users as $user) {
    $user->update(['password' => $password]);
    echo "Updated: {$user->email} ({$user->role})\n";
}

echo "\nPassword for all SINDATO STORE users is now: {$password}\n";
