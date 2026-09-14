<?php
namespace App\Services;
use App\Models\{SalonStaffProfile,Tenant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalonRecordService
{
    public function save(string $kind, array $data, array $context, ?int $id = null)
    {
        return DB::transaction(function() use($kind,$data,$context,$id) {
            // Serialize numbering, quota checks and salon relation changes for this tenant.
            $tenant = Tenant::lockForUpdate()->findOrFail($context['clinic']->id);
            $access = app(SalonAccessService::class);
            $definition = config('salon.'.$kind); $class = $definition['model'];
            $record = $id ? $access->query($kind,$context)->lockForUpdate()->findOrFail($id) : new $class;
            if ($id) $access->canEditLocations($record,$kind,$context);
            if ($id && $record->status === 'archived') throw ValidationException::withMessages(['status'=>'Archived records cannot be edited.']);
            if (!$id && in_array($kind,['clients','services'])) {
                $column = $kind==='clients'?'client_limit':'service_limit';
                $limit = DB::table('subscriptions')->join('plans','plans.id','=','subscriptions.plan_id')->where('tenant_id',$tenant->id)->value('plans.'.$column);
                if ($limit !== null && $class::count() >= $limit) throw ValidationException::withMessages(['plan'=>'The plan limit for '.$kind.' has been reached. Archived records count toward this limit.']);
            }
            $branches = array_map('intval',$data['branch_ids'] ?? []);
            if (in_array($kind,['stylists','services'])) $access->branches($branches,$context);
            if ($kind === 'stylists') {
                if ($id && (int)$record->user_id !== (int)$data['user_id']) throw ValidationException::withMessages(['user_id'=>'The linked staff account cannot be changed.']);
                $access->memberBranches((int)$data['user_id'],$branches,$context);
                // Do not leave existing clients or services referring to an unavailable location.
                if ($id) {
                    $removed = $record->branches()->pluck('branches.id')->diff($branches);
                    if ($removed->isNotEmpty() && (DB::table('salon_clients')->where('tenant_id',$tenant->id)->where('preferred_stylist_id',$id)->whereIn('branch_id',$removed)->exists() || $record->services()->whereHas('branches',fn($q)=>$q->whereIn('branches.id',$removed))->exists())) throw ValidationException::withMessages(['branch_ids'=>'Remove client preferences and service assignments before removing these locations.']);
                }
                if (empty($data['commission_type'])) $data['commission_value'] = null;
            }
            if ((!empty($data['preferred_stylist_id']) || !empty($data['stylist_ids'])) && empty($context['features']['salon_staff'])) app(ClinicAccessService::class)->deny('PLAN_FEATURE_UNAVAILABLE','Stylist assignment requires the Salon Stylists plan feature.');
            if ($kind === 'clients' && !empty($data['preferred_stylist_id'])) {
                $stylist = $access->query('stylists',$context)->where('status','active')->find($data['preferred_stylist_id']);
                if (!$stylist) throw ValidationException::withMessages(['preferred_stylist_id'=>'Select an active stylist in the current location.']);
                $access->memberBranches($stylist->user_id,[$context['branch']->id],$context);
            }
            if ($kind === 'services') {
                foreach ($data['stylist_ids'] as $stylistId) {
                    $stylist = SalonStaffProfile::where('status','active')->findOrFail($stylistId);
                    $assigned = $stylist->branches()->pluck('branches.id')->all();
                    $intersection = array_values(array_intersect($branches,$assigned));
                    if (!$intersection || array_diff($assigned,$context['branches']->pluck('id')->all())) throw ValidationException::withMessages(['stylist_ids'=>'Each stylist must be visible to you and available at a service location.']);
                    $access->memberBranches($stylist->user_id,$intersection,$context);
                }
                if (!$data['requires_deposit']) $data['deposit_amount'] = null;
            }
            // Recheck uniqueness under the tenant lock so concurrent submissions return validation errors.
            foreach (match($kind) { 'stylists'=>['user_id'], 'services'=>['code'], 'categories'=>['name'], default=>[] } as $field) {
                if (!empty($data[$field]) && $class::where($field,$data[$field])->when($id,fn($q)=>$q->where('id','!=',$id))->exists()) throw ValidationException::withMessages([$field=>'This value is already used in this business.']);
            }
            $record->fill(collect($data)->except(['branch_ids','stylist_ids','status'])->all());
            $record->status = $data['status'];
            if (!$id && in_array($kind,['clients','stylists'])) {
                $sequence = $kind==='clients'?'client_sequence':'salon_staff_sequence';
                $tenant->increment($sequence);
                $record->{$kind==='clients'?'client_number':'staff_number'} = ($kind==='clients'?'CLI-':'STY-').str_pad($tenant->$sequence,6,'0',STR_PAD_LEFT);
            }
            if ($kind==='clients') { if (!$id) { $record->branch_id=$context['branch']->id; $record->created_by=request()->user()->id; } $record->updated_by=request()->user()->id; }
            $record->save();
            if (in_array($kind,['stylists','services'])) $record->branches()->syncWithPivotValues($branches,['tenant_id'=>$tenant->id]);
            if ($kind==='services') $record->stylists()->syncWithPivotValues($data['stylist_ids'],['tenant_id'=>$tenant->id]);
            $this->audit($kind,$record,$id?'updated':'created',$context);
            return $record->load($definition['relations']);
        },3);
    }

    public function archive(string $kind, int $id, array $context)
    {
        return DB::transaction(function() use($kind,$id,$context) {
            Tenant::lockForUpdate()->findOrFail($context['clinic']->id);
            $access = app(SalonAccessService::class);
            $record = $access->query($kind,$context)->lockForUpdate()->findOrFail($id);
            $access->canEditLocations($record,$kind,$context);
            if ($record->status !== 'archived') {
                $record->status='archived';
                if ($kind==='clients') $record->updated_by=request()->user()->id;
                $record->save(); $this->audit($kind,$record,'archived',$context);
            }
            return $record->load(config('salon.'.$kind.'.relations'));
        },3);
    }

    private function audit(string $kind, $record, string $action, array $context): void
    {
        app(PlatformService::class)->audit(request()->user()->id,config('salon.'.$kind.'.event').'.'.$action,'tenant',$context['clinic']->id,
            ['salon_entity'=>$kind,'record_id'=>$record->id,'branch_id'=>$context['branch']->id]);
    }
}
