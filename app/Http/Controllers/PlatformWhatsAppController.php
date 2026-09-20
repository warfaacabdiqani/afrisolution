<?php

namespace App\Http\Controllers;

use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformWhatsAppController extends Controller
{
    public function index(Request $request, SystemSettingsService $settings)
    {
        abort_unless($request->user()?->hasPlatformPermission('settings.view'), 403);
        $connections = DB::table('tenants as t')
            ->leftJoin('whatsapp_connections as wc', 'wc.tenant_id', '=', 't.id')
            ->leftJoin('business_types as bt', 'bt.id', '=', 't.business_type_id')
            ->orderBy('t.name')
            ->select(['t.id as tenant_id', 't.name as business_name', 'bt.name as business_type', 'wc.display_phone_number', 'wc.phone_number_id', 'wc.last_checked_at', 'wc.last_error_code'])
            ->selectRaw("COALESCE(wc.status, 'not_configured') as status")
            ->paginate(50);
        return response()->json(['data' => $connections, 'platform' => [
            'enabled' => (bool) $settings->get('notifications.whatsapp_enabled', false),
            'webhook_configured' => (bool) $settings->get('notifications.whatsapp_verify_token'),
            'signature_configured' => (bool) $settings->get('notifications.whatsapp_secret'),
        ]]);
    }
}
