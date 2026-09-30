<?php
namespace App\Services;

use App\Models\{Appointment, DentalPlan, DentalProcedure};
use App\Services\Billing\{BillingLock, BillingMoney};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DentalPlanService
{
    public function visible(array $context)
    {
        return DentalPlan::whereIn('branch_id', $context['branches']->pluck('id'));
    }

    public function find(array $context, int $id, bool $write = false): DentalPlan
    {
        $plan = $this->visible($context)->findOrFail($id);
        app(DentalAccessService::class)->patient($context, $plan->patient_id, $write);
        return $plan;
    }

    public function present(DentalPlan $plan, array $context): array
    {
        $plan->load(['items.completedBy']);
        if (app(ClinicAccessService::class)->can($context['permissions'], 'billing.view') && !empty($context['features']['billing'])) {
            $plan->load('items.invoice:id,source_id,source_type');
        }
        $items = $plan->items->map(function ($item) {
            $data = $item->toArray();
            $data['amount'] = BillingMoney::decimal(BillingMoney::cents($item->unit_price) * $item->quantity);
            $data['invoice_id'] = $item->relationLoaded('invoice') ? $item->invoice?->id : null;
            unset($data['invoice']);
            return $data;
        })->all();
        return array_replace($plan->toArray(), $this->totals($items, $plan->tax_rate), ['items' => $items]);
    }

    private function totals(array $items, string $taxRate): array
    {
        // Each completed treatment issues its own invoice: round tax at that same boundary.
        $sums = ['subtotal' => 0, 'tax' => 0, 'total' => 0];
        foreach ($items as $item) {
            $calculation = app(BillingMoney::class)->calculate([['description' => $item['procedure_name'],
                'unit_price' => $item['unit_price'], 'quantity' => $item['quantity']]], '0.00', $taxRate);
            foreach ($sums as $field => $sum) $sums[$field] += BillingMoney::cents($calculation['totals'][$field]);
        }
        if ($sums['total'] > 999999999999) throw ValidationException::withMessages(['items' => 'The plan exceeds the supported amount.']);
        return array_map(fn ($value) => BillingMoney::decimal($value), $sums) + ['discount' => '0.00', 'tax_rate' => $taxRate];
    }

    public function save(array $context, int $patient, array $data, ?int $id = null): DentalPlan
    {
        return DB::transaction(function () use ($context, $patient, $data, $id) {
            app(BillingLock::class)->acquire($context['clinic']->id);
            app(DentalAccessService::class)->patient($context, $patient, true);
            $plan = $id ? $this->find($context, $id, true) : new DentalPlan;
            if ($id) {
                abort_unless((int) $plan->patient_id === $patient, 404);
                if ($plan->status !== 'draft') throw ValidationException::withMessages(['plan' => 'Only draft plans can be edited.']);
                $this->version($plan, $data['version']);
            }
            $rows = [];
            foreach ($data['items'] as $item) {
                $procedure = DentalProcedure::where('active', true)->find($item['procedure_id']);
                if (!$procedure) throw ValidationException::withMessages(['items' => 'Select active procedures belonging to this Dental business.']);
                $rows[] = [
                    'procedure_id' => $procedure->id, 'procedure_code' => $procedure->code, 'procedure_name' => $procedure->name,
                    'tooth' => $item['tooth'] ?? null, 'surfaces' => $item['surfaces'] ?? [], 'visit_number' => $item['visit_number'],
                    'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'] ?? $procedure->price, 'notes' => $item['notes'] ?? null,
                ];
            }
            if (!$id) {
                $settings = app(ClinicSettingsService::class)->section($context['clinic']->id, 'billing');
                $plan->fill(['patient_id' => $patient, 'branch_id' => $context['branch']->id, 'created_by' => request()->user()->id,
                    'currency' => $settings['currency'], 'tax_rate' => $settings['tax_rate'], 'status' => 'draft', 'version' => 1]);
            } else {
                $plan->version++;
            }
            $this->totals($rows, $plan->tax_rate);
            $plan->fill(['title' => $data['title'], 'notes' => $data['notes'] ?? null])->save();
            // Only unaccepted draft items are replaceable; accepted/completed records are never deleted.
            if ($id) $plan->items()->delete();
            $plan->items()->createMany($rows);
            app(DentalAccessService::class)->audit($context, $id ? 'plan.updated' : 'plan.created', ['patient_id' => $patient, 'plan_id' => $plan->id]);
            return $plan;
        }, 5);
    }

    private function version(DentalPlan $plan, int $version): void
    {
        abort_unless($plan->version === $version, 409, 'This plan changed. Reload it before saving.');
    }

    public function transition(array $context, int $id, array $data): DentalPlan
    {
        return DB::transaction(function () use ($context, $id, $data) {
            app(BillingLock::class)->acquire($context['clinic']->id);
            $plan = $this->find($context, $id, true);
            $this->version($plan, $data['version']);
            $target = $data['status'];
            if (($target === 'accepted' && $plan->status !== 'draft') || ($target === 'cancelled' && !in_array($plan->status, ['draft', 'accepted']))) {
                throw ValidationException::withMessages(['status' => 'This plan cannot make that status change.']);
            }
            $plan->status = $target; $plan->version++;
            if ($target === 'accepted') $plan->accepted_at = now();
            else {
                $plan->cancelled_at = now(); $plan->cancellation_reason = $data['reason'];
                $plan->items()->where('status', 'planned')->update(['status' => 'cancelled']);
            }
            $plan->save();
            app(DentalAccessService::class)->audit($context, 'plan.'.$target, ['plan_id' => $id, 'patient_id' => $plan->patient_id]);
            return $plan;
        }, 5);
    }

    public function complete(array $context, int $id, int $itemId, array $data): DentalPlan
    {
        return DB::transaction(function () use ($context, $id, $itemId, $data) {
            app(BillingLock::class)->acquire($context['clinic']->id);
            $plan = $this->find($context, $id, true);
            $item = $plan->items()->findOrFail($itemId);
            if ($item->status === 'completed') return $plan;
            if ($plan->status !== 'accepted' || $item->status !== 'planned') throw ValidationException::withMessages(['status' => 'Accept the plan before completing planned treatments.']);
            if (!empty($data['appointment_id'])) {
                $appointment = Appointment::where('patient_id', $plan->patient_id)->where('branch_id', $plan->branch_id)
                    ->whereIn('status', ['in_consultation', 'completed'])->find($data['appointment_id']);
                if (!$appointment) throw ValidationException::withMessages(['appointment_id' => 'Select an in-consultation or completed appointment for this patient and plan branch.']);
            }
            $item->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => request()->user()->id,
                'appointment_id' => $data['appointment_id'] ?? null, 'completion_notes' => $data['notes'] ?? null]);
            if (!$plan->items()->where('status', '!=', 'completed')->exists()) {
                $plan->status = 'completed'; $plan->completed_at = now();
            }
            $plan->version++; $plan->save();
            app(DentalAccessService::class)->audit($context, 'treatment.completed', ['patient_id' => $plan->patient_id, 'plan_id' => $id, 'item_id' => $itemId]);
            return $plan;
        }, 5);
    }
}
