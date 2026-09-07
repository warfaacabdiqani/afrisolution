<?php
namespace App\Http\Controllers;
use App\Http\Requests\PlatformRoleRequest;
use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Services\PlatformService;
use Illuminate\Http\Request;
class PlatformRoleController extends Controller
{
    public function permissions(Request $request){abort_unless($request->user()->hasPlatformPermission('users.view'),403);return response()->json(['data'=>PlatformPermission::orderBy('name')->get()]);}
    public function store(PlatformRoleRequest $request,PlatformService $service){return response()->json(['data'=>$service->savePlatformRole(null,$request->validated(),$request->user()->id)],201);}
    public function update(PlatformRoleRequest $request,PlatformRole $role,PlatformService $service){return response()->json(['data'=>$service->savePlatformRole($role,$request->validated(),$request->user()->id)]);}
}
