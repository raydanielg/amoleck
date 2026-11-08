<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Models\PatientDuplicateReview;
use Modules\PatientCare\Services\DuplicateDetectionService;
use Modules\PatientCare\Services\PatientAccessService;
use Modules\PatientCare\Services\PatientNumberGenerator;

class PatientController extends Controller
{
    public function index(Request $request, PatientAccessService $access)
    {
        $patients = $access->scopeFor($request->user(), Patient::query())
            ->with('primaryBranch:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('mrn', $s)
                ->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('middle_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->branch_id, fn ($q, $b) => $q->where('primary_branch_id', $b))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('admin/patients/index', [
            'patients' => $patients,
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'branch_id']),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/patients/form', [
            'patient' => null,
            'branches' => BranchContext::allowed(request()->user())->values(['id', 'name']),
            'duplicates' => [],
        ]);
    }

    public function store(Request $request, PatientNumberGenerator $mrn, DuplicateDetectionService $dup)
    {
        $data = $this->validatePatient($request);

        $duplicates = $dup->find($data + ['identification_number' => $request->input('identification_number')]);
        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_duplicates')) {
            return Inertia::render('admin/patients/form', [
                'patient' => null,
                'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
                'duplicates' => $duplicates->map(fn ($d) => ['id' => $d['patient']->id, 'mrn' => $d['patient']->mrn, 'name' => $d['patient']->full_name, 'matched_on' => $d['matched_on']]),
                'old' => $request->all(),
                'duplicateWarning' => true,
            ]);
        }

        $patient = DB::transaction(function () use ($data, $request, $mrn, $duplicates) {
            $patient = Patient::create($data + [
                'mrn' => $mrn->next(),
                'registered_by' => $request->user()->id,
                'registration_branch_id' => $data['registration_branch_id'] ?? BranchContext::current()?->id,
                'primary_branch_id' => $data['primary_branch_id'] ?? $data['registration_branch_id'] ?? BranchContext::current()?->id,
            ]);
            $this->syncRelations($patient, $request);
            foreach ($duplicates as $d) {
                PatientDuplicateReview::create(['patient_id' => $patient->id, 'matched_patient_id' => $d['patient']->id, 'matched_on' => implode(',', $d['matched_on'])]);
            }
            return $patient;
        });

        Audit::record('patient.registered', $patient, null, $patient->only(['mrn', 'first_name', 'last_name', 'status']), $patient->primary_branch_id);

        return redirect()->route('admin.patients.show', $patient)->with('toast', ['type' => 'success', 'message' => "Patient {$patient->mrn} registered."]);
    }

    public function show(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);

        return Inertia::render('admin/patients/show', [
            'patient' => $patient->load(['identifications', 'emergencyContacts', 'guardians', 'medicalEntries.recordedBy:id,name', 'documents.uploadedBy:id,name', 'consents', 'statusHistory', 'branchHistory', 'primaryBranch:id,name', 'registrationBranch:id,name', 'registeredBy:id,name',
                'conditions.recordedBy:id,name', 'surgicalHistories.recordedBy:id,name', 'medicationHistories.recordedBy:id,name',
                'familyHistories.recordedBy:id,name', 'socialHistories.recordedBy:id,name',
                'referrals.statusHistories.changedBy:id,name', 'referrals.receivingBranch:id,name',
                'branchTransfers.fromBranch:id,name', 'branchTransfers.toBranch:id,name', 'branchTransfers.transferredBy:id,name']),
            'activity' => AuditLog::where('entity_type', Patient::class)->where('entity_id', $patient->id)->latest('created_at')->limit(10)->get(['id', 'action', 'created_at']),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
        ]);
    }

    public function edit(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);

        return Inertia::render('admin/patients/form', [
            'patient' => $patient->load(['identifications', 'emergencyContacts', 'guardians']),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'duplicates' => [],
        ]);
    }

    public function update(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $this->validatePatient($request);
        $before = $patient->toArray();
        DB::transaction(function () use ($patient, $data, $request) {
            $patient->update($data);
            $this->syncRelations($patient, $request);
        });
        Audit::record('patient.updated', $patient, $before, $patient->fresh()->toArray(), $patient->primary_branch_id);

        return redirect()->route('admin.patients.show', $patient)->with('toast', ['type' => 'success', 'message' => 'Patient updated.']);
    }

    public function updateStatus(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(Patient::STATUSES)],
            'reason' => 'nullable|string|max:255',
        ]);
        $perm = match ($data['status']) { 'archived' => 'patientcare.patients.archive', 'active' => 'patientcare.patients.restore', default => 'patientcare.patients.deactivate' };
        abort_unless($request->user()->can($perm), 403);

        $before = $patient->status;
        DB::transaction(function () use ($patient, $data, $before, $request) {
            $patient->update(['status' => $data['status']]);
            $patient->statusHistory()->create(['from_status' => $before, 'to_status' => $data['status'], 'reason' => $data['reason'] ?? null, 'changed_by' => $request->user()->id]);
        });
        Audit::record("patient.{$data['status']}", $patient, ['status' => $before], ['status' => $data['status']], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Status updated.']);
    }

    public function associateBranch(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['branch_id' => 'required|exists:branches,id', 'set_primary' => 'boolean']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);

        $patient->branches()->syncWithoutDetaching([$data['branch_id'] => ['event' => 'associated', 'recorded_by' => $request->user()->id]]);
        if ($request->boolean('set_primary')) {
            $patient->update(['primary_branch_id' => $data['branch_id']]);
        }
        Audit::record('patient.branch_associated', $patient, null, ['branch_id' => $data['branch_id']], (int) $data['branch_id']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Branch associated.']);
    }

    public function storeMedicalEntry(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['allergy', 'condition', 'medication', 'alert', 'note'])],
            'description' => 'required|string|max:1000',
            'source' => ['required', Rule::in(['patient_reported', 'clinician', 'document'])],
            'verified' => 'boolean',
        ]);
        $patient->medicalEntries()->create($data + ['recorded_by' => $request->user()->id]);
        Audit::record('patient.medical_entry_added', $patient, null, ['type' => $data['type']], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Medical entry added.']);
    }

    public function storeConsent(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['data_processing', 'information_sharing', 'photography', 'other'])],
            'status' => ['required', Rule::in(['granted', 'declined'])],
            'consented_at' => 'nullable|date',
        ]);
        $patient->consents()->create($data + ['recorded_by' => $request->user()->id, 'consented_at' => $data['consented_at'] ?? now()->toDateString()]);
        Audit::record('patient.consent_recorded', $patient, null, ['type' => $data['type'], 'status' => $data['status']], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Consent recorded.']);
    }

    public function withdrawConsent(Request $request, Patient $patient, int $consent, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['withdrawal_reason' => 'required|string|max:255']);
        $c = $patient->consents()->findOrFail($consent);
        $c->update(['status' => 'withdrawn', 'withdrawn_at' => now()->toDateString(), 'withdrawal_reason' => $data['withdrawal_reason']]);
        Audit::record('patient.consent_withdrawn', $patient, null, ['type' => $c->type], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Consent withdrawn.']);
    }

    public function storeDocument(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'category' => ['required', Rule::in(['referral', 'identification', 'medical_report', 'consent', 'other', 'laboratory_report', 'imaging_report', 'discharge_summary', 'surgical_report', 'medical_certificate'])],
            'title' => 'nullable|string|max:255',
            'document_date' => 'nullable|date',
            'description' => 'nullable|string|max:255',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        $path = $request->file('file')->store("patients/{$patient->id}");
        $patient->documents()->create([
            'category' => $data['category'], 'title' => $data['title'] ?? null,
            'document_date' => $data['document_date'] ?? null, 'description' => $data['description'] ?? null,
            'branch_id' => BranchContext::current()?->id,
            'path' => $path, 'original_name' => $request->file('file')->getClientOriginalName(),
            'mime' => $request->file('file')->getMimeType(), 'size' => $request->file('file')->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        Audit::record('patient.document_uploaded', $patient, null, ['category' => $data['category']], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Document uploaded.']);
    }

    public function downloadDocument(Request $request, Patient $patient, int $document, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $doc = $patient->documents()->findOrFail($document);
        abort_unless(Storage::exists($doc->path), 404);
        Audit::record('patient.document_downloaded', $patient, null, ['document_id' => $doc->id], $patient->primary_branch_id);

        return Storage::download($doc->path, $doc->original_name);
    }

    public function duplicates()
    {
        return Inertia::render('admin/patients/duplicates', [
            'reviews' => PatientDuplicateReview::with(['patient:id,mrn,first_name,last_name', 'matchedPatient' => fn ($q) => $q->select('id', 'mrn', 'first_name', 'last_name')])
                ->where('status', 'pending')->latest()->paginate(15),
        ]);
    }

    public function resolveDuplicate(Request $request, PatientDuplicateReview $review)
    {
        $data = $request->validate(['resolution' => ['required', Rule::in(['confirmed_duplicate', 'not_duplicate'])]]);
        $review->update(['status' => 'resolved', 'resolution' => $data['resolution'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        Audit::record('patient.duplicate_resolved', $review->patient, null, ['resolution' => $data['resolution']]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Review resolved.']);
    }

    public function timeline(Request $request, Patient $patient, PatientAccessService $access, \Modules\PatientCare\Services\TimelineService $tl)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);

        return Inertia::render('admin/patients/timeline', [
            'patient' => $patient->only(['id', 'mrn', 'first_name', 'last_name']),
            'events' => $tl->for($patient->load(['referrals', 'documents', 'conditions', 'statusHistory', 'branchTransfers.toBranch:id,name'])),
        ]);
    }

    public function transferBranch(Request $request, Patient $patient, PatientAccessService $access, \Modules\PatientCare\Services\BranchTransferService $svc)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['to_branch_id' => 'required|exists:branches,id', 'reason' => 'nullable|string|max:500']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['to_branch_id']), 403);
        $svc->transfer($patient, (int) $data['to_branch_id'], $data['reason'] ?? null, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Patient transferred (MRN preserved).']);
    }

    public function verifyDocument(Request $request, Patient $patient, int $document, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $doc = $patient->documents()->findOrFail($document);
        $doc->update(['verification_status' => 'verified', 'verified_by' => $request->user()->id]);
        Audit::record('patient.document_verified', $patient, null, ['document_id' => $doc->id], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Document verified.']);
    }

    public function replaceDocument(Request $request, Patient $patient, int $document, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $doc = $patient->documents()->findOrFail($document);
        $data = $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', 'reason' => 'nullable|string|max:255']);
        $path = $request->file('file')->store("patients/{$patient->id}");
        DB::transaction(function () use ($doc, $request, $path, $data) {
            $doc->versions()->create(['path' => $doc->path, 'original_name' => $doc->original_name, 'replaced_by' => $request->user()->id, 'reason' => $data['reason'] ?? null]);
            $doc->update(['path' => $path, 'original_name' => $request->file('file')->getClientOriginalName(), 'mime' => $request->file('file')->getMimeType(), 'size' => $request->file('file')->getSize(), 'uploaded_by' => $request->user()->id, 'verification_status' => 'unverified']);
        });
        Audit::record('patient.document_replaced', $patient, null, ['document_id' => $doc->id], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Document replaced (previous version preserved).']);
    }

    private function validatePatient(Request $request): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => ['nullable', Rule::in(Patient::GENDERS)],
            'date_of_birth' => 'nullable|date|before:today',
            'nationality' => 'nullable|string|max:100',
            'marital_status' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'alt_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'registration_branch_id' => 'nullable|exists:branches,id',
            'primary_branch_id' => 'nullable|exists:branches,id',
            'registration_source' => 'nullable|string|max:100',
            'referred_by' => 'nullable|string|max:255',
            'admin_notes' => 'nullable|string',
            'identification_type' => ['nullable', Rule::in(Patient::ID_TYPES)],
            'identification_number' => 'nullable|string|max:100',
            'identification_country' => 'nullable|string|max:100',
            'identification_expires_at' => 'nullable|date',
        ]);
    }

    private function syncRelations(Patient $patient, Request $request): void
    {
        if ($request->filled('identification_number')) {
            $patient->identifications()->updateOrCreate(
                ['type' => $request->input('identification_type', 'other'), 'number' => $request->input('identification_number')],
                ['issuing_country' => $request->input('identification_country'), 'expires_at' => $request->input('identification_expires_at')],
            );
        }
        if ($request->filled('emergency_name')) {
            $patient->emergencyContacts()->updateOrCreate(
                ['name' => $request->input('emergency_name')],
                ['relationship' => $request->input('emergency_relationship'), 'phone' => $request->input('emergency_phone'), 'alt_phone' => $request->input('emergency_alt_phone')],
            );
        }
        if ($request->filled('guardian_name')) {
            $patient->guardians()->updateOrCreate(
                ['name' => $request->input('guardian_name')],
                ['relationship' => $request->input('guardian_relationship'), 'phone' => $request->input('guardian_phone'), 'email' => $request->input('guardian_email'), 'address' => $request->input('guardian_address')],
            );
        }
        if ($patient->primary_branch_id) {
            $patient->branches()->syncWithoutDetaching([$patient->primary_branch_id => ['event' => 'primary', 'recorded_by' => $request->user()->id]]);
        }
    }
}
