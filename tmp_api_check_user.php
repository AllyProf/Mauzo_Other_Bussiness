<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = $argv[1] ?? null;
if (! $email) {
    $user = User::query()->where('role', 'owner')->where('is_active', true)->first()
        ?? User::query()->where('is_active', true)->whereNotNull('business_id')->first();
    echo $user ? "Test user: {$user->email}\n" : "No user found\n";
    exit(0);
}

$user = User::where('email', strtolower($email))->first();
if (! $user) {
    echo "User not found\n";
    exit(1);
}

$password = $argv[2] ?? 'password';
$ok = Hash::check($password, $user->password);
echo $ok ? "Password OK for {$user->email}\n" : "Password mismatch for {$user->email}\n";
