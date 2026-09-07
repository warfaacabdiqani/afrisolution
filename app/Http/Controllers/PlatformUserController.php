<?php
namespace App\Http\Controllers;
use App\Http\Requests\PlatformUserRequest;
use App\Models\PlatformRole;
use App\Models\User;
use App\Services\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PlatformUserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPlatformPermission('users.view'),403);
        $query=User::query()->where('is_platform_admin',true)->with('platformRoles:id,name')->withCount('platformRoles');
        if($search=$request->string('search')->trim()->toString()) $query->where(fn($q)=>$q->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%"));
        if(in_array($request->status,['active','inactive'],true)) $query->where('status',$request->status);
        return response()->json(['data'=>$query->latest('id')->paginate(20)]);
    }
    public function store(PlatformUserRequest $request, PlatformService $service) { return response()->json(['data'=>$service->savePlatformUser(null,$request->validated(),$request->user()->id)],201); }
    public function show(Request $request, User $user) { abort_unless($request->user()->hasPlatformPermission('users.view'),403); abort_unless($user->is_platform_admin,404); return response()->json(['data'=>$user->load('platformRoles:id,name')]); }
    public function update(PlatformUserRequest $request, User $user, PlatformService $service)
    {
        abort_unless($user->is_platform_admin,404);
        abort_if($request->user()->is($user), 403, 'You cannot change your own administrator account. Another Super Administrator must make this change.');
        return response()->json(['data'=>$service->savePlatformUser($user,$request->validated(),$request->user()->id)]);
    }
    public function roles(Request $request) { abort_unless($request->user()->hasPlatformPermission('users.view'),403); return response()->json(['data'=>PlatformRole::with('permissions:id,name,label')->withCount('users')->orderBy('name')->get()]); }
}
