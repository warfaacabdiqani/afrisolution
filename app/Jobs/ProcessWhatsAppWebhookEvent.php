<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use App\Services\WhatsAppStatusService;

/** Processes metadata from an already signature-verified provider event. */
class ProcessWhatsAppWebhookEvent implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $tenantId, public string $eventKey) {}

    public function handle(WhatsAppStatusService $statuses): void
    {
        $statuses->applyEvent($this->tenantId, $this->eventKey);
    }
}
