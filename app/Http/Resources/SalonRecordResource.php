<?php
namespace App\Http\Resources;
use App\Models\{SalonClient,SalonStaffProfile,SalonService};
use Illuminate\Http\Resources\Json\JsonResource;
class SalonRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        $record = $this->resource;
        $context = $request->attributes->get('salon_context');
        $data = ['id'=>$this->id,'status'=>$this->status,'created_at'=>$this->created_at?->toISOString(),'updated_at'=>$this->updated_at?->toISOString()];
        $historyAllowed = ($context['modules']->firstWhere('key','appointments')['allowed'] ?? false);
        $history = $record instanceof SalonClient && $historyAllowed ? app(\App\Services\SalonBookingService::class)->visible($context)->where('client_id',$record->id)->where('branch_id',$context['branch']->id) : null;
        if ($record instanceof SalonClient) return $data + $record->only(['client_number','first_name','middle_name','last_name','gender','phone','email','address','notes','preferred_stylist_id','branch_id']) + [
            'full_name'=>$record->full_name,'date_of_birth'=>$record->date_of_birth?->toDateString(),'preferred_stylist'=>$record->preferredStylist?->only(['id','display_name','status']),
            'last_visit'=>$history ? (clone $history)->where('status','completed')->max('starts_at') : null,
            'next_appointment'=>$history ? (clone $history)->whereNotIn('status',['completed','cancelled','no_show'])->where('starts_at','>=',now($context['clinic']->timezone)->format('Y-m-d H:i:s'))->min('starts_at') : null,
            'history_available'=>$historyAllowed,'editable'=>$record->status!=='archived',
        ];
        if ($record instanceof SalonStaffProfile || $record instanceof SalonService) {
            $data['editable']=$record->status!=='archived' && $record->branches->pluck('id')->diff($context['branches']->pluck('id'))->isEmpty();
            $data['branches']=$record->branches->whereIn('id',$context['branches']->pluck('id'))->map(fn($b)=>$b->only(['id','name']))->values()->all();
            $data['branch_ids']=array_column($data['branches'],'id');
        }
        if ($record instanceof SalonStaffProfile) return $data + $record->only(['user_id','staff_number','display_name','title','bio','commission_type','commission_value']) + [
            'services'=>$record->services->filter(fn($service)=>$service->branches->contains('id',$context['branch']->id))->map(fn($s)=>$s->only(['id','name','status']))->values()->all(),
        ];
        if ($record instanceof SalonService) {
            $staff=$record->stylists->filter(fn($stylist)=>$stylist->branches->contains('id',$context['branch']->id));
            return $data + $record->only(['name','code','description','duration_minutes','price','requires_deposit','deposit_amount','service_category_id']) + [
                'currency'=>app(\App\Services\ClinicSettingsService::class)->get($context['clinic']->id,'general.currency','USD'),
                'category'=>$record->category?->only(['id','name','status']),
                'stylists'=>$staff->map(fn($s)=>$s->only(['id','display_name','status']))->values()->all(),
                // Managers need the full assignment list to preserve other authorized locations on edit.
                'stylist_ids'=>$data['editable']?$record->stylists->pluck('id')->all():$staff->pluck('id')->values()->all(),
            ];
        }
        return $data + $record->only(['name','description','sort_order']);
    }
}
