<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('primary_email')->nullable()->after('entity_type');
            $table->string('primary_phone', 32)->nullable()->after('primary_email');
            $table->string('vat_trn', 64)->nullable()->after('primary_phone');
            $table->string('corporate_tax_trn', 64)->nullable()->after('vat_trn');
            $table->string('vat_frequency', 16)->nullable()->after('primary_phone');
            $table->date('vat_period_starts_on')->nullable()->after('vat_frequency');
            $table->date('vat_period_ends_on')->nullable()->after('vat_period_starts_on');
            $table->date('corporate_tax_period_starts_on')->nullable()->after('vat_period_ends_on');
            $table->date('corporate_tax_period_ends_on')->nullable()->after('corporate_tax_period_starts_on');
            $table->text('trade_license_number')->nullable()->after('corporate_tax_period_ends_on');
            $table->string('trade_license_authority')->nullable()->after('trade_license_number');
            $table->date('trade_license_issued_on')->nullable()->after('trade_license_authority');
            $table->date('trade_license_expires_on')->nullable()->after('trade_license_issued_on');
            $table->text('passport_number')->nullable()->after('trade_license_expires_on');
            $table->date('passport_expires_on')->nullable()->after('passport_number');
            $table->text('emirates_id_number')->nullable()->after('passport_expires_on');
            $table->date('emirates_id_expires_on')->nullable()->after('emirates_id_number');
            $table->index(['firm_id', 'vat_period_ends_on']);
            $table->index(['firm_id', 'corporate_tax_period_ends_on']);
            $table->index(['firm_id', 'trade_license_expires_on']);
            $table->index(['firm_id', 'passport_expires_on']);
            $table->index(['firm_id', 'emirates_id_expires_on']);
        });

        Schema::create('client_people', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('name');
            $table->string('role', 64);
            $table->text('passport_number')->nullable();
            $table->date('passport_expires_on')->nullable();
            $table->text('emirates_id_number')->nullable();
            $table->date('emirates_id_expires_on')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->index(['firm_id', 'client_id', 'role', 'is_active']);
            $table->index(['firm_id', 'passport_expires_on']);
            $table->index(['firm_id', 'emirates_id_expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_people');

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['clients_firm_id_vat_period_ends_on_index']);
            $table->dropIndex(['clients_firm_id_corporate_tax_period_ends_on_index']);
            $table->dropIndex(['clients_firm_id_trade_license_expires_on_index']);
            $table->dropIndex(['clients_firm_id_passport_expires_on_index']);
            $table->dropIndex(['clients_firm_id_emirates_id_expires_on_index']);
            $table->dropColumn([
                'primary_email', 'primary_phone', 'vat_trn', 'corporate_tax_trn', 'vat_frequency', 'vat_period_starts_on', 'vat_period_ends_on',
                'corporate_tax_period_starts_on', 'corporate_tax_period_ends_on', 'trade_license_number',
                'trade_license_authority', 'trade_license_issued_on', 'trade_license_expires_on', 'passport_number',
                'passport_expires_on', 'emirates_id_number', 'emirates_id_expires_on',
            ]);
        });
    }
};
