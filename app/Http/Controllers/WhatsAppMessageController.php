<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use App\Services\{SystemSettingsService, WhatsAppConnectionService, WhatsAppMessageService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsAppMessageController extends Controller
{
    public function index(Request $request, WhatsAppConnectionService $access, WhatsAppMessageService $service)
    {
        $context = $access->authorize($request, 'whatsapp.view');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'sent', 'delivered', 'read', 'failed'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'],
            'recipient' => ['nullable', 'string', 'max:30'],
        ]);
        $query = WhatsAppMessage::where('tenant_id', $context['clinic']->id)->whereIn('branch_id', $context['branches']->pluck('id'));
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['date_from'])) $query->whereDate('requested_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('requested_at', '<=', $filters['date_to']);
        if (!empty($filters['recipient'])) $query->where('recipient', 'like', '%'.addcslashes($filters['recipient'], '%_\\').'%');
        $page = $query->orderByDesc('id')->paginate(20);
        $page->getCollection()->transform(fn ($message) => $service->safe($message));
        return response()->json(['data' => $page]);
    }

    public function show(Request $request, int $id, WhatsAppConnectionService $access, WhatsAppMessageService $service)
    {
        $context = $access->authorize($request, 'whatsapp.view');
        $message = WhatsAppMessage::where('tenant_id', $context['clinic']->id)->whereIn('branch_id', $context['branches']->pluck('id'))->findOrFail($id);
        return response()->json(['data' => $service->safe($message)]);
    }

    public function store(Request $request, WhatsAppConnectionService $access, WhatsAppMessageService $service)
    {
        $context = $access->authorize($request, 'whatsapp.send');
        abort_unless(app(SystemSettingsService::class)->get('notifications.whatsapp_enabled', false), 409, 'WhatsApp is disabled by the platform.');
        $input = $request->validate([
            'recipient' => ['required', 'string', 'max:40'], 'template_id' => ['required', 'integer'],
            'parameters' => ['sometimes', 'array'], 'parameters.header' => ['sometimes', 'array'], 'parameters.body' => ['sometimes', 'array'],
            'parameters.header.*' => ['required', 'string', 'max:1000'], 'parameters.body.*' => ['required', 'string', 'max:1000'],
            'tenant_id' => ['prohibited'], 'connection_id' => ['prohibited'], 'access_token' => ['prohibited'],
            'branch_id' => ['prohibited'], 'status' => ['prohibited'], 'meta_message_id' => ['prohibited'],
        ]);
        foreach (array_keys($input['parameters'] ?? []) as $key) if (!in_array($key, ['header', 'body'], true)) abort(422, 'Unsupported template parameter.');
        $message = $service->send($context['clinic']->id, $context['branch']->id, $request->user()->id, $input);
        return response()->json(['data' => $service->safe($message)], 202);
    }
}
