<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Tenant;
use App\Services\ClinicAccessService;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientDocumentController extends Controller
{
    public function index(Request $request, ClinicAccessService $access, int $patient)
    {
        $access->authorize($request, 'patients', 'patients.documents.view');
        return response()->json(['data' => Patient::findOrFail($patient)->documents()->whereNull('archived_at')->latest('id')->paginate(25)]);
    }
    public function store(Request $request, ClinicAccessService $access, PatientService $service, int $patient)
    {
        $context = $access->authorize($request, 'patients', 'patients.documents.upload');
        $model = Patient::findOrFail($patient);
        abort_if($model->status === 'archived', 422, 'Restore this patient before uploading documents.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'document_type' => ['required', Rule::in(['medical_report', 'referral', 'laboratory_report', 'identification', 'insurance', 'other'])],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png'],
            'tenant_id' => ['prohibited'], 'patient_id' => ['prohibited'],
        ]);
        $path = null;
        try {
            $document = DB::transaction(function () use ($context, $model, $data, $request, $service, &$path) {
                Tenant::lockForUpdate()->findOrFail($context['clinic']->id);
                $limit = DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')->where('tenant_id', $model->tenant_id)->value('storage_limit_gb');
                $file = $request->file('file');
                if ($limit !== null && PatientDocument::sum('size') + $file->getSize() > $limit * 1073741824) throw ValidationException::withMessages(['file' => 'Your clinic storage limit has been reached. Upgrade your subscription to upload more documents.']);
                $path = $file->store('patients/'.$model->tenant_id.'/'.$model->id, 'patient_private');
                $item = $model->documents()->create(collect($data)->except('file')->all() + ['path' => $path, 'mime' => $file->getMimeType(), 'extension' => $file->extension(), 'size' => $file->getSize(), 'recorded_by' => $request->user()->id]);
                $service->audit($model, 'patient.document.uploaded'); return $item;
            });
        } catch (\Throwable $e) { if ($path) Storage::disk('patient_private')->delete($path); throw $e; }
        return response()->json(['data' => $document], 201);
    }
    public function download(Request $request, ClinicAccessService $access, int $patient, int $document)
    {
        $access->authorize($request, 'patients', 'patients.documents.view');
        $item = Patient::findOrFail($patient)->documents()->whereNull('archived_at')->findOrFail($document);
        abort_unless(Storage::disk('patient_private')->exists($item->path), 404);
        return Storage::disk('patient_private')->download($item->path, 'patient-document-'.$item->id.'.'.$item->extension, ['Content-Type' => $item->mime, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
    public function archive(Request $request, ClinicAccessService $access, PatientService $service, int $patient, int $document)
    {
        $access->authorize($request, 'patients', 'patients.documents.delete');
        $model = Patient::findOrFail($patient);
        $item = $model->documents()->findOrFail($document);
        DB::transaction(function () use ($item, $model, $service) { $item->update(['archived_at' => now()]); $service->audit($model, 'patient.document.deleted'); });
        return response()->noContent();
    }
}
