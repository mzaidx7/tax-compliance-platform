<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_operational_filters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('surface', 40);
            $table->string('name', 80);
            $table->string('name_normalized', 80);
            $table->json('filters');
            $table->timestamps();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'user_id', 'surface', 'name_normalized'], 'saved_filter_owner_name_unique');
            $table->foreign('firm_id')->references('id')->on('firms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_operational_filters');
    }
};
