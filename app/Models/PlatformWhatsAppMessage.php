<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformWhatsAppMessage extends Model
{
    protected $table = 'platform_whatsapp_messages';
    protected $guarded = ['id'];
    protected $hidden = ['parameters'];
    protected $casts = ['parameters' => 'encrypted:array', 'requested_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime'];
}
