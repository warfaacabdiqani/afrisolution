<?php

/** Test-only records; callers use isolated test databases. No production issuance shortcuts. */
function clinicBillingFixture(string $name = 'clinic-billing', string $business = 'clinic'): array
{
    (new \Database\Seeders\BusinessTypeSeeder)->run();
    $actor = \App\Models\User::factory()->create();
    $features = ['appointments', 'billing', 'patient_management', 'clinicians', 'multi_branch'];
    if ($business === 'dental') $features[] = 'emr';
    $plan = \App\Models\Plan::create(['name' => 'Clinic Billing Test', 'branch_limit' => 3, 'member_limit' => 10, 'trial_days' => 14,
        'features' => array_fill_keys($features, true)]);
    $tenant = app(\App\Services\PlatformService::class)->createTenant(['name' => $name, 'timezone' => 'Africa/Nairobi',
        'business_type_id' => \App\Models\BusinessType::where('slug', $business)->value('id'), 'plan_id' => $plan->id,
        'owner_name' => 'Clinic Owner', 'owner_email' => $name.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
    $user = \App\Models\User::where('email', $name.'@example.test')->value('id');
    $db = \Illuminate\Support\Facades\DB::class;
    $branch = $db::table('branches')->where('tenant_id', $tenant->id)->value('id');
    $base = ['tenant_id' => $tenant->id, 'created_by' => $user, 'updated_by' => $user, 'created_at' => now(), 'updated_at' => now()];
    $patient = $db::table('patients')->insertGetId($base + ['registration_branch_id' => $branch, 'patient_number' => 'PAT-1',
        'first_name' => 'Amina', 'last_name' => 'Yusuf', 'gender' => 'female', 'registered_at' => now()]);
    $doctor = $db::table('doctors')->insertGetId($base + ['primary_branch_id' => $branch, 'doctor_number' => 'DOC-1',
        'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'consultation_fee' => 20, 'user_id' => $user]);
    $db::table('doctor_branch')->insert(['tenant_id' => $tenant->id, 'doctor_id' => $doctor, 'branch_id' => $branch]);
    $appointment = $db::table('appointments')->insertGetId($base + ['branch_id' => $branch, 'patient_id' => $patient, 'doctor_id' => $doctor,
        'appointment_number' => 'APT-1', 'starts_at' => now($tenant->timezone)->startOfDay()->addHours(9),
        'ends_at' => now($tenant->timezone)->startOfDay()->addHours(10), 'status' => 'completed', 'completed_at' => now()]);
    return ['tenant' => $tenant->id, 'user' => $user, 'branch' => $branch, 'patient' => $patient, 'doctor' => $doctor,
        'appointment' => $appointment, 'plan' => $plan->id];
}
