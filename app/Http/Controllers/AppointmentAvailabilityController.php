<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Services\AppointmentAvailabilityService;
use App\Services\ClinicAccessService;
use Illuminate\Http\Request;

class AppointmentAvailabilityController extends Controller
{
    public function slots(Request $r, ClinicAccessService $access, AppointmentAvailabilityService $service, int $doctor)
    {
        $c = $access->authorize($r, 'appointments');
        $d = $r->validate(['branch_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'duration' => ['required', 'integer', 'between:5,480'], 'patient_id' => ['nullable', 'integer'], 'appointment_id' => ['nullable', 'integer'], 'walk_in' => ['nullable', 'boolean']]);

        $result = $service->slots($c, $d + ['doctor_id' => $doctor]);
        return response()->json(['data' => $result['slots'], 'meta' => collect($result)->except('slots')->all()]);
    }

    public function hours(Request $r, ClinicAccessService $access)
    {
        $c = $access->authorize($r, 'appointments');
        $d = $r->validate(['branch_id' => ['required', 'integer'], 'doctor_id' => ['nullable', 'integer'], 'start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start']]);
        abort_unless($c['branches']->contains('id', (int) $d['branch_id']), 403);
        $doctors = Doctor::where('status', 'active')->whereNotIn('availability_status', ['unavailable', 'on_leave'])->whereHas('branches', fn ($q) => $q->where('branches.id', $d['branch_id']))->when(! empty($d['doctor_id']), fn ($q) => $q->whereKey($d['doctor_id']));
        if (! $access->can($c['permissions'], 'appointments.view_all')) {
            $doctors->where('user_id', $r->user()->id);
        }$ids = $doctors->pluck('id');

        return response()->json(['data' => ['schedules' => DoctorSchedule::whereIn('doctor_id', $ids)->where('branch_id', $d['branch_id'])->get(['doctor_id', 'day_of_week', 'start_time', 'end_time', 'break_start', 'break_end', 'is_available']), 'leaves' => DoctorLeave::whereIn('doctor_id', $ids)->where('status', '!=', 'cancelled')->where('start_date', '<=', $d['end'])->where('end_date', '>=', $d['start'])->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $d['branch_id']))->get(['doctor_id', 'start_date', 'end_date'])]]);
    }
}
