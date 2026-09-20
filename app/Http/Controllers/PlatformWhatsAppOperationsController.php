<?php

namespace App\Http\Controllers;

use App\Models\{PlatformWhatsAppConnection, PlatformWhatsAppMessage, PlatformWhatsAppTemplate};
use App\Services\{MetaWhatsAppClient, MetaWhatsAppException, PlatformService, PlatformWhatsAppService, SystemSettingsService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformWhatsAppOperationsController extends Controller
{
    public function connection(SystemSettingsService $settings)
    {
        $connection = PlatformWhatsAppConnection::first();
        $data = $connection ? [
            'status' => $connection->status, 'business_account_id' => $connection->business_account_id,
            'phone_number_id' => $connection->phone_number_id, 'display_phone_number' => $connection->display_phone_number,
            'has_access_token' => true,
        ] : ['status' => 'not_configured', 'has_access_token' => false];
        $data['enabled'] = (bool) $settings->get('notifications.whatsapp_enabled', false);
        $data['webhook_configured'] = (bool) $settings->get('notifications.whatsapp_verify_token');
        $data['signature_configured'] = (bool) $settings->get('notifications.whatsapp_secret');
        return response()->json(['data' => $data]);
    }

    public function saveConnection(Request $request)
    {
        $input = $request->validate([
            'business_account_id' => ['required', 'string', 'max:100'],
            'phone_number_id' => ['required', 'string', 'max:100'],
            'display_phone_number' => ['nullable', 'string', 'max:40'],
            'access_token' => ['nullable', 'string', 'max:4096'],
        ]);
        abort_if(DB::table('whatsapp_connections')->where('phone_number_id', $input['phone_number_id'])->exists(), 422, 'This number is assigned to a business.');
        $connection = PlatformWhatsAppConnection::firstOrNew(['singleton_key' => 'platform']);
        if (!$connection->exists && empty($input['access_token'])) abort(422, 'Access token is required.');
        foreach (['business_account_id', 'phone_number_id', 'display_phone_number'] as $field) $connection->$field = $input[$field] ?? null;
        if (!empty($input['access_token'])) $connection->access_token = $input['access_token'];
        $connection->status = 'configured';
        $connection->save();
        app(PlatformService::class)->audit($request->user()->id, 'whatsapp.platform.connection.saved', 'platform', $connection->id, ['connection_id' => $connection->id]);
        return $this->connection(app(SystemSettingsService::class));
    }

    public function templates(PlatformWhatsAppService $service)
    {
        $connection = PlatformWhatsAppConnection::first();
        $rows = $connection ? PlatformWhatsAppTemplate::where('platform_whatsapp_connection_id', $connection->id)->orderBy('name')->get() : collect();
        return response()->json(['data' => $rows->map(fn ($row) => $service->safeTemplate($row))->values()]);
    }

    public function sync(Request $request, PlatformWhatsAppService $service, MetaWhatsAppClient $client)
    {
        abort_unless(app(SystemSettingsService::class)->get('notifications.whatsapp_enabled', false), 409, 'WhatsApp is disabled by the platform.');
        try { $count = $service->sync($client); }
        catch (MetaWhatsAppException $error) { return response()->json(['message' => 'Unable to synchronize Meta templates.', 'code' => $error->safeCode], $error->temporary ? 503 : 422); }
        app(PlatformService::class)->audit($request->user()->id, 'whatsapp.platform.templates.synced', 'platform', 1, ['count' => $count]);
        return response()->json(['count' => $count]);
    }

    public function purpose(Request $request, int $id, PlatformWhatsAppService $service)
    {
        $input = $request->validate(['purpose' => ['required', Rule::in(PlatformWhatsAppService::PURPOSES)]]);
        $connection = $service->connection();
        $template = PlatformWhatsAppTemplate::where('platform_whatsapp_connection_id', $connection->id)->findOrFail($id);
        $template->update(['purpose' => $input['purpose']]);
        app(PlatformService::class)->audit($request->user()->id, 'whatsapp.platform.template.purpose', 'platform', 1, ['template_id' => $template->id, 'purpose' => $input['purpose']]);
        return response()->json(['data' => $service->safeTemplate($template)]);
    }

    public function messages(Request $request, PlatformWhatsAppService $service)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'sent', 'delivered', 'read', 'failed'])],
            'recipient' => ['nullable', 'string', 'max:30'], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $query = PlatformWhatsAppMessage::query();
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['recipient'])) $query->where('recipient', 'like', '%'.addcslashes($filters['recipient'], '%_\\').'%');
        if (!empty($filters['date_from'])) $query->whereDate('requested_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('requested_at', '<=', $filters['date_to']);
        $page = $query->orderByDesc('id')->paginate(20);
        $page->getCollection()->transform(fn ($row) => $service->safeMessage($row));
        return response()->json(['data' => $page]);
    }

    public function send(Request $request, PlatformWhatsAppService $service)
    {
        abort_unless(app(SystemSettingsService::class)->get('notifications.whatsapp_enabled', false), 409, 'WhatsApp is disabled by the platform.');
        $input = $request->validate([
            'recipient' => ['required', 'string', 'max:40'], 'template_id' => ['required', 'integer'],
            'parameters' => ['sometimes', 'array'], 'parameters.header' => ['sometimes', 'array'], 'parameters.body' => ['sometimes', 'array'],
            'parameters.header.*' => ['required', 'string', 'max:1000'], 'parameters.body.*' => ['required', 'string', 'max:1000'],
            'tenant_id' => ['prohibited'], 'connection_id' => ['prohibited'], 'access_token' => ['prohibited'],
            'status' => ['prohibited'], 'meta_message_id' => ['prohibited'],
        ]);
        foreach (array_keys($input['parameters'] ?? []) as $key) if (!in_array($key, ['header', 'body'], true)) abort(422, 'Unsupported template parameter.');
        $message = $service->send($input);
        app(PlatformService::class)->audit($request->user()->id, 'whatsapp.platform.message.requested', 'platform', 1, ['message_id' => $message->id, 'template_id' => $input['template_id']]);
        return response()->json(['data' => $service->safeMessage($message)], 202);
    }
}
