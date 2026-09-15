<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->unsignedBigInteger('billing_invoice_sequence')->default(0));
        foreach (['salon_clients', 'salon_staff_profiles', 'salon_services'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unique(['tenant_id', 'id']));
        }
        Schema::create('salon_appointments', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            foreach (['branch_id' => 'branches', 'client_id' => 'salon_clients', 'stylist_id' => 'salon_staff_profiles'] as $key => $table) {
                $t->unsignedBigInteger($key);
                $t->foreign(['tenant_id', $key])->references(['tenant_id', 'id'])->on($table)->restrictOnDelete();
            }
            $t->string('appointment_number', 40); $t->dateTime('starts_at'); $t->dateTime('ends_at');
            $t->string('status', 30)->default('scheduled'); $t->string('source', 20); $t->text('notes')->nullable();
            $t->string('currency', 3); $t->unsignedInteger('duration_minutes');
            foreach (['subtotal', 'discount', 'tax', 'total', 'deposit_required'] as $field) $t->decimal($field, 12, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->default(0);
            foreach (['checked_in_at', 'service_started_at', 'completed_at', 'cancelled_at'] as $field) $t->timestamp($field)->nullable();
            foreach (['created_by', 'updated_by', 'cancelled_by'] as $field) $t->foreignId($field)->nullable($field === 'cancelled_by')->constrained('users')->restrictOnDelete();
            $t->text('cancellation_reason')->nullable(); $t->timestamps();
            $t->unique(['tenant_id', 'id']); $t->unique(['tenant_id', 'appointment_number']);
            foreach (['branch_id', 'stylist_id', 'client_id', 'status'] as $field) $t->index(['tenant_id', $field, 'starts_at']);
        });
        Schema::create('salon_appointment_services', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('appointment_id'); $t->unsignedBigInteger('service_id');
            $t->foreign(['tenant_id', 'appointment_id'])->references(['tenant_id', 'id'])->on('salon_appointments')->restrictOnDelete();
            $t->foreign(['tenant_id', 'service_id'])->references(['tenant_id', 'id'])->on('salon_services')->restrictOnDelete();
            $t->string('name'); $t->unsignedInteger('duration_minutes'); $t->decimal('unit_price', 12, 2); $t->decimal('amount', 12, 2);
            $t->decimal('deposit_amount', 12, 2)->default(0); $t->timestamps(); $t->unique(['appointment_id', 'service_id']);
        });
        foreach (['salon_staff_schedules', 'salon_location_hours'] as $table) Schema::create($table, function (Blueprint $t) use ($table) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete(); $t->unsignedBigInteger('branch_id');
            $t->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            if ($table === 'salon_staff_schedules') {
                $t->unsignedBigInteger('stylist_id');
                $t->foreign(['tenant_id', 'stylist_id'])->references(['tenant_id', 'id'])->on('salon_staff_profiles')->restrictOnDelete();
            }
            $t->unsignedTinyInteger('day_of_week'); $t->boolean('is_available')->default(true);
            $t->time('start_time'); $t->time('end_time'); $t->time('break_start')->nullable(); $t->time('break_end')->nullable(); $t->timestamps();
            $t->unique($table === 'salon_staff_schedules' ? ['tenant_id', 'stylist_id', 'branch_id', 'day_of_week'] : ['tenant_id', 'branch_id', 'day_of_week'], $table.'_day_unique');
        });
        Schema::create('salon_staff_time_off', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete(); $t->unsignedBigInteger('stylist_id');
            $t->foreign(['tenant_id', 'stylist_id'])->references(['tenant_id', 'id'])->on('salon_staff_profiles')->restrictOnDelete();
            $t->dateTime('starts_at'); $t->dateTime('ends_at'); $t->string('kind', 20); $t->text('reason')->nullable();
            $t->string('status', 20)->default('active'); $t->timestamps(); $t->index(['tenant_id', 'stylist_id', 'starts_at']);
        });
        Schema::create('billing_invoices', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete(); $t->unsignedBigInteger('branch_id');
            $t->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $t->string('source_type', 40); $t->unsignedBigInteger('source_id'); $t->string('number', 60);
            $t->string('customer_name'); $t->string('currency', 3); $t->string('status', 20)->default('unpaid');
            foreach (['subtotal', 'discount', 'tax', 'total', 'paid'] as $field) $t->decimal($field, 12, 2)->default(0);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->unique(['tenant_id', 'source_type', 'source_id']); $t->unique(['tenant_id', 'number']); $t->unique(['tenant_id', 'id']);
        });
        Schema::create('billing_invoice_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('invoice_id');
            $t->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('billing_invoices')->restrictOnDelete();
            $t->string('description'); $t->unsignedInteger('quantity')->default(1); $t->decimal('unit_price', 12, 2); $t->decimal('amount', 12, 2); $t->timestamps();
        });
        Schema::create('billing_payments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('invoice_id');
            $t->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('billing_invoices')->restrictOnDelete();
            $t->decimal('amount', 12, 2); $t->string('method', 30); $t->string('reference')->nullable(); $t->string('idempotency_key', 80);
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete(); $t->timestamp('paid_at'); $t->timestamps();
            $t->unique(['tenant_id', 'idempotency_key']);
        });
    }
    public function down(): void
    {
        foreach (['billing_payments', 'billing_invoice_items', 'billing_invoices', 'salon_staff_time_off', 'salon_staff_schedules', 'salon_location_hours', 'salon_appointment_services', 'salon_appointments'] as $table) Schema::dropIfExists($table);
        foreach (['salon_clients', 'salon_staff_profiles', 'salon_services'] as $table) Schema::table($table, fn (Blueprint $t) => $t->dropUnique(['tenant_id', 'id']));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('billing_invoice_sequence'));
    }
};
