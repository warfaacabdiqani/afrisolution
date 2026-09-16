<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Services\BusinessProfileService;
use Illuminate\Validation\ValidationException;

class BillingCustomerResolver
{
    /** Validate a history filter without exposing customer data or broadening invoice branch scope. */
    public function historyFilter(Tenant $tenant, string $type, int $id, array $branchIds): array
    {
        $definition = config('billing.customers.'.$type);
        abort_unless($definition && app(BusinessProfileService::class)->moduleEnabled($tenant, $definition['capability']), 403);
        $query = $definition['model']::where('tenant_id', $tenant->id);
        if (isset($definition['branch_column'])) $query->whereIn($definition['branch_column'], $branchIds);
        $customer = $query->findOrFail($id);
        return [$definition['column'], $customer->id];
    }

    public function resolve(Tenant $tenant, string $type, int $id, int $branchId): array
    {
        $definition = config('billing.customers.'.$type);
        if (!$definition || !app(BusinessProfileService::class)->moduleEnabled($tenant, $definition['capability'])) {
            throw ValidationException::withMessages(['customer' => 'This customer type is not available for this business.']);
        }
        $customer = $definition['model']::where('tenant_id', $tenant->id)->find($id);
        if (!$customer || (isset($definition['branch_column']) && (int) $customer->{$definition['branch_column']} !== $branchId)) {
            throw ValidationException::withMessages(['customer' => 'Select a customer belonging to this business and location.']);
        }
        return [$definition['column'] => $customer->id, 'customer_name' => $customer->full_name];
    }
}
