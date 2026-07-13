<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Events\DocumentUploaded;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\ValidationException;

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

        // Strict PDF Validation
        if ($file->getMimeType() !== 'application/pdf') {
            throw ValidationException::withMessages(['file' => 'The document must be a valid PDF file.']);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'The document must not be greater than 10 MB.']);
        }

        // Check magic bytes for PDF (%PDF-)
        $resource = fopen($file->getRealPath(), 'rb');
        $magicBytes = fread($resource, 5);
        fclose($resource);

        if ($magicBytes !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'The document content is not a valid PDF.']);
        }

        // Sanitize filename for storage
        $title = $args['title'] ?? $file->getClientOriginalName();
        $originalName = $file->getClientOriginalName();
        $sanitizedName = Str::slug(pathinfo($title, PATHINFO_FILENAME)) . '.pdf';

        $path = $file->storeAs('documents', uniqid('', true) . '_' . $sanitizedName);

        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();

        $document = Document::create([
            'user_id'       => $user->id,
            'title'         => $title,
            'original_name' => $originalName,
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
