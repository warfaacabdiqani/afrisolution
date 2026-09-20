<?php

namespace App\Jobs;

use App\Models\{Plan, WhatsAppConnection, WhatsAppMessage, WhatsAppTemplate};
use App\Services\{MetaWhatsAppClient, MetaWhatsAppException, SystemSettingsService, WhatsAppStatusService};
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendWhatsAppTemplateMessage implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 4;
    public int $timeout = 30;

    public function __construct(public int $tenantId, public int $messageId) {}

    public function backoff(): array { return [60, 300, 900]; }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('whatsapp-message-'.$this->tenantId.'-'.$this->messageId))->releaseAfter(10)->expireAfter(45)];
    }

    public function handle(MetaWhatsAppClient $client): void
    {
        $scope = app(TenantContext::class);
        $scope->set($this->tenantId);
        try {
            $message = WhatsAppMessage::find($this->messageId);
            if (!$message || $message->status !== 'queued' || $message->meta_message_id) return;
            $connection = WhatsAppConnection::find($message->whatsapp_connection_id);
            $template = WhatsAppTemplate::find($message->whatsapp_template_id);
            $subscription = DB::table('subscriptions')->where('tenant_id', $this->tenantId)->first();
            $plan = $subscription ? Plan::find($subscription->plan_id) : null;
            $active = $subscription && ($subscription->status === 'active' || ($subscription->status === 'trial' && $subscription->trial_ends_at && now()->lt($subscription->trial_ends_at)));
            if (!$connection || !in_array($connection->status, ['configured', 'connected'], true)) { $this->markFailed('CONNECTION_UNAVAILABLE'); return; }
            if (!$template || !$template->sendable()) { $this->markFailed('TEMPLATE_UNAVAILABLE'); return; }
            if (!$active || empty($plan?->features['whatsapp_notifications'])) { $this->markFailed('PLAN_UNAVAILABLE'); return; }
            if (!app(SystemSettingsService::class)->get('notifications.whatsapp_enabled', false)) { $this->markFailed('PLATFORM_DISABLED'); return; }
            try {
                $providerId = $client->sendTemplate($connection, $template, $message->recipient, $message->parameters ?? []);
                $message->meta_message_id = $providerId;
                $message->parameters = null;
                $message->failure_code = null;
                $message->save();
                app(WhatsAppStatusService::class)->applyPending($this->tenantId, $connection->id, $providerId);
            } catch (MetaWhatsAppException $error) {
                if ($error->temporary) {
                    $message->failure_code = $error->safeCode;
                    $message->save();
                    throw $error;
                }
                $this->markFailed($error->safeCode);
            }
        } finally {
            $scope->clear();
        }
    }

    private function markFailed(string $code): void
    {
        WhatsAppMessage::whereKey($this->messageId)->where('status', 'queued')->update([
            'status' => 'failed', 'failure_code' => $code, 'failed_at' => now(), 'parameters' => null,
        ]);
    }

    public function failed(?Throwable $error): void
    {
        $scope = app(TenantContext::class);
        $scope->set($this->tenantId);
        try { $this->markFailed($error instanceof MetaWhatsAppException ? $error->safeCode : 'DELIVERY_FAILED'); }
        finally { $scope->clear(); }
    }
}
