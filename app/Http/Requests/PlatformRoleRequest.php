<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class PlatformRoleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPlatformPermission('users.manage') ?? false; }
    public function rules(): array
    {
        $role=$this->route('role');
        return ['name'=>['required','string','max:100'],'slug'=>['required','alpha_dash','max:100',Rule::unique('platform_roles')->ignore($role)],'description'=>['nullable','string','max:500'],'permissions'=>['required','array','min:1'],'permissions.*'=>['string',Rule::exists('platform_permissions','name')]];
    }
}
