<?php
namespace App\Http\Controllers;
use App\Http\Requests\DoctorRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\Tenant;
use App\Services\ClinicAccessService;
use App\Services\DoctorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class DoctorController extends Controller {
    private function context(Request $request, ?string $permission = null): array {
        $context = app(ClinicAccessService::class)->authorize($request,'doctors',$permission);
        $request->attributes->set('doctor_branch_ids',$context['branches']->pluck('id')->all()); return $context;
    }
    public function options(Request $request) {
        $context=$this->context($request);
        $users=[];
        if (app(ClinicAccessService::class)->can($context['permissions'],'staff.manage')) $users=DB::table('tenant_memberships')->join('users','users.id','=','tenant_memberships.user_id')->where('tenant_id',$context['clinic']->id)->where('tenant_memberships.status','active')->where('users.status','active')
            ->where(fn($q)=>$q->where('all_branches',true)->orWhereIn('tenant_memberships.id',DB::table('branch_memberships')->where('tenant_id',$context['clinic']->id)->whereIn('branch_id',$context['branches']->pluck('id'))->select('membership_id')))
            ->orderBy('users.name')->get(['users.id','users.name','users.email']);
        return response()->json(['data'=>['specialties'=>Specialty::orderBy('name')->get(['id','name']),'branches'=>$context['branches'],'users'=>$users,'account_permissions'=>config('clinic.roles.doctor'),'doctor_limit'=>$context['limits']['doctor_limit'],'usage'=>app(DoctorService::class)->usage($context['clinic']->id)]]);
    }
    public function specialty(Request $request) {
        $this->context($request,'doctors.specialties.manage');
        $data=$request->validate(['name'=>['required','string','max:120',Rule::unique('specialties','name')->where('tenant_id',$request->session()->get('tenant_id'))]]);
        return response()->json(['data'=>Specialty::create($data)],201);
    }
    public function specialties(Request $request) { $this->context($request); return response()->json(['data' => Specialty::orderBy('name')->get(['id','name'])]); }
    public function index(Request $request,DoctorService $service) {
        $context=$this->context($request);
        $data=$request->validate(['search'=>['nullable','string','max:150'],'specialty_id'=>['nullable','integer'],'status'=>['nullable',Rule::in(['active','inactive'])], 'availability'=>['nullable',Rule::in(['available','in_consultation','unavailable','on_leave'])], 'branch_id'=>['nullable','integer'],'per_page'=>['nullable',Rule::in([25,50,100])],'page'=>['nullable','integer','min:1']]);
        $branch=(int)($data['branch_id']??$context['branch']->id);
        abort_unless($context['branches']->contains('id',$branch),403,'Select an authorized branch.');
        $query=$service->availabilityQuery($context,$branch);
        $base=clone $query;
        if (!empty($data['search'])) foreach(preg_split('/\s+/',trim($data['search'])) as $word) $query->where(function($q)use($word){foreach(['first_name','middle_name','last_name','doctor_number','phone','email','license_number'] as $field)$q->orWhere($field,'like','%'.$word.'%');$q->orWhereHas('specialties',fn($s)=>$s->where('name','like','%'.$word.'%'));});
        if(!empty($data['specialty_id']))$query->whereHas('specialties',fn($q)=>$q->where('specialties.id',$data['specialty_id']));
        if(!empty($data['status']))$query->where('doctors.status',$data['status']);
        if(!empty($data['availability']))$query->whereRaw(DoctorService::AVAILABILITY_SQL.' = ?',[$context['today'],$context['today'],$branch,$data['availability']]);
        $stats=['total'=>(clone $base)->count(),'active'=>(clone $base)->where('doctors.status','active')->count(),
            'specializations'=>DB::table('doctor_specialty')->where('tenant_id',$context['clinic']->id)->whereIn('doctor_id',(clone $base)->reorder()->select('doctors.id'))->distinct()->count('specialty_id'),
            'on_leave'=>(clone $base)->whereRaw(DoctorService::AVAILABILITY_SQL." = 'on_leave'",[$context['today'],$context['today'],$branch])->count()];
        return DoctorResource::collection($query->with(['branches','specialties'])->orderBy('last_name')->orderBy('id')->paginate($data['per_page']??25))->additional(['stats'=>$stats]);
    }
    public function store(DoctorRequest $request,DoctorService $service) { $context=$this->context($request,'doctors.create'); return (new DoctorResource($service->save($request->validated(),$context)))->response()->setStatusCode(201); }
    public function show(Request $request,DoctorService $service,int $doctor) {
        $context=$this->context($request); $model=$service->find($context,$doctor);
        $branch=$model->branches->firstWhere('id',$context['branch']->id)?->id ?? $model->branches->whereIn('id',$context['branches']->pluck('id'))->first()->id;
        $model->effective_availability=$service->availabilityQuery($context,$branch)->where('doctors.id',$doctor)->value('effective_availability');
        $data=(new DoctorResource($model))->resolve($request)+$model->only(['first_name','middle_name','last_name','gender','license_number','qualification','consultation_fee','notes','created_at']);
        $data['primary_branch_id']=$context['branches']->contains('id',$model->primary_branch_id)?$model->primary_branch_id:null;
        $data['user']=$model->user?->only(['id','name','email','status']);
        $data['display_branch_id']=$branch;
        return response()->json(['data'=>$data]);
    }
    public function update(DoctorRequest $request,DoctorService $service,int $doctor) { $context=$this->context($request,'doctors.update'); return new DoctorResource($service->save($request->validated(),$context,$service->find($context,$doctor,true))); }
    public function status(Request $request,DoctorService $service,int $doctor,string $action) {
        $context=$this->context($request,'doctors.deactivate');
        $model=DB::transaction(function()use($context,$service,$doctor,$action){Tenant::lockForUpdate()->findOrFail($context['clinic']->id);$model=$service->find($context,$doctor,true);$model->status=$action==='activate'?'active':'inactive';$model->updated_by=request()->user()->id;$model->save();$service->audit($model,'doctor.'.($action==='activate'?'activated':'deactivated'));return $model;});
        return new DoctorResource($model);
    }
    public function activity(Request $request,DoctorService $service,int $doctor) {
        $context=$this->context($request); $model=$service->find($context,$doctor);
        return response()->json(['data'=>DB::table('platform_audit_logs')->where('tenant_id',$model->tenant_id)->where('metadata->doctor_id',$model->id)->where('action','like','doctor.%')->whereIn('metadata->branch_id',$context['branches']->pluck('id'))->orderByDesc('id')->paginate(25,['id','action','actor_name','created_at'])]);
    }
}
