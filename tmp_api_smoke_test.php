<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;

function api(Request $request)
{
    global $kernel;
    $request->headers->set('Accept', 'application/json');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}

// 1) Login (set email/password via env or args)
$email = getenv('API_TEST_EMAIL') ?: 'sindato@gmail.com';
$password = getenv('API_TEST_PASSWORD') ?: 'password';

$login = api(Request::create('/api/v1/auth/login', 'POST', [
    'email' => $email,
    'password' => $password,
    'device_name' => 'api-test',
], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
    'email' => $email,
    'password' => $password,
    'device_name' => 'api-test',
])));

echo "LOGIN ".$login->getStatusCode()."\n";
$loginJson = json_decode($login->getContent(), true);
echo substr($login->getContent(), 0, 500)."\n\n";

if (! ($loginJson['success'] ?? false)) {
    echo "Login failed — set API_TEST_EMAIL and API_TEST_PASSWORD env vars.\n";
    exit(1);
}

$token = $loginJson['data']['token'];

$auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'Accept' => 'application/json'];

// 2) Me
$me = api(Request::create('/api/v1/auth/me', 'GET', [], [], [], $auth));
echo "ME ".$me->getStatusCode()."\n";

// 3) Dashboard
$dash = api(Request::create('/api/v1/dashboard/today', 'GET', [], [], [], $auth));
echo "DASHBOARD ".$dash->getStatusCode()."\n";

// 4) Items search
$items = api(Request::create('/api/v1/items/search?q=', 'GET', [], [], [], $auth));
echo "ITEMS ".$items->getStatusCode()." count=".count(json_decode($items->getContent(), true)['data']['items'] ?? [])."\n";

// 5) Payment methods
$pm = api(Request::create('/api/v1/payment-methods', 'GET', [], [], [], $auth));
echo "PAYMENT METHODS ".$pm->getStatusCode()."\n";

echo "\nAPI smoke test OK\n";
