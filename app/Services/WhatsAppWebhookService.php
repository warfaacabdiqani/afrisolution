<?php

namespace App\Services;

use App\Jobs\ProcessWhatsAppWebhookEvent;
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
                    if (is_array($message) && !empty($message['id']) && is_string($message['id'])) $events[] = ['incoming', $message['id'], ''];
                }
                foreach (is_array($value['statuses'] ?? null) ? $value['statuses'] : [] as $status) {
                    if (is_array($status) && !empty($status['id']) && is_string($status['id']) && !empty($status['status']) && is_string($status['status'])) $events[] = ['status', $status['id'], $status['status']];
                }
                foreach ($events as [$type, $providerId, $state]) {
                    $key = hash('sha256', implode('|', [$phoneId, $type, $providerId, $state]));
                    $created = DB::table('whatsapp_webhook_events')->insertOrIgnore([
                        'tenant_id' => $connection->tenant_id,
                        'whatsapp_connection_id' => $connection->id,
                        'event_key' => $key,
                        'event_type' => $type,
                        'provider_event_id' => substr($providerId, 0, 200),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($created) ProcessWhatsAppWebhookEvent::dispatch($connection->tenant_id, $key)->afterCommit();
                }
            }
        }
    }
}
