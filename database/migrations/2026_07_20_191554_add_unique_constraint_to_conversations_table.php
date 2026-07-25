<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, deduplicate existing conversations
        // Keep the oldest conversation for each (user_id, document_id) pair
        // and delete duplicates along with their messages (cascaded)
        \Illuminate\Support\Facades\DB::statement("
            DELETE FROM conversations
            WHERE id NOT IN (
                SELECT MIN(id)
                FROM conversations
                GROUP BY user_id, document_id
            )
        ");

        Schema::table('conversations', function (Blueprint $table) {
            // Add unique constraint on (user_id, document_id)
            // This ensures one conversation per document per user
            // Note: PostgreSQL treats NULL as distinct, so multiple global chats
            // (document_id = NULL) will still be allowed if needed
            $table->unique(['user_id', 'document_id'], 'conversations_user_document_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_user_document_unique');
        });
    }
};
