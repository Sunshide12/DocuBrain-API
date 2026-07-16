<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

$subscription = new \App\GraphQL\Subscriptions\DocumentProgress();
$payload = [
    'document_id' => 1,
    'status'      => 'extracting',
    'message'     => 'Testing broadcast',
    'progress'    => 10,
];

// $subscriber = new Subscriber([], null, null);
// $subscriber->root = $payload;

$result = $subscription->resolve($payload, [], app(\Nuwave\Lighthouse\Support\Contracts\GraphQLContext::class), null);
var_dump($result);
