<?php

namespace App\Http\Controllers;

use App\Services\ClinicAccessService;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportController extends Controller
{
    private function context(Request $request, ?string $permission = null): array
    {
        return app(ClinicAccessService::class)->authorize($request, 'support', $permission);
    }

    private function ticketQuery(int $tenantId, int $userId, bool $viewClinic): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('support_tickets as st')
            ->leftJoin('users as u', 'u.id', '=', 'st.user_id')
            ->where('st.tenant_id', $tenantId);

        if (! $viewClinic) {
            $query->where('st.user_id', $userId);
        }

        return $query->select(
            'st.*',
            'u.name as user_name',
            'u.email as user_email'
        );
    }

    public function articles(Request $request)
    {
        $this->context($request, 'support.view');

        return response()->json([
            'data' => config('support.articles'),
        ]);
    }

    public function article(Request $request, string $slug)
    {
        $this->context($request, 'support.view');

        $article = collect(config('support.articles'))->firstWhere('slug', $slug);

        abort_unless($article, 404, 'Help article not found.');

        $related = collect(config('support.articles'))
            ->filter(fn ($item) => in_array($item['slug'], $article['related'], true))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'article' => $article,
                'related' => $related,
            ],
        ]);
    }

    public function faqs(Request $request)
    {
        $this->context($request, 'support.view');

        return response()->json([
            'data' => config('support.faqs'),
        ]);
    }

    public function search(Request $request)
    {
        $this->context($request, 'support.view');

        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $normalized = strtolower($query);

        $results = collect(config('support.articles'))
            ->filter(function ($article) use ($normalized) {
                $text = collect([
                    $article['title'],
                    $article['category'],
                    $article['summary'],
                    implode(' ', $article['keywords'] ?? []),
                    implode(' ', $article['content'] ?? []),
                ])->implode(' ');

                return str_contains(strtolower($text), $normalized);
            })
            ->values()
            ->all();

        return response()->json(['data' => $results]);
    }

    public function tickets(Request $request)
    {
        $context = $this->context($request, 'support.tickets.view_own');
        $viewClinic = $this->canViewClinic($context['permissions']);

        $query = $this->ticketQuery((int) $context['clinic']->id, (int) $request->user()->id, $viewClinic)
            ->orderByDesc('st.id');

        return response()->json([
            'data' => $query->get()->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'category' => $ticket->category,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ];
            }),
        ]);
    }

    public function storeTicket(Request $request)
    {
        $context = $this->context($request, 'support.tickets.create');

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['Technical Issue', 'Account / Access', 'Billing / Subscription', 'Feature Question', 'Bug Report', 'Other'])],
            'priority' => ['required', Rule::in(['Low', 'Normal', 'High', 'Urgent'])],
            'description' => ['required', 'string', 'max:6000'],
            'current_page' => ['nullable', 'string', 'max:255'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:3000'],
            'expected_result' => ['nullable', 'string', 'max:3000'],
            'actual_result' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:2048'],
        ]);

        $tenantId = (int) $context['clinic']->id;
        $branchId = $context['branch']->id ?? null;

        $ticketNumber = $this->generateTicketNumber($tenantId);

        $ticketId = DB::table('support_tickets')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $request->user()->id,
            'ticket_number' => $ticketNumber,
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'Open',
            'description' => $data['description'],
            'current_page' => $data['current_page'] ?? null,
            'steps_to_reproduce' => $data['steps_to_reproduce'] ?? null,
            'expected_result' => $data['expected_result'] ?? null,
            'actual_result' => $data['actual_result'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messageId = DB::table('support_ticket_messages')->insertGetId([
            'support_ticket_id' => $ticketId,
            'user_id' => $request->user()->id,
            'message' => $data['description'],
            'created_at' => now(),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->storeAs('support_attachments/' . $ticketId, $this->safeFileName($file), 'private');

            DB::table('support_ticket_attachments')->insert([
                'support_ticket_id' => $ticketId,
                'message_id' => $messageId,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
                'created_at' => now(),
            ]);
        }

        app(\App\Services\PlatformService::class)->audit($request->user()->id, 'support.ticket.created', 'tenant', $tenantId, [
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'category' => $data['category'],
            'priority' => $data['priority'],
        ]);

        return response()->json([
            'data' => ['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber],
        ], 201);
    }

    public function ticket(Request $request, int $ticket)
    {
        $context = $this->context($request, 'support.tickets.view_own');
        $viewClinic = $this->canViewClinic($context['permissions']);

        $query = $this->ticketQuery((int) $context['clinic']->id, (int) $request->user()->id, $viewClinic)
            ->where('st.id', $ticket);

        $ticketRow = $query->firstOrFail();

        $messages = DB::table('support_ticket_messages as stm')
            ->leftJoin('users as u', 'u.id', '=', 'stm.user_id')
            ->where('stm.support_ticket_id', $ticket)
            ->orderBy('stm.id')
            ->get([
                'stm.id',
                'stm.message',
                'stm.created_at',
                'u.name as user_name',
                'u.email as user_email',
            ]);

        $attachments = DB::table('support_ticket_attachments as sta')
            ->leftJoin('users as u', 'u.id', '=', 'sta.uploaded_by')
            ->where('sta.support_ticket_id', $ticket)
            ->get([
                'sta.id',
                'sta.original_name',
                'sta.mime_type',
                'sta.size',
                'sta.created_at',
                'u.name as uploaded_by_name',
            ]);

        return response()->json([
            'data' => [
                'ticket' => [
                    'id' => $ticketRow->id,
                    'ticket_number' => $ticketRow->ticket_number,
                    'subject' => $ticketRow->subject,
                    'category' => $ticketRow->category,
                    'priority' => $ticketRow->priority,
                    'status' => $ticketRow->status,
                    'description' => $ticketRow->description,
                    'current_page' => $ticketRow->current_page,
                    'steps_to_reproduce' => $ticketRow->steps_to_reproduce,
                    'expected_result' => $ticketRow->expected_result,
                    'actual_result' => $ticketRow->actual_result,
                    'user_name' => $ticketRow->user_name,
                    'user_email' => $ticketRow->user_email,
                    'created_at' => $ticketRow->created_at,
                    'updated_at' => $ticketRow->updated_at,
                ],
                'messages' => $messages,
                'attachments' => $attachments,
            ],
        ]);
    }

    public function reply(Request $request, int $ticket)
    {
        $context = $this->context($request, 'support.tickets.view_own');

        $ticketRow = DB::table('support_tickets')
            ->where('tenant_id', $context['clinic']->id)
            ->where('id', $ticket)
            ->firstOrFail();

        if ($ticketRow->user_id !== $request->user()->id && ! $this->canViewClinic($context['permissions'])) {
            abort(403, 'You do not have access to this support ticket.');
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $messageId = DB::table('support_ticket_messages')->insertGetId([
            'support_ticket_id' => $ticket,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'created_at' => now(),
        ]);

        DB::table('support_tickets')->where('id', $ticket)->update([
            'status' => 'In Progress',
            'updated_at' => now(),
        ]);

        app(\App\Services\PlatformService::class)->audit($request->user()->id, 'support.ticket.replied', 'tenant', $context['clinic']->id, [
            'ticket_id' => $ticket,
        ]);

        return response()->json([
            'data' => ['message_id' => $messageId],
        ], 201);
    }

    public function attachments(Request $request, int $ticket)
    {
        $context = $this->context($request, 'support.tickets.create');

        $ticketRow = DB::table('support_tickets')
            ->where('tenant_id', $context['clinic']->id)
            ->where('id', $ticket)
            ->firstOrFail();

        if ($ticketRow->user_id !== $request->user()->id && ! $this->canViewClinic($context['permissions'])) {
            abort(403, 'You do not have access to this support ticket.');
        }

        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:2048'],
        ]);

        $file = $request->file('attachment');
        $path = $file->storeAs('support_attachments/' . $ticket, $this->safeFileName($file), 'private');

        $attachmentId = DB::table('support_ticket_attachments')->insertGetId([
            'support_ticket_id' => $ticket,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => ['attachment_id' => $attachmentId],
        ], 201);
    }

    public function systemInfo(Request $request)
    {
        $this->context($request, 'support.view');

        $context = app(ClinicAccessService::class)->context($request);
        $settings = app(SystemSettingsService::class);

        return response()->json([
            'data' => [
                'afri_clinic_version' => '1.0.0',
                'environment' => app()->environment(),
                'browser' => $request->header('User-Agent', 'Unknown Browser'),
                'clinic' => $context['clinic']->name,
                'branch' => $context['branch']->name,
                'user_role' => $context['role'],
                'subscription_plan' => $context['plan']['name'] ?? null,
                'current_page' => $request->query('page', '/app/support'),
            ],
        ]);
    }

    public function downloadAttachment(Request $request, int $ticket, int $attachment)
    {
        $context = $this->context($request, 'support.tickets.view_own');
        $viewClinic = $this->canViewClinic($context['permissions']);

        $row = DB::table('support_ticket_attachments')
            ->where('id', $attachment)
            ->where('support_ticket_id', $ticket)
            ->firstOrFail();

        $ticketRow = DB::table('support_tickets')
            ->where('tenant_id', $context['clinic']->id)
            ->where('id', $ticket)
            ->firstOrFail();

        if ($ticketRow->user_id !== $request->user()->id && ! $viewClinic) {
            abort(403, 'This attachment is not available to your account.');
        }

        abort_unless(Storage::disk('local')->exists($row->file_path), 404, 'Attachment not found.');

        return Storage::disk('local')->download($row->file_path, $row->original_name);
    }

    private function canViewClinic(array $permissions): bool
    {
        return in_array('*', $permissions, true) || in_array('support.tickets.view_clinic', $permissions, true);
    }

    private function generateTicketNumber(int $tenantId): string
    {
        $ticket = DB::table('support_tickets')
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $next = $ticket ? ((int) $ticket->id + 1) : 1;

        return 'SUP-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function safeFileName($file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $original);

        return ($safe ?: 'attachment') . '-' . now()->timestamp . '.' . $file->getClientOriginalExtension();
    }
}
