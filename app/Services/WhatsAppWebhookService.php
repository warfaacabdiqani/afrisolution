<?php

namespace App\Services;

use App\Jobs\ProcessWhatsAppWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookService
{
    public function intake(array $payload): void
    {
        foreach (is_array($payload['entry'] ?? null) ? $payload['entry'] : [] as $entry) {
            if (!is_array($entry)) continue;
            foreach (is_array($entry['changes'] ?? null) ? $entry['changes'] : [] as $change) {
                if (!is_array($change)) continue;
                $value = $change['value'] ?? [];
                if (!is_array($value)) continue;
                $phoneId = is_array($value['metadata'] ?? null) ? ($value['metadata']['phone_number_id'] ?? null) : null;
                if (!is_string($phoneId) || $phoneId === '') continue;
                $connection = DB::table('whatsapp_connections')->where('phone_number_id', $phoneId)->where('status', '!=', 'disabled')->first(['id', 'tenant_id']);
                if (!$connection) continue;
                $events = [];
                foreach (is_array($value['messages'] ?? null) ? $value['messages'] : [] as $message) {
                    if (is_array($message) && !empty($message['id']) && is_string($message['id'])) $events[] = ['incoming', $message['id'], '', null, null];
                }
                foreach (is_array($value['statuses'] ?? null) ? $value['statuses'] : [] as $status) {
                    if (!is_array($status) || !is_string($status['id'] ?? null) || !is_string($status['status'] ?? null)) continue;
                    $state = strtolower($status['status']);
                    if (!in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) continue;
                    $timestamp = filter_var($status['timestamp'] ?? null, FILTER_VALIDATE_INT);
                    $when = $timestamp && $timestamp >= 946684800 && $timestamp <= 4102444800 ? Carbon::createFromTimestampUTC($timestamp) : null;
                    $rawCode = (string) ($status['errors'][0]['code'] ?? 'META_FAILED');
                    $code = preg_match('/^[A-Za-z0-9_.-]{1,90}$/', $rawCode) ? $rawCode : 'META_FAILED';
                    $events[] = ['status', $status['id'], $state, $when, $state === 'failed' ? $code : null];
                }
                foreach ($events as [$type, $providerId, $state, $when, $failureCode]) {
                    $key = hash('sha256', implode('|', [$phoneId, $type, $providerId, $state]));
                    $created = DB::table('whatsapp_webhook_events')->insertOrIgnore([
                        'tenant_id' => $connection->tenant_id,
                        'whatsapp_connection_id' => $connection->id,
                        'event_key' => $key,
                        'event_type' => $type,
                        'provider_event_id' => substr($providerId, 0, 200),
                        'event_status' => $state ?: null,
                        'provider_timestamp' => $when,
                        'failure_code' => $failureCode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($created) ProcessWhatsAppWebhookEvent::dispatch($connection->tenant_id, $key)->afterCommit();
                }
            }
        }
    }
}
