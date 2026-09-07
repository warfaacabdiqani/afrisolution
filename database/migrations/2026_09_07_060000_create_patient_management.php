<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->unsignedBigInteger('patient_sequence')->default(0));
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('registration_branch_id');
            $t->foreign(['tenant_id', 'registration_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $t->string('patient_number', 50);
            foreach (['first_name', 'middle_name', 'last_name'] as $name) $t->string($name, 100)->nullable($name === 'middle_name');
            $t->string('gender', 20);
            $t->date('date_of_birth')->nullable();
            $t->string('blood_group', 5)->nullable();
            $t->string('marital_status', 20)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('city', 100)->nullable();
            $t->string('country', 100)->nullable();
            $t->string('emergency_contact_name', 150)->nullable();
            $t->string('emergency_contact_phone', 40)->nullable();
            $t->string('emergency_contact_relationship', 100)->nullable();
            $t->string('status', 20)->default('active');
            $t->text('notes')->nullable();
            $t->timestamp('registered_at');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->timestamp('archived_at')->nullable();
            $t->unique(['tenant_id', 'patient_number']);
            $t->unique(['tenant_id', 'id']);
            $t->index(['tenant_id', 'status', 'registered_at']);
            $t->index(['tenant_id', 'last_name', 'first_name']);
            $t->index(['tenant_id', 'phone']);
            $t->index(['tenant_id', 'email']);
        });
        foreach (['patient_allergies', 'patient_conditions', 'patient_documents'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('patient_id');
                $t->foreign(['tenant_id', 'patient_id'])->references(['tenant_id', 'id'])->on('patients')->restrictOnDelete();
                if ($table === 'patient_allergies') {
                    $t->string('allergen', 150); $t->string('reaction', 255)->nullable(); $t->string('severity', 20)->nullable();
                    $t->string('status', 20)->default('active'); $t->text('notes')->nullable();
                } elseif ($table === 'patient_conditions') {
                    $t->string('condition_name', 150); $t->date('diagnosed_date')->nullable();
                    $t->string('status', 20)->default('active'); $t->text('notes')->nullable();
                } else {
                    $t->string('title', 150); $t->string('document_type', 30); $t->text('description')->nullable();
                    $t->string('path'); $t->string('mime', 100); $t->string('extension', 5); $t->unsignedBigInteger('size');
                    $t->timestamp('archived_at')->nullable();
                }
                $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
                $t->timestamps(); $t->index(['tenant_id', 'patient_id']);
            });
        }
    }
    public function down(): void
    {
        foreach (['patient_documents', 'patient_conditions', 'patient_allergies', 'patients'] as $table) Schema::dropIfExists($table);
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('patient_sequence'));
    }
};
