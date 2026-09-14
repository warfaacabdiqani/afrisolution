<?php

namespace App\Http\Controllers;

use App\Models\BusinessType;
use App\Services\BusinessCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessCodeController extends Controller
{
    public function preview(Request $request, BusinessCodeGenerator $codes)
    {
        $data = $request->validate(['business_type_id' => ['required', 'integer', Rule::exists('business_types', 'id')->where('status', 'active')]]);
        return response()->json(['code' => $codes->preview(BusinessType::findOrFail($data['business_type_id']))]);
    }

    public function examples(Request $request, BusinessCodeGenerator $codes)
    {
        $defaults = $codes->settings();
        $prefix = $request->input('code_prefix', $defaults['code_prefix']);
        $request->merge(['code_prefix' => is_string($prefix) ? strtoupper(trim($prefix)) : $prefix,
            'business_code_start' => $request->input('business_code_start', $defaults['business_code_start'])]);
        $settings = $request->validate(BusinessCodeGenerator::rules());
        return response()->json(['data' => BusinessType::where('status', 'active')->orderBy('id')->get()->map(fn ($type) => [
            'business_type_name' => $type->name, 'code' => $codes->preview($type, $settings),
        ])]);
    }
}
