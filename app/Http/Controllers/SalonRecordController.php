<?php
namespace App\Http\Controllers;
use App\Http\Resources\SalonRecordResource;
use App\Services\{SalonAccessService,SalonRecordRules,SalonRecordService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

abstract class SalonRecordController extends Controller
{
    protected string $kind;
    protected function context(Request $r, ?string $action=null): array {
        $c=app(SalonAccessService::class)->authorize($r,$this->kind,$action);
        $r->attributes->set('salon_context',$c); return $c;
    }
    public function index(Request $r) {
        $c=$this->context($r);
        $filters=$r->validate(['search'=>'nullable|string|max:100','status'=>['nullable',Rule::in(['active','inactive','archived'])],'per_page'=>'nullable|integer|between:1,100','page'=>'nullable|integer|min:1']);
        $query=app(SalonAccessService::class)->query($this->kind,$c);
        $stats=['total'=>(clone $query)->count(),'month'=>(clone $query)->where('created_at','>=',now($c['clinic']->timezone)->startOfMonth())->count(),'active'=>(clone $query)->where('status','active')->count(),'inactive'=>(clone $query)->whereIn('status',['inactive','archived'])->count()];
        if (!empty($filters['search'])) $query->where(function($q) use($filters) { foreach(config('salon.'.$this->kind.'.search') as $field) $q->orWhere($field,'like','%'.$filters['search'].'%'); });
        if (!empty($filters['status'])) $query->where('status',$filters['status']);
        return SalonRecordResource::collection($query->with(config('salon.'.$this->kind.'.relations'))->orderByDesc('id')->paginate($filters['per_page']??25))->additional(['stats'=>$stats]);
    }
    public function show(Request $r, int $record) {
        $c=$this->context($r);
        $model=app(SalonAccessService::class)->query($this->kind,$c)->with(config('salon.'.$this->kind.'.relations'))->findOrFail($record);
        $activity=DB::table('platform_audit_logs')->where('tenant_id',$c['clinic']->id)->where('metadata->salon_entity',$this->kind)->where('metadata->record_id',$record)->latest('id')->limit(50)->get(['action','created_at']);
        return (new SalonRecordResource($model))->additional(['activity'=>$activity]);
    }
    public function store(Request $r) {
        $c=$this->context($r,'create'); $data=app(SalonRecordRules::class)->validate($r,$this->kind,$c,null);
        return (new SalonRecordResource(app(SalonRecordService::class)->save($this->kind,$data,$c)))->response()->setStatusCode(201);
    }
    public function update(Request $r, int $record) {
        $c=$this->context($r,'update');
        app(SalonAccessService::class)->query($this->kind,$c)->findOrFail($record);
        $data=app(SalonRecordRules::class)->validate($r,$this->kind,$c,$record);
        return new SalonRecordResource(app(SalonRecordService::class)->save($this->kind,$data,$c,$record));
    }
    public function archive(Request $r, int $record) {
        $c=$this->context($r,'archive');
        return new SalonRecordResource(app(SalonRecordService::class)->archive($this->kind,$record,$c));
    }
    public function options(Request $r) {
        $c=$this->context($r); $a=app(SalonAccessService::class);
        $data=['branches'=>$c['branches'],'current_branch_id'=>$c['branch']->id,'currency'=>app(\App\Services\ClinicSettingsService::class)->get($c['clinic']->id,'general.currency','USD')];
        if (in_array($this->kind,['clients','services'])) $data['stylists']=!empty($c['features']['salon_staff'])?($this->kind==='services'?\App\Models\SalonStaffProfile::whereHas('branches',fn($q)=>$q->whereIn('branches.id',$c['branches']->pluck('id')))->whereDoesntHave('branches',fn($q)=>$q->whereNotIn('branches.id',$c['branches']->pluck('id'))):$a->query('stylists',$c))->where('status','active')->orderBy('display_name')->get(['id','display_name']):[];
        if ($this->kind==='services') $data['categories']=$a->query('categories',$c)->where('status','active')->orderBy('sort_order')->orderBy('name')->get(['id','name']);
        if ($this->kind==='stylists') {
            $data['titles']=['Hair Stylist','Barber','Nail Technician','Beautician','Makeup Artist','Massage Therapist','Receptionist','Other'];
            $data['users']=DB::table('tenant_memberships as m')->join('users as u','u.id','=','m.user_id')->where('m.tenant_id',$c['clinic']->id)->where('m.status','active')->where('u.status','active')
                ->where(fn($q)=>$q->where('m.all_branches',true)->orWhereIn('m.id',DB::table('branch_memberships')->where('tenant_id',$c['clinic']->id)->where('branch_id',$c['branch']->id)->select('membership_id')))->orderBy('u.name')->get(['u.id','u.name']);
        }
        return response()->json(['data'=>$data]);
    }
}
