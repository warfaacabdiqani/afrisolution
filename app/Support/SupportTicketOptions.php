<?php

namespace App\Support;

class SupportTicketOptions
{
    public const OPEN = 'Open';
    public const IN_PROGRESS = 'In Progress';
    public const WAITING = 'Waiting for Customer';
    public const RESOLVED = 'Resolved';
    public const CLOSED = 'Closed';
    public const BUSINESS = 'business';
    public const PLATFORM = 'platform';
    public const STATUSES = [self::OPEN, self::IN_PROGRESS, self::WAITING, self::RESOLVED, self::CLOSED];
    public const PRIORITIES = ['Low', 'Normal', 'High', 'Urgent'];
    public const CATEGORIES = ['Technical Issue', 'Account / Access', 'Billing / Subscription', 'Feature Question', 'Bug Report', 'Other'];
    public const PERMISSIONS = [
        'support_tickets.view' => 'View support tickets across businesses',
        'support_tickets.reply' => 'Reply to support tickets',
        'support_tickets.update' => 'Update support ticket status and category',
        'support_tickets.close' => 'Close and reopen closed support tickets',
        'support_tickets.manage_priority' => 'Change support ticket priority',
        'support_tickets.view_attachments' => 'Download support ticket attachments',
    ];

    public static function metadata(): array
    {
        return ['statuses' => self::STATUSES, 'priorities' => self::PRIORITIES, 'categories' => self::CATEGORIES];
    }
}
