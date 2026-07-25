<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_math_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('page_number');
            $table->text('content');
            $table->timestamps();

            $table->unique(['document_id', 'page_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_math_pages');
    }
};
