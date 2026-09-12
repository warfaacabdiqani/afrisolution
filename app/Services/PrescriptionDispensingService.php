<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PrescriptionDispensingService {
    // Absolute totals make retries safe. Stock movements belong to Pharmacy.
    public function dispense(array $c,int $id,array $items) {
        return DB::transaction(function() use($c,$id,$items) {
            $service=app(PrescriptionService::class);
            $p=$service->visible($c)->lockForUpdate()->findOrFail($id);
            if(!in_array($p->status,[\App\Support\PrescriptionStatus::PENDING,\App\Support\PrescriptionStatus::PARTIALLY_DISPENSED,\App\Support\PrescriptionStatus::DISPENSED])) throw ValidationException::withMessages(['status'=>'This prescription is not in the dispensing queue.']);
            foreach($items as $input) {
                $item=$p->items()->find($input['id']);
                if(!$item || !$item->quantity || $input['dispensed_quantity']<$item->dispensed_quantity || $input['dispensed_quantity']>$item->quantity) throw ValidationException::withMessages(['items'=>'Dispensed totals must increase and cannot exceed the prescribed quantity.']);
                $item->dispensed_quantity=$input['dispensed_quantity'];
                $item->status=$item->dispensed_quantity==$item->quantity?\App\Support\PrescriptionStatus::DISPENSED:($item->dispensed_quantity>0?\App\Support\PrescriptionStatus::PARTIALLY_DISPENSED:\App\Support\PrescriptionStatus::PENDING); $item->save();
            }
            $all=$p->items()->get(); $p->status=$all->every(fn($i)=>$i->status===\App\Support\PrescriptionStatus::DISPENSED)?\App\Support\PrescriptionStatus::DISPENSED:($all->contains(fn($i)=>$i->dispensed_quantity>0)?\App\Support\PrescriptionStatus::PARTIALLY_DISPENSED:\App\Support\PrescriptionStatus::PENDING);
            $p->updated_by=request()->user()->id; $p->save(); $service->audit($p,\App\Support\PrescriptionStatus::DISPENSED); return $service->find($c,$id);
        },3);
    }
}
