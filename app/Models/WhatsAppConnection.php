<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConnection extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_connections';
    protected $fillable = ['business_account_id', 'phone_number_id', 'display_phone_number', 'display_name', 'access_token', 'status'];
    protected $hidden = ['access_token'];
    protected $casts = ['access_token' => 'encrypted', 'last_checked_at' => 'datetime'];
}
