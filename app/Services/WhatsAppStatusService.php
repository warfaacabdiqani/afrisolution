<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WhatsAppStatusService
{
    private const RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    public function updates(object $message, string $status, mixed $timestamp, ?string $failureCode): array
    {
        $updates = ['updated_at' => now()];
        if (isset(self::RANK[$status])) {
            $column = $status.'_at';
            if (!$message->$column) $updates[$column] = $timestamp;
            if ($message->status !== 'failed' && self::RANK[$status] > (self::RANK[$message->status] ?? 0)) $updates['status'] = $status;
        } elseif ($status === 'failed' && in_array($message->status, ['queued', 'sent'], true)) {
            $updates['status'] = 'failed';
            $updates['failed_at'] = $timestamp;
            $updates['failure_code'] = $failureCode ?: 'META_FAILED';
        }
        return $updates;
    }

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
            $updates = $this->updates($message, $status, $timestamp, $event->failure_code);
            DB::table('whatsapp_messages')->where('id', $message->id)->update($updates);
            DB::table('whatsapp_webhook_events')->where('id', $event->id)->update(['processed_at' => now(), 'updated_at' => now()]);
        });
    }

    public function applyPlatformPending(int $connectionId, string $metaMessageId): void
    {
        $events = DB::table('platform_whatsapp_webhook_events')->where('platform_whatsapp_connection_id', $connectionId)
            ->where('provider_event_id', $metaMessageId)->whereNull('processed_at')->pluck('event_key');
        foreach ($events as $key) $this->applyPlatformEvent($key);
    }

    public function applyPlatformEvent(string $eventKey): void
    {
        DB::transaction(function () use ($eventKey) {
            $event = DB::table('platform_whatsapp_webhook_events')->where('event_key', $eventKey)->lockForUpdate()->first();
            if (!$event || $event->processed_at) return;
            $message = DB::table('platform_whatsapp_messages')->where('platform_whatsapp_connection_id', $event->platform_whatsapp_connection_id)
                ->where('meta_message_id', $event->provider_event_id)->lockForUpdate()->first();
            if (!$message) return;
            DB::table('platform_whatsapp_messages')->where('id', $message->id)->update($this->updates($message, $event->event_status, $event->provider_timestamp ?? now(), $event->failure_code));
            DB::table('platform_whatsapp_webhook_events')->where('id', $event->id)->update(['processed_at' => now(), 'updated_at' => now()]);
        });
    }
}
