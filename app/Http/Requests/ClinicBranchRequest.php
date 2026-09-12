<?php
namespace App\Http\Requests;
use App\Services\ClinicSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ClinicBranchRequest extends FormRequest {
    public function authorize(): bool { app(ClinicSettingsService::class)->authorize($this,'branches',true); return true; }
    public function rules(): array {
        $tenant=$this->session()->get('tenant_id');
        return ['tenant_id'=>'prohibited','name'=>['required','string','max:150',Rule::unique('branches')->where('tenant_id',$tenant)->ignore($this->route('branch'))],
            'code'=>['nullable','string','max:30','regex:/^[A-Za-z0-9-]+$/',Rule::unique('branches')->where('tenant_id',$tenant)->ignore($this->route('branch'))],
            'phone'=>'nullable|string|max:40','email'=>'nullable|email|max:255','address'=>'nullable|string|max:1000','city'=>'nullable|string|max:100',
            'timezone'=>'required|timezone','status'=>'required|in:active,inactive','is_main'=>'prohibited'];
    }
}
