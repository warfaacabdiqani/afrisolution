<?php
namespace App\Http\Controllers;
use App\Models\Tenant;
use App\Services\ClinicAccessService;
use App\Services\DoctorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class DoctorLeaveController extends Controller {
    public function index(Request $request,ClinicAccessService $access,DoctorService $service,int $doctor){
        $context=$access->authorize($request,'doctors','doctors.schedule.view');$model=$service->find($context,$doctor);
        $result=$model->leaves()->where(fn($q)=>$q->whereNull('branch_id')->orWhereIn('branch_id',$context['branches']->pluck('id')))->orderByDesc('start_date')->paginate(25);
        $result->getCollection()->transform(function($leave)use($context,$access){$leave->status=$leave->status==='cancelled'?'cancelled':($leave->end_date<$context['today']?'completed':($leave->start_date>$context['today']?'scheduled':'active'));if(!$access->can($context['permissions'],'doctors.leave.manage'))$leave->makeHidden('reason');return $leave;});
        return response()->json(['data'=>$result]);
    }
    public function store(Request $request,ClinicAccessService $access,DoctorService $service,int $doctor){
        $context=$access->authorize($request,'doctors','doctors.leave.manage');
        $data=$request->validate(['branch_id'=>['nullable','integer'],'start_date'=>['required','date_format:Y-m-d'],'end_date'=>['required','date_format:Y-m-d','after_or_equal:start_date'],'reason'=>['nullable','string','max:1000']]);
        $leave=DB::transaction(function()use($data,$context,$doctor,$service){Tenant::lockForUpdate()->findOrFail($context['clinic']->id);$model=$service->find($context,$doctor,empty($data['branch_id']));if(!empty($data['branch_id']))$service->branch($context,$model,$data['branch_id']);
            $overlap=$model->leaves()->where('status','!=','cancelled')->where('start_date','<=',$data['end_date'])->where('end_date','>=',$data['start_date']);
            if(!empty($data['branch_id']))$overlap->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$data['branch_id']));
            if($overlap->exists())throw ValidationException::withMessages(['start_date'=>'Leave already exists for these dates.']);
            $leave=$model->leaves()->create($data+['created_by'=>request()->user()->id,'status'=>'scheduled']);$service->audit($model,'doctor.leave.created',$data['branch_id']??null);return $leave;
        });return response()->json(['data'=>$leave],201);
    }
    public function cancel(Request $request,ClinicAccessService $access,DoctorService $service,int $doctor,int $leave){
        $context=$access->authorize($request,'doctors','doctors.leave.manage');
        DB::transaction(function()use($context,$doctor,$leave,$service){Tenant::lockForUpdate()->findOrFail($context['clinic']->id);$model=$service->find($context,$doctor);$entry=$model->leaves()->findOrFail($leave);if($entry->branch_id)$service->branch($context,$model,$entry->branch_id);else $service->find($context,$doctor,true);$entry->update(['status'=>'cancelled']);$service->audit($model,'doctor.leave.cancelled',$entry->branch_id);});return response()->noContent();
    }
}
