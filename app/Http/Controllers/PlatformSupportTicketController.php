<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformSupportTicketIndexRequest;
use App\Services\SupportTicketService;
use App\Support\SupportTicketOptions as Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformSupportTicketController extends Controller
{
    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table('support_tickets as t')
            ->leftJoin('tenants as b', 'b.id', '=', 't.tenant_id')
            ->leftJoin('business_types as bt', 'bt.id', '=', 'b.business_type_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id');
    }

    private function columns(): array
    {
        return ['t.id', 't.ticket_number', 't.subject', 't.tenant_id', 't.category', 't.priority', 't.status',
            't.created_at', 't.updated_at', 'b.name as tenant_name', 'bt.id as business_type_id',
            'bt.name as business_type_name', 'bt.slug as business_type_slug', 'bt.category as business_type_category',
            'u.name as user_name', 'u.email as user_email'];
    }

    public function index(PlatformSupportTicketIndexRequest $request)
    {
        $data = $request->validated();
        $query = $this->query();
        if ($search = trim($data['search'] ?? '')) {
            $query->where(function ($q) use ($search) {
                foreach (['t.ticket_number', 't.subject', 'b.name', 'u.name', 'u.email'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }
        foreach (['tenant_id' => 't.tenant_id', 'business_type_id' => 'bt.id', 'status' => 't.status', 'priority' => 't.priority', 'category' => 't.category'] as $filter => $column) {
            if (isset($data[$filter])) $query->where($column, $data[$filter]);
        }
        if (! empty($data['date_from'])) $query->where('t.created_at', '>=', $data['date_from'].' 00:00:00');
        if (! empty($data['date_to'])) $query->where('t.created_at', '<', \Illuminate\Support\Carbon::parse($data['date_to'])->addDay()->startOfDay());
        $tickets = $query->orderByDesc('t.updated_at')->orderByDesc('t.id')->paginate(20, $this->columns());
        return response()->json(['data' => $tickets->items(), 'meta' => [
            'current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage(), 'total' => $tickets->total(),
        ]]);
    }

    public function stats()
    {
        $counts = DB::table('support_tickets')->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        return response()->json(['data' => [
            'open' => (int) ($counts[Options::OPEN] ?? 0),
            'in_progress' => (int) ($counts[Options::IN_PROGRESS] ?? 0),
            'waiting' => (int) ($counts[Options::WAITING] ?? 0),
            'resolved' => (int) ($counts[Options::RESOLVED] ?? 0),
            'closed' => (int) ($counts[Options::CLOSED] ?? 0), 'total' => (int) $counts->sum(),
        ]]);
    }

    public function options()
    {
        // Support permissions are sufficient; do not require unrelated tenant-management permissions.
        return response()->json(['data' => Options::metadata() + [
            'tenants' => DB::table('tenants')->orderBy('name')->get(['id', 'name', 'business_type_id']),
            'business_types' => DB::table('business_types')->orderBy('name')->get(['id', 'name', 'slug']),
        ]]);
    }

    public function show(Request $request, int $ticket, SupportTicketService $service)
    {
        $row = $this->query()->where('t.id', $ticket)->firstOrFail(array_merge($this->columns(), [
            't.branch_id', 't.user_id', 't.description', 't.current_page', 't.steps_to_reproduce', 't.expected_result', 't.actual_result',
        ]));
        $row->branch_name = DB::table('branches')->where('tenant_id', $row->tenant_id)->where('id', $row->branch_id)->value('name');
        $row->user_role = DB::table('tenant_memberships')->where('tenant_id', $row->tenant_id)->where('user_id', $row->user_id)->value('role');
        return response()->json(['data' => ['ticket' => $row] + $service->conversation($ticket)]);
    }

    public function reply(Request $request, int $ticket, SupportTicketService $service)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:2048'],
        ]);
        $message = $service->reply($ticket, $request->user(), $data['message'], Options::PLATFORM, $request->file('attachment'));
        return response()->json(['data' => ['message_id' => $message]], 201);
    }

    public function update(Request $request, int $ticket, string $field, SupportTicketService $service)
    {
        abort_unless($request->user()->hasPlatformPermission(
            $field === 'priority' ? 'support_tickets.manage_priority' : 'support_tickets.update'
        ), 403);
        $values = match ($field) {
            'status' => Options::STATUSES, 'priority' => Options::PRIORITIES, 'category' => Options::CATEGORIES,
        };
        $data = $request->validate([$field => ['required', Rule::in($values)]]);
        $service->update($ticket, $request->user(), $field, $data[$field]);
        return response()->noContent();
    }

    public function download(Request $request, int $ticket, int $attachment, SupportTicketService $service)
    {
        abort_unless($request->user()->hasPlatformPermission('support_tickets.view_attachments'), 403);
        DB::table('support_tickets')->where('id', $ticket)->firstOrFail();
        return $service->download($ticket, $attachment);
    }
}
