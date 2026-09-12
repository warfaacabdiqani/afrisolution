<?php
namespace App\Http\Controllers;
use App\Http\Requests\ClinicSettingsRequest;
use App\Services\{ClinicSettingsService,ClinicAccessService};
use Illuminate\Http\Request;
class ClinicSettingsController extends Controller {
    public function index(Request $r,ClinicSettingsService $s,ClinicAccessService $a) {
        $c=$a->authorize($r,'settings'); $sections=[];
        foreach(config('clinic_settings') as $key=>$schema) if($s->allowed($c,$key)) {
            $schema['fields']=collect($schema['fields'])->map(fn($f)=>collect($f)->except('rules')->all())->all();
            $values=$s->section($c['clinic']->id,$key);
            if($key==='notifications') foreach(['email','sms','whatsapp'] as $channel) if(empty($c['features'][$channel.'_notifications'])) { $schema['fields'][$channel.'_enabled']['locked']=true; $schema['fields'][$channel.'_enabled']['help']='Not included in the current plan.'; $values[$channel.'_enabled']=false; }
            if($key==='branding') $values=array_fill_keys(array_keys($values),true);
            $sections[$key]=$schema+['values'=>$values,'can_update'=>$s->canUpdate($c,$key)];
        }
        return response()->json(['data'=>['sections'=>$sections,'summary'=>$s->summary($c),'branches'=>$c['branches'],'timezones'=>timezone_identifiers_list()]]);
    }
    public function update(ClinicSettingsRequest $r,ClinicSettingsService $s,string $section) {
        abort_if(in_array($section,['branding','branches','subscription']),405);
        $c=$s->authorize($r,$section,true); $values=$r->safe()->except('tenant_id');
        return response()->json(['data'=>$s->set($c['clinic']->id,$section,$values,$r->user()->id),'message'=>'Clinic settings updated successfully.']);
    }
    public function preview(Request $r,ClinicSettingsService $s,string $kind) {
        $c=$s->authorize($r,'documents');
        $s->authorize($r,$kind==='prescription'?'clinical':'billing');
        $data=app(\App\Services\ClinicDocumentService::class)->data($c['clinic']->id,$kind);
        $billing=$s->section($c['clinic']->id,'billing');
        $number=$kind==='prescription'?'RX-000001':$billing[$kind.'_prefix'].str_pad('1',$billing['number_length'],'0',STR_PAD_LEFT);
        return response()->view('clinic.document-preview',$data+compact('billing','number'))->header('Cache-Control','private, no-store');
    }
    public function subscription(Request $r,ClinicSettingsService $s) {
        return response()->json(['data'=>$s->summary($s->authorize($r,'subscription'))]);
    }
}
