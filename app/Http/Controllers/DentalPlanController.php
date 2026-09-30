<?php
namespace App\Http\Controllers;

use App\Http\Requests\BillingSourceRequest;
use App\Http\Resources\BillingInvoiceResource;
use App\Services\{BillingService, ClinicAccessService, DentalAccessService, DentalPlanService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DentalPlanController extends Controller
{
    private function rules(bool $editing): array
    {
        return [
            'title' => 'required|string|max:150', 'notes' => 'nullable|string|max:4000',
            'version' => $editing ? 'required|integer|min:1' : 'prohibited',
            'items' => 'required|array|min:1|max:100', 'items.*' => 'array:procedure_id,tooth,surfaces,visit_number,quantity,unit_price,notes',
            'items.*.procedure_id' => 'required|integer', 'items.*.tooth' => ['nullable', 'string', Rule::in(config('dental.teeth'))],
            'items.*.surfaces' => 'sometimes|array|max:6', 'items.*.surfaces.*' => ['string', Rule::in(array_keys(config('dental.surfaces')))],
            'items.*.visit_number' => 'required|integer|min:1|max:100', 'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.unit_price' => 'sometimes|numeric|decimal:0,2|min:0|max:9999999.99', 'items.*.notes' => 'nullable|string|max:2000',
            'tenant_id' => 'prohibited', 'patient_id' => 'prohibited', 'branch_id' => 'prohibited', 'status' => 'prohibited', 'currency' => 'prohibited', 'tax_rate' => 'prohibited',
        ];
    }

    public function index(Request $request, int $patient, DentalAccessService $access, DentalPlanService $plans)
    {
        $context = $access->context($request); $access->patient($context, $patient);
        return response()->json(['data' => $plans->visible($context)->where('patient_id', $patient)->orderByDesc('id')->get()
            ->map(fn ($plan) => $plans->present($plan, $context))]);
    }

    public function show(Request $request, int $plan, DentalAccessService $access, DentalPlanService $plans)
    {
        $context = $access->context($request);
        return response()->json(['data' => $plans->present($plans->find($context, $plan), $context)]);
    }

    public function appointments(Request $request, int $plan, DentalAccessService $access, DentalPlanService $plans)
    {
        $context = $access->context($request, 'dental.treatments.complete');
        $record = $plans->find($context, $plan);
        return response()->json(['data' => \App\Models\Appointment::where('patient_id', $record->patient_id)
            ->where('branch_id', $record->branch_id)->whereIn('status', ['in_consultation', 'completed'])
            ->orderByDesc('starts_at')->limit(100)->get(['id', 'appointment_number', 'starts_at', 'status'])]);
    }

    public function store(Request $request, int $patient, DentalAccessService $access, DentalPlanService $plans)
    {
        $context = $access->context($request, 'dental.plans.manage');
        $data = $request->validate($this->rules(false));
        return response()->json(['data' => $plans->present($plans->save($context, $patient, $data), $context)], 201);
    }

    public function update(Request $request, int $plan, DentalAccessService $access, DentalPlanService $plans)
    {
        $context = $access->context($request, 'dental.plans.manage');
        $data = $request->validate($this->rules(true));
        $current = $plans->find($context, $plan, true);
        return response()->json(['data' => $plans->present($plans->save($context, $current->patient_id, $data, $plan), $context)]);
    }

    public function status(Request $request, int $plan, DentalAccessService $access, DentalPlanService $plans)
    {
        $context = $access->context($request, 'dental.plans.manage');
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'cancelled'])],
            'version' => 'required|integer|min:1', 'reason' => 'required_if:status,cancelled|nullable|string|max:2000']);
        return response()->json(['data' => $plans->present($plans->transition($context, $plan, $data), $context)]);
    }

    public function complete(Request $request, int $plan, int $item, DentalAccessService $access, DentalPlanService $plans)
    {
        $context = $access->context($request, 'dental.treatments.complete');
        $data = $request->validate(['appointment_id' => 'nullable|integer', 'notes' => 'nullable|string|max:4000']);
        return response()->json(['data' => $plans->present($plans->complete($context, $plan, $item, $data), $context)]);
    }

    public function invoice(BillingSourceRequest $request, int $plan, int $item, DentalAccessService $access, DentalPlanService $plans, BillingService $billing)
    {
        $context = $access->context($request);
        app(ClinicAccessService::class)->authorize($request, 'billing', 'billing.create');
        $plans->find($context, $plan)->items()->findOrFail($item);
        return (new BillingInvoiceResource($billing->createFromSource($context, 'dental_treatment', $item)))->response()->setStatusCode(201);
    }
}
