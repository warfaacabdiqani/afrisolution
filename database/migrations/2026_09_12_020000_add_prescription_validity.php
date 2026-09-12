<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('prescriptions',fn(Blueprint $t)=>$t->date('expires_on')->nullable()->index()); }
    public function down(): void { Schema::table('prescriptions',fn(Blueprint $t)=>$t->dropColumn('expires_on')); }
};
