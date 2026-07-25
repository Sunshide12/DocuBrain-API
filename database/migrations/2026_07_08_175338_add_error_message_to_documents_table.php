<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds error_message to the documents table.
 *
 * This column is set by ProcessDocumentJob when the processing pipeline
 * fails, so the user (and UI) can display a meaningful error reason.
 * Matches the spec: Document { error_message (nullable) }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Placed after 'status' for semantic grouping.
            // nullable() because only failed documents have this set.
            $table->text('error_message')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });
    }
};

