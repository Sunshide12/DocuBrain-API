<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\AgentServiceProvider::class,
    Illuminate\Broadcasting\BroadcastServiceProvider::class,
    Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider::class,
];
