<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSettingsRequest;
use App\Models\Plan;
use App\Services\PlatformService;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SystemSettingsController extends Controller
{
    private const DEFAULTS = [
        'general'=>['platform_name'=>'Afri Clinic','platform_url'=>'http://localhost','support_email'=>null,'support_phone'=>null,'organization_name'=>'Afri Clinic Healthcare SaaS','default_trial_days'=>14,'default_plan_id'=>null,'registration_enabled'=>true,'platform_status'=>'active','code_prefix'=>'AFRI','business_code_start'=>200001,'code_contains'=>null],
        'branding'=>['display_name'=>'Afri Clinic','footer_text'=>'Healthcare SaaS','logo'=>null,'small_logo'=>null,'favicon'=>null,'login_logo'=>null],
        'localization'=>['timezone'=>'Africa/Nairobi','currency'=>'USD','currency_symbol'=>'$','date_format'=>'DD/MM/YYYY','time_format'=>'12','language'=>'en'],
        'email'=>['mailer'=>'log','smtp_host'=>null,'smtp_port'=>587,'smtp_username'=>null,'smtp_password'=>null,'encryption'=>'tls','from_email'=>'hello@example.com','from_name'=>'Afri Clinic'],
        'notifications'=>['email_enabled'=>true,'appointment_enabled'=>true,'subscription_enabled'=>true,'trial_expiry_enabled'=>true,'security_enabled'=>true,'sms_enabled'=>false,'sms_provider'=>null,'sms_sender_id'=>null,'sms_secret'=>null,'whatsapp_enabled'=>false,'whatsapp_provider'=>null,'whatsapp_secret'=>null,'whatsapp_app_id'=>null,'whatsapp_verify_token'=>null],
        'security'=>['minimum_password_length'=>12,'require_uppercase'=>true,'require_lowercase'=>true,'require_number'=>true,'require_special'=>false,'session_lifetime'=>120,'login_attempt_limit'=>5,'lockout_duration'=>15,'admin_2fa_required'=>false,'security_notifications'=>true],
        'backup'=>['storage_driver'=>'local','s3_key'=>null,'s3_secret'=>null,'s3_region'=>null,'s3_bucket'=>null,'s3_endpoint'=>null,'automatic_backups'=>false,'backup_frequency'=>'disabled','backup_retention'=>30],
    ];

    public function index(Request $request, SystemSettingsService $settings)
    {
        abort_unless($request->user()->hasPlatformPermission('settings.view'),403);
        $sections=[]; foreach(self::DEFAULTS as $group=>$defaults)$sections[$group]=array_replace($defaults,$settings->section($group));
        $sections['general'] = array_replace($sections['general'], app(\App\Services\BusinessCodeGenerator::class)->settings());
        return response()->json(['data'=>$sections,'plans'=>Plan::where('status','active')->orderBy('name')->get(['id','name'])]);
    }
    public function publicSettings(SystemSettingsService $settings)
    {
        $values = $settings->public();
        foreach (['platform_name', 'organization_name', 'support_email', 'support_phone'] as $key) {
            $values['general.'.$key] = $settings->get('general.'.$key, self::DEFAULTS['general'][$key]);
        }
        return response()->json(['data' => $values]);
    }
    public function update(SystemSettingsRequest $request, string $section, SystemSettingsService $settings, PlatformService $platform)
    {
        abort_unless(isset(self::DEFAULTS[$section]),404); $values=$request->validated();
        if($section==='branding')foreach(['logo','small_logo','favicon','login_logo'] as $key)if($request->hasFile($key))$values[$key]='/storage/'.$request->file($key)->store('branding','public');
        return DB::transaction(function () use ($request, $section, $settings, $platform, $values) {
            $codes = app(\App\Services\BusinessCodeGenerator::class);
            $codeBefore = $section === 'general' ? $codes->validateSettingsChange($values) : [];
            $publicKeys = match ($section) {
                'branding' => ['display_name', 'footer_text', 'logo', 'small_logo', 'favicon', 'login_logo'],
                'localization' => ['language'],
                'general' => ['platform_name'],
                default => [],
            };
            [$before, $after] = $settings->setSection($section, $values, $publicKeys);
            $platform->audit($request->user()->id, "settings.$section.updated", 'system_settings', 0, ['section' => $section], $before, $after);
            if ($section === 'general') {
                $codeAfter = $codes->settings(true);
                if ($codeBefore !== $codeAfter) {
                    $platform->audit($request->user()->id, 'platform.business_code_settings.updated', 'system_settings', 0, [], $codeBefore, $codeAfter);
                }
            }
            return response()->json(['message' => 'Settings updated successfully.', 'data' => array_replace(self::DEFAULTS[$section], $after)]);
        }, 5);
    }
    public function testEmail(Request $request, SystemSettingsService $settings)
    {
        abort_unless($request->user()->hasPlatformPermission('settings.update'),403); $data=$request->validate(['email'=>['required','email']]);
        app(\App\Services\PlatformMailConfigurator::class)->apply();
        $platformName = $settings->get('general.platform_name', self::DEFAULTS['general']['platform_name']);
        Mail::raw("Your {$platformName} email configuration is working.",fn($message)=>$message->to($data['email'])->subject('Email configuration test'));
        return response()->json(['message'=>'Email sent successfully.']);
    }
    public function systemInfo(Request $request)
    {
        abort_unless($request->user()->hasPlatformPermission('settings.view'),403);
        return response()->json(['data'=>['application_version'=>config('app.version','1.0.0'),'laravel_version'=>app()->version(),'php_version'=>PHP_VERSION,'database'=>DB::connection()->getDriverName(),'environment'=>app()->environment(),'queue'=>config('queue.default'),'maintenance'=>app(SystemSettingsService::class)->get('general.platform_status','active')==='maintenance']]);
    }
    public function clearCache(Request $request, PlatformService $platform)
    {
        abort_unless($request->user()->hasPlatformPermission('settings.maintenance'),403); $data=$request->validate(['cache'=>['required','in:application,config,route,view']]);
        Artisan::call(match($data['cache']){'application'=>'cache:clear','config'=>'config:clear','route'=>'route:clear','view'=>'view:clear'});
        $platform->audit($request->user()->id,'settings.cache.cleared','system_settings',0,['cache'=>$data['cache']]);
        return response()->json(['message'=>ucfirst($data['cache']).' cache cleared successfully.']);
    }
    public function maintenance(Request $request, SystemSettingsService $settings, PlatformService $platform)
    {
        abort_unless($request->user()->hasPlatformPermission('settings.maintenance'),403);
        $data=$request->validate(['enabled'=>['required','boolean'],'message'=>['nullable','string','max:500']]);
        [$before,$after]=$settings->setSection('general',['platform_status'=>$data['enabled']?'maintenance':'active','maintenance_message'=>$data['message']??null],['platform_name']);
        $platform->audit($request->user()->id,$data['enabled']?'maintenance.enabled':'maintenance.disabled','system_settings',0,['section'=>'maintenance'],$before,$after);
        return response()->json(['message'=>$data['enabled']?'Maintenance mode enabled.':'Maintenance mode disabled.','data'=>['maintenance'=>$data['enabled'],'message'=>$data['message']??null]]);
    }
}
