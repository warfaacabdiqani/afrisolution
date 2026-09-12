<?php
namespace App\Http\Controllers;
use App\Http\Requests\ClinicBranchRequest;
use App\Models\Tenant;
use App\Services\{ClinicSettingsService,TenantProvisioningService,PlatformService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ClinicSettingsBranchController extends Controller {
    public function index(Request $r,ClinicSettingsService $s) {
        $c=$s->authorize($r,'branches');
        // Inactive branches stay manageable, but are excluded from the operational branch selector.
        $member=DB::table('tenant_memberships')->where('tenant_id',$c['clinic']->id)->where('user_id',$r->user()->id)->firstOrFail();
        $q=DB::table('branches')->where('tenant_id',$c['clinic']->id);
        if(!$member->all_branches) $q->whereIn('id',DB::table('branch_memberships')->where('tenant_id',$c['clinic']->id)->where('membership_id',$member->id)->select('branch_id'));
        return response()->json(['data'=>$q->orderBy('id')->get(),'main_id'=>DB::table('branches')->where('tenant_id',$c['clinic']->id)->min('id')]);
    }
    public function store(ClinicBranchRequest $r,ClinicSettingsService $s,TenantProvisioningService $provisioning) {
        $c=$s->authorize($r,'branches',true);
        abort_unless($c['features']['multi_branch']??false,403,'Your plan does not include additional branches.');
        $branch=DB::transaction(function() use($r,$c,$provisioning) {
            $tenant=Tenant::lockForUpdate()->findOrFail($c['clinic']->id);
            if(DB::table('branches')->where('tenant_id',$tenant->id)->count()>=$c['limits']['branch_limit']) throw ValidationException::withMessages(['name'=>"Your current plan allows {$c['limits']['branch_limit']} branches."]);
            $branch=$provisioning->addBranch($tenant,$r->validated(),$r->user()->id);
            $member=DB::table('tenant_memberships')->where('tenant_id',$tenant->id)->where('user_id',$r->user()->id)->first();
            if(!$member->all_branches) DB::table('branch_memberships')->insert(['tenant_id'=>$tenant->id,'membership_id'=>$member->id,'branch_id'=>$branch->id]);
            return $branch;
        },3);
        return response()->json(['data'=>$branch],201);
    }
    public function update(ClinicBranchRequest $r,ClinicSettingsService $s,int $branch) {
        $c=$s->authorize($r,'branches',true);
        return DB::transaction(function() use($r,$c,$branch) {
            Tenant::lockForUpdate()->findOrFail($c['clinic']->id);
            $member=DB::table('tenant_memberships')->where('tenant_id',$c['clinic']->id)->where('user_id',$r->user()->id)->first();
            $q=DB::table('branches')->where('tenant_id',$c['clinic']->id)->where('id',$branch);
            if(!$member->all_branches) $q->whereIn('id',DB::table('branch_memberships')->where('tenant_id',$c['clinic']->id)->where('membership_id',$member->id)->select('branch_id'));
            $before=$q->firstOrFail(); $data=$r->safe()->except('tenant_id');
            $main=DB::table('branches')->where('tenant_id',$c['clinic']->id)->min('id');
            if($branch===$main && $data['status']!=='active') throw ValidationException::withMessages(['status'=>'The main branch must remain active.']);
            if($data['status']==='inactive' && !$c['branches']->contains(fn($b)=>$b->id!==$branch)) throw ValidationException::withMessages(['status'=>'Keep at least one accessible branch active.']);
            if($data['status']==='inactive' && DB::table('appointments')->where('tenant_id',$c['clinic']->id)->where('branch_id',$branch)->whereNotIn('status',config('appointments.non_blocking'))->where('ends_at','>=',now($c['clinic']->timezone))->exists()) throw ValidationException::withMessages(['status'=>'Resolve future appointments before deactivating this branch.']);
            $q->update($data+['updated_at'=>now()]);
            app(PlatformService::class)->audit($r->user()->id,'branch.updated','tenant',$c['clinic']->id,['branch_id'=>$branch,'changed_fields'=>array_keys(array_filter($data,fn($v,$k)=>$v!==($before->$k??null),ARRAY_FILTER_USE_BOTH))]);
            return response()->json(['data'=>$q->first()]);
        },3);
    }
}
