<?php

namespace App\Http\Controllers;

use App\Services\{SystemSettingsService, WhatsAppWebhookService};
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, SystemSettingsService $settings)
    {
        $token = $settings->get('notifications.whatsapp_verify_token');
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $provided = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        abort_unless($token && $mode === 'subscribe'
            && is_string($provided)
            && hash_equals($token, $provided), 403);
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));
        abort_unless(is_string($challenge) && preg_match('/^[A-Za-z0-9_-]{1,256}$/', $challenge), 403);
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, SystemSettingsService $settings, WhatsAppWebhookService $webhooks)
    {
        $secret = $settings->get('notifications.whatsapp_secret');
        $signature = $request->header('X-Hub-Signature-256', '');
        $raw = $request->getContent();
        abort_unless($secret && is_string($signature) && strlen($raw) <= 262144
            && hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $signature), 403);
        $payload = json_decode($raw, true);
        abort_unless(is_array($payload), 400);
        $webhooks->intake($payload);
        return response()->json(['received' => true]);
    }
}
