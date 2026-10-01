<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\AppointmentType;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\AppointmentStatusService;
use App\Services\ClinicAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function options(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'appointments');
        $branch = (int) $request->query('branch_id', $context['branch']->id);
        abort_unless($context['branches']->contains('id', $branch), 403);
        $doctors = Doctor::where('status', 'active')->whereHas('branches', fn ($q) => $q->where('branches.id', $branch));
        if (! $access->can($context['permissions'], 'appointments.view_all')) {
            $doctors->where('user_id', $request->user()->id);
        }

        return response()->json(['data' => ['doctors' => $doctors->orderBy('first_name')->get()->map(fn ($d) => ['id' => $d->id, 'full_name' => $d->full_name]), 'types' => AppointmentType::where('status', 'active')->orderBy('name')->get(), 'statuses' => config('appointments.statuses'), 'actions' => config('appointments.actions'), 'today' => $context['today'], 'timezone' => $context['clinic']->timezone, 'defaults' => app(\App\Services\ClinicSettingsService::class)->section($context['clinic']->id,'appointments')]]);
    }

    public function patients(Request $request, ClinicAccessService $access)
    {
        $c = $access->authorize($request, 'appointments');
        abort_unless($access->can($c['permissions'], 'appointments.create') || $access->can($c['permissions'], 'appointments.update'), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'id' => ['nullable', 'integer']]);
        $query = Patient::where('status', 'active');
        if (! empty($data['id'])) {
            $query->whereKey($data['id']);
        }
        foreach (preg_split('/\s+/', trim($data['search'] ?? '')) as $word) {
            if ($word !== '') {
                $query->where(function ($q) use ($word) {
                    foreach (['first_name', 'middle_name', 'last_name', 'patient_number', 'phone', 'email'] as $field) {
                        $q->orWhere($field, 'like', '%'.$word.'%');
                    }
                });
            }
        }

        return response()->json(['data' => $query->orderBy('first_name')->limit(20)->get()->map(fn ($p) => ['id' => $p->id, 'full_name' => $p->full_name, 'patient_number' => $p->patient_number, 'phone' => $p->phone])]);
    }

    public function types(Request $request, ClinicAccessService $access)
    {
        $access->authorize($request, 'appointments');

        return response()->json(['data' => AppointmentType::where('status', 'active')->orderBy('name')->get()]);
    }

    public function storeType(Request $request, ClinicAccessService $access)
    {
        $c = $access->authorize($request, 'appointments', 'appointments.types.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('appointment_types')->where('tenant_id', $c['clinic']->id)], 'default_duration' => ['required', 'integer', 'between:5,480'],
            'deposit_mode' => ['nullable', Rule::in(['none', 'fixed', 'percentage'])],
            'deposit_value' => ['nullable', 'required_if:deposit_mode,fixed', 'required_if:deposit_mode,percentage', 'numeric', 'min:0', 'max:'.($request->input('deposit_mode') === 'percentage' ? 100 : 99999999)]]);

        return response()->json(['data' => AppointmentType::create($data)], 201);
    }

    public function store(AppointmentRequest $request, ClinicAccessService $access, AppointmentService $service)
    {
        return (new AppointmentResource($service->save($access->authorize($request, 'appointments', 'appointments.create'), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(AppointmentRequest $request, ClinicAccessService $access, AppointmentService $service, int $appointment)
    {
        return new AppointmentResource($service->save($access->authorize($request, 'appointments', 'appointments.update'), $request->validated(), $appointment));
    }

    public function reschedule(AppointmentRequest $request, ClinicAccessService $access, AppointmentService $service, int $appointment)
    {
        $c = $access->authorize($request, 'appointments', 'appointments.reschedule');
        $a = $service->find($c, $appointment);
        // Reschedule grants timing changes, not permission to alter clinical notes or patient identity.
        $data = array_merge($request->validated(), $a->only(['patient_id', 'appointment_type_id', 'reason', 'notes', 'source']));

        return new AppointmentResource($service->save($c, $data, $appointment, true));
    }

    public function show(Request $request, ClinicAccessService $access, AppointmentService $service, int $appointment)
    {
        $a = $service->find($access->authorize($request, 'appointments'), $appointment);

        return response()->json(['data' => (new AppointmentResource($a))->resolve($request) + $a->only(['reason', 'notes', 'cancellation_reason', 'cancelled_at', 'checked_in_at', 'consultation_started_at', 'completed_at', 'created_at']) + ['created_by' => $a->creator->name]]);
    }

    public function status(Request $request, ClinicAccessService $access, AppointmentStatusService $service, int $appointment, string $action)
    {
        $c = $access->authorize($request, 'appointments');
        $data = $request->validate(['reason' => [$action === 'cancel' ? 'required' : 'nullable', 'string', 'max:2000'],
            'deposit_disposition' => [$action === 'cancel' ? 'nullable' : 'prohibited', Rule::in(['refund', 'forfeit'])],
            'deposit_idempotency_key' => $action === 'cancel' ? 'nullable|string|max:100' : 'prohibited']);

        return new AppointmentResource($service->transition($c, $appointment, $action, $data));
    }

    public function activity(Request $request, ClinicAccessService $access, AppointmentService $service, int $appointment)
    {
        $a = $service->find($access->authorize($request, 'appointments'), $appointment);

        return response()->json(['data' => DB::table('platform_audit_logs')->where('tenant_id',$a->tenant_id)->where('metadata->appointment_id',$a->id)->orderByDesc('id')->paginate(25,['id', 'action', 'actor_name', 'created_at'])]);
    }
}
