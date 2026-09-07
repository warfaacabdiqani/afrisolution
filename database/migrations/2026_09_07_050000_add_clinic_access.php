<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            // Null retains the existing role's defaults; an empty array denies all permissions.
            $table->json('permissions')->nullable();
            $table->boolean('all_branches')->default(true);
        });
        Schema::create('branch_memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('branch_id');
            $table->primary(['membership_id', 'branch_id']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('branch_memberships');
        Schema::table('tenant_memberships', fn (Blueprint $table) => $table->dropColumn(['permissions', 'all_branches']));
    }
};
