<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/** Transaction and interval primitives shared by industry booking workflows. */
class BookingCore
{
    public function lock(int $tenant): Tenant
    {
        if (!DB::transactionLevel()) throw new \LogicException('Booking writes require a transaction.');
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('tenants')->where('id', $tenant)->update(['appointment_sequence' => DB::raw('appointment_sequence')]);
        }
        return Tenant::lockForUpdate()->findOrFail($tenant);
    }

    public function number(Tenant $tenant): string
    {
        $tenant->increment('appointment_sequence');
        return 'APT-'.str_pad($tenant->appointment_sequence, 6, '0', STR_PAD_LEFT);
    }

    public static function overlaps($start, $end, $otherStart, $otherEnd): bool
    {
        return $start < $otherEnd && $end > $otherStart;
    }

    public function conflicts($query, string $start, string $end, ?int $ignore = null)
    {
        return $query->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
