<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
class DoctorResource extends JsonResource {
    public function toArray($request): array {
        $allowed = $request->attributes->get('doctor_branch_ids', []);
        return ['id'=>$this->id,'full_name'=>$this->full_name,'doctor_number'=>$this->doctor_number,'phone'=>$this->phone,'email'=>$this->email,'status'=>$this->status,
            'availability_status'=>$this->availability_status,'availability'=>$this->effective_availability ?? $this->availability_status,
            'specialties'=>$this->whenLoaded('specialties',fn()=> $this->specialties->map->only(['id','name'])),
            'branches'=>$this->whenLoaded('branches',fn()=> $this->branches->whereIn('id',$allowed)->map->only(['id','name'])->values()),
            'can_manage_branches'=>$this->relationLoaded('branches') && $this->branches->pluck('id')->diff($allowed)->isEmpty(),
        ];
    }
}
