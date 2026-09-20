<?php

namespace App\Services;

class WhatsAppTemplateSchema
{
    public function schema(array $components): ?array
    {
        $schema = ['header' => 0, 'body' => 0];
        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');
            if ($type === 'FOOTER') continue;
            if (!in_array($type, ['HEADER', 'BODY'], true)) return null;
            if ($type === 'HEADER' && strtoupper($component['format'] ?? 'TEXT') !== 'TEXT') return null;
            $matches = [];
            preg_match_all('/\{\{\s*([1-9][0-9]*)\s*\}\}/', $component['text'] ?? '', $matches);
            $numbers = array_values(array_unique(array_map('intval', $matches[1])));
            sort($numbers);
            if ($numbers !== [] && $numbers !== range(1, count($numbers))) return null;
            $schema[strtolower($type)] = count($numbers);
        }
        return $schema;
    }
}
