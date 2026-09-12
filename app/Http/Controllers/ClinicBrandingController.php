<?php
namespace App\Http\Controllers;
use App\Http\Requests\ClinicBrandingRequest;
use App\Models\Tenant;
use App\Services\ClinicSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
class ClinicBrandingController extends Controller {
    public function store(ClinicBrandingRequest $r,ClinicSettingsService $s) {
        $c=$s->authorize($r,'branding',true); $tenant=$c['clinic']->id; $key=$r->input('asset'); $path=null;
        try {
            $values=DB::transaction(function() use($r,$s,$c,$tenant,$key,&$path) {
                Tenant::lockForUpdate()->findOrFail($tenant);
                $current=$s->section($tenant,'branding');
                $used=DB::table('patient_documents')->where('tenant_id',$tenant)->sum('size')+collect($current)->sum('size');
                $limit=$c['limits']['storage_limit_gb'];
                if($limit!==null && $used-($current[$key]['size']??0)+$r->file('file')->getSize()>$limit*1073741824) throw ValidationException::withMessages(['file'=>'Your clinic storage limit has been reached.']);
                $path=$r->file('file')->store("clinic-branding/$tenant",'patient_private');
                $values=$s->set($tenant,'branding',[$key=>['path'=>$path,'mime'=>$r->file('file')->getMimeType(),'size'=>$r->file('file')->getSize()]],$r->user()->id);
                if(isset($current[$key]['path'])) DB::afterCommit(fn()=>Storage::disk('patient_private')->delete($current[$key]['path']));
                return $values;
            },3);
        } catch(\Throwable $e) { if($path) Storage::disk('patient_private')->delete($path); throw $e; }
        return response()->json(['data'=>array_keys($values),'message'=>'Clinic settings updated successfully.']);
    }
    public function show(Request $r,ClinicSettingsService $s,string $asset) {
        $c=$s->authorize($r,'branding'); $value=$s->section($c['clinic']->id,'branding')[$asset]??null;
        abort_unless($value && Storage::disk('patient_private')->exists($value['path']),404);
        return response(Storage::disk('patient_private')->get($value['path']))->header('Content-Type',$value['mime'])->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }
}
