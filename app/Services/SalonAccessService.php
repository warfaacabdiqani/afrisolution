<?php
namespace App\Services;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalonAccessService
{
    public function authorize(Request $request, string $kind, ?string $action = null): array
    {
        $access = app(ClinicAccessService::class);
        $context = $access->context($request);
        if ($context['business_type']['slug'] !== 'beauty-salon') $access->deny('BUSINESS_MODULE_UNAVAILABLE','Salon modules are only available for Beauty Salon businesses.');
        $definition = config('salon.'.$kind);
        return $access->authorize($request, $definition['module'], $action ? $definition[$action] : null);
    }

    public function query(string $kind, array $context): Builder
    {
        $model = config('salon.'.$kind.'.model');
        $query = $model::query();
        if ($kind === 'clients') $query->where('branch_id',$context['branch']->id);
        elseif ($kind !== 'categories') $query->whereHas('branches',fn($q)=>$q->where('branches.id',$context['branch']->id));
        return $query;
    }

    public function branches(array $ids, array $context): void
    {
        if (!in_array((int)$context['branch']->id,$ids,true) || array_diff($ids,$context['branches']->pluck('id')->all())) {
            throw ValidationException::withMessages(['branch_ids'=>'Select authorized locations, including the current location.']);
        }
    }

    public function canEditLocations($record, string $kind, array $context): void
    {
        if (in_array($kind,['services','stylists']) && $record->branches()->whereNotIn('branches.id',$context['branches']->pluck('id'))->exists()) {
            app(ClinicAccessService::class)->deny('PERMISSION_DENIED','Managing this record requires access to all its locations.');
        }
    }

    public function memberBranches(int $user, array $branches, array $context): void
    {
        $member = DB::table('tenant_memberships')->join('users','users.id','=','tenant_memberships.user_id')
            ->where('tenant_id',$context['clinic']->id)->where('user_id',$user)->where('tenant_memberships.status','active')->where('users.status','active')
            ->select('tenant_memberships.*')->first();
        if (!$member) throw ValidationException::withMessages(['user_id'=>'Select an active staff account in this business.']);
        if (!$member->all_branches) {
            $allowed = DB::table('branch_memberships')->where('tenant_id',$context['clinic']->id)->where('membership_id',$member->id)->pluck('branch_id')->all();
            if (array_diff($branches,$allowed)) throw ValidationException::withMessages(['branch_ids'=>'The linked staff account must have access to every assigned location.']);
        }
    }
}
