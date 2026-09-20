<?php

namespace App\Services;

use RuntimeException;

class MetaWhatsAppException extends RuntimeException
{
    public function __construct(public readonly string $safeCode, public readonly bool $temporary)
    {
        parent::__construct('Meta WhatsApp request failed: '.$safeCode);
    }
}
