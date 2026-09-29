<?php
namespace App\Services;

use App\Models\{DentalFinding, DentalPlanItem};
use App\Services\Billing\BillingLock;
use Illuminate\Support\Facades\DB;

class DentalChartService
{
    public function chart(array $context, int $patient): array
    {
        app(DentalAccessService::class)->patient($context, $patient);
        return [
            'findings' => DentalFinding::where('patient_id', $patient)->whereIn('branch_id', $context['branches']->pluck('id'))
                ->with('author')->orderByDesc('id')->get(),
            'treatments' => DentalPlanItem::where('status', 'completed')->whereHas('plan', fn ($q) => $q
                ->where('patient_id', $patient)->whereIn('branch_id', $context['branches']->pluck('id')))
                ->with('completedBy')->orderByDesc('completed_at')->orderByDesc('id')->get(),
        ];
    }

    public function record(array $context, int $patient, array $data): DentalFinding
    {
        return DB::transaction(function () use ($context, $patient, $data) {
            app(BillingLock::class)->acquire($context['clinic']->id);
            app(DentalAccessService::class)->patient($context, $patient, true);
            $finding = DentalFinding::create($data + ['patient_id' => $patient, 'branch_id' => $context['branch']->id,
                'surfaces' => [], 'recorded_by' => request()->user()->id]);
            app(DentalAccessService::class)->audit($context, 'finding.recorded', ['patient_id' => $patient, 'finding_id' => $finding->id]);
            return $finding->load('author');
        }, 5);
    }

    public function void(array $context, int $patient, int $id, string $reason): DentalFinding
    {
        return DB::transaction(function () use ($context, $patient, $id, $reason) {
            app(BillingLock::class)->acquire($context['clinic']->id);
            app(DentalAccessService::class)->patient($context, $patient, true);
            $finding = DentalFinding::where('patient_id', $patient)->whereIn('branch_id', $context['branches']->pluck('id'))->findOrFail($id);
            if (!$finding->voided_at) {
                $finding->update(['voided_at' => now(), 'voided_by' => request()->user()->id, 'void_reason' => $reason]);
                app(DentalAccessService::class)->audit($context, 'finding.voided', ['patient_id' => $patient, 'finding_id' => $id]);
            }
            return $finding;
        }, 5);
    }
}
