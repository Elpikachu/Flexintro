<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_chunk_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('worker_host', 128);
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('finished_at');

            // A retried chunk updates its row instead of adding a second one.
            $table->unique(['import_id', 'chunk_index']);

            // Supports the 60 second window behind the worker strip.
            $table->index(['import_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_chunk_runs');
    }
};
