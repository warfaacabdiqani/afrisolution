<?php
namespace App\Support;

final class PrescriptionStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const PENDING = 'pending';
    public const PARTIALLY_DISPENSED = 'partially_dispensed';
    public const DISPENSED = 'dispensed';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
}
