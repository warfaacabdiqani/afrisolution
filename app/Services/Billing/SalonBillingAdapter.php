<?php

namespace App\Services\Billing;

use App\Models\SalonAppointment;
use App\Services\{ClinicAccessService, SalonBookingService};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalonBillingAdapter implements InvoiceSource
{
    public function context(Request $request): array
    {
        $access = app(ClinicAccessService::class);
        $context = $access->authorize($request, 'billing', 'billing.create');
        if ($context['business_type']['slug'] !== 'beauty-salon') {
            $access->deny('BUSINESS_MODULE_UNAVAILABLE', 'Salon billing sources require a Beauty Salon business.');
        }
        $access->authorizeBusiness($context, 'appointments');
        if (empty($context['features']['appointments'])) $access->deny('PLAN_FEATURE_UNAVAILABLE', 'Your current plan does not include appointments.');
        return $context;
    }

    public function visible(array $context)
    {
        // Keep stylist visibility for appointment users. Billing-only operators receive
        // financial source access within their authorized branches, never booking access.
        if (app(ClinicAccessService::class)->can($context['permissions'], 'appointments.view')) {
            return app(SalonBookingService::class)->visible($context);
        }
        return SalonAppointment::whereIn('branch_id', $context['branches']->pluck('id'));
    }

    public function pending(array $context)
    {
        return $this->visible($context)->where('status', 'completed')->whereDoesntHave('invoice')
            ->with(['client', 'branch'])->latest('id')->paginate(25);
    }

    public function snapshot(array $context, int $id): array
    {
        abort_unless($context['business_type']['slug'] === 'beauty-salon', 403);
        $appointment = $this->visible($context)->with('items')->findOrFail($id);
        if ($appointment->status !== 'completed') {
            throw ValidationException::withMessages(['status' => 'Complete the appointment before creating its invoice.']);
        }
        return [
            'tenant_id' => $appointment->tenant_id, 'branch_id' => $appointment->branch_id,
            'source_id' => $appointment->id, 'customer_type' => 'salon_client', 'customer_id' => $appointment->client_id,
            'currency' => $appointment->currency, 'discount' => $appointment->discount, 'tax_rate' => $appointment->tax_rate,
            'expected_totals' => $appointment->only(['subtotal', 'discount', 'tax', 'total']),
            'items' => $appointment->items->map(fn ($item) => [
                'source_type' => 'salon_appointment_service', 'source_id' => $item->id,
                'description' => $item->name, 'quantity' => 1, 'unit_price' => $item->unit_price,
            ])->all(),
            'audit' => ['business_type' => 'beauty-salon', 'appointment_id' => $appointment->id,
                'branch_id' => $appointment->branch_id, 'salon_client_id' => $appointment->client_id],
        ];
    }
}
