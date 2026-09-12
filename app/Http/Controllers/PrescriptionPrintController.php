<?php
namespace App\Http\Controllers;
use App\Services\{ClinicAccessService,PrescriptionService};
use Illuminate\Http\Request;
class PrescriptionPrintController extends Controller {
    public function __invoke(Request $r,ClinicAccessService $a,PrescriptionService $s,int $prescription) {
        $c=$a->authorize($r,'prescriptions','prescriptions.print'); $p=$s->find($c,$prescription); $s->audit($p,'printed');
        return response()->view('prescriptions.print',['p'=>$p,'clinic'=>$c['clinic'],'branchName'=>$p->branch->name]+app(\App\Services\ClinicDocumentService::class)->data($c['clinic']->id,'prescription'))->header('Cache-Control','private, no-store');
    }
}
