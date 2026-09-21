<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrationRules
{
    private const STEP_FIELDS = [
        1 => ['owner_name', 'owner_email', 'owner_password', 'owner_password_confirmation'],
        2 => ['business_type_id'],
        3 => ['plan_id'],
        4 => ['name', 'timezone'],
    ];

    public static function all(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'owner_password' => ['required', Password::min(12)->mixedCase()->numbers()],
            // Validate the same confirmation requirement on its own field for inline feedback.
            'owner_password_confirmation' => ['required', 'same:owner_password'],
            'business_type_id' => ['required', 'integer', Rule::exists('business_types', 'id')->where('status', 'active')],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where(fn ($query) => $query->where('status', 'active')->where('trial_days', '>', 0))],
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    public static function forStep(int $step): array
    {
        return array_intersect_key(self::all(), array_flip(self::STEP_FIELDS[$step]));
    }
}
