<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformWhatsAppConnection extends Model
{
    protected $table = 'platform_whatsapp_connections';
    protected $guarded = ['id'];
    protected $hidden = ['access_token'];
    protected $casts = ['access_token' => 'encrypted'];
}
