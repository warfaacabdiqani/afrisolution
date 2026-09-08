<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Services\AppointmentService;
use App\Services\ClinicAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AppointmentCalendarController extends Controller
{
    public function query(Request $request, array $c)
    {
        $data = $request->validate(['start' => ['nullable', 'date_format:Y-m-d'], 'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'], 'doctor_id' => ['nullable', 'integer'], 'patient_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(array_keys(config('appointments.statuses')))], 'appointment_type_id' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']]);
        $q = app(AppointmentService::class)->visible($c);
        if (! empty($data['branch_id'])) {
            abort_unless($c['branches']->contains('id', (int) $data['branch_id']), 403);
            $q->where('branch_id', $data['branch_id']);
        }
        foreach (['doctor_id', 'patient_id', 'status', 'appointment_type_id'] as $key) {
            if (! empty($data[$key])) {
                $q->where($key, $data[$key]);
            }
        }
        if (! empty($data['start'])) {
            $q->where('starts_at', '>=', $data['start'].' 00:00:00');
        }
        if (! empty($data['end'])) {
            $q->where('starts_at', '<', Carbon::parse($data['end'])->addDay()->toDateString().' 00:00:00');
        }
        foreach (preg_split('/\s+/', trim($data['search'] ?? '')) as $word) {
            if ($word !== '') {
                $q->where(fn ($q) => $q->where('appointment_number', 'like', '%'.$word.'%')->orWhereHas('patient', fn ($p) => $p->where(function ($p) use ($word) {
                    foreach (['first_name', 'middle_name', 'last_name', 'patient_number', 'phone'] as $f) {
                        $p->orWhere($f, 'like', '%'.$word.'%');
                    }
                }))->orWhereHas('doctor', fn ($d) => $d->where(function ($d) use ($word) {
                    foreach (['first_name', 'middle_name', 'last_name'] as $f) {
                        $d->orWhere($f, 'like', '%'.$word.'%');
                    }
                })));
            }
        }

        return $q;
    }

    public function index(Request $request, ClinicAccessService $access)
    {
        $c = $access->authorize($request, 'appointments');

        return AppointmentResource::collection($this->query($request, $c)->with(['patient', 'doctor', 'branch', 'type'])->orderBy('starts_at')->orderBy('id')->paginate(25));
    }

    public function calendar(Request $request, ClinicAccessService $access)
    {
        $c = $access->authorize($request, 'appointments');
        $data = $request->validate(['start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start']]);
        abort_if(Carbon::parse($data['start'])->diffInDays(Carbon::parse($data['end'])) > 42, 422, 'Select a calendar range of at most 42 days.');
        $q = $this->query($request, $c);
        $counts = (clone $q)->selectRaw('DATE(starts_at) as date, count(*) as total')->groupByRaw('DATE(starts_at)')->get()->map(fn ($r) => ['date' => $r->date, 'total' => $r->total]);

        return AppointmentResource::collection($q->with(['patient', 'doctor', 'branch', 'type'])->orderBy('starts_at')->orderBy('id')->paginate(200))->additional(['counts' => $counts]);
    }

    public function today(Request $request, ClinicAccessService $access)
    {
        $c = $access->authorize($request, 'appointments');
        $request->merge(['start' => $c['today'], 'end' => $c['today']]);
        $q = $this->query($request, $c);
        $summaryRequest = clone $request;
        $summaryRequest->merge(['status' => null]);
        $summary = $this->query($summaryRequest, $c)->reorder()->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total', 'status');

        return AppointmentResource::collection($q->with(['patient', 'doctor', 'branch', 'type'])->orderBy('starts_at')->orderBy('id')->paginate(25))->additional(['summary' => $summary, 'today' => $c['today']]);
    }
}
