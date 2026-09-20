<?php

namespace App\Services;

use App\Models\{WhatsAppConnection, WhatsAppTemplate};
use Illuminate\Support\Facades\DB;

class WhatsAppTemplateService
{
    public function connection(int $tenantId): WhatsAppConnection
    {
        $connection = WhatsAppConnection::where('tenant_id', $tenantId)->first();
        abort_unless($connection && in_array($connection->status, ['configured', 'connected'], true), 409, 'Connect WhatsApp before synchronizing templates or sending messages.');
        return $connection;
    }

    public function sync(int $tenantId, int $actorId, MetaWhatsAppClient $client): int
    {
        $connection = $this->connection($tenantId);
        app(PlatformService::class)->audit($actorId, 'whatsapp.templates.sync_requested', 'tenant', $tenantId, ['connection_id' => $connection->id]);
        $rows = $client->templates($connection);
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null) || !is_string($row['language'] ?? null)
                || !is_string($row['status'] ?? null) || !is_array($row['components'] ?? null)
                || strlen($row['name']) > 200 || strlen($row['language']) > 20) {
                throw new MetaWhatsAppException('META_TEMPLATE_INVALID', false);
            }
        }
        DB::transaction(function () use ($rows, $tenantId, $connection, $actorId, &$seen) {
            foreach ($rows as $row) {
                $template = WhatsAppTemplate::firstOrNew(['whatsapp_connection_id' => $connection->id, 'name' => $row['name'], 'language' => $row['language']]);
                $template->fill([
                    'meta_template_id' => isset($row['id']) ? (string) $row['id'] : null,
                    'category' => isset($row['category']) ? substr((string) $row['category'], 0, 50) : null,
                    'status' => substr(strtoupper($row['status']), 0, 50),
                    'components' => $row['components'],
                    'is_available' => true,
                    'last_synced_at' => now(),
                ]);
                $template->save();
                $seen[] = $template->id;
            }
            WhatsAppTemplate::where('whatsapp_connection_id', $connection->id)->whereNotIn('id', $seen)->update(['is_available' => false, 'updated_at' => now()]);
            app(PlatformService::class)->audit($actorId, 'whatsapp.templates.synced', 'tenant', $tenantId, ['connection_id' => $connection->id, 'count' => count($seen)]);
        });
        return count($seen);
    }

    public function safe(WhatsAppTemplate $template): array
    {
        return [
            'id' => $template->id, 'name' => $template->name, 'language' => $template->language,
            'category' => $template->category, 'status' => $template->status,
            'is_available' => $template->is_available, 'sendable' => $template->sendable(),
            'parameter_schema' => $template->parameterSchema(), 'last_synced_at' => $template->last_synced_at,
        ];
    }
}
