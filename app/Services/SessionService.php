<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SessionService {
 public function login(Request $request,array $credentials): void {
  if (! Auth::guard('web')->attempt($credentials + ['status'=>'active'])) throw ValidationException::withMessages(['email'=>'The credentials provided are incorrect.']);
  $request->session()->regenerate(); $request->session()->forget('tenant_id');
  $ids=DB::table('tenant_memberships')->join('tenants','tenants.id','=','tenant_memberships.tenant_id')
   ->where('user_id',$request->user()->id)->where('tenant_memberships.status','active')->where('tenants.status','active')->pluck('tenants.id');
  if($ids->count()===1) $request->session()->put('tenant_id',(int)$ids->first());
 }
 public function select(Request $request,int $id): void {
  abort_unless(DB::table('tenant_memberships')->join('tenants','tenants.id','=','tenant_memberships.tenant_id')
   ->where('user_id',$request->user()->id)->where('tenant_memberships.tenant_id',$id)
   ->where('tenant_memberships.status','active')->where('tenants.status','active')->exists(),403);
  $request->session()->put('tenant_id',$id); $request->session()->regenerate();
 }
 public function logout(Request $request): void {
  Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
 }
}
