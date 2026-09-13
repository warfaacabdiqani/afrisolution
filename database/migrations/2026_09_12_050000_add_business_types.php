<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('business_types')->insertOrIgnore([
            'name' => 'Healthcare / Clinic',
            'slug' => 'clinic',
            'category' => 'healthcare',
            'description' => 'Healthcare and clinical operations.',
            'icon' => 'clinic',
            'status' => 'active',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('business_type_id')->nullable()->after('timezone')->constrained('business_types')->nullOnDelete();
        });

        $clinicBusinessTypeId = DB::table('business_types')->where('slug', 'clinic')->value('id');
        if ($clinicBusinessTypeId) {
            DB::table('tenants')->whereNull('business_type_id')->update(['business_type_id' => $clinicBusinessTypeId]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('business_type_id');
        });

        Schema::dropIfExists('business_types');
    }
};
