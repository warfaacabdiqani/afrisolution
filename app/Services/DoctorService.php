<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorService
{
    public const AVAILABILITY_SQL = "CASE WHEN doctors.status = 'inactive' THEN 'unavailable' WHEN EXISTS (SELECT 1 FROM doctor_leaves dl WHERE dl.tenant_id = doctors.tenant_id AND dl.doctor_id = doctors.id AND dl.status != 'cancelled' AND dl.start_date <= ? AND dl.end_date >= ? AND (dl.branch_id IS NULL OR dl.branch_id = ?)) THEN 'on_leave' ELSE doctors.availability_status END";
    public function visible(array $context)
    {
        return Doctor::whereHas('branches', fn ($q) => $q->whereIn('branches.id', $context['branches']->pluck('id')));
    }
    public function find(array $context, int $id, bool $manage = false): Doctor
    {
        $doctor = $this->visible($context)->with(['branches', 'specialties'])->findOrFail($id);
        if ($manage) abort_unless($doctor->branches->pluck('id')->diff($context['branches']->pluck('id'))->isEmpty(), 403, 'Managing this profile requires access to all its assigned branches.');
        return $doctor;
    }
    public function branch(array $context, Doctor $doctor, int $branch): void
    {
        abort_unless($context['branches']->contains('id', $branch) && $doctor->branches()->where('branches.id', $branch)->exists(), 403, 'Select an authorized branch assigned to this doctor.');
    }
    public function audit(Doctor $doctor, string $action, ?int $branch = null): void
    {
        app(PlatformService::class)->audit(request()->user()->id, $action, 'tenant', $doctor->tenant_id, ['doctor_id' => $doctor->id, 'branch_id' => $branch ?? request()->session()->get('branch_id')]);
    }
    public function usage(int $tenant): int
    {
        $profiles = DB::table('doctors')->where('tenant_id', $tenant)->count();
        $unlinked = DB::table('tenant_memberships')->where('tenant_id', $tenant)->where('role', 'doctor')->where('status', 'active')
            ->whereNotIn('user_id', DB::table('doctors')->where('tenant_id', $tenant)->whereNotNull('user_id')->select('user_id'))->count();
        return $profiles + $unlinked;
    }
    public function availabilityQuery(array $context, int $branch)
    {
        return $this->visible($context)->whereHas('branches', fn ($q) => $q->where('branches.id', $branch))
            ->select('doctors.*')->selectRaw(self::AVAILABILITY_SQL.' AS effective_availability', [$context['today'], $context['today'], $branch]);
    }
    public function availableAt(array $context, Doctor $doctor, int $branch, \Illuminate\Support\Carbon $time): bool
    {
        $this->branch($context, $doctor, $branch);
        $local = $time->copy()->setTimezone($context['clinic']->timezone);
        $dateContext = array_merge($context, ['today' => $local->toDateString()]);
        if (!$this->availabilityQuery($dateContext, $branch)->where('doctors.id', $doctor->id)->whereRaw(self::AVAILABILITY_SQL." = 'available'", [$dateContext['today'], $dateContext['today'], $branch])->exists()) return false;
        $clock = $local->format('H:i:s');
        return $doctor->schedules()->where('branch_id', $branch)->where('day_of_week', $local->isoWeekday())->where('is_available', true)
            ->where('start_time', '<=', $clock)->where('end_time', '>', $clock)
            ->where(fn ($q) => $q->whereNull('break_start')->orWhere('break_start', '>', $clock)->orWhere('break_end', '<=', $clock))->exists();
    }
    public function save(array $data, array $context, ?Doctor $doctor = null): Doctor
    {
        return DB::transaction(function () use ($data, $context, $doctor) {
            $tenant = Tenant::lockForUpdate()->findOrFail($context['clinic']->id);
            $creating = !$doctor;
            if ($doctor) $doctor = $this->find($context, $doctor->id, true);
            $branchIds = array_values(array_unique(array_merge([$data['primary_branch_id']], $data['branch_ids'] ?? [])));
            if (collect($branchIds)->diff($context['branches']->pluck('id'))->isNotEmpty()) throw ValidationException::withMessages(['branch_ids' => 'Select only branches you are authorized to manage.']);
            $accountMode = $data['account_mode'] ?? 'none';
            $linkUser = $accountMode === 'existing' ? ($data['user_id'] ?? null) : null;
            $existingSeat = $linkUser && DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('user_id', $linkUser)->where('role', 'doctor')->where('status', 'active')->exists();
            $limit = DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')->where('tenant_id', $tenant->id)->value('doctor_limit');
            if ($creating && $limit !== null && $this->usage($tenant->id) + ($existingSeat ? 0 : 1) > $limit) throw ValidationException::withMessages(['plan' => "Your current subscription allows up to {$limit} doctors. Upgrade your plan to add more doctors."]);
            if ($creating && !($data['confirm_duplicate'] ?? false)) {
                $matches = Doctor::where(function ($q) use ($data) { $q->whereRaw('1 = 0'); foreach (['phone','email','license_number'] as $field) if (!empty($data[$field])) $q->orWhere($field, $data[$field]); })->limit(5)->get();
                if ($matches->isNotEmpty()) throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => 'A similar clinician profile already exists. Review it before continuing.', 'duplicates' => $matches->filter(fn ($d) => $d->branches()->whereIn('branches.id', $context['branches']->pluck('id'))->exists())->map(fn ($d) => ['id' => $d->id, 'full_name' => $d->full_name, 'doctor_number' => $d->doctor_number])->values()], 409));
            }
            $doctor ??= new Doctor();
            $doctor->fill(collect($data)->only($doctor->getFillable())->all());
            if ($creating) {
                $tenant->increment('doctor_sequence'); $doctor->doctor_number = 'DOC-'.str_pad($tenant->doctor_sequence, 5, '0', STR_PAD_LEFT);
                $doctor->created_by = request()->user()->id;
                $doctor->status = $data['status'] ?? 'active';
            }
            $doctor->primary_branch_id = $data['primary_branch_id']; $doctor->updated_by = request()->user()->id;
            if ($accountMode !== 'none') {
                if ($doctor->user_id && $doctor->user_id !== (int) $linkUser) throw ValidationException::withMessages(['user_id' => 'This clinician already has a linked account. Manage the account separately.']);
                abort_unless(app(ClinicAccessService::class)->can($context['permissions'], 'staff.manage'), 403, 'Staff management permission is required to link or create accounts.');
                if ($accountMode === 'create') {
                    $sub = DB::table('subscriptions')->where('tenant_id', $tenant->id)->first();
                    if (DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('status', 'active')->count() >= $sub->member_limit) throw ValidationException::withMessages(['account_email' => 'Your clinic member limit has been reached.']);
                    $permissions = $data['account_permissions'] ?? config('clinic.roles.doctor');
                    foreach ($permissions as $permission) abort_unless(app(ClinicAccessService::class)->can($context['permissions'], $permission), 403, 'You cannot grant permissions you do not hold.');
                    $user = User::create(['name' => $doctor->full_name, 'email' => $data['account_email'], 'password' => $data['password']]);
                    $user->markEmailAsVerified();
                    $membership = DB::table('tenant_memberships')->insertGetId(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'doctor', 'status' => 'active', 'permissions' => json_encode($permissions), 'all_branches' => false, 'created_at' => now(), 'updated_at' => now()]);
                    foreach ($branchIds as $branch) DB::table('branch_memberships')->insert(['tenant_id' => $tenant->id, 'membership_id' => $membership, 'branch_id' => $branch]);
                    $linkUser = $user->id;
                }
                $membership = DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('user_id', $linkUser)->where('status', 'active')->first();
                if (!$membership || !User::where('id', $linkUser)->where('status', 'active')->exists()) throw ValidationException::withMessages(['user_id' => 'Select an active user belonging to this clinic.']);
                if (!$membership->all_branches && collect($branchIds)->diff(DB::table('branch_memberships')->where('tenant_id', $tenant->id)->where('membership_id', $membership->id)->pluck('branch_id'))->isNotEmpty()) throw ValidationException::withMessages(['user_id' => 'The account must already have access to every assigned branch. Update its branch access first.']);
                if (Doctor::where('user_id', $linkUser)->when(!$creating, fn ($q) => $q->where('id', '!=', $doctor->id))->exists()) throw ValidationException::withMessages(['user_id' => 'This account is already linked to another doctor.']);
                $doctor->user_id = $linkUser;
            }
            $doctor->save();
            $removed = $doctor->branches()->pluck('branches.id')->diff($branchIds);
            if ($removed->isNotEmpty() && ($doctor->schedules()->whereIn('branch_id', $removed)->exists() || $doctor->leaves()->whereIn('branch_id', $removed)->where('status', '!=', 'cancelled')->where('end_date', '>=', $context['today'])->exists())) throw ValidationException::withMessages(['branch_ids' => 'Clear working schedules and cancel upcoming leave before removing a branch.']);
            $doctor->branches()->sync(collect($branchIds)->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenant->id]])->all());
            $doctor->specialties()->sync(collect($data['specialty_ids'])->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenant->id]])->all());
            $this->audit($doctor, $creating ? 'doctor.created' : 'doctor.updated'); $this->audit($doctor, 'doctor.branch.assigned');
            return $doctor->load(['branches','specialties']);
        }, 3);
    }
}
