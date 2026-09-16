<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class BillingLock
{
    public function acquire(int $tenantId): Tenant
    {
        abort_unless($tenantId === app(TenantContext::class)->id(), 403);
        if (!DB::transactionLevel()) throw new \LogicException('Billing writes require a transaction.');
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('tenants')->where('id', $tenantId)->update(['billing_invoice_sequence' => DB::raw('billing_invoice_sequence')]);
        }
        return Tenant::lockForUpdate()->findOrFail($tenantId);
    }
}
