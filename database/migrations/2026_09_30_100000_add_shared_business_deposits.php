<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_invoices', fn (Blueprint $table) => $table->decimal('credit', 12, 2)->default(0)->after('tax'));
        Schema::table('billing_invoice_items', fn (Blueprint $table) => $table->string('kind', 20)->default('charge')->after('source_id'));
        Schema::table('billing_payments', function (Blueprint $table) {
            $table->string('type', 20)->default('payment')->after('invoice_id');
            $table->unsignedBigInteger('reverses_payment_id')->nullable()->after('type');
            $table->foreign('reverses_payment_id')->references('id')->on('billing_payments')->restrictOnDelete();
            $table->index(['tenant_id', 'reverses_payment_id']);
        });
        Schema::create('billing_deposit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('deposit_invoice_id');
            $table->unsignedBigInteger('final_invoice_id');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->foreign(['tenant_id', 'deposit_invoice_id'])->references(['tenant_id', 'id'])->on('billing_invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'final_invoice_id'])->references(['tenant_id', 'id'])->on('billing_invoices')->restrictOnDelete();
            $table->unique(['tenant_id', 'final_invoice_id']);
            $table->index(['tenant_id', 'deposit_invoice_id']);
        });
        Schema::table('salon_appointments', function (Blueprint $table) {
            $table->string('deposit_disposition', 20)->nullable();
            $table->timestamp('deposit_disposition_at')->nullable();
            $table->foreignId('deposit_disposition_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->string('deposit_mode', 20)->nullable();
            $table->decimal('deposit_value', 12, 2)->nullable();
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('deposit_mode', 20)->default('none');
            $table->decimal('deposit_value', 12, 2)->default(0);
            $table->decimal('deposit_basis', 12, 2)->default(0);
            $table->decimal('deposit_required', 12, 2)->default(0);
            $table->string('deposit_disposition', 20)->nullable();
            $table->timestamp('deposit_disposition_at')->nullable();
            $table->foreignId('deposit_disposition_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('dental_plans', function (Blueprint $table) {
            $table->string('deposit_mode', 20)->default('none');
            $table->decimal('deposit_value', 12, 2)->default(0);
            $table->decimal('deposit_basis', 12, 2)->default(0);
            $table->decimal('deposit_required', 12, 2)->default(0);
            $table->string('deposit_disposition', 20)->nullable();
            $table->timestamp('deposit_disposition_at')->nullable();
            $table->foreignId('deposit_disposition_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['dental_plans', 'appointments'] as $source) Schema::table($source, function (Blueprint $table) {
            $table->dropForeign(['deposit_disposition_by']);
            $table->dropColumn(['deposit_mode', 'deposit_value', 'deposit_basis', 'deposit_required', 'deposit_disposition', 'deposit_disposition_at', 'deposit_disposition_by']);
        });
        Schema::table('appointment_types', fn (Blueprint $table) => $table->dropColumn(['deposit_mode', 'deposit_value']));
        Schema::table('salon_appointments', function (Blueprint $table) {
            $table->dropForeign(['deposit_disposition_by']);
            $table->dropColumn(['deposit_disposition', 'deposit_disposition_at', 'deposit_disposition_by']);
        });
        Schema::dropIfExists('billing_deposit_allocations');
        Schema::table('billing_payments', function (Blueprint $table) {
            $table->dropForeign(['reverses_payment_id']);
            $table->dropIndex(['tenant_id', 'reverses_payment_id']);
            $table->dropColumn(['type', 'reverses_payment_id']);
        });
        Schema::table('billing_invoice_items', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('billing_invoices', fn (Blueprint $table) => $table->dropColumn('credit'));
    }
};
