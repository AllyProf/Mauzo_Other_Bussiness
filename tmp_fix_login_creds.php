<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$password = '123456';

$users = User::query()
    ->whereNotIn('role', ['super_admin', 'platform_staff'])
    ->orderBy('id')
    ->limit(20)
    ->get(['id', 'name', 'email', 'role', 'is_active', 'business_id', 'password']);

foreach ($users as $user) {
    echo sprintf(
        "%d | %s | %s | active=%s | biz=%s | hash_ok=%s\n",
        $user->id,
        $user->role,
        $user->email,
        $user->is_active ? '1' : '0',
        $user->business_id ?? '-',
        Hash::check($password, $user->password) ? 'yes' : 'no'
    );
}

$owner = User::query()->where('email', 'hsindato@yahoo.com')->first();
if ($owner) {
    $owner->password = $password;
    $owner->save();
    echo "\nReset hsindato@yahoo.com password. Hash check: ".(Hash::check($password, $owner->fresh()->password) ? 'yes' : 'no')."\n";
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode([
    'email' => 'hsindato@yahoo.com',
    'password' => $password,
    'device_name' => 'postman',
]));
$r = $kernel->handle($req);
echo "Login status: ".$r->getStatusCode()."\n";
echo $r->getContent()."\n";
