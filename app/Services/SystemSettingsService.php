<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemSettingsService
{
    private const CACHE_KEY = 'system-settings.all';
    public const SECRET_MASK = '••••••••••';
    private const SECRETS = ['email.smtp_password','notifications.sms_secret','notifications.whatsapp_secret','backup.s3_secret'];

    public function all(): array { return Cache::rememberForever(self::CACHE_KEY, fn () => SystemSetting::all()->keyBy('key')->map(fn ($row) => $this->decode($row))->all()); }
    public function get(string $key, mixed $default = null): mixed { return $this->all()[$key] ?? $default; }
    public function section(string $group, bool $maskSecrets = true): array
    {
        return SystemSetting::where('group',$group)->get()->mapWithKeys(function ($row) use ($maskSecrets) {
            $value = $row->is_encrypted && $maskSecrets ? ($row->value ? self::SECRET_MASK : null) : $this->decode($row);
            return [str($row->key)->after('.')->toString() => $value];
        })->all();
    }
    public function public(): array { return SystemSetting::where('is_public',true)->get()->mapWithKeys(fn ($row) => [$row->key=>$this->decode($row)])->all(); }
    public function setSection(string $group, array $values, array $publicKeys = []): array
    {
        $before = $this->section($group);
        foreach ($values as $name => $value) {
            $key = "$group.$name";
            $secret = in_array($key,self::SECRETS,true);
            if ($secret && ($value === null || $value === '' || $value === self::SECRET_MASK)) continue;
            SystemSetting::updateOrCreate(['key'=>$key],['group'=>$group,'value'=>$this->encode($value,$secret),'type'=>$this->type($value),'is_public'=>in_array($name,$publicKeys,true),'is_encrypted'=>$secret]);
        }
        Cache::forget(self::CACHE_KEY);
        return [$before,$this->section($group)];
    }
    private function encode(mixed $value, bool $secret): ?string { if ($value === null) return null; $encoded = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value?'1':'0') : (string)$value); return $secret ? Crypt::encryptString($encoded) : $encoded; }
    private function decode(SystemSetting $row): mixed { $value=$row->value; if ($value===null)return null; if($row->is_encrypted)$value=Crypt::decryptString($value); return match($row->type){'boolean'=>(bool)(int)$value,'integer'=>(int)$value,'array'=>json_decode($value,true),default=>$value}; }
    private function type(mixed $value): string { return is_bool($value)?'boolean':(is_int($value)?'integer':(is_array($value)?'array':'string')); }
}
