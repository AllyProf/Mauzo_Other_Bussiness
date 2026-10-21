<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = App\Models\User::where('email', 'hsindato@yahoo.com')->first();
if (! $u || ! $u->business) {
    echo "User or business not found\n";
    exit(1);
}
$b = $u->business;
$b->expiry_date = now()->addYear();
$b->save();
echo "Updated business #{$b->id} ({$b->name}) expiry to {$b->expiry_date->toDateString()}\n";
