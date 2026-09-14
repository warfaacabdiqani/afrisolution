<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPlatformPermission('settings.update') ?? false; }
    public function rules(): array
    {
        return match ($this->route('section')) {
            'general' => ['platform_name'=>['required','string','max:100'],'platform_url'=>['required','url','max:255'],'support_email'=>['nullable','email','max:255'],'support_phone'=>['nullable','string','max:40'],'organization_name'=>['nullable','string','max:150'],'default_trial_days'=>['required','integer','min:0','max:365'],'default_plan_id'=>['nullable','integer','exists:plans,id'],'registration_enabled'=>['required','boolean'],'platform_status'=>['required',Rule::in(['active','maintenance'])],'code_prefix'=>['nullable','string','max:20','regex:/^[A-Za-z0-9-]+$/'],'code_contains'=>['nullable','string','max:40','regex:/^[A-Za-z0-9-]+$/']],
            'branding' => ['display_name'=>['required','string','max:100'],'footer_text'=>['nullable','string','max:255'],'logo'=>['nullable','image','mimes:png,jpg,jpeg,webp,svg','max:2048'],'small_logo'=>['nullable','image','mimes:png,jpg,jpeg,webp,svg','max:1024'],'favicon'=>['nullable','file','mimes:png,ico','max:512'],'login_logo'=>['nullable','image','mimes:png,jpg,jpeg,webp,svg','max:2048']],
            'localization' => ['timezone'=>['required','timezone:all'],'currency'=>['required','string','size:3'],'currency_symbol'=>['required','string','max:5'],'date_format'=>['required',Rule::in(['DD/MM/YYYY','MM/DD/YYYY','YYYY-MM-DD'])],'time_format'=>['required',Rule::in(['12','24'])],'language'=>['required',Rule::in(['en'])]],
            'email' => ['mailer'=>['required',Rule::in(['smtp','log','array'])],'smtp_host'=>['nullable','required_if:mailer,smtp','string','max:255'],'smtp_port'=>['nullable','required_if:mailer,smtp','integer','min:1','max:65535'],'smtp_username'=>['nullable','string','max:255'],'smtp_password'=>['nullable','string','max:1000'],'encryption'=>['nullable',Rule::in(['tls','ssl'])],'from_email'=>['required','email','max:255'],'from_name'=>['required','string','max:100']],
            'notifications' => ['email_enabled'=>['required','boolean'],'appointment_enabled'=>['required','boolean'],'subscription_enabled'=>['required','boolean'],'trial_expiry_enabled'=>['required','boolean'],'security_enabled'=>['required','boolean'],'sms_enabled'=>['required','boolean'],'sms_provider'=>['nullable','string','max:100'],'sms_sender_id'=>['nullable','string','max:30'],'sms_secret'=>['nullable','string','max:1000'],'whatsapp_enabled'=>['required','boolean'],'whatsapp_provider'=>['nullable','string','max:100'],'whatsapp_secret'=>['nullable','string','max:1000']],
            'security' => ['minimum_password_length'=>['required','integer','min:8','max:128'],'require_uppercase'=>['required','boolean'],'require_lowercase'=>['required','boolean'],'require_number'=>['required','boolean'],'require_special'=>['required','boolean'],'session_lifetime'=>['required','integer','min:15','max:1440'],'login_attempt_limit'=>['required','integer','min:3','max:20'],'lockout_duration'=>['required','integer','min:1','max:1440'],'admin_2fa_required'=>['required','boolean'],'security_notifications'=>['required','boolean']],
            'backup' => ['storage_driver'=>['required',Rule::in(['local','s3'])],'s3_key'=>['nullable','required_if:storage_driver,s3','string','max:255'],'s3_secret'=>['nullable','string','max:1000'],'s3_region'=>['nullable','required_if:storage_driver,s3','string','max:100'],'s3_bucket'=>['nullable','required_if:storage_driver,s3','string','max:255'],'s3_endpoint'=>['nullable','url','max:255'],'automatic_backups'=>['required','boolean'],'backup_frequency'=>['required',Rule::in(['disabled','daily','weekly'])],'backup_retention'=>['required',Rule::in([7,14,30,90])]],
            default => [],
        };
    }
}
