<?php

namespace App\Jobs;

use App\Services\WhatsAppStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessPlatformWhatsAppWebhookEvent implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $eventKey) {}

    public function handle(WhatsAppStatusService $statuses): void
    {
        $statuses->applyPlatformEvent($this->eventKey);
    }
}
