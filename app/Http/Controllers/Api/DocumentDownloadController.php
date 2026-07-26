<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        // Ensure user can only download their own document
        if ($request->user()->id !== $document->user_id) {
            abort(403, 'Unauthorized.');
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Document file not found.');
        }

        return Storage::disk('local')->response($document->file_path, $document->original_name);
    }
}
