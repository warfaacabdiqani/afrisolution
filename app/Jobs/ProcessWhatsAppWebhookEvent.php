<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;

/** Foundation boundary: acknowledge and record verified provider events; no messaging side effects. */
class ProcessWhatsAppWebhookEvent implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $tenantId, public string $eventKey) {}

    public function handle(): void
    {
        DB::table('whatsapp_webhook_events')
            ->where('tenant_id', $this->tenantId)
            ->where('event_key', $this->eventKey)
            ->whereNull('processed_at')
            ->update(['processed_at' => now(), 'updated_at' => now()]);
    }
}
