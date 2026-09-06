<?php

namespace App\Tenancy;

final class TenantContext
{
    private ?int $id = null;

    public function set(int $id): void
    {
        $this->id = $id;
    }

    public function clear(): void
    {
        $this->id = null;
    }

    public function id(): int
    {
        abort_unless($this->id !== null, 403, 'An active clinic is required.');

        return $this->id;
    }
}
