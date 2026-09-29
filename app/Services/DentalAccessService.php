<?php
namespace App\Services;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DentalAccessService
{
    public function context(Request $request, ?string $permission = null): array
    {
        $access = app(ClinicAccessService::class);
        $context = $access->authorize($request, 'dental', $permission);
        if ($context['business_type']['slug'] !== 'dental') $access->deny('BUSINESS_MODULE_UNAVAILABLE', 'Dental workflows require a Dental business.');
        return $context;
    }

    public function patient(array $context, int $id, bool $write = false): Patient
    {
        $access = app(ClinicAccessService::class);
        if (!$access->can($context['permissions'], 'patients.view') || empty($context['features']['patient_management'])) {
            $access->deny('PERMISSION_DENIED', 'Patient access is required.');
        }
        $patient = Patient::findOrFail($id);
        if ($write && $patient->status === 'archived') throw ValidationException::withMessages(['patient' => 'Archived patients are read-only.']);
        return $patient;
    }

    public function audit(array $context, string $action, array $ids): void
    {
        app(PlatformService::class)->audit(request()->user()->id, 'dental.'.$action, 'tenant', $context['clinic']->id, $ids);
    }
}
