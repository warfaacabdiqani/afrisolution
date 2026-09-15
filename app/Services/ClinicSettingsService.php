<?php
namespace App\Services;
use App\Models\Tenant;
use Illuminate\Support\Facades\{Cache,DB};
use Illuminate\Support\Str;
class ClinicSettingsService {
    public function all(int $tenant): array {
        return Cache::rememberForever("clinic-settings.$tenant", fn()=>DB::table('tenant_settings')->where('tenant_id',$tenant)->get()->mapWithKeys(fn($r)=>[$r->section=>json_decode($r->values,true)])->all());
    }
    public function section(int $tenant,string $section): array {
        $defaults=collect(config("clinic_settings.$section.fields",[]))->map(fn($f)=>$f['default'])->all();
        if($section==='patients') $defaults['number_prefix']=strtoupper(substr(Str::slug(Tenant::findOrFail($tenant)->slug),0,12)).'-';
        if($section==='billing') $defaults['currency']=$this->get($tenant,'general.currency','USD');
        if($section==='security') {
            $platform=app(SystemSettingsService::class);
            $defaults['session_timeout']=min(120,(int)$platform->get('security.session_lifetime',120));
            $defaults['minimum_password_length']=max(12,(int)$platform->get('security.minimum_password_length',12));
        }
        $values=array_replace($defaults,$this->all($tenant)[$section]??[]);
        if($section==='general') { $clinic=Tenant::findOrFail($tenant); $values=array_replace($values,['name'=>$clinic->name,'code'=>$clinic->slug,'timezone'=>$clinic->timezone,'status'=>$clinic->status]); }
        return $values;
    }
    public function get(int $tenant,string $key,mixed $default=null): mixed { [$section,$field]=explode('.',$key,2); return $this->section($tenant,$section)[$field]??$default; }
    public function set(int $tenant,string $section,array $values,int $actor): array {
        return DB::transaction(function() use($tenant,$section,$values,$actor) {
            Tenant::lockForUpdate()->findOrFail($tenant);
            $before=$this->section($tenant,$section);
            if($section==='general') Tenant::whereKey($tenant)->update(collect($values)->only(['name','timezone'])->all());
            $stored=DB::table('tenant_settings')->where('tenant_id',$tenant)->where('section',$section)->value('values');
            $after=array_replace($stored?json_decode($stored,true):[],$values);
            DB::table('tenant_settings')->updateOrInsert(['tenant_id'=>$tenant,'section'=>$section],['values'=>json_encode($after),'created_at'=>now(),'updated_at'=>now()]);
            Cache::forget("clinic-settings.$tenant");
            DB::afterCommit(fn()=>Cache::forget("clinic-settings.$tenant"));
            $changed=array_keys(array_filter($values,fn($v,$k)=>($before[$k]??null)!==$v,ARRAY_FILTER_USE_BOTH));
            $event=$section==='patients'?'patient':$section;
            app(PlatformService::class)->audit($actor,"clinic.settings.$event.updated",'tenant',$tenant,['changed_fields'=>$changed]);
            return array_replace($before,$values);
        },3);
    }
    public function businessAllowed(array $c, string $section): bool {
        if ($section === 'appointments' && empty($c['business_modules']['clinical'])) return false;
        $module = $section === 'clinical' ? 'consultations' : $section;
        $requirements = config('clinic.business_module_map.'.$module, []);
        return collect($requirements)->every(fn ($key) => !empty($c['business_modules'][$key]));
    }
    public function allowed(array $c,string $section): bool {
        if (!$this->businessAllowed($c, $section)) return false;
        $definition=config("clinic_settings.$section"); if(!$definition) return false;
        $features=$definition['feature']; return !$features || collect((array)$features)->contains(fn($feature)=>!empty($c['features'][$feature]));
    }
    public function canUpdate(array $c,string $section): bool {
        $access=app(ClinicAccessService::class);
        return $access->can($c['permissions'],'clinic_settings.update') || $access->can($c['permissions'],"clinic_settings.$section.".($section==='branches'?'manage':'update'));
    }
    public function authorize($request,string $section,bool $write=false): array {
        $c=app(ClinicAccessService::class)->authorize($request,'settings');
        if (!$this->businessAllowed($c, $section)) app(ClinicAccessService::class)->deny('BUSINESS_MODULE_UNAVAILABLE', 'This settings section is not available for your business type.');
        abort_unless($this->allowed($c,$section),403,'This settings section is not included in your plan.');
        if($write) abort_unless($this->canUpdate($c,$section),403,'You cannot update this settings section.');
        return $c;
    }
    public function summary(array $c): array {
        $tenant=$c['clinic']->id; $counts=[];
        foreach(['branches','tenant_memberships','patients'] as $table) $counts[$table]=DB::table($table)->where('tenant_id',$tenant)->when($table==='tenant_memberships',fn($q)=>$q->where('status','active'))->count();
        $counts['doctors']=app(DoctorService::class)->usage($tenant);
        $counts['storage_bytes']=(int)DB::table('patient_documents')->where('tenant_id',$tenant)->sum('size') + collect($this->section($tenant,'branding'))->sum('size');
        return ['clinic'=>['name'=>$c['clinic']->name,'code'=>$c['clinic']->slug,'status'=>$c['clinic']->status,'created_at'=>$c['clinic']->created_at],
            'main_branch'=>DB::table('branches')->where('tenant_id',$tenant)->orderBy('id')->value('name'),
            'plan'=>$c['plan'],'subscription'=>$c['subscription'],'limits'=>$c['limits'],'usage'=>$counts,'features'=>$c['features'],
            'support_email'=>app(SystemSettingsService::class)->get('general.support_email')];
    }
}
