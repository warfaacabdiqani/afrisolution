<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WhatsAppStatusService
{
    private const RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    public function applyPending(int $tenantId, int $connectionId, string $metaMessageId): void
    {
        $events = DB::table('whatsapp_webhook_events')
            ->where('tenant_id', $tenantId)->where('whatsapp_connection_id', $connectionId)
            ->where('event_type', 'status')->where('provider_event_id', $metaMessageId)
            ->whereNull('processed_at')->orderBy('id')->pluck('event_key');
        foreach ($events as $key) $this->applyEvent($tenantId, $key);
    }

    public function applyEvent(int $tenantId, string $eventKey): void
    {
        DB::transaction(function () use ($tenantId, $eventKey) {
            $event = DB::table('whatsapp_webhook_events')->where('tenant_id', $tenantId)->where('event_key', $eventKey)->lockForUpdate()->first();
            if (!$event || $event->processed_at) return;
            if ($event->event_type !== 'status' || !isset(self::RANK[$event->event_status]) && $event->event_status !== 'failed') {
                DB::table('whatsapp_webhook_events')->where('id', $event->id)->update(['processed_at' => now(), 'updated_at' => now()]);
                return;
            }
            $message = DB::table('whatsapp_messages')
                ->where('tenant_id', $tenantId)->where('whatsapp_connection_id', $event->whatsapp_connection_id)
                ->where('meta_message_id', $event->provider_event_id)->lockForUpdate()->first();
            if (!$message) return; // The send job can still be recording Meta's returned ID.
            $status = $event->event_status;
            $timestamp = $event->provider_timestamp ?? now();
            $updates = ['updated_at' => now()];
            if (isset(self::RANK[$status])) {
                $column = $status.'_at';
                if (!$message->$column) $updates[$column] = $timestamp;
                if ($message->status !== 'failed' && self::RANK[$status] > (self::RANK[$message->status] ?? 0)) $updates['status'] = $status;
            } elseif ($status === 'failed' && in_array($message->status, ['queued', 'sent'], true)) {
                $updates['status'] = 'failed';
                $updates['failed_at'] = $timestamp;
                $updates['failure_code'] = $event->failure_code ?: 'META_FAILED';
            }
            DB::table('whatsapp_messages')->where('id', $message->id)->update($updates);
            DB::table('whatsapp_webhook_events')->where('id', $event->id)->update(['processed_at' => now(), 'updated_at' => now()]);
        });
    }
}
