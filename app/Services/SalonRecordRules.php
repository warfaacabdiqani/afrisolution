<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalonRecordRules
{
    public function validate(Request $request, string $kind, array $context, ?int $id): array
    {
        $tenant = $context['clinic']->id;
        $rules = ['tenant_id'=>'prohibited','id'=>'prohibited','branch_id'=>'prohibited','client_number'=>'prohibited','staff_number'=>'prohibited','created_by'=>'prohibited','updated_by'=>'prohibited'];
        $status = ['required',Rule::in(['active','inactive'])];
        $locations = ['branch_ids'=>'required|array|min:1','branch_ids.*'=>'required|integer|distinct'];
        if ($kind === 'clients') $rules += [
            'first_name'=>'required|string|max:100','middle_name'=>'nullable|string|max:100','last_name'=>'required|string|max:100',
            'gender'=>['nullable',Rule::in(['female','male','other','prefer_not_to_say'])],'date_of_birth'=>'nullable|date_format:Y-m-d|before_or_equal:today',
            'phone'=>'nullable|string|max:40','email'=>'nullable|email|max:255','address'=>'nullable|string|max:2000','notes'=>'nullable|string|max:5000','status'=>$status,
            'preferred_stylist_id'=>['nullable','integer',Rule::exists('salon_staff_profiles','id')->where('tenant_id',$tenant)->where('status','active')],
        ];
        if ($kind === 'stylists') $rules += $locations + [
            'user_id'=>['required','integer',Rule::unique('salon_staff_profiles','user_id')->where('tenant_id',$tenant)->ignore($id)],
            'display_name'=>'required|string|max:150','title'=>'nullable|string|max:100','bio'=>'nullable|string|max:5000','status'=>$status,
            'commission_type'=>['nullable',Rule::in(['percentage','fixed'])],
            'commission_value'=>['nullable','required_with:commission_type','numeric','min:0','max:'.($request->input('commission_type')==='percentage'?'100':'9999999999.99')],
        ];
        if ($kind === 'categories') $rules += [
            'name'=>['required','string','max:100',Rule::unique('service_categories','name')->where('tenant_id',$tenant)->ignore($id)],
            'description'=>'nullable|string|max:2000','sort_order'=>'required|integer|between:0,100000','status'=>$status,
        ];
        if ($kind === 'services') $rules += $locations + [
            'service_category_id'=>['required','integer',Rule::exists('service_categories','id')->where('tenant_id',$tenant)->where('status','active')],
            'name'=>'required|string|max:150','code'=>['nullable','string','max:40',Rule::unique('salon_services','code')->where('tenant_id',$tenant)->ignore($id)],
            'description'=>'nullable|string|max:5000','duration_minutes'=>'required|integer|between:5,1440','price'=>'required|numeric|between:0,9999999999.99','status'=>$status,
            'requires_deposit'=>'required|boolean','deposit_amount'=>['nullable',Rule::requiredIf($request->boolean('requires_deposit')),'numeric','min:0','lte:price'],
            'stylist_ids'=>'present|array','stylist_ids.*'=>['integer','distinct',Rule::exists('salon_staff_profiles','id')->where('tenant_id',$tenant)->where('status','active')],
        ];
        return $request->validate($rules);
    }
}
