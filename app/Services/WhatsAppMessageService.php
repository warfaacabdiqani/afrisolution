<?php

namespace App\Services;

use App\Jobs\SendWhatsAppTemplateMessage;
use App\Models\{WhatsAppMessage, WhatsAppTemplate};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppMessageService
{
    public function normalizeRecipient(string $input): string
    {
        $number = preg_replace('/[\s().-]/', '', trim($input));
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
            throw ValidationException::withMessages(['recipient' => 'Enter an international phone number beginning with + and country code.']);
        }
        return $number;
    }

    public function send(int $tenantId, ?int $branchId, int $actorId, array $input): WhatsAppMessage
    {
        $connection = app(WhatsAppTemplateService::class)->connection($tenantId);
        $template = WhatsAppTemplate::where('id', $input['template_id'])->where('whatsapp_connection_id', $connection->id)->first();
        if (!$template || !$template->sendable()) throw ValidationException::withMessages(['template_id' => 'Select an approved, supported template from this business.']);
        $schema = $template->parameterSchema();
        $parameters = $input['parameters'] ?? [];
        foreach (['header', 'body'] as $part) {
            $values = $parameters[$part] ?? [];
            if (!array_is_list($values) || count($values) !== $schema[$part]) throw ValidationException::withMessages(['parameters.'.$part => 'Provide exactly '.$schema[$part].' '.$part.' parameter(s).']);
        }
        $recipient = $this->normalizeRecipient($input['recipient']);
        return DB::transaction(function () use ($tenantId, $branchId, $actorId, $connection, $template, $parameters, $recipient) {
            $message = WhatsAppMessage::create([
                'branch_id' => $branchId,
                'whatsapp_connection_id' => $connection->id,
                'whatsapp_template_id' => $template->id,
                'direction' => 'outbound',
                'recipient' => $recipient,
                'template_name' => $template->name,
                'template_language' => $template->language,
                'parameters' => $parameters,
                'status' => 'queued',
                'requested_at' => now(),
            ]);
            app(PlatformService::class)->audit($actorId, 'whatsapp.message.requested', 'tenant', $tenantId, ['message_id' => $message->id, 'template_id' => $template->id]);
            SendWhatsAppTemplateMessage::dispatch($tenantId, $message->id)->afterCommit();
            return $message;
        });
    }

    public function safe(WhatsAppMessage $message): array
    {
        return [
            'id' => $message->id, 'recipient' => $message->recipient,
            'template_name' => $message->template_name, 'template_language' => $message->template_language,
            'direction' => $message->direction, 'status' => $message->status,
            'requested_at' => $message->requested_at, 'sent_at' => $message->sent_at,
            'delivered_at' => $message->delivered_at, 'read_at' => $message->read_at,
            'failed_at' => $message->failed_at, 'failure_code' => $message->failure_code,
        ];
    }
}
