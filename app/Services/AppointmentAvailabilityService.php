<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppointmentAvailabilityService
{
    public function doctor(array $context, int $doctor, int $branch): Doctor
    {
        abort_unless($context['branches']->contains('id', $branch), 403, 'Select an authorized branch.');
        $model = Doctor::where('status', 'active')->whereHas('branches', fn ($q) => $q->where('branches.id', $branch))->find($doctor);
        if (! $model) {
            throw ValidationException::withMessages(['doctor_id' => 'Select an active clinician assigned to this branch.']);
        }
        if (! app(ClinicAccessService::class)->can($context['permissions'], 'appointments.view_all')) {
            abort_unless($model->user_id === request()->user()->id, 403);
        }

        return $model;
    }

    public function reason(Doctor $doctor, int $branch, Carbon $start, Carbon $end, ?int $patient = null, ?int $ignore = null, bool $override = false, ?Collection $bookings = null): ?string
    {
        if ($doctor->status !== 'active' || in_array($doctor->availability_status, ['unavailable', 'on_leave'])) {
            return 'This clinician is unavailable.';
        }
        if ($start->toDateString() !== $end->toDateString()) {
            return 'The appointment must end on the same day.';
        }
        $onLeave = $doctor->relationLoaded('leaves') ? $doctor->leaves->isNotEmpty() : $doctor->leaves()->where('status', '!=', 'cancelled')->where('start_date', '<=', $start->toDateString())->where('end_date', '>=', $start->toDateString())->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch))->exists();
        if ($onLeave) {
            return 'This clinician is on leave on this date.';
        }
        if (! $override) {
            $schedule = $doctor->relationLoaded('schedules') ? $doctor->schedules->first() : $doctor->schedules()->where('branch_id', $branch)->where('day_of_week', $start->isoWeekday())->where('is_available', true)->first();
            if (! $schedule || $start->format('H:i') < substr($schedule->start_time, 0, 5) || $end->format('H:i') > substr($schedule->end_time, 0, 5)) {
                return 'Select a time inside the clinician’s working hours.';
            }
            if ($schedule->break_start && $start->format('H:i') < substr($schedule->break_end, 0, 5) && $end->format('H:i') > substr($schedule->break_start, 0, 5)) {
                return 'This time overlaps the clinician’s break.';
            }
        }
        if ($bookings !== null) {
            $overlapping = $bookings->filter(fn ($a) => $a->starts_at < $end->format('Y-m-d H:i:s') && $a->ends_at > $start->format('Y-m-d H:i:s'));
            if ($overlapping->contains('doctor_id', $doctor->id)) {
                return 'This clinician already has an overlapping appointment.';
            }
            if ($patient && $overlapping->contains('patient_id', $patient)) {
                return 'This patient already has an overlapping appointment.';
            }

            return null;
        }
        $conflicts = app(BookingCore::class)->conflicts(Appointment::whereNotIn('status', config('appointments.non_blocking')), $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $ignore);
        if ((clone $conflicts)->where('doctor_id', $doctor->id)->exists()) {
            return 'This clinician already has an overlapping appointment.';
        }
        if ($patient && (clone $conflicts)->where('patient_id', $patient)->exists()) {
            return 'This patient already has an overlapping appointment.';
        }

        return null;
    }

    public function slots(array $context, array $data): array
    {
        $doctor = $this->doctor($context, $data['doctor_id'], $data['branch_id']);
        if (! empty($data['appointment_id'])) {
            app(AppointmentService::class)->find($context, $data['appointment_id']);
        }
        if (! empty($data['patient_id'])) {
            Patient::findOrFail($data['patient_id']);
        }
        $date = Carbon::parse($data['date'], $context['clinic']->timezone);
        $doctor->load(['schedules' => fn ($q) => $q->where('branch_id', $data['branch_id'])->where('day_of_week', $date->isoWeekday())->where('is_available', true), 'leaves' => fn ($q) => $q->where('status', '!=', 'cancelled')->where('start_date', '<=', $data['date'])->where('end_date', '>=', $data['date'])->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $data['branch_id']))]);
        $schedule = $doctor->schedules->first();
        $result = ['slots' => [], 'reason_code' => null, 'message' => null, 'working_hours' => $schedule ? substr($schedule->start_time, 0, 5).' – '.substr($schedule->end_time, 0, 5) : null];
        if (in_array($doctor->availability_status, ['unavailable', 'on_leave'])) {
            return array_replace($result, ['reason_code' => 'clinician_unavailable', 'message' => $doctor->full_name.' is marked '.str_replace('_', ' ', $doctor->availability_status).'. Choose another clinician or update their availability.']);
        }
        if ($doctor->leaves->isNotEmpty()) {
            return array_replace($result, ['reason_code' => 'on_leave', 'message' => $doctor->full_name.' is on leave on this date. Choose another date or clinician.']);
        }
        if (! $schedule) {
            $configured = $doctor->schedules()->where('branch_id', $data['branch_id'])->where('is_available', true)->exists();
            return array_replace($result, ['reason_code' => $configured ? 'non_working_day' : 'schedule_missing', 'message' => $configured ? $doctor->full_name.' does not have working hours on '.$date->format('l').'. Choose a working day or update the schedule.' : 'No working hours have been set for '.$doctor->full_name.' at this branch. Set working hours to make appointment times available.']);
        }
        if ($date->toDateString() < now($context['clinic']->timezone)->toDateString()) {
            return array_replace($result, ['reason_code' => 'past_date', 'message' => 'This date is in the past. Choose today for a walk-in, or a future date for a scheduled appointment.']);
        }
        $bookings = Appointment::whereNotIn('status', config('appointments.non_blocking'))->when(! empty($data['appointment_id']), fn ($q) => $q->where('id', '!=', $data['appointment_id']))->where('starts_at', '<', $date->copy()->addDay()->format('Y-m-d H:i:s'))->where('ends_at', '>', $date->format('Y-m-d H:i:s'))->where(fn ($q) => $q->where('doctor_id', $doctor->id)->when(! empty($data['patient_id']), fn ($q) => $q->orWhere('patient_id', $data['patient_id'])))->get(['doctor_id', 'patient_id', 'starts_at', 'ends_at']);
        $slots = [];
        $interval=(int)app(ClinicSettingsService::class)->get($context['clinic']->id,'appointments.slot_interval',15);
        for ($start = $date->copy()->setTimeFromTimeString($schedule->start_time); $start->copy()->addMinutes((int) $data['duration'])->format('H:i') <= substr($schedule->end_time, 0, 5) && $start->toDateString() === $data['date']; $start->addMinutes($interval)) {
            if ($start->lt(now($context['clinic']->timezone)) && ! (! empty($data['walk_in']) && $date->isSameDay(now($context['clinic']->timezone)))) {
                continue;
            }
            if (! $this->reason($doctor,$data['branch_id'],$start,$start->copy()->addMinutes((int) $data['duration']),$data['patient_id'] ?? null,$data['appointment_id'] ?? null,false,$bookings)) {
                $slots[] = $start->format('H:i');
            }
        }

        $result['slots'] = $slots;
        if (! $slots) {
            $current = now($context['clinic']->timezone);
            $finished = $date->isSameDay($current) && empty($data['walk_in']) && $current->copy()->addMinutes((int) $data['duration'])->gt($date->copy()->setTimeFromTimeString($schedule->end_time));
            $result['reason_code'] = $finished ? 'day_finished' : 'no_matching_slots';
            $result['message'] = $finished ? 'There is not enough time left in today’s working hours for this duration. Choose another date, or select Walk-in to record an arrival earlier today.' : 'No free interval fits this duration. Existing appointments and breaks may use the available time. Choose another date, clinician, or shorter duration.';
        }
        return $result;
    }
}
