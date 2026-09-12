<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\ClinicAccessService;
use App\Services\ClinicSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportsController extends Controller
{
    public function overview(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);
        $billing = app(ClinicSettingsService::class)->section($context['clinic']->id, 'billing');

        $patients = $this->patientScope($state)->with(['registrationBranch'])->get();
        $appointments = $this->appointmentScope($state)->with(['patient', 'doctor', 'branch', 'type'])->get();
        $prescriptions = $this->prescriptionScope($state)->with(['patient', 'doctor'])->get();

        $completedAppointments = $appointments->where('status', 'completed');
        $estimatedRevenue = $completedAppointments->sum(fn (Appointment $appointment) => $this->resolvedConsultationFee($appointment, $billing));

        return response()->json(['data' => [
            'metrics' => [
                ['label' => 'Total Patients', 'value' => $patients->count(), 'help' => 'All active and archived patient records in scope'],
                ['label' => 'Appointments', 'value' => $appointments->count(), 'help' => 'Appointments in the selected date range'],
                ['label' => 'Consultations', 'value' => $completedAppointments->count(), 'help' => 'Completed consultations'],
                ['label' => 'Revenue', 'value' => $estimatedRevenue, 'help' => 'Estimated revenue from completed consultations'],
                ['label' => 'Outstanding', 'value' => 0, 'help' => 'No invoice/payment tables are present in this release yet'],
            ],
            'charts' => [
                ['title' => 'Patient Registrations Trend', 'type' => 'bar', 'items' => $this->chartItems($this->seriesByDay($patients, 'registered_at'))],
                ['title' => 'Appointment Trend', 'type' => 'bar', 'items' => $this->chartItems($this->seriesByDay($appointments, 'starts_at'))],
                ['title' => 'Consultation Trend', 'type' => 'bar', 'items' => $this->chartItems($this->seriesByDay($completedAppointments, 'completed_at'))],
                ['title' => 'Revenue Trend', 'type' => 'bar', 'items' => $this->chartItems($this->seriesRevenueByDay($completedAppointments, $billing))],
                ['title' => 'Appointment Status Distribution', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($appointments, 'status'))],
            ],
            'table' => $patients->sortByDesc('registered_at')->take(10)->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'name' => $patient->full_name,
                'gender' => $patient->gender,
                'age' => $patient->age,
                'registered_at' => $patient->registered_at?->toDateString(),
                'branch' => $patient->registrationBranch?->name ?? '—',
            ])->values()->all(),
            'message' => null,
        ]]);
    }

    public function patients(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);

        $patients = $this->patientScope($state)->with(['registrationBranch'])->get();
        $recent = $patients->sortByDesc('registered_at')->take(10)->values();

        return response()->json(['data' => [
            'metrics' => [
                ['label' => 'Total Patients', 'value' => $patients->count()],
                ['label' => 'New Patients', 'value' => $patients->filter(fn ($patient) => $this->inRange($patient->registered_at ?? $patient->created_at, $state))->count()],
                ['label' => 'Active Patients', 'value' => $patients->where('status', 'active')->count()],
                ['label' => 'Archived Patients', 'value' => $patients->where('status', 'archived')->count()],
            ],
            'charts' => [
                ['title' => 'Patient Registrations Over Time', 'type' => 'bar', 'items' => $this->chartItems($this->seriesByDay($patients, 'registered_at'))],
                ['title' => 'Gender Distribution', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($patients, 'gender'))],
                ['title' => 'Age Group Distribution', 'type' => 'bar', 'items' => $this->chartItems($this->seriesAgeGroups($patients))],
                ['title' => 'Branch Distribution', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($patients->whereNotNull('registrationBranch'), 'registration_branch_id', fn ($patient) => $patient->registrationBranch?->name ?? 'Unknown'))],
            ],
            'table' => $recent->map(fn (Patient $patient) => [
                'patient_number' => $patient->patient_number,
                'name' => $patient->full_name,
                'gender' => $patient->gender,
                'age' => $patient->age,
                'registered_at' => $patient->registered_at?->toDateString(),
                'branch' => $patient->registrationBranch?->name ?? '—',
            ])->all(),
            'message' => null,
        ]]);
    }

    public function appointments(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);
        $appointments = $this->appointmentScope($state)->with(['patient', 'doctor', 'branch', 'type'])->get();

        return response()->json(['data' => [
            'metrics' => [
                ['label' => 'Total Appointments', 'value' => $appointments->count()],
                ['label' => 'Completed', 'value' => $appointments->where('status', 'completed')->count()],
                ['label' => 'Cancelled', 'value' => $appointments->where('status', 'cancelled')->count()],
                ['label' => 'No Shows', 'value' => $appointments->where('status', 'no_show')->count()],
                ['label' => 'Walk-ins', 'value' => $appointments->where('is_walk_in', true)->count()],
            ],
            'charts' => [
                ['title' => 'Appointments by Day', 'type' => 'bar', 'items' => $this->chartItems($this->seriesByDay($appointments, 'starts_at'))],
                ['title' => 'Appointments by Status', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($appointments, 'status'))],
                ['title' => 'Appointments by Doctor', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($appointments, 'doctor_id', fn ($appointment) => $appointment->doctor?->full_name ?? 'Unknown'))],
                ['title' => 'Appointments by Visit Type', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($appointments, 'appointment_type_id', fn ($appointment) => $appointment->type?->name ?? 'General'))],
                ['title' => 'Appointments by Branch', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($appointments, 'branch_id', fn ($appointment) => $appointment->branch?->name ?? 'Unknown'))],
            ],
            'table' => [],
            'message' => null,
        ]]);
    }

    public function clinical(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);
        $appointments = $this->appointmentScope($state)->with(['patient', 'doctor', 'branch', 'type'])->get();
        $prescriptions = $this->prescriptionScope($state)->with(['patient', 'doctor'])->get();
        $completed = $appointments->whereIn('status', ['completed', 'in_consultation']);

        return response()->json(['data' => [
            'metrics' => [
                ['label' => 'Total Consultations', 'value' => $completed->count()],
                ['label' => 'Completed Consultations', 'value' => $appointments->where('status', 'completed')->count()],
                ['label' => 'Follow-ups', 'value' => $prescriptions->count(), 'help' => 'Prescriptions created in the period'],
                ['label' => 'Average Consultations / Day', 'value' => $completed->count() > 0 ? round($completed->count() / max(1, $state['days']), 2) : 0],
            ],
            'charts' => [
                ['title' => 'Consultations by Day', 'type' => 'bar', 'items' => $this->chartItems($this->seriesByDay($completed, 'completed_at'))],
                ['title' => 'Consultations by Doctor', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($completed, 'doctor_id', fn ($appointment) => $appointment->doctor?->full_name ?? 'Unknown'))],
                ['title' => 'Consultations by Visit Source', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($completed, 'source'))],
                ['title' => 'Consultations by Branch', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($completed, 'branch_id', fn ($appointment) => $appointment->branch?->name ?? 'Unknown'))],
            ],
            'table' => [],
            'message' => 'Follow-up tracking is currently derived from prescription activity because a dedicated follow-up model is not yet implemented.',
        ]]);
    }

    public function doctors(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);
        $appointments = $this->appointmentScope($state)->with(['doctor'])->get();
        $doctors = Doctor::where('tenant_id', $context['clinic']->id)->whereIn('id', $state['branchIds'] ? $appointments->pluck('doctor_id')->filter()->unique()->all() : [0])->get();
        $prescriptions = $this->prescriptionScope($state)->with(['doctor'])->get();

        $rows = [];
        foreach ($doctors as $doctor) {
            $doctorAppointments = $appointments->where('doctor_id', $doctor->id);
            $doctorPrescriptions = $prescriptions->where('doctor_id', $doctor->id);
            $rows[] = [
                'doctor' => $doctor->full_name,
                'appointments' => $doctorAppointments->count(),
                'consultations' => $doctorAppointments->where('status', 'completed')->count(),
                'cancelled' => $doctorAppointments->where('status', 'cancelled')->count(),
                'no_shows' => $doctorAppointments->where('status', 'no_show')->count(),
                'prescriptions' => $doctorPrescriptions->count(),
                'revenue' => round($doctorAppointments->where('status', 'completed')->sum(fn ($appointment) => $this->resolvedConsultationFee($appointment, app(ClinicSettingsService::class)->section($context['clinic']->id, 'billing'))), 2),
            ];
        }

        return response()->json(['data' => [
            'metrics' => [],
            'charts' => [
                ['title' => 'Consultations per Doctor', 'type' => 'bar', 'items' => $this->chartItems(collect($rows)->map(fn ($row) => ['label' => $row['doctor'], 'value' => $row['consultations']]))],
                ['title' => 'Appointments per Doctor', 'type' => 'bar', 'items' => $this->chartItems(collect($rows)->map(fn ($row) => ['label' => $row['doctor'], 'value' => $row['appointments']]))],
            ],
            'table' => $rows,
            'message' => null,
        ]]);
    }

    public function prescriptions(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);
        $prescriptions = $this->prescriptionScope($state)->with(['patient', 'doctor'])->get();
        $items = PrescriptionItem::where('tenant_id', $context['clinic']->id)->whereIn('prescription_id', $prescriptions->pluck('id'))->get();

        $metricRows = [
            ['label' => 'Total Prescriptions', 'value' => $prescriptions->count()],
            ['label' => 'Active', 'value' => $prescriptions->where('status', 'draft')->count() + $prescriptions->where('status', 'active')->count()],
            ['label' => 'Dispensed', 'value' => $prescriptions->where('status', 'dispensed')->count()],
            ['label' => 'Cancelled', 'value' => $prescriptions->where('status', 'cancelled')->count()],
            ['label' => 'Medication Items', 'value' => $items->count()],
        ];

        $medicationSummary = $items->groupBy('medication_name')->map(fn ($group) => [
            'medication' => $group->first()->medication_name,
            'times_prescribed' => $group->count(),
            'total_quantity' => round($group->sum(fn ($item) => (float) ($item->quantity ?? 0)), 2),
            'doctors' => $group->pluck('prescription.doctor_id')->filter()->unique()->count(),
            'status' => $group->first()->status,
        ])->values();

        return response()->json(['data' => [
            'metrics' => $metricRows,
            'charts' => [
                ['title' => 'Prescriptions Over Time', 'type' => 'bar', 'items' => $this->chartItems($this->seriesByDay($prescriptions, 'prescription_date'))],
                ['title' => 'Prescriptions by Doctor', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($prescriptions, 'doctor_id', fn ($prescription) => $prescription->doctor?->full_name ?? 'Unknown'))],
                ['title' => 'Prescription Status', 'type' => 'bar', 'items' => $this->chartItems($this->seriesCounts($prescriptions, 'status'))],
            ],
            'table' => $medicationSummary->sortByDesc('times_prescribed')->take(10)->values()->all(),
            'message' => null,
        ]]);
    }

    public function financial(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);
        $billing = app(ClinicSettingsService::class)->section($context['clinic']->id, 'billing');
        $appointments = $this->appointmentScope($state)->with(['doctor', 'branch'])->get();
        $completed = $appointments->where('status', 'completed');
        $grossBilled = $completed->sum(fn ($appointment) => $this->resolvedConsultationFee($appointment, $billing));

        return response()->json(['data' => [
            'metrics' => [
                ['label' => 'Revenue', 'value' => $grossBilled],
                ['label' => 'Amount Collected', 'value' => 0],
                ['label' => 'Outstanding Balance', 'value' => 0],
                ['label' => 'Refunds', 'value' => 0],
                ['label' => 'Invoices', 'value' => $completed->count()],
                ['label' => 'Payments', 'value' => 0],
            ],
            'charts' => [
                ['title' => 'Revenue Trend', 'type' => 'bar', 'items' => $this->chartItems($this->seriesRevenueByDay($completed, $billing))],
                ['title' => 'Revenue by Branch', 'type' => 'bar', 'items' => $this->chartItems($this->seriesBranchRevenue($completed, $billing))],
            ],
            'table' => [],
            'message' => 'Financial transaction tables are not yet implemented in this release, so collected payments, refunds and outstanding balances remain derived from available appointment data only.',
        ]]);
    }

    public function branches(Request $request, ClinicAccessService $access)
    {
        $context = $access->authorize($request, 'reports');
        $state = $this->resolveState($request, $context);
        $billing = app(ClinicSettingsService::class)->section($context['clinic']->id, 'billing');

        $appointments = $this->appointmentScope($state)->with(['doctor', 'branch'])->get();
        $patients = $this->patientScope($state)->with(['registrationBranch'])->get();

        $rows = [];
        foreach ($context['branches'] as $branch) {
            $branchAppointments = $appointments->where('branch_id', $branch->id);
            $branchPatients = $patients->where('registration_branch_id', $branch->id);
            $rows[] = [
                'branch' => $branch->name,
                'patients' => $branchPatients->count(),
                'appointments' => $branchAppointments->count(),
                'consultations' => $branchAppointments->where('status', 'completed')->count(),
                'doctors' => Doctor::where('tenant_id', $context['clinic']->id)->whereHas('branches', fn ($q) => $q->where('branches.id', $branch->id))->count(),
                'revenue' => round($branchAppointments->where('status', 'completed')->sum(fn ($appointment) => $this->resolvedConsultationFee($appointment, $billing)), 2),
                'collections' => 0,
                'outstanding' => 0,
            ];
        }

        return response()->json(['data' => [
            'metrics' => [],
            'charts' => [],
            'table' => $rows,
            'message' => null,
        ]]);
    }

    private function resolveState(Request $request, array $context): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', Rule::in(['all', ...$context['branches']->pluck('id')->all()])],
            'doctor_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
            'patient_id' => ['nullable', 'integer'],
            'appointment_type_id' => ['nullable', 'integer'],
        ]);

        $allowedBranchIds = $context['branches']->pluck('id')->all();
        $branchId = $validated['branch_id'] ?? null;

        if ($branchId === 'all') {
            $branchIds = $allowedBranchIds;
        } elseif ($branchId) {
            abort_unless(in_array((int) $branchId, $allowedBranchIds, true), 403, 'You are not authorized to view that branch.');
            $branchIds = [(int) $branchId];
        } else {
            $branchIds = [$context['branch']->id];
        }

        $from = Carbon::parse($validated['from'] ?? $context['today'])->startOfMonth()->toDateString();
        $to = Carbon::parse($validated['to'] ?? $context['today'])->toDateString();

        return [
            'tenantId' => $context['clinic']->id,
            'branchIds' => $branchIds,
            'from' => $from,
            'to' => $to,
            'days' => max(1, Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1),
            'doctorId' => $validated['doctor_id'] ?? null,
            'status' => $validated['status'] ?? null,
            'patientId' => $validated['patient_id'] ?? null,
            'appointmentTypeId' => $validated['appointment_type_id'] ?? null,
        ];
    }

    private function patientScope(array $state)
    {
        $query = Patient::where('tenant_id', $state['tenantId'])
            ->whereIn('registration_branch_id', $state['branchIds'])
            ->where('status', '!=', 'archived');

        if ($state['patientId']) {
            $query->whereKey($state['patientId']);
        }

        return $query->whereDate('registered_at', '>=', $state['from'])
            ->whereDate('registered_at', '<=', $state['to']);
    }

    private function appointmentScope(array $state)
    {
        $query = Appointment::where('tenant_id', $state['tenantId'])
            ->whereIn('branch_id', $state['branchIds']);

        if ($state['doctorId']) {
            $query->where('doctor_id', $state['doctorId']);
        }

        if ($state['status']) {
            $query->where('status', $state['status']);
        }

        if ($state['patientId']) {
            $query->where('patient_id', $state['patientId']);
        }

        if ($state['appointmentTypeId']) {
            $query->where('appointment_type_id', $state['appointmentTypeId']);
        }

        return $query->whereDate('starts_at', '>=', $state['from'])
            ->whereDate('starts_at', '<=', $state['to']);
    }

    private function prescriptionScope(array $state)
    {
        $query = Prescription::where('tenant_id', $state['tenantId'])
            ->whereIn('branch_id', $state['branchIds']);

        if ($state['doctorId']) {
            $query->where('doctor_id', $state['doctorId']);
        }

        if ($state['patientId']) {
            $query->where('patient_id', $state['patientId']);
        }

        return $query->whereDate('prescription_date', '>=', $state['from'])
            ->whereDate('prescription_date', '<=', $state['to']);
    }

    private function seriesByDay($collection, string $dateField): array
    {
        $dates = collect();
        foreach ($collection as $item) {
            $date = $item->{$dateField};
            if ($date instanceof \DateTimeInterface) {
                $label = $date->format('Y-m-d');
            } else {
                $label = Carbon::parse($date)->toDateString();
            }

            $dates->has($label) ? $dates[$label]++ : $dates->put($label, 1);
        }

        return $dates->sortKeys()->map(fn (int $count, string $label) => ['label' => $label, 'value' => $count])->values()->all();
    }

    private function seriesRevenueByDay($collection, array $billing): array
    {
        $dates = collect();
        foreach ($collection as $appointment) {
            $label = Carbon::parse($appointment->completed_at ?? $appointment->starts_at)->toDateString();
            $dates[$label] = ($dates[$label] ?? 0) + $this->resolvedConsultationFee($appointment, $billing);
        }

        return $dates->sortKeys()->map(fn ($value, $label) => ['label' => $label, 'value' => round((float) $value, 2)])->values()->all();
    }

    private function seriesCounts($collection, string $field, ?callable $labelMapper = null): array
    {
        $series = $collection->groupBy(function ($item) use ($field, $labelMapper) {
            $value = $item->{$field};
            if ($labelMapper) {
                return $labelMapper($item);
            }
            return $value ?? 'Unknown';
        })->map(fn ($group) => $group->count());

        return $series->sortKeys()->map(fn ($count, $label) => ['label' => (string) $label, 'value' => $count])->values()->all();
    }

    private function seriesAgeGroups($collection): array
    {
        $ranges = [
            '0-5' => ['min' => 0, 'max' => 5],
            '6-12' => ['min' => 6, 'max' => 12],
            '13-17' => ['min' => 13, 'max' => 17],
            '18-30' => ['min' => 18, 'max' => 30],
            '31-45' => ['min' => 31, 'max' => 45],
            '46-60' => ['min' => 46, 'max' => 60],
            '61+' => ['min' => 61, 'max' => 999],
        ];

        $summary = collect($ranges)->map(fn () => 0)->all();

        foreach ($collection as $patient) {
            $age = (int) $patient->age;
            foreach ($ranges as $label => $range) {
                if ($age >= $range['min'] && $age <= $range['max']) {
                    $summary[$label]++;
                    break;
                }
            }
        }

        return collect($summary)->map(fn ($value, $label) => ['label' => $label, 'value' => $value])->values()->all();
    }

    private function seriesBranchRevenue($collection, array $billing): array
    {
        $series = collect();
        foreach ($collection as $appointment) {
            $label = $appointment->branch?->name ?? 'Unknown';
            $series[$label] = ($series[$label] ?? 0) + $this->resolvedConsultationFee($appointment, $billing);
        }

        return $series->sortKeys()->map(fn ($value, $label) => ['label' => $label, 'value' => round((float) $value, 2)])->values()->all();
    }

    private function chartItems(array $items): array
    {
        return $items;
    }

    private function inRange($date, array $state): bool
    {
        if (empty($date)) {
            return false;
        }

        $value = Carbon::parse($date);
        return $value->toDateString() >= $state['from'] && $value->toDateString() <= $state['to'];
    }

    private function resolvedConsultationFee(Appointment $appointment, array $billing): float
    {
        if ($appointment->doctor && $appointment->doctor->consultation_fee !== null) {
            return (float) $appointment->doctor->consultation_fee;
        }

        return (float) ($billing['consultation_fee'] ?? 0);
    }
}
