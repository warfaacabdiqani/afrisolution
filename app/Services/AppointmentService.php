<?php

namespace App\Services;

use App\Events\AppointmentChanged;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function visible(array $context)
    {
        $query = Appointment::whereIn('branch_id', $context['branches']->pluck('id'));
        if (! app(ClinicAccessService::class)->can($context['permissions'], 'appointments.view_all')) {
            $query->whereHas('doctor', fn ($q) => $q->where('user_id', request()->user()->id));
        }

        return $query;
    }

    public function find(array $context, int $id): Appointment
    {
        return $this->visible($context)->with(['patient', 'doctor', 'branch', 'type', 'creator'])->findOrFail($id);
    }

    public function audit(Appointment $appointment, string $action, array $extra = []): void
    {
        app(PlatformService::class)->audit(request()->user()->id, 'appointment.'.$action, 'tenant', $appointment->tenant_id, ['appointment_id' => $appointment->id, 'branch_id' => $appointment->branch_id, 'patient_id' => $appointment->patient_id, 'doctor_id' => $appointment->doctor_id] + $extra);
        AppointmentChanged::dispatch($appointment->tenant_id, $appointment->id, $action);
    }

    public function save(array $context, array $data, ?int $id = null, bool $reschedule = false): Appointment
    {
        return DB::transaction(function () use ($context, $data, $id) {
            $tenant = Tenant::lockForUpdate()->findOrFail($context['clinic']->id);
            $a = $id ? $this->find($context, $id) : new Appointment;
            if ($id && in_array($a->status, array_merge(config('appointments.terminal'), ['in_consultation']))) {
                throw ValidationException::withMessages(['status' => 'This appointment can no longer be edited or rescheduled.']);
            }
            $start = Carbon::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['start_time'], $context['clinic']->timezone);
            $end = $start->copy()->addMinutes((int) $data['duration']);
            $changed = ! $id || $a->starts_at !== $start->format('Y-m-d H:i:s') || $a->ends_at !== $end->format('Y-m-d H:i:s') || $a->doctor_id != $data['doctor_id'] || $a->branch_id != $data['branch_id'];
            if ($id && $changed) {
                abort_unless(app(ClinicAccessService::class)->can($context['permissions'], 'appointments.reschedule'), 403, 'Rescheduling permission is required.');
            }
            $availability = app(AppointmentAvailabilityService::class);
            $doctor = $availability->doctor($context, $data['doctor_id'], $data['branch_id']);
            $patient = Patient::where('status', 'active')->find($data['patient_id']);
            if (! $patient) {
                throw ValidationException::withMessages(['patient_id' => 'Select an active patient in this clinic.']);
            }
            if (! empty($data['appointment_type_id']) && ! AppointmentType::where('status', 'active')->whereKey($data['appointment_type_id'])->exists()) {
                throw ValidationException::withMessages(['appointment_type_id' => 'Select an active appointment type in this clinic.']);
            }
            $walkIn = $data['source'] === 'walk_in';
            $preferences=app(ClinicSettingsService::class)->section($tenant->id,'appointments');
            if($walkIn && !$preferences['allow_walk_in']) throw ValidationException::withMessages(['source'=>'Walk-in appointments are disabled by clinic policy.']);
            if($changed && !$preferences['allow_same_day'] && $start->isSameDay(now($context['clinic']->timezone))) throw ValidationException::withMessages(['date'=>'Same-day booking is disabled by clinic policy.']);
            if ($changed && $start->lt(now($context['clinic']->timezone)) && ! ($walkIn && $start->isSameDay(now($context['clinic']->timezone)))) {
                throw ValidationException::withMessages(['start_time' => 'Select a future time, or register a walk-in for today.']);
            }
            $override = $data['override_schedule'] ?? false;
            if ($override) {
                abort_unless(app(ClinicAccessService::class)->can($context['permissions'], 'appointments.override_schedule'), 403);
            }
            if ($changed || $a->patient_id != $data['patient_id']) {
                $error = $availability->reason($doctor, $data['branch_id'], $start, $end, $patient->id, $id, $override);
                if ($error) {
                    throw ValidationException::withMessages(['start_time' => $error]);
                }
            }
            $limit = $context['limits']['appointment_limit'] ?? null;
            if ($limit !== null && (! $id || substr($a->starts_at, 0, 7) !== $start->format('Y-m')) && Appointment::when($id, fn ($q) => $q->where('id', '!=', $id))->where('starts_at', '>=', $start->copy()->startOfMonth()->format('Y-m-d H:i:s'))->where('starts_at', '<', $start->copy()->addMonthNoOverflow()->startOfMonth()->format('Y-m-d H:i:s'))->count() >= $limit) {
                throw ValidationException::withMessages(['plan' => 'Your plan has reached its monthly appointment limit.']);
            }
            $previous = $id ? ['previous_starts_at' => $a->starts_at, 'previous_ends_at' => $a->ends_at, 'previous_doctor_id' => $a->doctor_id, 'previous_branch_id' => $a->branch_id] : [];
            $a->fill(collect($data)->only(['branch_id', 'patient_id', 'doctor_id', 'appointment_type_id', 'reason', 'notes', 'source'])->all());
            $a->starts_at = $start->format('Y-m-d H:i:s');
            $a->ends_at = $end->format('Y-m-d H:i:s');
            $a->is_walk_in = $walkIn;
            $a->updated_by = request()->user()->id;
            if (! $id) {
                $tenant->increment('appointment_sequence');
                $a->appointment_number = 'APT-'.str_pad($tenant->appointment_sequence, 6, '0', STR_PAD_LEFT);
                $a->created_by = request()->user()->id;
                $a->status = $walkIn ? ($data['status'] ?? 'waiting') : 'scheduled';
            } elseif ($changed) {
                $a->status = 'scheduled';
                $a->checked_in_at = null;
            }
            $a->save();
            $this->audit($a, ! $id ? 'created' : ($changed ? 'rescheduled' : 'updated'), ($changed ? $previous : []) + ($override ? ['schedule_override' => true, 'override_reason' => $data['override_reason']] : []));

            return $a->load(['patient', 'doctor', 'branch', 'type', 'creator']);
        }, 3);
    }
}
