<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->unsignedBigInteger('appointment_sequence')->default(0));
        Schema::create('appointment_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->unsignedSmallInteger('default_duration')->default(30);
            $t->string('color_key', 20)->default('blue');
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->unique(['tenant_id', 'id']);
            $t->unique(['tenant_id', 'name']);
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            foreach (['branch_id' => 'branches', 'patient_id' => 'patients', 'doctor_id' => 'doctors', 'appointment_type_id' => 'appointment_types'] as $column => $table) {
                $t->unsignedBigInteger($column)->nullable($column === 'appointment_type_id');
                $t->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($table)->restrictOnDelete();
            }
            $t->string('appointment_number', 40);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('status', 30)->default('scheduled');
            $t->text('reason')->nullable();
            $t->text('notes')->nullable();
            $t->string('source', 20)->default('clinic');
            $t->boolean('is_walk_in')->default(false);
            foreach (['created_by', 'updated_by', 'cancelled_by'] as $actor) {
                $t->foreignId($actor)->nullable($actor === 'cancelled_by')->constrained('users')->restrictOnDelete();
            }
            foreach (['cancelled_at', 'checked_in_at', 'consultation_started_at', 'completed_at'] as $date) {
                $t->timestamp($date)->nullable();
            }
            $t->text('cancellation_reason')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'appointment_number']);
            $t->unique(['tenant_id', 'id']);
            foreach (['branch_id', 'doctor_id', 'patient_id', 'status'] as $column) {
                $t->index(['tenant_id', $column, 'starts_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_types');
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('appointment_sequence'));
    }
};
