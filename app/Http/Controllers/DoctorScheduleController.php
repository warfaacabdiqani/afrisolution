<?php
namespace App\Http\Controllers;
use App\Models\Tenant;
use App\Models\DoctorSchedule;
use App\Services\ClinicAccessService;
use App\Services\DoctorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class DoctorScheduleController extends Controller {
    public function index(Request $request,ClinicAccessService $access,DoctorService $service,int $doctor) {
        $context=$access->authorize($request,'doctors','doctors.schedule.view');$model=$service->find($context,$doctor);
        $data=$request->validate(['branch_id'=>['required','integer']]);$service->branch($context,$model,$data['branch_id']);
        return response()->json(['data'=>$model->schedules()->where('branch_id',$data['branch_id'])->orderBy('day_of_week')->get(), 'defaults'=>app(\App\Services\ClinicSettingsService::class)->section($context['clinic']->id,'appointments')]);
    }
    public function update(Request $request,ClinicAccessService $access,DoctorService $service,int $doctor) {
        $context=$access->authorize($request,'doctors','doctors.schedule.update');
        $data=$request->validate(['branch_id'=>['required','integer'],'days'=>['required','array','size:7'],'days.*'=>['array:day_of_week,is_available,start_time,end_time,break_start,break_end'],'days.*.day_of_week'=>['required','integer','between:1,7','distinct'],'days.*.is_available'=>['required','boolean'],
            'days.*.start_time'=>['nullable','date_format:H:i'],'days.*.end_time'=>['nullable','date_format:H:i'],'days.*.break_start'=>['nullable','date_format:H:i'],'days.*.break_end'=>['nullable','date_format:H:i']]);
        DB::transaction(function()use($context,$service,$doctor,$data){
            Tenant::lockForUpdate()->findOrFail($context['clinic']->id);$model=$service->find($context,$doctor);$service->branch($context,$model,$data['branch_id']);
            foreach($data['days'] as $index=>$day){
                $start=$day['start_time']??null;$end=$day['end_time']??null;$breakStart=$day['break_start']??null;$breakEnd=$day['break_end']??null;
                if($day['is_available']){
                    if(!$start||!$end||$start>=$end)throw ValidationException::withMessages(["days.$index.start_time"=>'Provide a start time before the end time. Overnight shifts are not supported.']);
                    if(($breakStart||$breakEnd)&&(!$breakStart||!$breakEnd||$breakStart<$start||$breakEnd>$end||$breakStart>=$breakEnd))throw ValidationException::withMessages(["days.$index.break_start"=>'The complete break must fall inside the working hours.']);
                    if($model->schedules()->where('branch_id','!=',$data['branch_id'])->where('day_of_week',$day['day_of_week'])->where('is_available',true)->where('start_time','<',$end)->where('end_time','>',$start)->exists())throw ValidationException::withMessages(["days.$index.start_time"=>'These hours overlap a schedule at another branch.']);
                }
            }
            $model->schedules()->where('branch_id',$data['branch_id'])->delete();
            foreach($data['days'] as $day){if(!$day['is_available'])continue;$model->schedules()->create($day+['branch_id'=>$data['branch_id']]);}
            $service->audit($model,'doctor.schedule.updated',$data['branch_id']);
        });return response()->noContent();
    }
}
