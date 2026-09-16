<?php

namespace App\Services\Billing;

interface InvoiceSource
{
    /** Resolve a tenant-scoped source and immutable charges; never use HTTP prices/totals. */
    public function snapshot(array $context, int $id): array;
}
