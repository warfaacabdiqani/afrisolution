<?php

namespace App\Http\Controllers;

use App\Http\Requests\{BillingInvoiceIndexRequest, BillingPaymentRequest, BillingSourceRequest};
use App\Http\Resources\BillingInvoiceResource;
use App\Services\{BillingService, ClinicAccessService, ClinicSettingsService};
use App\Services\Billing\SalonBillingAdapter;
use App\Http\Resources\SalonBillingSourceResource;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    private function methods(array $context): array
    {
        return app(ClinicSettingsService::class)->get($context['clinic']->id, 'billing.payment_methods', ['cash']);
    }

    public function index(BillingInvoiceIndexRequest $request, BillingService $billing)
    {
        $context = app(ClinicAccessService::class)->authorize($request, 'billing');
        $query = $billing->visible($context)->with('branch');
        $filter = $request->validated();
        if (isset($filter['customer_type'], $filter['customer_id'])) {
            [$column, $customerId] = app(\App\Services\Billing\BillingCustomerResolver::class)->historyFilter(
                \App\Models\Tenant::findOrFail($context['clinic']->id), $filter['customer_type'], (int) $filter['customer_id'], $context['branches']->pluck('id')->all());
            $query->where($column, $customerId);
        }
        $page = $query->latest('id')->paginate(50)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn ($invoice) => (new BillingInvoiceResource($invoice))->resolve($request)));
        return response()->json(['data' => $page, 'methods' => $this->methods($context)]);
    }

    public function show(Request $request, int $id, BillingService $billing)
    {
        $context = app(ClinicAccessService::class)->authorize($request, 'billing');
        $invoice = $billing->visible($context)->with(['branch', 'items', 'payments.receipt', 'payments.recordedBy'])->findOrFail($id);
        return (new BillingInvoiceResource($invoice))->additional(['methods' => $this->methods($context)]);
    }

    public function invoicePrint(Request $request, int $id, BillingService $billing)
    {
        $context = app(ClinicAccessService::class)->authorize($request, 'billing');
        $invoice = $billing->visible($context)->with(['branch', 'items', 'payments.receipt'])->findOrFail($id);
        $identity = $invoice->document_snapshot;
        return (new BillingInvoiceResource($invoice))->additional(['document' => [
            'identity' => $identity ?? app(\App\Services\Billing\BillingDocumentSnapshot::class)->identity($context, $invoice->branch->name,
                $invoice->salon_client_id ? 'salon_client' : 'patient'),
            'historical_identity_available' => $identity !== null,
        ]]);
    }

    public function receipt(Request $request, int $id, BillingService $billing)
    {
        $context = app(ClinicAccessService::class)->authorize($request, 'billing');
        $payment = \App\Models\BillingPayment::whereHas('invoice', fn ($query) => $query->whereIn('branch_id', $context['branches']->pluck('id')))
            ->with('receipt')->findOrFail($id);
        abort_unless($payment->receipt, 404, 'No receipt was issued for this historical payment.');
        return response()->json(['data' => [
            'id' => $payment->receipt->id,
            'number' => $payment->receipt->number,
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'snapshot' => $payment->receipt->snapshot,
        ]]);
    }

    public function fromSalon(BillingSourceRequest $request, int $id, BillingService $billing)
    {
        $context = app(SalonBillingAdapter::class)->context($request);
        return (new BillingInvoiceResource($billing->createFromSource($context, 'salon_appointment', $id)))->response()->setStatusCode(201);
    }

    public function salonSources(Request $request, SalonBillingAdapter $adapter)
    {
        $context = $adapter->context($request);
        $request->validate(['page' => 'sometimes|integer|min:1']);
        return SalonBillingSourceResource::collection($adapter->pending($context));
    }

    public function fromClinic(BillingSourceRequest $request, int $appointment, BillingService $billing)
    {
        $access = app(ClinicAccessService::class);
        $context = $access->authorize($request, 'appointments');
        $access->authorizeBusiness($context, 'consultations');
        $access->authorize($request, 'billing', 'billing.create');
        return (new BillingInvoiceResource($billing->createFromSource($context, 'clinic_appointment', $appointment)))->response()->setStatusCode(201);
    }

    public function payment(BillingPaymentRequest $request, int $id, BillingService $billing)
    {
        $context = app(ClinicAccessService::class)->authorize($request, 'billing', 'billing.payments');
        return new BillingInvoiceResource($billing->payment($context, $id, $request->validated()));
    }
}
