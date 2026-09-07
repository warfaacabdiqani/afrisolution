<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class AuditIndexRequest extends FormRequest {
 public function authorize(): bool{return $this->user()?->hasPlatformPermission('audit.view')??false;}
 public function rules(): array{return ['search'=>['nullable','string','max:150'],'action'=>['nullable','string','max:100'],'module'=>['nullable','string','max:100'],'actor_id'=>['nullable','integer'],'tenant_id'=>['nullable','integer'],'date'=>['nullable',Rule::in(['today','yesterday','7','30'])],'per_page'=>['nullable',Rule::in([25,50,100])],'sort'=>['nullable',Rule::in(['created_at','actor_name','module','action'])],'direction'=>['nullable',Rule::in(['asc','desc'])],'page'=>['nullable','integer','min:1']];}
}
