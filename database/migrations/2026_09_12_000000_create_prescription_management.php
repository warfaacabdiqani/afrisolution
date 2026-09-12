<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('tenants', fn(Blueprint $t) => $t->unsignedBigInteger('prescription_sequence')->default(0));
        Schema::create('medications', function(Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('name'); $t->string('generic_name')->nullable(); $t->string('strength',100)->nullable(); $t->string('dosage_form',100)->nullable();
            $t->boolean('active')->default(true); $t->timestamps(); $t->unique(['tenant_id','id']); $t->index(['tenant_id','name']);
        });
        Schema::create('prescriptions', function(Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            foreach (['branch_id'=>'branches','patient_id'=>'patients','doctor_id'=>'doctors','appointment_id'=>'appointments'] as $field=>$table) {
                $t->unsignedBigInteger($field)->nullable($field === 'appointment_id');
                $t->foreign(['tenant_id',$field])->references(['tenant_id','id'])->on($table)->restrictOnDelete();
                $t->index(['tenant_id',$field]);
            }
            // Reserved until the consultation module can validate a real clinical encounter.
            $t->unsignedBigInteger('consultation_id')->nullable()->index();
            $t->string('prescription_number',50); $t->date('prescription_date'); $t->string('status',30)->default('draft');
            foreach (['diagnosis','notes','internal_notes','cancellation_reason'] as $f) $t->text($f)->nullable();
            foreach (['created_by','updated_by','cancelled_by'] as $f) $t->foreignId($f)->nullable($f==='cancelled_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('cancelled_at')->nullable(); $t->timestamps();
            $t->unique(['tenant_id','prescription_number']); $t->unique(['tenant_id','id']);
            $t->index(['tenant_id','prescription_date']); $t->index(['tenant_id','status']);
        });
        Schema::create('prescription_items', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('prescription_id'); $t->unsignedBigInteger('medication_id')->nullable();
            $t->foreign(['tenant_id','prescription_id'])->references(['tenant_id','id'])->on('prescriptions')->restrictOnDelete();
            $t->foreign(['tenant_id','medication_id'])->references(['tenant_id','id'])->on('medications')->restrictOnDelete();
            $t->string('medication_name'); $t->string('strength',100)->nullable(); $t->string('dosage_form',100)->nullable();
            $t->string('dose'); $t->string('route',30); $t->string('frequency',30); $t->string('custom_frequency')->nullable(); $t->string('duration');
            $t->decimal('quantity',12,2)->nullable(); $t->decimal('dispensed_quantity',12,2)->default(0); $t->text('instructions')->nullable();
            $t->string('status',30)->default('pending'); $t->timestamps(); $t->index(['tenant_id','medication_name']);
        });
    }
    public function down(): void {
        foreach (['prescription_items','prescriptions','medications'] as $table) Schema::dropIfExists($table);
        Schema::table('tenants', fn(Blueprint $t) => $t->dropColumn('prescription_sequence'));
    }
};
