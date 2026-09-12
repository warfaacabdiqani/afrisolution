<?php
namespace App\Http\Controllers;
use App\Http\Requests\PrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Services\{ClinicAccessService,PrescriptionService,PrescriptionDispensingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PrescriptionController extends Controller {
    public function store(PrescriptionRequest $r,ClinicAccessService $a,PrescriptionService $s) {
        return (new PrescriptionResource($s->save($a->authorize($r,'prescriptions','prescriptions.create'),$r->validated())))->response()->setStatusCode(201);
    }
    public function update(PrescriptionRequest $r,ClinicAccessService $a,PrescriptionService $s,int $prescription) {
        return new PrescriptionResource($s->save($a->authorize($r,'prescriptions','prescriptions.update'),$r->validated(),$prescription));
    }
    public function show(Request $r,ClinicAccessService $a,PrescriptionService $s,int $prescription) {
        return new PrescriptionResource($s->find($a->authorize($r,'prescriptions'),$prescription));
    }
    public function cancel(Request $r,ClinicAccessService $a,PrescriptionService $s,int $prescription) {
        $c=$a->authorize($r,'prescriptions','prescriptions.cancel');
        return new PrescriptionResource($s->transition($c,$prescription,'cancel',$r->validate(['reason'=>'required|string|max:2000'])));
    }
    public function send(Request $r,ClinicAccessService $a,PrescriptionService $s,int $prescription) {
        $c=$a->authorize($r,'prescriptions','prescriptions.update'); $a->authorize($r,'pharmacy');
        return new PrescriptionResource($s->transition($c,$prescription,'send',[]));
    }
    public function dispense(Request $r,ClinicAccessService $a,PrescriptionDispensingService $s,int $prescription) {
        $c=$a->authorize($r,'prescriptions','prescriptions.dispense'); $a->authorize($r,'pharmacy');
        $data=$r->validate(['items'=>'required|array|min:1|max:50','items.*.id'=>'required|integer|distinct','items.*.dispensed_quantity'=>'required|numeric|min:0|max:9999999999']);
        return new PrescriptionResource($s->dispense($c,$prescription,$data['items']));
    }
    public function activity(Request $r,ClinicAccessService $a,PrescriptionService $s,int $prescription) {
        $p=$s->find($a->authorize($r,'prescriptions'),$prescription);
        return response()->json(['data'=>DB::table('platform_audit_logs')->where('tenant_id',$p->tenant_id)->where('metadata->prescription_id',$p->id)->latest('id')->paginate(20,['id','action','actor_name','created_at'])]);
    }
}
