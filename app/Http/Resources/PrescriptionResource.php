<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
class PrescriptionResource extends JsonResource {
    public function toArray($request): array {
        $p=$this->resource;
        return $p->only(['id','branch_id','patient_id','doctor_id','appointment_id','consultation_id','prescription_number','status','diagnosis','notes','internal_notes','cancellation_reason','cancelled_at','created_at']) + [
            'prescription_date'=>$p->prescription_date->toDateString(),
            'editable'=>in_array($p->status,config('prescriptions.editable')) && !$p->items->contains(fn($i)=>$i->dispensed_quantity>0),
            'patient'=>['id'=>$p->patient->id,'full_name'=>$p->patient->full_name,'patient_number'=>$p->patient->patient_number,'age'=>$p->patient->age,'gender'=>$p->patient->gender,'phone'=>$p->patient->phone],
            'doctor'=>['id'=>$p->doctor->id,'full_name'=>$p->doctor->full_name,'specialty'=>$p->doctor->specialties->pluck('name')->join(', '),'license_number'=>$p->doctor->license_number],
            'branch'=>['id'=>$p->branch->id,'name'=>$p->branch->name],
            'appointment'=>$p->appointment?->only(['id','appointment_number','starts_at']),
            'items'=>$p->items->map->only(['id','medication_id','medication_name','strength','dosage_form','dose','route','frequency','custom_frequency','duration','quantity','instructions','status','dispensed_quantity']),
        ];
    }
}
