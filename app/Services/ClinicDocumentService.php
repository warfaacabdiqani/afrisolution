<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;
class ClinicDocumentService {
    public function data(int $tenant,string $kind): array {
        $settings=app(ClinicSettingsService::class);
        $profile=$settings->section($tenant,'general'); $documents=$settings->section($tenant,'documents');
        $branding=$settings->section($tenant,'branding');
        $asset=$branding[$kind.'_logo']??$branding['logo']??null; $logo=null;
        if($documents['show_logo'] && $asset && str_starts_with($asset['path'],"clinic-branding/$tenant/") && Storage::disk('patient_private')->exists($asset['path'])) $logo='data:'.$asset['mime'].';base64,'.base64_encode(Storage::disk('patient_private')->get($asset['path']));
        return compact('profile','documents','logo','kind');
    }
}
