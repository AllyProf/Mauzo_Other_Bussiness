<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'mobile.demo@mauzolink.co.tz';
$password = 'MobileDemo@2026';
$name = 'Mobile Demo User';

$business = Business::query()
    ->where('is_active', true)
    ->where('pending_approval', false)
    ->orderBy('id')
    ->first();

if (! $business) {
    echo "No active business found.\n";
    exit(1);
}

$branch = Branch::query()
    ->where('is_active', true)
    ->where(function ($q) use ($business) {
        $q->where('business_id', $business->id)
            ->orWhereHas('businesses', fn ($b) => $b->where('businesses.id', $business->id));
    })
    ->orderByDesc('is_default')
    ->first();

$user = User::query()->where('email', $email)->first();

if ($user) {
    $user->update([
        'name' => $name,
        'password' => $password,
        'business_id' => $business->id,
        'branch_id' => $branch?->id,
        'role' => 'owner',
        'role_id' => null,
        'is_active' => true,
        'phone' => '255700000001',
    ]);
    echo "Updated existing mobile demo account.\n";
} else {
    User::create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'phone' => '255700000001',
        'business_id' => $business->id,
        'branch_id' => $branch?->id,
        'role' => 'owner',
        'role_id' => null,
        'is_active' => true,
        'locale' => 'en',
    ]);
    echo "Created mobile demo account.\n";
}

echo "\n";
echo "Business: {$business->name} (#{$business->id})\n";
echo "Branch: ".($branch?->name ?? 'n/a')."\n";
echo "\n";
echo "LOGIN (web + mobile API)\n";
echo "Email:    {$email}\n";
echo "Password: {$password}\n";
echo "\n";
echo "API: POST /api/v1/auth/login\n";
