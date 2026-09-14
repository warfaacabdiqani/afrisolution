<?php

namespace App\Services;

use App\Models\User;
use App\Support\SupportTicketOptions as Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportTicketService
{
    public function conversation(int $ticket): array
    {
        return [
            'messages' => DB::table('support_ticket_messages as m')
                ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                ->where('m.support_ticket_id', $ticket)->orderBy('m.id')
                ->get(['m.id', 'm.message', 'm.source', 'm.created_at', 'u.name as user_name']),
            'attachments' => DB::table('support_ticket_attachments as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.uploaded_by')
                ->where('a.support_ticket_id', $ticket)->orderBy('a.id')
                ->get(['a.id', 'a.message_id', 'a.original_name', 'a.mime_type', 'a.size', 'a.created_at', 'u.name as uploaded_by_name']),
        ];
    }

    public function create(int $tenant, ?int $branch, User $user, array $data, ?UploadedFile $file): array
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($tenant, $branch, $user, $data, $file, &$storedPath) {
                $id = DB::table('support_tickets')->insertGetId(collect($data)->only([
                    'subject', 'category', 'priority', 'description', 'current_page',
                    'steps_to_reproduce', 'expected_result', 'actual_result',
                ])->all() + [
                    'tenant_id' => $tenant, 'branch_id' => $branch, 'user_id' => $user->id,
                    'ticket_number' => 'pending-'.Str::uuid(), 'status' => Options::OPEN,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $number = 'SUP-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                // Preserve any legacy ticket numbers that used a tenant-local sequence.
                if (DB::table('support_tickets')->where('ticket_number', $number)->exists()) {
                    $number .= '-'.Str::ulid();
                }
                DB::table('support_tickets')->where('id', $id)->update(['ticket_number' => $number]);
                $message = $this->message($id, $user, $data['description'], Options::BUSINESS);
                if ($file) $this->storeAttachment($id, $user, $file, $message, $storedPath);
                app(PlatformService::class)->audit($user->id, 'support.ticket.created', 'tenant', $tenant, [
                    'ticket_id' => $id, 'ticket_number' => $number, 'category' => $data['category'], 'priority' => $data['priority'],
                ]);
                return ['ticket_id' => $id, 'ticket_number' => $number];
            });
        } catch (\Throwable $e) {
            if ($storedPath) Storage::disk('local')->delete($storedPath);
            throw $e;
        }
    }

    public function reply(int $id, User $user, string $message, string $source, ?UploadedFile $file = null): int
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($id, $user, $message, $source, $file, &$storedPath) {
                $ticket = DB::table('support_tickets')->where('id', $id)->lockForUpdate()->firstOrFail();
                abort_if($ticket->status === Options::CLOSED, 422, 'This ticket is closed. Platform support must reopen it before replies can be added.');
                $messageId = $this->message($id, $user, $message, $source);
                if ($file) $this->storeAttachment($id, $user, $file, $messageId, $storedPath);
                $changes = ['updated_at' => now()];
                if ($source === Options::BUSINESS) $changes['status'] = Options::IN_PROGRESS;
                DB::table('support_tickets')->where('id', $id)->update($changes);
                app(PlatformService::class)->audit($user->id,
                    $source === Options::PLATFORM ? 'support_ticket.admin_replied' : 'support.ticket.replied',
                    'tenant', $ticket->tenant_id, ['ticket_id' => $id, 'message_id' => $messageId],
                    isset($changes['status']) ? ['status' => $ticket->status] : [],
                    isset($changes['status']) ? ['status' => $changes['status']] : []);
                return $messageId;
            });
        } catch (\Throwable $e) {
            if ($storedPath) Storage::disk('local')->delete($storedPath);
            throw $e;
        }
    }

    public function update(int $id, User $actor, string $field, string $value): void
    {
        DB::transaction(function () use ($id, $actor, $field, $value) {
            $ticket = DB::table('support_tickets')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($field === 'status' && ($value === Options::CLOSED || $ticket->status === Options::CLOSED)) {
                abort_unless($actor->hasPlatformPermission('support_tickets.close'), 403);
            }
            if ($ticket->$field === $value) return;
            DB::table('support_tickets')->where('id', $id)->update([$field => $value, 'updated_at' => now()]);
            $event = $field === 'status' && $value === Options::CLOSED ? 'closed' : $field.'_changed';
            app(PlatformService::class)->audit($actor->id, 'support_ticket.'.$event, 'tenant', $ticket->tenant_id,
                ['ticket_id' => $id, 'ticket_number' => $ticket->ticket_number], [$field => $ticket->$field], [$field => $value]);
        });
    }

    public function upload(int $id, User $user, UploadedFile $file): int
    {
        $path = null;
        try {
            return DB::transaction(function () use ($id, $user, $file, &$path) {
                $ticket = DB::table('support_tickets')->where('id', $id)->lockForUpdate()->firstOrFail();
                abort_if($ticket->status === Options::CLOSED, 422, 'This ticket is closed.');
                $attachment = $this->storeAttachment($id, $user, $file, null, $path);
                DB::table('support_tickets')->where('id', $id)->update(['updated_at' => now()]);
                return $attachment;
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function download(int $ticket, int $attachment)
    {
        $row = DB::table('support_ticket_attachments')->where('support_ticket_id', $ticket)->where('id', $attachment)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($row->file_path), 404, 'Attachment not found.');
        return Storage::disk('local')->download($row->file_path, $row->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function message(int $ticket, User $user, string $text, string $source): int
    {
        return DB::table('support_ticket_messages')->insertGetId([
            'support_ticket_id' => $ticket, 'user_id' => $user->id, 'message' => $text, 'source' => $source, 'created_at' => now(),
        ]);
    }

    private function storeAttachment(int $ticket, User $user, UploadedFile $file, ?int $message, ?string &$path): int
    {
        $path = $file->storeAs('support_attachments/'.$ticket, Str::uuid().'.'.$file->extension(), 'local');
        if (! $path) throw new \RuntimeException('Support attachment storage failed.');
        return DB::table('support_ticket_attachments')->insertGetId([
            'support_ticket_id' => $ticket, 'message_id' => $message, 'file_path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(), 'uploaded_by' => $user->id, 'created_at' => now(),
        ]);
    }
}
