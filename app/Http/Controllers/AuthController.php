<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\SelectTenantRequest;
use App\Http\Resources\SessionResource;
use App\Services\SessionService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, SessionService $service)
    {
        $service->login($request, $request->safe()->only(['email', 'password']));

        return new SessionResource($request->user());
    }

    public function session(Request $request)
    {
        return new SessionResource($request->user());
    }

    public function select(SelectTenantRequest $request, SessionService $service)
    {
        $service->select($request, $request->integer('clinic_id'));

        return new SessionResource($request->user());
    }

    public function logout(Request $request, SessionService $service)
    {
        $service->logout($request);

        return response()->noContent();
    }
}
