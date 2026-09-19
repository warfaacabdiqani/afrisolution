<?php

namespace App\Http\Controllers;

use App\Services\Billing\BillingReportService;
use App\Services\ClinicAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingReportController extends Controller
{
    public function summary(Request $request, ClinicAccessService $access, BillingReportService $report)
    {
        $context = $access->authorize($request, 'billing');
        $filters = $request->validate([
            'from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'branch_id' => ['sometimes', 'required', Rule::in(['all', ...$context['branches']->pluck('id')->map(fn ($id) => (string) $id)->all()])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'tenant_id' => ['prohibited'],
        ]);
        if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }
        return response()->json(['data' => $report->summary($context, $filters)]);
    }
}
