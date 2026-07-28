<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_service_enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('service', 64);
            $table->string('status', 24);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->ulid('responsible_membership_id');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->foreign(['firm_id', 'responsible_membership_id'])
                ->references(['firm_id', 'id'])->on('firm_users')->restrictOnDelete();
            $table->unique(['firm_id', 'client_id', 'service']);
            $table->index(['firm_id', 'status', 'starts_on']);
        });

        Schema::create('tax_registrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('tax_type', 32);
            $table->string('registration_number', 64);
            $table->string('registration_number_normalized', 64);
            $table->string('status', 24);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->unique(['firm_id', 'tax_type', 'registration_number_normalized']);
            $table->index(['firm_id', 'client_id', 'status']);
        });

        Schema::create('tax_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('tax_registration_id');
            $table->string('label', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 24);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['firm_id', 'tax_registration_id'])
                ->references(['firm_id', 'id'])->on('tax_registrations')->restrictOnDelete();
            $table->unique(['firm_id', 'tax_registration_id', 'starts_on', 'ends_on']);
            $table->index(['firm_id', 'status', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_periods');
        Schema::dropIfExists('tax_registrations');
        Schema::dropIfExists('client_service_enrollments');
    }
};
