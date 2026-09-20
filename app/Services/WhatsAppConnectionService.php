<?php

namespace App\Services;

use App\Models\WhatsAppConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppConnectionService
{
    public function authorize(Request $request, string $permission): array
    {
        $access = app(ClinicAccessService::class);
        $context = $access->context($request);
        if (!$context['operational']) $access->deny($context['restriction_code'], $context['restriction']);
        $access->authorizeBusiness($context, 'settings');
        if ($request->hasHeader('X-Branch-Context')) abort_unless((string) $context['branch']->id === $request->header('X-Branch-Context'), 409);
        if (empty($context['features']['whatsapp_notifications'])) $access->deny('PLAN_FEATURE_UNAVAILABLE', 'Your current plan does not include WhatsApp.');
        if (!$access->can($context['permissions'], $permission)) $access->deny('PERMISSION_DENIED', 'You do not have permission for WhatsApp settings.');
        return $context;
    }

    public function safe(?WhatsAppConnection $connection): array
    {
        return $connection ? [
            'status' => $connection->status,
            'business_account_id' => $connection->business_account_id,
            'phone_number_id' => $connection->phone_number_id,
            'display_phone_number' => $connection->display_phone_number,
            'display_name' => $connection->display_name,
            'has_access_token' => true,
            'last_checked_at' => $connection->last_checked_at,
            'last_error_code' => $connection->last_error_code,
        ] : ['status' => 'not_configured', 'has_access_token' => false];
    }

    public function save(int $tenantId, int $actorId, array $values): WhatsAppConnection
    {
        abort_if(DB::table('platform_whatsapp_connections')->where('phone_number_id', $values['phone_number_id'])->exists(), 422, 'This number is assigned to the platform.');
        return DB::transaction(function () use ($tenantId, $actorId, $values) {
            $connection = WhatsAppConnection::where('tenant_id', $tenantId)->lockForUpdate()->first();
            $created = !$connection;
            $connection ??= new WhatsAppConnection();
            foreach (['business_account_id', 'phone_number_id', 'display_phone_number', 'display_name'] as $key) $connection->$key = $values[$key] ?? null;
            if (!empty($values['access_token'])) $connection->access_token = $values['access_token'];
            $connection->status = 'configured';
            $connection->last_error_code = null;
            $connection->save();
            app(PlatformService::class)->audit($actorId, $created ? 'whatsapp.connection.configured' : 'whatsapp.connection.updated', 'tenant', $tenantId, ['connection_id' => $connection->id]);
            return $connection;
        });
    }
}
