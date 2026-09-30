<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class SalonAppointmentResource extends JsonResource {
    public function toArray(Request $request): array {
        return $this->resource->only(['id','appointment_number','branch_id','client_id','stylist_id','starts_at','ends_at','status','source','notes','currency','duration_minutes','subtotal','discount','tax','tax_rate','total','deposit_required','checked_in_at','service_started_at','completed_at','cancelled_at','cancellation_reason']) + [
            'client'=>['id'=>$this->client->id, 'full_name'=>$this->client->full_name, 'client_number'=>$this->client->client_number, 'phone'=>$this->client->phone],
            'stylist'=>['id'=>$this->stylist->id, 'name'=>$this->stylist->display_name], 'location'=>$this->branch->name,
            'items'=>$this->items->map(fn ($i) => $i->only(['service_id','name','duration_minutes','unit_price','amount','deposit_amount'])),
            'invoice_id'=>$this->invoice?->id,
            'deposit'=>app(\App\Services\Billing\BillingDepositService::class)->summary($this->depositInvoice, $this->deposit_required),
        ];
    }
}
