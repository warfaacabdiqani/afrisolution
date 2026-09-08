<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Integration event for queued reminders/notifications. No delivery provider is assumed. */
class AppointmentChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $tenantId, public int $appointmentId, public string $action) {}
}
