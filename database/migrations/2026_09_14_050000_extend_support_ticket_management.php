<?php

use App\Support\SupportTicketOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing messages were all submitted through the tenant workflow.
        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->string('source', 20)->default('business');
        });

        foreach (SupportTicketOptions::PERMISSIONS as $name => $label) {
            DB::table('platform_permissions')->updateOrInsert(['name' => $name], [
                'label' => $label, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $permission = DB::table('platform_permissions')->where('name', $name)->value('id');
            $role = DB::table('platform_roles')->where('slug', 'super-administrator')->value('id');
            if ($role) {
                DB::table('platform_permission_role')->insertOrIgnore([
                    'platform_role_id' => $role, 'platform_permission_id' => $permission,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('platform_permissions')->whereIn('name', array_keys(SupportTicketOptions::PERMISSIONS))->pluck('id');
        DB::table('platform_permission_role')->whereIn('platform_permission_id', $ids)->delete();
        DB::table('platform_permissions')->whereIn('id', $ids)->delete();
        Schema::table('support_ticket_messages', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
