<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id'=>$this->id,'action'=>$this->action,'description'=>$this->description,'module'=>$this->module,'result'=>$this->result,'actor_id'=>$this->actor_id,'actor_name'=>$this->actor_display_name??$this->actor_name??null,'actor_email'=>$this->actor_display_email??$this->actor_email??null,'tenant_id'=>$this->tenant_id,'tenant_name'=>$this->tenant_name,'subject_type'=>$this->subject_type,'subject_id'=>$this->subject_id,'subject_name'=>$this->subject_name,'metadata'=>$this->metadata?json_decode($this->metadata,true):null,'old_values'=>$this->old_values?json_decode($this->old_values,true):null,'new_values'=>$this->new_values?json_decode($this->new_values,true):null,'ip_address'=>$this->ip_address,'user_agent'=>$this->user_agent,'request_method'=>$this->request_method,'request_url'=>$this->request_url,'created_at'=>$this->created_at];
    }
}
