<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A tax record stores retained tax figures for an obligation. Amounts are
     * entered or computed values that are kept as recorded; the platform never
     * infers a statutory amount. Tax state is separate from work, filing and
     * payment state.
     */
    public function up(): void
    {
        Schema::create('tax_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('obligation_id');
            $table->string('tax_type', 32);
            $table->string('period_label', 100);
            $table->string('currency', 3);
            $table->decimal('taxable_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->string('status', 16);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['firm_id', 'obligation_id'])
                ->references(['firm_id', 'id'])
                ->on('obligations')
                ->restrictOnDelete();
            $table->unique(['firm_id', 'obligation_id']);
            $table->unique(['firm_id', 'id']);
            $table->index(['firm_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_records');
    }
};
