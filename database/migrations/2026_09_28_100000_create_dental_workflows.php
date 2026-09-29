<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dental_procedures', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('code', 40); $t->string('name', 150); $t->text('description')->nullable();
            $t->decimal('price', 12, 2); $t->boolean('active')->default(true); $t->timestamps();
            $t->unique(['tenant_id', 'code']); $t->unique(['tenant_id', 'id']);
        });
        Schema::create('dental_findings', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            foreach (['patient_id' => 'patients', 'branch_id' => 'branches'] as $column => $table) {
                $t->unsignedBigInteger($column);
                $t->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($table)->restrictOnDelete();
            }
            $t->string('tooth', 2); $t->json('surfaces'); $t->string('condition', 30); $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('voided_at')->nullable(); $t->text('void_reason')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->index(['tenant_id', 'patient_id', 'branch_id']);
        });
        Schema::create('dental_plans', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            foreach (['patient_id' => 'patients', 'branch_id' => 'branches'] as $column => $table) {
                $t->unsignedBigInteger($column);
                $t->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($table)->restrictOnDelete();
            }
            $t->string('title', 150); $t->text('notes')->nullable(); $t->string('status', 20)->default('draft');
            $t->string('currency', 3); $t->decimal('tax_rate', 5, 2)->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('accepted_at')->nullable(); $t->timestamp('completed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable(); $t->text('cancellation_reason')->nullable(); $t->timestamps();
            $t->unique(['tenant_id', 'id']); $t->index(['tenant_id', 'patient_id', 'branch_id']);
        });
        Schema::create('dental_plan_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('procedure_id');
            $t->foreign(['tenant_id', 'plan_id'])->references(['tenant_id', 'id'])->on('dental_plans')->restrictOnDelete();
            $t->foreign(['tenant_id', 'procedure_id'])->references(['tenant_id', 'id'])->on('dental_procedures')->restrictOnDelete();
            $t->string('procedure_code', 40); $t->string('procedure_name', 150);
            $t->string('tooth', 2)->nullable(); $t->json('surfaces'); $t->unsignedSmallInteger('visit_number');
            $t->unsignedSmallInteger('quantity')->default(1); $t->decimal('unit_price', 12, 2);
            $t->string('status', 20)->default('planned'); $t->text('notes')->nullable();
            $t->unsignedBigInteger('appointment_id')->nullable();
            $t->foreign(['tenant_id', 'appointment_id'])->references(['tenant_id', 'id'])->on('appointments')->restrictOnDelete();
            $t->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('completed_at')->nullable(); $t->text('completion_notes')->nullable(); $t->timestamps();
            $t->index(['tenant_id', 'plan_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['dental_plan_items', 'dental_plans', 'dental_findings', 'dental_procedures'] as $table) Schema::dropIfExists($table);
    }
};
