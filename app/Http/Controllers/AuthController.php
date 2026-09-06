<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\SelectTenantRequest;
use App\Http\Resources\SessionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        if (! Auth::guard('web')->attempt($request->safe()->only(['email', 'password']))) {
            throw ValidationException::withMessages(['email' => 'The credentials provided are incorrect.']);
        }
        $request->session()->regenerate();
        $request->session()->forget('tenant_id');
        $ids = DB::table('tenant_memberships')->join('tenants', 'tenants.id', '=', 'tenant_memberships.tenant_id')
            ->where('user_id', $request->user()->id)->where('tenant_memberships.status', 'active')->where('tenants.status', 'active')->pluck('tenants.id');
        if ($ids->count() === 1) {
            $request->session()->put('tenant_id', (int) $ids->first());
        }

        return new SessionResource($request->user());
    }

    public function session(Request $request)
    {
        return new SessionResource($request->user());
    }

    public function select(SelectTenantRequest $request)
    {
        $id = $request->integer('clinic_id');
        abort_unless(DB::table('tenant_memberships')->join('tenants', 'tenants.id', '=', 'tenant_memberships.tenant_id')
            ->where('user_id', $request->user()->id)->where('tenant_memberships.tenant_id', $id)
            ->where('tenant_memberships.status', 'active')->where('tenants.status', 'active')->exists(), 403);
        $request->session()->put('tenant_id', $id);
        $request->session()->regenerate();

        return new SessionResource($request->user());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
