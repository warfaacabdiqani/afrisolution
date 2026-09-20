<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConnection;
use App\Services\{WhatsAppConnectionService, PlatformService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WhatsAppConnectionController extends Controller
{
    public function show(Request $request, WhatsAppConnectionService $service)
    {
        $context = $service->authorize($request, 'whatsapp.view');
        $connection = WhatsAppConnection::where('tenant_id', $context['clinic']->id)->first();
        return response()->json(['data' => $service->safe($connection) + [
            'notifications_enabled' => (bool) app(\App\Services\ClinicSettingsService::class)->get($context['clinic']->id, 'notifications.whatsapp_enabled', false),
            'can_manage' => app(\App\Services\ClinicAccessService::class)->can($context['permissions'], 'whatsapp.manage'),
        ]]);
    }

    public function update(Request $request, WhatsAppConnectionService $service)
    {
        $context = $service->authorize($request, 'whatsapp.manage');
        $existing = WhatsAppConnection::where('tenant_id', $context['clinic']->id)->first();
        $values = $request->validate([
            'business_account_id' => ['required', 'string', 'max:100', 'regex:/^[0-9]+$/'],
            'phone_number_id' => ['required', 'string', 'max:100', 'regex:/^[0-9]+$/', Rule::unique('whatsapp_connections')->ignore($existing?->id)],
            'display_phone_number' => ['nullable', 'string', 'max:40'],
            'display_name' => ['nullable', 'string', 'max:150'],
            'access_token' => [$existing ? 'nullable' : 'required', 'string', 'min:10', 'max:4096'],
        ]);
        $connection = $service->save($context['clinic']->id, $request->user()->id, $values);
        return response()->json(['data' => $service->safe($connection)]);
    }

    public function disable(Request $request, WhatsAppConnectionService $service)
    {
        $context = $service->authorize($request, 'whatsapp.manage');
        $connection = WhatsAppConnection::where('tenant_id', $context['clinic']->id)->firstOrFail();
        DB::transaction(function () use ($connection, $request, $context) {
            $connection->update(['status' => 'disabled']);
            app(PlatformService::class)->audit($request->user()->id, 'whatsapp.connection.disabled', 'tenant', $context['clinic']->id, ['connection_id' => $connection->id]);
        });
        return response()->json(['data' => $service->safe($connection)]);
    }
}
