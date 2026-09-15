<?php
namespace App\Http\Controllers;
use App\Services\{BillingService, ClinicAccessService, SalonBookingService, ClinicSettingsService};
use Illuminate\Http\Request;
class BillingController extends Controller {
    public function index(Request $r, BillingService $billing) {
        $c=app(ClinicAccessService::class)->authorize($r,'billing');
        return response()->json(['data'=>$billing->visible($c)->latest('id')->paginate(50), 'methods'=>app(ClinicSettingsService::class)->get($c['clinic']->id,'billing.payment_methods',['cash'])]);
    }
    public function show(Request $r, int $id, BillingService $billing) { $c=app(ClinicAccessService::class)->authorize($r,'billing'); return response()->json(['data'=>$billing->visible($c)->with(['items','payments'])->findOrFail($id),'methods'=>app(ClinicSettingsService::class)->get($c['clinic']->id,'billing.payment_methods',['cash'])]); }
    public function fromSalon(Request $r, int $id, BillingService $billing) {
        $c=app(SalonBookingService::class)->context($r);
        app(ClinicAccessService::class)->authorize($r,'billing','billing.create');
        return response()->json(['data'=>$billing->fromSalon($c,$id)],201);
    }
    public function payment(Request $r, int $id, BillingService $billing) {
        $c=app(ClinicAccessService::class)->authorize($r,'billing','billing.payments');
        $data=$r->validate(['amount'=>'required|numeric|min:0.01|max:9999999999|decimal:0,2','method'=>'required|in:cash,card,mobile_money,bank_transfer','reference'=>'nullable|string|max:255','idempotency_key'=>'required|string|max:80']);
        return response()->json(['data'=>$billing->payment($c,$id,$data)]);
    }
}
