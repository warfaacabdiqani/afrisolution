<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'action' => $this->action, 'actor_id' => $this->actor_id, 'subject_type' => $this->subject_type, 'subject_id' => $this->subject_id, 'created_at' => $this->created_at];
    }
}
