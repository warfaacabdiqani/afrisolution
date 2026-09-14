<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use App\Services\ClinicAccessService;
use App\Services\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClinicStaffController extends Controller
{
    private function context(Request $request, ?string $permission = null): array
    {
        return app(ClinicAccessService::class)->authorize($request, 'staff', $permission);
    }

    private function staffNumber(int $id): string
    {
        return 'STF-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function normalizeName(array $data): string
    {
        $parts = [
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
        ];

        return trim(implode(' ', array_filter($parts, fn ($value) => is_string($value) && trim($value) !== '')));
    }

    private function rolePermissions(string $role): array
    {
        return config('clinic.roles.' . $role, []);
    }

    private function currentPermissions(?string $permissions, string $role): array
    {
        if (blank($permissions)) {
            return $this->rolePermissions($role);
        }

        $decoded = json_decode($permissions, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function staffQuery(int $tenantId)
    {
        return DB::table('tenant_memberships as tm')
            ->join('users as u', 'u.id', '=', 'tm.user_id')
            ->where('tm.tenant_id', $tenantId)
            ->select('tm.*', 'u.name', 'u.email', 'u.status as user_status');
    }

    private function branchIdsForMembership(int $tenantId, int $membershipId): array
    {
        return DB::table('branch_memberships')
            ->where('tenant_id', $tenantId)
            ->where('membership_id', $membershipId)
            ->pluck('branch_id')
            ->all();
    }

    private function buildPayload(int $tenantId, object $membership, array $branches): array
    {
        $user = User::findOrFail($membership->user_id);
        $branchIds = $this->branchIdsForMembership($tenantId, (int) $membership->id);
        $linkedDoctor = Doctor::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->select('id', 'doctor_number', 'first_name', 'middle_name', 'last_name', 'status')
            ->first();

        return [
            'id' => $membership->id,
            'staff_number' => $membership->staff_number ?? $this->staffNumber((int) $membership->id),
            'first_name' => $membership->first_name ?? (isset($user->name) ? explode(' ', trim($user->name))[0] : null),
            'middle_name' => $membership->middle_name ?? null,
            'last_name' => $membership->last_name ?? (count(explode(' ', trim($user->name))) > 1 ? implode(' ', array_slice(explode(' ', trim($user->name)), 1)) : null),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $membership->phone ?? null,
            'job_title' => $membership->job_title ?? null,
            'role' => $membership->role,
            'status' => $membership->status,
            'all_branches' => (bool) $membership->all_branches,
            'branch_ids' => $branchIds,
            'primary_branch_id' => $branchIds[0] ?? null,
            'branches' => collect($branches)->filter(fn ($branch) => in_array((int) $branch->id, $branchIds, true))->values()->all(),
            'permissions' => $this->currentPermissions($membership->permissions ?? null, $membership->role),
            'default_permissions' => $this->rolePermissions($membership->role),
            'created_at' => $membership->created_at,
            'updated_at' => $membership->updated_at,
            'last_login_at' => $membership->last_login_at ?? null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ],
            'linked_doctor' => $linkedDoctor ? [
                'id' => $linkedDoctor->id,
                'doctor_number' => $linkedDoctor->doctor_number,
                'full_name' => trim(($linkedDoctor->first_name ?? '') . ' ' . ($linkedDoctor->middle_name ?? '') . ' ' . ($linkedDoctor->last_name ?? '')),
                'status' => $linkedDoctor->status,
            ] : null,
        ];
    }

    private function validateMemberLimit(int $tenantId, string $status): void
    {
        $subscription = DB::table('subscriptions')->where('tenant_id', $tenantId)->first();

        if (! $subscription) {
            throw ValidationException::withMessages(['email' => 'This clinic does not have an active subscription.']);
        }

        $activeCount = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        if ($status === 'active' && $subscription->member_limit !== null && $activeCount >= $subscription->member_limit) {
            throw ValidationException::withMessages([
                'email' => 'Your current plan allows up to ' . $subscription->member_limit . ' staff members. Upgrade your subscription to add more staff.',
            ]);
        }
    }

    public function options(Request $request)
    {
        $context = $this->context($request, 'staff.view');

        $roles = collect(config('clinic.roles'))
            ->keys()
            ->map(fn ($role) => [
                'value' => $role,
                'label' => ucfirst(str_replace('_', ' ', $role)),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'salon_permissions' => $context['business_type']['slug'] === 'beauty-salon' ? config('clinic.salon_permissions') : [],
                'roles' => $roles,
                'role_permissions' => collect(config('clinic.roles'))
                    ->map(fn (array $permissions, string $role) => [
                        'role' => $role,
                        'label' => ucfirst(str_replace('_', ' ', $role)),
                        'permissions' => $permissions,
                    ])
                    ->values()
                    ->all(),
                'branches' => $context['branches']->all(),
                'doctors' => DB::table('doctors')
                    ->where('tenant_id', $context['clinic']->id)
                    ->whereNull('user_id')
                    ->orderBy('last_name')
                    ->orderBy('id')
                    ->get(['id', 'first_name', 'middle_name', 'last_name', 'doctor_number']),
                'limits' => [
                    'member_limit' => $context['limits']['member_limit'] ?? null,
                ],
            ],
        ]);
    }

    public function stats(Request $request)
    {
        $context = $this->context($request, 'staff.view');

        $members = $this->staffQuery($context['clinic']->id)->get();

        $stats = [
            'total' => $members->count(),
            'active' => $members->where('status', 'active')->count(),
            'inactive' => $members->where('status', 'inactive')->count(),
            'suspended' => $members->where('status', 'suspended')->count(),
            'admins' => $members->filter(fn ($member) => in_array($member->role, ['owner', 'admin', 'management'], true))->count(),
            'available_seats' => max(0, ($context['limits']['member_limit'] ?? 0) - $members->where('status', 'active')->count()),
        ];

        return response()->json(['data' => $stats]);
    }

    public function index(Request $request)
    {
        $context = $this->context($request, 'staff.view');

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', 'string', Rule::in(array_keys(config('clinic.roles')))],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'branch_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $branches = DB::table('branches')->where('tenant_id', $context['clinic']->id)->get(['id', 'name']);
        $assignments = DB::table('branch_memberships')->where('tenant_id', $context['clinic']->id)->get()->groupBy('membership_id');

        $query = $this->staffQuery($context['clinic']->id);

        if (! empty($data['search'])) {
            $search = trim($data['search']);
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%')
                    ->orWhere('tm.role', 'like', '%' . $search . '%')
                    ->orWhere('tm.staff_number', 'like', '%' . $search . '%')
                    ->orWhere('tm.phone', 'like', '%' . $search . '%');
            });
        }

        if (! empty($data['role'])) {
            $query->where('tm.role', $data['role']);
        }

        if (! empty($data['status'])) {
            $query->where('tm.status', $data['status']);
        }

        if (! empty($data['branch_id'])) {
            $query->whereExists(function ($q) use ($data, $context) {
                $q->from('branch_memberships as bm')
                    ->whereColumn('bm.membership_id', 'tm.id')
                    ->where('bm.tenant_id', $context['clinic']->id)
                    ->where('bm.branch_id', (int) $data['branch_id']);
            });
        }

        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = (int) ($data['per_page'] ?? 25);
        $members = $query->orderByDesc('tm.id')->paginate($perPage, ['tm.*', 'u.name', 'u.email', 'u.status as user_status'], 'page', $page);

        $items = collect($members->items())->map(function ($member) use ($assignments, $branches) {
            $branchIds = $assignments->get($member->id, collect())->pluck('branch_id')->all();
            $branchNames = collect($branches)->filter(fn ($branch) => in_array((int) $branch->id, $branchIds, true))->pluck('name')->all();

            return [
                'id' => $member->id,
                'staff_number' => $member->staff_number ?? $this->staffNumber((int) $member->id),
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'role' => $member->role,
                'status' => $member->status,
                'branches' => $branchNames,
                'branch_ids' => $branchIds,
                'primary_branch' => $branchNames[0] ?? null,
                'permissions' => $this->currentPermissions($member->permissions ?? null, $member->role),
                'default_permissions' => $this->rolePermissions($member->role),
                'last_login_at' => $member->last_login_at ?? null,
                'created_at' => $member->created_at,
                'updated_at' => $member->updated_at,
                'user_status' => $member->user_status,
            ];
        })->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'from' => $members->firstItem(),
                'to' => $members->lastItem(),
            ],
            'stats' => [
                'total' => $members->total(),
                'active' => DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('status', 'active')->count(),
                'inactive' => DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('status', 'inactive')->count(),
                'suspended' => DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('status', 'suspended')->count(),
                'admins' => DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->whereIn('role', ['owner', 'admin', 'management'])->count(),
                'available_seats' => max(0, ($context['limits']['member_limit'] ?? 0) - DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('status', 'active')->count()),
            ],
        ]);
    }

    public function show(Request $request, int $staff)
    {
        $context = $this->context($request, 'staff.view');

        $membership = $this->staffQuery($context['clinic']->id)
            ->where('tm.id', $staff)
            ->firstOrFail();

        $branches = DB::table('branches')->where('tenant_id', $context['clinic']->id)->get(['id', 'name']);

        return response()->json(['data' => $this->buildPayload((int) $context['clinic']->id, $membership, $branches->all())]);
    }

    public function store(Request $request)
    {
        $context = $this->context($request, 'staff.create');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'string', Rule::in(array_keys(config('clinic.roles')))],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('tenant_id', $context['clinic']->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where('tenant_id', $context['clinic']->id)->whereNull('user_id')],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $data['all_branches'] = $data['all_branches'] ?? false;

        if (! $data['all_branches'] && empty($data['branch_ids'] ?? [])) {
            throw ValidationException::withMessages(['branch_ids' => 'Select at least one branch for this staff member.']);
        }

        $this->validateMemberLimit((int) $context['clinic']->id, $data['status'] ?? 'active');

        $user = User::create([
            'name' => $this->normalizeName($data),
            'email' => $data['email'],
            'password' => $data['password'] ?? Str::random(24),
        ]);

        $membershipId = DB::table('tenant_memberships')->insertGetId([
            'tenant_id' => $context['clinic']->id,
            'user_id' => $user->id,
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'permissions' => ! empty($data['permissions']) ? json_encode(array_values(array_unique($data['permissions']))) : null,
            'all_branches' => $data['all_branches'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_memberships')
            ->where('id', $membershipId)
            ->update(['staff_number' => $this->staffNumber($membershipId)]);

        if (! $data['all_branches']) {
            foreach ($data['branch_ids'] ?? [] as $branchId) {
                DB::table('branch_memberships')->insert([
                    'tenant_id' => $context['clinic']->id,
                    'membership_id' => $membershipId,
                    'branch_id' => $branchId,
                ]);
            }
        }

        if (! empty($data['doctor_id'])) {
            Doctor::where('tenant_id', $context['clinic']->id)
                ->where('id', $data['doctor_id'])
                ->update(['user_id' => $user->id]);
        }

        app(PlatformService::class)->audit($request->user()->id, 'staff.created', 'tenant', $context['clinic']->id, [
            'staff_id' => $membershipId,
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
            'branch_ids' => $data['all_branches'] ? null : ($data['branch_ids'] ?? []),
        ]);

        return response()->json([
            'data' => $this->buildPayload((int) $context['clinic']->id, $this->staffQuery($context['clinic']->id)->where('tm.id', $membershipId)->firstOrFail(), DB::table('branches')->where('tenant_id', $context['clinic']->id)->get(['id', 'name'])->all()),
            'message' => 'Staff member created successfully.',
        ], 201);
    }

    public function update(Request $request, int $staff)
    {
        $context = $this->context($request, 'staff.update');

        $search = DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('id', $staff)->firstOrFail();
        $user = User::findOrFail($search->user_id);

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', Rule::in(array_keys(config('clinic.roles')))],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('tenant_id', $context['clinic']->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where('tenant_id', $context['clinic']->id)],
        ]);

        if (isset($data['all_branches']) || array_key_exists('branch_ids', $data)) {
            $data['all_branches'] = $data['all_branches'] ?? false;

            if (! $data['all_branches'] && empty($data['branch_ids'] ?? [])) {
                throw ValidationException::withMessages(['branch_ids' => 'Select at least one branch for this staff member.']);
            }
        }

        if (isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (isset($data['first_name']) || isset($data['middle_name']) || isset($data['last_name'])) {
            $user->name = $this->normalizeName([
                'first_name' => $data['first_name'] ?? ($user->name ? explode(' ', trim($user->name))[0] : ''),
                'middle_name' => $data['middle_name'] ?? '',
                'last_name' => $data['last_name'] ?? (count(explode(' ', trim($user->name))) > 1 ? implode(' ', array_slice(explode(' ', trim($user->name)), 1)) : ''),
            ]);
        }

        $user->save();

        $updates = [
            'first_name' => $data['first_name'] ?? $search->first_name,
            'middle_name' => $data['middle_name'] ?? $search->middle_name,
            'last_name' => $data['last_name'] ?? $search->last_name,
            'phone' => $data['phone'] ?? $search->phone,
            'job_title' => $data['job_title'] ?? $search->job_title,
            'updated_at' => now(),
        ];

        if (isset($data['role'])) {
            $updates['role'] = $data['role'];
        }

        if (isset($data['status'])) {
            $updates['status'] = $data['status'];
        }

        if (array_key_exists('permissions', $data)) {
            $updates['permissions'] = empty($data['permissions']) ? null : json_encode(array_values(array_unique($data['permissions'])));
        }

        if (array_key_exists('all_branches', $data)) {
            $updates['all_branches'] = (bool) $data['all_branches'];
        }

        DB::table('tenant_memberships')
            ->where('tenant_id', $context['clinic']->id)
            ->where('id', $staff)
            ->update($updates);

        if (array_key_exists('branch_ids', $data)) {
            DB::table('branch_memberships')->where('tenant_id', $context['clinic']->id)->where('membership_id', $staff)->delete();

            if (! $data['all_branches'] && ! empty($data['branch_ids'])) {
                foreach ($data['branch_ids'] as $branchId) {
                    DB::table('branch_memberships')->insert([
                        'tenant_id' => $context['clinic']->id,
                        'membership_id' => $staff,
                        'branch_id' => $branchId,
                    ]);
                }
            }
        }

        if (! empty($data['doctor_id'])) {
            Doctor::where('tenant_id', $context['clinic']->id)
                ->where('id', $data['doctor_id'])
                ->update(['user_id' => $user->id]);
        }

        app(PlatformService::class)->audit($request->user()->id, 'staff.updated', 'tenant', $context['clinic']->id, [
            'staff_id' => $staff,
            'role' => $updates['role'] ?? $search->role,
            'status' => $updates['status'] ?? $search->status,
        ]);

        $membership = $this->staffQuery($context['clinic']->id)->where('tm.id', $staff)->firstOrFail();
        $branches = DB::table('branches')->where('tenant_id', $context['clinic']->id)->get(['id', 'name']);

        return response()->json(['data' => $this->buildPayload((int) $context['clinic']->id, $membership, $branches->all())]);
    }

    public function activate(Request $request, int $staff)
    {
        return $this->toggleStatus($request, $staff, 'activate');
    }

    public function deactivate(Request $request, int $staff)
    {
        return $this->toggleStatus($request, $staff, 'deactivate');
    }

    private function toggleStatus(Request $request, int $staff, string $action)
    {
        $context = $this->context($request, $action === 'activate' ? 'staff.activate' : 'staff.deactivate');

        $membership = DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('id', $staff)->firstOrFail();

        if ($action === 'deactivate') {
            $activeOwners = DB::table('tenant_memberships')
                ->where('tenant_id', $context['clinic']->id)
                ->where('role', 'owner')
                ->where('status', 'active')
                ->count();

            if ($membership->role === 'owner' && $activeOwners <= 1) {
                throw ValidationException::withMessages(['status' => 'The last active clinic owner cannot be deactivated.']);
            }
        }

        DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('id', $staff)->update([
            'status' => $action === 'activate' ? 'active' : 'inactive',
            'updated_at' => now(),
        ]);

        app(PlatformService::class)->audit($request->user()->id, 'staff.' . ($action === 'activate' ? 'activated' : 'deactivated'), 'tenant', $context['clinic']->id, [
            'staff_id' => $staff,
            'status' => $action === 'activate' ? 'active' : 'inactive',
        ]);

        $membership = $this->staffQuery($context['clinic']->id)->where('tm.id', $staff)->firstOrFail();
        $branches = DB::table('branches')->where('tenant_id', $context['clinic']->id)->get(['id', 'name']);

        return response()->json(['data' => $this->buildPayload((int) $context['clinic']->id, $membership, $branches->all())]);
    }

    public function resetPassword(Request $request, int $staff)
    {
        $context = $this->context($request, 'staff.reset_password');
        $membership = DB::table('tenant_memberships')->where('tenant_id', $context['clinic']->id)->where('id', $staff)->firstOrFail();

        $password = Str::random(24);
        User::findOrFail($membership->user_id)->update(['password' => $password]);

        app(PlatformService::class)->audit($request->user()->id, 'staff.password_reset_requested', 'tenant', $context['clinic']->id, [
            'staff_id' => $staff,
        ]);

        return response()->json([
            'data' => ['sent' => true],
            'message' => 'A password reset link has been sent.',
        ]);
    }
}
