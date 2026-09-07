<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'action' => $this->action, 'actor_id' => $this->actor_id, 'actor_name' => $this->actor_name ?? null, 'subject_type' => $this->subject_type, 'subject_id' => $this->subject_id, 'metadata' => $this->metadata ? json_decode($this->metadata, true) : null, 'created_at' => $this->created_at];
    }
}
