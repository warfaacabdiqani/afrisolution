<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientService
{
    public function audit(Patient $patient, string $action): void
    {
        app(PlatformService::class)->audit(request()->user()->id, $action, 'tenant', $patient->tenant_id, ['patient_id' => $patient->id, 'branch_id' => request()->session()->get('branch_id')]);
    }

    public function duplicates(array $data)
    {
        return Patient::where(function ($query) use ($data) {
            $query->whereRaw('1 = 0');
            if (!empty($data['phone'])) $query->orWhere('phone', $data['phone']);
            if (!empty($data['email'])) $query->orWhereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])]);
            if (!empty($data['date_of_birth'])) $query->orWhere(fn ($q) => $q->whereRaw('LOWER(first_name) = ?', [mb_strtolower($data['first_name'])])->whereRaw('LOWER(last_name) = ?', [mb_strtolower($data['last_name'])])->whereDate('date_of_birth', $data['date_of_birth']));
        })->limit(5)->get();
    }

    public function create(array $data, array $context): Patient
    {
        return DB::transaction(function () use ($data, $context) {
            // All registrations and quota writes serialize on the tenant, including sequence allocation.
            $tenant = Tenant::lockForUpdate()->findOrFail($context['clinic']->id);
            $plan = DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')->where('tenant_id', $tenant->id)->select('plans.patient_limit')->first();
            $limit = $plan?->patient_limit;
            // Retained records, including archived patients, consume quota.
            if ($limit !== null && Patient::count() >= $limit) throw ValidationException::withMessages(['plan' => "Your current plan allows up to {$limit} patients. Upgrade your subscription to register additional patients."]);
            if (!($data['confirm_duplicate'] ?? false)) {
                $matches = $this->duplicates($data);
                if ($matches->isNotEmpty()) throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => 'Possible matching patient found.', 'duplicates' => $matches->map(fn ($p) => ['id' => $p->id, 'full_name' => $p->full_name, 'patient_number' => $p->patient_number, 'phone' => $p->phone, 'date_of_birth' => $p->date_of_birth?->toDateString()])], 409));
            }
            $tenant->increment('patient_sequence');
            $patient = new Patient(collect($data)->except(['allergy', 'condition', 'confirm_duplicate'])->all());
            $patient->patient_number = strtoupper(substr(Str::slug($tenant->slug), 0, 12)).'-'.str_pad($tenant->patient_sequence, 6, '0', STR_PAD_LEFT);
            $patient->registration_branch_id = $context['branch']->id;
            $patient->created_by = $patient->updated_by = request()->user()->id;
            $patient->registered_at = now(); $patient->save();
            $this->audit($patient, 'patient.created');
            if (!empty($data['allergy'])) { $patient->allergies()->create(['allergen' => $data['allergy'], 'recorded_by' => request()->user()->id]); $this->audit($patient, 'patient.allergy.created'); }
            if (!empty($data['condition'])) { $patient->conditions()->create(['condition_name' => $data['condition'], 'recorded_by' => request()->user()->id]); $this->audit($patient, 'patient.condition.created'); }
            return $patient;
        }, 3);
    }
}
