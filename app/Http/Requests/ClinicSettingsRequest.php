<?php
namespace App\Http\Requests;
use App\Services\{ClinicSettingsService,SystemSettingsService};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ClinicSettingsRequest extends FormRequest {
    public function authorize(): bool { app(ClinicSettingsService::class)->authorize($this,$this->route('section'),true); return true; }
    public function rules(): array {
        $section=$this->route('section'); $rules=['tenant_id'=>'prohibited'];
        foreach(config("clinic_settings.$section.fields",[]) as $key=>$field) $rules[$key]=$field['rules'];
        if($section==='billing') $rules['payment_methods.*']=['string','distinct',Rule::in(['cash','card','mobile_money','bank_transfer'])];
        return $rules;
    }
    public function after(): array { return [function($v) {
        $section=$this->route('section'); $c=app(ClinicSettingsService::class)->authorize($this,$section,true);
        $known=array_keys(config("clinic_settings.$section.fields",[]));
        foreach(array_keys($this->all()) as $key) if(!in_array($key,$known) && $key!=='tenant_id') $v->errors()->add($key,'This setting is not supported.');
        if($section==='security') {
            $platform=app(SystemSettingsService::class);
            if($this->integer('session_timeout')>(int)$platform->get('security.session_lifetime',120)) $v->errors()->add('session_timeout','Timeout cannot exceed the platform policy.');
            if($this->integer('minimum_password_length')<max(12,(int)$platform->get('security.minimum_password_length',12))) $v->errors()->add('minimum_password_length','Password length cannot weaken the platform policy.');
        }
        if($section==='pharmacy' && $this->filled('default_branch_id') && !$c['branches']->contains('id',$this->integer('default_branch_id'))) $v->errors()->add('default_branch_id','Select an accessible branch in this clinic.');
        if($section==='general' && $this->input('timezone')!==$c['clinic']->timezone && \Illuminate\Support\Facades\DB::table('appointments')->where('tenant_id',$c['clinic']->id)->exists()) $v->errors()->add('timezone','Existing appointments use this timezone. Contact the platform administrator to migrate scheduled times before changing it.');
        if($section==='notifications') foreach(['email','sms','whatsapp'] as $channel) if($this->boolean($channel.'_enabled') && empty($c['features'][$channel.'_notifications'])) $v->errors()->add($channel.'_enabled','This notification channel is not included in your plan.');
    }]; }
}
