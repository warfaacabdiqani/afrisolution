<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1/clinic/appointments';

    public function test_empty_slot_diagnostics_and_schedule_setup(): void
    {
        $c = $this->clinic();
        $scheduleUrl = '/api/v1/clinic/doctors/'.$c['doctor'].'/schedule';
        $days = array_map(fn ($day) => ['day_of_week' => $day, 'is_available' => false], range(1, 7));
        $this->putJson($scheduleUrl, ['branch_id' => $c['branch'], 'days' => $days])->assertNoContent();
        $url = '/api/v1/clinic/doctors/'.$c['doctor'].'/available-slots?'.http_build_query(['branch_id' => $c['branch'], 'date' => '2026-09-12', 'duration' => 30]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.reason_code', 'schedule_missing');
        $days[5] = ['day_of_week' => 6, 'is_available' => true, 'start_time' => '08:00', 'end_time' => '11:00'];
        $this->putJson($scheduleUrl, ['branch_id' => $c['branch'], 'days' => $days])->assertNoContent();
        $this->getJson($url)->assertOk()->assertJsonPath('data.0', '08:00')->assertJsonPath('meta.reason_code', null);
        $this->getJson(str_replace('2026-09-12', '2026-09-13', $url))->assertJsonPath('meta.reason_code', 'non_working_day');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-12 09:00:00', 'UTC'));
        $this->getJson($url)->assertJsonPath('meta.reason_code', 'day_finished');
        $this->postJson('/api/v1/clinic/doctors/'.$c['doctor'].'/leaves', ['branch_id' => $c['branch'], 'start_date' => '2026-09-12', 'end_date' => '2026-09-12'])->assertCreated();
        $this->getJson($url)->assertJsonPath('meta.reason_code', 'on_leave');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-12 04:00:00', 'UTC'));
    }

    private function select(User $user, int $tenant): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($user, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant])->assertOk();
    }

    private function clinic(string $slug = 'alpha'): array
    {
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'Scheduling', 'branch_limit' => 3, 'member_limit' => 10, 'doctor_limit' => 10, 'appointment_limit' => 100, 'trial_days' => 14, 'features' => ['appointments' => true, 'patient_management' => true, 'clinicians' => true, 'multi_branch' => true]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        $owner = User::where('email', $slug.'@example.test')->firstOrFail();
        $this->select($owner, $tenant->id);
        $branch = DB::table('branches')->where('tenant_id', $tenant->id)->value('id');
        $specialty = $this->postJson('/api/v1/clinic/specialties', ['name' => 'General Practice'])->assertCreated()->json('data.id');
        $doctor = $this->postJson('/api/v1/clinic/doctors', ['availability_status' => 'available', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'primary_branch_id' => $branch, 'specialty_ids' => [$specialty]])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/clinic/doctors/'.$doctor.'/schedule', ['branch_id' => $branch, 'days' => array_map(fn ($day) => ['day_of_week' => $day, 'is_available' => true, 'start_time' => '08:00', 'end_time' => '16:00', 'break_start' => '12:00', 'break_end' => '13:00'], range(1, 7))])->assertNoContent();
        $patient = $this->postJson('/api/v1/clinic/patients', ['first_name' => 'Amina', 'last_name' => 'Yusuf', 'gender' => 'female'])->assertCreated()->json('data.id');
        $type = $this->postJson('/api/v1/clinic/appointment-types', ['name' => 'General Consultation', 'default_duration' => 30])->assertCreated()->json('data.id');

        return compact('tenant', 'owner', 'plan', 'branch', 'doctor', 'patient', 'type', 'specialty');
    }

    private function data(array $c, array $extra = []): array
    {
        return array_merge(['branch_id' => $c['branch'], 'patient_id' => $c['patient'], 'doctor_id' => $c['doctor'], 'appointment_type_id' => $c['type'], 'date' => '2026-09-12', 'start_time' => '09:00', 'duration' => 30, 'source' => 'clinic'], $extra);
    }

    private function book(array $c, array $extra = []): int
    {
        return $this->postJson(self::ROOT, $this->data($c, $extra))->assertCreated()->json('data.id');
    }

    public function test_creation_validation_edit_reschedule_and_audit(): void
    {
        $this->getJson(self::ROOT)->assertUnauthorized();
        $c = $this->clinic();
        $this->postJson(self::ROOT, [])->assertUnprocessable();
        foreach ([['tenant_id' => 999], ['duration' => 0], ['start_time' => '25:00'], ['date' => '2026-02-30'], ['source' => 'invalid'], ['appointment_type_id' => 99999]] as $invalid) {
            $this->postJson(self::ROOT, $this->data($c, $invalid))->assertUnprocessable();
        }
        $id = $this->book($c, ['reason' => 'Private reason', 'notes' => 'Private notes']);
        $this->getJson(self::ROOT.'/'.$id)->assertOk()->assertJsonPath('data.appointment_number', 'APT-000001')->assertJsonPath('data.starts_at', '2026-09-12 09:00:00')->assertJsonPath('data.ends_at', '2026-09-12 09:30:00')->assertJsonPath('data.status', 'scheduled')->assertJsonPath('data.created_by', 'Owner');
        $this->putJson(self::ROOT.'/'.$id, $this->data($c, ['reason' => 'Changed reason', 'notes' => 'Preserved notes']))->assertOk();
        $this->postJson(self::ROOT.'/'.$id.'/reschedule', $this->data($c, ['date' => '2026-09-13', 'start_time' => '10:00', 'notes' => 'Attempted overwrite']))->assertOk()->assertJsonPath('data.appointment_number', 'APT-000001')->assertJsonPath('data.starts_at', '2026-09-13 10:00:00');
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.notes', 'Preserved notes');
        $this->getJson(self::ROOT.'/'.$id.'/activity')->assertOk()->assertJsonPath('data.data.0.action', 'appointment.rescheduled');
        $logs = DB::table('platform_audit_logs')->where('action', 'like', 'appointment.%')->get()->toJson();
        $this->assertStringContainsString('previous_starts_at', $logs);
        $this->assertStringNotContainsString('Private reason', $logs);
        $this->assertStringNotContainsString('Preserved notes', $logs);
        $this->deleteJson(self::ROOT.'/'.$id)->assertMethodNotAllowed();
    }

    public function test_overlap_boundaries_patient_conflicts_and_available_slots(): void
    {
        $c = $this->clinic();
        $this->book($c);
        foreach (['09:00', '09:15', '08:45'] as $time) {
            $this->postJson(self::ROOT, $this->data($c, ['start_time' => $time]))->assertUnprocessable()->assertJsonValidationErrors('start_time');
        }
        $this->book($c, ['start_time' => '08:30']);
        $this->book($c, ['start_time' => '09:30']);
        $second = $this->postJson('/api/v1/clinic/doctors', ['availability_status' => 'available', 'first_name' => 'Fatuma', 'last_name' => 'Ali', 'primary_branch_id' => $c['branch'], 'specialty_ids' => [$c['specialty']]])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/clinic/doctors/'.$second.'/schedule', ['branch_id' => $c['branch'], 'days' => array_map(fn ($d) => ['day_of_week' => $d, 'is_available' => true, 'start_time' => '08:00', 'end_time' => '16:00'], range(1, 7))])->assertNoContent();
        $this->postJson(self::ROOT, $this->data($c, ['doctor_id' => $second]))->assertUnprocessable()->assertJsonPath('errors.start_time.0', 'This patient already has an overlapping appointment.');
        $otherPatient = $this->postJson('/api/v1/clinic/patients', ['first_name' => 'Omar', 'last_name' => 'Yusuf', 'gender' => 'male'])->assertCreated()->json('data.id');
        $this->book($c, ['doctor_id' => $second, 'patient_id' => $otherPatient]);
        $slots = $this->getJson('/api/v1/clinic/doctors/'.$c['doctor'].'/available-slots?'.http_build_query(['branch_id' => $c['branch'], 'patient_id' => $c['patient'], 'date' => '2026-09-12', 'duration' => 30]))->assertOk()->json('data');
        $this->assertContains('08:00', $slots);
        $this->assertContains('10:00', $slots);
        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('11:45', $slots);
        $this->assertNotContains('12:00', $slots);
        $id = $this->book($c, ['start_time' => '14:00']);
        $this->postJson(self::ROOT.'/'.$id.'/reschedule', $this->data($c, ['start_time' => '09:15']))->assertUnprocessable();
        $this->assertDatabaseHas('appointments', ['id' => $id, 'starts_at' => '2026-09-12 14:00:00']);
    }

    public function test_working_hours_leave_inactive_doctors_and_override(): void
    {
        $c = $this->clinic();
        foreach ([['start_time' => '07:30'], ['start_time' => '15:45'], ['start_time' => '11:45'], ['start_time' => '12:15']] as $invalid) {
            $this->postJson(self::ROOT, $this->data($c, $invalid))->assertUnprocessable();
        }
        $this->postJson(self::ROOT, $this->data($c, ['start_time' => '17:00', 'override_schedule' => true]))->assertUnprocessable();
        $this->book($c, ['start_time' => '17:00', 'override_schedule' => true, 'override_reason' => 'Extended clinic hours']);
        $this->postJson(self::ROOT, $this->data($c, ['start_time' => '17:15', 'override_schedule' => true, 'override_reason' => 'Urgent']))->assertUnprocessable();
        $this->postJson('/api/v1/clinic/doctors/'.$c['doctor'].'/leaves', ['start_date' => '2026-09-13', 'end_date' => '2026-09-13'])->assertCreated();
        $this->postJson(self::ROOT, $this->data($c, ['date' => '2026-09-13', 'override_schedule' => true, 'override_reason' => 'Urgent']))->assertUnprocessable()->assertJsonPath('errors.start_time.0', 'This clinician is on leave on this date.');
        $this->postJson('/api/v1/clinic/doctors/'.$c['doctor'].'/deactivate')->assertOk();
        $this->postJson(self::ROOT, $this->data($c))->assertUnprocessable();
    }

    public function test_workflow_timestamps_cancellation_no_show_and_walk_in(): void
    {
        $c = $this->clinic();
        $id = $this->book($c);
        $this->postJson(self::ROOT.'/'.$id.'/complete')->assertUnprocessable();
        foreach (['confirm' => 'confirmed', 'check-in' => 'checked_in', 'start-consultation' => 'in_consultation', 'complete' => 'completed'] as $action => $status) {
            $this->postJson(self::ROOT.'/'.$id.'/'.$action)->assertOk()->assertJsonPath('data.status', $status);
        }
        $record = DB::table('appointments')->find($id);
        $this->assertNotNull($record->checked_in_at);
        $this->assertNotNull($record->consultation_started_at);
        $this->assertNotNull($record->completed_at);
        $this->putJson(self::ROOT.'/'.$id, $this->data($c))->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$id.'/cancel', ['reason' => 'Changed'])->assertUnprocessable();
        $cancel = $this->book($c, ['start_time' => '10:00']);
        $this->postJson(self::ROOT.'/'.$cancel.'/cancel', [])->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$cancel.'/cancel', ['reason' => 'Patient requested another day'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('appointments', ['id' => $cancel, 'cancelled_by' => $c['owner']->id, 'cancellation_reason' => 'Patient requested another day']);
        $this->book($c, ['start_time' => '10:00']);
        $missing = $this->book($c, ['start_time' => '11:00']);
        $this->postJson(self::ROOT.'/'.$missing.'/no-show')->assertUnprocessable();
        $this->travelTo(Carbon::parse('2026-09-12 08:15:00', 'UTC'));
        $this->postJson(self::ROOT.'/'.$missing.'/no-show')->assertOk()->assertJsonPath('data.status', 'no_show');
        $walk = $this->book($c, ['start_time' => '11:00', 'source' => 'walk_in']);
        $this->getJson(self::ROOT.'/'.$walk)->assertJsonPath('data.status', 'waiting')->assertJsonPath('data.is_walk_in', true);
        $this->postJson(self::ROOT.'/'.$walk.'/check-in')->assertOk();
        $future = $this->book($c, ['date' => '2026-09-13']);
        $this->postJson(self::ROOT.'/'.$future.'/check-in')->assertUnprocessable();
    }

    public function test_tenant_branch_and_assigned_doctor_access(): void
    {
        $a = $this->clinic();
        $id = $this->book($a);
        $b = $this->clinic('beta');
        $foreign = $this->book($b);
        $this->select($a['owner'], $a['tenant']->id);
        foreach (['', '/activity'] as $path) {
            $this->getJson(self::ROOT.'/'.$foreign.$path)->assertNotFound();
        }
        $this->putJson(self::ROOT.'/'.$foreign, $this->data($a))->assertNotFound();
        $this->postJson(self::ROOT.'/'.$foreign.'/reschedule', $this->data($a))->assertNotFound();
        $this->postJson(self::ROOT.'/'.$foreign.'/cancel', ['reason' => 'Attempt'])->assertNotFound();
        foreach (['patient_id' => $b['patient'], 'doctor_id' => $b['doctor'], 'appointment_type_id' => $b['type']] as $key => $value) {
            $this->postJson(self::ROOT, $this->data($a, [$key => $value]))->assertUnprocessable();
        }
        $this->postJson(self::ROOT, $this->data($a, ['branch_id' => $b['branch']]))->assertForbidden();
        $other = DB::table('branches')->insertGetId(['tenant_id' => $a['tenant']->id, 'name' => 'Second']);
        $this->postJson(self::ROOT, $this->data($a, ['branch_id' => $other]))->assertUnprocessable();
        DB::table('doctor_branch')->insert(['tenant_id' => $a['tenant']->id, 'doctor_id' => $a['doctor'], 'branch_id' => $other]);
        $otherId = $this->book($a, ['branch_id' => $other, 'start_time' => '14:00', 'override_schedule' => true, 'override_reason' => 'Second branch clinic']);
        $membership = DB::table('tenant_memberships')->where('user_id', $a['owner']->id)->value('id');
        DB::table('tenant_memberships')->where('id', $membership)->update(['all_branches' => false]);
        DB::table('branch_memberships')->insert(['tenant_id' => $a['tenant']->id, 'membership_id' => $membership, 'branch_id' => $a['branch']]);
        $this->getJson(self::ROOT.'/'.$otherId)->assertNotFound();
        $this->getJson(self::ROOT.'?branch_id='.$other)->assertForbidden();
        $this->postJson(self::ROOT.'/'.$otherId.'/cancel', ['reason' => 'Attempt'])->assertNotFound();
        $doctorUser = User::factory()->create();
        DB::table('tenant_memberships')->insert(['tenant_id' => $a['tenant']->id, 'user_id' => $doctorUser->id, 'role' => 'doctor', 'status' => 'active', 'all_branches' => true]);
        DB::table('doctors')->where('id', $a['doctor'])->update(['user_id' => $doctorUser->id]);
        $this->select($doctorUser, $a['tenant']->id);
        $this->getJson(self::ROOT.'/'.$id)->assertOk();
        $this->postJson(self::ROOT, $this->data($a))->assertForbidden();
        DB::table('doctors')->where('id', $a['doctor'])->update(['user_id' => null]);
        $this->getJson(self::ROOT.'/'.$id)->assertNotFound();
        $this->getJson(self::ROOT)->assertJsonCount(0, 'data');
    }

    public function test_permissions_feature_subscription_and_monthly_quota(): void
    {
        $c = $this->clinic();
        $c['plan']->update(['appointment_limit' => 1]);
        $id = $this->book($c);
        $this->postJson(self::ROOT, $this->data($c, ['start_time' => '10:00']))->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->postJson(self::ROOT.'/'.$id.'/cancel', ['reason' => 'Unavailable'])->assertOk();
        $this->postJson(self::ROOT, $this->data($c))->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->book($c, ['date' => '2026-10-01']);
        $c['plan']->update(['appointment_limit' => 10]);
        DB::table('tenant_memberships')->where('user_id', $c['owner']->id)->update(['permissions' => json_encode(['appointments.view', 'appointments.view_all'])]);
        $this->getJson(self::ROOT)->assertOk();
        $this->postJson(self::ROOT, $this->data($c))->assertForbidden();
        $this->putJson(self::ROOT.'/'.$id, $this->data($c))->assertForbidden();
        $this->postJson(self::ROOT.'/'.$id.'/reschedule', $this->data($c))->assertForbidden();
        $this->postJson(self::ROOT.'/'.$id.'/cancel', ['reason' => 'Attempt'])->assertForbidden();
        DB::table('tenant_memberships')->where('user_id', $c['owner']->id)->update(['permissions' => json_encode(['appointments.view', 'appointments.view_all', 'appointments.create'])]);
        $this->postJson(self::ROOT, $this->data($c, ['start_time' => '17:00', 'override_schedule' => true, 'override_reason' => 'Attempt']))->assertForbidden();
        $c['plan']->update(['features' => ['appointments' => false]]);
        $this->getJson(self::ROOT)->assertForbidden();
        $c['plan']->update(['features' => ['appointments' => true]]);
        DB::table('subscriptions')->where('tenant_id', $c['tenant']->id)->update(['status' => 'expired']);
        $this->getJson(self::ROOT)->assertForbidden();
    }

    public function test_calendar_search_pagination_today_and_dashboard_integration(): void
    {
        $c = $this->clinic();
        $id = $this->book($c, ['notes' => 'Confidential booking note']);
        $this->postJson(self::ROOT.'/'.$id.'/check-in')->assertOk();
        for ($i = 1; $i < 26; $i++) {
            $this->book($c, ['date' => Carbon::parse('2026-09-12')->addDays($i)->toDateString()]);
        }
        $this->getJson(self::ROOT.'?patient_id='.$c['patient'])->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26)->assertJsonMissingPath('data.0.notes');
        $this->getJson(self::ROOT.'?page=2')->assertJsonCount(1, 'data');
        $this->getJson(self::ROOT.'/calendar?start=2026-09-12&end=2026-09-18&doctor_id='.$c['doctor'])->assertOk()->assertJsonCount(7, 'data')->assertJsonCount(7, 'counts');
        $this->getJson(self::ROOT.'/calendar?start=2026-01-01&end=2026-12-31')->assertUnprocessable();
        $this->getJson(self::ROOT.'?search=APT-000001')->assertJsonCount(1,'data');
        $this->getJson(self::ROOT.'?search=Amina%20Yusuf')->assertJsonPath('meta.total',26);
        $this->getJson(self::ROOT.'?search=Ahmed%20Hassan')->assertJsonPath('meta.total',26);
        $this->getJson(self::ROOT.'/today?status=scheduled')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('summary.checked_in',1);
        $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonPath('data.stats.today_appointments',1)->assertJsonPath('data.today_appointments.0.id',$id)->assertJsonPath('data.calendar.0','2026-09-12');
    }
}
