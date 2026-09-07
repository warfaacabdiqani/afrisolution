<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\ClinicAccessService;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientHistoryController extends Controller
{
    public function index(Request $request, ClinicAccessService $access, int $patient, string $kind)
    {
        $access->authorize($request, 'patients', 'patients.medical_history.view');
        $model = Patient::findOrFail($patient);
        return response()->json(['data' => $model->{$kind}()->latest('id')->paginate(25)]);
    }
    public function save(Request $request, ClinicAccessService $access, PatientService $service, int $patient, string $kind, ?int $entry = null)
    {
        $access->authorize($request, 'patients', 'patients.medical_history.update');
        $model = Patient::findOrFail($patient);
        abort_if($model->status === 'archived', 422, 'Restore this patient before editing medical history.');
        $rules = ['tenant_id' => ['prohibited'], 'patient_id' => ['prohibited'], 'notes' => ['nullable', 'string', 'max:3000']];
        $rules += $kind === 'allergies' ? [
            'allergen' => ['required', 'string', 'max:150'], 'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', Rule::in(['mild', 'moderate', 'severe'])], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ] : [
            'condition_name' => ['required', 'string', 'max:150'], 'diagnosed_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'status' => ['required', Rule::in(['active', 'resolved', 'historical'])],
        ];
        $data = $request->validate($rules);
        $item = DB::transaction(function () use ($model, $kind, $entry, $data, $request, $service) {
            $item = $entry ? $model->{$kind}()->findOrFail($entry) : $model->{$kind}()->make();
            $item->fill($data); $item->recorded_by = $request->user()->id; $item->save();
            $service->audit($model, 'patient.'.($kind === 'allergies' ? 'allergy' : 'condition').($entry ? '.updated' : '.created'));
            return $item;
        });
        return response()->json(['data' => $item], $entry ? 200 : 201);
    }
}
