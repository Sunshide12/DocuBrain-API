<?php

use App\Providers\AgentServiceProvider;
use App\Providers\AppServiceProvider;
use Illuminate\Broadcasting\BroadcastServiceProvider;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;

return [
    AppServiceProvider::class,
    AgentServiceProvider::class,
    BroadcastServiceProvider::class,
    SubscriptionServiceProvider::class,
];
