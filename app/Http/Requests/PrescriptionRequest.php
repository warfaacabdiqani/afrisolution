<?php
namespace App\Http\Requests;
use App\Services\ClinicAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class PrescriptionRequest extends FormRequest {
    public function authorize(): bool {
        app(ClinicAccessService::class)->authorize($this, 'prescriptions', $this->isMethod('POST') ? 'prescriptions.create' : 'prescriptions.update');
        return true;
    }
    public function rules(): array {
        return [
            'tenant_id'=>'prohibited','consultation_id'=>'prohibited',
            'branch_id'=>'required|integer','patient_id'=>'required|integer','doctor_id'=>'required|integer','appointment_id'=>'nullable|integer',
            'prescription_date'=>'required|date_format:Y-m-d','status'=>['required',Rule::in([\App\Support\PrescriptionStatus::DRAFT,\App\Support\PrescriptionStatus::ACTIVE])],
            'diagnosis'=>'nullable|string|max:4000','notes'=>'nullable|string|max:4000','internal_notes'=>'nullable|string|max:4000',
            'items'=>'required|array|min:1|max:50','items.*'=>'array:medication_id,medication_name,strength,dosage_form,dose,route,frequency,custom_frequency,duration,quantity,instructions','items.*.medication_id'=>'nullable|integer','items.*.medication_name'=>'required|string|max:255',
            'items.*.strength'=>'nullable|string|max:100','items.*.dosage_form'=>'nullable|string|max:100',
            'items.*.dose'=>'required|string|max:255','items.*.route'=>['required',Rule::in(array_keys(config('prescriptions.routes')))],
            'items.*.frequency'=>['required',Rule::in(array_keys(config('prescriptions.frequencies')))],
            'items.*.custom_frequency'=>'nullable|required_if:items.*.frequency,custom|string|max:255',
            'items.*.duration'=>'required|string|max:255','items.*.quantity'=>'nullable|numeric|min:0.01|max:9999999999',
            'items.*.instructions'=>'nullable|string|max:2000',
        ];
    }
}
