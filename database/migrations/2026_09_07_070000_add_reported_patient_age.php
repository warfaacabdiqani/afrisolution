<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('patients', function (Blueprint $t) { $t->unsignedSmallInteger('reported_age')->nullable(); $t->date('age_recorded_on')->nullable(); }); }
    public function down(): void { Schema::table('patients', fn (Blueprint $t) => $t->dropColumn(['reported_age', 'age_recorded_on'])); }
};
