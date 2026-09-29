<?php
namespace App\Http\Controllers;

use App\Models\DentalProcedure;
use App\Services\{ClinicSettingsService, DentalAccessService, DentalChartService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DentalController extends Controller
{
    public function options(Request $request, DentalAccessService $access)
    {
        $context = $access->context($request);
        return response()->json(['data' => config('dental') + ['currency' => app(ClinicSettingsService::class)->get($context['clinic']->id, 'billing.currency', 'USD')]]);
    }

    public function procedures(Request $request, DentalAccessService $access)
    {
        $access->context($request);
        $data = $request->validate(['search' => 'nullable|string|max:150', 'active' => 'sometimes|boolean', 'page' => 'sometimes|integer|min:1']);
        $query = DentalProcedure::query();
        if (!empty($data['search'])) $query->where(fn ($q) => $q->where('name', 'like', '%'.$data['search'].'%')->orWhere('code', 'like', '%'.$data['search'].'%'));
        if (isset($data['active'])) $query->where('active', $data['active']);
        return response()->json(['data' => $query->orderBy('name')->paginate(50)]);
    }

    public function saveProcedure(Request $request, DentalAccessService $access, ?int $id = null)
    {
        $context = $access->context($request, 'dental.procedures.manage');
        $procedure = $id ? DentalProcedure::findOrFail($id) : new DentalProcedure;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/D', Rule::unique('dental_procedures')->where('tenant_id', $context['clinic']->id)->ignore($id)],
            'name' => 'required|string|max:150', 'description' => 'nullable|string|max:4000',
            'price' => 'required|numeric|decimal:0,2|min:0|max:9999999.99', 'active' => 'required|boolean', 'tenant_id' => 'prohibited',
        ]);
        $procedure->fill($data)->save();
        $access->audit($context, $id ? 'procedure.updated' : 'procedure.created', ['procedure_id' => $procedure->id]);
        return response()->json(['data' => $procedure], $id ? 200 : 201);
    }

    public function chart(Request $request, int $patient, DentalAccessService $access, DentalChartService $chart)
    {
        return response()->json(['data' => $chart->chart($access->context($request), $patient)]);
    }

    public function finding(Request $request, int $patient, DentalAccessService $access, DentalChartService $chart)
    {
        $context = $access->context($request, 'dental.chart');
        $data = $request->validate([
            'tooth' => ['required', 'string', Rule::in(config('dental.teeth'))],
            'surfaces' => 'sometimes|array|max:6', 'surfaces.*' => ['string', 'distinct', Rule::in(array_keys(config('dental.surfaces')))],
            'condition' => ['required', Rule::in(array_keys(config('dental.conditions')))], 'notes' => 'nullable|string|max:4000',
            'tenant_id' => 'prohibited', 'patient_id' => 'prohibited', 'branch_id' => 'prohibited', 'recorded_by' => 'prohibited',
        ]);
        return response()->json(['data' => $chart->record($context, $patient, $data)], 201);
    }

    public function voidFinding(Request $request, int $patient, int $finding, DentalAccessService $access, DentalChartService $chart)
    {
        $context = $access->context($request, 'dental.chart');
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        return response()->json(['data' => $chart->void($context, $patient, $finding, $data['reason'])]);
    }
}
