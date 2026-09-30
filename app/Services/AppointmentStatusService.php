<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentStatusService
{
    public function transition(array $context, int $id, string $action, array $data)
    {
        $definition = config('appointments.actions.'.$action);
        abort_unless($definition, 404);
        abort_unless(app(ClinicAccessService::class)->can($context['permissions'], $definition['permission']), 403);

        return DB::transaction(function () use ($context, $id, $action, $data, $definition) {
            app(BookingCore::class)->lock($context['clinic']->id);
            $service = app(AppointmentService::class);
            $a = $service->find($context, $id);
            if (! in_array($a->status, $definition['from'])) {
                throw ValidationException::withMessages(['status' => 'This status transition is not allowed. Refresh the appointment.']);
            }
            $now = now($context['clinic']->timezone);
            if (in_array($action, ['check-in', 'start-consultation', 'complete']) && substr($a->starts_at, 0, 10) > $now->toDateString()) {
                throw ValidationException::withMessages(['status' => 'This action is not available before the appointment date.']);
            }
            if ($action === 'no-show' && $a->starts_at > $now->format('Y-m-d H:i:s')) {
                throw ValidationException::withMessages(['status' => 'A future appointment cannot be marked no show.']);
            }
            $a->status = $definition['to'];
            if (isset($definition['timestamp'])) {
                $a->{$definition['timestamp']} = now();
            }
            if ($action === 'cancel') {
                app(\App\Services\Billing\BillingDepositService::class)->resolveCancellation($context, $a->depositInvoice, $data['deposit_disposition'] ?? '', $data['deposit_idempotency_key'] ?? 'cancel-'.$a->id);
                $a->cancelled_by = request()->user()->id;
                $a->cancellation_reason = $data['reason'];
                $a->deposit_disposition = \App\Services\Billing\BillingMoney::cents($a->depositInvoice?->paid ?? 0) ? $data['deposit_disposition'] : 'void';
                $a->deposit_disposition_at = now(); $a->deposit_disposition_by = request()->user()->id;
            }
            $a->updated_by = request()->user()->id;
            $a->save();
            $service->audit($a, $definition['event']);

            return $a;
        }, 3);
    }
}
