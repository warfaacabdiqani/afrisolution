<?php

namespace App\Http\Controllers;

use App\Services\ClinicAccessService;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SupportTicketService;
use App\Support\SupportTicketOptions as Options;
use Illuminate\Validation\Rule;

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

    private function businessArticles(array $context): \Illuminate\Support\Collection
    {
        return collect(config('support.articles'))->filter(fn ($article) =>
            !empty($context['business_modules']['clinical']) || in_array($article['slug'], ['switching-clinics', 'switching-branches', 'understanding-roles', 'add-staff', 'branch-access', 'clinic-settings'], true)
        );
    }

    public function articles(Request $request)
    {
        $context = $this->context($request, 'support.view');

        return response()->json([
            'data' => $this->businessArticles($context)->values()->all(),
        ]);
    }

    public function article(Request $request, string $slug)
    {
        $context = $this->context($request, 'support.view');

        $article = $this->businessArticles($context)->firstWhere('slug', $slug);

        abort_unless($article, 404, 'Help article not found.');

        $related = $this->businessArticles($context)
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
        $context = $this->context($request, 'support.view');

        return response()->json([
            'data' => collect(config('support.faqs'))->filter(fn ($faq) => !isset($faq['business_module']) || !empty($context['business_modules'][$faq['business_module']]))->values()->all(),
        ]);
    }

    public function search(Request $request)
    {
        $context = $this->context($request, 'support.view');

        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $normalized = strtolower($query);

        $results = $this->businessArticles($context)
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
            'category' => ['required', Rule::in(Options::CATEGORIES)],
            'priority' => ['required', Rule::in(Options::PRIORITIES)],
            'description' => ['required', 'string', 'max:6000'],
            'current_page' => ['nullable', 'string', 'max:255'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:3000'],
            'expected_result' => ['nullable', 'string', 'max:3000'],
            'actual_result' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:2048'],
        ]);

        return response()->json(['data' => app(SupportTicketService::class)->create(
            (int) $context['clinic']->id, $context['branch']->id ?? null,
            $request->user(), $data, $request->file('attachment')
        )], 201);
    }

    public function ticket(Request $request, int $ticket)
    {
        $context = $this->context($request, 'support.tickets.view_own');
        $viewClinic = $this->canViewClinic($context['permissions']);

        $query = $this->ticketQuery((int) $context['clinic']->id, (int) $request->user()->id, $viewClinic)
            ->where('st.id', $ticket);

        $ticketRow = $query->firstOrFail();

        return response()->json([
            'data' => [
                'ticket' => [
                    'id' => $ticketRow->id,
                    'tenant_name' => $context['clinic']->name,
                    'branch_name' => DB::table('branches')->where('tenant_id', $context['clinic']->id)->where('id', $ticketRow->branch_id)->value('name'),
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
                ...app(SupportTicketService::class)->conversation($ticket),
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

        if ((int) $ticketRow->user_id !== (int) $request->user()->id && ! $this->canViewClinic($context['permissions'])) {
            abort(403, 'You do not have access to this support ticket.');
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        return response()->json(['data' => ['message_id' => app(SupportTicketService::class)->reply(
            $ticket, $request->user(), $data['message'], Options::BUSINESS
        )]], 201);
    }

    public function attachments(Request $request, int $ticket)
    {
        $context = $this->context($request, 'support.tickets.create');

        $ticketRow = DB::table('support_tickets')
            ->where('tenant_id', $context['clinic']->id)
            ->where('id', $ticket)
            ->firstOrFail();

        if ((int) $ticketRow->user_id !== (int) $request->user()->id && ! $this->canViewClinic($context['permissions'])) {
            abort(403, 'You do not have access to this support ticket.');
        }

        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:2048'],
        ]);

        return response()->json(['data' => ['attachment_id' => app(SupportTicketService::class)->upload(
            $ticket, $request->user(), $request->file('attachment')
        )]], 201);
    }

    public function systemInfo(Request $request)
    {
        $context = $this->context($request, 'support.view');

        $context = app(ClinicAccessService::class)->context($request);
        $settings = app(SystemSettingsService::class);

        return response()->json([
            'data' => [
                'afri_clinic_version' => '1.0.0',
                'environment' => app()->environment(),
                'browser' => $request->header('User-Agent', 'Unknown Browser'),
                'clinic' => $context['clinic']->name,
                'branch' => $context['branch']?->name,
                'ticket_options' => Options::metadata(),
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

        $ticketRow = DB::table('support_tickets')
            ->where('tenant_id', $context['clinic']->id)
            ->where('id', $ticket)
            ->firstOrFail();

        if ((int) $ticketRow->user_id !== (int) $request->user()->id && ! $viewClinic) {
            abort(403, 'This attachment is not available to your account.');
        }

        return app(SupportTicketService::class)->download($ticket, $attachment);
    }

    private function canViewClinic(array $permissions): bool
    {
        return in_array('*', $permissions, true) || in_array('support.tickets.view_clinic', $permissions, true);
    }

}
