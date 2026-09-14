<?php

namespace App\Http\Controllers;

use App\Services\ClinicAccessService;
use App\Services\PlatformService;
use Illuminate\Http\Request;

class ClinicDashboardController extends Controller
{
    public function context(Request $request, ClinicAccessService $access)
    {
        return response()->json(['data' => $access->context($request)]);
    }

    public function switchBranch(Request $request, ClinicAccessService $access)
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'tenant_id' => ['prohibited']]);
        $context = $access->context($request);
        abort_unless($context['operational'], 403, $context['restriction']);
        abort_unless($context['branches']->contains('id', $data['branch_id']), 403, 'You cannot access this branch.');
        $old = $context['branch']->id;
        $request->session()->put('branch_id', (int) $data['branch_id']);
        if ($old !== (int) $data['branch_id']) app(PlatformService::class)->audit($request->user()->id, 'branch.switched', 'tenant', $context['clinic']->id, ['branch_id' => (int) $data['branch_id'], 'previous_branch_id' => $old]);
        return $this->context($request, $access);
    }

    public function dashboard(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'dashboard');
        return response()->json(['data' => app(\App\Services\DashboardProfileService::class)->resolve($request, $context)]);
    }

    public function module(Request $request, ClinicAccessService $access, string $module)
    {
        $action = $request->query('action');
        abort_unless(in_array($action, [null, 'create'], true), 422);
        $permission = $action === 'create' ? ($module === 'billing' ? 'billing.create' : $module.'.create') : null;
        $access->authorize($request, $module, $permission);
        if (in_array($module, ['patients', 'doctors', 'appointments', 'consultations', 'billing', 'staff'], true)) return response()->json(['data' => ['available' => true]]);
        return response()->json(['data' => ['available' => false, 'message' => 'This module is scheduled for a later implementation phase.']]);
    }
}
