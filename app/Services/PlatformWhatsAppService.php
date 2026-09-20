<?php

namespace App\Services;

use App\Jobs\SendPlatformWhatsAppTemplateMessage;
use App\Models\{PlatformWhatsAppConnection, PlatformWhatsAppMessage, PlatformWhatsAppTemplate};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformWhatsAppService
{
    public const PURPOSES = ['verification', 'welcome', 'security', 'trial', 'subscription', 'announcement', 'maintenance', 'general'];

    public function connection(): PlatformWhatsAppConnection
    {
        $connection = PlatformWhatsAppConnection::first();
        abort_unless($connection && $connection->status === 'configured', 409, 'Platform WhatsApp is not configured.');
        return $connection;
    }

    public function sync(MetaWhatsAppClient $client): int
    {
        $connection = $this->connection();
        $rows = $client->templates($connection);
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null) || !is_string($row['language'] ?? null)
                || !is_string($row['status'] ?? null) || !is_array($row['components'] ?? null)
                || strlen($row['name']) > 200 || strlen($row['language']) > 20) throw new MetaWhatsAppException('META_TEMPLATE_INVALID', false);
        }
        DB::transaction(function () use ($connection, $rows) {
            $seen = [];
            foreach ($rows as $row) {
                $template = PlatformWhatsAppTemplate::firstOrNew(['platform_whatsapp_connection_id' => $connection->id, 'name' => $row['name'], 'language' => $row['language']]);
                $template->fill([
                    'meta_template_id' => isset($row['id']) ? (string) $row['id'] : null,
                    'category' => isset($row['category']) ? substr((string) $row['category'], 0, 50) : null,
                    'status' => substr(strtoupper($row['status']), 0, 50), 'components' => $row['components'],
                    'is_available' => true, 'last_synced_at' => now(),
                ]);
                if (!$template->exists) $template->purpose = 'general';
                $template->save();
                $seen[] = $template->id;
            }
            PlatformWhatsAppTemplate::where('platform_whatsapp_connection_id', $connection->id)->whereNotIn('id', $seen)->update(['is_available' => false, 'updated_at' => now()]);
        });
        return count($rows);
    }

    public function safeTemplate(PlatformWhatsAppTemplate $template): array
    {
        return [
            'id' => $template->id, 'name' => $template->name, 'language' => $template->language,
            'category' => $template->category, 'purpose' => $template->purpose, 'status' => $template->status,
            'is_available' => $template->is_available, 'sendable' => $template->sendable(),
            'parameter_schema' => $template->parameterSchema(), 'last_synced_at' => $template->last_synced_at,
        ];
    }

    public function send(array $input): PlatformWhatsAppMessage
    {
        $connection = $this->connection();
        $template = PlatformWhatsAppTemplate::where('platform_whatsapp_connection_id', $connection->id)->find($input['template_id']);
        if (!$template || !$template->sendable()) throw ValidationException::withMessages(['template_id' => 'Select an approved platform template.']);
        $schema = $template->parameterSchema();
        $parameters = $input['parameters'] ?? [];
        foreach (['header', 'body'] as $part) {
            $values = $parameters[$part] ?? [];
            if (!array_is_list($values) || count($values) !== $schema[$part]) throw ValidationException::withMessages(['parameters.'.$part => 'Provide exactly '.$schema[$part].' '.$part.' parameter(s).']);
        }
        $recipient = app(WhatsAppMessageService::class)->normalizeRecipient($input['recipient']);
        return DB::transaction(function () use ($connection, $template, $parameters, $recipient) {
            $message = PlatformWhatsAppMessage::create([
                'platform_whatsapp_connection_id' => $connection->id, 'platform_whatsapp_template_id' => $template->id,
                'recipient' => $recipient, 'template_name' => $template->name, 'template_language' => $template->language,
                'purpose' => $template->purpose, 'parameters' => $parameters, 'status' => 'queued', 'requested_at' => now(),
            ]);
            SendPlatformWhatsAppTemplateMessage::dispatch($message->id)->afterCommit();
            return $message;
        });
    }

    public function safeMessage(PlatformWhatsAppMessage $message): array
    {
        return [
            'id' => $message->id, 'recipient' => $message->recipient, 'template_name' => $message->template_name,
            'template_language' => $message->template_language, 'purpose' => $message->purpose, 'status' => $message->status,
            'requested_at' => $message->requested_at, 'sent_at' => $message->sent_at,
            'delivered_at' => $message->delivered_at, 'read_at' => $message->read_at,
            'failed_at' => $message->failed_at, 'failure_code' => $message->failure_code,
        ];
    }
}
