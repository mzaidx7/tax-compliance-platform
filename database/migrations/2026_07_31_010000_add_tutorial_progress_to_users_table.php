<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('tutorial_prompt_dismissed_at')->nullable()->after('remember_token');
            $table->timestamp('tutorial_completed_at')->nullable()->after('tutorial_prompt_dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['tutorial_prompt_dismissed_at', 'tutorial_completed_at']);
        });
    }
};
