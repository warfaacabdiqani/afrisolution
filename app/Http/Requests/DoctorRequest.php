<?php
namespace App\Http\Requests;
use App\Services\ClinicAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
class DoctorRequest extends FormRequest {
    public function authorize(): bool { app(ClinicAccessService::class)->authorize($this, 'doctors', $this->isMethod('POST') ? 'doctors.create' : 'doctors.update'); return true; }
    public function rules(): array {
        $tenant = $this->session()->get('tenant_id');
        $rules = [
            'first_name' => ['required','string','max:100'], 'middle_name' => ['nullable','string','max:100'], 'last_name' => ['required','string','max:100'],
            'gender' => ['nullable',Rule::in(['male','female'])], 'phone' => ['nullable','string','max:40','regex:/^[+0-9() .\-]{5,40}$/'], 'email' => ['nullable','email','max:255'],
            'license_number' => ['nullable','string','max:100'], 'qualification' => ['nullable','string','max:255'], 'consultation_fee' => ['nullable','numeric','min:0','max:9999999999.99'], 'notes' => ['nullable','string','max:5000'],
            'primary_branch_id' => ['required','integer',Rule::exists('branches','id')->where('tenant_id',$tenant)],
            'branch_ids' => ['sometimes','array','max:100'], 'branch_ids.*' => ['integer','distinct',Rule::exists('branches','id')->where('tenant_id',$tenant)],
            'specialty_ids' => ['required','array','min:1','max:20'], 'specialty_ids.*' => ['integer','distinct',Rule::exists('specialties','id')->where('tenant_id',$tenant)],
            'status' => ['sometimes',Rule::in(['active','inactive'])], 'availability_status' => ['required',Rule::in(['available','in_consultation','unavailable','on_leave'])],
            'account_mode' => ['sometimes',Rule::in(['none','existing','create'])], 'user_id' => ['required_if:account_mode,existing','nullable','integer',Rule::exists('tenant_memberships','user_id')->where('tenant_id',$tenant)->where('status','active')],
            'account_email' => ['required_if:account_mode,create','nullable','email','max:255','unique:users,email'],
            'password' => ['required_if:account_mode,create','nullable','confirmed',Password::min(12)->mixedCase()->numbers()],
            'account_permissions' => ['sometimes','array'], 'account_permissions.*' => ['string',Rule::in(config('clinic.roles.doctor'))],
            'confirm_duplicate' => ['sometimes','boolean'],
        ];
        foreach (['id','tenant_id','doctor_number','created_by','updated_by'] as $field) $rules[$field] = ['prohibited'];
        return $rules;
    }
}
