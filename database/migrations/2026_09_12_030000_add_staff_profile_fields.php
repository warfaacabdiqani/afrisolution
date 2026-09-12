<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->string('staff_number', 50)->nullable()->after('role');
            $table->string('first_name', 100)->nullable()->after('staff_number');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('phone', 40)->nullable()->after('last_name');
            $table->string('job_title', 120)->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropColumn(['staff_number', 'first_name', 'middle_name', 'last_name', 'phone', 'job_title', 'last_login_at']);
        });
    }
};
