<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->string('type', 30); // multiple_choice | flashcard | true_false | open_ended
            $table->json('options')->nullable(); // ["A) ...", "B) ...", ...]
            $table->text('correct_answer');
            $table->text('explanation')->nullable();
            $table->integer('page_number')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
