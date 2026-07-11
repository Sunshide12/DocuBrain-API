<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Events\DocumentUploaded;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class UploadDocument
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke(null $_, array $args): Document
    {
        /** @var UploadedFile $file */
        $file = $args['file'];

        $path = $file->store('documents');

        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();

        $document = Document::create([
            'user_id'       => $user->id,
            'title'         => $args['title'] ?? $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'file_path'     => $path,
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
            'status'        => 'pending',
        ]);

        // 1. Dispatch the heavy processing to the Redis queue (async).
        //    The HTTP response returns immediately with status = 'pending'.
        //    A queue:work worker will pick this up in the background.
        ProcessDocumentJob::dispatch($document);

        // 2. Fire a synchronous Laravel event so listeners (e.g. cache
        //    invalidation) can react without being coupled to this mutation.
        DocumentUploaded::dispatch($document);

        return $document;
    }
}
