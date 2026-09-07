<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->unsignedBigInteger('doctor_sequence')->default(0));
        Schema::create('specialties', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete(); $t->string('name', 120); $t->timestamps();
            $t->unique(['tenant_id', 'name']); $t->unique(['tenant_id', 'id']);
        });
        Schema::create('doctors', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'user_id'])->on('tenant_memberships')->restrictOnDelete();
            $t->unique(['tenant_id', 'user_id']);
            $t->string('doctor_number', 50); $t->string('first_name', 100); $t->string('middle_name', 100)->nullable(); $t->string('last_name', 100);
            $t->string('gender', 10)->nullable(); $t->string('phone', 40)->nullable(); $t->string('email')->nullable();
            $t->string('license_number', 100)->nullable(); $t->string('qualification', 255)->nullable(); $t->decimal('consultation_fee', 12, 2)->nullable();
            $t->string('status', 20)->default('active'); $t->string('availability_status', 30)->default('available'); $t->text('notes')->nullable();
            $t->unsignedBigInteger('primary_branch_id'); $t->foreign(['tenant_id', 'primary_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->foreignId('updated_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->unique(['tenant_id', 'doctor_number']); $t->unique(['tenant_id', 'id']); $t->index(['tenant_id', 'status']); $t->index(['tenant_id', 'last_name', 'first_name']);
        });
        foreach (['doctor_branch' => ['branch_id', 'branches'], 'doctor_specialty' => ['specialty_id', 'specialties']] as $table => [$column, $target]) {
            Schema::create($table, function (Blueprint $t) use ($column, $target) {
                $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('doctor_id'); $t->unsignedBigInteger($column);
                $t->primary(['doctor_id', $column]);
                $t->foreign(['tenant_id', 'doctor_id'])->references(['tenant_id', 'id'])->on('doctors')->cascadeOnDelete();
                $t->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($target)->restrictOnDelete();
            });
        }
        Schema::create('doctor_schedules', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('doctor_id'); $t->unsignedBigInteger('branch_id');
            $t->foreign(['tenant_id', 'doctor_id'])->references(['tenant_id', 'id'])->on('doctors')->restrictOnDelete();
            $t->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $t->unsignedTinyInteger('day_of_week'); $t->time('start_time')->nullable(); $t->time('end_time')->nullable();
            $t->time('break_start')->nullable(); $t->time('break_end')->nullable(); $t->boolean('is_available')->default(false); $t->timestamps();
            $t->unique(['doctor_id', 'branch_id', 'day_of_week']);
        });
        Schema::create('doctor_leaves', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('doctor_id'); $t->unsignedBigInteger('branch_id')->nullable();
            $t->foreign(['tenant_id', 'doctor_id'])->references(['tenant_id', 'id'])->on('doctors')->restrictOnDelete();
            $t->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $t->date('start_date'); $t->date('end_date'); $t->text('reason')->nullable(); $t->string('status', 20)->default('scheduled');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps(); $t->index(['tenant_id', 'doctor_id', 'start_date', 'end_date']);
        });
    }
    public function down(): void
    {
        foreach (['doctor_leaves', 'doctor_schedules', 'doctor_specialty', 'doctor_branch', 'doctors', 'specialties'] as $table) Schema::dropIfExists($table);
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('doctor_sequence'));
    }
};
