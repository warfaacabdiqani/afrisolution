<?php
namespace App\Services;
use App\Models\{Prescription,Patient,Doctor,Medication,Appointment,Tenant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PrescriptionService {
    public function visible(array $c) {
        $q = Prescription::whereIn('branch_id',$c['branches']->pluck('id'));
        if (!app(ClinicAccessService::class)->can($c['permissions'],'prescriptions.view_all_doctors')) $q->whereHas('doctor',fn($d)=>$d->where('user_id',request()->user()->id));
        return $q;
    }
    public function find(array $c,int $id): Prescription { return $this->visible($c)->with(['items','patient','doctor.specialties','branch','appointment'])->findOrFail($id); }
    public function audit(Prescription $p,string $event): void {
        app(PlatformService::class)->audit(request()->user()->id,'prescription.'.$event,'tenant',$p->tenant_id,['prescription_id'=>$p->id,'branch_id'=>$p->branch_id,'patient_id'=>$p->patient_id]);
    }
    public function editable(Prescription $p): bool { return in_array($p->status,config('prescriptions.editable')) && !$p->items()->where('dispensed_quantity','>',0)->exists(); }
    public function save(array $c,array $data,?int $id=null): Prescription {
        return DB::transaction(function() use($c,$data,$id) {
            $tenant=Tenant::lockForUpdate()->findOrFail($c['clinic']->id);
            $p=$id ? $this->visible($c)->lockForUpdate()->findOrFail($id) : new Prescription;
            if($id && !$this->editable($p)) throw ValidationException::withMessages(['status'=>'This prescription is read-only after dispensing or closure.']);
            if(!$c['branches']->contains('id',(int)$data['branch_id'])) throw ValidationException::withMessages(['branch_id'=>'Select an accessible branch.']);
            if(!Patient::whereKey($data['patient_id'])->whereIn('registration_branch_id',$c['branches']->pluck('id'))->where('status','active')->exists()) throw ValidationException::withMessages(['patient_id'=>'Select an active patient in an accessible branch.']);
            $doctor=Doctor::whereKey($data['doctor_id'])->where('status','active')->whereHas('branches',fn($q)=>$q->where('branches.id',$data['branch_id']));
            if(!app(ClinicAccessService::class)->can($c['permissions'],'prescriptions.view_all_doctors')) $doctor->where('user_id',request()->user()->id);
            if(!$doctor->exists()) throw ValidationException::withMessages(['doctor_id'=>'Select an authorized active prescriber in this branch.']);
            if(!empty($data['appointment_id']) && !Appointment::whereKey($data['appointment_id'])->where('branch_id',$data['branch_id'])->where('patient_id',$data['patient_id'])->where('doctor_id',$data['doctor_id'])->exists()) throw ValidationException::withMessages(['appointment_id'=>'The appointment must match this patient, prescriber and branch.']);
            foreach($data['items'] as $i=>$item) if(!empty($item['medication_id']) && !Medication::whereKey($item['medication_id'])->where(\App\Support\PrescriptionStatus::ACTIVE,true)->exists()) throw ValidationException::withMessages(["items.$i.medication_id"=>'Select a medication from this clinic.']);
            $items=$data['items']; unset($data['items']);
            if($id && $p->status===\App\Support\PrescriptionStatus::PENDING) $data['status']=\App\Support\PrescriptionStatus::PENDING;
            $p->fill($data); $p->updated_by=request()->user()->id;
            if(!$id) { $p->expires_on=\Illuminate\Support\Carbon::parse($data['prescription_date'])->addDays((int)app(ClinicSettingsService::class)->get($tenant->id,'clinical.prescription_validity_days',30))->toDateString(); $tenant->increment('prescription_sequence'); $p->prescription_number='RX-'.str_pad($tenant->prescription_sequence,6,'0',STR_PAD_LEFT); $p->created_by=request()->user()->id; }
            $p->save(); $p->items()->delete();
            foreach($items as $item) {
                if(!empty($item['medication_id'])) { $med=Medication::findOrFail($item['medication_id']); $item['medication_name']=$med->name; }
                $p->items()->create($item);
            }
            $this->audit($p,$id?'updated':'created'); return $this->find($c,$p->id);
        },3);
    }
    public function transition(array $c,int $id,string $action,array $data): Prescription {
        return DB::transaction(function() use($c,$id,$action,$data) {
            $p=$this->visible($c)->lockForUpdate()->findOrFail($id);
            if($action==='cancel') {
                if(in_array($p->status,[\App\Support\PrescriptionStatus::CANCELLED,\App\Support\PrescriptionStatus::COMPLETED,\App\Support\PrescriptionStatus::DISPENSED,\App\Support\PrescriptionStatus::EXPIRED])) throw ValidationException::withMessages(['status'=>'This prescription is already closed.']);
                $p->status=\App\Support\PrescriptionStatus::CANCELLED; $p->cancelled_by=request()->user()->id; $p->cancelled_at=now(); $p->cancellation_reason=$data['reason'];
            } else {
                if($p->status!==\App\Support\PrescriptionStatus::ACTIVE) throw ValidationException::withMessages(['status'=>'Only an active prescription can be sent to Pharmacy.']);
                $p->status=\App\Support\PrescriptionStatus::PENDING;
            }
            $p->updated_by=request()->user()->id; $p->save(); $this->audit($p,$action==='cancel'?\App\Support\PrescriptionStatus::CANCELLED:'sent_to_pharmacy'); return $this->find($c,$id);
        },3);
    }
}
