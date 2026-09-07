<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50);
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
            $table->index('group');
        });

        $now = now();
        foreach (['settings.view'=>'View system settings','settings.update'=>'Update system settings','settings.maintenance'=>'Manage platform maintenance'] as $name => $label) {
            $permissionId = DB::table('platform_permissions')->insertGetId(['name'=>$name,'label'=>$label,'created_at'=>$now,'updated_at'=>$now]);
            $roleId = DB::table('platform_roles')->where('slug','super-administrator')->value('id');
            DB::table('platform_permission_role')->insert(['platform_role_id'=>$roleId,'platform_permission_id'=>$permissionId]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('platform_permissions')->whereIn('name',['settings.view','settings.update','settings.maintenance'])->pluck('id');
        DB::table('platform_permission_role')->whereIn('platform_permission_id',$ids)->delete();
        DB::table('platform_permissions')->whereIn('id',$ids)->delete();
        Schema::dropIfExists('system_settings');
    }
};
