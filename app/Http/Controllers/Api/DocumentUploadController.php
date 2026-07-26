<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\DocumentUploaded;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DocumentUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');

        // Check magic bytes for PDF (%PDF-)
        $resource = fopen($file->getRealPath(), 'rb');
        $magicBytes = fread($resource, 5);
        fclose($resource);

        if ($magicBytes !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'The document content is not a valid PDF.']);
        }

        // Sanitize filename for storage
        $title = $request->input('title') ?? $file->getClientOriginalName();
        $originalName = $file->getClientOriginalName();
        $sanitizedName = Str::slug(pathinfo($title, PATHINFO_FILENAME)).'.pdf';

        $path = $file->storeAs('documents', uniqid('', true).'_'.$sanitizedName);

        /** @var User $user */
        $user = $request->user();

        $document = Document::create([
            'user_id' => $user->id,
            'title' => $title,
            'original_name' => $originalName,
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => 'pending',
        ]);

        // 1. Dispatch the heavy processing to the Redis queue (async).
        //    The HTTP response returns immediately with status = 'pending'.
        //    A queue:work worker will pick this up in the background.
        ProcessDocumentJob::dispatch($document);

        // 2. Fire a synchronous Laravel event so listeners (e.g. cache
        //    invalidation) can react without being coupled to this mutation.
        DocumentUploaded::dispatch($document);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $document->toArray(),
        ], Response::HTTP_CREATED);
    }
}
