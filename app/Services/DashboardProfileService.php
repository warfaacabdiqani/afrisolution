<?php
namespace App\Services;

use App\Http\Resources\{AppointmentResource, PatientResource};
use App\Models\{BillingPayment, Doctor, Patient};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardProfileService
{
    private function monthlyRevenue(array $context, string $currency): float
    {
        $start = Carbon::parse($context['today'], $context['clinic']->timezone)->startOfMonth();
        return (float) BillingPayment::whereHas('invoice', fn ($query) => $query
            ->where('branch_id', $context['branch']->id)->where('currency', $currency))
            ->where('paid_at', '>=', $start->copy()->utc())
            ->where('paid_at', '<', $start->addMonth()->utc())
            ->sum('amount');
    }

    public function resolve(Request $request, array $context): array
    {
        $access = app(ClinicAccessService::class);
        $profile = $context['dashboard_profile_key'];
        $definition = config('dashboard.profiles.'.$profile, ['widgets' => [], 'sections' => ['business_information'], 'quick_actions' => []]);
        $allowed = fn (string $module) => (bool) ($context['modules']->firstWhere('key', $module)['allowed'] ?? false);
        $members = DB::table('tenant_memberships')->join('users', 'users.id', '=', 'tenant_memberships.user_id')
            ->where('tenant_memberships.tenant_id', $context['clinic']->id)->where('tenant_memberships.status', 'active')->where('users.status', 'active')
            ->where(function ($q) use ($context) {
                $q->where('all_branches', true)->orWhereIn('tenant_memberships.id', DB::table('branch_memberships')->where('tenant_id', $context['clinic']->id)->where('branch_id', $context['branch']->id)->select('membership_id'));
            });
        $staffCount = $allowed('staff') ? (clone $members)->count() : null;
        $patientCount = 0; $doctors = 0; $appointmentCount = 0;
        $recentPatients = []; $todayAppointments = []; $dates = [];
        $patientsAllowed = $allowed('patients') && in_array('recent_customers', $definition['sections'], true);
        $appointmentsAllowed = $allowed('appointments') && in_array('appointments', $definition['sections'], true);

        // Only healthcare profiles can reach clinical models, even with a full plan and owner permissions.
        if (!empty($context['business_modules']['clinical']) && in_array('active_doctors', $definition['widgets'], true)) {
            $profiles = Doctor::where('status', 'active')->whereHas('branches', fn ($q) => $q->where('branches.id', $context['branch']->id))->count();
            $doctors = $profiles + (clone $members)->where('role', 'doctor')->whereNotIn('tenant_memberships.user_id', Doctor::whereNotNull('user_id')->select('user_id'))->count();
        }
        if ($patientsAllowed) {
            $patients = Patient::where('status', 'active');
            $patientCount = (clone $patients)->count();
            $recentPatients = PatientResource::collection($patients->latest('registered_at')->latest('id')->limit(5)->get())->resolve($request);
        }
        if ($appointmentsAllowed && !empty($context['business_modules']['clinical'])) {
            $appointments = app(AppointmentService::class)->visible($context)->where('branch_id', $context['branch']->id);
            $today = (clone $appointments)->where('starts_at', '>=', $context['today'].' 00:00:00')->where('starts_at', '<', Carbon::parse($context['today'])->addDay()->toDateString().' 00:00:00');
            $appointmentCount = (clone $today)->count();
            $todayAppointments = AppointmentResource::collection($today->with(['patient', 'doctor', 'branch', 'type'])->orderBy('starts_at')->limit(5)->get())->resolve($request);
            $month = Carbon::parse($context['today'])->startOfMonth();
            $dates = (clone $appointments)->where('starts_at', '>=', $month->format('Y-m-d H:i:s'))->where('starts_at', '<', $month->copy()->addMonth()->format('Y-m-d H:i:s'))->selectRaw('DATE(starts_at) as date')->distinct()->pluck('date')->all();
        }
        $currency = $profile === 'beauty-salon'
            ? app(ClinicSettingsService::class)->get($context['clinic']->id, 'general.currency', 'USD')
            : app(ClinicSettingsService::class)->get($context['clinic']->id, 'billing.currency', 'USD');
        $values = ['total_patients' => $patientCount, 'today_appointments' => $appointmentCount, 'active_doctors' => $doctors,
            'monthly_revenue' => in_array($profile, ['clinic', 'beauty-salon'], true) && $allowed('billing')
                ? $this->monthlyRevenue($context, $currency) : null,
            'staff_count' => $staffCount, 'branch_count' => $context['branches']->count(), 'subscription' => $context['plan']['name'] ?? null];
        if ($profile === 'beauty-salon') {
            $values['total_clients'] = $allowed('clients') ? \App\Models\SalonClient::where('branch_id',$context['branch']->id)->where('status','active')->count() : null;
            $values['active_stylists'] = $allowed('stylists') ? \App\Models\SalonStaffProfile::where('status','active')->whereHas('branches',fn($q)=>$q->where('branches.id',$context['branch']->id))->whereHas('user',fn($q)=>$q->where('status','active'))->count() : null;
            $values['today_appointments'] = $allowed('appointments') ? app(SalonBookingService::class)->visible($context)->where('branch_id',$context['branch']->id)->whereDate('starts_at',$context['today'])->count() : null;
        }
        $widgets = collect($definition['widgets'])->filter(fn ($key) => $context['modules']->firstWhere('key', config('dashboard.widgets.'.$key.'.module'))['business_allowed'] ?? false)->map(function ($key) use ($values, $allowed, $context, $currency) {
            $widget = config('dashboard.widgets.'.$key);
            $available = $allowed($widget['module']) && $values[$key] !== null;
            return ['key' => $key] + $widget + ['value' => $available ? $values[$key] : null, 'available' => $available,
                'currency' => $key === 'monthly_revenue' ? $currency : app(ClinicSettingsService::class)->get($context['clinic']->id,'general.currency',$context['plan']['currency'] ?? 'USD'),
                'description' => $available ? ($key === 'monthly_revenue' ? 'Payments received this month in this branch' : ($key === 'staff_count' ? 'Active members in this location' : ($key === 'branch_count' ? 'Authorized locations' : 'Current summary'))) : 'Not available with your access'];
        })->values()->all();
        $sections = [];
        foreach ($definition['sections'] as $section) {
            if ($section === 'appointments' && $appointmentsAllowed) $sections[$section] = ['title' => "Today's ".$context['labels']['bookings'], 'items' => $todayAppointments];
            if ($section === 'recent_customers' && $patientsAllowed) $sections[$section] = ['title' => 'Recent '.$context['labels']['customers'], 'items' => $recentPatients];
            if ($section === 'calendar' && $appointmentsAllowed) $sections[$section] = ['today' => $context['today'], 'dates' => $dates];
            if ($section === 'business_information') $sections[$section] = ['title' => $context['labels']['information'] ?? 'Business Information', 'can_manage' => $allowed('settings')];
        }
        $actions = collect($definition['quick_actions'])->map(fn ($key) => ['key' => $key] + config('dashboard.quick_actions.'.$key))
            ->filter(fn ($action) => $allowed($action['module']) && $access->can($context['permissions'], $action['permission']))->values()->all();
        $result = [
            'profile' => $profile,
            'business' => ['id' => $context['clinic']->id, 'name' => $context['clinic']->name, 'type' => $context['business_type']['name'],
                'workspace_label' => $context['labels']['workspace'] ?? 'business', 'branch' => $context['branch'],
                'plan' => $context['plan']['name'] ?? null, 'status' => $context['clinic']->status, 'subscription_status' => $context['subscription']->status,
                'staff_count' => $staffCount],
            'widgets' => $widgets, 'sections' => $sections, 'quick_actions' => $actions,
            'staff_count' => $staffCount, 'today' => $context['today'],
        ];
        // Preserve the established clinic API without advertising clinical metrics to other businesses.
        if (!empty($context['business_modules']['clinical'])) $result += [
            'stats' => ['total_patients' => $patientCount, 'today_appointments' => $appointmentCount, 'active_doctors' => $doctors, 'monthly_revenue' => $values['monthly_revenue']],
            'staff_count' => $staffCount, 'today_appointments' => $todayAppointments, 'recent_patients' => $recentPatients, 'visit_types' => [], 'calendar' => $dates,
            'availability' => ['patients' => $patientsAllowed, 'appointments' => $appointmentsAllowed, 'revenue' => $values['monthly_revenue'] !== null], 'today' => $context['today'],
        ];
        return $result;
    }
}
