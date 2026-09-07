<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
class PlatformUserRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPlatformPermission('users.manage') ?? false; }
    public function rules(): array
    {
        $user=$this->route('user'); $creating=$this->isMethod('post');
        return ['name'=>['required','string','max:150'],'email'=>['required','email','max:255',Rule::unique('users')->ignore($user)],'status'=>['required',Rule::in(['active','inactive'])],'role_ids'=>['required','array','min:1'],'role_ids.*'=>['integer','exists:platform_roles,id'],'password'=>[$creating?'required':'nullable','confirmed',Password::min(12)->mixedCase()->numbers()],'tenant_id'=>['prohibited'],'is_platform_admin'=>['prohibited']];
    }
}
