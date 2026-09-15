<?php
namespace App\Http\Controllers;
use App\Models\SalonStaffProfile;
use App\Services\{SalonBookingService, SalonBookingAvailability, BookingCore, ClinicAccessService, PlatformService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SalonScheduleController extends Controller
{
    private function scope(Request $r, ?int $stylist, bool $write = false): array
    {
        $c=app(SalonBookingService::class)->context($r);
        $branch=(int)$r->validate(['branch_id'=>'required|integer'])['branch_id'];
        app(SalonBookingAvailability::class)->branch($c,$branch);
        if ($stylist) SalonStaffProfile::whereHas('branches',fn($q)=>$q->where('branches.id',$branch))->findOrFail($stylist);
        if ($write) {
            $can=fn($p)=>app(ClinicAccessService::class)->can($c['permissions'],$p);
            abort_unless($stylist ? $can('salon_staff.manage') : ($can('clinic_settings.branches.manage') || $can('clinic_settings.update')),403);
        }
        return [$c,$branch];
    }
    public function hours(Request $r) { return $this->read($r,null); }
    public function schedule(Request $r, int $stylist) { return $this->read($r,$stylist); }
    private function read(Request $r, ?int $stylist) {
        [$c,$branch]=$this->scope($r,$stylist);
        return response()->json(['data'=>app(SalonBookingAvailability::class)->table($stylist?'salon_staff_schedules':'salon_location_hours',$c)->where('branch_id',$branch)->when($stylist,fn($q)=>$q->where('stylist_id',$stylist))->orderBy('day_of_week')->get()]);
    }
    public function saveHours(Request $r) { return $this->save($r,null); }
    public function saveSchedule(Request $r, int $stylist) { return $this->save($r,$stylist); }
    private function save(Request $r, ?int $stylist)
    {
        [$c,$branch]=$this->scope($r,$stylist,true);
        $data=$r->validate(['days'=>'required|array|size:7','days.*.day_of_week'=>'required|integer|between:1,7|distinct','days.*.is_available'=>'required|boolean',
            'days.*.start_time'=>'required|date_format:H:i','days.*.end_time'=>'required|date_format:H:i','days.*.break_start'=>'nullable|date_format:H:i','days.*.break_end'=>'nullable|date_format:H:i']);
        DB::transaction(function() use($c,$branch,$stylist,$data) {
            app(BookingCore::class)->lock($c['clinic']->id);
            $availability=app(SalonBookingAvailability::class);
            foreach($data['days'] as $day) {
                if ($day['start_time'] >= $day['end_time']) throw ValidationException::withMessages(['days'=>'Working end must be after working start.']);
                $breakStart=$day['break_start']??null; $breakEnd=$day['break_end']??null;
                if (($breakStart || $breakEnd) && (!$breakStart || !$breakEnd || $breakStart < $day['start_time'] || $breakEnd > $day['end_time'] || $breakStart >= $breakEnd)) throw ValidationException::withMessages(['days'=>'Provide a complete break within working hours.']);
                if ($stylist && $day['is_available']) {
                    $hours=$availability->table('salon_location_hours',$c)->where('branch_id',$branch)->where('day_of_week',$day['day_of_week'])->first();
                    if (!$hours || !$hours->is_available || $day['start_time'] < substr($hours->start_time,0,5) || $day['end_time'] > substr($hours->end_time,0,5)) throw ValidationException::withMessages(['days'=>'Stylist working hours must fit within configured location hours.']);
                }
                $future=\App\Models\SalonAppointment::where('branch_id',$branch)->when($stylist,fn($q)=>$q->where('stylist_id',$stylist))->whereNotIn('status',['completed','cancelled','no_show'])->where('ends_at','>',now($c['clinic']->timezone)->format('Y-m-d H:i:s'))->get();
                foreach ($future as $a) if (Carbon::parse($a->starts_at)->isoWeekday() === $day['day_of_week'] && (!$day['is_available'] || substr($a->starts_at,11,5) < $day['start_time'] || substr($a->ends_at,11,5) > $day['end_time'] || ($breakStart && BookingCore::overlaps(substr($a->starts_at,11,5),substr($a->ends_at,11,5),$breakStart,$breakEnd)))) throw ValidationException::withMessages(['days'=>'Reschedule existing appointments before changing these working hours.']);
                $key=['tenant_id'=>$c['clinic']->id,'branch_id'=>$branch,'day_of_week'=>$day['day_of_week']];
                if ($stylist) $key['stylist_id']=$stylist;
                DB::table($stylist?'salon_staff_schedules':'salon_location_hours')->updateOrInsert($key,collect($day)->except('day_of_week')->all()+['created_at'=>now(),'updated_at'=>now()]);
            }
            app(PlatformService::class)->audit(request()->user()->id,$stylist?'salon.schedule.updated':'salon.location_hours.updated','tenant',$c['clinic']->id,['branch_id'=>$branch,'stylist_id'=>$stylist]);
        },5);
        return $this->read($r,$stylist);
    }
    public function timeOff(Request $r, int $stylist) {
        [$c]=$this->scope($r,$stylist);
        return response()->json(['data'=>app(SalonBookingAvailability::class)->table('salon_staff_time_off',$c)->where('stylist_id',$stylist)->orderByDesc('starts_at')->limit(100)->get()]);
    }
    public function createTimeOff(Request $r, int $stylist) {
        [$c]=$this->scope($r,$stylist,true);
        $data=$r->validate(['starts_at'=>'required|date_format:Y-m-d H:i','ends_at'=>'required|date_format:Y-m-d H:i|after:starts_at','kind'=>'required|in:leave,day_off,unavailable','reason'=>'nullable|string|max:1000']);
        $id=DB::transaction(function() use($c,$stylist,$data) {
            app(BookingCore::class)->lock($c['clinic']->id);
            $a=app(SalonBookingAvailability::class);
            // Time off affects this stylist at every location; never hide conflicting bookings at another location.
            $query=\App\Models\SalonAppointment::where('stylist_id',$stylist)->whereNotIn('status',['completed','cancelled','no_show']);
            if (app(BookingCore::class)->conflicts($query,$data['starts_at'].':00',$data['ends_at'].':00')->exists()) throw ValidationException::withMessages(['starts_at'=>'Reschedule existing appointments before adding time off.']);
            $id=DB::table('salon_staff_time_off')->insertGetId($data+['tenant_id'=>$c['clinic']->id,'stylist_id'=>$stylist,'created_at'=>now(),'updated_at'=>now()]);
            app(PlatformService::class)->audit(request()->user()->id,'salon.time_off.created','tenant',$c['clinic']->id,['stylist_id'=>$stylist,'time_off_id'=>$id]);
            return $id;
        },5);
        return response()->json(['data'=>['id'=>$id]],201);
    }
    public function cancelTimeOff(Request $r, int $stylist, int $id) {
        [$c]=$this->scope($r,$stylist,true);
        DB::transaction(function() use($c,$stylist,$id) {
            app(BookingCore::class)->lock($c['clinic']->id);
            $q=app(SalonBookingAvailability::class)->table('salon_staff_time_off',$c)->where('stylist_id',$stylist)->where('id',$id); $q->firstOrFail(); $q->update(['status'=>'cancelled','updated_at'=>now()]);
            app(PlatformService::class)->audit(request()->user()->id,'salon.time_off.cancelled','tenant',$c['clinic']->id,['stylist_id'=>$stylist,'time_off_id'=>$id]);
        },5);
        return response()->noContent();
    }
}
