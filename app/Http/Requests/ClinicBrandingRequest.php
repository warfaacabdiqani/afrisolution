<?php
namespace App\Http\Requests;
use App\Services\ClinicSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ClinicBrandingRequest extends FormRequest {
    public function authorize(): bool { app(ClinicSettingsService::class)->authorize($this,'branding',true); return true; }
    public function rules(): array { return ['tenant_id'=>'prohibited','asset'=>['required',Rule::in(['logo','small_logo','receipt_logo','prescription_logo','invoice_logo','stamp'])],
        'file'=>'required|file|image|mimes:png,jpg,jpeg,webp|extensions:png,jpg,jpeg,webp|max:2048|dimensions:max_width=4000,max_height=4000']; }
}
