<?php
namespace App\Http\Controllers;
use App\Http\Resources\PrescriptionResource;
use App\Models\{Patient,Doctor,Medication,Appointment};
use App\Services\{ClinicAccessService,PrescriptionService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class PrescriptionQueryController extends Controller {
    private function filtered(Request $r,array $c,PrescriptionService $s) {
        $d=$r->validate(['search'=>'nullable|string|max:200','status'=>['nullable',Rule::in(array_keys(config('prescriptions.statuses')))],'doctor_id'=>'nullable|integer','branch_id'=>'nullable|integer','patient_id'=>'nullable|integer','from'=>'nullable|date_format:Y-m-d','to'=>array_filter(['nullable','date_format:Y-m-d',$r->filled('from')?'after_or_equal:from':null]),'medication'=>'nullable|string|max:255','page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|between:1,100']);
        $q=$s->visible($c);
        foreach(['status','doctor_id','branch_id','patient_id'] as $f) if(!empty($d[$f])) $q->where($f,$d[$f]);
        if(!empty($d['from'])) $q->whereDate('prescription_date','>=',$d['from']);
        if(!empty($d['to'])) $q->whereDate('prescription_date','<=',$d['to']);
        if(!empty($d['medication'])) $q->whereHas('items',fn($i)=>$i->where('medication_name','like','%'.$d['medication'].'%'));
        foreach(preg_split('/\s+/',trim($d['search']??'')) as $word) if($word!=='') $q->where(function($q) use($word) {
            $like='%'.$word.'%'; $q->where('prescription_number','like',$like)
                ->orWhereHas('patient',fn($p)=>$p->where(function($p) use($like) { foreach(['first_name','middle_name','last_name','patient_number','phone'] as $f) $p->orWhere($f,'like',$like); }))
                ->orWhereHas('doctor',fn($p)=>$p->where(function($p) use($like) { foreach(['first_name','middle_name','last_name'] as $f) $p->orWhere($f,'like',$like); }))
                ->orWhereHas('items',fn($i)=>$i->where('medication_name','like',$like));
        });
        return $q;
    }
    public function index(Request $r,ClinicAccessService $a,PrescriptionService $s) {
        $c=$a->authorize($r,'prescriptions');
        return PrescriptionResource::collection($this->filtered($r,$c,$s)->with(['items','patient','doctor.specialties','branch','appointment'])->orderByDesc('prescription_date')->orderByDesc('id')->paginate($r->integer('per_page',20)));
    }
    public function stats(Request $r,ClinicAccessService $a,PrescriptionService $s) {
        $c=$a->authorize($r,'prescriptions'); $q=$s->visible($c);
        if($r->filled('branch_id')) $q->where('branch_id',$r->integer('branch_id'));
        return response()->json(['data'=>['total'=>(clone $q)->count(),'today'=>(clone $q)->whereDate('prescription_date',$c['today'])->count(),\App\Support\PrescriptionStatus::ACTIVE=>(clone $q)->whereIn('status',[\App\Support\PrescriptionStatus::ACTIVE,\App\Support\PrescriptionStatus::PENDING,\App\Support\PrescriptionStatus::PARTIALLY_DISPENSED])->count(),\App\Support\PrescriptionStatus::PENDING=>(clone $q)->whereIn('status',[\App\Support\PrescriptionStatus::PENDING,\App\Support\PrescriptionStatus::PARTIALLY_DISPENSED])->count()]]);
    }
    public function options(Request $r,ClinicAccessService $a) {
        $c=$a->authorize($r,'prescriptions'); $branch=$r->integer('branch_id',$c['branch']->id); abort_unless($c['branches']->contains('id',$branch),403);
        $doctors=Doctor::where('status','active')->whereHas('branches',fn($q)=>$q->where('branches.id',$branch));
        if(!$a->can($c['permissions'],'prescriptions.view_all_doctors')) $doctors->where('user_id',$r->user()->id);
        return response()->json(['data'=>['statuses'=>config('prescriptions.statuses'),'routes'=>config('prescriptions.routes'),'frequencies'=>config('prescriptions.frequencies'),'today'=>$c['today'],'doctors'=>$doctors->with('specialties')->orderBy('first_name')->get()->map(fn($d)=>['id'=>$d->id,'full_name'=>$d->full_name,'is_self'=>$d->user_id===$r->user()->id,'specialty'=>$d->specialties->pluck('name')->join(', ')])]]);
    }
    public function patients(Request $r,ClinicAccessService $a) {
        $c=$a->authorize($r,'prescriptions'); $r->validate(['search'=>'nullable|string|max:100','id'=>'nullable|integer']);
        $q=Patient::whereIn('registration_branch_id',$c['branches']->pluck('id'))->where('status','active');
        if($r->filled('id')) $q->whereKey($r->integer('id'));
        foreach(preg_split('/\s+/',trim($r->input('search',''))) as $word) if($word!=='') $q->where(function($q) use($word) {foreach(['first_name','middle_name','last_name','patient_number','phone'] as $f) $q->orWhere($f,'like','%'.$word.'%');});
        return response()->json(['data'=>$q->orderBy('first_name')->limit(20)->get()->map(fn($p)=>['id'=>$p->id,'full_name'=>$p->full_name,'patient_number'=>$p->patient_number,'age'=>$p->age,'gender'=>$p->gender,'phone'=>$p->phone])]);
    }
    public function medications(Request $r,ClinicAccessService $a) {
        $a->authorize($r,'prescriptions'); $r->validate(['search'=>'nullable|string|max:100']);
        return response()->json(['data'=>Medication::where(\App\Support\PrescriptionStatus::ACTIVE,true)->where(fn($q)=>$q->where('name','like','%'.$r->input('search','').'%')->orWhere('generic_name','like','%'.$r->input('search','').'%'))->orderBy('name')->limit(20)->get(['id','name','generic_name','strength','dosage_form'])]);
    }
    public function storeMedication(Request $r,ClinicAccessService $a) {
        $a->authorize($r,'prescriptions','prescriptions.medications.manage');
        $d=$r->validate(['tenant_id'=>'prohibited','name'=>'required|string|max:255','generic_name'=>'nullable|string|max:255','strength'=>'nullable|string|max:100','dosage_form'=>'nullable|string|max:100']);
        return response()->json(['data'=>Medication::create($d)],201);
    }
    public function appointments(Request $r,ClinicAccessService $a) {
        $c=$a->authorize($r,'prescriptions'); $r->validate(['patient_id'=>'required|integer','doctor_id'=>'required|integer','branch_id'=>'required|integer']);
        $q=Appointment::whereIn('branch_id',$c['branches']->pluck('id'))->where('branch_id',$r->integer('branch_id'))->where('patient_id',$r->integer('patient_id'))->where('doctor_id',$r->integer('doctor_id'));
        if(!$a->can($c['permissions'],'prescriptions.view_all_doctors')) $q->whereHas('doctor',fn($d)=>$d->where('user_id',$r->user()->id));
        return response()->json(['data'=>$q->orderByDesc('starts_at')->limit(30)->get(['id','appointment_number','starts_at'])]);
    }
}
