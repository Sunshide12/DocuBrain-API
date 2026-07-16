<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Nuwave\Lighthouse\Execution\Utils\Subscription;

$payload = [
    'document_id' => 1,
    'status'      => 'extracting',
    'message'     => 'Testing broadcast',
    'progress'    => 10,
];

try {
    Subscription::broadcast('documentProgress', $payload);
    echo "Broadcast successful\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
