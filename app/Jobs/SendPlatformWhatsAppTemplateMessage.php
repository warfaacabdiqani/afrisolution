<?php

namespace App\Jobs;

use App\Models\{PlatformWhatsAppConnection, PlatformWhatsAppMessage, PlatformWhatsAppTemplate};
use App\Services\{MetaWhatsAppClient, MetaWhatsAppException, SystemSettingsService, WhatsAppStatusService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendPlatformWhatsAppTemplateMessage implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 4;
    public int $timeout = 30;

    public function __construct(public int $messageId) {}

    public function backoff(): array { return [60, 300, 900]; }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('platform-whatsapp-message-'.$this->messageId))->releaseAfter(10)->expireAfter(45)];
    }

    public function handle(MetaWhatsAppClient $client): void
    {
        $message = PlatformWhatsAppMessage::find($this->messageId);
        if (!$message || $message->status !== 'queued' || $message->meta_message_id) return;
        $connection = PlatformWhatsAppConnection::find($message->platform_whatsapp_connection_id);
        $template = PlatformWhatsAppTemplate::find($message->platform_whatsapp_template_id);
        if (!$connection || $connection->status !== 'configured') { $this->markFailed('CONNECTION_UNAVAILABLE'); return; }
        if (!$template || !$template->sendable()) { $this->markFailed('TEMPLATE_UNAVAILABLE'); return; }
        if (!app(SystemSettingsService::class)->get('notifications.whatsapp_enabled', false)) { $this->markFailed('PLATFORM_DISABLED'); return; }
        try {
            $providerId = $client->sendTemplate($connection, $template, $message->recipient, $message->parameters ?? []);
            $message->meta_message_id = $providerId;
            $message->parameters = null;
            $message->failure_code = null;
            $message->save();
            app(WhatsAppStatusService::class)->applyPlatformPending($connection->id, $providerId);
        } catch (MetaWhatsAppException $error) {
            if ($error->temporary) {
                $message->failure_code = $error->safeCode;
                $message->save();
                throw $error;
            }
            $this->markFailed($error->safeCode);
        }
    }

    private function markFailed(string $code): void
    {
        PlatformWhatsAppMessage::whereKey($this->messageId)->where('status', 'queued')->update([
            'status' => 'failed', 'failure_code' => $code, 'failed_at' => now(), 'parameters' => null,
        ]);
    }

    public function failed(?Throwable $error): void
    {
        $this->markFailed($error instanceof MetaWhatsAppException ? $error->safeCode : 'DELIVERY_FAILED');
    }
}
