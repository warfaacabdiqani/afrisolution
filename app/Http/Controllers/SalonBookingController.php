<?php
namespace App\Http\Controllers;
use App\Http\Resources\SalonAppointmentResource;
use App\Models\{SalonClient, SalonService, SalonStaffProfile};
use App\Services\{SalonBookingService, SalonBookingAvailability, ClinicSettingsService, ClinicAccessService};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalonBookingController extends Controller
{
    public function index(Request $r, SalonBookingService $bookings)
    {
        $c = $bookings->context($r);
        $data = $r->validate(['start'=>'required|date_format:Y-m-d','end'=>'required|date_format:Y-m-d|after_or_equal:start', 'branch_id'=>'nullable|integer',
            'stylist_id'=>'nullable|integer','client_id'=>'nullable|integer','service_id'=>'nullable|integer','status'=>'nullable|in:'.implode(',',array_keys(config('salon_booking.statuses'))), 'search'=>'nullable|string|max:150', 'page'=>'nullable|integer|min:1']);
        abort_if(Carbon::parse($data['start'])->diffInDays(Carbon::parse($data['end'])) > 93, 422, 'Select a range of up to 93 days.');
        if (!empty($data['branch_id'])) app(SalonBookingAvailability::class)->branch($c, $data['branch_id']);
        $query = $bookings->visible($c)->with(['client','stylist','branch','items','invoice']);
        foreach (['branch_id','stylist_id','client_id','status'] as $key) if (!empty($data[$key])) $query->where($key,$data[$key]);
        if (!empty($data['service_id'])) $query->whereHas('items',fn($q)=>$q->where('service_id',$data['service_id']));
        if (!empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(fn($q)=>$q->where('appointment_number','like',$term)->orWhereHas('client',fn($q)=>$q->where('first_name','like',$term)->orWhere('last_name','like',$term)->orWhere(function($q) use($data) { foreach(preg_split('/\s+/',trim($data['search'])) as $word) $q->where(fn($q)=>$q->where('first_name','like','%'.$word.'%')->orWhere('middle_name','like','%'.$word.'%')->orWhere('last_name','like','%'.$word.'%')); })->orWhere('phone','like',$term))->orWhereHas('stylist',fn($q)=>$q->where('display_name','like',$term))->orWhereHas('items',fn($q)=>$q->where('name','like',$term)));
        }
        $todayQuery = (clone $query)->where('starts_at','>=',$c['today'].' 00:00:00')->where('starts_at','<',Carbon::parse($c['today'])->addDay()->format('Y-m-d').' 00:00:00');
        $summary = (clone $todayQuery)->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status');
        $query->where('starts_at','>=',$data['start'].' 00:00:00')->where('starts_at','<',Carbon::parse($data['end'])->addDay()->format('Y-m-d').' 00:00:00');
        $counts = (clone $query)->reorder()->selectRaw('DATE(starts_at) as date, COUNT(*) as total')->groupByRaw('DATE(starts_at)')->get();
        return SalonAppointmentResource::collection($query->orderBy('starts_at')->orderBy('id')->paginate(100))->additional([
            'counts'=>$counts, 'today'=>SalonAppointmentResource::collection($todayQuery->orderBy('starts_at')->limit(100)->get()), 'summary'=>$summary,
        ]);
    }

    public function options(Request $r, SalonBookingService $bookings)
    {
        $c = $bookings->context($r);
        $r->validate(['branch_id'=>'nullable|integer']);
        $branch = (int)($r->input('branch_id') ?: $c['branch']->id);
        app(SalonBookingAvailability::class)->branch($c,$branch);
        $atBranch = fn($q)=>$q->where('branches.id',$branch);
        $stylists = SalonStaffProfile::where('status','active')->whereHas('branches',$atBranch)->whereHas('user',fn($q)=>$q->where('status','active'))
            ->when(!app(ClinicAccessService::class)->can($c['permissions'],'appointments.view_all'),fn($q)=>$q->where('user_id',$r->user()->id))->with('services')->orderBy('display_name')->get();
        return response()->json(['data'=>[
            'branches'=>$c['branches'], 'branch_id'=>$branch, 'statuses'=>config('salon_booking.statuses'), 'actions'=>config('salon_booking.actions'),
            'services'=>SalonService::where('status','active')->whereHas('category',fn($q)=>$q->where('status','active'))->whereHas('branches',$atBranch)->with('category')->orderBy('name')->get()->map(fn($s)=>$s->only(['id','name','duration_minutes','price','requires_deposit','deposit_amount'])+['category'=>$s->category->name]),
            'stylists'=>$stylists->map(fn($s)=>['id'=>$s->id,'name'=>$s->display_name,'service_ids'=>$s->services->pluck('id')]),
            'settings'=>app(ClinicSettingsService::class)->section($c['clinic']->id,'salon'),
            'currency'=>app(ClinicSettingsService::class)->get($c['clinic']->id,'general.currency','USD'),
            'now'=>now($c['clinic']->timezone)->format('Y-m-d H:i'),
        ]]);
    }

    public function clients(Request $r, SalonBookingService $bookings)
    {
        $c=$bookings->context($r); $data=$r->validate(['branch_id'=>'required|integer','search'=>'nullable|string|max:150']);
        app(SalonBookingAvailability::class)->branch($c,$data['branch_id']);
        $q=SalonClient::where('status','active')->where('branch_id',$data['branch_id']);
        if ($r->filled('search')) {
            $terms=preg_split('/\s+/',trim($data['search']));
            foreach ($terms as $term) $q->where(fn($q)=>$q->where('client_number','like','%'.$term.'%')->orWhere('first_name','like','%'.$term.'%')->orWhere('middle_name','like','%'.$term.'%')->orWhere('last_name','like','%'.$term.'%')->orWhere('phone','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%'));
        }
        return response()->json(['data'=>$q->orderBy('first_name')->limit(30)->get()->map(fn($x)=>$x->only(['id','client_number','phone','email'])+['name'=>$x->full_name])]);
    }

    public function slots(Request $r, SalonBookingService $bookings, SalonBookingAvailability $availability)
    {
        $c=$bookings->context($r); $data=$r->validate(['branch_id'=>'required|integer','client_id'=>'required|integer','stylist_id'=>'required|integer','service_ids'=>'required|array|min:1|max:20','service_ids.*'=>'integer|distinct','date'=>'required|date_format:Y-m-d','appointment_id'=>'nullable|integer']);
        return response()->json(['data'=>$availability->slots($c,$data)]);
    }

    public function availableStylists(Request $r, SalonBookingService $bookings, SalonBookingAvailability $availability) {
        $c=$bookings->context($r);
        $data=$r->validate(['branch_id'=>'required|integer','client_id'=>'required|integer','service_ids'=>'required|array|min:1|max:20','service_ids.*'=>'integer|distinct','date'=>'required|date_format:Y-m-d','start_time'=>'required|date_format:H:i','appointment_id'=>'nullable|integer']);
        $availability->branch($c,$data['branch_id']);
        $old=!empty($data['appointment_id'])?$bookings->find($c,$data['appointment_id']):null;
        $rows=[];
        foreach (SalonStaffProfile::where('status','active')->whereHas('branches',fn($q)=>$q->where('branches.id',$data['branch_id']))->when(!app(ClinicAccessService::class)->can($c['permissions'],'appointments.view_all'),fn($q)=>$q->where('user_id',$r->user()->id))->get() as $stylist) {
            try { [,,$services]=$availability->selection($c,$data+['stylist_id'=>$stylist->id]); } catch (\Illuminate\Validation\ValidationException $e) { continue; }
            $duration=$services->sum(fn($s)=>$old?->items->firstWhere('service_id',$s->id)?->duration_minutes??$s->duration_minutes);
            $start=Carbon::createFromFormat('!Y-m-d H:i',$data['date'].' '.$data['start_time'],$c['clinic']->timezone);
            if (!$availability->reason($c,$stylist->id,$data['branch_id'],$start,$start->copy()->addMinutes($duration),$old?->id,false,$data['client_id'])) $rows[]=['id'=>$stylist->id,'name'=>$stylist->display_name];
        }
        return response()->json(['data'=>$rows]);
    }

    public function show(Request $r, int $id, SalonBookingService $bookings) { $c=$bookings->context($r); return new SalonAppointmentResource($bookings->find($c,$id)); }
    public function store(Request $r, SalonBookingService $bookings) { $c=$bookings->context($r,'appointments.create'); return (new SalonAppointmentResource($bookings->save($c,$r->validate(SalonBookingService::rules()))))->response()->setStatusCode(201); }
    public function update(Request $r, int $id, SalonBookingService $bookings) { $c=$bookings->context($r,'appointments.update'); return new SalonAppointmentResource($bookings->save($c,$r->validate(SalonBookingService::rules()),$id)); }
    public function reschedule(Request $r, int $id, SalonBookingService $bookings) { $c=$bookings->context($r,'appointments.reschedule'); return new SalonAppointmentResource($bookings->save($c,$r->validate(SalonBookingService::rules()),$id,true)); }
    public function status(Request $r, int $id, string $action, SalonBookingService $bookings) {
        $c=$bookings->context($r); $data=$r->validate(['reason'=>($action==='cancel'?'required':'nullable').'|string|max:1000']);
        return new SalonAppointmentResource($bookings->transition($c,$id,$action,$data));
    }
    public function activity(Request $r, int $id, SalonBookingService $bookings) {
        $c=$bookings->context($r); $bookings->find($c,$id);
        return response()->json(['data'=>DB::table('platform_audit_logs')->where('tenant_id',$c['clinic']->id)->where('action','like','salon.appointment.%')->where('metadata->appointment_id',$id)->latest('id')->limit(100)->get(['action','created_at'])]);
    }
    public function history(Request $r, string $kind, int $id, SalonBookingService $bookings) {
        $c=$bookings->context($r);
        $access=app(\App\Services\SalonAccessService::class);
        $access->authorize($r,$kind); $access->query($kind,$c)->findOrFail($id);
        $query=$bookings->visible($c)->where('branch_id',$c['branch']->id);
        if ($kind==='services') $query->whereHas('items',fn($q)=>$q->where('service_id',$id));
        else $query->where($kind==='clients'?'client_id':'stylist_id',$id);
        $last=(clone $query)->where('status','completed')->max('starts_at');
        $next=(clone $query)->whereNotIn('status',['completed','cancelled','no_show'])->where('starts_at','>=',now($c['clinic']->timezone)->format('Y-m-d H:i:s'))->min('starts_at');
        $count=(clone $query)->count();
        $query->with(['client','stylist','branch','items','invoice']);
        $today=(clone $query)->whereDate('starts_at',$c['today'])->orderBy('starts_at')->limit(25)->get();
        $upcoming=(clone $query)->whereNotIn('status',['completed','cancelled','no_show'])->where('starts_at','>=',now($c['clinic']->timezone)->format('Y-m-d H:i:s'))->orderBy('starts_at')->limit(10)->get();
        return SalonAppointmentResource::collection($query->orderByDesc('starts_at')->paginate(25))->additional(['last_visit'=>$last,'next_appointment'=>$next,'booking_count'=>$count,'today'=>SalonAppointmentResource::collection($today),'upcoming'=>SalonAppointmentResource::collection($upcoming)]);
    }
}
