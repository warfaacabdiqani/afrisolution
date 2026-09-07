<?php

namespace App\Http\Controllers;

use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Support\Facades\Gate;

class ClinicController extends Controller
{
    public function branches()
    {
        Gate::authorize('viewAny', Branch::class);

        $context = app(\App\Services\ClinicAccessService::class)->context(request());
        return BranchResource::collection(Branch::whereIn('id', $context['branches']->pluck('id'))->orderBy('name')->get());
    }

    public function branch(int $branch)
    {
        $model = Branch::findOrFail($branch);
        Gate::authorize('view', $model);
        $context = app(\App\Services\ClinicAccessService::class)->context(request());
        abort_unless($context['branches']->contains('id', $model->id), 403);

        return new BranchResource($model);
    }
}
