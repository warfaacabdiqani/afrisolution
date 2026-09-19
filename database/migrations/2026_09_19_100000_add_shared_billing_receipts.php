<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->unsignedBigInteger('billing_receipt_sequence')->default(0));
        Schema::table('billing_invoices', fn (Blueprint $table) => $table->json('document_snapshot')->nullable());
        Schema::table('billing_payments', fn (Blueprint $table) => $table->unique(['tenant_id', 'id'], 'billing_payments_tenant_id_id_unique'));
        Schema::create('billing_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('payment_id');
            $table->string('number', 60);
            $table->json('snapshot');
            $table->timestamps();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('billing_invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('billing_payments')->restrictOnDelete();
            $table->unique(['tenant_id', 'payment_id']);
            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_receipts');
        Schema::table('billing_payments', fn (Blueprint $table) => $table->dropUnique('billing_payments_tenant_id_id_unique'));
        Schema::table('billing_invoices', fn (Blueprint $table) => $table->dropColumn('document_snapshot'));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('billing_receipt_sequence'));
    }
};
