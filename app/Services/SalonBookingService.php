<?php
namespace App\Services;

use App\Models\SalonAppointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalonBookingService
{
    public function context(Request $r, ?string $permission = null): array
    {
        $access = app(ClinicAccessService::class);
        $c = $access->context($r);
        if ($c['business_type']['slug'] !== 'beauty-salon') $access->deny('BUSINESS_MODULE_UNAVAILABLE', 'Salon booking is only available to Beauty Salon businesses.');
        return $access->authorize($r, 'appointments', $permission);
    }

    public function visible(array $c)
    {
        return SalonAppointment::whereIn('branch_id', $c['branches']->pluck('id'))
            ->when(!app(ClinicAccessService::class)->can($c['permissions'], 'appointments.view_all'), fn ($q) => $q->whereHas('stylist', fn ($q) => $q->where('user_id', request()->user()->id)));
    }

    public function find(array $c, int $id): SalonAppointment
    {
        return $this->visible($c)->with(['client', 'stylist', 'branch', 'items', 'invoice'])->findOrFail($id);
    }

    public function audit(SalonAppointment $a, string $action, array $extra = []): void
    {
        app(PlatformService::class)->audit(request()->user()->id, 'salon.appointment.'.$action, 'tenant', $a->tenant_id,
            ['appointment_id' => $a->id, 'branch_id' => $a->branch_id, 'client_id' => $a->client_id, 'stylist_id' => $a->stylist_id] + $extra);
    }

    public static function rules(): array
    {
        return ['branch_id'=>'required|integer', 'client_id'=>'required|integer', 'stylist_id'=>'required|integer',
            'service_ids'=>'required|array|min:1|max:20', 'service_ids.*'=>'required|integer|distinct', 'date'=>'required|date_format:Y-m-d',
            'start_time'=>'required|date_format:H:i', 'source'=>'required|in:phone,walk_in,online,reception', 'notes'=>'nullable|string|max:5000',
            'discount'=>'sometimes|numeric|min:0|max:99999999|decimal:0,2', 'override_conflict'=>'sometimes|boolean', 'override_reason'=>'nullable|required_if:override_conflict,true|string|max:500',
            'tenant_id'=>'prohibited', 'patient_id'=>'prohibited', 'doctor_id'=>'prohibited', 'status'=>'prohibited', 'total'=>'prohibited', 'duration_minutes'=>'prohibited'];
    }

    public function save(array $c, array $data, ?int $id = null, bool $reschedule = false): SalonAppointment
    {
        return DB::transaction(function () use ($c, $data, $id, $reschedule) {
            $tenant = app(BookingCore::class)->lock($c['clinic']->id);
            $a = $id ? $this->find($c, $id) : new SalonAppointment;
            if ($reschedule) $data = array_replace($data, ['notes'=>$a->notes, 'source'=>$a->source, 'discount'=>$a->discount]);
            if ($id && in_array($a->status, ['completed','cancelled','no_show','in_service'])) throw ValidationException::withMessages(['status'=>'This appointment can no longer be edited.']);
            if ($a->invoice) throw ValidationException::withMessages(['invoice'=>'An invoiced appointment cannot be edited.']);
            $availability = app(SalonBookingAvailability::class);
            [, $stylist, $services] = $availability->selection($c, $data);
            $oldItems = $id ? $a->items->keyBy('service_id') : collect();
            $items = $services->map(function ($service) use ($oldItems) {
                $old = $oldItems->get($service->id);
                return ['service_id'=>$service->id, 'name'=>$old?->name ?? $service->name,
                    'duration_minutes'=>$old?->duration_minutes ?? $service->duration_minutes,
                    'unit_price'=>$old?->unit_price ?? $service->price, 'amount'=>$old?->amount ?? $service->price,
                    'deposit_amount'=>$old?->deposit_amount ?? ($service->requires_deposit ? $service->deposit_amount : 0)];
            });
            $duration = $items->sum('duration_minutes');
            if ($duration > 1440) throw ValidationException::withMessages(['service_ids'=>'Services must fit within one day.']);
            $start = Carbon::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['start_time'], $tenant->timezone);
            $end = $start->copy()->addMinutes($duration);
            $changed = !$id || $a->starts_at !== $start->format('Y-m-d H:i:s') || $a->ends_at !== $end->format('Y-m-d H:i:s') || $a->stylist_id != $stylist->id || $a->branch_id != $data['branch_id'];
            $can = fn ($permission) => app(ClinicAccessService::class)->can($c['permissions'], $permission);
            if ($id && $changed) abort_unless($can('appointments.reschedule'), 403);
            if ($reschedule && ($a->client_id != $data['client_id'] || $oldItems->keys()->sort()->values()->all() !== collect($data['service_ids'])->map(fn ($i) => (int)$i)->sort()->values()->all())) throw ValidationException::withMessages(['service_ids'=>'Rescheduling retains the original client and services. Use Edit Appointment to change them.']);
            $settings = app(ClinicSettingsService::class)->section($tenant->id, 'salon');
            $walkIn = $data['source'] === 'walk_in';
            if ($walkIn && !$settings['allow_walk_in']) throw ValidationException::withMessages(['source'=>'Walk-ins are disabled by salon policy.']);
            if ($changed && $start->lt(now($tenant->timezone)) && !($walkIn && $start->isSameDay(now($tenant->timezone)))) throw ValidationException::withMessages(['start_time'=>'Choose a future time, or register a walk-in today.']);
            $override = !empty($data['override_conflict']);
            if ($override) abort_unless($settings['allow_overbooking'] && $can('appointments.override_conflict'), 403, 'Overbooking requires salon policy and override permission.');
            if ($reason = $availability->reason($c, $stylist->id, $data['branch_id'], $start, $end, $id, $override, $data['client_id'])) throw ValidationException::withMessages(['start_time'=>$reason]);
            $subtotal = $items->sum(fn ($item) => (int)round((float)$item['amount'] * 100));
            $discount = (int)round((float)($data['discount'] ?? $a->discount ?? 0) * 100);
            if ($discount !== (int)round((float)($a->discount ?? 0) * 100)) {
                abort_unless($can('appointments.discount'), 403);
                if (app(ClinicSettingsService::class)->get($tenant->id, 'billing.discount_policy') === 'disabled') throw ValidationException::withMessages(['discount'=>'Discounts are disabled by billing policy.']);
            }
            if ($discount > $subtotal) throw ValidationException::withMessages(['discount'=>'Discount cannot exceed the service subtotal.']);
            $taxRate = $id ? $a->tax_rate : $settings['default_service_tax'];
            $tax = (int)round(($subtotal - $discount) * (float)$taxRate / 100);
            if ($subtotal > 999999999999 || $subtotal-$discount+$tax > 999999999999) throw ValidationException::withMessages(['service_ids'=>'The appointment total exceeds the supported amount.']);
            $limit = $c['limits']['appointment_limit'] ?? null;
            if ($limit !== null && (!$id || substr($a->starts_at, 0, 7) !== $start->format('Y-m')) && SalonAppointment::when($id, fn ($q) => $q->where('id','!=',$id))->where('starts_at','>=',$start->copy()->startOfMonth()->format('Y-m-d H:i:s'))->where('starts_at','<',$start->copy()->startOfMonth()->addMonth()->format('Y-m-d H:i:s'))->count() >= $limit) throw ValidationException::withMessages(['plan'=>'Your monthly appointment limit has been reached.']);
            $before = $id ? $a->only(['starts_at','ends_at','stylist_id','branch_id']) : [];
            $a->fill(collect($data)->only(['branch_id','client_id','stylist_id','source','notes'])->all());
            $a->fill(['starts_at'=>$start->format('Y-m-d H:i:s'), 'ends_at'=>$end->format('Y-m-d H:i:s'), 'duration_minutes'=>$duration,
                'subtotal'=>$subtotal/100, 'discount'=>$discount/100, 'tax'=>$tax/100, 'tax_rate'=>$taxRate, 'total'=>($subtotal-$discount+$tax)/100,
                'deposit_required'=>min($items->sum('deposit_amount'), ($subtotal-$discount+$tax)/100), 'updated_by'=>request()->user()->id]);
            if (!$id) {
                $a->appointment_number = app(BookingCore::class)->number($tenant);
                $a->currency = app(ClinicSettingsService::class)->get($tenant->id, 'general.currency', 'USD');
                $a->created_by = request()->user()->id;
                $a->status = $walkIn ? 'waiting' : $settings['default_status'];
            } elseif ($changed) { $a->status = 'scheduled'; $a->checked_in_at = null; }
            $a->save();
            $a->items()->delete();
            $a->items()->createMany($items->all());
            $this->audit($a, !$id ? 'created' : ($changed ? 'rescheduled' : 'updated'), ['previous'=>$before] + ($override ? ['override_reason'=>$data['override_reason']] : []));
            return $this->find($c, $a->id);
        }, 5);
    }

    public function transition(array $c, int $id, string $action, array $data): SalonAppointment
    {
        $definition = config('salon_booking.actions.'.$action);
        abort_unless($definition, 404);
        abort_unless(app(ClinicAccessService::class)->can($c['permissions'], $definition['permission']), 403);
        return DB::transaction(function () use ($c, $id, $action, $data, $definition) {
            app(BookingCore::class)->lock($c['clinic']->id);
            $a = $this->find($c, $id);
            if (!in_array($a->status, $definition['from'])) throw ValidationException::withMessages(['status'=>'This status transition is not allowed.']);
            $now = now($c['clinic']->timezone);
            if (in_array($action, ['check-in','start-service','complete']) && substr($a->starts_at,0,10) > $now->toDateString()) throw ValidationException::withMessages(['status'=>'This action is not available before the appointment date.']);
            if ($action === 'no-show' && $a->starts_at > $now->format('Y-m-d H:i:s')) throw ValidationException::withMessages(['status'=>'A future appointment cannot be marked no show.']);
            $a->status = $definition['to'];
            if (isset($definition['timestamp'])) $a->{$definition['timestamp']} = now();
            if ($action === 'cancel') { $a->cancelled_at = now(); $a->cancelled_by = request()->user()->id; $a->cancellation_reason = $data['reason']; }
            $a->updated_by = request()->user()->id; $a->save(); $this->audit($a, $action);
            return $this->find($c, $id);
        }, 5);
    }
}
