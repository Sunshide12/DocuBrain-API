<?php

namespace App\GraphQL\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use App\Models\Document;

class DocumentProgress extends GraphQLSubscription
{
    /**
     * Check if subscriber is allowed to listen to the subscription.
     */
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        $args = $subscriber->args;
        $documentId = $args['document_id'] ?? null;
        if ($documentId) {
            // Only allow if the user owns the document
            return Document::where('id', $documentId)
                ->where('user_id', $subscriber->context->user()->id)
                ->exists();
        }

        // Allow generic subscription for all user's documents
        return true;
    }

    /**
     * Filter which subscribers should receive the subscription.
     */
    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        $args = $subscriber->args;
        if (isset($args['document_id']) && $args['document_id']) {
            return $root['document_id'] == $args['document_id'];
        }

        // Global subscription for the user, check if the document belongs to the user
        $document = Document::find($root['document_id']);
        return $document && $document->user_id == $subscriber->context->user()->id;
    }
}
