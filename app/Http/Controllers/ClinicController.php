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

        return BranchResource::collection(Branch::orderBy('name')->get());
    }

    public function branch(int $branch)
    {
        $model = Branch::findOrFail($branch);
        Gate::authorize('view', $model);

        return new BranchResource($model);
    }
}
