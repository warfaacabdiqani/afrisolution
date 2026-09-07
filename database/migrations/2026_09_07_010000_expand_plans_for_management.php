<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->string('status')->default('active')->after('description');
            $table->decimal('price', 12, 2)->default(0)->after('status');
            $table->char('currency', 3)->default('USD')->after('price');
            $table->string('billing_period')->default('monthly')->after('currency');
            $table->unsignedInteger('doctor_limit')->nullable()->after('member_limit');
            $table->unsignedInteger('patient_limit')->nullable()->after('doctor_limit');
            $table->unsignedInteger('storage_limit_gb')->nullable()->after('patient_limit');
            $table->unsignedInteger('appointment_limit')->nullable()->after('storage_limit_gb');
            $table->unsignedInteger('invoice_limit')->nullable()->after('appointment_limit');
            $table->json('features')->nullable()->after('trial_days');
        });

        foreach (DB::table('plans')->orderBy('id')->get(['id', 'name']) as $plan) {
            DB::table('plans')->where('id', $plan->id)->update(['slug' => Str::slug($plan->name).'-'.$plan->id]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn([
            'slug', 'description', 'status', 'price', 'currency', 'billing_period', 'doctor_limit',
            'patient_limit', 'storage_limit_gb', 'appointment_limit', 'invoice_limit', 'features',
        ]));
    }
};
