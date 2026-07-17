<?php
require __DIR__.'/backend/vendor/autoload.php';
$app = require_once __DIR__.'/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::where('email', 'steban@gmail.com')->first();
$token = $user->createToken('test')->plainTextToken;

$request = Illuminate\Http\Request::create('/broadcasting/auth', 'POST', [
    'socket_id' => '123.456',
    'channel_name' => 'private-App.Models.User.1'
]);
$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer ' . $token);

$response = $kernel->handle($request);
echo $response->getStatusCode() . "\n";
echo $response->getContent() . "\n";
