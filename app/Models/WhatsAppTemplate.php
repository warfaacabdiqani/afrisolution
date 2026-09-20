<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_templates';
    protected $guarded = ['id', 'tenant_id'];
    protected $casts = ['components' => 'array', 'is_available' => 'boolean', 'last_synced_at' => 'datetime'];

    public function parameterSchema(): ?array
    {
        $schema = ['header' => 0, 'body' => 0];
        foreach ($this->components ?? [] as $component) {
            $type = strtoupper($component['type'] ?? '');
            if ($type === 'FOOTER') continue;
            if (!in_array($type, ['HEADER', 'BODY'], true)) return null;
            if ($type === 'HEADER' && strtoupper($component['format'] ?? 'TEXT') !== 'TEXT') return null;
            $matches = [];
            preg_match_all('/\{\{\s*([1-9][0-9]*)\s*\}\}/', $component['text'] ?? '', $matches);
            $numbers = array_values(array_unique(array_map('intval', $matches[1])));
            sort($numbers);
            if ($numbers !== range(1, count($numbers))) {
                if ($numbers !== []) return null;
            }
            $schema[strtolower($type)] = count($numbers);
        }
        return $schema;
    }

    public function sendable(): bool
    {
        return $this->is_available && strtoupper($this->status) === 'APPROVED' && $this->parameterSchema() !== null;
    }
}
