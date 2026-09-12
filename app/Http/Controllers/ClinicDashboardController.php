<?php

namespace App\Http\Controllers;

use App\Services\ClinicAccessService;
use App\Services\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicDashboardController extends Controller
{
    public function context(Request $request, ClinicAccessService $access)
    {
        return response()->json(['data' => $access->context($request)]);
    }

    public function switchBranch(Request $request, ClinicAccessService $access)
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'tenant_id' => ['prohibited']]);
        $context = $access->context($request);
        abort_unless($context['operational'], 403, $context['restriction']);
        abort_unless($context['branches']->contains('id', $data['branch_id']), 403, 'You cannot access this branch.');
        $old = $context['branch']->id;
        $request->session()->put('branch_id', (int) $data['branch_id']);
        if ($old !== (int) $data['branch_id']) app(PlatformService::class)->audit($request->user()->id, 'branch.switched', 'tenant', $context['clinic']->id, ['branch_id' => (int) $data['branch_id'], 'previous_branch_id' => $old]);
        return $this->context($request, $access);
    }

    public function dashboard(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'dashboard');
        $members = DB::table('tenant_memberships')->join('users', 'users.id', '=', 'tenant_memberships.user_id')
            ->where('tenant_memberships.tenant_id', $context['clinic']->id)->where('tenant_memberships.status', 'active')->where('users.status', 'active')
            ->where(function ($q) use ($context) {
                $q->where('all_branches', true)->orWhereIn('tenant_memberships.id', DB::table('branch_memberships')->where('tenant_id', $context['clinic']->id)->where('branch_id', $context['branch']->id)->select('membership_id'));
            });
        $profiles = \App\Models\Doctor::where('status', 'active')->whereHas('branches', fn ($q) => $q->where('branches.id', $context['branch']->id))->count();
        $doctors = $profiles + (clone $members)->where('role', 'doctor')->whereNotIn('tenant_memberships.user_id', \App\Models\Doctor::whereNotNull('user_id')->select('user_id'))->count();
        $patientsAllowed = $access->can($context['permissions'], 'patients.view') && ($context['features']['patient_management'] ?? false);
        $patientQuery = \App\Models\Patient::where('status', 'active');
        $patientCount = $patientsAllowed ? (clone $patientQuery)->count() : 0;
        $recentPatients = $patientsAllowed ? \App\Http\Resources\PatientResource::collection($patientQuery->latest('registered_at')->latest('id')->limit(5)->get())->resolve($request) : [];
        $appointmentsAllowed = $access->can($context['permissions'], 'appointments.view') && ($context['features']['appointments'] ?? false);
        $todayAppointments=[];$appointmentCount=0;$dates=[];
        if($appointmentsAllowed){
            $appointments=app(\App\Services\AppointmentService::class)->visible($context)->where('branch_id',$context['branch']->id);
            $today=(clone $appointments)->where('starts_at','>=',$context['today'].' 00:00:00')->where('starts_at','<',\Illuminate\Support\Carbon::parse($context['today'])->addDay()->toDateString().' 00:00:00');
            $appointmentCount=(clone $today)->count();
            $todayAppointments=\App\Http\Resources\AppointmentResource::collection($today->with(['patient','doctor','branch','type'])->orderBy('starts_at')->limit(5)->get())->resolve($request);
            $month=\Illuminate\Support\Carbon::parse($context['today'])->startOfMonth();
            $dates=(clone $appointments)->where('starts_at','>=',$month->format('Y-m-d H:i:s'))->where('starts_at','<',$month->copy()->addMonth()->format('Y-m-d H:i:s'))->selectRaw('DATE(starts_at) as date')->distinct()->pluck('date');
        }
        return response()->json(['data' => [
            'stats' => ['total_patients' => $patientCount, 'today_appointments' => $appointmentCount, 'active_doctors' => $doctors, 'monthly_revenue' => 0],
            'staff_count' => $members->count(), 'today_appointments' => $todayAppointments, 'recent_patients' => $recentPatients, 'visit_types' => [], 'calendar' => $dates,
            'availability' => ['patients' => $patientsAllowed, 'appointments' => $appointmentsAllowed, 'revenue' => false],
            'today' => $context['today'],
        ]]);
    }

    public function module(Request $request, ClinicAccessService $access, string $module)
    {
        $action = $request->query('action');
        abort_unless(in_array($action, [null, 'create'], true), 422);
        $permission = $action === 'create' ? ($module === 'billing' ? 'billing.create' : $module.'.create') : null;
        $access->authorize($request, $module, $permission);
        if (in_array($module, ['patients', 'doctors', 'appointments', 'consultations', 'billing'], true)) return response()->json(['data' => ['available' => true]]);
        return response()->json(['data' => ['available' => false, 'message' => 'This module is scheduled for a later implementation phase.']]);
    }
}
