<?php

namespace Tests\Feature;

class ClinicBillingConcurrencyTest extends SharedBillingConcurrencyTest
{
    protected function worker(): string { return 'tests/Support/clinic-billing-worker.php'; }
    protected function expectedItems(): int { return 1; }
}
