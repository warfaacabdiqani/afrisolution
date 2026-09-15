<?php
namespace App\Services;
use App\Models\{BillingInvoice, BillingPayment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shared invoice/payment ledger; industry adapters supply immutable line items. */
class BillingService
{
    public function visible(array $c) { return BillingInvoice::whereIn('branch_id',$c['branches']->pluck('id')); }
    public function fromSalon(array $c, int $appointment): BillingInvoice
    {
        return DB::transaction(function() use($c,$appointment) {
            $tenant=app(BookingCore::class)->lock($c['clinic']->id);
            $a=app(SalonBookingService::class)->find($c,$appointment);
            if ($a->status !== 'completed') throw ValidationException::withMessages(['status'=>'Complete the appointment before creating its invoice.']);
            if ($a->invoice) return $a->invoice->load(['items','payments']);
            $limit=$c['limits']['invoice_limit']??null;
            if ($limit!==null && BillingInvoice::where('created_at','>=',now()->startOfMonth())->count()>=$limit) throw ValidationException::withMessages(['plan'=>'Your monthly invoice limit has been reached.']);
            $settings=app(ClinicSettingsService::class)->section($c['clinic']->id,'billing');
            do {
                $tenant->increment('billing_invoice_sequence');
                $number=$settings['invoice_prefix'].str_pad((string)$tenant->billing_invoice_sequence,(int)$settings['number_length'],'0',STR_PAD_LEFT);
            } while (BillingInvoice::where('number',$number)->exists());
            $invoice=BillingInvoice::create(['branch_id'=>$a->branch_id,'source_type'=>'salon_appointment','source_id'=>$a->id,
                'number'=>$number,
                'customer_name'=>$a->client->full_name,'currency'=>$a->currency,'subtotal'=>$a->subtotal,'discount'=>$a->discount,'tax'=>$a->tax,'total'=>$a->total,
                'status'=>(float)$a->total===0.0?'paid':'unpaid','created_by'=>request()->user()->id]);
            $invoice->items()->createMany($a->items->map(fn($item)=>['description'=>$item->name,'quantity'=>1,'unit_price'=>$item->unit_price,'amount'=>$item->amount])->all());
            app(PlatformService::class)->audit(request()->user()->id,'billing.invoice.created','tenant',$c['clinic']->id,['invoice_id'=>$invoice->id,'appointment_id'=>$a->id]);
            return $invoice->load(['items','payments']);
        },5);
    }
    public function payment(array $c, int $id, array $data): BillingInvoice
    {
        return DB::transaction(function() use($c,$id,$data) {
            app(BookingCore::class)->lock($c['clinic']->id);
            $invoice=$this->visible($c)->findOrFail($id);
            $duplicate=BillingPayment::where('idempotency_key',$data['idempotency_key'])->first();
            if ($duplicate) {
                abort_unless($duplicate->invoice_id===$invoice->id && (int)round($duplicate->amount*100)===(int)round($data['amount']*100) && $duplicate->method===$data['method'],409,'This payment request key was already used.');
                return $invoice->load(['items','payments']);
            }
            $settings=app(ClinicSettingsService::class)->section($c['clinic']->id,'billing');
            if (!in_array($data['method'],$settings['payment_methods'])) throw ValidationException::withMessages(['method'=>'This payment method is disabled.']);
            $balance=(int)round($invoice->total*100)-(int)round($invoice->paid*100); $amount=(int)round($data['amount']*100);
            if ($amount>$balance || $amount<=0) throw ValidationException::withMessages(['amount'=>'Payment must be positive and cannot exceed the outstanding balance.']);
            if (!$settings['partial_payments'] && $amount!==$balance) throw ValidationException::withMessages(['amount'=>'Partial payments are disabled.']);
            $invoice->payments()->create($data+['recorded_by'=>request()->user()->id,'paid_at'=>now()]);
            $paid=(int)round($invoice->paid*100)+$amount;
            $invoice->update(['paid'=>$paid/100,'status'=>$paid===(int)round($invoice->total*100)?'paid':'partial']);
            app(PlatformService::class)->audit(request()->user()->id,'billing.payment.recorded','tenant',$c['clinic']->id,['invoice_id'=>$id,'amount'=>$amount/100,'method'=>$data['method']]);
            return $invoice->load(['items','payments']);
        },5);
    }
}
