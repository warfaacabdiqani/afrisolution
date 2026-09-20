<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_messages';
    protected $guarded = ['id', 'tenant_id'];
    protected $hidden = ['parameters'];
    protected $casts = ['parameters' => 'encrypted:array', 'requested_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime'];
}
