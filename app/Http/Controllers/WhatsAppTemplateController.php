<?php

namespace App\Http\Controllers;

use App\Models\{WhatsAppConnection, WhatsAppTemplate};
use App\Services\{MetaWhatsAppClient, MetaWhatsAppException, SystemSettingsService, WhatsAppConnectionService, WhatsAppTemplateService};
use Illuminate\Http\Request;

class WhatsAppTemplateController extends Controller
{
    public function index(Request $request, WhatsAppConnectionService $access, WhatsAppTemplateService $service)
    {
        $context = $access->authorize($request, 'whatsapp.view');
        $connection = WhatsAppConnection::where('tenant_id', $context['clinic']->id)->first();
        $templates = $connection ? WhatsAppTemplate::where('whatsapp_connection_id', $connection->id)->orderBy('name')->orderBy('language')->get() : collect();
        return response()->json(['data' => $templates->map(fn ($template) => $service->safe($template))->values()]);
    }

    public function sync(Request $request, WhatsAppConnectionService $access, WhatsAppTemplateService $service, MetaWhatsAppClient $client)
    {
        $context = $access->authorize($request, 'whatsapp.manage');
        abort_unless(app(SystemSettingsService::class)->get('notifications.whatsapp_enabled', false), 409, 'WhatsApp is disabled by the platform.');
        try { $count = $service->sync($context['clinic']->id, $request->user()->id, $client); }
        catch (MetaWhatsAppException $error) { return response()->json(['message' => 'Unable to synchronize Meta templates.', 'code' => $error->safeCode], $error->temporary ? 503 : 422); }
        return response()->json(['message' => 'Templates synchronized.', 'count' => $count]);
    }
}
