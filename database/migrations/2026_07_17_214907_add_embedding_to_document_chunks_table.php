<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Habilitar extensión si no está activa (idempotente)
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedding vector(1536)');
        } else {
            Schema::table('document_chunks', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding');
        } else {
            Schema::table('document_chunks', function (Blueprint $table) {
                $table->dropColumn('embedding');
            });
        }
    }
};
