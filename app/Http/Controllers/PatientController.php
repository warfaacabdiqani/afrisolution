<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\ClinicAccessService;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'patients');
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'], 'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'unknown'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived', 'all'])], 'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from') ? 'after_or_equal:from' : null]),
            'sort' => ['nullable', Rule::in(['name', 'patient_number', 'registered_at', 'status'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([25, 50, 100])], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Patient::query();
        $status = $data['status'] ?? 'active';
        if ($status !== 'all') $query->where('status', $status);
        foreach (['gender', 'blood_group'] as $field) if (!empty($data[$field])) $query->where($field, $data[$field]);
        foreach (['from' => '>=', 'to' => '<='] as $field => $operator) if (!empty($data[$field])) {
            $date = \Illuminate\Support\Carbon::parse($data[$field], $context['clinic']->timezone);
            $query->where('registered_at', $operator, ($field === 'from' ? $date->startOfDay() : $date->endOfDay())->utc());
        }
        if (!empty($data['search'])) {
            foreach (preg_split('/\s+/', trim($data['search'])) as $word) {
                $query->where(function ($q) use ($word) {
                    foreach (['patient_number', 'first_name', 'middle_name', 'last_name', 'phone', 'email'] as $column) $q->orWhere($column, 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%');
                });
            }
        }
        $sort = $data['sort'] ?? 'registered_at'; $direction = $data['direction'] ?? 'desc';
        if ($sort === 'name') $query->orderBy('first_name', $direction)->orderBy('last_name', $direction);
        else $query->orderBy($sort, $direction);
        $today = now($context['clinic']->timezone)->startOfDay();
        $stats = ['total' => Patient::count(), 'month' => Patient::where('registered_at', '>=', $today->copy()->startOfMonth()->utc())->count(), 'today' => Patient::where('registered_at', '>=', $today->copy()->utc())->count(), 'archived' => Patient::where('status', 'archived')->count()];
        return PatientResource::collection($query->orderBy('id', $direction)->paginate($data['per_page'] ?? 25))->additional(['stats' => $stats]);
    }

    public function store(PatientRequest $request, ClinicAccessService $access, PatientService $service)
    {
        $context = $access->authorize($request, 'patients', 'patients.create');
        return (new PatientResource($service->create($request->validated(), $context)))->response()->setStatusCode(201);
    }

    public function show(Request $request, ClinicAccessService $access, int $patient)
    {
        $context = $access->authorize($request, 'patients');
        $model = Patient::with('registrationBranch')->findOrFail($patient);
        $data = (new PatientResource($model))->resolve($request) + $model->only(['date_of_birth', 'marital_status', 'email', 'address', 'city', 'country', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'archived_at']);
        $data['date_of_birth'] = $model->date_of_birth?->toDateString();
        $data['registration_branch'] = $model->registrationBranch?->name;
        if ($access->can($context['permissions'], 'patients.medical_history.view')) {
            $data['notes'] = $model->notes;
            $data['allergies'] = $model->allergies()->where('status', 'active')->get(['id', 'allergen', 'severity']);
        }
        return response()->json(['data' => $data]);
    }

    public function update(PatientRequest $request, ClinicAccessService $access, PatientService $service, int $patient)
    {
        $context = $access->authorize($request, 'patients', 'patients.update');
        $model = Patient::findOrFail($patient);
        abort_if($model->status === 'archived', 422, 'Restore this patient before editing.');
        $data = $request->safe()->except(['confirm_duplicate', 'allergy', 'condition']);
        if (!$access->can($context['permissions'], 'patients.medical_history.update')) unset($data['notes']);
        DB::transaction(function () use ($model, $data, $service, $request) {
            $model->fill($data); $model->updated_by = $request->user()->id; $model->save(); $service->audit($model, 'patient.updated');
        });
        return new PatientResource($model);
    }

    public function status(Request $request, ClinicAccessService $access, PatientService $service, int $patient, string $action)
    {
        $access->authorize($request, 'patients', 'patients.'.$action);
        $model = Patient::findOrFail($patient);
        DB::transaction(function () use ($model, $action, $service, $request) {
            $model->status = $action === 'archive' ? 'archived' : 'active';
            $model->archived_at = $action === 'archive' ? now() : null;
            $model->updated_by = $request->user()->id; $model->save();
            $service->audit($model, $action === 'archive' ? 'patient.archived' : 'patient.restored');
        });
        return new PatientResource($model);
    }

    public function activity(Request $request, ClinicAccessService $access, int $patient)
    {
        $context = $access->authorize($request, 'patients');
        $model = Patient::findOrFail($patient);
        $query = DB::table('platform_audit_logs')->where('tenant_id', $model->tenant_id)->where('metadata->patient_id', $model->id)->where('action', 'like', 'patient.%');
        if (!$access->can($context['permissions'], 'patients.medical_history.view')) $query->where('action', 'not like', 'patient.allergy.%')->where('action', 'not like', 'patient.condition.%');
        if (!$access->can($context['permissions'], 'patients.documents.view')) $query->where('action', 'not like', 'patient.document.%');
        return response()->json(['data' => $query->orderByDesc('id')->paginate(25, ['id', 'action', 'actor_name', 'created_at'])]);
    }
}
