<?php

namespace App\Services;

use App\Models\{WhatsAppConnection, WhatsAppTemplate};
use Illuminate\Http\Client\{ConnectionException, Response};
use Illuminate\Support\Facades\Http;

class MetaWhatsAppClient
{
    private function baseUrl(): string
    {
        $version = (string) config('services.whatsapp.graph_version');
        if (!preg_match('/^v[0-9]+\.[0-9]+$/', $version)) throw new MetaWhatsAppException('GRAPH_VERSION_INVALID', false);
        return 'https://graph.facebook.com/'.$version;
    }

    private function request(WhatsAppConnection $connection)
    {
        return Http::baseUrl($this->baseUrl())->withToken($connection->access_token)->acceptJson()->timeout(15);
    }

    private function checked(Response $response): array
    {
        if ($response->successful()) {
            $data = $response->json();
            if (is_array($data)) return $data;
            throw new MetaWhatsAppException('META_RESPONSE_INVALID', false);
        }
        $raw = (string) ($response->json('error.code') ?? 'HTTP_'.$response->status());
        $code = preg_match('/^[A-Za-z0-9_.-]{1,90}$/', $raw) ? $raw : 'HTTP_'.$response->status();
        throw new MetaWhatsAppException($code, $response->status() === 429 || $response->serverError());
    }

    public function templates(WhatsAppConnection $connection): array
    {
        $rows = []; $cursor = null;
        try {
            for ($page = 0; $page < 20; $page++) {
                $query = ['fields' => 'id,name,language,category,status,components', 'limit' => 100];
                if ($cursor) $query['after'] = $cursor;
                $data = $this->checked($this->request($connection)->get('/'.$connection->business_account_id.'/message_templates', $query));
                if (!isset($data['data']) || !is_array($data['data'])) throw new MetaWhatsAppException('META_RESPONSE_INVALID', false);
                array_push($rows, ...$data['data']);
                $next = $data['paging']['cursors']['after'] ?? null;
                if (empty($data['paging']['next'])) return $rows;
                if (!is_string($next) || $next === $cursor) throw new MetaWhatsAppException('META_PAGINATION_INVALID', false);
                $cursor = $next;
            }
        } catch (ConnectionException) {
            throw new MetaWhatsAppException('NETWORK_ERROR', true);
        }
        throw new MetaWhatsAppException('META_PAGE_LIMIT', true);
    }

    public function sendTemplate(WhatsAppConnection $connection, WhatsAppTemplate $template, string $recipient, array $parameters): string
    {
        $components = [];
        foreach (['header', 'body'] as $kind) {
            if (!empty($parameters[$kind])) $components[] = ['type' => $kind, 'parameters' => array_map(fn ($text) => ['type' => 'text', 'text' => $text], $parameters[$kind])];
        }
        $payload = [
            'messaging_product' => 'whatsapp', 'to' => ltrim($recipient, '+'), 'type' => 'template',
            'template' => ['name' => $template->name, 'language' => ['code' => $template->language]],
        ];
        if ($components) $payload['template']['components'] = $components;
        try {
            $data = $this->checked($this->request($connection)->post('/'.$connection->phone_number_id.'/messages', $payload));
        } catch (ConnectionException) {
            throw new MetaWhatsAppException('NETWORK_ERROR', true);
        }
        $id = $data['messages'][0]['id'] ?? null;
        if (!is_string($id) || $id === '' || strlen($id) > 200) throw new MetaWhatsAppException('META_RESPONSE_INVALID', false);
        return $id;
    }
}
