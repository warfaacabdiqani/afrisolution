<?php
namespace App\Services;

use App\Models\{SalonAppointment, SalonClient, SalonService, SalonStaffProfile};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalonBookingAvailability
{
    public function table(string $table, array $c)
    {
        return DB::table($table)->where('tenant_id', $c['clinic']->id);
    }

    public function branch(array $c, int $id): void
    {
        abort_unless($c['branches']->contains('id', $id), 403, 'Select an authorized location.');
    }

    public function selection(array $c, array $data): array
    {
        $this->branch($c, $data['branch_id']);
        $client = SalonClient::where('status', 'active')->where('branch_id', $data['branch_id'])->find($data['client_id']);
        if (!$client) throw ValidationException::withMessages(['client_id' => 'Select an active client at this location.']);
        $stylist = SalonStaffProfile::where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $data['branch_id']))->find($data['stylist_id']);
        if (!$stylist) throw ValidationException::withMessages(['stylist_id' => 'Select an active stylist assigned to this location.']);
        app(SalonAccessService::class)->memberBranches($stylist->user_id, [$data['branch_id']], $c);
        if (!app(ClinicAccessService::class)->can($c['permissions'], 'appointments.view_all')) abort_unless($stylist->user_id === request()->user()->id, 403);
        $services = SalonService::whereIn('id', $data['service_ids'])->where('status', 'active')->whereHas('category', fn ($q) => $q->where('status', 'active'))
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $data['branch_id']))
            ->whereHas('stylists', fn ($q) => $q->where('salon_staff_profiles.id', $stylist->id))->get();
        if ($services->count() !== count($data['service_ids'])) throw ValidationException::withMessages(['service_ids' => 'Every service must be active and assigned to this location and stylist.']);
        return [$client, $stylist, $services];
    }

    public function reason(array $c, int $stylist, int $branch, Carbon $start, Carbon $end, ?int $ignore = null, bool $override = false, ?int $client = null): ?string
    {
        $buffer = (int) app(ClinicSettingsService::class)->get($c['clinic']->id, 'salon.buffer_minutes', 0);
        $occupiedEnd = $end->copy()->addMinutes($buffer);
        if ($start->toDateString() !== $occupiedEnd->toDateString()) return 'Services and buffer must finish on the same day.';
        foreach (['salon_location_hours' => 'location', 'salon_staff_schedules' => 'stylist'] as $table => $label) {
            $schedule = $this->table($table, $c)->where('branch_id', $branch)->where('day_of_week', $start->isoWeekday())
                ->when($label === 'stylist', fn ($q) => $q->where('stylist_id', $stylist))->first();
            if (!$schedule || !$schedule->is_available) return 'No '.$label.' working hours are configured for this day.';
            if ($start->format('H:i') < substr($schedule->start_time, 0, 5) || $occupiedEnd->format('H:i') > substr($schedule->end_time, 0, 5)) return 'Select a time inside '.$label.' working hours.';
            if ($schedule->break_start && BookingCore::overlaps($start->format('H:i'), $occupiedEnd->format('H:i'), substr($schedule->break_start, 0, 5), substr($schedule->break_end, 0, 5))) return 'This time overlaps the '.$label.' break.';
        }
        $core = app(BookingCore::class);
        if ($core->conflicts($this->table('salon_staff_time_off', $c)->where('stylist_id', $stylist)->where('status', 'active'), $start->format('Y-m-d H:i:s'), $occupiedEnd->format('Y-m-d H:i:s'))->exists()) return 'This stylist has time off during the requested time.';
        $conflicts = $core->conflicts(SalonAppointment::whereNotIn('status', config('salon_booking.non_blocking')), $start->copy()->subMinutes($buffer)->format('Y-m-d H:i:s'), $occupiedEnd->format('Y-m-d H:i:s'), $ignore);
        if (!$override && (clone $conflicts)->where('stylist_id', $stylist)->exists()) return 'This stylist already has an overlapping appointment.';
        if ($client && (clone $conflicts)->where('client_id', $client)->exists()) return 'This client already has an overlapping appointment.';
        return null;
    }

    public function slots(array $c, array $data): array
    {
        [, $stylist, $services] = $this->selection($c, $data);
        $old = !empty($data['appointment_id']) ? app(SalonBookingService::class)->find($c, $data['appointment_id']) : null;
        $duration = $services->sum(fn ($s) => $old?->items->firstWhere('service_id', $s->id)?->duration_minutes ?? $s->duration_minutes);
        $day = Carbon::parse($data['date'], $c['clinic']->timezone)->startOfDay();
        $schedule = $this->table('salon_staff_schedules', $c)->where('stylist_id', $stylist->id)->where('branch_id', $data['branch_id'])->where('day_of_week', $day->isoWeekday())->where('is_available', true)->first();
        $result = ['slots' => [], 'duration_minutes' => $duration, 'message' => 'No available times. Check location hours, stylist schedule, time off, or choose another date.'];
        if (!$schedule) return $result;
        $interval = (int) app(ClinicSettingsService::class)->get($c['clinic']->id, 'salon.slot_interval', 15);
        $now = now($c['clinic']->timezone);
        for ($start = $day->copy()->setTimeFromTimeString($schedule->start_time); $start->toDateString() === $day->toDateString() && $start->format('H:i') < substr($schedule->end_time, 0, 5); $start->addMinutes($interval)) {
            if ($start->lt($now) && !($old && $old->starts_at === $start->format('Y-m-d H:i:s'))) continue;
            if (!$this->reason($c, $stylist->id, $data['branch_id'], $start, $start->copy()->addMinutes($duration), $old?->id, false, $data['client_id'])) $result['slots'][] = $start->format('H:i');
        }
        if ($result['slots']) $result['message'] = null;
        return $result;
    }
}
