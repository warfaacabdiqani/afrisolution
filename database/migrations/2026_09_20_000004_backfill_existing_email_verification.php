<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // The verification gate starts with new self-registrations. Existing accounts
        // predate it and must retain access without an unexpected lockout.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Historical verification state cannot be distinguished safely on rollback.
    }
};
