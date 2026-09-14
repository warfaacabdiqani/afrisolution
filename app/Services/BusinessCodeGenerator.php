<?php

namespace App\Services;

use App\Models\BusinessType;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BusinessCodeGenerator
{
    public static function rules(): array
    {
        return ['code_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'business_code_start' => ['required', 'integer', 'min:1', 'max:999999999999']];
    }

    public function settings(bool $lock = false): array
    {
        $query = DB::table('system_settings')->whereIn('key', ['general.code_prefix', 'general.business_code_start']);
        $values = ($lock ? $query->lockForUpdate() : $query)->pluck('value', 'key');
        $prefix = strtoupper(trim($values['general.code_prefix'] ?? ''));
        return ['code_prefix' => $prefix === '' ? 'AFRI' : $prefix,
            'business_code_start' => (int) ($values['general.business_code_start'] ?? 200001)];
    }

    // All writers take this lock first, including settings changes. SQLite needs
    // a write lock before any reads; databases with row locks use FOR UPDATE.
    public function lockSequence(): object
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Business code allocation requires a transaction.');
        }
        $query = DB::table('platform_sequences')->where('key', 'business_code');
        if (DB::connection()->getDriverName() === 'sqlite') {
            $query->update(['current_value' => DB::raw('current_value')]);
        }
        return $query->lockForUpdate()->firstOrFail();
    }

    public function validateSettingsChange(array $values): array
    {
        $sequence = $this->lockSequence();
        $before = $this->settings(true);
        if ((int) $values['business_code_start'] !== $before['business_code_start']
            && (int) $values['business_code_start'] < $sequence->current_value) {
            throw ValidationException::withMessages(['business_code_start' => 'Starting sequence cannot be lower than the current generated sequence.']);
        }
        return $before;
    }

    public function preview(BusinessType $type, array $overrides = []): string
    {
        $settings = Validator::make(array_replace($this->settings(), $overrides), self::rules())->validate();
        $current = (int) DB::table('platform_sequences')->where('key', 'business_code')->value('current_value');
        return $this->availableCode($type, $settings, $current)[0];
    }

    public function generate(BusinessType $type): string
    {
        $sequence = $this->lockSequence();
        $settings = Validator::make($this->settings(true), self::rules())->validate();
        [$code, $next] = $this->availableCode($type, $settings, (int) $sequence->current_value);
        DB::table('platform_sequences')->where('key', 'business_code')->update(['current_value' => $next, 'updated_at' => now()]);
        return $code;
    }

    private function availableCode(BusinessType $type, array $settings, int $current): array
    {
        $next = $current > 0 ? $current + 1 : (int) $settings['business_code_start'];
        $abbreviation = config('business_types.'.$type->slug.'.code_prefix', 'BUS');
        Validator::make(['business_type_code' => $abbreviation], ['business_type_code' => ['required', 'regex:/^[A-Z]{2,5}$/']])->validate();
        do {
            $code = $settings['code_prefix'].'-'.$abbreviation.'-'.$next++;
        } while (Tenant::where('slug', $code)->exists());
        return [$code, $next - 1];
    }
}
