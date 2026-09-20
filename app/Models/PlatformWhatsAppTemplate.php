<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformWhatsAppTemplate extends Model
{
    protected $table = 'platform_whatsapp_templates';
    protected $guarded = ['id'];
    protected $casts = ['components' => 'array', 'is_available' => 'boolean', 'last_synced_at' => 'datetime'];

    public function parameterSchema(): ?array
    {
        return app(\App\Services\WhatsAppTemplateSchema::class)->schema($this->components ?? []);
    }

    public function sendable(): bool
    {
        return $this->is_available && strtoupper($this->status) === 'APPROVED' && $this->parameterSchema() !== null;
    }
}
