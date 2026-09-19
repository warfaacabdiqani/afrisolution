<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_payments', fn (Blueprint $table) => $table->index(['tenant_id', 'paid_at', 'invoice_id'], 'billing_payments_report_index'));
    }

    public function down(): void
    {
        Schema::table('billing_payments', fn (Blueprint $table) => $table->dropIndex('billing_payments_report_index'));
    }
};
